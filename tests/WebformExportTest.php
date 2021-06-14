<?php

namespace Drupal\sagepay_payment;

use Upal\DrupalUnitTestCase;

/**
 * Test hooks other module functions.
 */
class WebformExportTest extends DrupalUnitTestCase {

  /**
   * Create a test payment and payment method.
   */
  public function setUp() : void {
    parent::setUp();
    $this->method = entity_create('payment_method', [
      'name' => 'sagepay_test',
      'module' => 'sagepay_payment',
      'title_specific' => 'Sagepay test',
      'title_generic' => 'Sagepay',
      'controller' => new Controller(),
      'controller_data' => ['vendorname' => 'vendor1'],
    ]);
    $this->method->controller->name = '\\' . Controller::class;
    entity_save('payment_method', $this->method);
    $this->method = entity_load_single('payment_method', $this->method->pmid);
    $this->payment = new \Payment([
      'method' => $this->method,
      'contextObj' => NULL,
      'finish_callback' => 'sagepay_payment_test_finish',
    ]);
    $this->payment->sagepay = [
      'vpstxid' => 'test-transaction-id',
      'securitykey' => 'test-key',
    ];
    entity_save('payment', $this->payment);
    // Reload the payment to test entity_load hooks as well.
    $this->payment = entity_load_single('payment', $this->payment->pid);
  }

  /**
   * Delete the test payment and payment method.S
   */
  public function tearDown(): void {
    entity_delete('payment', $this->payment->pid);
    entity_delete('payment_method', $this->method->pmid);
    parent::tearDown();
  }

  /**
   * Test getting the CSV export header and data for a payment.
   */
  public function testExport() {
    $controller = $this->payment->method->controller;
    $this->assertEqual([
      'transaction_id' => t('Transaction ID'),
    ], $controller->webformDataInfo());
    $this->assertEqual([
      'transaction_id' => 'test-transaction-id',
    ], $controller->webformData($this->payment));
  }

}
