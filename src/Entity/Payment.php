<?php

namespace Drupal\simplepay\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\simplepay\PaymentInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Payment entity class.
 *
 * @ContentEntityType(
 *   id = "payment",
 *   label = @Translation("Payment"),
 *   label_collection = @Translation("Payments"),
 *   handlers = {
 *     "view_builder" = "Drupal\simplepay\PaymentViewBuilder",
 *     "list_builder" = "Drupal\simplepay\PaymentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\simplepay\PaymentAccessControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "payment",
 *   admin_permission = "administer payment",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/pte/payment/{payment}",
 *     "delete-form" = "/admin/config/pte/payment/{payment}/delete",
 *     "collection" = "/admin/config/pte/payment/list"
 *   }
 * )
 */
class Payment extends ContentEntityBase implements PaymentInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   *
   * When a new Payment entity is created, set the uid entity reference to
   * the current user as the creator of the entity.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += ['uid' => \Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->getFullName() . ' ' . $this->getChargeTotal();
  }

  /**
   * {@inheritdoc}
   */
  public function getStreet(): string {
    return $this->get('street')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStreet(string $street): PaymentInterface {
    $this->set('street', $street);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCity(): string {
    return $this->get('city')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCity(string $city): PaymentInterface {
    $this->set('city', $city);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCounty() {
    return $this->get('county')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCounty(string $county): PaymentInterface {
    $this->set('county', $county);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCountry(): string {
    return $this->get('country')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCountry(string $country): PaymentInterface {
    $this->set('country', $country);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPostCode(): string {
    return $this->get('postcode')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPostCode(string $postcode): PaymentInterface {
    $this->set('postcode', $postcode);
    return $this;
  }

  /**
   * Gets Address.
   *
   * @return string
   *   Address/
   */
  public function getAddress(): string {
    $output = $this->getStreet() . ' ';
    $output .= $this->getCity() . ' ';
    $output .= $this->getPostCode() . ' ';
    $output .= $this->getCounty() ? $this->getCounty() . ' ' : '';
    $output .= $this->getCountry();
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title): PaymentInterface {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstName(): string {
    return $this->get('first_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFirstName(string $first_name): PaymentInterface {
    $this->set('first_name', $first_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastName(): string {
    return $this->get('last_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastName(string $last_name): PaymentInterface {
    $this->set('last_name', $last_name);
    return $this;
  }

  /**
   * Get Full Name.
   *
   * @return string
   *   Full name
   */
  public function getFullName(): string {
    $output = $this->getTitle() ? $this->getTitle() . ' ' : '';
    $output .= $this->getFirstName() . ' ' . $this->getLastName();
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): string {
    return $this->get('email')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(string $email): PaymentInterface {
    $this->set('email', $email);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhone(): string {
    return $this->get('phone')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPhone(string $phone): PaymentInterface {
    $this->set('phone', $phone);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChargeTotal(): float {
    return $this->get('charge_total')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setChargeTotal(float $charge_total): PaymentInterface {
    $this->set('charge_total', $charge_total);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNewsLetter(): bool {
    return (bool) $this->get('newsletter')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setNewsLetter(bool $newsletter): PaymentInterface {
    $this->set('newsletter', $newsletter);
    return $this;
  }

  public function getTransactionId(): string {
    return $this->get('transaction_id')->value;
  }

  public function setTransactionId(string $transaction_id): PaymentInterface {
    $this->set('transaction_id', $transaction_id);
    return $this;
  }

  public function getOrderId(): string {
    return $this->get('order_id')->value;
  }

  public function setOrderId(string $order_id): PaymentInterface {
    $this->set('order_id', $order_id);
    return $this;
  }

  public function getPst(): string {
    return $this->get('pst')->value;
  }

  public function setPst(string $pst): PaymentInterface {
    $this->set('pst', $pst);
    return $this;
  }

  public function getAcceptNationality(): bool {
    return $this->get('accept_nationality')->value;
  }

  public function setAcceptNationality(bool $accept_nationality): PaymentInterface {
    $this->set('accept_nationality', $accept_nationality);
    return $this;
  }

  public function getAcceptOther(): bool {
    return $this->get('accept_other')->value;
  }

  public function setAcceptOther(bool $accept_nationality): PaymentInterface {
    $this->set('accept_other', $accept_nationality);
    return $this;
  }

  public function getAcceptPhoto(): bool {
    return $this->get('accept_photo')->value;
  }

  public function setAcceptPhoto(bool $accept_nationality): PaymentInterface {
    $this->set('accept_photo', $accept_nationality);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * Get crated in date format.
   *
   * @return false|int
   *   Date.
   */
  public function getCreated() {
    return date('Y-m-d H:i:s', $this->getCreatedTime());
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): PaymentInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    return $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status): PaymentInterface {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['faculty'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Faculty'))
      ->setSetting('max_length', 128);

    $fields['full_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Full name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['place_of_birth'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Place of birth'))
      ->setSetting('max_length', 255);

    $fields['date_of_birth'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Date of birth'));

    $fields['mothers_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Mother\'s name'))
      ->setSetting('max_length', 255);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email address'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['nationality'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Nationality'))
      ->setSetting('max_length', 128);

    $fields['phone'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Telephone'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['postcode'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Postcode'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['city'] = BaseFieldDefinition::create('string')
      ->setLabel(t('City'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['street'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Street'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['major'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Major'))
      ->setSetting('max_length', 255);

    $fields['etk_city'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ETK city'))
      ->setSetting('max_length', 64);

    $fields['notified'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Notified'))
      ->setSetting('max_length', 255);

    $fields['allergy'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Allergy'))
      ->setSetting('max_length', 255);

    $fields['meal'] = BaseFieldDefinition::create('string')
      ->setLabel(t('meal'))
      ->setSetting('max_length', 255);

    $fields['t_shirt_size'] = BaseFieldDefinition::create('string')
      ->setLabel(t('T-shirt size'))
      ->setSetting('max_length', 8)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['stay'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Stay'))
      ->setSetting('max_length', 1);

    $fields['stay_day'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Stay day'))
      ->setSetting('max_length', 255);

    $fields['pst'] = BaseFieldDefinition::create('string')
      ->setLabel(t('PST'))
      ->setSetting('max_length', 128);

    $fields['accept_nationality'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Accept nationality'))
      ->setSetting('max_length', 32);

    $fields['accept_other'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Accept other'))
      ->setSetting('max_length', 32);

    $fields['accept_photo'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Accept photo'))
      ->setSetting('max_length', 32);

    $fields['charge_total'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Amount'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['hash'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Payment hash'))
      ->setSetting('max_length', 64);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setSetting('max_length', 32);

    $fields['fail_reason'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Fail reason'))
      ->setSetting('max_length', 255);

    $fields['order_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Order Id'))
      ->setSetting('max_length', 32);

    $fields['transaction_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Transaction Id'))
      ->setSetting('max_length', 32);

    $fields['approval_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Approval Code'))
      ->setSetting('max_length', 128);

    $fields['currency'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Currency'))
      ->setSetting('max_length', 3);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the Payment was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the Payment was last edited.'));

    return $fields;
  }

}
