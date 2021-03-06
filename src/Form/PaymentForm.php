<?php

namespace Drupal\simplepay\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\simplepay\Entity\Payment;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;

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
   * Private temp store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

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
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store
   *   Temp store.
   */
  public function __construct(
    MessengerInterface $messenger,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    PrivateTempStoreFactory $temp_store) {
    $this->messenger = $messenger;
    $this->config = $config_factory->get(SettingsForm::SETTINGS);
    $this->entityTypeManager = $entity_type_manager;
    $this->tempStore = $temp_store->get('simplepay');

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
      $container->get('tempstore.private')
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

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('<h3>??dv??zl??nk a PTE G??lyat??bor regisztr??ci??s fel??let??n!</h3>'),
    ];

    $faculties_data = $this->config->get('faculties');

    foreach (SettingsForm::FACULTIES as $key => $title) {
      $options[$key] = $title;
    }

    $form['faculty'] = [
      '#type' => 'select',
      '#title' => $this->t('K??rlek, v??laszd ki, melyik g??lyat??borunkba jelentkezel!'),
      '#required' => TRUE,
      '#options' => $options,
      '#ajax' => [
        'callback' => [$this, 'loadInfo'],
      ],
    ];

    $selected_faculty = $form_state->getValues()['faculty'] ?? NULL;
    $info = '';
    if (is_numeric($selected_faculty)) {
      $info = $this->t('<strong>A t??bor ideje: @date<br>A t??bor helysz??ne: @location<br>Jelentkez??si id??szak: @apply<br>T??bor d??ja: @price</strong>',
        [
          '@date' => $faculties_data[$selected_faculty]['start_date'] . ' - ' . $faculties_data[$selected_faculty]['end_date'],
          '@location' => $faculties_data[$selected_faculty]['location'],
          '@apply' => $faculties_data[$selected_faculty]['apply_start_date'] . ' - ' . $faculties_data[$selected_faculty]['apply_end_date'],
          '@price' => $faculties_data[$selected_faculty]['price'] . ' Ft.',
        ]);
    }

    $form['apply']['info'] = [
      '#type' => 'item',
      '#markup' => $info,
    ];

    $can_apply = FALSE;
    if (is_numeric($selected_faculty) && $faculties_data[$selected_faculty]['apply_start_date'] && $faculties_data[$selected_faculty]['apply_end_date']) {
      $apply_start_ts = strtotime($faculties_data[$selected_faculty]['apply_start_date'] . ' 00:00:00');
      $apply_end_ts = strtotime($faculties_data[$selected_faculty]['apply_end_date'] . ' 23:59:59');
      $current_time = time();

      $entity = \Drupal::entityTypeManager()->getStorage('payment');
      $results = $entity->loadByProperties([
        'status' => 'SUCCESS',
        'faculty' => $selected_faculty,
      ]);

      $count = count($results);

      if ($current_time > $apply_start_ts &&
        $current_time < $apply_end_ts &&
        $count < $faculties_data[$selected_faculty]['participants']
      ) {
        $can_apply = TRUE;
      }
    }

    if ($can_apply) {
      $form['apply']['full_name'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#placeholder' => $this->t("Full name"),
        '#title' => $this->t("Full name"),
      ];

      $form['apply']['place_of_birth'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#placeholder' => $this->t("Place of birth"),
        '#title' => $this->t("Place of birth"),
      ];

      $form['apply']['date_of_birth'] = [
        '#type' => 'datelist',
        '#required' => TRUE,
        '#placeholder' => $this->t("Date of birth"),
        '#title' => $this->t("Date of birth"),
        '#date_part_order' => ['year', 'month', 'day'],
        '#date_year_range' => '1990:2005',
      ];

      $form['apply']['mothers_name'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#placeholder' => $this->t("Mother's name"),
        '#title' => $this->t("Mother's name"),
      ];

      $form['apply']['email'] = [
        '#type' => 'email',
        '#required' => TRUE,
        '#placeholder' => $this->t("Email address"),
        '#title' => $this->t("Email address"),
      ];

      $form['apply']['nationality'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Nationality'),
        '#required' => TRUE,
      ];

      $form['apply']['phone'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#placeholder' => $this->t("Enter a phone number we can contact you with"),
        '#title' => $this->t("Telephone number"),
      ];

      $form['apply']['postcode'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#placeholder' => $this->t("Enter your postcode"),
        '#title' => $this->t("Postcode"),
      ];

      $form['apply']['city'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#placeholder' => $this->t("Enter name of your city or town"),
        '#title' => $this->t("City / Town"),
      ];

      $form['apply']['street'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#placeholder' => $this->t("Enter your number and street name"),
        '#title' => $this->t("Number / Street name"),
      ];

      $form['apply']['major'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Szak/szakir??ny/k??pz??si k??zpont/k??pz??si rend'),
        '#required' => TRUE,
      ];

      $form['apply']['etk_city'] = [
        '#type' => 'select',
        '#title' => $this->t('Melyik v??rosban fogsz tanulni?'),
        '#options' => [
          '' => $this->t('- Select -'),
          'P??cs' => $this->t('P??cs'),
          'Kaposv??r' => $this->t('Kaposv??r'),
          'Szombathely' => $this->t('Szombathely'),
          'Zalaegerszeg' => $this->t('Zalaegerszeg'),
          'Zombor' => $this->t('Zombor'),
        ],
        '#states' => ['visible' => ['select[name="faculty"]' => ['value' => '4']]],
      ];

      $form['apply']['notified'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Sz??ks??g eset??n ??rtes??tend?? szem??ly telefonsz??ma (sz??l??, gondvisel??)'),
        '#description' => $this->t('Amennyiben szeretn??d, hogy sz??ks??g eset??n ??rtes??ts??nk egy ??ltalad megjel??lt szem??lyt (sz??l??t, gondvisel??t), k??rlek t??ntesd fel el??rhet??s??g??ket!'),
        '#required' => FALSE,
      ];

      $form['apply']['allergy'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Allergia, egy??b betegs??g'),
        '#description' => $this->t('Amennyiben szeretn??d biztos??tani, hogy a G??lyat??bor ter??let??n tart??zkod?? eg??szs??g??gyi ell??t??st ny??jt?? szem??lyzet  gy??gyszerek ??s gy??gy??szati seg??deszk??z??k tekintet??ben a legfelk??sz??ltebben tudja v??gezni feladat??t, k??rlek el??zetesen t??j??koztass minket ismert allergi??idr??l, betegs??geidr??l! Az allergia/betegs??g el??zetes bejelent??se nagyban hozz??j??rul az eg??szs??g??gyi szem??lyzet hat??kony felk??sz??l??s??hez.'),
        '#required' => FALSE,
      ];

      $form['apply']['meal'] = [
        '#type' => 'textfield',
        '#title' => $this->t('??tkez??si ig??ny/??rz??kenys??g'),
        '#description' => $this->t('Amennyiben szeretn??d, hogy speci??lis ??tkez??si ig??nyednek megfelel?? ell??t??st biztos??tsunk Neked, k??rlek ??rd le ??telallergi??d, egy??b ??tkez??si ig??nyed!'),
        '#required' => FALSE,
      ];

      $form['apply']['t_shirt_size'] = [
        '#type' => 'select',
        '#title' => $this->t('T-Shirt size'),
        '#description' => $this->t('Amennyiben szeretn??d, hogy m??rethelyes p??l??val k??sz??lhess??nk sz??modra, ??gy k??rlek t??ntesd fel p??l??m??reted! Term??szetesen, ha nem szeretn??d megadni, akkor is biztos??tunk Neked p??l??t, de ez esetben a m??rethelyess??get nem tudjuk garant??lni.'),
        '#options' => [
          'S' => $this->t('S'),
          'M' => $this->t('M'),
          'L' => $this->t('L'),
          'XL' => $this->t('XL'),
          'XXL' => $this->t('XXL'),
          'XXXL' => $this->t('XXXL'),
        ],
      ];

      $form['apply']['stay'] = [
        '#type' => 'select',
        '#title' => $this->t('Meddig maradsz a t??borban?'),
        '#options' => [
          '1' => $this->t('V??gig'),
          '2' => $this->t('Nem v??gig'),
        ],
        '#required' => TRUE,
      ];

      $form['apply']['stay_day'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Meddig maradsz a t??borban?'),
        '#states' => ['visible' => ['select[name="stay"]' => ['value' => '2']]],
      ];

      $form['apply']['accept_nationality'] = [
        '#type' => 'checkbox',
        '#title' => $this
          ->t('Hozz??j??rul??s az ??llampolg??rs??g adat kezel??s??hez'),
        '#description' => $this->t('Hozz??j??rul??somat adom ??llampolg??rs??gomra vonatkoz?? adat statisztikai c??lb??l t??rt??n?? kezel??s??hez hozz??j??rul??som visszavon??s??ig.'),
      ];

      $form['apply']['accept_other'] = [
        '#type' => 'checkbox',
        '#title' => $this
          ->t('Hozz??j??rul??s megbeteged??s, p??l??m??ret, sz??l??/gondvisel?? telefonsz??ma, ??tkez??si ig??ny adatok kezel??s??hez'),
        '#description' => $this->t('Hozz??j??rul??somat adom allergi??s ??s egy??b megbeteged??semre, p??l??m??retemre, sz??l??/gondvisel?? telefonsz??m??ra/??tkez??si ig??nyre vonatkoz?? adatok kezel??s??hez - azokra vonatkoz??an, mely inform??ci??kat jelen nyilatkozaton felt??ntettem - extra szolg??ltat??sok ny??jt??sa c??lj??b??l, hozz??j??rul??som visszavon??s??ig, de legfeljebb a G??lyat??bor rendezv??ny v??g??ig.'),
      ];

      $form['apply']['accept_photo'] = [
        '#type' => 'checkbox',
        '#title' => $this
          ->t('Hozz??j??rul??s fot?? ??s vide??felv??telek k??sz??t??s??hez'),
        '#description' => $this->t('Hozz??j??rul??somat adom, hogy a G??lyat??bor rendezv??ny sor??n fot?? ??s vide??felv??teleket k??sz??tsenek r??lam prom??ci??s ??s marketing c??lb??l, ??s azokat a G??lyat??bor befejez??s??t??l sz??m??tott egy ??ven ??t kezelj??k, felhaszn??lj??k.'),
        '#required' => TRUE,
      ];

      $form['apply']['accept_privacy'] = [
        '#type' => 'checkbox',
        '#title' => $this
          ->t('Adatkezel??s'),
        '#description' => $this->t('A r??szletes adatkezel??si t??j??koztat??t elolvastam ??s az abban foglaltakat elfogadom.'),
        '#required' => TRUE,
      ];

      $form['apply']['accept_rules'] = [
        '#type' => 'checkbox',
        '#title' => $this
          ->t('Nyilatkozat a h??zirend elfogad??s??r??l'),
        '#description' => $this->t('Nyilatkozom, hogy a rendezv??ny etikai k??dex??t ??s h??zirendj??t, valamint a befogad?? int??zm??ny h??zirendj??t megismertem, annak tartalm??t magamra n??zve k??telez??nek tekintem.'),
        '#required' => TRUE,
      ];

      $form['apply']['accept_responsibility'] = [
        '#type' => 'checkbox',
        '#title' => $this
          ->t('Nyilatkozat a szem??lyi ??s anyagi felel??ss??gr??l'),
        '#description' => $this->t('Nyilatkozom, hogy a rendezv??nyen saj??t szem??lyi ??s anyagi felel??ss??gem tudat??ban veszek r??szt.'),
        '#required' => TRUE,
      ];

      $form['apply']['actions']['#type'] = 'actions';
      $form['apply']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Send'),
        '#button_type' => 'primary',
      ];
    }

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    if (isset($form_state->getValues()['stay']) && (int) $form_state->getValues()['stay'] === 2 && empty($form_state->getValues()['stay_day'])) {
      $form_state->setErrorByName('stay_day', $this->t('Ha nem v??gig maradsz akkor ez k??telez??.'));
    }

    if (isset($form_state->getValues()['faculty']) && (int) $form_state->getValues()['faculty'] === 4 && empty($form_state->getValues()['etk_city'])) {
      $form_state->setErrorByName('etk_city', $this->t('ETK g??lyat??bor eset??n k??telez??.'));
    }
  }

  /**
   * @param $r
   * @param $s
   *
   * @return array|false
   */
  public static function checkHash($r, $s) {

    $config_factory = \Drupal::service('config.factory');
    $config = $config_factory->get(SettingsForm::SETTINGS);

    require_once dirname(__DIR__) . '/../sdk/SimplePayV21.php';
    $trx = new \SimplePayBack();

    $trx->addConfig([
      'HUF_MERCHANT' => $config->get('data')['huf_merchant'],
      'HUF_SECRET_KEY' => $config->get('data')['huf_secret_key'],
      'SANDBOX' => (bool) $config->get('environment') == 'Test',
    ]);

    if ($trx->isBackSignatureCheck($r, $s)) {
      return $trx->getRawNotification();
    }
    return FALSE;
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
    $trx->addData('total', $faculties_data[$selected_faculty]['price']);
    $trx->addData('orderRef', $order_id);

    $trx->addData('threeDSReqAuthMethod', '02');
    $trx->addData('customerEmail', $form_state->getValue('email'));
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

    $trx->addItems([
      'ref' => $faculties_data[$selected_faculty]['pst'],
      'title' => SettingsForm::FACULTIES[$selected_faculty],
      'amount' => 1,
      'price' => $faculties_data[$selected_faculty]['price'],
    ]);

    $trx->addGroupData('invoice', 'name', $form_state->getValue('full_name'));
    $trx->addGroupData('invoice', 'country', 'hu');
    $trx->addGroupData('invoice', 'city', $form_state->getValue('city'));
    $trx->addGroupData('invoice', 'zip', $form_state->getValue('postcode'));
    $trx->addGroupData('invoice', 'address', $form_state->getValue('street'));

    $trx->runStart();
    $simplepay_data = $trx->getReturnData();
    $this->logger('simplepay')->warning('<pre><code>' . print_r($this->simplepayConfig, TRUE) . '</code></pre>');
    $this->logger('simplepay')->warning('<pre><code>' . print_r($simplepay_data, TRUE) . '</code></pre>');

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
      'hash' => $simplepay_data['salt'],
      'order_id' => $order_id,
      'transaction_id' => $simplepay_data['transactionId'],
      'currency' => $currency,
      'pst' => $faculties_data[$selected_faculty]['pst'],
      'accept_nationality' => $form_state->getValue('accept_nationality'),
      'accept_other' => $form_state->getValue('accept_other'),
      'accept_photo' => $form_state->getValue('accept_photo'),
    ]);

    $this->tempStore->set('price', $faculties_data[$selected_faculty]['price']);
    $this->tempStore->set('order_id', $order_id);
    $this->tempStore->set('transaction_id', $simplepay_data['transactionId']);

    try {
      $payment->save();
      $response = new TrustedRedirectResponse(Url::fromUri($simplepay_data['paymentUrl'])->toString());
      $form_state->setResponse($response);
    }
    catch (EntityStorageException $e) {
      $this->logger('simplepay')->warning($e->getMessage());
    }
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function loadInfo(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('div.form-item-info', $form['apply']));
    return $response;
  }

}
