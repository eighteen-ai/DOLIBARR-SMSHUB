<?php
/* Copyright (C) 2026 SMSHUB - Log viewer */

$res = 0;
$path = dirname(__FILE__);
for ($i = 0; $i < 8; $i++) {
	$path = dirname($path);
	if (file_exists($path.'/main.inc.php')) { $res = @include $path.'/main.inc.php'; break; }
	if (file_exists($path.'/htdocs/main.inc.php')) { $res = @include $path.'/htdocs/main.inc.php'; break; }
}
if (!$res) die('Impossible de charger Dolibarr');

require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshublog.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/lib/smshub.lib.php';

if (!$user->hasRight('smshub', 'read')) accessforbidden();

$langs->loadLangs(array("admin", "smshub@smshub"));

$filter_status = GETPOST('filter_status', 'alphanohtml');
$filter_source = GETPOST('filter_source', 'alphanohtml');
$filter_phone = GETPOST('filter_phone', 'alphanohtml');
$limit = (int) GETPOST('limit', 'int') ?: 200;

$rows = SmsHubLog::listRecent($db, $limit, array(
	'status' => $filter_status,
	'source' => $filter_source,
	'phone' => $filter_phone,
));

llxHeader('', $langs->trans("SmsHubLog"));
print load_fiche_titre($langs->trans("SmsHubLog"), '', 'phoning');
print dol_get_fiche_head(smshubAdminTabs(), 'log', '', -1);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" style="margin-bottom:15px">';
print 'Statut : <select name="filter_status"><option value="">Tous</option>';
foreach (array('sent','scheduled','pending','failed','dryrun') as $s) {
	print '<option value="'.$s.'"'.($filter_status === $s ? ' selected' : '').'>'.$s.'</option>';
}
print '</select> &nbsp; ';
print 'Source : <select name="filter_source"><option value="">Toutes</option>';
foreach (array('manual','bill','ticket','relance','cron') as $s) {
	print '<option value="'.$s.'"'.($filter_source === $s ? ' selected' : '').'>'.$s.'</option>';
}
print '</select> &nbsp; ';
print 'Téléphone : <input type="text" name="filter_phone" value="'.dol_escape_htmltag($filter_phone).'" class="flat">';
print ' &nbsp; <button type="submit" class="button">Filtrer</button>';
print '</form>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>Date</td><td>Téléphone</td><td>Source</td><td>Réf.</td><td>Modèle</td><td>Statut</td><td>Task</td><td>Programmé</td><td>Message</td><td>Erreur</td>';
print '</tr>';
foreach ($rows as $r) {
	$status_color = array(
		'sent' => 'green', 'scheduled' => 'blue', 'pending' => 'orange',
		'failed' => 'red', 'dryrun' => 'gray',
	)[$r->status] ?? 'inherit';
	print '<tr class="oddeven">';
	print '<td>'.dol_print_date($db->jdate($r->datec), 'dayhour').'</td>';
	print '<td>'.dol_escape_htmltag($r->phone).'</td>';
	print '<td>'.dol_escape_htmltag($r->source).'</td>';
	print '<td>'.($r->fk_source ? '#'.(int) $r->fk_source : '').'</td>';
	print '<td><code>'.dol_escape_htmltag($r->template_code ?: '').'</code></td>';
	print '<td style="color:'.$status_color.'"><strong>'.dol_escape_htmltag($r->status).'</strong></td>';
	print '<td>'.($r->task_id ? '#'.(int) $r->task_id : '').'</td>';
	print '<td>'.($r->scheduled_at ? dol_escape_htmltag($r->scheduled_at) : '').'</td>';
	print '<td title="'.dol_escape_htmltag($r->message).'">'.dol_escape_htmltag(dol_substr($r->message, 0, 80)).'</td>';
	print '<td style="color:red">'.dol_escape_htmltag(dol_substr($r->error_message ?? '', 0, 60)).'</td>';
	print '</tr>';
}
print '</table>';

if (empty($rows)) print '<p>Aucun enregistrement.</p>';

print dol_get_fiche_end();
llxFooter();
$db->close();
