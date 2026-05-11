<?php
/* Copyright (C) 2026 SMSHUB */

$res = 0;
$path = dirname(__FILE__);
for ($i = 0; $i < 8; $i++) {
	$path = dirname($path);
	if (file_exists($path.'/main.inc.php')) { $res = @include $path.'/main.inc.php'; break; }
	if (file_exists($path.'/htdocs/main.inc.php')) { $res = @include $path.'/htdocs/main.inc.php'; break; }
}
if (!$res) die('Impossible de charger Dolibarr');

require_once DOL_DOCUMENT_ROOT.'/custom/smshub/lib/smshub.lib.php';

if (!$user->admin) accessforbidden();
$langs->loadLangs(array("admin", "smshub@smshub"));

llxHeader('', 'SMSHUB - À propos');
print load_fiche_titre('SMSHUB', '', 'phoning');
print dol_get_fiche_head(smshubAdminTabs(), 'about', '', -1);

print '<div class="info">';
print '<p><strong>SMSHUB</strong> v1.0.0 — Passerelle SMS via routeurs 4G locaux</p>';
print '<p>Module Dolibarr d\'intégration avec le serveur SMSHUB : envoi SMS via passerelles Huawei / Cudy / Capcom6, notifications automatiques factures et tickets, workflow de relances impayés multi-paliers, modèles SMS avec variables dynamiques.</p>';
print '<p>Service SMSHUB : <a href="https://smshub.siliteo.com" target="_blank">smshub.siliteo.com</a></p>';
print '<p>Repo GitHub : <a href="https://github.com/eighteen-ai/DOLIBARR-SMSHUB" target="_blank">github.com/eighteen-ai/DOLIBARR-SMSHUB</a></p>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
