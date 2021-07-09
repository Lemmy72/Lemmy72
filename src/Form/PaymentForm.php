<?php

namespace Drupal\simplepay\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplepay\Entity\Payment;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;

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
      try {
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
      } catch (InvalidPluginDefinitionException|PluginNotFoundException|EntityMalformedException $e) {
        $this->logger('simplepay')->warning($e->getMessage());
      }
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
    return 'pte_payment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    //TODO PST menteni
    //Ellenorzni hogy a sikeres megrendelesek szama kisebb-e mint a letszam az adott karnal
    //foglalasi idopont ellenorzese az adott karnal

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

    $selected_faculty = $form_state->getValues()['faculty'];

    $form['info'] = [
      '#type' => 'item',
      '#markup' => $this->t('<strong>A tábor ideje: @date<br>A tábor helyszíne: @location<br>Jelentkezési időszak: @apply<br>Tábor díja: @price</strong>',
        [
          '@date' => is_numeric($selected_faculty) ? $faculties_data[$selected_faculty]['start_date'] . ' - ' . $faculties_data[$selected_faculty]['end_date'] : '',
          '@location' => is_numeric($selected_faculty) ? $faculties_data[$selected_faculty]['location'] : '',
          '@apply' => is_numeric($selected_faculty) ? $faculties_data[$selected_faculty]['apply_start_date'] . ' - ' . $faculties_data[$selected_faculty]['apply_end_date'] : '',
          '@price' => is_numeric($selected_faculty) ? $faculties_data[$selected_faculty]['price'] . ' Ft.' : '',
        ]),
    ];

    $can_apply = TRUE;
    if ($faculties_data[$selected_faculty]['apply_start_date'] && $faculties_data[$selected_faculty]['apply_end_date']) {
      $apply_start_ts = strtotime($faculties_data[$selected_faculty]['apply_start_date'] . ' 00:00:00');
      $apply_end_ts = strtotime($faculties_data[$selected_faculty]['apply_end_date'] . ' 23:59:59');
      $current_time = time();
      if ($current_time < $apply_start_ts || $current_time > $apply_end_ts) {
        $can_apply = FALSE;
        $this->messenger->addMessage($this->t('Apply is note enabled, you can apply between @start and @end',
          ['@start' => $faculties_data[$selected_faculty]['apply_start_date'], '@end' => $faculties_data[$selected_faculty]['apply_end_date']]));
      }
    }

    if ($can_apply) {
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

      $form['phone'] = [
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
          '' => $this->t('- Select -'),
          'Pécs' => $this->t('Pécs'),
          'Kaposvár' => $this->t('Kaposvár'),
          'Szombathely' => $this->t('Szombathely'),
          'Zalaegerszeg' => $this->t('Zalaegerszeg'),
          'Zombor' => $this->t('Zombor'),
        ],
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
        '#required' => TRUE,
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
        '#attributes' => [
          'disabled' => $can_apply,
        ]
      ];
    }

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    if ((int) $form_state->getValues()['stay'] === 2 && empty($form_state->getValues()['stay_day'])) {
      $form_state->setErrorByName('stay_day', $this->t('Ha nem végig maradsz akkor ez kötelező.'));
    }

    if ((int) $form_state->getValues()['faculty'] === 4 && empty($form_state->getValues()['etk_city'])) {
      $form_state->setErrorByName('etk_city', $this->t('ETK gólyatábor esetén kötelező.'));
    }

    //$form_state->setRebuild(TRUE);
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

    $faculties_data = $this->config->get('faculties');
    $selected_faculty = $form_state->getValues()['faculty'];

    require_once dirname(__DIR__) . '/../sdk/SimplePayV21.php';

    $trx = new \SimplePayStart;
    $order_id = str_replace([
        '.',
        ':',
        '/',
      ], "", @$_SERVER['SERVER_ADDR']) . @date("U", time()) . rand(1000, 9999);

    $currency = 'HUF';
    $trx->addData('currency', $currency);
    $trx->addConfig($this->simplepayConfig);
    $trx->addData('total', 3000);
    $trx->addData('orderRef', );

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


    /** @var \Drupal\Core\Datetime\DrupalDateTime $dateObject */
    $dateObject = $form_state->getValue('date_of_birth');
    $date_of_birth = date('Y-m-d', $dateObject->getTimestamp());

    $payment = Payment::create([
      'faculty' => $form_state->getValue('faculty'),
      'full_name' => $form_state->getValue('full_name'),
      'place_of_birth' => $form_state->getValue('place_of_birth'),
      'date_of_birth' => $date_of_birth,
      'mothers_name' => $form_state->getValue('mothers_name'),
      'email' => $form_state->getValue('email'),
      'nationality' => $form_state->getValue('nationality'),
      'phone' => $form_state->getValue('phone'),
      'postcode' => $form_state->getValue('postcode'),
      'city' => $form_state->getValue('city'),
      'street' => $form_state->getValue('street'),
      'major' => $form_state->getValue('major'),
      'etk_city' => $form_state->getValue('etk_city'),
      'notified' => $form_state->getValue('notified'),
      'allergy' => $form_state->getValue('allergy'),
      'meal' => $form_state->getValue('meal'),
      't_shirt_size' => $form_state->getValue('t_shirt_size'),
      'stay' => $form_state->getValue('stay'),
      'stay_day' => $form_state->getValue('stay_day'),
      'charge_total' => $faculties_data[$selected_faculty]['price'],
      //'hash' => $form_state->getValue('notified'),
      'order_id' => $order_id,
      //'transaction_id' => ,
      'currency' => $currency,
      'pst' => $faculties_data[$selected_faculty]['pst'],
      'accept_nationality' => $form_state->getValue('accept_nationality'),
      'accept_other' => $form_state->getValue('accept_other'),
      'accept_photo' => $form_state->getValue('accept_photo'),
    ]);

    try {
      $payment->save();
    }
    catch (EntityStorageException $e) {
      $this->logger('simplepay')->warning($e->getMessage());
    }
  }

  public function loadInfo(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#edit-info', $form['info']));
    $response->addCommand(new HtmlCommand('#edit-info', $form['info']));
    return $response;
  }

}
