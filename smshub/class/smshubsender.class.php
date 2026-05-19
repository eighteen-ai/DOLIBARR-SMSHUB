<?php
/* Copyright (C) 2026 SMSHUB - Central send orchestrator (template + API + log) */

require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubapi.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubtemplate.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshublog.class.php';

class SmsHubSender
{
	public $db;
	public $api;
	public $last_error;
	public $last_log_id;
	public $last_task_id;

	public function __construct($db)
	{
		$this->db = $db;
		$this->api = new SmsHubApi();
	}

	/**
	 * Send a SMS rendered from a template.
	 *
	 * @param string $template_code Template code (e.g. 'bill_validated')
	 * @param string $phone Destination
	 * @param array $vars Variables for substitution
	 * @param string $source Source tag (manual|bill|ticket|relance|cron)
	 * @param int $fk_source Foreign key on the source object (rowid)
	 * @param string|null $scheduled_at Optional schedule
	 * @param User|null $user
	 * @return bool
	 */
	public function sendFromTemplate($template_code, $phone, array $vars, $source = 'manual', $fk_source = 0, $scheduled_at = null, $user = null)
	{
		$tpl = new SmsHubTemplate($this->db);
		if ($tpl->fetchByCode($template_code) <= 0) {
			$this->last_error = 'Modèle SMS introuvable : '.$template_code;
			return false;
		}
		if (!$tpl->active) {
			$this->last_error = 'Modèle désactivé : '.$template_code;
			return false;
		}
		$message = SmsHubTemplate::render($tpl->content, $vars);
		return $this->sendDirect($phone, $message, $source, $fk_source, $scheduled_at, $template_code, $user);
	}

	/**
	 * Send a direct SMS (no template).
	 */
	public function sendDirect($phone, $message, $source = 'manual', $fk_source = 0, $scheduled_at = null, $template_code = null, $user = null)
	{
		if (empty($user)) { global $user; }
		$normalized = $this->api->normalizePhone($phone);

		$log = new SmsHubLog($this->db);
		$log->phone = $normalized;
		$log->message = $message;
		$log->source = $source;
		$log->fk_source = (int) $fk_source;
		$log->template_code = $template_code;
		$log->scheduled_at = $scheduled_at;
		$log->fk_user = $user ? $user->id : 0;

		$dryrun = (int) getDolGlobalString('SMSHUB_DRYRUN', '0') === 1;

		// Test-phone bypass: if dry-run is on but the destination matches the configured
		// test phone, force a real send so the full pipeline (including scheduled_at) can
		// be verified end-to-end without disabling dry-run for everything else.
		$testPhoneRaw = getDolGlobalString('SMSHUB_TEST_PHONE', '');
		if ($dryrun && !empty($testPhoneRaw)) {
			$testPhone = $this->api->normalizePhone($testPhoneRaw);
			if ($testPhone !== '' && $testPhone === $normalized) {
				$dryrun = false;
				$log->message = '[TEST] '.$log->message;
			}
		}

		if ($dryrun) {
			$log->status = SmsHubLog::STATUS_DRYRUN;
			$log->create($user);
			$this->last_log_id = $log->id;
			$this->last_task_id = null;
			return true;
		}

		$log->status = $scheduled_at ? SmsHubLog::STATUS_SCHEDULED : SmsHubLog::STATUS_PENDING;
		$log->create($user);
		$this->last_log_id = $log->id;

		$resp = $this->api->send($normalized, $message, $scheduled_at);
		if ($resp === false) {
			$this->last_error = $this->api->last_error ?: 'Échec inconnu';
			$log->updateStatus(SmsHubLog::STATUS_FAILED, null, $this->last_error);
			return false;
		}

		// SMSHUB doc states task_id is always returned on a successful send.
		// If it is missing, treat the call as failed and surface the raw body
		// so the user can diagnose what the server actually returned.
		$task_id = is_array($resp) && isset($resp['task_id']) && ($resp['task_id'] !== null && $resp['task_id'] !== '' && (int) $resp['task_id'] > 0)
			? (int) $resp['task_id'] : null;

		if ($task_id === null) {
			$rawDiag = 'API succeeded (HTTP '.$this->api->last_http_code.') but task_id missing/invalid in response — body: '.dol_substr((string) $this->api->last_raw_body, 0, 500);
			$this->last_error = $rawDiag;
			$log->updateStatus(SmsHubLog::STATUS_FAILED, null, $rawDiag);
			return false;
		}

		$this->last_task_id = $task_id;
		$log->updateStatus($scheduled_at ? SmsHubLog::STATUS_SCHEDULED : SmsHubLog::STATUS_SENT, $task_id);
		$this->logActionComm($source, (int) $fk_source, $normalized, $message, $task_id, $user);
		return true;
	}

	/**
	 * Record a Dolibarr agenda event (llx_actioncomm) for the SMS we just sent,
	 * so it appears in the object's history tab and so sibling modules like
	 * DOLIBARR-FILTRABLENOTIFICATION can detect "last client notification" by
	 * filtering on code LIKE '%SENTBYSMS'. Mirrors DOLIBARR-CHORUSPRO's pattern
	 * (AC_BILL_SENTBYCHORUS).
	 */
	protected function logActionComm($source, $fk_source, $phone, $message, $task_id, $user)
	{
		// Only the object-bound sources produce an agenda entry. Manual sends to
		// an arbitrary number, dolibarr-API interceptions, and cron heartbeats
		// have no document to attach to.
		$map = array(
			'bill'   => array('code' => 'AC_BILL_SENTBYSMS',   'elementtype' => 'invoice', 'class' => 'Facture', 'path' => '/compta/facture/class/facture.class.php'),
			'propal' => array('code' => 'AC_PROPAL_SENTBYSMS', 'elementtype' => 'propal',  'class' => 'Propal',  'path' => '/comm/propal/class/propal.class.php'),
			'ticket' => array('code' => 'AC_TICKET_SENTBYSMS', 'elementtype' => 'ticket',  'class' => 'Ticket',  'path' => '/ticket/class/ticket.class.php'),
		);
		if (empty($map[$source]) || $fk_source <= 0) return;
		$cfg = $map[$source];

		require_once DOL_DOCUMENT_ROOT.$cfg['path'];
		$cls = $cfg['class'];
		$obj = new $cls($this->db);
		if ($obj->fetch($fk_source) <= 0) return;
		$socid = (int) ($obj->socid ?? ($obj->fk_soc ?? 0));

		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$ac = new ActionComm($this->db);
		$ac->type_code = 'AC_OTH_AUTO';
		$ac->code = $cfg['code'];
		$ac->label = 'SMS envoyé via SMSHUB'.(!empty($obj->ref) ? ' — '.$obj->ref : '');
		$ac->note_private = "Destinataire : ".$phone."\nTask SMSHUB : ".$task_id."\n\n".$message;
		$ac->datep = dol_now();
		$ac->datef = dol_now();
		$ac->percentage = -1;
		$ac->userownerid = (!empty($user) && !empty($user->id)) ? (int) $user->id : 0;
		$ac->elementtype = $cfg['elementtype'];
		$ac->fk_element = $fk_source;
		$ac->socid = $socid;
		// Best-effort: don't surface failures here — the SMS already went out.
		@$ac->create($user);
	}

	/**
	 * Build the third-party / contact variable subset shared across contexts.
	 * Prefers the billing contact's data when available, falls back to thirdparty.
	 *
	 * @param Societe $thirdparty
	 * @param Contact|null $contact Optional billing/customer contact
	 * @return array
	 */
	public static function buildThirdpartyVars($thirdparty, $contact = null)
	{
		$vars = array(
			'client_name' => $thirdparty->name ?? '',
			'client_firstname' => '',
			'client_lastname' => '',
			'client_civility' => '',
			'client_address' => $thirdparty->address ?? '',
			'client_zip' => $thirdparty->zip ?? '',
			'client_town' => $thirdparty->town ?? '',
			'client_country' => $thirdparty->country_code ?? $thirdparty->country ?? '',
			'client_email' => $thirdparty->email ?? '',
			'client_phone' => $thirdparty->phone_mobile ?? $thirdparty->phone ?? '',
		);
		if ($contact) {
			if (!empty($contact->firstname)) $vars['client_firstname'] = $contact->firstname;
			if (!empty($contact->lastname)) $vars['client_lastname'] = $contact->lastname;
			if (!empty($contact->civility)) $vars['client_civility'] = $contact->civility;
			if (!empty($contact->civility_code)) $vars['client_civility'] = $contact->civility_code;
			if (!empty($contact->address)) $vars['client_address'] = $contact->address;
			if (!empty($contact->zip)) $vars['client_zip'] = $contact->zip;
			if (!empty($contact->town)) $vars['client_town'] = $contact->town;
			if (!empty($contact->email)) $vars['client_email'] = $contact->email;
			if (!empty($contact->phone_pro)) $vars['client_phone'] = $contact->phone_pro;
			elseif (!empty($contact->phone_mobile)) $vars['client_phone'] = $contact->phone_mobile;
		}
		// Best-effort firstname/lastname split when none came from contact: only if name has exactly one space
		if (empty($vars['client_firstname']) && empty($vars['client_lastname']) && !empty($vars['client_name'])) {
			$parts = explode(' ', trim($vars['client_name']), 2);
			if (count($parts) === 2) {
				$vars['client_firstname'] = $parts[0];
				$vars['client_lastname'] = $parts[1];
			} else {
				$vars['client_lastname'] = $vars['client_name'];
			}
		}
		return $vars;
	}

	/**
	 * Load the customer/billing contact for an object that supports getIdContact().
	 */
	protected static function loadBillingContact($db, $object, $code = 'BILLING')
	{
		if (empty($object->id) || !method_exists($object, 'getIdContact')) return null;
		$ids = $object->getIdContact('external', $code);
		if (empty($ids)) {
			$ids = $object->getIdContact('external', 'CUSTOMER');
		}
		if (empty($ids)) return null;
		require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
		$c = new Contact($db);
		if ($c->fetch((int) $ids[0]) > 0) return $c;
		return null;
	}

	/**
	 * Configurable list of payment methods text (e.g. "virement, chèque ou carte (SumUp)")
	 * Used for the {payment_methods_text} variable.
	 */
	public static function paymentMethodsText()
	{
		return getDolGlobalString('SMSHUB_PAYMENT_METHODS_TEXT', 'virement, chèque ou carte bancaire');
	}

	/**
	 * Build the standard variable map for an invoice.
	 *
	 * @param Facture $facture
	 * @return array
	 */
	public static function buildBillVars($facture)
	{
		global $conf, $langs, $mysoc;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
		if (empty($facture->thirdparty)) $facture->fetch_thirdparty();

		$due = !empty($facture->date_lim_reglement) ? $facture->date_lim_reglement : 0;
		$today = dol_now();
		$days_late = $due ? max(0, (int) floor(($today - $due) / 86400)) : 0;
		$amount = price2num($facture->total_ttc, 'MT');
		$remaining = price2num($facture->total_ttc - ($facture->totalpaye ?: $facture->getSommePaiement()), 'MT');

		$payment_link = '';
		if (!empty($conf->global->ONLINE_PAYMENT_CREDITOR) && !empty($conf->global->PAYMENT_SECURITY_TOKEN)) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/payments.lib.php';
			$payment_link = getOnlinePaymentUrl(0, 'invoice', $facture->ref);
		}

		// Short-lived public document link (Dolibarr core helper). Falls back to the
		// internal card URL when the public helper is unavailable.
		$document_link = self::buildDocumentLink('invoice', $facture);

		$contact = self::loadBillingContact($GLOBALS['db'], $facture, 'BILLING');
		$base = self::buildThirdpartyVars($facture->thirdparty, $contact);
		return array_merge($base, array(
			'company_name' => $mysoc->name ?? '',
			'ref' => $facture->ref,
			'amount' => price($amount, 0, $langs, 1, -1, -1, $conf->currency ?? 'EUR'),
			'amount_remaining' => price($remaining, 0, $langs, 1, -1, -1, $conf->currency ?? 'EUR'),
			'due_date' => $due ? dol_print_date($due, 'day') : '',
			'days_late' => $days_late,
			'payment_link' => $payment_link,
			'document_link' => $document_link,
			'payment_methods_text' => self::paymentMethodsText(),
			'date' => dol_print_date($today, 'day'),
		));
	}

	/**
	 * Build the standard variable map for a commercial proposal (devis).
	 *
	 * @param Propal $propal
	 * @return array
	 */
	public static function buildPropalVars($propal)
	{
		global $conf, $langs, $mysoc;
		if (empty($propal->thirdparty)) $propal->fetch_thirdparty();

		// Online signature link if module available
		$signature_link = '';
		$onlineSignFile = DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php';
		if (file_exists($onlineSignFile)) {
			require_once $onlineSignFile;
			if (function_exists('getOnlineSignatureUrl')) {
				$signature_link = getOnlineSignatureUrl(0, 'proposal', $propal->ref);
			}
		}

		$valid_until = !empty($propal->fin_validite) ? $propal->fin_validite : 0;
		$today = dol_now();
		$days_remaining = $valid_until ? max(0, (int) floor(($valid_until - $today) / 86400)) : 0;

		$document_link = self::buildDocumentLink('propal', $propal);

		$contact = self::loadBillingContact($GLOBALS['db'], $propal, 'CUSTOMER');
		$base = self::buildThirdpartyVars($propal->thirdparty, $contact);
		return array_merge($base, array(
			'company_name' => $mysoc->name ?? '',
			'ref' => $propal->ref,
			'amount' => price($propal->total_ttc, 0, $langs, 1, -1, -1, $conf->currency ?? 'EUR'),
			'amount_ht' => price($propal->total_ht, 0, $langs, 1, -1, -1, $conf->currency ?? 'EUR'),
			'valid_until' => $valid_until ? dol_print_date($valid_until, 'day') : '',
			'days_remaining' => $days_remaining,
			'signature_link' => $signature_link,
			'document_link' => $document_link,
			'payment_methods_text' => self::paymentMethodsText(),
			'date' => dol_print_date($today, 'day'),
		));
	}

	/**
	 * Build the standard variable map for a ticket.
	 */
	public static function buildTicketVars($ticket)
	{
		global $mysoc, $langs;
		$tech = '';
		if (!empty($ticket->fk_user_assign)) {
			require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
			$u = new User($GLOBALS['db']);
			if ($u->fetch($ticket->fk_user_assign) > 0) {
				$tech = trim($u->firstname.' '.$u->lastname);
			}
		}
		if (empty($ticket->thirdparty) && !empty($ticket->fk_soc)) {
			require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
			$soc = new Societe($GLOBALS['db']);
			$soc->fetch($ticket->fk_soc);
			$ticket->thirdparty = $soc;
		}

		$labels = array(0 => 'À traiter', 1 => 'Lu', 3 => 'Assigné', 4 => 'En cours', 5 => 'Besoin info', 6 => 'En attente', 7 => 'En attente', 8 => 'Résolu', 9 => 'Fermé');
		$status_label = $labels[(int) $ticket->fk_statut] ?? (string) $ticket->fk_statut;

		$contact = self::loadBillingContact($GLOBALS['db'], $ticket, 'SUPPORTCLI');
		$base = !empty($ticket->thirdparty) ? self::buildThirdpartyVars($ticket->thirdparty, $contact) : array();
		$ticket_link = self::buildDocumentLink('ticket', $ticket);
		return array_merge($base, array(
			'company_name' => $mysoc->name ?? '',
			'ticket_ref' => $ticket->ref,
			'ticket_subject' => $ticket->subject ?? $ticket->track_id ?? '',
			'ticket_status' => $status_label,
			'ticket_link' => $ticket_link,
			'technician' => $tech,
			'date' => dol_print_date(dol_now(), 'day'),
		));
	}

	/**
	 * Build the public-facing URL for an object (invoice/propal/ticket).
	 *
	 * Strategy: prefer the public payment / signature / ticket-view pages exposed
	 * by Dolibarr core because those work without a Dolibarr login. Falls back
	 * to nothing when public pages are not configured.
	 *
	 * @param string $type 'invoice' | 'propal' | 'ticket'
	 * @param object $obj
	 * @return string
	 */
	public static function buildDocumentLink($type, $obj)
	{
		global $conf;
		if (empty($obj->ref)) return '';

		switch ($type) {
			case 'invoice':
				if (!empty($conf->global->ONLINE_PAYMENT_CREDITOR) && !empty($conf->global->PAYMENT_SECURITY_TOKEN)) {
					require_once DOL_DOCUMENT_ROOT.'/core/lib/payments.lib.php';
					return getOnlinePaymentUrl(0, 'invoice', $obj->ref);
				}
				return '';
			case 'propal':
				$f = DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php';
				if (file_exists($f)) {
					require_once $f;
					if (function_exists('getOnlineSignatureUrl')) {
						return getOnlineSignatureUrl(0, 'proposal', $obj->ref);
					}
				}
				return '';
			case 'ticket':
				$track = $obj->track_id ?? '';
				if (empty($track)) return '';
				$base = !empty($conf->global->TICKET_URL_PUBLIC_INTERFACE)
					? rtrim($conf->global->TICKET_URL_PUBLIC_INTERFACE, '/')
					: (defined('DOL_MAIN_URL_ROOT') ? rtrim(DOL_MAIN_URL_ROOT, '/').'/public/ticket' : '');
				if (empty($base)) return '';
				return $base.'/view.php?track_id='.urlencode($track);
		}
		return '';
	}

	/**
	 * Pick the first non-empty phone number of a thirdparty (priority: mobile → phone).
	 */
	public static function thirdpartyPhone($thirdparty)
	{
		if (empty($thirdparty)) return '';
		foreach (array('phone_mobile', 'phone') as $field) {
			if (!empty($thirdparty->$field)) return $thirdparty->$field;
		}
		return '';
	}
}
