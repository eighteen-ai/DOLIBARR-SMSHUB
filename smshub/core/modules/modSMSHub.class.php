<?php
/* Copyright (C) 2026 SMSHUB
 *
 * Dolibarr module descriptor for SMSHUB integration.
 * Provides: native SMS driver, automated reminders, ticket/invoice notifications,
 * SMS templates with dynamic variable substitution.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modSMSHub extends DolibarrModules
{
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;
		$this->numero = 500200;
		$this->rights_class = 'smshub';
		$this->family = "crm";
		$this->module_position = '80';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Passerelle SMSHUB : envoi SMS via routeurs 4G locaux. Notifications factures/tickets, relances impayés automatiques.";
		$this->descriptionlong = "Intègre SMSHUB (https://smshub.siliteo.com) à Dolibarr. Driver SMS natif compatible avec le module SMS standard, plus automatisations avancées : relances clients par paliers, notifications création/paiement de factures, alertes tickets, modèles SMS avec variables dynamiques.";
		$this->editor_name = 'SMSHUB';
		$this->editor_url = 'https://smshub.siliteo.com';
		$this->version = '1.1.1';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'phoning';

		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array('hookcontext' => array('invoicecard', 'ticketcard', 'thirdpartycard', 'propalcard')),
			'models' => 0,
		);

		$this->dirs = array("/smshub/temp");
		$this->config_page_url = array("setup.php@smshub");

		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(18, 0);
		$this->langfiles = array("smshub@smshub");

		// Constants set at module activation
		$this->const = array(
			0 => array('SMSHUB_SERVER_URL', 'chaine', 'https://smshub.siliteo.com/SERVER', 'URL du serveur SMSHUB', 0, 'current', 0),
			1 => array('SMSHUB_API_KEY', 'chaine', '', 'Clé API SMSHUB (X-Api-Key)', 0, 'current', 0),
			2 => array('SMSHUB_DEFAULT_COUNTRY_CODE', 'chaine', '+33', 'Indicatif pays par défaut', 0, 'current', 0),
			3 => array('SMSHUB_SENDER_NAME', 'chaine', '', 'Nom expéditeur (informatif)', 0, 'current', 0),
			4 => array('SMSHUB_ENABLE_BILL_VALIDATE', 'chaine', '0', 'SMS à la validation facture', 0, 'current', 0),
			5 => array('SMSHUB_ENABLE_BILL_PAYED', 'chaine', '1', 'SMS au paiement facture', 0, 'current', 0),
			6 => array('SMSHUB_ENABLE_TICKET_CREATE', 'chaine', '1', 'SMS à la création ticket', 0, 'current', 0),
			7 => array('SMSHUB_ENABLE_TICKET_MODIFY', 'chaine', '0', 'SMS à chaque modif ticket', 0, 'current', 0),
			8 => array('SMSHUB_ENABLE_TICKET_CLOSE', 'chaine', '1', 'SMS à la clôture ticket', 0, 'current', 0),
			9 => array('SMSHUB_DRYRUN', 'chaine', '0', 'Mode test : pas d\'envoi réel', 0, 'current', 0),
			10 => array('SMSHUB_ENABLE_PROPAL_VALIDATE', 'chaine', '0', 'SMS à la validation d\'un devis', 0, 'current', 0),
			11 => array('SMSHUB_ENABLE_PROPAL_SENT', 'chaine', '1', 'SMS quand un devis est envoyé par mail', 0, 'current', 0),
			12 => array('SMSHUB_ENABLE_PROPAL_SIGNED', 'chaine', '1', 'SMS à la signature d\'un devis', 0, 'current', 0),
			13 => array('SMSHUB_ENABLE_PROPAL_REFUSED', 'chaine', '0', 'SMS au refus d\'un devis', 0, 'current', 0),
			14 => array('SMSHUB_BRIDGE_PUBLIC', 'chaine', '1', 'Exposer le bridge SMSHUB aux autres modules (ex : RelanceAuto)', 0, 'current', 0),
		);

		// Boxes / Widgets
		$this->boxes = array();

		// Permissions
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = 500201;
		$this->rights[$r][1] = 'Envoyer des SMS via SMSHUB';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'send';
		$r++;

		$this->rights[$r][0] = 500202;
		$this->rights[$r][1] = 'Administrer SMSHUB (templates, config)';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'admin';
		$r++;

		$this->rights[$r][0] = 500203;
		$this->rights[$r][1] = 'Lire le journal des envois';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'read';
		$r++;

		// Top menu
		$this->menu = array();
		$r = 0;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'top',
			'titre' => 'SMSHUB',
			'mainmenu' => 'smshub',
			'leftmenu' => '',
			'url' => '/custom/smshub/admin/dashboard.php',
			'langs' => 'smshub@smshub',
			'position' => 200,
			'enabled' => '$conf->smshub->enabled',
			'perms' => '$user->admin || $user->hasRight("smshub","read")',
			'target' => '',
			'user' => 2,
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=smshub',
			'type' => 'left',
			'titre' => 'Tableau de bord',
			'mainmenu' => 'smshub',
			'leftmenu' => 'smshub_dashboard',
			'url' => '/custom/smshub/admin/dashboard.php',
			'langs' => 'smshub@smshub',
			'position' => 100,
			'enabled' => '$conf->smshub->enabled',
			'perms' => '$user->admin || $user->hasRight("smshub","read")',
			'target' => '',
			'user' => 2,
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=smshub',
			'type' => 'left',
			'titre' => 'Envoi rapide',
			'mainmenu' => 'smshub',
			'leftmenu' => 'smshub_send',
			'url' => '/custom/smshub/admin/send.php',
			'langs' => 'smshub@smshub',
			'position' => 200,
			'enabled' => '$conf->smshub->enabled',
			'perms' => '$user->admin || $user->hasRight("smshub","send")',
			'target' => '',
			'user' => 2,
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=smshub',
			'type' => 'left',
			'titre' => 'Modèles SMS',
			'mainmenu' => 'smshub',
			'leftmenu' => 'smshub_templates',
			'url' => '/custom/smshub/admin/templates.php',
			'langs' => 'smshub@smshub',
			'position' => 300,
			'enabled' => '$conf->smshub->enabled',
			'perms' => '$user->admin || $user->hasRight("smshub","admin")',
			'target' => '',
			'user' => 2,
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=smshub',
			'type' => 'left',
			'titre' => 'Journal',
			'mainmenu' => 'smshub',
			'leftmenu' => 'smshub_log',
			'url' => '/custom/smshub/admin/log.php',
			'langs' => 'smshub@smshub',
			'position' => 500,
			'enabled' => '$conf->smshub->enabled',
			'perms' => '$user->admin || $user->hasRight("smshub","read")',
			'target' => '',
			'user' => 2,
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=smshub',
			'type' => 'left',
			'titre' => 'Configuration',
			'mainmenu' => 'smshub',
			'leftmenu' => 'smshub_setup',
			'url' => '/custom/smshub/admin/setup.php',
			'langs' => 'smshub@smshub',
			'position' => 900,
			'enabled' => '$conf->smshub->enabled',
			'perms' => '$user->admin || $user->hasRight("smshub","admin")',
			'target' => '',
			'user' => 2,
		);
		$r++;

		// Cron jobs : aucun (les relances sont gérées par le module RelanceAuto)
		$this->cronjobs = array();
	}

	public function init($options = '')
	{
		$sql = array();
		$result = $this->_load_tables('/smshub/sql/');
		if ($result < 0) return -1;
		return $this->_init($sql, $options);
	}

	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
