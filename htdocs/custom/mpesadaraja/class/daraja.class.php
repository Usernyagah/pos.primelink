<?php
declare(strict_types=1);

/**
 * Small Daraja API client for STK Push.
 */
class MpesaDaraja
{
	/**
	 * Return a configured environment value.
	 *
	 * @param string $name Environment variable name.
	 * @return string
	 */
	public static function config(string $name): string
	{
		$value = getenv($name);
		return $value === false ? '' : trim($value);
	}

	/**
	 * Check whether required Daraja settings are present.
	 *
	 * @return bool
	 */
	public static function isConfigured(): bool
	{
		return self::config('MPESA_DARAJA_CONSUMER_KEY') !== ''
			&& self::config('MPESA_DARAJA_CONSUMER_SECRET') !== ''
			&& self::config('MPESA_DARAJA_SHORTCODE') !== ''
			&& self::config('MPESA_DARAJA_PASSKEY') !== ''
			&& self::config('MPESA_DARAJA_CALLBACK_URL') !== ''
			&& in_array(self::config('MPESA_DARAJA_ENV'), array('sandbox', 'production'), true);
	}

	/**
	 * Create an STK Push request.
	 *
	 * @param string $phone Kenyan MSISDN in 254XXXXXXXXX format.
	 * @param int $amount Amount in whole KES.
	 * @param string $accountReference Dolibarr payment reference.
	 * @param string $description Payment description.
	 * @return array{success:bool,checkout_request_id?:string,merchant_request_id?:string,error?:string}
	 */
	public static function initiate(string $phone, int $amount, string $accountReference, string $description): array
	{
		$accessToken = self::getAccessToken();
		if ($accessToken === '') {
			return array('success' => false, 'error' => 'Daraja authentication failed. Check the configured credentials.');
		}

		$shortcode = self::config('MPESA_DARAJA_SHORTCODE');
		$timestamp = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('YmdHis');
		$password = base64_encode($shortcode.self::config('MPESA_DARAJA_PASSKEY').$timestamp);
		$transactionType = self::config('MPESA_DARAJA_TRANSACTION_TYPE');
		if ($transactionType === '') {
			$transactionType = 'CustomerPayBillOnline';
		}
		if (!in_array($transactionType, array('CustomerPayBillOnline', 'CustomerBuyGoodsOnline'), true)) {
			return array('success' => false, 'error' => 'The configured Daraja transaction type is not supported.');
		}

		$payload = array(
			'BusinessShortCode' => $shortcode,
			'Password' => $password,
			'Timestamp' => $timestamp,
			'TransactionType' => $transactionType,
			'Amount' => $amount,
			'PartyA' => $phone,
			'PartyB' => $shortcode,
			'PhoneNumber' => $phone,
			'CallBackURL' => self::config('MPESA_DARAJA_CALLBACK_URL'),
			'AccountReference' => $accountReference,
			'TransactionDesc' => dol_substr($description, 0, 64)
		);

		$response = self::request(
			self::apiBase().'/mpesa/stkpush/v1/processrequest',
			'POST',
			json_encode($payload),
			array('Authorization: Bearer '.$accessToken, 'Content-Type: application/json')
		);
		if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
			return array('success' => false, 'error' => 'Daraja rejected the STK Push request (HTTP '.$response['http_code'].').');
		}

		$data = json_decode($response['content'], true);
		if (!is_array($data) || empty($data['CheckoutRequestID']) || empty($data['MerchantRequestID'])) {
			return array('success' => false, 'error' => 'Daraja returned an incomplete STK Push response.');
		}
		if (isset($data['ResponseCode']) && (string) $data['ResponseCode'] !== '0') {
			return array('success' => false, 'error' => (string) ($data['ResponseDescription'] ?? 'Daraja rejected the STK Push request.'));
		}

		return array(
			'success' => true,
			'checkout_request_id' => (string) $data['CheckoutRequestID'],
			'merchant_request_id' => (string) $data['MerchantRequestID']
		);
	}

	/**
	 * Query the result of an STK Push against Daraja's transaction status API.
	 *
	 * @param string $checkoutRequestId Daraja checkout request identifier.
	 * @return ?int Verified Daraja result code, or null if verification failed.
	 */
	public static function getStkPushResult(string $checkoutRequestId): ?int
	{
		$accessToken = self::getAccessToken();
		if ($accessToken === '') {
			return null;
		}

		$shortcode = self::config('MPESA_DARAJA_SHORTCODE');
		$timestamp = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('YmdHis');
		$payload = array(
			'BusinessShortCode' => $shortcode,
			'Password' => base64_encode($shortcode.self::config('MPESA_DARAJA_PASSKEY').$timestamp),
			'Timestamp' => $timestamp,
			'CheckoutRequestID' => $checkoutRequestId
		);
		$response = self::request(
			self::apiBase().'/mpesa/stkpushquery/v1/query',
			'POST',
			json_encode($payload),
			array('Authorization: Bearer '.$accessToken, 'Content-Type: application/json')
		);
		if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
			return null;
		}

		$data = json_decode($response['content'], true);
		if (!is_array($data) || !isset($data['ResponseCode'], $data['ResultCode']) || (string) $data['ResponseCode'] !== '0') {
			return null;
		}

		return is_numeric($data['ResultCode']) ? (int) $data['ResultCode'] : null;
	}

	/**
	 * Obtain an OAuth access token.
	 *
	 * @return string
	 */
	private static function getAccessToken(): string
	{
		$credentials = base64_encode(self::config('MPESA_DARAJA_CONSUMER_KEY').':'.self::config('MPESA_DARAJA_CONSUMER_SECRET'));
		$response = self::request(
			self::apiBase().'/oauth/v1/generate?grant_type=client_credentials',
			'GET',
			'',
			array('Authorization: Basic '.$credentials)
		);
		if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
			return '';
		}

		$data = json_decode($response['content'], true);
		return is_array($data) && !empty($data['access_token']) ? (string) $data['access_token'] : '';
	}

	/**
	 * Make an HTTPS API request through Dolibarr's URL helper.
	 *
	 * @param string $url API URL.
	 * @param string $method HTTP method.
	 * @param string $body JSON request body.
	 * @param string[] $headers HTTP request headers.
	 * @return array{http_code:int,content:string,curl_error_no:int,curl_error_msg:string}
	 */
	private static function request(string $url, string $method, string $body, array $headers): array
	{
		return getURLContent($url, $method, $body, 0, $headers, array('https'), 0, 1, 10, 30);
	}

	/**
	 * Select the Daraja API host from the environment.
	 *
	 * @return string
	 */
	private static function apiBase(): string
	{
		return self::config('MPESA_DARAJA_ENV') === 'production'
			? 'https://api.safaricom.co.ke'
			: 'https://sandbox.safaricom.co.ke';
	}
}
