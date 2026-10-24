<?php
declare(strict_types=1);

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Atomically consume an M-Pesa confirmation when Dolibarr creates its payment.
 */
class InterfaceMpesadarajaTriggers extends DolibarrTriggers
{
	/**
	 * @param DoliDB $db Database handler.
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = 'MpesadarajaTriggers';
		$this->family = 'mpesadaraja';
		$this->description = 'Triggers for M-Pesa Daraja payments';
		$this->version = self::VERSIONS['prod'];
		$this->picto = 'fa-mobile-alt';
	}

	/**
	 * Consume the confirmed transaction during Dolibarr's payment transaction.
	 *
	 * @param string $action Trigger action.
	 * @param CommonObject $object Trigger object.
	 * @param User $user Current user.
	 * @param Translate $langs Language handler.
	 * @param Conf $conf Configuration.
	 * @return int
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf): int
	{
		if ($action !== 'PAYMENT_CUSTOMER_CREATE' || !isModEnabled('mpesadaraja') || !is_object($object) || $object->element !== 'payment' || $object->paiementcode !== 'MPESA') {
			return 0;
		}

		$reference = GETPOST('mpesa_reference', 'aZ09');
		$amount = price2num(GETPOST('amount', 'alphanohtml'), 'MT');
		if (!preg_match('/^MP[0-9]+[a-f0-9]{32}$/', $reference) || count((array) $object->amounts) !== 1) {
			$this->error = 'The M-Pesa transaction reference is invalid.';
			return -1;
		}
		$invoiceId = (int) key($object->amounts);
		if ($invoiceId <= 0 || (float) $object->amounts[$invoiceId] !== (float) $amount) {
			$this->error = 'The M-Pesa payment amount does not match the confirmed transaction.';
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'mpesadaraja_transaction';
		$sql .= ' SET status = \'PAID\', fk_paiement = '.((int) $object->id);
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_facture = '.$invoiceId;
		$sql .= " AND reference = '".$this->db->escape($reference)."'";
		$sql .= ' AND amount = '.((float) $amount)." AND status = 'SUCCESS' AND fk_paiement IS NULL";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = 'Unable to consume the confirmed M-Pesa transaction.';
			dol_syslog($this->error.': '.$this->db->lasterror(), LOG_ERR);
			return -1;
		}
		if ($this->db->affected_rows($resql) !== 1) {
			$this->error = 'The confirmed M-Pesa transaction was already used or does not match this payment.';
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'paiement';
		$sql .= " SET num_payment = '".$this->db->escape($reference)."'";
		$sql .= ' WHERE rowid = '.((int) $object->id);
		if (!$this->db->query($sql)) {
			$this->error = 'Unable to store the M-Pesa payment reference.';
			dol_syslog($this->error.': '.$this->db->lasterror(), LOG_ERR);
			return -1;
		}
		$object->num_payment = $reference;

		return 1;
	}
}
