<?php

namespace Drupal\sagepay_payment;

use Drupal\sagepay_payment\Sagepay\Basket;
use Drupal\sagepay_payment\Sagepay\CustomerDetails;
use Drupal\sagepay_payment\Sagepay\Item;
use Drupal\sagepay_payment\Sagepay\ServerApi;
use Drupal\sagepay_payment\Sagepay\Settings;

/**
 * Payment controller for the SagePay server integration.
 *
 * @link https://www.sagepay.co.uk/support/find-an-integration-document/server-integration-documents SagePay documentation @endlink
 */
class Controller extends \PaymentMethodController {

  public $controller_data_defaults = array(
    'testmode' => '0',
    'partnerid' => '',
    'vendorname' => '',
    'personal_data' => array(),
  );

  public function __construct() {
    $this->title = t('Sage Pay');

    $this->payment_configuration_form_elements_callback = 'payment_forms_payment_form';
    $this->payment_method_configuration_form_elements_callback = 'payment_forms_method_configuration_form';
  }

  public function paymentForm() {
    return new RedirectForm();
  }

  public function configurationForm() {
    return new ControllerForm();
  }

  public function execute(\Payment $payment) {
    $md = $payment->method_data;
    $test_mode = $payment->method->controller_data['testmode'];
    $partner_id = $payment->method->controller_data['partnerid'];
    $vendor_name = $payment->method->controller_data['vendorname'];

    $config = Settings::getInstance(
      array(
        'env' => $test_mode ? 'test' : 'live',
        'currency' => $payment->currency_code,
        'vendorName' => $vendor_name,
        'partnerId' => isset($partner_id) ? $partner_id : '',
        'siteFqdns' => array(
          'live' => $GLOBALS['base_url'],
          'test' => $GLOBALS['base_url'],
        ),
        'serverNotificationUrl' => '/sagepay_payment/notify',
        'customerPasswordSalt' => drupal_get_hash_salt(),
        'website' => $GLOBALS['base_url'],
      ),
      FALSE
    );

    $basket = new Basket();
    $basket->setDescription($payment->description);

    $item = new Item();
    $item->setDescription($payment->description);
    $item->setUnitNetAmount($payment->totalAmount(TRUE));
    $item->setQuantity(1);
    $basket->addItem($item);

    $address = new CustomerDetails();
    $address->firstname = $md['firstname'];
    $address->lastname = $md['lastname'];
    $address->email = $md['email'];
    $address->address1 = $test_mode ? '88' : $md['address']['address1'];
    $address->phone = $md['phone'];
    $address->city = $md['address']['city'];
    $address->postcode = $test_mode ? '412' : $md['address']['postcode'];
    $address->country = $md['address']['country'];
    $address->state = $md['address']['state'];
    if ($address->country === 'UK') {
      $address->country = 'GB';
    };

    $api = new ServerApi($config);
    $api->setBasket($basket);
    $api->addAddress($address);
    $api->addAddress($address);
    $result = $api->createRequest();
    if ($result['Status'] == 'OK') {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_PENDING));
      entity_save('payment', $payment);
      $params = array(
        'pid' => $payment->pid,
        'securitykey' => $result['SecurityKey'],
        'vpstxid' => $result['VPSTxId'],
      );
      drupal_write_record('sagepay_payment_payments', $params);
      $payment->contextObj->redirect($result['NextURL']);
    }
    else {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_FAILED));

      $message = '::@method:: encountered an error while contacting ' .
        'the SagePay server. The status code was ::@status:: and the StatusDetail ' .
        '::@statusdetail:: (pid: @pid, pmid: @pmid)';
      $variables = array(
        '@status'       => $result['Status'],
        '@statusdetail' => $result['StatusDetail'],
        '@pid'          => $payment->pid,
        '@pmid'         => $payment->method->pmid,
        '@method'       => $payment->method->title_specific,
      );
      watchdog('sagepay_payment', $message, $variables, WATCHDOG_ERROR);
    }
  }

}
