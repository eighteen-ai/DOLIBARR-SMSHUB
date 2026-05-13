<?php
/* Copyright (C) 2026 SMSHUB - Manual quick send */

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

if (!$user->admin && !$user->hasRight('smshub', 'send')) accessforbidden();

$langs->loadLangs(array("admin", "smshub@smshub"));

$action = GETPOST('action', 'aZ09');
$phone = GETPOST('phone', 'alphanohtml');
$message = GETPOST('message', 'restricthtml');
$scheduled_at = GETPOST('scheduled_at', 'alphanohtml');
$template_code = GETPOST('template_code', 'alphanohtml');
$socid = (int) GETPOST('socid', 'int');

if ($action === 'send' && GETPOST('token') === newToken()) {
	$sender = new SmsHubSender($db);
	if (!empty($template_code) && $socid > 0) {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$soc = new Societe($db); $soc->fetch($socid);
		$vars = array(
			'client_name' => $soc->name,
			'company_name' => $mysoc->name ?? '',
			'date' => dol_print_date(dol_now(), 'day'),
		);
		$ok = $sender->sendFromTemplate($template_code, $phone, $vars, 'manual', 0, $scheduled_at, $user);
	} else {
		$ok = $sender->sendDirect($phone, $message, 'manual', 0, $scheduled_at, $template_code ?: null, $user);
	}
	if ($ok) {
		setEventMessages(sprintf($langs->trans("SmsHubSendSuccess"), $sender->last_task_id ?: 'dryrun'), null, 'mesgs');
	} else {
		setEventMessages(sprintf($langs->trans("SmsHubSendError"), $sender->last_error), null, 'errors');
	}
}

$templates = SmsHubTemplate::listAll($db, null, true);

llxHeader('', $langs->trans("SmsHubSend"));
print load_fiche_titre($langs->trans("SmsHubSend"), '', 'phoning');
print dol_get_fiche_head(smshubAdminTabs(), 'send', '', -1);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="send">';

print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("SmsHubSendPhone").'</td>';
print '<td><input type="text" name="phone" value="'.dol_escape_htmltag($phone).'" class="flat minwidth300" required placeholder="+33600000000"></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SmsHubSendUseTemplate").'</td><td>';
print '<select name="template_code"><option value="">— Aucun (saisie libre) —</option>';
foreach ($templates as $t) {
	print '<option value="'.dol_escape_htmltag($t->code).'">'.dol_escape_htmltag($t->label.' ['.$t->context.']').'</option>';
}
print '</select> &nbsp; ';
print 'Tiers (pour variables) : <input type="number" name="socid" placeholder="ID tiers" class="flat">';
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SmsHubSendMessage").'</td>';
print '<td><textarea name="message" rows="4" cols="80" class="flat">'.dol_escape_htmltag($message).'</textarea>';
print '<br><span class="opacitymedium">Si un modèle est sélectionné, ce champ est ignoré.</span></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SmsHubSendScheduledAt").'</td>';
print '<td><input type="text" name="scheduled_at" value="" class="flat" placeholder="+15m, +2h, 2026-12-25T10:30:00…">';
print '<br><span class="opacitymedium">'.$langs->trans("SmsHubSendScheduledAtHelp").'</span></td></tr>';

print '</table>';
print '<div class="center" style="margin-top:15px">';
print '<button type="submit" class="button">'.$langs->trans("SmsHubSendButton").'</button>';
print '</div>';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
