/* SMSHUB — inject "Send SMS to client" row (with editable textarea) into
 * Dolibarr's mail send form. Loaded on every page via module_parts['js'].
 * Version stamp logged to console so the loaded file is identifiable. */
(function () {
	var SMSHUB_JS_VERSION = '1.1.12';
	if (typeof console !== 'undefined' && console.log) console.log('[SMSHUB] mailform JS', SMSHUB_JS_VERSION);

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

		// Textarea is always rendered (not hidden) so an out-of-date cached JS is
		// obvious vs. the new editable layout. Starts pre-populated with a hint;
		// AJAX overwrites it with the rendered template content.
		var placeholder =
			'<tr class="smshub_send_sms_row">' +
				'<td class="titlefield"><label for="smshub_send_sms_cb">📱 Envoyer aussi un SMS au client</label><br><span style="font-size:10px;color:#999">v' + SMSHUB_JS_VERSION + '</span></td>' +
				'<td>' +
					'<input type="hidden" name="smshub_send_sms" value="0">' +
					'<input type="checkbox" id="smshub_send_sms_cb" name="smshub_send_sms" value="1">' +
					' &nbsp; ' +
					'<input type="text" class="smshub_send_sms_phone_input" name="smshub_send_sms_phone" placeholder="+33600000000" style="width:180px" autocomplete="off">' +
					' <span class="smshub_send_sms_meta" style="margin-left:8px;color:#666;font-size:12px">…chargement…</span>' +
					'<br>' +
					'<textarea class="smshub_send_sms_textarea" name="smshub_send_sms_message" rows="5" style="width:95%;margin-top:6px;font-family:Menlo,Consolas,monospace;font-size:12px;background:#fafafa;border:1px solid #c0c0c0;border-radius:4px;padding:8px;color:#222;box-sizing:border-box" placeholder="Texte du SMS — modifiable avant envoi (chargement en cours…)"></textarea>' +
					'<div class="smshub_send_sms_count" style="font-size:11px;color:#888;text-align:right;margin-top:2px">—</div>' +
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

		function updateCount() {
			var v = textarea.val() || '';
			var len = v.length;
			var unicode = /[^\x00-\x7F]/.test(v);
			var segmentSize = unicode ? 70 : 160;
			var segments = len === 0 ? 0 : Math.ceil(len / segmentSize);
			counter.text(len + ' caractère' + (len > 1 ? 's' : '') +
				(segments > 0 ? ' · ' + segments + ' SMS (' + segmentSize + ' / segment' + (unicode ? ', mode Unicode' : '') + ')' : ''));
		}
		textarea.on('input', updateCount);

		var docroot = (typeof window.DOL_URL_ROOT !== 'undefined') ? window.DOL_URL_ROOT : '/htdocs';
		var ajaxUrl = docroot + '/custom/smshub/ajax/mailform_data.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(objId);
		$.ajax({ url: ajaxUrl, dataType: 'json' })
			.fail(function () {
				return $.ajax({ url: '/custom/smshub/ajax/mailform_data.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(objId), dataType: 'json' });
			})
			.done(function (data) {
				if (!data || data.ok !== true) {
					meta.text(data && data.error ? data.error : 'numéro client introuvable');
					textarea.attr('placeholder', 'Aucun template trouvé — saisissez le SMS ici');
					return;
				}
				phoneInput.val(data.phone || '');
				if (data.phone) {
					cb.prop('checked', true);
					meta.text(data.template ? '(modèle : ' + data.template + ')' : '');
				} else {
					meta.text('aucun numéro mobile sur la fiche client — saisissez-le ci-dessus');
				}
				textarea.val(data.preview || '');
				if (!data.preview) {
					textarea.attr('placeholder', 'Aucun modèle SMS — tapez le message à envoyer ici');
				}
				updateCount();
			});
	});
})();
