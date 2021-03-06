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

  /**
   * Create a sagepay basket based on a payment.
   *
   * @param \Payment $payment
   *   The payment to create the basket for.
   *
   * @return \Drupal\sagepay_payment\Sagepay\Basket
   *   The fully configured basket object.
   */
  public function createBasket(\Payment $payment) {
    $basket = new Basket();
    $basket->setDescription($payment->description);

    foreach ($payment->line_items as $pitem) {
      $net_amount = $pitem->unitAmount(FALSE);
      $item = new Item();
      $item->setDescription($pitem->description);
      $item->setUnitNetAmount($net_amount);
      $item->setUnitTaxAmount($pitem->unitAmount(TRUE) - $net_amount);
      $item->setQuantity($pitem->quantity);
      $basket->addItem($item);
    }
    return $basket;
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
    $api->setBasket($this->createBasket($payment));
    $api->addAddress($address);
    $api->addAddress($address);
    $result = $api->createRequest();
    if ($result['Status'] == 'OK') {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_PENDING));
      $payment->sagepay = [
        'securitykey' => $result['SecurityKey'],
        'vpstxid' => $result['VPSTxId'],
      ];
      entity_save('payment', $payment);
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

  /**
   * Define columns for the webform data export.
   */
  public function webformDataInfo() {
    $info['transaction_id'] = t('Transaction ID');
    return $info;
  }

  /**
   * Generate data for the webform export.
   */
  public function webformData(\Payment $payment) {
    $data['transaction_id'] = $payment->sagepay['vpstxid'] ?? '';
    return $data;
  }

}
