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
print '<br><h3>Intégration "opérateur SMS Dolibarr"</h3>';
print '<div class="info">';
print '<p>Le module se déclare auprès de Dolibarr comme fournisseur SMS (<code>module_parts[\'sms\']</code>) et écoute le hook <code>sendsms</code> émis par <code>CSMSFile::sendfile()</code>.</p>';
if ($intercept) {
	print '<p style="color:green"><strong>✅ Interception active</strong> — tous les SMS envoyés par Dolibarr (module Notifications, confirmations de paiement, etc.) sont routés via SMSHUB.</p>';
} else {
	print '<p style="color:#888"><strong>○ Interception désactivée</strong> — activez l\'option « Intercepter tous les SMS Dolibarr » dans la configuration pour router les envois standard via SMSHUB.</p>';
}
print '<p><span class="opacitymedium">Indépendamment de cette option, les déclencheurs SMSHUB (facture/propal/ticket) et la case à cocher sur les formulaires d\'envoi de mail fonctionnent toujours.</span></p>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
