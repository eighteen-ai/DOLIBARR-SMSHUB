<?php
/* Copyright (C) 2026 SMSHUB - SMS send journal */

class SmsHubLog
{
	public $db;
	public $id;
	public $datec;
	public $fk_user;
	public $phone;
	public $message;
	public $source;
	public $fk_source;
	public $template_code;
	public $status;
	public $task_id;
	public $scheduled_at;
	public $error_message;

	const STATUS_PENDING = 'pending';
	const STATUS_SENT = 'sent';
	const STATUS_FAILED = 'failed';
	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_DRYRUN = 'dryrun';

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function create($user)
	{
		$now = dol_now();
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."smshub_log";
		$sql .= " (entity, datec, fk_user, phone, message, source, fk_source, template_code, status, task_id, scheduled_at, error_message)";
		$sql .= " VALUES (".((int) $GLOBALS['conf']->entity).", '".$this->db->idate($now)."',";
		$sql .= " ".((int) ($this->fk_user ?: ($user ? $user->id : 0))).",";
		$sql .= " '".$this->db->escape($this->phone)."',";
		$sql .= " '".$this->db->escape(dol_substr($this->message, 0, 4000))."',";
		$sql .= " '".$this->db->escape($this->source ?: 'manual')."',";
		$sql .= " ".((int) $this->fk_source).",";
		$sql .= " ".($this->template_code ? "'".$this->db->escape($this->template_code)."'" : "NULL").",";
		$sql .= " '".$this->db->escape($this->status ?: self::STATUS_PENDING)."',";
		$sql .= " ".($this->task_id ? (int) $this->task_id : "NULL").",";
		$sql .= " ".($this->scheduled_at ? "'".$this->db->escape($this->scheduled_at)."'" : "NULL").",";
		$sql .= " ".($this->error_message ? "'".$this->db->escape(dol_substr($this->error_message, 0, 1000))."'" : "NULL").")";
		if (!$this->db->query($sql)) return -1;
		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."smshub_log");
		return $this->id;
	}

	public function updateStatus($status, $task_id = null, $error = null)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."smshub_log SET status='".$this->db->escape($status)."'";
		if ($task_id !== null) $sql .= ", task_id=".(int) $task_id;
		if ($error !== null) $sql .= ", error_message='".$this->db->escape(dol_substr($error, 0, 1000))."'";
		$sql .= " WHERE rowid=".(int) $this->id;
		return $this->db->query($sql) ? 1 : -1;
	}

	public static function listRecent($db, $limit = 100, $filters = array())
	{
		$rows = array();
		$sql = "SELECT rowid, datec, fk_user, phone, message, source, fk_source, template_code, status, task_id, scheduled_at, error_message";
		$sql .= " FROM ".MAIN_DB_PREFIX."smshub_log";
		$sql .= " WHERE entity IN (".getEntity('smshub_log').")";
		if (!empty($filters['status'])) $sql .= " AND status='".$db->escape($filters['status'])."'";
		if (!empty($filters['source'])) $sql .= " AND source='".$db->escape($filters['source'])."'";
		if (!empty($filters['phone'])) $sql .= " AND phone LIKE '%".$db->escape($filters['phone'])."%'";
		$sql .= " ORDER BY datec DESC LIMIT ".(int) $limit;
		$res = $db->query($sql);
		if (!$res) return $rows;
		while ($obj = $db->fetch_object($res)) $rows[] = $obj;
		return $rows;
	}

	public static function countByStatus($db)
	{
		$counts = array();
		$sql = "SELECT status, COUNT(*) as nb FROM ".MAIN_DB_PREFIX."smshub_log";
		$sql .= " WHERE entity IN (".getEntity('smshub_log').")";
		$sql .= " AND datec >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
		$sql .= " GROUP BY status";
		$res = $db->query($sql);
		if (!$res) return $counts;
		while ($obj = $db->fetch_object($res)) $counts[$obj->status] = (int) $obj->nb;
		return $counts;
	}
}
