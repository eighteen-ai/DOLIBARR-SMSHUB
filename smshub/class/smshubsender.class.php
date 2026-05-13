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

		$task_id = is_array($resp) && !empty($resp['task_id']) ? (int) $resp['task_id'] : null;
		$this->last_task_id = $task_id;
		$log->updateStatus($scheduled_at ? SmsHubLog::STATUS_SCHEDULED : SmsHubLog::STATUS_SENT, $task_id);
		return true;
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

		return array(
			'client_name' => $facture->thirdparty ? $facture->thirdparty->name : '',
			'company_name' => $mysoc->name ?? '',
			'ref' => $facture->ref,
			'amount' => price($amount, 0, $langs, 1, -1, -1, $conf->currency ?? 'EUR'),
			'amount_remaining' => price($remaining, 0, $langs, 1, -1, -1, $conf->currency ?? 'EUR'),
			'due_date' => $due ? dol_print_date($due, 'day') : '',
			'days_late' => $days_late,
			'payment_link' => $payment_link,
			'date' => dol_print_date($today, 'day'),
		);
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

		return array(
			'client_name' => $propal->thirdparty ? $propal->thirdparty->name : '',
			'company_name' => $mysoc->name ?? '',
			'ref' => $propal->ref,
			'amount' => price($propal->total_ttc, 0, $langs, 1, -1, -1, $conf->currency ?? 'EUR'),
			'amount_ht' => price($propal->total_ht, 0, $langs, 1, -1, -1, $conf->currency ?? 'EUR'),
			'valid_until' => $valid_until ? dol_print_date($valid_until, 'day') : '',
			'days_remaining' => $days_remaining,
			'signature_link' => $signature_link,
			'date' => dol_print_date($today, 'day'),
		);
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

		return array(
			'client_name' => !empty($ticket->thirdparty) ? $ticket->thirdparty->name : '',
			'company_name' => $mysoc->name ?? '',
			'ticket_ref' => $ticket->ref,
			'ticket_subject' => $ticket->subject ?? $ticket->track_id ?? '',
			'ticket_status' => $status_label,
			'technician' => $tech,
			'date' => dol_print_date(dol_now(), 'day'),
		);
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
