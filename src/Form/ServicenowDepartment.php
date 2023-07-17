<?php

namespace Drupal\servicenow\Form;

use Drupal\servicenow\Plugin\PrincessList;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\UserStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class ShortSheets.
 */
class ServicenowDepartment extends ConfigFormBase {

  /**
   * Servicenow call princess list.
   *
   * @var \Drupal\servicenow\Plugin\PrincessList
   */
  protected $princessList;

  /**
   * Object used to get request data, such as the hash.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Invoke account interface.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user load function.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs request stuff.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Access to the current request, including to session objects.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Load user entity.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   User Load function.
   */
  public function __construct(
    RequestStack $request_stack,
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    UserStorageInterface $user_storage,
    PrincessList $princess_list
  ) {
    $this->requestStack = $request_stack;
    $this->account = $account;
    $this->entityTypeManager = $entity_type_manager;
    $this->userStorage = $user_storage;
    $this->princessList = $princess_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $self = new self(
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('servicenow.princess.list')
    );
    return $self;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'servicenow_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'servicenow.servicenow_form',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_user = $this->account;
    $user_detail = $this->entityTypeManager->getStorage('user')->load($current_user->id());
    $user_dds_checkbox = $user_detail->get('field_dds')->getString();
    $current_user_sys_id = $user_detail->get('field_service_meow_sys_id')->getString();
    $princess_list = $this->princessList;
    $current_user_princess = $princess_list->getData()['users'][$current_user_sys_id] ?? NULL;
    $princess_list = $princess_list->getData()['departments'];
    $pl_departments = [];
    $pretty_princess = 0;

    if (isset($user_dds_checkbox)) {
      if ($user_dds_checkbox == 1) {
        foreach ($princess_list as $dept_id => $princess_dept) {
          $dept_name = $princess_dept;
          $pl_departments[$dept_id] = $dept_name;
        }
        $pretty_princess = 1;
      }
    }
    if (!isset($current_user_princess['request_group'][0]) && $pretty_princess == 0) {
      $form['no_dept'] = [
        '#markup' => 'An issue has occurred when attempting to log you in as a Dedicated Desktop Support customer. Please contact your DDS technician or the IT Service Center at 303-735-4357 (5-HELP) or help@colorado.edu for assistance. We apologize for the inconvenience.',
      ];
      return $form;
    }
    if (!isset($current_user_princess['request_group'][1]) && $pretty_princess == 0) {
      $department = $current_user_princess['request_group'][0];
      $this->forwardToWebform($department);
    }
    if (isset($current_user_princess['request_group'][1]) && $pretty_princess == 0) {
      $princess_depts = $princess_list;
      foreach ($current_user_princess['request_group'] as $set_dept) {
        $dept_id = $set_dept;
        $dept_name = $princess_depts[$dept_id];
        $pl_departments[$dept_id] = $dept_name;
      }
    }
    asort($pl_departments);

    $form['princess_department'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Department'),
      '#default_value' => '',
      '#description' => $this->t('Select the department you are representing.'),
      '#options' => $pl_departments,
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
    ];

    return $form;
  }

  /**
   * Implements form validation.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Probably don't need error check...
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Forward to webform with proper dept.
    $dept = $form_state->getValue('princess_department');
    $this->forwardToWebform($dept);

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  private function forwardToWebform($dept) {
    $forcedev = !empty($this->requestStack->getCurrentRequest()->get('forcedev')) ?
                Xss::filter($this->requestStack->getCurrentRequest()->get('forcedev')) :
                0;
    $url = Url::fromRoute('entity.node.canonical', ['node' => 16699]);
    $user = $this->userStorage->load($this->account->id());
    $profile_name = $user->field_user_name->value ?? '';
    $args['sn_hidden_name'] = $profile_name;
    $args['department'] = $dept;
    if ($forcedev == "true") {
      $args['forcedev'] = 'true';
    }
    $url->setOptions(['query' => $args]);
    $response = new RedirectResponse($url->toString());
    $response->send();
    exit;
  }

}
