<?php
declare(strict_types=1);

define('NOTOKENRENEWAL', '1');
define('NOREQUIREMENU', '1');
define('NOREQUIREHTML', '1');
require '../../main.inc.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var User $user
 */
if (!$user->hasRight('takepos', 'run') || !isModEnabled('mpesadaraja')) {
	http_response_code(403);
	exit;
}

$invoiceid = GETPOSTINT('invoiceid');
$reference = GETPOST('reference', 'aZ09');
if (!preg_match('/^MP[0-9]+[a-f0-9]{32}$/', $reference)) {
	http_response_code(400);
	exit;
}

$sql = 'SELECT status, result_description FROM '.MAIN_DB_PREFIX.'mpesadaraja_transaction';
$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_facture = '.((int) $invoiceid);
$sql .= " AND reference = '".$db->escape($reference)."'";
$resql = $db->query($sql);
if (!$resql) {
	dol_syslog('Unable to read M-Pesa transaction status: '.$db->lasterror(), LOG_ERR);
	http_response_code(500);
	exit;
}
$row = $db->fetch_object($resql);
if (!$row) {
	http_response_code(404);
	exit;
}

top_httphead('application/json');
echo json_encode(array(
	'status' => (string) $row->status,
	'message' => $row->status === 'FAILED' ? (string) $row->result_description : ''
));
