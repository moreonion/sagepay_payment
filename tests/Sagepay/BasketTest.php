<?php

namespace Drupal\sagepay_payment\Sagepay;

class BasketTest extends \DrupalUnitTestCase {
  public function test_toXml_simple() {
    $basket = new Basket();
    $basket->setDescription('Basket description');

    $item = new Item();
    $item->setDescription('Item description');
    $item->setUnitNetAmount(42);
    $item->setQuantity(1);
    $basket->addItem($item);

    $this->assertEqual('<basket><item><description>Item description</description><quantity>1</quantity><unitNetAmount>42.00</unitNetAmount><unitTaxAmount>0.00</unitTaxAmount><unitGrossAmount>42.00</unitGrossAmount><totalGrossAmount>42.00</totalGrossAmount></item></basket>', $basket->exportAsXml());
  }

  public function test_toXml_withBigAmount() {
    $basket = new Basket();
    $basket->setDescription('Basket description');

    $item = new Item();
    $item->setDescription('Item description');
    $item->setUnitNetAmount(4242);
    $item->setQuantity(1);
    $basket->addItem($item);

    $this->assertEqual('<basket><item><description>Item description</description><quantity>1</quantity><unitNetAmount>4242.00</unitNetAmount><unitTaxAmount>0.00</unitTaxAmount><unitGrossAmount>4242.00</unitGrossAmount><totalGrossAmount>4242.00</totalGrossAmount></item></basket>', $basket->exportAsXml());
  }

}
