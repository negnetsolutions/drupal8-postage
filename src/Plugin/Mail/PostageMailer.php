<?php

namespace Drupal\postage\Plugin\Mail;

use Drupal\postage\Postage;
use Drupal\Core\Mail\MailInterface;

/**
 * Postage Mailer Backend
 *
 * @Mail(
 *  id = "postage_mailer",
 *  label = @Translation("Postage Mailer"),
 *  description = @Translation("Postage Mailer implementation.")
 *  )
 */
class PostageMailer implements MailInterface
{
  public function format(array $message)
  {
    // $this->AllowHtml = variable_get('smtp_allowhtml', 1);
    // // Join the body array into one string.
    // $message['body'] = implode("\n\n", $message['body']);
    // if ($this->AllowHtml == 0) {
    //   // Convert any HTML to plain-text.
    //   $message['body'] = drupal_html_to_text($message['body']);
    //   // Wrap the mail body for sending.
    //   $message['body'] = drupal_wrap_mail($message['body']);
    // }
    return $message;
  }

  public function mail(array $message)
  {
    $mail = new postage();
    $config = \Drupal::config('postage.settings');

    //set postage's api key
    $mail->setKey($config->get('api_key'));

    // Parse 'From' e-mail address.
    $address = $this->parse_address($message['from']);

    if ($address[0]['name'] == '') {
      $mail->addFrom($address[0]['mail']);
    }
    else {
      $mail->addFrom($address[0]['mail'], $address[0]['name']);
    }

    unset($message['headers']['From']);

    foreach ($this->parse_address($message['to']) as $id => $address) {
      $mail->addTo($address['mail'], ($address['name'] != '') ? $address['name'] : null);
    }

    $mail->subject($message['subject']);

    if(is_array($message['body'])) {
      $body = '';
      foreach ($message['body'] as $m) {
        $body .= $m;
      }
    } else {
      $body = $message['body'];
    }

    // Check the header content type to see if email is plain text
    // if not we send as HTML
    if (strpos($message['headers']['Content-Type'], 'text/plain') !== FALSE) {
      $mail->messagePlain($body);
    }
    else {
      $mail->messageHtml($body);
    }

    try {
      if (!($result = $mail->send())) {
        \Drupal::logger('postage')->error("Mail sending error: ".$mail->ErrorInfo);
      }

      return $result;
    }
    catch (Exception $e) {
      \Drupal::logger('postage')->error('Exception message: '. $e->getMessage());
      drupal_set_message('Mail sending error: '. $e->getMessage(), 'error');
    }

    return false;
  }

  protected function parse_address($address) {
    $parsed = array();
    $regexp = "/^(.*) <([a-z0-9]+(?:[_\\.-][a-z0-9]+)*@(?:[a-z0-9]+(?:[\.-][a-z0-9]+)*)+\\.[a-z]{2,})>$/i";

    // Split multiple addresses and process each.
    foreach (explode(',', $address) as $email) {
      $email = trim($email);
      if (preg_match($regexp, $email, $matches)) {
        $parsed[] = array('mail' => $matches[2], 'name' => trim($matches[1], '"'));
      }
      else {
        $parsed[] = array('mail' => $email, 'name' => '');
      }
    }
    return $parsed;
  }
}
