<?php

namespace Drupal\sagepay_payment;

use \Drupal\payment_forms\PaymentContextInterface;

class Controller extends \PaymentMethodController {
  public $controller_data_defaults = array(
    'testmode' => '0',
    'partnerid' => '',
    'vendorname' => '',
    'personal_data' => array(),
  );

  public function __construct() {
    $this->title = t('Sage Pay');
    $this->form = new RedirectForm();

    $this->payment_configuration_form_elements_callback = 'payment_forms_method_form';
    $this->payment_method_configuration_form_elements_callback = '\Drupal\sagepay_payment\configuration_form';
  }

  public function execute(\Payment $payment) {
    libraries_load('sagepay-php');
    $md = $payment->method_data;
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
      $payment->contextObj->redirect($result['NextURL']);
    } else {
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
        $method->controller_data['personal_data'] = unserialize($data['personal_data']);
        $method->controller_data += $method->controller->controller_data_defaults;
        $method->controller_data['personal_data'] += $method->controller->controller_data_defaults['personal_data'];
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
    $values['personal_data'] = serialize($values['personal_data']);
    $query->fields($values);
    $query->execute();
  }

  /**
   * Helper for entity_update().
   */
  public function update($method) {
    $query = db_update('sagepay_payment_payment_method_controller');
    $values = array_merge($method->controller_data, array('pmid' => $method->pmid));
    $values['personal_data'] = serialize($values['personal_data']);
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
  $method = $form_state['payment_method'];
  $controller_data = $method->controller_data + $method->controller->controller_data_defaults;

  $library = libraries_detect('sagepay-php');
  if (empty($library['installed'])) {
    drupal_set_message($library['error message'], 'error', FALSE);
  }

  $form['#tree'] = TRUE;
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

  $form['personal_data'] = array(
    '#type' => 'fieldset',
    '#title' => t('Personal data'),
    '#description' => t('Configure how personal data can be mapped from the payment context.'),
  );

  $display_options = array(
    'ifnotset' => t('Show field if it is not available from the context.'),
    'always' => t('Always show the field - prefill with context values.'),
  );
  $non_mandatory = array(
    'hidden' => t("Don't display, use values from context if available."),
  );
  $extra = RedirectForm::extraElements();

  foreach ($extra as $key => &$element) {
    $element['#top_level'] = TRUE;
  }

  $stored = &$controller_data['personal_data'];
  $flat = RedirectForm::flatten($extra);
  foreach ($flat as $key => &$element) {
    $defaults = isset($stored[$key]) ? $stored[$key] : array();
    $defaults += array('display' => 'ifnotset', 'keys' => array($key), 'mandatory' => FALSE);
    $form['personal_data'][$key] = array(
      '#type' => 'fieldset',
      '#title' => $element['#title'],
    );
    $required = !empty($element['#required']);
    $defaults['mandatory'] = $defaults['mandatory'] || $required;
    $id = drupal_html_id('controller_data_' . $key);
    if (!empty($element['#top_level'])) {
      $form['personal_data'][$key]['display'] = array(
        '#type' => 'select',
        '#title' => t('Display'),
        '#options' => ($required ? array() : $non_mandatory) + $display_options,
        '#default_value' => $defaults['display'],
        '#id' => $id,
      );
    }
    $form['personal_data'][$key]['mandatory'] = array(
      '#type' => 'checkbox',
      '#title' => t('Mandatory'),
      '#states' => array(
        'disabled' => array(
          "#$id" => array('value' => 'hidden'),
        ),
      ),
      '#default_value' => $defaults['mandatory'],
      '#access' => !$required,
    );
    if ($element['#type'] != 'fieldset') {
      $form['personal_data'][$key]['keys'] = array(
        '#type' => 'textfield',
        '#title' => t('Context keys'),
        '#description' => t('When building the form these (comma separated) keys are used to ask the Payment Context for a (default) value for this field.'),
        '#default_value' => implode(', ', $defaults['keys']),
      );
    }
  }
  return $form;
}

/**
 * Implements form validate callback for
 * \stripe_payment\configuration_form().
 */
function configuration_form_validate(array $element, array &$form_state) {
  $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
  $data = &$form_state['payment_method']->controller_data;
  $data['testmode'] = $values['testmode'];
  $data['partnerid'] = $values['partnerid'];
  $data['vendorname'] = $values['vendorname'];
  $data['personal_data'] = $values['personal_data'];
  foreach ($data['personal_data'] as &$field) {
    $field += array('keys' => '');
    $field['keys'] = array_map('trim', explode(',', $field['keys']));
  }
}
