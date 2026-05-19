/* SMSHUB — inject "Send SMS to client" checkbox into Dolibarr's mail send form.
 *
 * Loaded on every Dolibarr page via module_parts['js']. No-ops unless:
 *   - URL contains action=presend
 *   - Page path is facture / propal / ticket card
 *   - The mail form (#mailform) is present in the DOM
 *
 * Fetches phone + SMS preview from /custom/smshub/ajax/mailform_data.php and
 * appends a row to the mail form table. Posts smshub_send_sms=1 when ticked
 * (a hidden 0 ensures the unticked state is also posted so the trigger handler
 * can distinguish "user opted out" from "form did not contribute"). */
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

		// Pull the object id from the URL or from the hidden field already inside the form.
		function getParam(name) {
			var m = qs.match(new RegExp('[?&]' + name + '=([^&]+)'));
			return m ? decodeURIComponent(m[1]) : '';
		}
		var objId = getParam(idParam) || form.find('input[name="' + idParam + '"]').val() || form.find('input[name="id"]').val() || '';
		if (!objId) return;

		// Build a placeholder row immediately, then enrich with phone + preview via AJAX.
		var placeholder =
			'<tr class="smshub_send_sms_row">' +
				'<td class="titlefield"><label for="smshub_send_sms_cb">📱 Envoyer aussi un SMS au client</label></td>' +
				'<td>' +
					'<input type="hidden" name="smshub_send_sms" value="0">' +
					'<input type="checkbox" id="smshub_send_sms_cb" name="smshub_send_sms" value="1">' +
					'<span class="smshub_send_sms_meta" style="margin-left:8px;color:#666;font-size:12px">…chargement…</span>' +
					'<div class="smshub_send_sms_preview" style="margin-top:4px;padding:6px;background:#fafafa;border:1px solid #ddd;font-size:12px;color:#444;white-space:pre-wrap;display:none"></div>' +
				'</td>' +
			'</tr>';

		var table = form.find('table.tableforemailform, table').first();
		if (!table.length) return;
		var tbody = table.find('tbody').first();
		(tbody.length ? tbody : table).append(placeholder);

		var row = form.find('.smshub_send_sms_row');
		var cb = row.find('input[type="checkbox"]');
		var meta = row.find('.smshub_send_sms_meta');
		var preview = row.find('.smshub_send_sms_preview');

		// Use DOL_URL_ROOT only if available, otherwise infer from current path.
		var docroot = (typeof window.DOL_URL_ROOT !== 'undefined') ? window.DOL_URL_ROOT : '/htdocs';
		var ajaxUrl = docroot + '/custom/smshub/ajax/mailform_data.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(objId);
		// Fallback path probe: if /htdocs isn't the actual root, try without.
		$.ajax({ url: ajaxUrl, dataType: 'json' })
			.fail(function () {
				return $.ajax({ url: '/custom/smshub/ajax/mailform_data.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(objId), dataType: 'json' });
			})
			.done(function (data) {
				if (!data || data.ok !== true) {
					meta.text(data && data.error ? data.error : 'numéro client introuvable');
					cb.prop('disabled', true);
					return;
				}
				if (!data.phone) {
					meta.text('aucun numéro mobile sur la fiche client');
					cb.prop('disabled', true);
					return;
				}
				meta.text('→ ' + data.phone + (data.template ? ' (modèle: ' + data.template + ')' : ''));
				cb.prop('checked', true);
				if (data.preview) {
					preview.text(data.preview);
					preview.show();
				}
			});
	});
})();
