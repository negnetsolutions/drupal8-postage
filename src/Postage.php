<?php

namespace Drupal\postage;

/**
 * Postage Mailer Class.
 */
class Postage {
  static protected $instance;
  private $message = [];
  private $apiKey = '8663433-3923-9572-1305-524689115863';
  private $debugMode = self::DEBUG_OFF;
  private $serverAddress = 'https://postage.negnetsolutions.com/compose';

  const DEBUG_OFF = 0;
  const DEBUG_VERBOSE = 1;

  /**
   * Instantiates an instance of self.
   */
  public static function compose() {
    return new self();
  }

  /**
   * Implements constructor.
   */
  public function __construct() {
    $this->message['Recipients'] = [];
  }

  /**
   * Sets the server's address.
   */
  public function setServer($address) {
    $this->serverAddress = $address;
    return $this;
  }

  /**
   * Sets the api key.
   */
  public function &setKey($key) {
    $this->apiKey = $key;
    return $this;
  }

  /**
   * Sets the class debug mode.
   */
  public function &debug($mode = self::DEBUG_VERBOSE) {
    $this->debugMode = $mode;
    return $this;
  }

  /**
   * Adds a recipient to the email.
   */
  public function addRecipient($type, $address, $name) {
    if (!$this->validateAddress($address)) {
      throw new InvalidArgumentException("Address \"{$address}\" is invalid");
    }

    if (!isset($this->message['Recipients'][$type])) {
      $this->message['Recipients'][$type] = [];
    }

    $this->message['Recipients'][$type][] = ['name' => $name, 'address' => $address];
  }

  /**
   * Sets a to recipient.
   */
  public function &addTo($address, $name = NULL) {
    $this->addRecipient('To', $address, $name);
    return $this;
  }

  /**
   * Sets a cc recipient.
   */
  public function &addCc($address, $name = NULL) {
    $this->addRecipient('Cc', $address, $name);
    return $this;
  }

  /**
   * Sets a BCC recipient.
   */
  public function &addBcc($address, $name = NULL) {
    $this->addRecipient('Bcc', $address, $name);
    return $this;
  }

  /**
   * Sets a from recipient.
   */
  public function &addFrom($address, $name = NULL) {
    $this->addRecipient('From', $address, $name);
    return $this;
  }

  /**
   * Sets the email subject.
   */
  public function &subject($subject) {
    $this->message['Subject'] = $subject;
    return $this;
  }

  /**
   * Sets a plain text email body.
   */
  public function &messagePlain($text) {
    $this->message['TextBody'] = $text;
    return $this;
  }

  /**
   * Sets a HTML email body.
   */
  public function &messageHtml($text) {
    $this->message['HtmlBody'] = $text;
    return $this;
  }

  /**
   * Validates the email message data.
   */
  private function validateData() {
    if (count($this->message['Recipients']) == 0) {
      throw new BadMethodCallException('No To address is set');
    }

    foreach ($this->message['Recipients'] as $type) {
      foreach ($type as $recipient) {
        if (!$this->validateAddress($recipient['address'])) {
          throw new BadMethodCallException($recipient['address'] . ' is not a valid email address!');
        }
      }
    }

    if (!isset($this->message['Subject'])) {
      throw new BadMethodCallException('Subject is not set');
    }
  }

  /**
   * Prepares Data for shipping to Postage.
   */
  private function prepareData() {
    $data = $this->message;

    foreach ($data['Recipients'] as $key => $type) {
      $data[$key] = '';
      foreach ($type as $recipient) {
        $data[$key] .= $this->createAddress($recipient['address'], $recipient['name']) . ', ';
      }

      $data[$key] = substr($data[$key], 0, strlen($data[$key]) - 2);
    }

    unset($data['Recipients']);

    return $data;
  }

  /**
   * Sends an email to Postage.
   */
  public function send() {
    $this->validateData();
    $data = $this->prepareData();

    $headers = [
      'Accept: application/json',
      'Content-Type: application/json',
      'X-Server-Token: ' . $this->apiKey,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->serverAddress);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $return = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($curlError !== '') {
      throw new \Exception($curlError);
    }

    if (!$this->isTwoHundred($httpCode)) {
      if ($httpCode == 422) {
        $return = json_decode($return);
        throw new \Exception($return->Message, $return->ErrorCode);
      }
      else {
        throw new \Exception("Error while mailing. Mailer returned HTTP code {$httpCode} with message \"{$return}\"", $httpCode);
      }
    }

    // Check mailer status.
    $mail_status = json_decode($return, TRUE);

    if ($mail_status['Message'] == 'ERROR') {
      throw new \Exception($mail_status['ErrorCode']);
    }

    if (($this->debugMode & self::DEBUG_VERBOSE) === self::DEBUG_VERBOSE) {
      echo "<pre>" . print_r([
        'json' => json_encode($data),
        'headers' => $headers,
        'return' => $return,
        'curlError' => $curlError,
        'httpCode' => $httpCode,
        'mail_status' => $mail_status,
      ], TRUE) . "</pre>";
    }

    unset($this->message);

    return TRUE;
  }

  /**
   * Validates an email address.
   */
  public function validateAddress($email) {
    $regex = "/^([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*[\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?)$/i";
    return preg_match($regex, $email) === 1;
  }

  /**
   * Checks to see that the value = 200.
   */
  private function isTwoHundred($value) {
    return intval($value / 100) == 2;
  }

  /**
   * Creates a recipient addressed line.
   */
  private function createAddress($address, $name = NULL) {
    if (isset($name)) {
      return '"' . str_replace('"', '', $name) . '" <' . $address . '>';
    }
    else {
      return $address;
    }
  }

}
