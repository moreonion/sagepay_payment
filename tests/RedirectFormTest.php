<?php

namespace Drupal\sagepay_payment;

use \Drupal\campaignion\CRM\Import\Source\ArraySource;

class RedirectFormTest extends \DrupalUnitTestCase {

  protected function mockPayment($data) {
    $cd['personal_data'] = [];
    $elements = RedirectForm::flatten(RedirectForm::extraElements());
    foreach (element_children($elements) as $k) {
      $cd['personal_data'][$k] = [
        'keys' => [$k],
        'display' => !empty($elements[$k]['#required']) ? 'ifnotset' : 'hidden',
        'mandatory' => FALSE,
      ];
    }
    return entity_create('payment', [
      'method' => entity_create('payment_method', [
        'controller_data' => $cd,
      ]),
      'contextObj' => new ArraySource($data),
    ]);
  }

  /**
   * Test that all fields are hidden, if all required data is supplied.
   */
  public function testFormAllRequiredDataFieldsHidden() {
    $form = new RedirectForm();
    $form_state = [];
    $payment = $this->mockPayment([
      'firstname' => 'First',
      'lastname' => 'Last',
      'address1' => 'Address1',
      'city' => 'City',
      'postcode' => 'TEST',
      'country' => 'GB',
    ]);
    $element = $form->form([], $form_state, $payment);
    foreach (element_children($element['personal_data']) as $key) {
      $e = $element['personal_data'][$key];
      $this->assertFalse($e['#access'], "Field $key should be hidden (#access = FALSE).");
    }
  }

  /**
   * Test that address fieldset shows if a required field is missing.
   */
  public function testFormCityMissingAddressVisible() {
    $form = new RedirectForm();
    $form_state = [];
    $payment = $this->mockPayment([
      'firstname' => 'First',
      'lastname' => 'Last',
      'address1' => 'Address1',
      'postcode' => 'TEST',
      'country' => 'GB',
    ]);
    $element = $form->form([], $form_state, $payment);
    $pd = $element['personal_data'];
    $pd['address'] += ['#access' => TRUE];
    $this->assertTrue($pd['address']['#access']);
  }

  /**
   * Test that address fields are shown if country is missing.
   */
  public function testFormCountryMissingAddressVisible() {
    $form = new RedirectForm();
    $form_state = [];
    $payment = $this->mockPayment([
      'firstname' => 'First',
      'lastname' => 'Last',
      'address1' => 'Address1',
      'postcode' => 'TEST',
      'city' => 'London',
    ]);
    $element = $form->form([], $form_state, $payment);
    $pd = $element['personal_data'];
    $pd['address'] += ['#access' => TRUE];
    $this->assertTrue($pd['address']['#access']);
    $this->assertTrue($pd['address']['city']['#access']);
    $this->assertEquals('London', $pd['address']['city']['#default_value']);
  }


}
