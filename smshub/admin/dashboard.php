<?php
/* Copyright (C) 2026 SMSHUB - Dashboard */

$res = 0;
$path = dirname(__FILE__);
for ($i = 0; $i < 8; $i++) {
	$path = dirname($path);
	if (file_exists($path.'/main.inc.php')) { $res = @include $path.'/main.inc.php'; break; }
	if (file_exists($path.'/htdocs/main.inc.php')) { $res = @include $path.'/htdocs/main.inc.php'; break; }
}
if (!$res) die('Impossible de charger Dolibarr');

require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshublog.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubapi.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/lib/smshub.lib.php';

if (!$user->admin && !$user->hasRight('smshub', 'read')) accessforbidden();

$langs->loadLangs(array("admin", "smshub@smshub"));

llxHeader('', 'SMSHUB - '.$langs->trans('SmsHubDashboard'));

print load_fiche_titre('SMSHUB — '.$langs->trans('SmsHubDashboard'), '', 'phoning');

// Server status card
$api = new SmsHubApi();
$ver = $api->version();
$serverOnline = $ver && !empty($ver['version']);

print '<div class="info" style="margin-bottom:20px">';
print '<strong>Serveur SMSHUB :</strong> ';
if ($serverOnline) {
	print '<span style="color:green">✅ En ligne</span> — version '.dol_escape_htmltag($ver['version']);
} else {
	print '<span style="color:red">❌ Hors ligne</span> — '.dol_escape_htmltag($api->last_error ?: 'inconnu');
}
print ' &nbsp; URL : <code>'.dol_escape_htmltag(getDolGlobalString('SMSHUB_SERVER_URL')).'</code>';
print '</div>';

// Stats last 30 days
$counts = SmsHubLog::countByStatus($db);
$labels = array(
	'sent' => 'Envoyés',
	'scheduled' => 'Programmés',
	'pending' => 'En file',
	'failed' => 'Échecs',
	'dryrun' => 'Test (dryrun)',
);

print '<h3>Statistiques (30 derniers jours)</h3>';
print '<table class="noborder centpercent"><tr class="liste_titre">';
foreach ($labels as $code => $lbl) print '<td>'.$lbl.'</td>';
print '</tr><tr class="oddeven">';
foreach ($labels as $code => $lbl) {
	$n = (int) ($counts[$code] ?? 0);
	$color = $code === 'sent' ? 'green' : ($code === 'failed' ? 'red' : 'inherit');
	print '<td style="font-size:1.5em;color:'.$color.'"><strong>'.$n.'</strong></td>';
}
print '</tr></table>';

// Quick navigation
print '<br><h3>Navigation rapide</h3>';
print '<div style="display:flex;gap:10px;flex-wrap:wrap">';
$cards = array(
	array('Envoi rapide', '/custom/smshub/admin/send.php', 'phoning'),
	array('Modèles SMS', '/custom/smshub/admin/templates.php', 'edit'),
	array('Relances impayés', '/custom/smshub/admin/relances.php', 'bill'),
	array('Journal', '/custom/smshub/admin/log.php', 'log'),
	array('Configuration', '/custom/smshub/admin/setup.php', 'setup'),
);
foreach ($cards as $c) {
	print '<a class="button" href="'.DOL_URL_ROOT.$c[1].'">'.$c[0].'</a>';
}
print '</div>';

// Recent log
print '<br><h3>Derniers envois</h3>';
$rows = SmsHubLog::listRecent($db, 10);
if (empty($rows)) {
	print '<p>Aucun envoi pour l\'instant.</p>';
} else {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>Date</td><td>Destinataire</td><td>Source</td><td>Statut</td><td>Message</td></tr>';
	foreach ($rows as $r) {
		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($db->jdate($r->datec), 'dayhour').'</td>';
		print '<td>'.dol_escape_htmltag($r->phone).'</td>';
		print '<td>'.dol_escape_htmltag($r->source).'</td>';
		print '<td>'.dol_escape_htmltag($r->status).'</td>';
		print '<td>'.dol_escape_htmltag(dol_substr($r->message, 0, 80)).'</td>';
		print '</tr>';
	}
	print '</table>';
}

llxFooter();
$db->close();
