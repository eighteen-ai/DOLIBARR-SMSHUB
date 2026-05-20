<?php
/* Copyright (C) 2026 SMSHUB - AJAX endpoint:
 * returns { ok, phone, template, preview } for the mail-form SMS checkbox. */

$res = 0;
$path = dirname(__FILE__);
for ($i = 0; $i < 8; $i++) {
	$path = dirname($path);
	if (file_exists($path.'/main.inc.php')) { $res = @include $path.'/main.inc.php'; break; }
	if (file_exists($path.'/htdocs/main.inc.php')) { $res = @include $path.'/htdocs/main.inc.php'; break; }
}
if (!$res) {
	header('Content-Type: application/json');
	echo json_encode(array('ok' => false, 'error' => 'Impossible de charger Dolibarr'));
	exit;
}

header('Content-Type: application/json; charset=utf-8');

if (empty($user->id)) {
	echo json_encode(array('ok' => false, 'error' => 'non authentifié'));
	exit;
}
if (!$user->admin && !$user->hasRight('smshub', 'send')) {
	echo json_encode(array('ok' => false, 'error' => 'droit smshub.send manquant'));
	exit;
}
if (empty($conf->smshub) || empty($conf->smshub->enabled)) {
	echo json_encode(array('ok' => false, 'error' => 'module désactivé'));
	exit;
}

require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubsender.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubtemplate.class.php';

// Lazy-seed default templates so existing installs that never had any (e.g. fresh
// reactivation without SQL data file) still get a working preview here.
SmsHubTemplate::seedDefaults($db, $user);

$type = GETPOST('type', 'aZ09');
$id = (int) GETPOST('id', 'int');
if (empty($id) || !in_array($type, array('bill', 'propal', 'ticket'), true)) {
	echo json_encode(array('ok' => false, 'error' => 'paramètres invalides'));
	exit;
}

$phone = '';
$template_code = '';
$vars = array();

switch ($type) {
	case 'bill':
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$o = new Facture($db);
		if ($o->fetch($id) <= 0) { echo json_encode(array('ok' => false, 'error' => 'facture introuvable')); exit; }
		$o->fetch_thirdparty();
		$phone = SmsHubSender::thirdpartyPhone($o->thirdparty);
		$template_code = 'bill_validated';
		$vars = SmsHubSender::buildBillVars($o);
		break;
	case 'propal':
		require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
		$o = new Propal($db);
		if ($o->fetch($id) <= 0) { echo json_encode(array('ok' => false, 'error' => 'devis introuvable')); exit; }
		$o->fetch_thirdparty();
		$phone = SmsHubSender::thirdpartyPhone($o->thirdparty);
		$template_code = 'propal_sent';
		$vars = SmsHubSender::buildPropalVars($o);
		break;
	case 'ticket':
		require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';
		$o = new Ticket($db);
		if ($o->fetch($id) <= 0) { echo json_encode(array('ok' => false, 'error' => 'ticket introuvable')); exit; }
		// fetch_thirdparty (CommonObject) handles both fk_soc and socid property
		// names depending on Dolibarr version — more reliable than a manual check.
		if (method_exists($o, 'fetch_thirdparty')) $o->fetch_thirdparty();
		$phone = SmsHubSender::thirdpartyPhone($o->thirdparty);
		// Fallback: if the thirdparty has no phone, try the SUPPORTCLI contact
		// attached to the ticket (often more relevant than the company's main line).
		if (empty($phone) && method_exists($o, 'getIdContact')) {
			$cids = $o->getIdContact('external', 'SUPPORTCLI');
			if (!empty($cids)) {
				require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
				$c = new Contact($db);
				if ($c->fetch((int) $cids[0]) > 0) {
					foreach (array('phone_mobile', 'phone_pro', 'phone_perso') as $f) {
						if (!empty($c->$f)) { $phone = $c->$f; break; }
					}
				}
			}
		}
		$template_code = 'ticket_modified';
		$vars = SmsHubSender::buildTicketVars($o);
		break;
}

$preview = '';
if (!empty($template_code)) {
	$tpl = new SmsHubTemplate($db);
	if ($tpl->fetchByCode($template_code) > 0 && $tpl->active) {
		$preview = SmsHubTemplate::render($tpl->content, $vars);
	}
}

echo json_encode(array(
	'ok' => true,
	'phone' => $phone,
	'template' => $template_code,
	'preview' => $preview,
), JSON_UNESCAPED_UNICODE);
