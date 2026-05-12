<?php
/* Copyright (C) 2026 SMSHUB - Public bridge for other Dolibarr modules
 *
 * Lets other modules (e.g. RelanceAuto, custom workflows) route their SMS
 * through SMSHUB without depending on the heavy orchestrator API.
 *
 * Usage from another module:
 *
 *   if (class_exists('SmsHubBridge') && SmsHubBridge::isAvailable()) {
 *       $task_id = SmsHubBridge::send($phone, $message, 'relance', $fk_facture);
 *       if ($task_id === false) { fallback to CSMSFile... }
 *   }
 */

class SmsHubBridge
{
	/**
	 * True if SMSHUB module is enabled and exposed to other modules.
	 */
	public static function isAvailable()
	{
		global $conf;
		if (empty($conf->smshub) || empty($conf->smshub->enabled)) return false;
		if (!getDolGlobalString('SMSHUB_BRIDGE_PUBLIC', '1')) return false;
		if (!getDolGlobalString('SMSHUB_API_KEY')) return false;
		return true;
	}

	/**
	 * Send a SMS through SMSHUB. Returns task_id on success, false on failure.
	 *
	 * @param string $phone Destination (international or local — normalized internally)
	 * @param string $message Body
	 * @param string $source Tag for traceability (e.g. 'relance', 'custom')
	 * @param int $fk_source Foreign key on the source object (e.g. invoice id)
	 * @param string|null $scheduled_at Optional schedule (ISO 8601 or "+15m" etc.)
	 * @return int|false task_id (integer) on success, false on failure
	 */
	public static function send($phone, $message, $source = 'external', $fk_source = 0, $scheduled_at = null)
	{
		if (!self::isAvailable()) return false;
		global $db, $user;
		require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubsender.class.php';
		$sender = new SmsHubSender($db);
		$ok = $sender->sendDirect($phone, $message, $source, $fk_source, $scheduled_at, null, $user ?? null);
		return $ok ? ($sender->last_task_id ?: 0) : false;
	}

	/**
	 * Last error message after a failed send.
	 */
	public static $lastError = '';
}
