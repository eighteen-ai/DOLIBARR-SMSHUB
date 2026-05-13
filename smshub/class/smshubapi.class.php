<?php
/* Copyright (C) 2026 SMSHUB - REST client for SMSHUB server */

class SmsHubApi
{
	public $server_url;
	public $api_key;
	public $last_error;
	public $last_http_code;
	public $last_response;
	public $last_raw_body;
	public $last_request_url;

	public function __construct($server_url = null, $api_key = null)
	{
		$this->server_url = rtrim($server_url ?: getDolGlobalString('SMSHUB_SERVER_URL'), '/');
		$this->api_key = $api_key ?: getDolGlobalString('SMSHUB_API_KEY');
	}

	/**
	 * Send a SMS through SMSHUB.
	 *
	 * @param string $phone Destination (E.164 or local, will be normalized)
	 * @param string $message Body
	 * @param string|null $scheduled_at Optional schedule (ISO 8601, "+15m", "+2h", timestamp...)
	 * @return array|false Decoded response on success, false on failure
	 */
	public function send($phone, $message, $scheduled_at = null)
	{
		$phone = $this->normalizePhone($phone);
		if (empty($phone)) {
			$this->last_error = 'Numéro de téléphone vide ou invalide';
			return false;
		}
		if (empty($message)) {
			$this->last_error = 'Message vide';
			return false;
		}

		$body = array('phone' => $phone, 'message' => $message);
		if (!empty($scheduled_at)) $body['scheduled_at'] = $scheduled_at;

		return $this->call('client_api_send', 'POST', $body);
	}

	public function version()
	{
		return $this->call('version', 'GET', null, false);
	}

	public function call($action, $method = 'GET', $body = null, $authed = true)
	{
		if (empty($this->server_url)) {
			$this->last_error = 'URL serveur SMSHUB non configurée';
			return false;
		}
		if ($authed && empty($this->api_key)) {
			$this->last_error = 'Clé API SMSHUB non configurée';
			return false;
		}

		$url = $this->server_url.'/api.php?action='.urlencode($action);
		$this->last_request_url = $url;
		$headers = array('User-Agent: SMSHUB-Dolibarr/1.1', 'Accept: application/json');
		if ($authed) $headers[] = 'X-Api-Key: '.$this->api_key;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

		if (strtoupper($method) === 'POST') {
			$headers[] = 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body ?: new stdClass()));
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$raw = curl_exec($ch);
		$this->last_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlErr = curl_error($ch);
		curl_close($ch);

		if ($raw === false) {
			$this->last_error = 'Curl error: '.$curlErr;
			$this->last_raw_body = '';
			return false;
		}

		$this->last_raw_body = $raw;
		$decoded = json_decode($raw, true);
		$this->last_response = $decoded;

		if ($this->last_http_code >= 400) {
			$msg = is_array($decoded) && !empty($decoded['error']) ? $decoded['error'] : 'HTTP '.$this->last_http_code;
			$this->last_error = $msg.' — body: '.dol_substr($raw, 0, 300);
			return false;
		}
		if (!is_array($decoded)) {
			$this->last_error = 'Réponse non JSON (HTTP '.$this->last_http_code.') — body: '.dol_substr($raw, 0, 300);
			return false;
		}
		if (isset($decoded['ok']) && $decoded['ok'] === false) {
			$this->last_error = $decoded['error'] ?? 'Erreur inconnue';
			return false;
		}
		return $decoded;
	}

	/**
	 * Normalize a phone number: strip spaces/dashes/dots, add country code if local format.
	 */
	public function normalizePhone($phone)
	{
		$phone = preg_replace('/[\s\-\.\(\)]/', '', trim($phone));
		if (empty($phone)) return '';
		// Already international (+...)
		if (substr($phone, 0, 1) === '+') return $phone;
		// 00... → +...
		if (substr($phone, 0, 2) === '00') return '+'.substr($phone, 2);
		// Local: prefix with default country code (e.g. +33 + drop leading 0)
		$cc = getDolGlobalString('SMSHUB_DEFAULT_COUNTRY_CODE', '+33');
		if (!preg_match('/^\+/', $cc)) $cc = '+'.ltrim($cc, '+');
		if (substr($phone, 0, 1) === '0') $phone = substr($phone, 1);
		return $cc.$phone;
	}
}
