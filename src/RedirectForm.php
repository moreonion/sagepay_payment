<?php

namespace Drupal\sagepay_payment;

class RedirectForm extends \Drupal\payment_forms\OnlineBankingForm {

  /**
   * Helper method to apply a function recursively to all elements.
   */
  protected function doRec(&$element, $func) {
    foreach (element_children($element) as $key) {
      $this->doRec($element[$key], $func);
      $func($element[$key], $key);
    }
  }

  public function form(array $element, array &$form_state, \Payment $payment) {
    $context = $payment->contextObj;
    $data = $payment->method->controller_data['personal_data'];

    $pd = static::extraElements() + ['#type' => 'container'];

   // Add defaults for all elements.
    $this->doRec($pd, function(&$e, $key) use (&$data) {
      if ($e['#type'] != 'fieldset') {
        $data += [$key => []];
        $data[$key] += [
          'keys' => [],
          'mandatory' => FALSE,
          'display' => 'ifnotset',
          'display_other' => 'always',
        ];
      }
    });

    // Set default values from context and remove #required.
    if ($context) {
      $this->doRec($pd, function(&$e, $key) use ($context, $data) {
        if ($e['#type'] != 'fieldset') {
          foreach ($data[$key]['keys'] as $k) {
            if ($value = $context->value($k)) {
              $e['#default_value'] = $value;
              break;
            }
          }
        }
        $e['#controller_required'] = !empty($e['#required']);
        unset($e['#required']);
      });
    }

    $display = function ($e, $display) {
      return ($display == 'always') || (empty($e['#default_value']) && $display == 'ifnotset');
    };

    // Set visibility.
    $this->doRec($pd, function(&$e, $key) use ($data, $display) {
      if ($e['#type'] == 'fieldset') {
        $access = FALSE;
        foreach (element_children($e) as $k) {
          if ($e[$k]['#access']) {
            $access = TRUE;
            break;
          }
        }
        $e['#access'] = $access;
        if ($access) {
          // Give child elements a chance to be displayed if other childs are.
          foreach (element_children($e) as $k) {
            $c = &$e[$k];
            if ($c['#type'] != 'fieldset' && !$c['#access']) {
              $c['#access'] = $display($c, $data[$k]['display_other']);
            }
          }
        }
      }
      else {
        $e['#access'] = $display($e, $data[$key]['display']);
      }
    });

    $element['personal_data'] = $pd;
    $element += parent::form($element, $form_state, $payment);
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

  public function validate(array $element, array &$form_state, \Payment $payment) {
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
    $payment->method_data += $values;
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
      '#required' => TRUE,
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
      '#title' => t('Country'),
      '#required' => TRUE,
    );
    return $element;
  }

  public static function flatten($elements) {
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
