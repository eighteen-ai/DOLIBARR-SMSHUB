<?php
/* Copyright (C) 2026 SMSHUB - Setup page */

$res = 0;
$path = dirname(__FILE__);
for ($i = 0; $i < 8; $i++) {
	$path = dirname($path);
	if (file_exists($path.'/main.inc.php')) { $res = @include $path.'/main.inc.php'; break; }
	if (file_exists($path.'/htdocs/main.inc.php')) { $res = @include $path.'/htdocs/main.inc.php'; break; }
}
if (!$res) die('Impossible de charger Dolibarr');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubapi.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/lib/smshub.lib.php';

if (!$user->admin) accessforbidden();

$langs->loadLangs(array("admin", "smshub@smshub"));

$action = GETPOST('action', 'aZ09');
$test_result = null;

$constants = array(
	'SMSHUB_SERVER_URL' => 'alphanohtml',
	'SMSHUB_API_KEY' => 'alphanohtml',
	'SMSHUB_DEFAULT_COUNTRY_CODE' => 'alphanohtml',
	'SMSHUB_SENDER_NAME' => 'alphanohtml',
	'SMSHUB_TEST_PHONE' => 'alphanohtml',
);
$bool_constants = array(
	'SMSHUB_ENABLE_BILL_VALIDATE',
	'SMSHUB_ENABLE_BILL_PAYED',
	'SMSHUB_ENABLE_TICKET_CREATE',
	'SMSHUB_ENABLE_TICKET_MODIFY',
	'SMSHUB_ENABLE_TICKET_CLOSE',
	'SMSHUB_ENABLE_TICKET_ASSIGN',
	'SMSHUB_ENABLE_PROPAL_VALIDATE',
	'SMSHUB_ENABLE_PROPAL_SENT',
	'SMSHUB_ENABLE_PROPAL_SIGNED',
	'SMSHUB_ENABLE_PROPAL_REFUSED',
	'SMSHUB_BRIDGE_PUBLIC',
	'SMSHUB_DRYRUN',
);

if ($action === 'save' && GETPOST('token') === newToken()) {
	foreach ($constants as $name => $type) {
		$val = GETPOST($name, $type);
		dolibarr_set_const($db, $name, $val, 'chaine', 0, '', $conf->entity);
	}
	foreach ($bool_constants as $name) {
		$val = GETPOST($name) ? '1' : '0';
		dolibarr_set_const($db, $name, $val, 'chaine', 0, '', $conf->entity);
	}
	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	header("Location: ".$_SERVER['PHP_SELF']); exit;
}

if ($action === 'test_connection') {
	$api = new SmsHubApi();
	$res = $api->version();
	if ($res && !empty($res['version'])) {
		$test_result = array('ok' => true, 'msg' => sprintf($langs->trans("SmsHubConnectionOk"), $res['version']));
	} else {
		$test_result = array('ok' => false, 'msg' => sprintf($langs->trans("SmsHubConnectionKo"), $api->last_error ?: 'inconnue'));
	}
}

$page_name = $langs->trans("SmsHubSetup");
llxHeader('', $page_name);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($page_name, $linkback, 'phoning');

print dol_get_fiche_head(smshubAdminTabs(), 'setup', '', -1);

if ($test_result) {
	setEventMessages($test_result['msg'], null, $test_result['ok'] ? 'mesgs' : 'errors');
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SmsHubServerUrl").'</td><td>';
print '<input type="text" name="SMSHUB_SERVER_URL" class="flat minwidth500" value="'.dol_escape_htmltag(getDolGlobalString('SMSHUB_SERVER_URL')).'">';
print '<br><span class="opacitymedium">'.$langs->trans("SmsHubServerUrlHelp").'</span>';
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SmsHubApiKey").'</td><td>';
print '<input type="password" name="SMSHUB_API_KEY" class="flat minwidth500" value="'.dol_escape_htmltag(getDolGlobalString('SMSHUB_API_KEY')).'">';
print '<br><span class="opacitymedium">'.$langs->trans("SmsHubApiKeyHelp").'</span>';
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SmsHubDefaultCountryCode").'</td><td>';
print '<input type="text" name="SMSHUB_DEFAULT_COUNTRY_CODE" class="flat" size="6" value="'.dol_escape_htmltag(getDolGlobalString('SMSHUB_DEFAULT_COUNTRY_CODE', '+33')).'">';
print '<br><span class="opacitymedium">'.$langs->trans("SmsHubDefaultCountryCodeHelp").'</span>';
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SmsHubSenderName").'</td><td>';
print '<input type="text" name="SMSHUB_SENDER_NAME" class="flat minwidth300" value="'.dol_escape_htmltag(getDolGlobalString('SMSHUB_SENDER_NAME')).'">';
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SmsHubDryRun").'</td><td>';
print '<input type="checkbox" name="SMSHUB_DRYRUN" value="1"'.(getDolGlobalString('SMSHUB_DRYRUN') ? ' checked' : '').'>';
print '<br><span class="opacitymedium">'.$langs->trans("SmsHubDryRunHelp").'</span>';
print '</td></tr>';

print '<tr class="oddeven"><td>Numéro de test (bypass dry-run)</td><td>';
print '<input type="text" name="SMSHUB_TEST_PHONE" class="flat" value="'.dol_escape_htmltag(getDolGlobalString('SMSHUB_TEST_PHONE', '')).'" placeholder="+33600000000">';
print '<br><span class="opacitymedium">Si renseigné, tout envoi vers ce numéro part en réel même si dry-run est actif. Permet de tester la planification (scheduled_at) sans désactiver le dry-run global.</span>';
print '</td></tr>';

print '</table>';

print '<br><h3>'.$langs->trans("SmsHubTriggers").'</h3>';
print '<table class="noborder centpercent">';
$triggers_labels = array(
	'SMSHUB_ENABLE_BILL_VALIDATE' => $langs->trans("SmsHubEnableBillValidate"),
	'SMSHUB_ENABLE_BILL_PAYED' => $langs->trans("SmsHubEnableBillPayed"),
	'SMSHUB_ENABLE_TICKET_CREATE' => $langs->trans("SmsHubEnableTicketCreate"),
	'SMSHUB_ENABLE_TICKET_MODIFY' => $langs->trans("SmsHubEnableTicketModify"),
	'SMSHUB_ENABLE_TICKET_CLOSE' => $langs->trans("SmsHubEnableTicketClose"),
	'SMSHUB_ENABLE_TICKET_ASSIGN' => 'Notifier le technicien assigné',
	'SMSHUB_ENABLE_PROPAL_VALIDATE' => 'SMS à la validation d\'un devis',
	'SMSHUB_ENABLE_PROPAL_SENT' => 'SMS quand un devis est envoyé par mail',
	'SMSHUB_ENABLE_PROPAL_SIGNED' => 'SMS à la signature d\'un devis',
	'SMSHUB_ENABLE_PROPAL_REFUSED' => 'SMS au refus d\'un devis',
	'SMSHUB_BRIDGE_PUBLIC' => 'Exposer le bridge SMS aux autres modules (RelanceAuto, etc.)',
);
foreach ($triggers_labels as $cst => $label) {
	print '<tr class="oddeven"><td>'.$label.'</td><td>';
	print '<input type="checkbox" name="'.$cst.'" value="1"'.(getDolGlobalString($cst) ? ' checked' : '').'>';
	print '</td></tr>';
}
print '</table>';

print '<div class="center" style="margin-top:20px">';
print '<button type="submit" class="button">'.$langs->trans("Save").'</button>';
print ' &nbsp; ';
print '<a class="button button-add" href="?action=test_connection&token='.newToken().'">'.$langs->trans("SmsHubTestConnection").'</a>';
print '</div>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
