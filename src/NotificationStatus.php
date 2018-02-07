<?php

namespace Drupal\sagepay_payment;

/**
 * Generate notification callback responses.
 */
class NotificationStatus {

  /**
   * @var string
   * Status code. Either OK, ERROR or INVALID.
   */
  public $status;

  /**
   * @var string
   * Human readable description of the response.
   */
  public $details;

  /**
   * @var string
   * The user is redirected to this URL after the payment.
   */
  public $redirect;

  /**
   * Construct new notification status instance.
   *
   * @param string $status
   *   A status code. Either OK, ERROR or INVALID.
   * @param string $redirect
   *   An absolute URL to redirect the user to.
   * @param string $details
   *   (optional) Human readable description of the response.
   */
  public function __construct($status, $redirect, $details = '') {
    $this->status = $status;
    $this->redirect = $redirect;
    $this->details = $details;
  }

  /**
   * Create an error response.
   *
   * @param string $details
   *   A human readable description of the error.
   */
  public static function error($details) {
    return new static('ERROR', url('/', ['absolute' => TRUE]), $details);
  }

  /**
   * Create an INVALID response.
   *
   * @param string $details
   *   A human readable description of the error.
   */
  public static function invalid($details) {
    return new static('INVALID', url('/', ['absolute' => TRUE]), $details);
  }

  /**
   * Create an affirmative response.
   *
   * @param \Payment $payment
   *   The payment this is the response for.
   */
  public static function ok(\Payment $payment, $details = '') {
    $hash = sagepay_payment_signature($payment->pid);
    $url = url("sagepay_payment/finish/{$payment->pid}/$hash", ['absolute' => TRUE]);
    return new static('OK', $url, $details);
  }

  /**
   * Generate string suitable for a response to a notification call.
   *
   * @return string
   *   Values encoded as a response.
   */
  public function response() {
    return implode("\r\n", [
      'Status=' . $this->status,
      'RedirectURL=' . $this->redirect,
      'StatusDetail=' . $this->details,
    ]);
  }

}
