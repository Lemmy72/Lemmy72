<?php

namespace Drupal\simplepay;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\simplepay\Form\FilterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a list controller for the simplepay entity type.
 */
class PaymentListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * Count of filtered entities.
   *
   * @var int
   *   Count of entities.
   */
  protected $count;

  /**
   * Constructs a new simplepayListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The request stack.
   */
  public function __construct(EntityTypeInterface $entity_type,
                              EntityStorageInterface $storage,
                              DateFormatterInterface $date_formatter,
                              RedirectDestinationInterface $redirect_destination,
                              FormBuilderInterface $form_builder,
                              EntityTypeManagerInterface $entity_type_manager,
                              RequestStack $request
  ) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
    $this->redirectDestination = $redirect_destination;
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('redirect.destination'),
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_query = $this->entityTypeManager->getStorage('simplepay')->getQuery();

    $query_string = $this->request->getCurrentRequest()->getQueryString();
    if (!empty($query_string)) {
      parse_str($query_string, $query_string_array);
    }

    $header = $this->buildHeader();

    $entity_query->pager(10);
    $entity_query->tableSort($header);

    if (!empty($query_string_array['status'])) {
      $entity_query->condition('status', $query_string_array['status']);
    }

    if (!empty($query_string_array['amount_from'])) {
      $entity_query->condition('charge_total', (int) $query_string_array['amount_from'], '>=');
    }

    if (!empty($query_string_array['amount_to'])) {
      $entity_query->condition('charge_total', (int) $query_string_array['amount_to'], '<=');
    }

    if (!empty($query_string_array['first_name'])) {
      $entity_query->condition('first_name', '%' . $query_string_array['first_name'] . '%', 'LIKE');
    }

    if (!empty($query_string_array['last_name'])) {
      $entity_query->condition('last_name', '%' . $query_string_array['last_name'] . '%', 'LIKE');
    }

    if (!empty($query_string_array['start_date'])) {
      $date = strtotime($query_string_array['start_date'] . ' 00:00:00');
      $entity_query->condition('created', $date, '>=');
    }

    if (!empty($query_string_array['end_date'])) {
      $date = strtotime($query_string_array['end_date'] . ' 23:59:59');
      $entity_query->condition('created', $date, '<=');
    }

    $ids = $entity_query->execute();

    $this->count = count($ids);

    return $this->storage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {

    $build['form'] = $this->formBuilder->getForm(FilterForm::class);

    $build['table'] = parent::render();

    $total = $this->getStorage()
      ->getQuery()
      ->count()
      ->execute();

    $build['summary']['#markup'] = $this->t('Total simplepays: @total, filtered: @filtered',
      ['@total' => $total, '@filtered' => $this->count]);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = [
      'data' => $this->t('ID'),
      'field' => 'id',
      'specifier' => 'id',
    ];
    $header['full_name'] = [
      'data' => $this->t('Name'),
      'field' => 'last_name',
      'specifier' => 'last_name',
    ];

    $header['address'] = $this->t('Address');
    $header['email'] = $this->t('Email address');
    $header['charge_total'] = [
      'data' => $this->t('Charge total'),
      'field' => 'charge_total',
      'specifier' => 'charge_total',
    ];
    $header['newsletter'] = [
      'data' => $this->t('Newsletter'),
      'field' => 'newsletter',
      'specifier' => 'newsletter',
    ];
    $header['status'] = [
      'data' => $this->t('Status'),
      'field' => 'status',
      'specifier' => 'status',
    ];
    $header['created'] = [
      'data' => $this->t('Created'),
      'field' => 'created',
      'specifier' => 'created',
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\simplepay\PaymentInterface $entity */
    $row['id'] = $entity->id();
    $row['full_name'] = $entity->getFullName();
    $row['address'] = $entity->getAddress();
    $row['email'] = $entity->getEmail();
    $row['charge_total'] = $entity->getChargeTotal();
    $row['newsletter'] = $entity->getNewsLetter() ? $this->t('Yes') : $this->t('No');
    $row['status'] = $entity->getStatus();
    $row['created'] = $this->dateFormatter->format($entity->getCreatedTime(), 'custom', 'D, d/m/y g:i A');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $destination = $this->redirectDestination->getAsArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }
    return $operations;
  }

}
