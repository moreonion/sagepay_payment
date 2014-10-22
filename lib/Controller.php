<?php

namespace Drupal\sagepay_payment;

class Controller extends \PaymentMethodController {
  public $controller_data_defaults = array(
    'testmode' => '0',
    'partnerid' => '',
    'vendorname' => ''
  );

  public function __construct() {
    $this->title = t('Sage Pay');
    $this->form = new \Drupal\payment_forms\OnlineBankingForm();

    $this->payment_configuration_form_elements_callback = 'payment_forms_method_form';
    $this->payment_method_configuration_form_elements_callback = '\Drupal\sagepay_payment\configuration_form';
  }

  public function execute(\Payment $payment) {
    libraries_load('sagepay-php');
    $context = &$payment->context_data['context'];
    $test_mode = $payment->method->controller_data['testmode'];
    $partner_id = $payment->method->controller_data['partnerid'];
    $vendor_name = $payment->method->controller_data['vendorname'];

    $config = \SagepaySettings::getInstance(
      array(
        'env' => $test_mode ? 'test': 'live',
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
      false
    );

    $basket = new \SagepayBasket();
    $basket->setDescription($payment->description);

    $item = new \SagepayItem();
    $item->setDescription($payment->description);
    $item->setUnitNetAmount($payment->totalAmount(TRUE));
    $item->setQuantity(1);
    $basket->addItem($item);

    $address = new \SagepayCustomerDetails();
    $address->firstname = $context->value('first_name');
    $address->lastname = $context->value('last_name');
    $address->email = $context->value('email');
    $address->address1 = $test_mode ? '88' : $context->value('street_address');
    $address->phone = $context->value('mobile_number');
    $address->city = $context->value('city');
    $address->postcode = $test_mode ? '412' : $context->value('zip_code');
    $address->country = $context->value('country');
    $address->state = $context->value('state');
    if ($address->country === 'GB') {
      $address->country = 'UK';
    };

    $api = \SagepayApiFactory::create('server', $config);
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
      $payment->form_state['redirect'] = $result['NextURL'];
    } else {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_FAILED));
      entity_save('payment', $payment);
    }
  }

  /**
   * Helper for entity_load().
   */
  public static function load($entities) {
    $pmids = array();
    foreach ($entities as $method) {
      if ($method->controller instanceof Controller) {
        $pmids[] = $method->pmid;
      }
    }
    if ($pmids) {
      $query = db_select('sagepay_payment_payment_method_controller', 'controller')
        ->fields('controller')
        ->condition('pmid', $pmids);
      $result = $query->execute();
      while ($data = $result->fetchAssoc()) {
        $method = $entities[$data['pmid']];
        unset($data['pmid']);
        $method->controller_data = (array) $data;
        $method->controller_data += $method->controller->controller_data_defaults;
      }
    }
  }

  /**
   * Helper for entity_insert().
   */
  public function insert($method) {
    $method->controller_data += $this->controller_data_defaults;
    $query = db_insert('sagepay_payment_payment_method_controller');
    $values = array_merge($method->controller_data, array('pmid' => $method->pmid));
    $query->fields($values);
    $query->execute();
  }

  /**
   * Helper for entity_update().
   */
  public function update($method) {
    $query = db_update('sagepay_payment_payment_method_controller');
    $values = array_merge($method->controller_data, array('pmid' => $method->pmid));
    $query->fields($values);
    $query->condition('pmid', $method->pmid);
    $query->execute();
  }

  /**
   * Helper for entity_delete().
   */
  public function delete($method) {
    db_delete('sagepay_payment_payment_method_controller')
      ->condition('pmid', $method->pmid)
      ->execute();
  }
}

/* Implements PaymentMethodController::payment_method_configuration_form_elements_callback().
 *
 * @return array
 *   A Drupal form.
 */
function configuration_form(array $form, array &$form_state) {
  $controller_data = $form_state['payment_method']->controller_data;

  $library = libraries_detect('sagepay-php');
  if (empty($library['installed'])) {
    drupal_set_message($library['error message'], 'error', FALSE);
  }

  $form['testmode'] = array(
    '#type' => 'checkbox',
    '#title' => t('test mode'),
    '#default_value' => isset($controller_data['testmode']) ? $controller_data['testmode'] : '',
  );

  $form['vendorname'] = array(
    '#type' => 'textfield',
    '#title' => t('Vendor name'),
    '#required' => true,
    '#default_value' => isset($controller_data['vendorname']) ? $controller_data['vendorname'] : '',
  );

  $form['partnerid'] = array(
    '#type' => 'textfield',
    '#title' => t('Partner ID'),
    '#required' => false,
    '#default_value' => isset($controller_data['partnerid']) ? $controller_data['partnerid'] : '',
  );

  return $form;
}

/**
 * Implements form validate callback for
 * \stripe_payment\configuration_form().
 */
function configuration_form_validate(array $element, array &$form_state) {
  $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
  $form_state['payment_method']->controller_data['testmode'] = $values['testmode'];
  $form_state['payment_method']->controller_data['partnerid'] = $values['partnerid'];
  $form_state['payment_method']->controller_data['vendorname'] = $values['vendorname'];
}
