<?php

namespace Drupal\sagepay_payment;
use \Drupal\payment_forms\PaymentContextInterface;

class RedirectForm extends \Drupal\payment_forms\OnlineBankingForm {
  public function getForm(array &$element, array &$form_state, PaymentContextInterface $context) {
    $payment = $form_state['payment'];
    $data = $payment->method->controller_data['personal_data'];
    $default = array('keys' => array());

    $pd = static::extraElements();
    $all = TRUE;
    foreach ($pd['address'] as $controller_key => &$field) {
      $config = isset($data[$controller_key]) ? $data[$controller_key] + $default : $default;
      if (!empty($field['#required'])) {
        $field['#controller_required'] = $field['#required'];
        unset($field['#required']);
      }
      if ($context) {
        foreach ($config['keys'] as $key) {
          if ($value = $context->value($key)) {
            $field['#default_value'] = $value;
            break;
          }
        }
      }
      $all = $all && !empty($field['#default_value']);
    }
    $pd['address']['#default_value'] = $all;

    foreach ($pd as $controller_key => &$field) {
      if (!empty($field['#required'])) {
        $field['#controller_required'] = $field['#required'];
        unset($field['#required']);
      }
      $config = isset($data[$controller_key]) ? $data[$controller_key] + $default : $default;
      if ($context) {
        foreach ($config['keys'] as $key) {
          if ($value = $context->value($key)) {
            $field['#default_value'] = $value;
            break;
          }
        }
        $field['#access'] = $this->shouldDisplay($field, $config);
      }
    }

    $element['personal_data'] = $pd + array(
      '#type' => 'container',
    );
    $element += parent::getForm($element, $form_state, $context);
    $element['redirection_info']['#weight'] = 100;
    return $element;
  }

  protected function setRequiredError(array &$elements) {
    if (isset($elements['#title'])) {
      form_error($elements, t('!name field is required.', array('!name' => $elements['#title'])));
    }
    else {
      form_error($elements);
    }
  }

  public function validateForm(array &$element, array &$form_state) {
    $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    $pd = &$element['personal_data'];
    foreach ($pd['address'] as $controller_key => &$field) {
      if (!empty($field['#controller_required']) && empty($values['personal_data']['address'][$controller_key])) {
        $this->setRequiredError($field);
      }
    }
    foreach ($pd as $controller_key => &$field) {
      if (!empty($field['#controller_required']) && empty($values['personal_data'][$controller_key])) {
        $this->setRequiredError($field);
      }
    }
    $values += $values['personal_data'];
    unset($values['personal_data']);
    $form_state['payment']->method_data += $values;
  }

  protected function shouldDisplay(&$field, &$config) {
    return ($config['display'] == 'always') || (empty($field['#default_value']) && $config['display'] == 'ifnotset');
  }

  public static function extraElements() {
    require_once DRUPAL_ROOT . '/includes/locale.inc';
    $element['firstname'] = array(
      '#type' => 'textfield',
      '#title' => t('First name'),
      '#required' => TRUE,
    );
    $element['lastname'] = array(
      '#type' => 'textfield',
      '#title' => t('Last name'),
      '#required' => TRUE,
    );
    $element['email'] = array(
      '#type' => 'textfield',
      '#title' => t('Email'),
    );
    $element['phone'] = array(
      '#type' => 'textfield',
      '#title' => t('Phone number'),
    );
    $element['address'] = array(
      '#title' => t('Address'),
      '#type' => 'fieldset',
    );
    $element['address']['address1'] = array(
      '#type' => 'textfield',
      '#title' => t('Street address'),
      '#required' => TRUE,
    );
    $element['address']['city'] = array(
      '#type' => 'textfield',
      '#title' => t('City'),
      '#required' => TRUE,
    );
    $element['address']['postcode'] = array(
      '#type' => 'textfield',
      '#title' => t('Postcode'),
      '#required' => TRUE,
    );
    $element['address']['state'] = array(
      '#type' => 'textfield',
      '#title' => t('State'),
    );
    $element['address']['country'] = array(
      '#type' => 'select',
      '#options' => country_get_list(),
      '#title' => t('County'),
      '#required' => TRUE,
    );
    return $element;
  }

  public static function flatten(&$elements) {
    $fieldsets = array($elements);
    $flat = array();
    while ($fs = array_shift($fieldsets)) {
      foreach ($fs as $key => &$subelem) {
        if (is_array($subelem)) {
          if ($subelem['#type'] == 'fieldset') {
            $fieldsets[] = &$subelem;
          }
          $flat[$key] = &$subelem;
        }
      }
    }
    return $flat;
  }
}

