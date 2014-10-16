<?php

namespace Drupal\sagepay_payment;

class Controller extends \PaymentMethodController {
  public $controller_data_defaults = array(
    'testmode' => '0',
    'partnerid'  => '',
  );

  public function __construct() {
    $this->title = t('Sage Pay');
    $this->form = new \Drupal\payment_forms\OnlineBankingForm();

    $this->payment_configuration_form_elements_callback = 'payment_forms_method_form';
    $this->payment_method_configuration_form_elements_callback = '\Drupal\sagepay_payment\configuration_form';
  }

  public function validate(\Payment $payment, \PaymentMethod $payment_method, $strict) {
    // convert amount to cents.
    foreach ($payment->line_items as $name => &$line_item) {
      $line_item->amount = $line_item->amount * 100;
    }
  }

  public function execute(\Payment $payment) {
    libraries_load('sagepay-php');

    $context = &$payment->context_data['context'];
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
}
