<?php
declare(strict_types=1);

define('NOTOKENRENEWAL', '1');
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../class/daraja.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */
$langs->load('mpesadaraja@mpesadaraja');
if (!$user->admin) {
	accessforbidden();
}

$form = new Form($db);
$status = MpesaDaraja::isConfigured() ? $langs->trans('MpesadarajaConfigured') : $langs->trans('MpesadarajaConfigurationMissing');
llxHeader('', $langs->trans('MpesadarajaSetup'));
print load_fiche_titre($langs->trans('MpesadarajaSetup'));
print '<div class="fichecenter">';
print '<div class="info">'.$status.'</div>';
print '<p>'.$langs->trans('MpesadarajaSetupInstructions').'</p>';
print '<ul>';
foreach (array(
	'MPESA_DARAJA_ENV' => 'sandbox or production',
	'MPESA_DARAJA_CONSUMER_KEY' => 'Daraja app Consumer Key',
	'MPESA_DARAJA_CONSUMER_SECRET' => 'Daraja app Consumer Secret',
	'MPESA_DARAJA_SHORTCODE' => 'PayBill or Till shortcode',
	'MPESA_DARAJA_PASSKEY' => 'Daraja STK Push passkey',
	'MPESA_DARAJA_CALLBACK_URL' => 'Public HTTPS callback.php URL',
	'MPESA_DARAJA_TRANSACTION_TYPE' => 'CustomerPayBillOnline or CustomerBuyGoodsOnline'
) as $variable => $description) {
	print '<li><strong>'.dol_escape_htmltag($variable).'</strong> - '.dol_escape_htmltag($description).'</li>';
}
print '</ul>';
print '<p>'.$langs->trans('MpesadarajaCallbackUrl').': <strong>'.dol_escape_htmltag(DOL_MAIN_URL_ROOT.'/custom/mpesadaraja/callback.php').'</strong></p>';
print '<p>'.$langs->trans('MpesadarajaBankSetup').'</p>';
print '</div>';
llxFooter();
