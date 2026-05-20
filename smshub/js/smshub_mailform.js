/* SMSHUB — inject "Send SMS to client" row (with editable textarea) into
 * Dolibarr's mail send form. Loaded on every page via module_parts['js'].
 * Version stamp logged to console so the loaded file is identifiable. */
(function () {
	var SMSHUB_JS_VERSION = '1.1.16';
	function log() {
		if (typeof console !== 'undefined' && console.log) {
			var args = ['[SMSHUB]'];
			for (var i = 0; i < arguments.length; i++) args.push(arguments[i]);
			console.log.apply(console, args);
		}
	}
	log('mailform JS', SMSHUB_JS_VERSION);

	if (typeof jQuery === 'undefined') return;

	jQuery(function ($) {
		var qs = window.location.search || '';
		var path = window.location.pathname || '';

		// Detect object type from URL path. Patterns covered:
		//   /(htdocs/)?compta/facture/card.php
		//   /(htdocs/)?comm/propal/card.php
		//   /(htdocs/)?ticket/card.php
		var type = null;
		var idParam = null;
		if (/\/compta\/facture\/card\.php/.test(path)) { type = 'bill'; idParam = 'facid'; }
		else if (/\/comm\/propal\/card\.php/.test(path)) { type = 'propal'; idParam = 'id'; }
		else if (/\/ticket\/card\.php/.test(path)) { type = 'ticket'; idParam = 'id'; }
		else return;

		// Look for ANY Dolibarr mail form on the page — don't gate on action=presend,
		// because ticket pages use action=presend_addmessage or similar variants and
		// the form-id/name remains stable across them.
		var form = $('#mailform, form[name="mailform"]').first();
		if (!form.length) {
			log('type=' + type + ' but no #mailform on page (path=' + path + ', qs=' + qs + ')');
			return;
		}
		if (form.find('.smshub_send_sms_row').length) return;
		log('injecting SMS row for type=' + type);

		function getParam(name) {
			var m = qs.match(new RegExp('[?&]' + name + '=([^&]+)'));
			return m ? decodeURIComponent(m[1]) : '';
		}
		// Try URL param, then hidden input named idParam, then any hidden input named "id",
		// then "track_id" (tickets often link via track_id rather than rowid).
		var objId = getParam(idParam)
			|| form.find('input[name="' + idParam + '"]').val()
			|| form.find('input[name="id"]').val()
			|| getParam('id')
			|| getParam('track_id')
			|| '';
		if (!objId) {
			log('no object id resolvable for type=' + type + ' (url=' + path + qs + ')');
			return;
		}

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
					'<textarea class="smshub_send_sms_textarea" name="smshub_send_sms_message" rows="3" style="width:95%;margin-top:6px;font-family:Menlo,Consolas,monospace;font-size:12px;background:#fafafa;border:1px solid #c0c0c0;border-radius:4px;padding:8px;color:#222;box-sizing:border-box;overflow:hidden;resize:vertical" placeholder="Texte du SMS — modifiable avant envoi (chargement en cours…)"></textarea>' +
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

		// When the user manually types a phone number, auto-check the box so they
		// don't have to think about it. Empty input → uncheck.
		phoneInput.on('input', function () {
			cb.prop('checked', (phoneInput.val() || '').trim() !== '');
		});

		// Counter shows length + estimated segment count. SMSHUB sends long SMS
		// as multi-segment messages with no cap on length — the counter is purely
		// informative (operator billing is per segment).
		function updateCount() {
			var v = textarea.val() || '';
			var len = v.length;
			var unicode = /[^\x00-\x7F]/.test(v);
			var segmentSize = unicode ? 70 : 160;
			var segments = len === 0 ? 0 : Math.ceil(len / segmentSize);
			var seg = segments > 0
				? ' · ' + segments + ' segment' + (segments > 1 ? 's' : '') + ' SMS' + (unicode ? ' (Unicode)' : '')
				: '';
			counter.text(len + ' caractère' + (len > 1 ? 's' : '') + seg);
		}
		// Auto-resize to fit the content — long SMS aren't capped, only scrolled
		// otherwise, which made users think the text was truncated.
		function autoResize() {
			textarea[0].style.height = 'auto';
			textarea[0].style.height = (textarea[0].scrollHeight + 4) + 'px';
		}
		textarea.on('input', function () { updateCount(); autoResize(); });

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
				autoResize();
			});
	});
})();
