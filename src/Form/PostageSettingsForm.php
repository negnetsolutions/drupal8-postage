<?php

namespace Drupal\postage\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class PostageSettingsForm extends ConfigFormBase
{

  /** 
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'postage_settings';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() 
  {
    return [
      'postage.settings',
    ];
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) 
  {
    $config = $this->config('postage.settings');

    $enabled = $config->get('enabled');

    $form['postage_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable postage to send Drupal emails'),
      '#default_value' => $config->get('enabled'),
      '#description' => t('Use postage to act as your mail handler.'),
    );

    $form['api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Postage API Key'),
      '#default_value' => $config->get('api_key'),
      '#required' => true,
      '#description' => t('You postage API key similar to : 8663433-3923-9572-1305-524689115863 (taken from the API page)'),
    );

    $form['test'] = array(
      '#type' => 'fieldset',
      '#title' => t('Test Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['test']['test_address'] = array(
      '#type' => 'textfield',
      '#title' => t('Recipient'),
      '#default_value' => '',
      '#description' => t('Enter a valid email address to send a test email.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) 
  {
    $enabled = $form_state->getValue('postage_enabled');

    $mail = $this->configFactory->getEditable('system.mail');
    if ($enabled) {
      $mail->set('interface.default', 'postage_mailer')->save();
    } else {
      drupal_set_message('postage is installed but <em>disabled</em>, also the current SMTP library is not set: <em>sending via default PHP mail class</em>.', 'warning', true);
      $mail->set('interface.default', 'php_mail')->save();
    }

    // Retrieve the configuration
    $this->configFactory->getEditable('postage.settings')
      // Set the submitted configuration setting
      ->set('enabled', $form_state->getValue('postage_enabled'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    $test_address = $form_state->getValue('test_address');

    if ($test_address !== '') {
      $this->sendTestEmail($test_address);
    }

    parent::submitForm($form, $form_state);
  }

  protected function sendTestEmail($email)
  {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $langcode = \Drupal::currentUser()->getPreferredLangcode();

    $result = $mailManager->mail('postage', 'test', $email, $langcode, [], NULL, true);
    if ($result['result'] != true) {
      $message = t('There was a problem sending your email notification to @email.', array('@email' => $to));
      drupal_set_message($message, 'error');
      \Drupal::logger('postage')->error($message);
      return;
    }

    $message = t('A test email has been sent to @email ', array('@email' => $email));
    drupal_set_message($message);
    \Drupal::logger('postage')->notice($message);
  }
}
