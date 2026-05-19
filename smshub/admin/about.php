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
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/core/modules/modSMSHub.class.php';
$mod = new modSMSHub($db);
print '<p><strong>SMSHUB</strong> v'.dol_escape_htmltag($mod->version).' — Passerelle SMS via routeurs 4G locaux</p>';
print '<p>Module Dolibarr d\'intégration avec le serveur SMSHUB : envoi SMS via passerelles Huawei / Cudy / Capcom6, notifications automatiques factures et tickets, workflow de relances impayés multi-paliers, modèles SMS avec variables dynamiques.</p>';
print '<p>Service SMSHUB : <a href="https://smshub.siliteo.com" target="_blank">smshub.siliteo.com</a></p>';
print '<p>Repo GitHub : <a href="https://github.com/eighteen-ai/DOLIBARR-SMSHUB" target="_blank">github.com/eighteen-ai/DOLIBARR-SMSHUB</a></p>';
print '</div>';

$intercept = (int) getDolGlobalString('SMSHUB_INTERCEPT_DOLIBARR_SMS') === 1;
print '<br><h3>Intégration SMS Dolibarr</h3>';
print '<div class="info">';
print '<p>Le module écoute le hook <code>sendsms</code> émis par <code>CSMSFile::sendfile()</code>. Quand un autre code Dolibarr (module Notifications, confirmation de paiement, etc.) appelle <code>CSMSFile</code>, le SMS est routé via SMSHUB au lieu du fournisseur configuré dans Outils → SMS.</p>';
if ($intercept) {
	print '<p style="color:green"><strong>✅ Interception active</strong> — les SMS Dolibarr standards sont routés via SMSHUB.</p>';
} else {
	print '<p style="color:#888"><strong>○ Interception désactivée</strong> — activez l\'option « Intercepter tous les SMS Dolibarr » dans la configuration pour router les envois standard via SMSHUB.</p>';
}
print '<p><span class="opacitymedium">Le module n\'apparaît PAS dans la liste déroulante « Fournisseur de SMS » d\'<em>Outils → SMS → Configuration</em> : cette liste demande une classe <code>CSMSFile</code>-compatible que le module ne fournit pas (les hooks et la case à cocher mail couvrent tous les usages courants sans cette intégration).</span></p>';
print '<p><span class="opacitymedium">Indépendamment, les déclencheurs SMSHUB (facture/propal/ticket) et la case à cocher sur les formulaires d\'envoi de mail fonctionnent toujours.</span></p>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
