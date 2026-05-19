<?php
/* Copyright (C) 2026 SMSHUB - Hook handler:
 *   - "Send SMS via SMSHUB" action button on key cards
 *   - SMS log block in the right column
 *   - "Also send a SMS to the client" checkbox injected into the mail form
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
	 * Hook called on card pages: outputs SMS log block + injects the "send SMS"
	 * checkbox into the mail form (when action=presend) via a small JS payload.
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;
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

		$html = '';

		// 1) Mail-form checkbox injection (only when the user is on the "Send by email" tab).
		if ($action === 'presend' && ($user->admin || $user->hasRight('smshub', 'send'))) {
			$html .= $this->renderMailCheckbox($source, $object);
		}

		// 2) Recent SMS log for this object.
		require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshublog.class.php';
		$rows = SmsHubLog::listRecent($this->db, 10, array('source' => $source));
		$rows = array_filter($rows, function($r) use ($object) { return (int) $r->fk_source === (int) $object->id; });
		if (!empty($rows)) {
			$html .= '<div class="info" style="margin-top:10px">';
			$html .= '<strong>SMS envoyés (SMSHUB)</strong><table class="noborder centpercent">';
			foreach ($rows as $r) {
				$html .= '<tr><td>'.dol_print_date($this->db->jdate($r->datec), 'dayhour').'</td>';
				$html .= '<td>'.dol_escape_htmltag($r->phone).'</td>';
				$html .= '<td>'.dol_escape_htmltag($r->status).'</td></tr>';
			}
			$html .= '</table></div>';
		}

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

	/**
	 * Build the HTML payload that injects a "send SMS" checkbox row into the
	 * standard Dolibarr mail form (#mailform). Uses jQuery (always present on
	 * Dolibarr admin pages). Renders a preview of the SMS the customer will get.
	 */
	protected function renderMailCheckbox($source, $object)
	{
		require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubsender.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubtemplate.class.php';

		// Map (source, object) → template + vars.
		$template_code = null;
		$vars = array();
		switch ($source) {
			case 'bill':
				$template_code = 'bill_validated';
				$vars = SmsHubSender::buildBillVars($object);
				$phone = SmsHubSender::thirdpartyPhone($object->thirdparty ?? null);
				break;
			case 'propal':
				$template_code = 'propal_sent';
				$vars = SmsHubSender::buildPropalVars($object);
				$phone = SmsHubSender::thirdpartyPhone($object->thirdparty ?? null);
				break;
			case 'ticket':
				$template_code = 'ticket_modified';
				$vars = SmsHubSender::buildTicketVars($object);
				if (empty($object->thirdparty) && !empty($object->fk_soc)) {
					require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
					$soc = new Societe($this->db);
					$soc->fetch($object->fk_soc);
					$object->thirdparty = $soc;
				}
				$phone = SmsHubSender::thirdpartyPhone($object->thirdparty ?? null);
				break;
			default:
				return '';
		}

		// Fetch + render the template body for preview. Silent on missing template.
		$preview = '';
		if ($template_code) {
			$tpl = new SmsHubTemplate($this->db);
			if ($tpl->fetchByCode($template_code) > 0 && $tpl->active) {
				$preview = SmsHubTemplate::render($tpl->content, $vars);
			}
		}

		$has_phone = !empty($phone);
		$default_checked = $has_phone ? 'checked' : '';
		$disabled = $has_phone ? '' : 'disabled';
		$phone_label = $has_phone ? dol_escape_htmltag($phone) : 'aucun numéro mobile sur la fiche client';

		$preview_html = $preview
			? '<div id="smshub_sms_preview" style="margin-top:4px;padding:6px;background:#fafafa;border:1px solid #ddd;font-size:12px;color:#444;white-space:pre-wrap">'.dol_escape_htmltag($preview).'</div>'
			: '<div id="smshub_sms_preview" style="margin-top:4px;font-size:12px;color:#888;font-style:italic">Aucun modèle SMS actif pour ce contexte ('.dol_escape_htmltag($template_code).')</div>';

		// Hidden "0" sent when checkbox is unchecked; checkbox overrides when ticked.
		// jQuery appends a new <tr> at the end of the mail form's main table.
		$row = '<tr class="smshub_send_sms_row">'
			.'<td class="titlefield"><label for="smshub_send_sms_cb">📱 Envoyer aussi un SMS au client</label></td>'
			.'<td>'
			.'<input type="hidden" name="smshub_send_sms" value="0">'
			.'<input type="checkbox" id="smshub_send_sms_cb" name="smshub_send_sms" value="1" '.$default_checked.' '.$disabled.'>'
			.' <span style="color:#666;font-size:12px">→ '.$phone_label.'</span>'
			.$preview_html
			.'</td></tr>';

		$row_js = json_encode($row);

		return <<<HTML
<script type="text/javascript">
jQuery(document).ready(function(\$) {
	var form = \$('#mailform, form[name="mailform"]').first();
	if (!form.length) return;
	if (form.find('.smshub_send_sms_row').length) return; // already injected
	var table = form.find('table').first();
	if (table.length) {
		table.find('tbody').length ? table.find('tbody').append($row_js) : table.append($row_js);
	} else {
		form.append($row_js);
	}
});
</script>
HTML;
	}
}
