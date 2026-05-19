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
		$this->version = '1.1.10';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'phoning';

		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array('hookcontext' => array(
				'invoicecard', 'ticketcard', 'thirdpartycard', 'propalcard',
				// 'sendsms' lets us intercept any Dolibarr SMS send (notifications module,
				// payment confirmations, etc.) and route it through SMSHUB. Activated only
				// when SMSHUB_INTERCEPT_DOLIBARR_SMS = 1. This IS the supported way to
				// integrate with Dolibarr's SMS layer; there is no "native SMS operator
				// registration" for third-party modules without shipping a full
				// CSMSFile-compatible class.
				'sendsms',
			)),
			// Global JS, loaded on every Dolibarr page. The script no-ops unless it
			// detects action=presend on a facture/propal/ticket card, in which case it
			// AJAX-fetches preview data and injects the "send SMS" checkbox into the
			// mail form. Page-level hooks (printCommonFooter / formObjectOptions) do
			// not fire reliably on action=presend in Dolibarr 23, hence the JS approach.
			'js' => array('/smshub/js/smshub_mailform.js'),
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
			15 => array('SMSHUB_TEST_PHONE', 'chaine', '', 'Numéro de test : bypass le dry-run quand le destinataire correspond', 0, 'current', 0),
			16 => array('SMSHUB_PAYMENT_METHODS_TEXT', 'chaine', 'virement, chèque ou carte bancaire', 'Texte des moyens de paiement (variable {payment_methods_text})', 0, 'current', 0),
			17 => array('SMSHUB_INTERCEPT_DOLIBARR_SMS', 'chaine', '0', 'Intercepter tous les SMS Dolibarr (CSMSFile) et les router via SMSHUB', 0, 'current', 0),
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
		// Drop the broken MAIN_MODULE_SMSHUB_SMS constant that v1.1.5–1.1.7 set to '1'
		// (Dolibarr's SMS test page errors out with "SMS manager '1' not found"). The
		// module never shipped a CSMSFile-compatible class, so this const should not
		// exist — integration with Dolibarr's SMS layer happens through the 'sendsms'
		// hook (SMSHUB_INTERCEPT_DOLIBARR_SMS), not through MAIN_MODULE_*_SMS.
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		dolibarr_del_const($this->db, 'MAIN_MODULE_SMSHUB_SMS', -1);

		$sql = array();
		$result = $this->_load_tables('/smshub/sql/');
		if ($result < 0) return -1;
		$initRes = $this->_init($sql, $options);
		// Belt-and-suspenders template seeding: the SQL data file already INSERT
		// IGNOREs defaults, but some installs end up without rows (older versions
		// shipped without that file, or the SQL loader skipped it). The PHP seeder
		// is fully idempotent — never overwrites customized templates.
		if ($initRes >= 0) {
			require_once dirname(__FILE__).'/../../class/smshubtemplate.class.php';
			global $user;
			SmsHubTemplate::seedDefaults($this->db, $user);
		}
		return $initRes;
	}

	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
