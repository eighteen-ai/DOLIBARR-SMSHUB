<?php
/* Copyright (C) 2026 SMSHUB - Template preview (render a template against a real object) */

$res = 0;
$path = dirname(__FILE__);
for ($i = 0; $i < 8; $i++) {
	$path = dirname($path);
	if (file_exists($path.'/main.inc.php')) { $res = @include $path.'/main.inc.php'; break; }
	if (file_exists($path.'/htdocs/main.inc.php')) { $res = @include $path.'/htdocs/main.inc.php'; break; }
}
if (!$res) die('Impossible de charger Dolibarr');

require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubsender.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubtemplate.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/lib/smshub.lib.php';

if (!$user->admin && !$user->hasRight('smshub', 'admin')) accessforbidden();

$langs->loadLangs(array("admin", "smshub@smshub"));

$action = GETPOST('action', 'aZ09');
$template_code = GETPOST('template_code', 'alphanohtml');
$object_type = GETPOST('object_type', 'alphanohtml');
$object_id = (int) GETPOST('object_id', 'int');

$preview = null;
$preview_error = null;
$vars_resolved = null;

if ($action === 'preview' && !empty($template_code) && !empty($object_type) && $object_id > 0) {
	$tpl = new SmsHubTemplate($db);
	if ($tpl->fetchByCode($template_code) <= 0) {
		$preview_error = 'Modèle introuvable : '.$template_code;
	} else {
		$vars = array();
		$object = null;
		switch ($object_type) {
			case 'bill':
				require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
				$object = new Facture($db);
				if ($object->fetch($object_id) > 0) {
					$object->fetch_thirdparty();
					$vars = SmsHubSender::buildBillVars($object);
				} else $preview_error = 'Facture #'.$object_id.' introuvable';
				break;
			case 'propal':
				require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
				$object = new Propal($db);
				if ($object->fetch($object_id) > 0) {
					$object->fetch_thirdparty();
					$vars = SmsHubSender::buildPropalVars($object);
				} else $preview_error = 'Devis #'.$object_id.' introuvable';
				break;
			case 'ticket':
				require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';
				$object = new Ticket($db);
				if ($object->fetch($object_id) > 0) {
					if (!empty($object->fk_soc)) {
						require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
						$soc = new Societe($db); $soc->fetch($object->fk_soc);
						$object->thirdparty = $soc;
					}
					$vars = SmsHubSender::buildTicketVars($object);
				} else $preview_error = 'Ticket #'.$object_id.' introuvable';
				break;
			default:
				$preview_error = 'Type d\'objet inconnu';
		}

		if (!$preview_error) {
			$preview = SmsHubTemplate::render($tpl->content, $vars);
			$vars_resolved = $vars;
			$preview_phone = SmsHubSender::thirdpartyPhone($object->thirdparty ?? null);
		}
	}
}

llxHeader('', 'SMSHUB - Prévisualisation');
print load_fiche_titre('Prévisualisation d\'un modèle SMS', '', 'phoning');
print dol_get_fiche_head(smshubAdminTabs(), 'preview', '', -1);

$templates = SmsHubTemplate::listAll($db, null, true);

print '<form method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="preview">';
print '<table class="noborder centpercent">';

print '<tr class="oddeven"><td class="titlefield">Modèle</td><td>';
print '<select name="template_code" required><option value="">— Choisir un modèle —</option>';
foreach ($templates as $t) {
	$sel = $template_code === $t->code ? ' selected' : '';
	print '<option value="'.dol_escape_htmltag($t->code).'" data-context="'.dol_escape_htmltag($t->context).'"'.$sel.'>'.dol_escape_htmltag($t->label.' ['.$t->context.']').'</option>';
}
print '</select></td></tr>';

print '<tr class="oddeven"><td>Type d\'objet</td><td>';
print '<select name="object_type" required>';
foreach (array('bill' => 'Facture', 'propal' => 'Devis (proposition)', 'ticket' => 'Ticket') as $k => $l) {
	$sel = $object_type === $k ? ' selected' : '';
	print '<option value="'.$k.'"'.$sel.'>'.$l.'</option>';
}
print '</select></td></tr>';

print '<tr class="oddeven"><td>ID de l\'objet (rowid)</td><td>';
print '<input type="number" name="object_id" value="'.($object_id ?: '').'" min="1" required class="flat">';
print ' <span class="opacitymedium">ID numérique visible dans l\'URL Dolibarr de la fiche (?id=NN)</span>';
print '</td></tr>';

print '</table>';
print '<div class="center" style="margin-top:15px"><button type="submit" class="button">Prévisualiser</button></div>';
print '</form>';

if ($preview_error) {
	print '<div class="error" style="margin-top:15px">'.dol_escape_htmltag($preview_error).'</div>';
}

if ($preview !== null) {
	print '<br><h3>Rendu du SMS</h3>';
	print '<div style="border:2px solid #25d366;border-radius:12px;padding:15px;background:#dcf8c6;max-width:500px;margin:10px 0;font-family:sans-serif">';
	print '<div style="font-size:0.85em;color:#888;margin-bottom:5px">Destinataire : '.dol_escape_htmltag($preview_phone ?: '(aucun téléphone sur le tiers)').'</div>';
	print nl2br(dol_escape_htmltag($preview));
	print '<div style="font-size:0.75em;color:#888;margin-top:10px;text-align:right">'.strlen($preview).' caractères ('.ceil(strlen($preview)/160).' SMS standard)</div>';
	print '</div>';

	print '<h3>Variables résolues</h3>';
	print '<table class="noborder centpercent" style="max-width:800px">';
	print '<tr class="liste_titre"><td style="width:200px">Variable</td><td>Valeur</td></tr>';
	foreach ($vars_resolved as $k => $v) {
		$display = is_scalar($v) ? (string) $v : json_encode($v);
		print '<tr class="oddeven"><td><code>{'.dol_escape_htmltag($k).'}</code></td><td>'.dol_escape_htmltag($display ?: '<em>(vide)</em>').'</td></tr>';
	}
	print '</table>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
