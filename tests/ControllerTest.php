<?php

namespace Drupal\sagepay_payment;

/**
 * Test the payment controller.
 */
class ControllerTest extends \DrupalUnitTestCase {

  /**
   * Test creating a basket for an empty payment.
   */
  public function testCreateBasketFromEmptyPayment() {
    $controller = new Controller();
    $payment = entity_create('payment', []);
    $basket = $controller->createBasket($payment);
    $this->assertEqual(0, $basket->getAmount());
    $this->assertEmpty($basket->getItems());
    $this->assertEmpty($basket->getDescription());
  }

  /**
   * Test basket with two line items.
   */
  public function testCreateBasketWithTwoLineItems() {
    $controller = new Controller();
    $payment = entity_create('payment', [
      'description' => 'testPayment',
    ]);
    $payment->setLineItem(new \PaymentLineItem([
      'name' => 'item1',
      'description' => 'item1',
      'amount' => 10.0,
      'tax_rate' => 0.2,
      'quantity' => 2,
    ]));
    $payment->setLineItem(new \PaymentLineItem([
      'name' => 'item2',
      'description' => 'item2',
      'amount' => 11.0,
      'tax_rate' => 0.0,
      'quantity' => 1,
    ]));
    $basket = $controller->createBasket($payment);
    $this->assertEqual(35.0, $basket->getAmount());
    $this->assertCount(2, $basket->getItems());
    $this->assertEqual($payment->description, $basket->getDescription());
  }

}
