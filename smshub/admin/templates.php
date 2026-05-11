<?php
/* Copyright (C) 2026 SMSHUB - Templates CRUD */

$res = 0;
$path = dirname(__FILE__);
for ($i = 0; $i < 8; $i++) {
	$path = dirname($path);
	if (file_exists($path.'/main.inc.php')) { $res = @include $path.'/main.inc.php'; break; }
	if (file_exists($path.'/htdocs/main.inc.php')) { $res = @include $path.'/htdocs/main.inc.php'; break; }
}
if (!$res) die('Impossible de charger Dolibarr');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubtemplate.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/lib/smshub.lib.php';

if (!$user->hasRight('smshub', 'admin')) accessforbidden();

$langs->loadLangs(array("admin", "smshub@smshub"));

$action = GETPOST('action', 'aZ09');
$id = (int) GETPOST('id', 'int');

$tpl = new SmsHubTemplate($db);

if ($action === 'save' && GETPOST('token') === newToken()) {
	if ($id > 0) $tpl->fetch($id);
	$tpl->code = GETPOST('code', 'alphanohtml');
	$tpl->label = GETPOST('label', 'alphanohtml');
	$tpl->content = GETPOST('content', 'restricthtml');
	$tpl->context = GETPOST('context', 'alphanohtml');
	$tpl->active = GETPOST('active') ? 1 : 0;
	$ok = $id > 0 ? $tpl->update($user) : $tpl->create($user);
	if ($ok > 0) {
		setEventMessages('Modèle enregistré', null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']); exit;
	} else {
		setEventMessages('Erreur enregistrement', null, 'errors');
	}
}

if ($action === 'delete' && $id > 0) {
	$tpl->delete($id);
	setEventMessages('Modèle supprimé', null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']); exit;
}

if ($action === 'edit' && $id > 0) $tpl->fetch($id);

llxHeader('', $langs->trans("SmsHubTemplates"));
print load_fiche_titre($langs->trans("SmsHubTemplates"), '', 'phoning');
print dol_get_fiche_head(smshubAdminTabs(), 'templates', '', -1);

if ($action === 'new' || $action === 'edit') {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	if ($id > 0) print '<input type="hidden" name="id" value="'.$id.'">';

	print '<table class="noborder centpercent">';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("SmsHubTemplateCode").'</td>';
	print '<td><input type="text" name="code" value="'.dol_escape_htmltag($tpl->code).'" class="flat" required></td></tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SmsHubTemplateLabel").'</td>';
	print '<td><input type="text" name="label" value="'.dol_escape_htmltag($tpl->label).'" class="flat minwidth500" required></td></tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SmsHubTemplateContext").'</td><td>';
	print '<select name="context">';
	foreach (array('manual' => 'Manuel', 'bill' => 'Facture', 'ticket' => 'Ticket', 'relance' => 'Relance', 'order' => 'Commande', 'propal' => 'Devis') as $c => $l) {
		print '<option value="'.$c.'"'.($tpl->context === $c ? ' selected' : '').'>'.$l.'</option>';
	}
	print '</select></td></tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SmsHubTemplateActive").'</td>';
	print '<td><input type="checkbox" name="active" value="1"'.((!$id || $tpl->active) ? ' checked' : '').'></td></tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SmsHubTemplateContent").'</td>';
	print '<td><textarea name="content" rows="4" cols="80" class="flat" required>'.dol_escape_htmltag($tpl->content).'</textarea>';
	$current_ctx = $tpl->context ?: 'manual';
	$vars = SmsHubTemplate::availableVariables($current_ctx);
	print '<br><span class="opacitymedium">Variables disponibles : ';
	$parts = array();
	foreach ($vars as $k => $lbl) $parts[] = '<code>{'.$k.'}</code> ('.$lbl.')';
	print implode(', ', $parts).'</span>';
	print '</td></tr>';

	print '</table>';
	print '<div class="center" style="margin-top:15px">';
	print '<button type="submit" class="button">'.$langs->trans("Save").'</button> ';
	print '<a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans("Cancel").'</a>';
	print '</div></form>';
} else {
	print '<div style="margin-bottom:10px"><a class="button button-add" href="?action=new">'.$langs->trans("SmsHubTemplateNew").'</a></div>';
	$rows = SmsHubTemplate::listAll($db);
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>Code</td><td>Libellé</td><td>Contexte</td><td>Actif</td><td>Aperçu contenu</td><td></td></tr>';
	foreach ($rows as $r) {
		print '<tr class="oddeven">';
		print '<td><code>'.dol_escape_htmltag($r->code).'</code></td>';
		print '<td>'.dol_escape_htmltag($r->label).'</td>';
		print '<td>'.dol_escape_htmltag($r->context).'</td>';
		print '<td>'.($r->active ? '✅' : '❌').'</td>';
		print '<td>'.dol_escape_htmltag(dol_substr($r->content, 0, 100)).'</td>';
		print '<td>';
		print '<a href="?action=edit&id='.$r->rowid.'" title="Modifier">✏️</a> ';
		print '<a href="?action=delete&id='.$r->rowid.'" onclick="return confirm(\'Supprimer ?\')" title="Supprimer">🗑️</a>';
		print '</td></tr>';
	}
	print '</table>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
