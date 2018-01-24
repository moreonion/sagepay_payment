<?php

namespace Drupal\sagepay_payment;

/**
 * Integration test for the notification callback.
 */
class NotificationIntegrationTest extends \DrupalWebTestCase {

  /**
   * Create a test payment and payment method.
   */
  public function setUp() {
    $method = new \PaymentMethod([
      'controller' => new Controller(),
      'controller_data' => ['vendorname' => 'vendor1'],
    ]);
    entity_save('payment_method', $method);
    $p = new \Payment([
      'method' => $method,
      'contextObj' => NULL,
      'finish_callback' => 'sagepay_payment_test_finish',
    ]);
    entity_save('payment', $p);
    db_insert('sagepay_payment_payments')
      ->fields([
        'pid' => $p->pid,
        'vpstxid' => 'test',
        'securitykey' => 'testkey',
      ])->execute();
    $this->payment = $p;
  }

  /**
   * Remove the test data.
   */
  public function tearDown() {
    entity_delete('payment', $this->payment->pid);
    entity_delete('payment_method', $this->payment->method->pmid);
    db_delete('sagepay_payment_payments')
      ->condition('pid', $this->payment->pid)
      ->execute();
  }

  /**
   * Helper function to parse a notification response into an array.
   *
   * @param string $str
   *   A response from the notification callback.
   *
   * @return array
   *   An associative array with the parsed values.
   */
  protected function parseResponse($str) {
    $data = [];
    foreach (explode("\r\n", $str) as $line) {
      $p = explode('=', $line, 2);
      $data[$p[0]] = $p[1];
    }
    return $data;
  }

  /**
   * Test notification and finish callback for a successful payment.
   */
  public function testSuccessfulPayment() {
    $absolute = ['absolute' => TRUE];
    $url = url('/sagepay_payment/notify', $absolute);

    // Call the notification URL for the test-payment.
    $options['method'] = 'POST';
    $options['data'] = http_build_query([
      'VPSTxId' => 'test',
      'VPSSignature' => 'B778438713084BA8219B8554B7D254DC',
      'Status' => 'OK',
    ]);
    $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
    $resp = drupal_http_request($url, $options);
    $this->assertEqual(200, $resp->code);
    $data = $this->parseResponse($resp->data);
    $this->assertEqual('OK', $data['Status']);
    $url = url("/sagepay_payment/finish/{$this->payment->pid}", $absolute);
    $this->assertStringStartsWith($url, $data['RedirectURL']);

    // Test that an invalid hash yields an access denied error.
    $no_redirect['max_redirects'] = 0;
    $resp = drupal_http_request($data['RedirectURL'] . '1', $no_redirect);
    $this->assertEqual(403, $resp->code);

    // Test that the valid hash yields a redirect.
    $resp = drupal_http_request($data['RedirectURL'], $no_redirect);
    $this->assertEqual(302, $resp->code);
  }

}
