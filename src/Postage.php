<?php

namespace Drupal\postage;

class Postage
{
	static protected $instance;
	private $message = array();
	private $_apiKey = '8663433-3923-9572-1305-524689115863';
	private $_debugMode = self::DEBUG_OFF;
	private $_server_address = 'http://postage.negnetsolutions.com/compose';
	
	const DEBUG_OFF = 0;
	const DEBUG_VERBOSE = 1;
	
	public static function compose()
	{
		return new self();
	}
	function __construct()
	{
		$this->message['Recipients'] = array();
	}
	public function setServer($address)
	{
		$this->_server_address = $address;
		return $this;
	}
	public function &setKey($key)
	{
		$this->_apiKey = $key;
		return $this;
	}
	public function &debug($mode = self::DEBUG_VERBOSE)
	{
		$this->_debugMode = $mode;
		return $this;
	}
	public function _addRecipient($type, $address, $name)
	{
		if (!$this->_validateAddress($address)) {
			throw new InvalidArgumentException("Address \"{$address}\" is invalid");
		}
		
		if(!isset($this->message['Recipients'][$type]))
			$this->message['Recipients'][$type] = array();

		$this->message['Recipients'][$type][] = array('name'=>$name,'address'=>$address);
	}
	public function &addTo($address, $name = null)
	{
		$this->_addRecipient('To', $address, $name);
		return $this;
	}
	public function &addCc($address, $name = null)
	{
		$this->_addRecipient('Cc', $address, $name);
		return $this;
	}
	public function &addBcc($address, $name = null)
	{
		$this->_addRecipient('Bcc', $address, $name);
		return $this;
	}
	public function &addFrom($address, $name = null)
	{
		$this->_addRecipient('From', $address, $name);
		return $this;
	}
	public function &subject($subject)
	{
		$this->message['Subject'] = $subject;
		return $this;
	}
	public function &messagePlain($text)
	{
		$this->message['TextBody'] = $text;
		return $this;
	}
	public function &messageHtml($text)
	{
		$this->message['HtmlBody'] = $text;
		return $this;
	}
	private function _validateData()
	{
		if (count($this->message['Recipients']) == 0) {
			throw new BadMethodCallException('No To address is set');
		}

		foreach($this->message['Recipients'] as $type)
			foreach($type as $recipient)
				if(!$this->_validateAddress($recipient['address']))
					throw new BadMethodCallException($recipient['address'].' is not a valid email address!');

		if (!isset($this->message['Subject'])) {
			throw new BadMethodCallException('Subject is not set');
		}
	}
	private function _prepareData()
	{
		$data = $this->message;
		
		foreach($data['Recipients'] as $key=>$type)
		{
			$data[$key] = '';
			foreach($type as $recipient){
				$data[$key] .= $this->_createAddress($recipient['address'], $recipient['name']).', ';
			}
			
			$data[$key] = substr($data[$key], 0, strlen($data[$key]) - 2);
		}
		
		unset($data['Recipients']);
		
		return $data;
	}
	public function send()
	{
		$this->_validateData();
		$data = $this->_prepareData();
		
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'X-Server-Token: ' . $this->_apiKey
		);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->_server_address);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		$return = curl_exec($ch);
		$curlError = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				
		if ($curlError !== '') {
			throw new Exception($curlError);
		}
		
		if (!$this->_isTwoHundred($httpCode)) {
			if ($httpCode == 422) {
				$return = json_decode($return);
				throw new Exception($return->Message, $return->ErrorCode);
			} else {
				throw new Exception("Error while mailing. Mailer returned HTTP code {$httpCode} with message \"{$return}\"", $httpCode);
			}
		}
		
		// check mailer status
		$mail_status = json_decode($return, true);
		
		if( $mail_status['Message'] == 'ERROR' )
			throw new Exception($mail_status['ErrorCode']);
		
		
		if (($this->_debugMode & self::DEBUG_VERBOSE) === self::DEBUG_VERBOSE) {
			echo "<pre>".print_r( array(
				'json' => json_encode($data),
				'headers' => $headers,
				'return' => $return,
				'curlError' => $curlError,
				'httpCode' => $httpCode,
				'mail_status' => $mail_status
			)
			, true)."</pre>";
		}
		
		unset($this->message);
		
		return true;
	}
	
	public function _validateAddress($email)
	{
		$regex = "/^([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*[\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?)$/i";
		return preg_match($regex, $email) === 1;
	}
	private function _isTwoHundred($value)
	{
		return intval($value / 100) == 2;
	}
	private function _createAddress($address, $name = null)
	{
		if (isset($name)) {
			return '"' . str_replace('"', '', $name) . '" <' . $address . '>';
		} else {
			return $address;
		}
	}
}
