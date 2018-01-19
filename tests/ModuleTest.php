<?php

namespace Drupal\sagepay_payment;

/**
 * Tests for hook implementations, page and delivery callbacks.
 */
class ModuleTest extends \DrupalUnitTestCase {

  /**
   * Test the notification status delivery method.
   */
  public function testDeliverNotification() {
    $n = new NotificationStatus('STATUS', 'redirect', 'details');

    // drupal_page_footer() calls ob_flush() so we nest the function call in two
    // output buffers. One that is flushed and one that catches the flushed
    // content.

    $response = "Status=STATUS\r\nRedirectURL=redirect\r\nStatusDetail=details";
    ob_start();
    ob_start();
    sagepay_payment_deliver_notification($n);
    ob_end_clean();
    $r = ob_get_contents();
    ob_end_clean();
    $this->assertEqual($response, $r);

    $url = url('/', ['absolute' => TRUE]);
    $response = "Status=ERROR\r\nRedirectURL=$url\r\nStatusDetail=testing";
    ob_start();
    ob_start();
    sagepay_payment_deliver_notification('testing');
    ob_end_clean();
    $r = ob_get_contents();
    ob_end_clean();
    $this->assertEqual($response, $r);
  }

}
