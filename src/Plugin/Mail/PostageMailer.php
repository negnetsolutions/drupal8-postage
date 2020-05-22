<?php

namespace Drupal\postage\Plugin\Mail;

use Drupal\postage\Postage;
use Drupal\Core\Mail\MailInterface;

/**
 * Postage Mailer Backend.
 *
 * @Mail(
 *  id = "postage_mailer",
 *  label = @Translation("Postage Mailer"),
 *  description = @Translation("Postage Mailer implementation.")
 *  )
 */
class PostageMailer implements MailInterface {

  /**
   * Inplments MailInterface->format().
   */
  public function format(array $message) {
    return $message;
  }

  /**
   * Implements MailInterface->mail().
   */
  public function mail(array $message) {
    $mail = new postage();
    $config = \Drupal::config('postage.settings');

    // Set postage's api key.
    $mail->setKey($config->get('api_key'));

    // Parse 'From' e-mail address.
    $address = $this->parseAddress($message['from']);

    if ($address[0]['name'] == '') {
      $mail->addFrom($address[0]['mail']);
    }
    else {
      $mail->addFrom($address[0]['mail'], $address[0]['name']);
    }

    unset($message['headers']['From']);

    foreach ($this->parseAddress($message['to']) as $id => $address) {
      $mail->addTo($address['mail'], ($address['name'] != '') ? $address['name'] : NULL);
    }

    $mail->subject($message['subject']);

    if (is_array($message['body'])) {
      $body = '';
      foreach ($message['body'] as $m) {
        $body .= $m;
      }
    }
    else {
      $body = $message['body'];
    }

    // Check the header content type to see if email is plain text
    // if not we send as HTML.
    if (strpos($message['headers']['Content-Type'], 'text/plain') !== FALSE) {
      $mail->messagePlain($body);
    }
    else {
      $mail->messageHtml($body);
    }

    try {
      if (!($result = $mail->send())) {
        \Drupal::logger('postage')->error("Mail sending error: " . $mail->ErrorInfo);
      }

      return $result;
    }
    catch (Exception $e) {
      \Drupal::logger('postage')->error('Exception message: ' . $e->getMessage());
      \Drupal::messenger()->addError('Mail sending error: ' . $e->getMessage(), TRUE);
    }

    return FALSE;
  }

  /**
   * Parses email addresses for name and email.
   */
  protected function parseAddress($address) {
    $parsed = [];
    $regexp = "/^(.*) <([a-z0-9]+(?:[_\\.-][a-z0-9]+)*@(?:[a-z0-9]+(?:[\.-][a-z0-9]+)*)+\\.[a-z]{2,})>$/i";

    // Split multiple addresses and process each.
    foreach (explode(',', $address) as $email) {
      $email = trim($email);
      if (preg_match($regexp, $email, $matches)) {
        $parsed[] = ['mail' => $matches[2], 'name' => trim($matches[1], '"')];
      }
      else {
        $parsed[] = ['mail' => $email, 'name' => ''];
      }
    }
    return $parsed;
  }

}
