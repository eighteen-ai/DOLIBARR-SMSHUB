<?php
/* Copyright (C) 2026 SMSHUB - Reminder steps CRUD */

$res = 0;
$path = dirname(__FILE__);
for ($i = 0; $i < 8; $i++) {
	$path = dirname($path);
	if (file_exists($path.'/main.inc.php')) { $res = @include $path.'/main.inc.php'; break; }
	if (file_exists($path.'/htdocs/main.inc.php')) { $res = @include $path.'/htdocs/main.inc.php'; break; }
}
if (!$res) die('Impossible de charger Dolibarr');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubrelance.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubtemplate.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/lib/smshub.lib.php';

if (!$user->hasRight('smshub', 'admin')) accessforbidden();

$langs->loadLangs(array("admin", "smshub@smshub"));

$action = GETPOST('action', 'aZ09');
$id = (int) GETPOST('id', 'int');

if ($action === 'save' && GETPOST('token') === newToken()) {
	$ok = SmsHubRelance::saveStep($db, array(
		'rowid' => $id,
		'rank_order' => (int) GETPOST('rank_order', 'int'),
		'label' => GETPOST('label', 'alphanohtml'),
		'days_offset' => (int) GETPOST('days_offset', 'int'),
		'template_code' => GETPOST('template_code', 'alphanohtml'),
		'min_amount' => (float) GETPOST('min_amount', 'float'),
		'active' => GETPOST('active') ? 1 : 0,
	));
	if ($ok > 0) setEventMessages('Palier enregistré', null, 'mesgs');
	else setEventMessages('Erreur enregistrement', null, 'errors');
	header('Location: '.$_SERVER['PHP_SELF']); exit;
}

if ($action === 'delete' && $id > 0) {
	SmsHubRelance::deleteStep($db, $id);
	setEventMessages('Palier supprimé', null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']); exit;
}

if ($action === 'run_now') {
	$rel = new SmsHubRelance($db);
	$rel->runDailyReminders();
	setEventMessages('Cron exécuté : '.$rel->output, null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']); exit;
}

llxHeader('', $langs->trans("SmsHubRelances"));
print load_fiche_titre($langs->trans("SmsHubRelances"), '', 'phoning');
print dol_get_fiche_head(smshubAdminTabs(), 'relances', '', -1);

$templates = SmsHubTemplate::listAll($db, 'relance', true);
$steps = SmsHubRelance::listSteps($db, false);

if ($action === 'new' || $action === 'edit') {
	$step = null;
	if ($id > 0) {
		foreach ($steps as $s) if ((int) $s->rowid === $id) { $step = $s; break; }
	}
	print '<form method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	if ($step) print '<input type="hidden" name="id" value="'.(int) $step->rowid.'">';
	print '<table class="noborder centpercent">';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("SmsHubRelancePalier").' (rang)</td>';
	print '<td><input type="number" name="rank_order" value="'.($step->rank_order ?? '1').'" min="1" required></td></tr>';
	print '<tr class="oddeven"><td>Libellé</td>';
	print '<td><input type="text" name="label" class="flat minwidth300" value="'.dol_escape_htmltag($step->label ?? '').'" required></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("SmsHubRelanceDays").'</td>';
	print '<td><input type="number" name="days_offset" value="'.($step->days_offset ?? '7').'" min="0" required></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("SmsHubRelanceTemplate").'</td><td>';
	print '<select name="template_code" required><option value="">-</option>';
	foreach ($templates as $t) {
		$sel = (isset($step->template_code) && $step->template_code === $t->code) ? ' selected' : '';
		print '<option value="'.dol_escape_htmltag($t->code).'"'.$sel.'>'.dol_escape_htmltag($t->label.' ('.$t->code.')').'</option>';
	}
	print '</select></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("SmsHubRelanceMinAmount").' (€)</td>';
	print '<td><input type="number" step="0.01" name="min_amount" value="'.($step->min_amount ?? '0').'"></td></tr>';
	print '<tr class="oddeven"><td>Actif</td>';
	print '<td><input type="checkbox" name="active" value="1"'.((!$step || $step->active) ? ' checked' : '').'></td></tr>';
	print '</table>';
	print '<div class="center" style="margin-top:15px">';
	print '<button type="submit" class="button">'.$langs->trans("Save").'</button> ';
	print '<a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans("Cancel").'</a>';
	print '</div></form>';
} else {
	print '<div style="margin-bottom:10px">';
	print '<a class="button button-add" href="?action=new">'.$langs->trans("SmsHubRelanceAddStep").'</a> ';
	print '<a class="button" href="?action=run_now&token='.newToken().'" onclick="return confirm(\'Exécuter le cron de relances maintenant ?\')">▶️ Exécuter le cron maintenant</a>';
	print '</div>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>Rang</td><td>Libellé</td><td>J+N</td><td>Modèle</td><td>Montant min</td><td>Actif</td><td></td></tr>';
	foreach ($steps as $s) {
		print '<tr class="oddeven">';
		print '<td>'.(int) $s->rank_order.'</td>';
		print '<td>'.dol_escape_htmltag($s->label).'</td>';
		print '<td>J+'.(int) $s->days_offset.'</td>';
		print '<td><code>'.dol_escape_htmltag($s->template_code).'</code></td>';
		print '<td>'.price($s->min_amount).'</td>';
		print '<td>'.($s->active ? '✅' : '❌').'</td>';
		print '<td><a href="?action=edit&id='.(int) $s->rowid.'">✏️</a> ';
		print '<a href="?action=delete&id='.(int) $s->rowid.'" onclick="return confirm(\'Supprimer ?\')">🗑️</a></td>';
		print '</tr>';
	}
	print '</table>';
	print '<p class="opacitymedium" style="margin-top:15px">Le cron quotidien (déclaré au module) parcourt toutes les factures impayées validées dont la date d\'échéance est dépassée, et envoie le palier le plus avancé non encore atteint pour chaque facture.</p>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
