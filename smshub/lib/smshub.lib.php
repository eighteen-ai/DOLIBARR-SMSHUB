<?php
/* Copyright (C) 2026 SMSHUB - Shared helpers (admin tabs, etc.) */

if (!function_exists('smshubAdminTabs')) {
	function smshubAdminTabs()
	{
		global $langs;
		$head = array();
		$h = 0;
		$head[$h][0] = dol_buildpath('/smshub/admin/setup.php', 1);
		$head[$h][1] = $langs->trans("Configuration");
		$head[$h][2] = 'setup';
		$h++;
		$head[$h][0] = dol_buildpath('/smshub/admin/templates.php', 1);
		$head[$h][1] = $langs->trans("SmsHubTemplates");
		$head[$h][2] = 'templates';
		$h++;
		$head[$h][0] = dol_buildpath('/smshub/admin/log.php', 1);
		$head[$h][1] = $langs->trans("SmsHubLog");
		$head[$h][2] = 'log';
		$h++;
		$head[$h][0] = dol_buildpath('/smshub/admin/send.php', 1);
		$head[$h][1] = $langs->trans("SmsHubSend");
		$head[$h][2] = 'send';
		$h++;
		$head[$h][0] = dol_buildpath('/smshub/admin/update.php', 1);
		$head[$h][1] = 'Mise à jour';
		$head[$h][2] = 'update';
		$h++;
		$head[$h][0] = dol_buildpath('/smshub/admin/about.php', 1);
		$head[$h][1] = 'À propos';
		$head[$h][2] = 'about';
		return $head;
	}
}
