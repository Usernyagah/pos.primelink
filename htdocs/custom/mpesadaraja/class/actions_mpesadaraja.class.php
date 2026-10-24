<?php
declare(strict_types=1);

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * TakePOS hooks for M-Pesa Daraja payments.
 */
class ActionsMpesadaraja extends CommonHookActions
{
	/** @var DoliDB Database handler. */
	public $db;

	/** @var string Hook error message. */
	public $error = '';

	/** @var string[] Hook error list. */
	public $errors = array();

	/** @var mixed[] Hook result values. */
	public $results = array();

	/** @var ?string Hook output. */
	public $resprints;

	/** @var int Hook execution priority. */
	public $priority;

	/**
	 * @param DoliDB $db Database handler.
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add the M-Pesa checkout button to the TakePOS payment popup.
	 *
	 * @param array<string,mixed> $parameters Hook parameters.
	 * @param CommonObject $object Current invoice.
	 * @param string $action Current action.
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action): int
	{
		global $langs, $user;

		if (($parameters['context'] ?? '') !== 'takepospay' || !isModEnabled('mpesadaraja') || !$user->hasRight('takepos', 'run') || !is_object($object)) {
			return 0;
		}

		$langs->load('mpesadaraja@mpesadaraja');
		$place = GETPOST('place', 'aZ09');
		$url = DOL_URL_ROOT.'/custom/mpesadaraja/start.php?invoiceid='.((int) $object->id).'&place='.urlencode((string) $place);
		$this->results[] = array(
			'function' => 'parent.$.colorbox({href:'.json_encode($url).',iframe:true,width:"460px",height:"620px",transition:"none",title:'.json_encode($langs->trans('MpesadarajaPayWithMpesa')).'});',
			'span' => '',
			'text' => $langs->trans('MpesadarajaPayWithMpesa'),
			'class' => 'poscolorblue'
		);

		return 0;
	}

	/**
	 * Add a per-terminal toggle for the supermarket cashier layout.
	 *
	 * @param array<string,mixed> $parameters Hook parameters.
	 * @param ?CommonObject $object Current object.
	 * @param string $action Current action.
	 * @return int
	 */
	public function ActionButtons($parameters, &$object, &$action): int
	{
		global $langs, $user;

		if (!isModEnabled('mpesadaraja') || !$user->hasRight('takepos', 'run')) {
			return 0;
		}

		$langs->load('mpesadaraja@mpesadaraja');
		$this->results[] = array(
			array(
				'title' => '<span class="fa fa-cash-register paddingrightonly"></span><div class="trunc" id="mpesa-cashier-mode-label" data-enable-label="'.dol_escape_htmltag($langs->trans('MpesadarajaEnableCashierMode')).'" data-disable-label="'.dol_escape_htmltag($langs->trans('MpesadarajaDisableCashierMode')).'" data-scan-placeholder="'.dol_escape_htmltag($langs->trans('MpesadarajaScanSearchPlaceholder')).'" data-standard-placeholder="'.dol_escape_htmltag($langs->trans('Search')).'">'.$langs->trans('MpesadarajaEnableCashierMode').'</div>',
				'action' => 'MpesaToggleCashierMode();',
				'class' => 'mpesa-cashier-mode-toggle'
			)
		);

		return 0;
	}

	/**
	 * Require an approved Daraja callback before TakePOS records an M-Pesa payment.
	 *
	 * @param array<string,mixed> $parameters Hook parameters.
	 * @param CommonObject $object Current invoice.
	 * @param string $action Current action.
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action): int
	{
		global $conf, $langs, $user;

		if ($action !== 'valid' || GETPOST('pay', 'aZ09') !== 'MPESA') {
			return 0;
		}
		$langs->load('mpesadaraja@mpesadaraja');
		if (!$user->hasRight('takepos', 'run') || !is_object($object) || $object->element !== 'facture') {
			$this->error = $langs->trans('MpesadarajaPaymentNotAuthorized');
			return -1;
		}

		$reference = GETPOST('mpesa_reference', 'aZ09');
		if (!preg_match('/^MP[0-9]+[a-f0-9]{32}$/', $reference)) {
			$this->error = $langs->trans('MpesadarajaConfirmationRequired');
			return -1;
		}

		$amount = price2num(GETPOST('amount', 'alphanohtml'), 'MT');
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'mpesadaraja_transaction';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' AND fk_facture = '.((int) $object->id);
		$sql .= " AND reference = '".$this->db->escape($reference)."'";
		$sql .= " AND status = 'SUCCESS'";
		$sql .= ' AND amount = '.((float) $amount);
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		if (!$resql || !$this->db->fetch_object($resql)) {
			$this->error = $langs->trans('MpesadarajaVerificationFailed');
			return -1;
		}

		return 0;
	}
}
