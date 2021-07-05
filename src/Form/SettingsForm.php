<?php

namespace Drupal\simplepay\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Simplepay form's settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'simplepay.settings';

  const FACULTIES = [
    'ÁJK gólyatábor',
    'ÁOK gólyatábor',
    'ÁOK-GYTK felsőbb éves gólyatábor',
    'BTK gólyatábor',
    'ETK gólyatábor',
    'GYTK gólyatábor',
    'KPVK gólyatábor',
    'KTK gólyatábor',
    'MIK gólyatábor',
    'MK gólyatábor',
    'MK felsőbb éves gólyatábor',
    'TTK gólyatábor',
    'TTK felsőbb éves gólyatábor',
  ];

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplepay_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $environment = $config->get('environment');
    $data = $config->get('data');
    $faculties = $config->get('faculties');

    $form['#tree'] = TRUE;

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t("Here you can set the Simplepay form related settings.") . '</p>',
    ];

    $form['environment'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t("Environment"),
      '#options' => [
        'test' => $this->t("Test"),
        'live' => $this->t("Live"),
      ],
      '#default_value' => $environment ?? NULL,
    ];

    $form['data']['huf_merchant'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t("HUF Merchant ID"),
      '#default_value' => $data['huf_merchant'] ?? NULL,
    ];

    $form['data']['huf_secret_key'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t("HUF Secret key"),
      '#description' => $this->t("Secret key for account ID (HUF)."),
      '#default_value' => $data['huf_secret_key'] ?? NULL,
    ];

    $form['data']['eur_merchant'] = [
      '#type' => 'textfield',
      '#title' => $this->t("EUR Merchant ID"),
      '#default_value' => $data['eur_merchant'] ?? NULL,
    ];

    $form['data']['eur_secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t("EUR Secret key"),
      '#description' => $this->t("Secret key for account ID (EUR)."),
      '#default_value' => $data['eur_secret_key'] ?? NULL,
    ];

    $form['data']['usd_merchant'] = [
      '#type' => 'textfield',
      '#title' => $this->t("USD Merchant ID"),
      '#default_value' => $data['usd_merchant'] ?? NULL,
    ];

    $form['data']['usd_secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t("USD Secret key"),
      '#description' => $this->t("Secret key for account ID (USD)."),
      '#default_value' => $data['usd_secret_key'] ?? NULL,
    ];

    $form['data']['response_success_url'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['article', 'landing_page'],
      ],
      '#required' => TRUE,
      '#title' => $this->t("Response - Success URL"),
      '#description' => $this->t("The URL where you wish to direct customers after a successful transaction (your Thank You URL)"),
      '#default_value' => !empty($data['response_success_url']) ? $this->entityTypeManager->getStorage('node')->load($data['response_success_url']) : NULL,
    ];

    $form['data']['response_fail_url'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['article'],
      ],
      '#required' => TRUE,
      '#title' => $this->t("Response - Fail URL"),
      '#description' => $this->t("The URL where you wish to direct customers after a declined or unsuccessful transaction (your Sorry URL)"),
      '#default_value' => !empty($data['response_fail_url']) ? $this->entityTypeManager->getStorage('node')->load($data['response_fail_url']) : NULL,
    ];

    $form['data']['response_cancel_url'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['article'],
      ],
      '#required' => TRUE,
      '#title' => $this->t("Response - Cancel URL"),
      '#description' => $this->t("The URL where you wish to direct customers after a canceled transaction (your Sorry URL)"),
      '#default_value' => !empty($data['response_cancel_url']) ? $this->entityTypeManager->getStorage('node')->load($data['response_cancel_url']) : NULL,
    ];

    $form['data']['response_timeout_url'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['article'],
      ],
      '#required' => TRUE,
      '#title' => $this->t("Response - Timeout URL"),
      '#description' => $this->t("The URL where you wish to direct customers after timeout"),
      '#default_value' => !empty($data['response_timeout_url']) ? $this->entityTypeManager->getStorage('node')->load($data['response_timeout_url']) : NULL,
    ];

    $form['data']['notification_email'] = [
      '#type' => 'email',
      '#required' => TRUE,
      '#title' => $this->t("Notification email address"),
      '#description' => $this->t("Send email to this email address about donation."),
      '#default_value' => $data['notification_email'] ?? NULL,
    ];

    $form['data']['email_content'] = [
      '#type' => 'text_format',
      '#format' => 'rich_text',
      '#required' => TRUE,
      '#title' => $this->t("Email Message"),
      '#description' => $this->t("This message will send to customer."),
      '#default_value' => $data['email_content'] ?? NULL,
    ];

    foreach (self::FACULTIES as $key => $title) {
      $form['faculties'][$key] = [
        '#type' => 'details',
        '#title' => $title,
      ];

      $form['faculties'][$key]['pst'] = [
        '#type' => 'number',
        '#title' => $this->t('SAP PST number'),
        '#required' => true,
        '#maxlength' => 10,
        '#default_value' => $faculties[$key]['pst'] ?? NULL,
      ];

      $form['faculties'][$key]['price'] = [
        '#type' => 'number',
        '#title' => $this->t('Price'),
        '#required' => true,
        '#maxlength' => 6,
        '#default_value' => $faculties[$key]['price'] ?? NULL,
      ];

      $form['faculties'][$key]['location'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Location'),
        '#required' => true,
        '#default_value' => $faculties[$key]['location'] ?? NULL,
      ];

      $form['faculties'][$key]['participants'] = [
        '#type' => 'number',
        '#title' => $this->t('Max participants'),
        '#required' => true,
        '#maxlength' => 3,
        '#default_value' => $faculties[$key]['participants'] ?? NULL,
      ];

      $form['faculties'][$key]['start_date'] = [
        '#type' => 'date',
        '#title' => $this->t('Start date'),
        '#required' => true,
        '#default_value' => $faculties[$key]['start_date'] ?? NULL,
      ];

      $form['faculties'][$key]['end_date'] = [
        '#type' => 'date',
        '#title' => $this->t('End date'),
        '#required' => true,
        '#default_value' => $faculties[$key]['end_date'] ?? NULL,
      ];

      $form['faculties'][$key]['apply_start_date'] = [
        '#type' => 'date',
        '#title' => $this->t('Apply start date'),
        '#required' => true,
        '#default_value' => $faculties[$key]['apply_start_date'] ?? NULL,
      ];

      $form['faculties'][$key]['apply_end_date'] = [
        '#type' => 'date',
        '#title' => $this->t('Apply end date'),
        '#required' => true,
        '#default_value' => $faculties[$key]['apply_end_date'] ?? NULL,
      ];

    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $values = $form_state->getValues();

    $values['data']['email_content'] = $values['data']['email_content']['value'];

    $this->configFactory->getEditable(static::SETTINGS)
      ->set('environment', $values['environment'])
      ->set('data', $values['data'])
      ->set('faculties', $values['faculties'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
