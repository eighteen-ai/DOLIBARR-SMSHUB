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
					'<div class="smshub_send_sms_preview" style="margin-top:6px;padding:8px;background:#fafafa;border:1px solid #ddd;font-size:12px;color:#444;white-space:pre-wrap;display:none"></div>' +
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
		var preview = row.find('.smshub_send_sms_preview');

		// Re-render the preview locally when the phone field is the only change.
		// (The server-rendered preview doesn't include the phone, so this is mostly
		// cosmetic — but we keep the user oriented.)
		function refreshMetaFromInput() {
			var v = (phoneInput.val() || '').trim();
			if (!v) {
				meta.text('aucun numéro saisi — le SMS ne sera pas envoyé');
			} else {
				meta.text('→ ' + v);
			}
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
					preview.text(data.preview);
					preview.show();
				}
				phoneInput.on('input', refreshMetaFromInput);
			});
	});
})();
