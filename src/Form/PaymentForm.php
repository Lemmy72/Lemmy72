<?php

namespace Drupal\simplepay\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provide the Payment form.
 */
class PaymentForm extends FormBase {

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Payment configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Date Formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * @var array
   */
  protected $simplepayConfig;

  /**
   * Constructs a new DonateForm object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   Date Formatter service.
   */
  public function __construct(
    MessengerInterface $messenger,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatter $date_formatter) {
    $this->messenger = $messenger;
    $this->config = $config_factory->get(SettingsForm::SETTINGS);
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;

    if (empty($this->config->get('data'))) {
      $this->messenger->addMessage($this->t('Please fill out <a href=":settings" target="_blank">simplepay settings!</a>', [':settings' => '/admin/config/simplepay/settings']));
    }
    else {
      $this->simplepayConfig = [
        'HUF_MERCHANT' => $this->config->get('data')['huf_merchant'],
        'HUF_SECRET_KEY' => $this->config->get('data')['huf_secret_key'],
        'EUR_MERCHANT' => $this->config->get('data')['eur_merchant'],
        'EUR_SECRET_KEY' => $this->config->get('data')['eur_secret_key'],
        'USD_MERCHANT' => $this->config->get('data')['usd_merchant'],
        'USD_SECRET_KEY' => $this->config->get('data')['usd_secret_key'],
        'SANDBOX' => (bool) $this->config->get('environment') == 'Test',
        'URLS_SUCCESS' => $this->entityTypeManager->getStorage('node')
          ->load($this->config->get('data')['response_success_url'])
          ->toUrl()
          ->setAbsolute(TRUE)
          ->toString(),
        'URLS_FAIL' => $this->entityTypeManager->getStorage('node')
          ->load($this->config->get('data')['response_fail_url'])
          ->toUrl()
          ->setAbsolute(TRUE)
          ->toString(),
        'URLS_CANCEL' => $this->entityTypeManager->getStorage('node')
          ->load($this->config->get('data')['response_cancel_url'])
          ->toUrl()
          ->setAbsolute(TRUE)
          ->toString(),
        'URLS_TIMEOUT' => $this->entityTypeManager->getStorage('node')
          ->load($this->config->get('data')['response_timeout_url'])
          ->toUrl()
          ->setAbsolute(TRUE)
          ->toString(),
        'GET_DATA' => (isset($_GET['r']) && isset($_GET['s'])) ? [
          'r' => $_GET['r'],
          's' => $_GET['s'],
        ] : [],
        'POST_DATA' => $_POST,
        'SERVER_DATA' => $_SERVER,
        'AUTOCHALLENGE' => TRUE,
      ];

      /*print "<pre>";
      print_r($this->simplepayConfig);
      print "</pre>";*/

      require_once dirname(__DIR__) . '/../sdk/SimplePayV21.php';

      $trx = new \SimplePayStart;

      $currency = 'HUF';
      $trx->addData('currency', $currency);
      $trx->addConfig($this->simplepayConfig);
      $trx->addData('total', 3000);
      $trx->addData('orderRef', str_replace([
          '.',
          ':',
          '/',
        ], "", @$_SERVER['SERVER_ADDR']) . @date("U", time()) . rand(1000, 9999));

      // customer's registration mehod
      // 01: guest
      // 02: registered
      // 05: third party
      $trx->addData('threeDSReqAuthMethod', '02');
      $trx->addData('customerEmail', 'sdk_test@otpmobil.com');
      $trx->addData('language', 'HU');
      $timeoutInSec = 600;
      $timeout = @date("c", time() + $timeoutInSec);
      $trx->addData('timeout', $timeout);
      $trx->addData('methods', ['CARD']);
      $trx->addData('url', $this->simplepayConfig['URLS_SUCCESS']);

      $trx->addGroupData('urls', 'success', $this->simplepayConfig['URLS_SUCCESS']);
      $trx->addGroupData('urls', 'fail', $this->simplepayConfig['URLS_FAIL']);
      $trx->addGroupData('urls', 'cancel', $this->simplepayConfig['URLS_CANCEL']);
      $trx->addGroupData('urls', 'timeout', $this->simplepayConfig['URLS_TIMEOUT']);


      $trx->addGroupData('invoice', 'name', 'SimplePay V2 Tester');
      //$trx->addGroupData('invoice', 'company', '');
      $trx->addGroupData('invoice', 'country', 'hu');
      $trx->addGroupData('invoice', 'state', 'Budapest');
      $trx->addGroupData('invoice', 'city', 'Budapest');
      $trx->addGroupData('invoice', 'zip', '1111');
      $trx->addGroupData('invoice', 'address', 'Address 1');

      $trx->formDetails['element'] = 'button';

      /*
      $trx->runStart();
      print "API REQUEST";
      print "<pre>";
      print_r($trx->getTransactionBase());
      print "</pre>";

      print "API RESULT";
      print "<pre>";
      print_r($trx->getReturnData());
      print "</pre>";
      */
    }

  }

  /**
   * Create function.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Contains the container for dependency injection.
   *
   * @return \Drupal\Core\Form\FormBase|static
   *   Return with the injected services.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'payment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('<h3>Üdvözlünk a PTE Gólyatábor regisztrációs felületén!</h3>'),
    ];

    $faculties_data = $this->config->get('faculties');

    foreach (SettingsForm::FACULTIES as $key => $title) {
      $options[$key] = $title;
    }

    $form['faculty'] = [
      '#type' => 'select',
      '#title' => $this->t('Kérlek, válaszd ki, melyik gólyatáborunkba jelentkezel!'),
      '#required' => TRUE,
      '#options' => $options,
      '#ajax' => [
        'callback' => [$this, 'loadInfo'],
      ],
    ];

    $selected_faculty = $form_state->getUserInput()['faculty'];

    $form['info'] = [
      '#type' => 'item',
      '#markup' => t('<strong>A tábor ideje: @date<br>A tábor helyszíne: @location<br>Jelentkezési időszak: @apply<br>Tábor díja: @price</strong>',
        [
          '@date' => $faculties_data[$selected_faculty]['start_date'] . ' - ' . $faculties_data[$selected_faculty]['end_date'],
          '@location' => $faculties_data[$selected_faculty]['location'],
          '@apply' => $faculties_data[$selected_faculty]['apply_start_date'] . ' - ' . $faculties_data[$selected_faculty]['apply_end_date'],
          '@price' => $faculties_data[$selected_faculty]['price'] . ' Ft.',
        ]),
    ];

    $form['full_name'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#placeholder' => $this->t("Full name"),
      '#title' => $this->t("Full name"),
    ];

    $form['place_of_birth'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#placeholder' => $this->t("Place of birth"),
      '#title' => $this->t("Place of birth"),
    ];

    $form['date_of_birth'] = [
      '#type' => 'datelist',
      '#required' => TRUE,
      '#placeholder' => $this->t("Date of birth"),
      '#title' => $this->t("Date of birth"),
      '#date_part_order' => ['year', 'month', 'day'],
      '#date_year_range' => '1990:2005',
    ];

    $form['mothers_name'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#placeholder' => $this->t("Mother's name"),
      '#title' => $this->t("Mother's name"),
    ];

    $form['email'] = [
      '#type' => 'email',
      '#required' => TRUE,
      '#placeholder' => $this->t("Email address"),
      '#title' => $this->t("Email address"),
    ];

    $form['nationality'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nationality'),
      '#required' => TRUE,
    ];

    $form['telephone_number'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#placeholder' => $this->t("Enter a phone number we can contact you with"),
      '#title' => $this->t("Telephone number"),
    ];

    $form['postcode'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#placeholder' => $this->t("Enter your postcode"),
      '#title' => $this->t("Postcode"),
    ];

    $form['city'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#placeholder' => $this->t("Enter name of your city or town"),
      '#title' => $this->t("City / Town"),
    ];

    $form['street'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#placeholder' => $this->t("Enter your number and street name"),
      '#title' => $this->t("Number / Street name"),
    ];

    $form['major'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Szak/szakirány/képzési központ/képzési rend'),
      '#required' => TRUE,
    ];

    $form['etk_city'] = [
      '#type' => 'select',
      '#title' => $this->t('Melyik városban fogsz tanulni?'),
      '#options' => [
        'Pécs' => $this->t('Pécs'),
        'Kaposvár' => $this->t('Kaposvár'),
        'Szombathely' => $this->t('Szombathely'),
        'Zalaegerszeg' => $this->t('Zalaegerszeg'),
        'Zombor' => $this->t('Zombor'),
      ],
      '#required' => TRUE,
      '#states' => ['visible' => ['select[name="faculty"]' => ['value' => '4']]],
    ];

    $form['notified'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Szükség esetén értesítendő személy telefonszáma (szülő, gondviselő)'),
      '#description' => $this->t('Amennyiben szeretnéd, hogy szükség esetén értesítsünk egy általad megjelölt személyt (szülőt, gondviselőt), kérlek tüntesd fel elérhetőségüket!'),
      '#required' => FALSE,
    ];

    $form['allergy'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allergia, egyéb betegség'),
      '#description' => $this->t('Amennyiben szeretnéd biztosítani, hogy a Gólyatábor területén tartózkodó egészségügyi ellátást nyújtó személyzet  gyógyszerek és gyógyászati segédeszközök tekintetében a legfelkészültebben tudja végezni feladatát, kérlek előzetesen tájékoztass minket ismert allergiáidról, betegségeidről! Az allergia/betegség előzetes bejelentése nagyban hozzájárul az egészségügyi személyzet hatékony felkészüléséhez.'),
      '#required' => FALSE,
    ];

    $form['meal'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Étkezési igény/érzékenység'),
      '#description' => $this->t('Amennyiben szeretnéd, hogy speciális étkezési igényednek megfelelő ellátást biztosítsunk Neked, kérlek írd le ételallergiád, egyéb étkezési igényed!'),
      '#required' => FALSE,
    ];

    $form['t_shirt_size'] = [
      '#type' => 'select',
      '#title' => $this->t('T-Shirt size'),
      '#description' => $this->t('Amennyiben szeretnéd, hogy mérethelyes pólóval készülhessünk számodra, úgy kérlek tüntesd fel pólóméreted! Természetesen, ha nem szeretnéd megadni, akkor is biztosítunk Neked pólót, de ez esetben a mérethelyességet nem tudjuk garantálni.'),
      '#options' => [
        'S' => $this->t('S'),
        'M' => $this->t('M'),
        'L' => $this->t('L'),
        'XL' => $this->t('XL'),
        'XXL' => $this->t('XXL'),
        'XXXL' => $this->t('XXXL'),
      ],
    ];

    $form['stay'] = [
      '#type' => 'select',
      '#title' => $this->t('Meddig maradsz a táborban?'),
      '#options' => [
        '1' => $this->t('Végig'),
        '2' => $this->t('Nem végig'),
      ],
      '#required' => TRUE,
    ];

    $form['stay_day'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Meddig maradsz a táborban?'),
      '#required' => TRUE,
      '#states' => ['visible' => ['select[name="stay"]' => ['value' => '2']]],
    ];

    $form['accept_nationality'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Hozzájárulás az állampolgárság adat kezeléséhez'),
      '#description' => $this->t('Hozzájárulásomat adom állampolgárságomra vonatkozó adat statisztikai célból történő kezeléséhez hozzájárulásom visszavonásáig.'),
    ];

    $form['accept_other'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Hozzájárulás megbetegedés, pólóméret, szülő/gondviselő telefonszáma, étkezési igény adatok kezeléséhez'),
      '#description' => $this->t('Hozzájárulásomat adom allergiás és egyéb megbetegedésemre, pólóméretemre, szülő/gondviselő telefonszámára/étkezési igényre vonatkozó adatok kezeléséhez - azokra vonatkozóan, mely információkat jelen nyilatkozaton feltüntettem - extra szolgáltatások nyújtása céljából, hozzájárulásom visszavonásáig, de legfeljebb a Gólyatábor rendezvény végéig.'),
    ];

    $form['accept_photo'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Hozzájárulás fotó és videófelvételek készítéséhez'),
      '#description' => $this->t('Hozzájárulásomat adom, hogy a Gólyatábor rendezvény során fotó és videófelvételeket készítsenek rólam promóciós és marketing célból, és azokat a Gólyatábor befejezésétől számított egy éven át kezeljék, felhasználják.'),
    ];

    $form['accept_privacy'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Adatkezelés'),
      '#description' => $this->t('A részletes adatkezelési tájékoztatót elolvastam és az abban foglaltakat elfogadom.'),
      '#required' => TRUE,
    ];

    $form['accept_rules'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Nyilatkozat a házirend elfogadásáról'),
      '#description' => $this->t('Nyilatkozom, hogy a rendezvény etikai kódexét és házirendjét, valamint a befogadó intézmény házirendjét megismertem, annak tartalmát magamra nézve kötelezőnek tekintem.'),
      '#required' => TRUE,
    ];

    $form['accept_responsibility'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Nyilatkozat a személyi és anyagi felelősségről'),
      '#description' => $this->t('Nyilatkozom, hogy a rendezvényen saját személyi és anyagi felelősségem tudatában veszek részt.'),
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#button_type' => 'primary',
    ];

    return $form;
  }


  /**
   * Builds the second step form (page 2 - Payment).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The render array defining the elements of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function paymentForm(array &$form, FormStateInterface $form_state) {

    $environment = $this->config->get('environment');

    $charge_total = number_format((float) $form_state->getValue('chargetotal'), 2, '.', '');
    $currency = $this->config->get('global')['currency_code'];
    $txndatetime = $this->getDateTime();

    $form['#attributes']['class'][] = 'donate';
    $form['#attributes']['class'][] = 'payment';

    $form['steps'] = [
      '#type' => 'markup',
      '#markup' => '<ul class="donate__steps">' .
        '<li class="donate__step donate__step--disabled">' . $this->t("Your details") . '</li>' .
        '<li class="donate__step donate__step--selected">' . $this->t("Payment details") . '</li>' .
        '</ul>',
    ];

    $form['#action'] = $this->config->get('environments')[$environment]['api_url'];

    // Hidden fields.
    $form['txntype'] = [
      '#type' => 'hidden',
      '#value' => 'sale',
    ];

    $form['timezone'] = [
      '#type' => 'hidden',
      '#value' => date_default_timezone_get(),
    ];

    $form['txndatetime'] = [
      '#type' => 'hidden',
      '#value' => $txndatetime,
    ];

    $form['hash_algorithm'] = [
      '#type' => 'hidden',
      '#value' => 'SHA256',
    ];

    $form['hash'] = [
      '#type' => 'hidden',
      '#value' => $this->createHash($charge_total, $currency, $txndatetime),
    ];

    $form['storename'] = [
      '#type' => 'hidden',
      '#value' => $this->config->get('environments')[$environment]['store_name'],
    ];

    $form['mode'] = [
      '#type' => 'hidden',
      '#value' => 'payplus',
    ];

    $form['chargetotal'] = [
      '#type' => 'hidden',
      '#value' => $charge_total,
    ];

    $form['currency'] = [
      '#type' => 'hidden',
      '#value' => $currency,
    ];

    $form['responseFailURL'] = [
      '#type' => 'hidden',
      '#value' => $this->entityTypeManager->getStorage('node')
        ->load($this->config->get('global')['response_fail_url'])
        ->toUrl()
        ->setAbsolute(TRUE)
        ->toString(),
    ];

    $form['responseSuccessURL'] = [
      '#type' => 'hidden',
      '#value' => $this->entityTypeManager->getStorage('node')
        ->load($this->config->get('global')['response_success_url'])
        ->toUrl()
        ->setAbsolute(TRUE)
        ->toString(),
    ];

    $form['bcompany'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('company_name'),
    ];

    $form['first_name'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('first_name'),
    ];

    $form['last_name'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('last_name'),
    ];

    $form['job_title'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('job_title'),
    ];

    $form['commission_ref'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('commission_ref'),
    ];

    $form['gamble_aware_donor_ref'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('gamble_aware_donor_ref'),
    ];

    $form['county'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('county'),
    ];

    $form['other'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('other'),
    ];

    $form['newsletter'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('newsletter'),
    ];

    $form['bname'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('first_name') . ' ' . $form_state->getValue('last_name'),
    ];

    $form['baddr1'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('street'),
    ];

    $form['bcity'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('city'),
    ];

    $form['bzip'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('postcode'),
    ];

    $form['bcountry'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('country'),
    ];

    $form['phone'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('telephone_number'),
    ];

    $form['email'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('email'),
    ];

    $form['parentCompany'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('parent_company_name'),
    ];

    $form['donatingonbehalf'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('donating_on_behalf_of'),
    ];

    $form['companytrade'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('company_trade'),
    ];

    $form['title'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('title'),
    ];

    $form['website'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('company_website'),
    ];

    $form['donationbased'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('donation_based'),
    ];

    $form['paymentMethod'] = [
      '#type' => 'hidden',
    ];

    $form['full_bypass'] = [
      '#type' => 'hidden',
      '#value' => 'false',
    ];

    $form['authenticateTransaction'] = [
      '#type' => 'hidden',
      '#value' => 'true',
    ];

    $form['threeDSRequestorChallengeIndicator'] = [
      '#type' => 'hidden',
      '#value' => '01',
    ];

    // Card detail fields.
    $form['card-name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Name on Card"),
      '#required' => TRUE,
      '#placeholder' => $this->t("Name on Card"),
    ];

    $form['cardnumber'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Card Number"),
      '#required' => TRUE,
      '#placeholder' => $this->t("Card Number (XXXX-XXXX-XXXX-XXXX)"),
    ];

    $form['expiry_date'] = [
      '#type' => 'fieldset',
      '#title' => $this->t("Expiry date and CVV"),
      '#attributes' => [
        'class' => [
          'expiry-date-fieldset',
        ],
      ],
    ];

    $expmonth_options = [];
    for ($i = 1; $i <= 12; $i++) {
      $expmonth_options[sprintf('%02d', $i)] = sprintf('%02d', $i);
    }

    $form['expiry_date']['expmonth'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $expmonth_options,
    ];

    $expyear_options = [];
    for ($year = intval(date('Y')); $year <= intval(date('Y')) + 9; $year++) {
      $expyear_options[$year] = $year;
    }

    $form['expiry_date']['expyear'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $expyear_options,
    ];

    $form['expiry_date']['cvm'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#placeholder' => $this->t("CVV"),
    ];

    // Review.
    $form['review'] = [
      '#type' => 'fieldset',
      '#title' => $this->t("Review Your Donation"),
      '#attributes' => [
        'class' => [
          'review',
        ],
      ],
    ];

    $form['review']['review_donation_amount'] = [
      '#type' => 'markup',
      '#markup' => '<p class="review__row review__donation_amount">' .
        $this->t("<span class='review__label'>@label:</span> <span class='review__value'>@value</span>", [
          '@label' => $this->t("Your donation amount"),
          '@value' => '£' . $charge_total,
        ]) .
        '</p>',
    ];

    $form['review']['review_name'] = [
      '#type' => 'markup',
      '#markup' => '<p class="review__row review__donation_amount">' .
        $this->t("<span class='review__label'>@label:</span> <span class='review__value'>@value</span>", [
          '@label' => $this->t("Name"),
          '@value' => $form_state->getValue('first_name') . ' ' . $form_state->getValue('last_name'),
        ]) .
        '</p>',
    ];

    $form['review']['review_email'] = [
      '#type' => 'markup',
      '#markup' => '<p class="review__row review__donation_amount">' .
        $this->t("<span class='review__label'>@label:</span> <span class='review__value'>@value</span>", [
          '@label' => $this->t("Email address"),
          '@value' => $form_state->getValue('email'),
        ]) .
        '</p>',
    ];

    $form['review']['review_company_name'] = [
      '#type' => 'markup',
      '#markup' => '<p class="review__row review__donation_amount">' .
        $this->t("<span class='review__label'>@label:</span> <span class='review__value'>@value</span>", [
          '@label' => $this->t("Company"),
          '@value' => $form_state->getValue('company_name'),
        ]) .
        '</p>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Donate'),
    ];

    return $form;
  }

  /**
   * Provides custom submission handler for 'Back' button (page 2).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function paymentBack(array &$form, FormStateInterface $form_state) {
    $form_state
      // Restore values for the first step.
      ->setValues($form_state->get('page_values'))
      ->set('page_num', 1)
      // Since we have logic in our buildForm() method, we have to tell the form
      // builder to rebuild the form. Otherwise, even though we set 'page_num'
      // to 1, the AJAX-rendered form will still show page 2.
      ->setRebuild(TRUE);
  }

  /**
   * Get country list with country codes.
   *
   * @return array
   *   Country list with country codes.
   */
  protected function getCountryOptions() {
    return [
      "AF" => "Afghanistan",
      "AL" => "Albania",
      "DZ" => "Algeria",
      "AS" => "American Samoa",
      "AD" => "Andorra",
      "AO" => "Angola",
      "AI" => "Anguilla",
      "AQ" => "Antarctica",
      "AG" => "Antigua and Barbuda",
      "AR" => "Argentina",
      "AM" => "Armenia",
      "AW" => "Aruba",
      "AU" => "Australia",
      "AT" => "Austria",
      "AZ" => "Azerbaijan",
      "BS" => "Bahamas",
      "BH" => "Bahrain",
      "BD" => "Bangladesh",
      "BB" => "Barbados",
      "BY" => "Belarus",
      "BE" => "Belgium",
      "BZ" => "Belize",
      "BJ" => "Benin",
      "BM" => "Bermuda",
      "BT" => "Bhutan",
      "BO" => "Bolivia",
      "BA" => "Bosnia and Herzegovina",
      "BW" => "Botswana",
      "BV" => "Bouvet Island",
      "BR" => "Brazil",
      "BQ" => "British Antarctic Territory",
      "IO" => "British Indian Ocean Territory",
      "VG" => "British Virgin Islands",
      "BN" => "Brunei",
      "BG" => "Bulgaria",
      "BF" => "Burkina Faso",
      "BI" => "Burundi",
      "KH" => "Cambodia",
      "CM" => "Cameroon",
      "CA" => "Canada",
      "CT" => "Canton and Enderbury Islands",
      "CV" => "Cape Verde",
      "KY" => "Cayman Islands",
      "CF" => "Central African Republic",
      "TD" => "Chad",
      "CL" => "Chile",
      "CN" => "China",
      "CX" => "Christmas Island",
      "CC" => "Cocos [Keeling] Islands",
      "CO" => "Colombia",
      "KM" => "Comoros",
      "CG" => "Congo - Brazzaville",
      "CD" => "Congo - Kinshasa",
      "CK" => "Cook Islands",
      "CR" => "Costa Rica",
      "HR" => "Croatia",
      "CU" => "Cuba",
      "CY" => "Cyprus",
      "CZ" => "Czech Republic",
      "CI" => "Côte d’Ivoire",
      "DK" => "Denmark",
      "DJ" => "Djibouti",
      "DM" => "Dominica",
      "DO" => "Dominican Republic",
      "NQ" => "Dronning Maud Land",
      "DD" => "East Germany",
      "EC" => "Ecuador",
      "EG" => "Egypt",
      "SV" => "El Salvador",
      "GQ" => "Equatorial Guinea",
      "ER" => "Eritrea",
      "EE" => "Estonia",
      "ET" => "Ethiopia",
      "FK" => "Falkland Islands",
      "FO" => "Faroe Islands",
      "FJ" => "Fiji",
      "FI" => "Finland",
      "FR" => "France",
      "GF" => "French Guiana",
      "PF" => "French Polynesia",
      "TF" => "French Southern Territories",
      "FQ" => "French Southern and Antarctic Territories",
      "GA" => "Gabon",
      "GM" => "Gambia",
      "GE" => "Georgia",
      "DE" => "Germany",
      "GH" => "Ghana",
      "GI" => "Gibraltar",
      "GR" => "Greece",
      "GL" => "Greenland",
      "GD" => "Grenada",
      "GP" => "Guadeloupe",
      "GU" => "Guam",
      "GT" => "Guatemala",
      "GG" => "Guernsey",
      "GN" => "Guinea",
      "GW" => "Guinea-Bissau",
      "GY" => "Guyana",
      "HT" => "Haiti",
      "HM" => "Heard Island and McDonald Islands",
      "HN" => "Honduras",
      "HK" => "Hong Kong SAR China",
      "HU" => "Hungary",
      "IS" => "Iceland",
      "IN" => "India",
      "ID" => "Indonesia",
      "IR" => "Iran",
      "IQ" => "Iraq",
      "IE" => "Ireland",
      "IM" => "Isle of Man",
      "IL" => "Israel",
      "IT" => "Italy",
      "JM" => "Jamaica",
      "JP" => "Japan",
      "JE" => "Jersey",
      "JT" => "Johnston Island",
      "JO" => "Jordan",
      "KZ" => "Kazakhstan",
      "KE" => "Kenya",
      "KI" => "Kiribati",
      "KW" => "Kuwait",
      "KG" => "Kyrgyzstan",
      "LA" => "Laos",
      "LV" => "Latvia",
      "LB" => "Lebanon",
      "LS" => "Lesotho",
      "LR" => "Liberia",
      "LY" => "Libya",
      "LI" => "Liechtenstein",
      "LT" => "Lithuania",
      "LU" => "Luxembourg",
      "MO" => "Macau SAR China",
      "MK" => "Macedonia",
      "MG" => "Madagascar",
      "MW" => "Malawi",
      "MY" => "Malaysia",
      "MV" => "Maldives",
      "ML" => "Mali",
      "MT" => "Malta",
      "MH" => "Marshall Islands",
      "MQ" => "Martinique",
      "MR" => "Mauritania",
      "MU" => "Mauritius",
      "YT" => "Mayotte",
      "FX" => "Metropolitan France",
      "MX" => "Mexico",
      "FM" => "Micronesia",
      "MI" => "Midway Islands",
      "MD" => "Moldova",
      "MC" => "Monaco",
      "MN" => "Mongolia",
      "ME" => "Montenegro",
      "MS" => "Montserrat",
      "MA" => "Morocco",
      "MZ" => "Mozambique",
      "MM" => "Myanmar [Burma]",
      "NA" => "Namibia",
      "NR" => "Nauru",
      "NP" => "Nepal",
      "NL" => "Netherlands",
      "AN" => "Netherlands Antilles",
      "NT" => "Neutral Zone",
      "NC" => "New Caledonia",
      "NZ" => "New Zealand",
      "NI" => "Nicaragua",
      "NE" => "Niger",
      "NG" => "Nigeria",
      "NU" => "Niue",
      "NF" => "Norfolk Island",
      "KP" => "North Korea",
      "VD" => "North Vietnam",
      "MP" => "Northern Mariana Islands",
      "NO" => "Norway",
      "OM" => "Oman",
      "PC" => "Pacific Islands Trust Territory",
      "PK" => "Pakistan",
      "PW" => "Palau",
      "PS" => "Palestinian Territories",
      "PA" => "Panama",
      "PZ" => "Panama Canal Zone",
      "PG" => "Papua New Guinea",
      "PY" => "Paraguay",
      "YD" => "People's Democratic Republic of Yemen",
      "PE" => "Peru",
      "PH" => "Philippines",
      "PN" => "Pitcairn Islands",
      "PL" => "Poland",
      "PT" => "Portugal",
      "PR" => "Puerto Rico",
      "QA" => "Qatar",
      "RO" => "Romania",
      "RU" => "Russia",
      "RW" => "Rwanda",
      "RE" => "Réunion",
      "BL" => "Saint Barthélemy",
      "SH" => "Saint Helena",
      "KN" => "Saint Kitts and Nevis",
      "LC" => "Saint Lucia",
      "MF" => "Saint Martin",
      "PM" => "Saint Pierre and Miquelon",
      "VC" => "Saint Vincent and the Grenadines",
      "WS" => "Samoa",
      "SM" => "San Marino",
      "SA" => "Saudi Arabia",
      "SN" => "Senegal",
      "RS" => "Serbia",
      "CS" => "Serbia and Montenegro",
      "SC" => "Seychelles",
      "SL" => "Sierra Leone",
      "SG" => "Singapore",
      "SK" => "Slovakia",
      "SI" => "Slovenia",
      "SB" => "Solomon Islands",
      "SO" => "Somalia",
      "ZA" => "South Africa",
      "GS" => "South Georgia and the South Sandwich Islands",
      "KR" => "South Korea",
      "ES" => "Spain",
      "LK" => "Sri Lanka",
      "SD" => "Sudan",
      "SR" => "Suriname",
      "SJ" => "Svalbard and Jan Mayen",
      "SZ" => "Swaziland",
      "SE" => "Sweden",
      "CH" => "Switzerland",
      "SY" => "Syria",
      "ST" => "São Tomé and Príncipe",
      "TW" => "Taiwan",
      "TJ" => "Tajikistan",
      "TZ" => "Tanzania",
      "TH" => "Thailand",
      "TL" => "Timor-Leste",
      "TG" => "Togo",
      "TK" => "Tokelau",
      "TO" => "Tonga",
      "TT" => "Trinidad and Tobago",
      "TN" => "Tunisia",
      "TR" => "Turkey",
      "TM" => "Turkmenistan",
      "TC" => "Turks and Caicos Islands",
      "TV" => "Tuvalu",
      "UM" => "U.S. Minor Outlying Islands",
      "PU" => "U.S. Miscellaneous Pacific Islands",
      "VI" => "U.S. Virgin Islands",
      "UG" => "Uganda",
      "UA" => "Ukraine",
      "SU" => "Union of Soviet Socialist Republics",
      "AE" => "United Arab Emirates",
      "GB" => "United Kingdom",
      "US" => "United States",
      "ZZ" => "Unknown or Invalid Region",
      "UY" => "Uruguay",
      "UZ" => "Uzbekistan",
      "VU" => "Vanuatu",
      "VA" => "Vatican City",
      "VE" => "Venezuela",
      "VN" => "Vietnam",
      "WK" => "Wake Island",
      "WF" => "Wallis and Futuna",
      "EH" => "Western Sahara",
      "YE" => "Yemen",
      "ZM" => "Zambia",
      "ZW" => "Zimbabwe",
      "AX" => "Åland Islands",
    ];
  }

  /**
   * Get Date and time in the following format: Y:m:d-H:i:s.
   */
  protected function getDateTime() {
    $timezone = date_default_timezone_get();
    return $this->dateFormatter->format(time(), 'custom', 'Y:m:d-H:i:s', $timezone);
  }

  /**
   * Function that calculates the hash of the following parameters.
   *
   * - Store ID
   * - Date/Time
   * - Charge total
   * - Currency (numeric ISO value)
   * - shared secret.
   *
   * @param string $charge_total
   *   Charge total.
   * @param string $currency
   *   Currency.
   * @param string $txndatetime
   *   Transaction date time.
   *
   * @return string
   *   Hash string.
   */
  protected function createHash($charge_total, $currency, $txndatetime) {
    $environment = $this->config->get('environment');
    $store_id = $this->config->get('environments')[$environment]['store_name'];
    $sharedSecret = $this->config->get('environments')[$environment]['shared_secret'];
    $stringToHash = $store_id . $txndatetime . $charge_total . $currency . $sharedSecret;
    $ascii = bin2hex($stringToHash);
    return hash('sha256', $ascii);
  }

  /**
   * Notification Hash.
   *
   * Check hash to validate date before save donate entity.
   *
   * @param string $charge_total
   *   Charge total.
   * @param string $currency
   *   Currency.
   * @param string $txndatetime
   *   Transaction date time.
   * @param string $approval_code
   *   Approval code.
   *
   * @return string
   *   Hash string.
   */
  public static function notificationHash(string $charge_total, string $currency, string $txndatetime, string $approval_code): string {
    $config_factory = \Drupal::service('config.factory');
    $config = $config_factory->get('ga_donate.settings');
    $environment = $config->get('environment');
    $store_id = $config->get('environments')[$environment]['store_name'];
    $sharedSecret = $config->get('environments')[$environment]['shared_secret'];
    $stringToHash = $sharedSecret . $approval_code . $charge_total . $currency . $txndatetime . $store_id;
    $ascii = bin2hex($stringToHash);
    return hash('sha256', $ascii);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  public function loadInfo(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#edit-info', $form['info']));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValues()['newsletter'] === NULL) {
      $form_state->setErrorByName('newsletter', $this->t('Please let us know if you would like to receive updates from us.'));
    }
    $form_state->setRebuild(TRUE);
  }

}
