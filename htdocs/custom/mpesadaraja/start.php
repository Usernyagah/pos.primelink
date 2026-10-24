<?php
declare(strict_types=1);

define('NOTOKENRENEWAL', '1');
require '../../main.inc.php';
require_once __DIR__.'/class/daraja.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */
$langs->load('mpesadaraja@mpesadaraja');
if (!$user->hasRight('takepos', 'run') || !isModEnabled('mpesadaraja')) {
	accessforbidden();
}

$invoiceid = GETPOSTINT('invoiceid');
$place = GETPOST('place', 'aZ09');
$action = GETPOST('action', 'aZ09');
$message = '';
$reference = '';
$phone = GETPOST('phone', 'alphanohtml');
$invoice = new Facture($db);

/* Actions */

if ($invoiceid > 0) {
	$fetchResult = $invoice->fetch($invoiceid);
} else {
	$terminal = GETPOST('terminal', 'aZ09');
	if ($terminal === '') {
		$terminal = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '';
	}
	$fetchResult = $terminal !== '' ? $invoice->fetch(0, '(PROV-POS'.$terminal.'-'.$place.')') : 0;
}

if ($fetchResult <= 0 || $invoice->module_source !== 'takepos' || (int) $invoice->entity !== (int) $conf->entity) {
	$message = $langs->trans('MpesadarajaInvalidInvoice');
} elseif ($action === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$remain = price2num($invoice->getRemainToPay(), 'MT');
	if (!MpesaDaraja::isConfigured()) {
		$message = $langs->trans('MpesadarajaConfigurationMissing');
	} elseif ($invoice->type === Facture::TYPE_CREDIT_NOTE || $remain <= 0 || $invoice->status === Facture::STATUS_DRAFT && count($invoice->lines) === 0) {
		$message = $langs->trans('MpesadarajaInvalidAmount');
	} elseif ((!empty($invoice->multicurrency_code) ? $invoice->multicurrency_code : $conf->currency) !== 'KES') {
		$message = $langs->trans('MpesadarajaKesOnly');
	} elseif (abs($remain - round($remain)) > 0.00001 || $remain > 150000) {
		$message = $langs->trans('MpesadarajaWholeKesLimit');
	} else {
		$normalizedPhone = preg_replace('/[\s()+-]/', '', $phone);
		if (preg_match('/^0([17][0-9]{8})$/', (string) $normalizedPhone, $matches)) {
			$normalizedPhone = '254'.$matches[1];
		}
		if (!preg_match('/^254[17][0-9]{8}$/', (string) $normalizedPhone)) {
			$message = $langs->trans('MpesadarajaInvalidPhone');
		} else {
			$reference = 'MP'.((int) $invoice->id).bin2hex(random_bytes(16));
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'mpesadaraja_transaction';
			$sql .= ' (entity, fk_facture, reference, amount, phone, status, datec, fk_user_author)';
			$sql .= ' VALUES ('.((int) $conf->entity).', '.((int) $invoice->id).", '".$db->escape($reference)."', ".((float) $remain);
			$sql .= ", '".$db->escape((string) $normalizedPhone)."', 'INITIALIZING', '".$db->idate(dol_now())."', ".((int) $user->id).')';
			if (!$db->query($sql)) {
				$message = $langs->trans('MpesadarajaRequestFailed');
			} else {
				$description = $langs->transnoentitiesnoconv('Invoice').' '.$invoice->ref;
				$apiResult = MpesaDaraja::initiate((string) $normalizedPhone, (int) round($remain), dol_substr('POS'.$invoice->id, 0, 12), $description);
				if (empty($apiResult['success'])) {
					$sql = "UPDATE ".MAIN_DB_PREFIX."mpesadaraja_transaction SET status = 'FAILED'";
					$sql .= ", result_description = '".$db->escape((string) ($apiResult['error'] ?? 'Daraja request failed'))."'";
					$sql .= " WHERE reference = '".$db->escape($reference)."' AND entity = ".((int) $conf->entity);
					$db->query($sql);
					$message = $langs->trans('MpesadarajaRequestFailed');
					dol_syslog('M-Pesa STK Push failed: '.($apiResult['error'] ?? 'Unknown Daraja error'), LOG_ERR);
					$reference = '';
				} else {
					$sql = "UPDATE ".MAIN_DB_PREFIX."mpesadaraja_transaction SET status = 'WAITING'";
					$sql .= ", checkout_request_id = '".$db->escape((string) $apiResult['checkout_request_id'])."'";
					$sql .= ", merchant_request_id = '".$db->escape((string) $apiResult['merchant_request_id'])."'";
					$sql .= " WHERE reference = '".$db->escape($reference)."' AND entity = ".((int) $conf->entity);
					if (!$db->query($sql)) {
						$message = $langs->trans('MpesadarajaRequestFailed');
						$reference = '';
					}
				}
			}
		}
	}
}

/* Views */

top_htmlhead('', $langs->trans('MpesadarajaPayWithMpesa'), 0, 0);
print '<body class="bodyforpopup">';
print '<div class="fichecenter">';
print '<h2>'.$langs->trans('MpesadarajaPayWithMpesa').'</h2>';
if ($message !== '') {
	print '<div class="warning">'.$message.'</div>';
}
if ($reference === '' && $fetchResult > 0) {
	print '<p>'.$langs->trans('MpesadarajaStkPromptDescription').'</p>';
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="start">';
	print '<input type="hidden" name="invoiceid" value="'.((int) $invoice->id).'">';
	print '<input type="hidden" name="place" value="'.dol_escape_htmltag($place).'">';
	print '<label for="phone">'.$langs->trans('MpesadarajaPhoneNumber').'</label>';
	print '<input class="flat minwidth300" type="tel" id="phone" name="phone" required value="'.dol_escape_htmltag($phone).'" placeholder="07XXXXXXXX or 2547XXXXXXXX">';
	print '<p>'.$langs->trans('MpesadarajaAmount').': <strong>'.price($invoice->getRemainToPay(), 0, $langs, 1, -1, -1, 'KES').'</strong></p>';
	print '<button class="button" type="submit">'.$langs->trans('MpesadarajaSendStkPrompt').'</button>';
	print '</form>';
}
if ($reference !== '') {
	print '<p id="status">'.$langs->trans('MpesadarajaWaitingForConfirmation').'</p>';
	print '<p>'.$langs->trans('MpesadarajaApprovePrompt').'</p>';
	print '<script>';
	print 'const statusUrl='.json_encode(DOL_URL_ROOT.'/custom/mpesadaraja/status.php?invoiceid='.((int) $invoice->id).'&reference='.urlencode($reference)).';';
	print 'let attempts=0;';
	print 'const poll=setInterval(async function(){attempts++;try{const response=await fetch(statusUrl,{credentials:"same-origin"});const result=await response.json();';
	print 'if(result.status==="SUCCESS"){clearInterval(poll);document.getElementById("status").textContent='.json_encode($langs->trans('MpesadarajaPaymentConfirmed')).';';
	print 'const url='.json_encode(DOL_URL_ROOT.'/takepos/invoice.php?place='.urlencode($place).'&action=valid&pay=MPESA&amount='.price2num($invoice->getRemainToPay(), 'MT').'&invoiceid='.((int) $invoice->id).'&mpesa_reference='.$reference.'&token='.currentToken()).';';
	print 'parent.$("#poslines").load(url,function(){parent.$("#invoiceid").val("");parent.$.colorbox.close();});';
	print '}else if(result.status==="FAILED"||attempts>=120){clearInterval(poll);document.getElementById("status").textContent=result.message||'.json_encode($langs->trans('MpesadarajaPaymentNotConfirmed')).';';
	print '} }catch(error){if(attempts>=120){clearInterval(poll);document.getElementById("status").textContent='.json_encode($langs->trans('MpesadarajaStatusUnavailable')).';}}},3000);';
	print '</script>';
}
print '</div></body></html>';
