<?php

namespace Drupal\simplepay;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a payment entity type.
 */
interface PaymentInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets street.
   *
   * @return string
   *   Street.
   */
  public function getStreet(): string;

  /**
   * Sets Street.
   *
   * @param string $street
   *   Street.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setStreet(string $street): PaymentInterface;

  /**
   * Get city.
   *
   * @return string
   *   City.
   */
  public function getCity(): string;

  /**
   * Sets City.
   *
   * @param string $city
   *   City.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setCity(string $city): PaymentInterface;

  /**
   * Gets County.
   *
   * @return mixed
   *   County.
   */
  public function getCounty();

  /**
   * Sets County.
   *
   * @param string $county
   *   County.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setCounty(string $county): PaymentInterface;

  /**
   * Gets Country.
   *
   * @return string
   *   Country.
   */
  public function getCountry(): string;

  /**
   * Sets Country.
   *
   * @param string $country
   *   Country.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setCountry(string $country): PaymentInterface;

  /**
   * Gets Post Code.
   *
   * @return string
   *   Post Code.
   */
  public function getPostCode(): string;

  /**
   * Sets Postcode.
   *
   * @param string $postcode
   *   Postcode.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setPostCode(string $postcode): PaymentInterface;

  /**
   * Gets Title.
   *
   * @return string
   *   Title.
   */
  public function getTitle(): string;

  /**
   * Sets Title.
   *
   * @param string $title
   *   Title.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setTitle(string $title): PaymentInterface;

  /**
   * Gets First Name.
   *
   * @return string
   *   First Name.
   */
  public function getFirstName(): string;

  /**
   * Sets First Name.
   *
   * @param string $first_name
   *   First Name.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setFirstName(string $first_name): PaymentInterface;

  /**
   * Gets Last Name.
   *
   * @return string
   *   Last Name.
   */
  public function getLastName(): string;

  /**
   * Sets Last Name.
   *
   * @param string $last_name
   *   Last Name.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setLastName(string $last_name): PaymentInterface;

  /**
   * Gets Email.
   *
   * @return string
   *   Email.
   */
  public function getEmail(): string;

  /**
   * Sets Email.
   *
   * @param string $email
   *   Email.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setEmail(string $email): PaymentInterface;

  /**
   * Gets Telephone Number.
   *
   * @return string
   *   Telephone Number.
   */
  public function getPhone(): string;

  /**
   * Sets Telephone Number.
   *
   * @param string $phone
   *   Telephone Number.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setPhone(string $phone): PaymentInterface;

  /**
   * Gets Charge Total.
   *
   * @return float
   *   Charge Total.
   */
  public function getChargeTotal(): float;

  /**
   * Sets Charge Total.
   *
   * @param float $charge_total
   *   Charge total.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setChargeTotal(float $charge_total): PaymentInterface;

  /**
   * Gets NewsLetter.
   *
   * @return bool
   *   NewsLetter.
   */
  public function getNewsLetter(): bool;

  /**
   * Gets NewsLetter.
   *
   * @param bool $newsletter
   *   NewsLetter.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setNewsLetter(bool $newsletter): PaymentInterface;

  /**
   * Gets the simplepay creation timestamp.
   *
   * @return int
   *   Creation timestamp of the simplepay.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the simplepay creation timestamp.
   *
   * @param int $timestamp
   *   The simplepay creation timestamp.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setCreatedTime(int $timestamp): PaymentInterface;

  /**
   * Returns the simplepay status.
   *
   * @return string
   *   TRUE if the simplepay is enabled, FALSE otherwise.
   */
  public function getStatus(): string;

  /**
   * Sets the simplepay status.
   *
   * @param string $status
   *   TRUE to enable this simplepay, FALSE to disable.
   *
   * @return \Drupal\simplepay\PaymentInterface
   *   The called simplepay entity.
   */
  public function setStatus(string $status): PaymentInterface;

}
