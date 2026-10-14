<?php

namespace Drupal\Tests\servicenow\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\servicenow\Plugin\ServicenowApiCall;
use Drupal\servicenow\Plugin\ServicenowUserLookup;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * Tests the ServiceNow user lookup.
 *
 * @coversDefaultClass \Drupal\servicenow\Plugin\ServicenowUserLookup
 * @group servicenow
 */
class ServicenowUserLookupTest extends UnitTestCase {

  const NOW = 1800000000;

  /**
   * The mocked API call service.
   *
   * @var \Drupal\servicenow\Plugin\ServicenowApiCall|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $apiCall;

  /**
   * The mocked user data service.
   *
   * @var \Drupal\user\UserDataInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $userData;

  /**
   * The lookup under test.
   *
   * @var \Drupal\servicenow\Plugin\ServicenowUserLookup
   */
  protected $lookup;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->apiCall = $this->createMock(ServicenowApiCall::class);
    $this->userData = $this->createMock(UserDataInterface::class);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(self::NOW);
    $channel = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->with('servicenow')->willReturn($channel);
    $this->lookup = new ServicenowUserLookup($this->apiCall, $this->userData, $time, $factory);
  }

  /**
   * Builds a mock account.
   *
   * @param bool $has_sys_id
   *   Whether the account has a sys_id.
   * @param string $email
   *   Current account email.
   *
   * @return \Drupal\user\UserInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The account.
   */
  protected function account(bool $has_sys_id = FALSE, string $email = '') {
    $list = $this->createMock(FieldItemListInterface::class);
    $list->method('isEmpty')->willReturn(!$has_sys_id);
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn(42);
    $account->method('getAccountName')->willReturn('jdoe');
    $account->method('getEmail')->willReturn($email);
    $account->method('get')->with('field_service_meow_sys_id')->willReturn($list);
    return $account;
  }

  /**
   * Builds a sys_user API response.
   */
  protected function snUser(array $fields): object {
    return (object) ['status' => 'success', 'result' => [(object) $fields]];
  }

  /**
   * Full record fixture.
   */
  protected function fullRecord(): array {
    return [
      'sys_id' => 'abc123',
      'name' => 'Jane Doe',
      'edu_status' => 'staff',
      'primaryphone' => '303-555-0100',
      'email' => 'jane@example.edu',
      'department' => (object) ['link' => 'x', 'value' => 'dept1'],
    ];
  }

  /**
   * Collects set() calls on an account.
   *
   * @return \ArrayObject
   *   Field name keyed values.
   */
  protected function captureSets($account): \ArrayObject {
    $sets = new \ArrayObject();
    $account->method('set')->willReturnCallback(function ($field, $value) use ($sets, $account) {
      $sets[$field] = $value;
      return $account;
    });
    return $sets;
  }

  /**
   * Tests isDue().
   *
   * @covers ::isDue
   * @dataProvider isDueProvider
   */
  public function testIsDue(?int $age, bool $has_sys_id, bool $expected): void {
    $this->userData->method('get')
      ->with('servicenow', 42, 'lookup_last')
      ->willReturn($age === NULL ? NULL : self::NOW - $age);
    $this->assertSame($expected, $this->lookup->isDue($this->account($has_sys_id)));
  }

  /**
   * Data provider for testIsDue().
   */
  public static function isDueProvider(): array {
    $day = 86400;
    return [
      'no stamp' => [NULL, TRUE, TRUE],
      'no stamp without sys_id' => [NULL, FALSE, TRUE],
      'sys_id 119 days' => [119 * $day, TRUE, FALSE],
      'sys_id 121 days' => [121 * $day, TRUE, TRUE],
      'no sys_id 29 days' => [29 * $day, FALSE, FALSE],
      'no sys_id 31 days' => [31 * $day, FALSE, TRUE],
    ];
  }

  /**
   * Tests a found record is mirrored onto the account.
   *
   * @covers ::update
   */
  public function testRecordFound(): void {
    $account = $this->account();
    $sets = $this->captureSets($account);
    $account->expects($this->once())->method('save');
    $account->expects($this->once())->method('setEmail')->with('jane@example.edu');
    $this->apiCall->method('apiCallMeMaybe')->willReturnCallback(function ($table, $query, $string, $close, $timeout) {
      $this->assertSame(ServicenowUserLookup::LOOKUP_TIMEOUT, $timeout);
      if ($table === 'sys_user') {
        $this->assertSame(['user_name' => 'jdoe'], $query);
        return $this->snUser($this->fullRecord());
      }
      $this->assertSame('cmn_department', $table);
      $this->assertSame(['sys_id' => 'dept1'], $query);
      return (object) ['status' => 'success', 'result' => [(object) ['name' => 'OIT']]];
    });
    $this->userData->expects($this->once())->method('set')
      ->with('servicenow', 42, 'lookup_last', self::NOW);

    $this->lookup->update($account);

    $this->assertSame('OIT', $sets['field_user_department']);
    $this->assertSame('Jane Doe', $sets['field_user_name']);
    $this->assertSame('staff', $sets['field_user_affiliation']);
    $this->assertSame('303-555-0100', $sets['field_user_phone']);
    $this->assertSame('abc123', $sets['field_service_meow_sys_id']);
  }

  /**
   * Tests empty SN values become NULL and sys_id is never cleared.
   *
   * @covers ::update
   */
  public function testEmptyValuesNullAndSysIdKept(): void {
    $account = $this->account(TRUE);
    $sets = $this->captureSets($account);
    $account->expects($this->once())->method('save');
    $this->apiCall->expects($this->once())->method('apiCallMeMaybe')
      ->willReturn($this->snUser([
        'sys_id' => '',
        'name' => '',
        'edu_status' => '',
        'primaryphone' => '',
        'email' => '',
        'department' => '',
      ]));
    $this->userData->expects($this->once())->method('set');

    $this->lookup->update($account);

    foreach (['field_user_department', 'field_user_name', 'field_user_affiliation', 'field_user_phone'] as $field) {
      $this->assertArrayHasKey($field, $sets->getArrayCopy());
      $this->assertNull($sets[$field], $field);
    }
    $this->assertArrayNotHasKey('field_service_meow_sys_id', $sets->getArrayCopy());
  }

  /**
   * Tests a department object with empty value clears the department.
   *
   * @covers ::update
   */
  public function testDepartmentObjectEmptyValue(): void {
    $account = $this->account();
    $sets = $this->captureSets($account);
    $this->apiCall->expects($this->once())->method('apiCallMeMaybe')
      ->willReturn($this->snUser(['department' => (object) ['value' => '']]));
    $this->lookup->update($account);
    $this->assertNull($sets['field_user_department']);
  }

  /**
   * Tests no record: stamp only.
   *
   * @covers ::update
   */
  public function testNoRecord(): void {
    $account = $this->account();
    $account->expects($this->never())->method('set');
    $account->expects($this->never())->method('save');
    $this->apiCall->method('apiCallMeMaybe')
      ->willReturn((object) ['status' => 'success', 'result' => []]);
    $this->userData->expects($this->once())->method('set')
      ->with('servicenow', 42, 'lookup_last', self::NOW);
    $this->lookup->update($account);
  }

  /**
   * Tests sys_user failure: no stamp, no save.
   *
   * @covers ::update
   */
  public function testSysUserFailure(): void {
    $account = $this->account();
    $account->expects($this->never())->method('set');
    $account->expects($this->never())->method('save');
    $this->apiCall->method('apiCallMeMaybe')->willReturn(NULL);
    $this->userData->expects($this->never())->method('set');
    $this->lookup->update($account);
  }

  /**
   * Tests a cmn_department failure keeps the existing department.
   *
   * @covers ::update
   */
  public function testDepartmentFailureKeepsExisting(): void {
    $account = $this->account();
    $sets = $this->captureSets($account);
    $account->expects($this->once())->method('save');
    $this->apiCall->method('apiCallMeMaybe')->willReturnCallback(function ($table) {
      return $table === 'sys_user' ? $this->snUser($this->fullRecord()) : NULL;
    });
    $this->userData->expects($this->once())->method('set');
    $this->lookup->update($account);
    $this->assertArrayNotHasKey('field_user_department', $sets->getArrayCopy());
    $this->assertSame('Jane Doe', $sets['field_user_name']);
  }

  /**
   * Tests an empty cmn_department result sets the department to NULL.
   *
   * @covers ::update
   */
  public function testDepartmentNotFoundSetsNull(): void {
    $account = $this->account();
    $sets = $this->captureSets($account);
    $this->apiCall->method('apiCallMeMaybe')->willReturnCallback(function ($table) {
      return $table === 'sys_user'
        ? $this->snUser($this->fullRecord())
        : (object) ['status' => 'success', 'result' => []];
    });
    $this->lookup->update($account);
    $this->assertArrayHasKey('field_user_department', $sets->getArrayCopy());
    $this->assertNull($sets['field_user_department']);
  }

  /**
   * Tests email is only filled when the account has none.
   *
   * @covers ::update
   * @dataProvider emailProvider
   */
  public function testEmail(string $existing, string $sn_email, ?string $expected): void {
    $account = $this->account(FALSE, $existing);
    if ($expected === NULL) {
      $account->expects($this->never())->method('setEmail');
    }
    else {
      $account->expects($this->once())->method('setEmail')->with($expected);
    }
    $this->apiCall->method('apiCallMeMaybe')
      ->willReturn($this->snUser(['email' => $sn_email]));
    $this->lookup->update($account);
  }

  /**
   * Data provider for testEmail().
   */
  public static function emailProvider(): array {
    return [
      'empty account, SN has email' => ['', 'new@example.edu', 'new@example.edu'],
      'account has email, not overwritten' => ['old@example.edu', 'new@example.edu', NULL],
      'both empty' => ['', '', NULL],
    ];
  }

}
