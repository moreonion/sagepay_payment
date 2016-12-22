<?php

namespace Drupal\sagepay_payment;

class ControllerForm implements \Drupal\payment_forms\MethodFormInterface {

  /**
   * Add form elements to the $element Form-API array.
   */
  public function form(array $form, array &$form_state, \PaymentMethod $method) {
    $controller_data = $method->controller_data;

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
   * Validate the submitted values.
   */
  public function validate(array $element, array &$form_state, \PaymentMethod $method) {
    $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    $data = &$method->controller_data;
    $data['testmode'] = $values['testmode'];
    $data['partnerid'] = $values['partnerid'];
    $data['vendorname'] = $values['vendorname'];
    $data['personal_data'] = $values['personal_data'];
    foreach ($data['personal_data'] as &$field) {
      $field += array('keys' => '');
      $field['keys'] = array_map('trim', explode(',', $field['keys']));
    }
  }

}
