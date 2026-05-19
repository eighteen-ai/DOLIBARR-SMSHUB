/* SMSHUB — inject "Send SMS to client" checkbox into Dolibarr's mail send form.
 *
 * Loaded on every Dolibarr page via module_parts['js']. No-ops unless:
 *   - URL contains action=presend
 *   - Page path is facture / propal / ticket card
 *   - The mail form (#mailform) is present in the DOM
 *
 * Fetches phone + SMS preview from /custom/smshub/ajax/mailform_data.php and
 * appends a row to the mail form table. The phone number is rendered as an
 * editable input so the user can override the thirdparty's number per-send.
 * A hidden 0 ensures the unticked state is also posted so the trigger handler
 * can distinguish "user opted out" from "form did not contribute". */
(function () {
	if (typeof jQuery === 'undefined') return;

	jQuery(function ($) {
		var qs = window.location.search || '';
		if (qs.indexOf('action=presend') === -1) return;

		var path = window.location.pathname || '';
		var type = null;
		var idParam = null;
		if (path.indexOf('/compta/facture/card.php') !== -1) { type = 'bill'; idParam = 'facid'; }
		else if (path.indexOf('/comm/propal/card.php') !== -1) { type = 'propal'; idParam = 'id'; }
		else if (path.indexOf('/ticket/card.php') !== -1) { type = 'ticket'; idParam = 'id'; }
		else return;

		var form = $('#mailform, form[name="mailform"]').first();
		if (!form.length) return;
		if (form.find('.smshub_send_sms_row').length) return;

		function getParam(name) {
			var m = qs.match(new RegExp('[?&]' + name + '=([^&]+)'));
			return m ? decodeURIComponent(m[1]) : '';
		}
		var objId = getParam(idParam) || form.find('input[name="' + idParam + '"]').val() || form.find('input[name="id"]').val() || '';
		if (!objId) return;

		var placeholder =
			'<tr class="smshub_send_sms_row">' +
				'<td class="titlefield"><label for="smshub_send_sms_cb">📱 Envoyer aussi un SMS au client</label></td>' +
				'<td>' +
					'<input type="hidden" name="smshub_send_sms" value="0">' +
					'<input type="checkbox" id="smshub_send_sms_cb" name="smshub_send_sms" value="1">' +
					' &nbsp; ' +
					'<input type="text" class="smshub_send_sms_phone_input" name="smshub_send_sms_phone" placeholder="+33600000000" style="width:180px" autocomplete="off">' +
					' <span class="smshub_send_sms_meta" style="margin-left:8px;color:#666;font-size:12px">…chargement…</span>' +
					'<textarea class="smshub_send_sms_textarea" name="smshub_send_sms_message" rows="4" style="display:none;width:95%;margin-top:6px;font-family:Menlo,Consolas,monospace;font-size:12px;background:#fafafa;border:1px solid #ddd;padding:8px;color:#333" placeholder="Texte du SMS — modifiable avant envoi"></textarea>' +
					'<div class="smshub_send_sms_count" style="display:none;font-size:11px;color:#888;text-align:right;margin-top:2px"></div>' +
				'</td>' +
			'</tr>';

		var table = form.find('table.tableforemailform, table').first();
		if (!table.length) return;
		var tbody = table.find('tbody').first();
		(tbody.length ? tbody : table).append(placeholder);

		var row = form.find('.smshub_send_sms_row');
		var cb = row.find('input[type="checkbox"]');
		var phoneInput = row.find('.smshub_send_sms_phone_input');
		var meta = row.find('.smshub_send_sms_meta');
		var textarea = row.find('.smshub_send_sms_textarea');
		var counter = row.find('.smshub_send_sms_count');

		// Standard SMS lengths: 160 chars for GSM-7, 70 for any-Unicode-char message.
		// Pure-ASCII heuristic is rough but enough for a hint.
		function updateCount() {
			var v = textarea.val() || '';
			var len = v.length;
			var unicode = /[^\x00-\x7F]/.test(v);
			var segmentSize = unicode ? 70 : 160;
			var segments = len === 0 ? 0 : Math.ceil(len / segmentSize);
			counter.text(len + ' caractère' + (len > 1 ? 's' : '') +
				(segments > 0 ? ' · ' + segments + ' SMS (' + segmentSize + ' / segment' + (unicode ? ', mode Unicode' : '') + ')' : ''));
		}

		var docroot = (typeof window.DOL_URL_ROOT !== 'undefined') ? window.DOL_URL_ROOT : '/htdocs';
		var ajaxUrl = docroot + '/custom/smshub/ajax/mailform_data.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(objId);
		$.ajax({ url: ajaxUrl, dataType: 'json' })
			.fail(function () {
				return $.ajax({ url: '/custom/smshub/ajax/mailform_data.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(objId), dataType: 'json' });
			})
			.done(function (data) {
				if (!data || data.ok !== true) {
					meta.text(data && data.error ? data.error : 'numéro client introuvable');
					return;
				}
				phoneInput.val(data.phone || '');
				if (data.phone) {
					cb.prop('checked', true);
					meta.text(data.template ? '(modèle : ' + data.template + ')' : '');
				} else {
					meta.text('aucun numéro mobile sur la fiche client — saisissez-le ci-dessus');
				}
				if (data.preview) {
					textarea.val(data.preview);
					textarea.show();
					counter.show();
					updateCount();
					textarea.on('input', updateCount);
				}
			});
	});
})();
