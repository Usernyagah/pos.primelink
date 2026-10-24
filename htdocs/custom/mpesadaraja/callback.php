<?php
declare(strict_types=1);

define('NOCSRFCHECK', '1');
define('NOLOGIN', '1');
define('NOREQUIREMENU', '1');
define('NOREQUIREHTML', '1');
define('NOREQUIREAJAX', '1');
require '../../main.inc.php';
require_once __DIR__.'/class/daraja.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 */
$rawBody = file_get_contents('php://input', false, null, 0, 65536);
$payload = json_decode($rawBody === false ? '' : $rawBody, true);
$callback = is_array($payload) ? ($payload['Body']['stkCallback'] ?? null) : null;
if (!is_array($callback) || empty($callback['CheckoutRequestID']) || !isset($callback['ResultCode'])
	|| !is_scalar($callback['CheckoutRequestID']) || !is_numeric($callback['ResultCode'])) {
	http_response_code(400);
	exit;
}

$checkoutRequestId = (string) $callback['CheckoutRequestID'];
if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $checkoutRequestId)) {
	http_response_code(400);
	exit;
}
$resultCode = (int) $callback['ResultCode'];
$description = isset($callback['ResultDesc']) && is_scalar($callback['ResultDesc']) ? (string) $callback['ResultDesc'] : '';
$verifiedResultCode = MpesaDaraja::getStkPushResult($checkoutRequestId);
if ($verifiedResultCode === null || $verifiedResultCode !== $resultCode) {
	dol_syslog('M-Pesa callback result could not be verified against Daraja.', LOG_WARNING);
	http_response_code(503);
	exit;
}

$metadata = array();
if ($resultCode === 0 && !empty($callback['CallbackMetadata']['Item']) && is_array($callback['CallbackMetadata']['Item'])) {
	foreach ($callback['CallbackMetadata']['Item'] as $item) {
		if (is_array($item) && isset($item['Name'], $item['Value'])) {
			$metadata[(string) $item['Name']] = $item['Value'];
		}
	}
}

$sql = 'SELECT rowid, amount, phone, status FROM '.MAIN_DB_PREFIX.'mpesadaraja_transaction';
$sql .= " WHERE checkout_request_id = '".$db->escape($checkoutRequestId)."'";
$sql .= $db->plimit(1);
$resql = $db->query($sql);
if (!$resql) {
	dol_syslog('Unable to locate M-Pesa callback transaction: '.$db->lasterror(), LOG_ERR);
	http_response_code(500);
	exit;
}
$transaction = $db->fetch_object($resql);
if (!$transaction) {
	dol_syslog('M-Pesa callback did not match a stored checkout request.', LOG_WARNING);
	http_response_code(503);
	exit;
}
if (in_array($transaction->status, array('SUCCESS', 'FAILED', 'PAID'), true)) {
	top_httphead('application/json');
	echo json_encode(array('ResultCode' => 0, 'ResultDesc' => 'Accepted'));
	exit;
}
if ($transaction->status === 'WAITING') {
	$status = 'FAILED';
	$receipt = '';
	if ($resultCode === 0
		&& isset($metadata['Amount'], $metadata['MpesaReceiptNumber'], $metadata['PhoneNumber'])
		&& is_numeric($metadata['Amount'])
		&& is_scalar($metadata['MpesaReceiptNumber'])
		&& is_scalar($metadata['PhoneNumber'])
		&& (float) $metadata['Amount'] === (float) $transaction->amount
		&& (string) $metadata['PhoneNumber'] === (string) $transaction->phone
		&& preg_match('/^[A-Z0-9]{1,32}$/', (string) $metadata['MpesaReceiptNumber'])) {
		$status = 'SUCCESS';
		$receipt = (string) $metadata['MpesaReceiptNumber'];
	} elseif ($resultCode === 0) {
		$description = 'The successful callback could not be verified against the Daraja transaction.';
	}

	$sql = 'UPDATE '.MAIN_DB_PREFIX.'mpesadaraja_transaction';
	$sql .= " SET status = '".$status."'";
	$sql .= ", result_description = '".$db->escape(dol_substr($description, 0, 255))."'";
	$sql .= ", mpesa_receipt = '".$db->escape(dol_substr($receipt, 0, 32))."'";
	$sql .= ' WHERE rowid = '.((int) $transaction->rowid)." AND status = 'WAITING'";
	if (!$db->query($sql)) {
		dol_syslog('Unable to persist M-Pesa callback result: '.$db->lasterror(), LOG_ERR);
		http_response_code(500);
		exit;
	}
}

top_httphead('application/json');
echo json_encode(array('ResultCode' => 0, 'ResultDesc' => 'Accepted'));
