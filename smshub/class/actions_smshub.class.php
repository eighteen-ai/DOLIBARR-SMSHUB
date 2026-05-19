<?php
/* Copyright (C) 2026 SMSHUB - Hook handler:
 *   - "Send SMS via SMSHUB" action button on key cards
 *   - SMS log block in the right column
 *   - SMS interception of Dolibarr's CSMSFile (when SMSHUB_INTERCEPT_DOLIBARR_SMS=1)
 *
 * Note: the "send SMS to client" checkbox on the mail form is injected client-side
 * by js/smshub_mailform.js (loaded via module_parts['js']) — page-level hooks do
 * not fire reliably on action=presend in Dolibarr 23.
 */

class ActionsSmshub
{
	public $db;
	public $error = '';
	public $errors = array();
	public $results = array();
	public $resprints = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Adds a "Send SMS via SMSHUB" button to the action toolbar of invoice / ticket / thirdparty cards.
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $langs, $conf;
		if (empty($conf->smshub) || empty($conf->smshub->enabled)) return 0;
		if (!$user->admin && !$user->hasRight('smshub', 'send')) return 0;

		$ctx = $parameters['context'] ?? '';
		$wanted = array('invoicecard', 'ticketcard', 'thirdpartycard', 'propalcard');
		if (!array_intersect(explode(':', $ctx), $wanted)) return 0;

		$socid = 0;
		$phone = '';
		$prefill_template = '';

		if (!empty($object->socid)) $socid = (int) $object->socid;
		elseif (!empty($object->id) && (strpos($ctx, 'thirdpartycard') !== false)) $socid = (int) $object->id;

		if (strpos($ctx, 'invoicecard') !== false) $prefill_template = 'bill_validated';
		if (strpos($ctx, 'ticketcard') !== false) $prefill_template = 'ticket_modified';
		if (strpos($ctx, 'propalcard') !== false) $prefill_template = 'propal_sent';

		if (!empty($object->thirdparty)) {
			require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubsender.class.php';
			$phone = SmsHubSender::thirdpartyPhone($object->thirdparty);
		}

		$url = DOL_URL_ROOT.'/custom/smshub/admin/send.php?phone='.urlencode($phone);
		if ($socid) $url .= '&socid='.$socid;
		if ($prefill_template) $url .= '&prefill_template='.$prefill_template;

		$this->resprints = '<a class="butAction" href="'.$url.'" title="Envoyer un SMS via SMSHUB">📱 SMS via SMSHUB</a>';
		return 0;
	}

	/**
	 * Hook called on card pages: outputs the SMS log block in the right panel.
	 *
	 * Note: Dolibarr SKIPS this hook on most cards when action=presend (the
	 * regular display path is bypassed in favor of the mail form), so the
	 * "send SMS" checkbox is injected via printCommonFooter() below instead.
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf;
		if (empty($conf->smshub) || empty($conf->smshub->enabled)) return 0;

		$ctx = $parameters['context'] ?? '';
		$source_map = array(
			'invoicecard' => 'bill',
			'ticketcard' => 'ticket',
			'propalcard' => 'propal',
		);
		$source = null;
		foreach ($source_map as $k => $v) if (strpos($ctx, $k) !== false) { $source = $v; break; }
		if (!$source || empty($object->id)) return 0;

		require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshublog.class.php';
		$rows = SmsHubLog::listRecent($this->db, 10, array('source' => $source));
		$rows = array_filter($rows, function($r) use ($object) { return (int) $r->fk_source === (int) $object->id; });
		if (empty($rows)) return 0;

		$html = '<div class="info" style="margin-top:10px">';
		$html .= '<strong>SMS envoyés (SMSHUB)</strong><table class="noborder centpercent">';
		foreach ($rows as $r) {
			$html .= '<tr><td>'.dol_print_date($this->db->jdate($r->datec), 'dayhour').'</td>';
			$html .= '<td>'.dol_escape_htmltag($r->phone).'</td>';
			$html .= '<td>'.dol_escape_htmltag($r->status).'</td></tr>';
		}
		$html .= '</table></div>';

		$this->resprints = $html;
		return 0;
	}

	/**
	 * Intercept Dolibarr's standard SMS send (CSMSFile) and route it through SMSHUB.
	 *
	 * Dolibarr calls executeHooks('sendsms', ...) from CSMSFile->sendfile() so that
	 * external modules can override the transport. We honor it only when the admin
	 * has explicitly enabled SMSHUB_INTERCEPT_DOLIBARR_SMS in setup, so coexistence
	 * with native providers (OVH, etc.) remains predictable.
	 *
	 * Returning > 0 signals to Dolibarr that this hook handled the send.
	 */
	public function sendSms($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;
		if (empty($conf->smshub) || empty($conf->smshub->enabled)) return 0;
		if (!getDolGlobalString('SMSHUB_INTERCEPT_DOLIBARR_SMS')) return 0;

		$csms = $object;
		$phone = '';
		$message = '';
		foreach (array('addr_to', 'addr_dest', 'destination') as $f) if (!empty($csms->$f)) { $phone = $csms->$f; break; }
		foreach (array('message', 'body', 'msg') as $f) if (!empty($csms->$f)) { $message = $csms->$f; break; }
		if (empty($phone) || empty($message)) {
			$this->errors[] = 'SMSHUB: téléphone ou message vide (CSMSFile)';
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubsender.class.php';
		$sender = new SmsHubSender($this->db);
		$ok = $sender->sendDirect($phone, $message, 'dolibarr');
		if (!$ok) {
			$this->errors[] = 'SMSHUB: '.($sender->last_error ?: 'échec inconnu');
			return -1;
		}
		// Expose the task_id back to Dolibarr so logs upstream can reference it.
		if (property_exists($csms, 'transaction_id')) $csms->transaction_id = $sender->last_task_id;
		return 1;
	}

}
