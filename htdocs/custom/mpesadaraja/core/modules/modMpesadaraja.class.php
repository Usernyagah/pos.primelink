<?php
declare(strict_types=1);

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor for the M-Pesa Daraja TakePOS integration.
 */
class modMpesadaraja extends DolibarrModules
{
	/**
	 * @param DoliDB $db Database handler.
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->numero = 50160;
		$this->rights_class = 'mpesadaraja';
		$this->family = 'financial';
		$this->module_position = '90';
		$this->name = 'Mpesadaraja';
		$this->description = 'M-Pesa Daraja STK Push payments for TakePOS';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_MPESADARAJA';
		$this->picto = 'fa-mobile-alt';
		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'theme' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'hooks' => array(
				'data' => array('takepospay', 'takeposinvoice'),
				'entity' => '0'
			)
		);
		$this->dirs = array();
		$this->config_page_url = array('setup.php@mpesadaraja');
		$this->depends = array('always' => array('modTakePos', 'modBanque'));
		$this->phpmin = array(8, 1);
		$this->need_dolibarr_version = array(20, 0);
		$this->langfiles = array('mpesadaraja@mpesadaraja');
		$this->const = array();
		$this->rights = array();
		$this->menu = array();
		$this->tabs = array();
		$this->boxes = array();
		$this->cronjobs = array();

		if (!isModEnabled('mpesadaraja')) {
			$conf->mpesadaraja = new stdClass();
			$conf->mpesadaraja->enabled = 0;
		}
	}

	/**
	 * Initialize module database objects.
	 *
	 * @param string $options Activation options.
	 * @return int
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/install/mysql/', 'mpesadaraja');
		if ($result < 0) {
			return -1;
		}

		return $this->_init(array(), $options);
	}

	/**
	 * Remove module configuration without deleting payment history.
	 *
	 * @param string $options Deactivation options.
	 * @return int
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
