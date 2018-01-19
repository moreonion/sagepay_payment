<?php

namespace Drupal\sagepay_payment;

/**
 * Tests for the NotificationStatus object.
 */
class NotificationStatusTest extends \DrupalUnitTestCase {

  /**
   * Test constructung a status object using the error() method.
   */
  public function testError() {
    $n = NotificationStatus::error('Test error');
    $this->assertEqual('ERROR', $n->status);
    $this->assertEqual(url('/', ['absolute' => TRUE]), $n->redirect);
    $this->assertEqual('Test error', $n->details);
  }

  /**
   * Test constructung a status object using the invalid() method.
   */
  public function testInvalid() {
    $n = NotificationStatus::invalid('Test error');
    $this->assertEqual('INVALID', $n->status);
    $this->assertEqual(url('/', ['absolute' => TRUE]), $n->redirect);
    $this->assertEqual('Test error', $n->details);
  }

  /**
   * Test constructung a status object using the ok() method.
   */
  public function testOk() {
    $p = new \Payment(['pid' => 1]);
    $finish_url = "/sagepay_payment/finish/{$p->pid}";

    $n = NotificationStatus::ok($p);
    $this->assertEqual('OK', $n->status);
    $this->assertStringEndsWith($finish_url, $n->redirect);
    $this->assertEqual('', $n->details);

    $n = NotificationStatus::ok($p, 'test');
    $this->assertEqual('OK', $n->status);
    $this->assertStringEndsWith($finish_url, $n->redirect);
    $this->assertEqual('test', $n->details);
  }

   /**
   * Test the response method.
   */
  public function testResponse() {
    $n = new NotificationStatus('STATUS', 'redirect', 'details');
    $response = "Status=STATUS\r\nRedirectURL=redirect\r\nStatusDetail=details";
    $this->assertEqual($response, $n->response());
  }

}
