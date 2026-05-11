<?php
/* Copyright (C) 2026 SMSHUB - Reminder workflow for unpaid invoices */

require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubsender.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

class SmsHubRelance
{
	public $db;
	public $error = '';
	public $output = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	public static function listSteps($db, $only_active = true)
	{
		$rows = array();
		$sql = "SELECT rowid, rank_order, label, days_offset, template_code, min_amount, active";
		$sql .= " FROM ".MAIN_DB_PREFIX."smshub_relance_step";
		$sql .= " WHERE entity IN (".getEntity('smshub_relance_step').")";
		if ($only_active) $sql .= " AND active = 1";
		$sql .= " ORDER BY rank_order ASC";
		$res = $db->query($sql);
		if (!$res) return $rows;
		while ($obj = $db->fetch_object($res)) $rows[] = $obj;
		return $rows;
	}

	public static function saveStep($db, $data)
	{
		$rowid = (int) ($data['rowid'] ?? 0);
		$rank = (int) ($data['rank_order'] ?? 0);
		$label = $db->escape($data['label'] ?? '');
		$days = (int) ($data['days_offset'] ?? 0);
		$tpl = $db->escape($data['template_code'] ?? '');
		$min = (float) ($data['min_amount'] ?? 0);
		$active = (int) ($data['active'] ?? 1);

		if ($rowid > 0) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."smshub_relance_step SET";
			$sql .= " rank_order=$rank, label='$label', days_offset=$days, template_code='$tpl', min_amount=$min, active=$active";
			$sql .= " WHERE rowid=$rowid";
		} else {
			$entity = (int) $GLOBALS['conf']->entity;
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."smshub_relance_step (entity, rank_order, label, days_offset, template_code, min_amount, active)";
			$sql .= " VALUES ($entity, $rank, '$label', $days, '$tpl', $min, $active)";
		}
		return $db->query($sql) ? 1 : -1;
	}

	public static function deleteStep($db, $id)
	{
		return $db->query("DELETE FROM ".MAIN_DB_PREFIX."smshub_relance_step WHERE rowid=".(int) $id) ? 1 : -1;
	}

	public function getStatus($fk_facture)
	{
		$sql = "SELECT rowid, last_step_rank, last_sent_at, stopped, stop_reason";
		$sql .= " FROM ".MAIN_DB_PREFIX."smshub_relance_status";
		$sql .= " WHERE fk_facture=".(int) $fk_facture;
		$sql .= " AND entity IN (".getEntity('smshub_relance_status').")";
		$res = $this->db->query($sql);
		if ($res && $obj = $this->db->fetch_object($res)) return $obj;
		return null;
	}

	public function setStatus($fk_facture, $last_rank, $stopped = 0, $reason = '')
	{
		$entity = (int) $GLOBALS['conf']->entity;
		$now = $this->db->idate(dol_now());
		$existing = $this->getStatus($fk_facture);
		if ($existing) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."smshub_relance_status SET";
			$sql .= " last_step_rank=".(int) $last_rank.",";
			$sql .= " last_sent_at='$now',";
			$sql .= " stopped=".(int) $stopped.",";
			$sql .= " stop_reason='".$this->db->escape($reason)."'";
			$sql .= " WHERE rowid=".(int) $existing->rowid;
		} else {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."smshub_relance_status (entity, fk_facture, last_step_rank, last_sent_at, stopped, stop_reason)";
			$sql .= " VALUES ($entity, ".(int) $fk_facture.", ".(int) $last_rank.", '$now', ".(int) $stopped.", '".$this->db->escape($reason)."')";
		}
		return $this->db->query($sql) ? 1 : -1;
	}

	/**
	 * Cron entry point: scan unpaid invoices and send relevant reminders.
	 */
	public function runDailyReminders()
	{
		$this->output = '';
		$count_sent = 0;
		$count_skip = 0;

		if (!getDolGlobalString('SMSHUB_ENABLE_RELANCES')) {
			$this->output = 'Relances désactivées (constante SMSHUB_ENABLE_RELANCES).';
			return 0;
		}

		$steps = self::listSteps($this->db, true);
		if (empty($steps)) {
			$this->output = 'Aucun palier de relance configuré.';
			return 0;
		}

		// Fetch unpaid invoices past due
		$sql = "SELECT f.rowid, f.ref, f.fk_soc, f.date_lim_reglement, f.total_ttc";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture f";
		$sql .= " WHERE f.entity IN (".getEntity('facture').")";
		$sql .= " AND f.fk_statut = 1"; // validated
		$sql .= " AND f.paye = 0";
		$sql .= " AND f.type IN (0, 1, 2)"; // standard, replacement, credit
		$sql .= " AND f.date_lim_reglement IS NOT NULL";
		$sql .= " AND f.date_lim_reglement <= '".$this->db->idate(dol_now())."'";

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$sender = new SmsHubSender($this->db);

		while ($row = $this->db->fetch_object($res)) {
			$status = $this->getStatus($row->rowid);
			if ($status && (int) $status->stopped === 1) { $count_skip++; continue; }

			$days_late = (int) floor((dol_now() - $this->db->jdate($row->date_lim_reglement)) / 86400);
			$last_rank = $status ? (int) $status->last_step_rank : 0;

			// Find the highest applicable step we have not yet reached
			$step_to_send = null;
			foreach ($steps as $st) {
				if ((int) $st->rank_order <= $last_rank) continue;
				if ($days_late < (int) $st->days_offset) continue;
				if ((float) $st->min_amount > 0 && (float) $row->total_ttc < (float) $st->min_amount) continue;
				$step_to_send = $st;
				break;
			}
			if (!$step_to_send) { $count_skip++; continue; }

			// Load full invoice + thirdparty
			$facture = new Facture($this->db);
			if ($facture->fetch($row->rowid) <= 0) { $count_skip++; continue; }
			$facture->fetch_thirdparty();
			$phone = SmsHubSender::thirdpartyPhone($facture->thirdparty);
			if (empty($phone)) { $count_skip++; continue; }

			$vars = SmsHubSender::buildBillVars($facture);
			$ok = $sender->sendFromTemplate(
				$step_to_send->template_code,
				$phone,
				$vars,
				'relance',
				$facture->id
			);
			if ($ok) {
				$this->setStatus($facture->id, (int) $step_to_send->rank_order);
				$this->logActioncomm($facture, $step_to_send, $vars);
				$count_sent++;
			} else {
				$this->output .= "[KO] Facture ".$facture->ref." : ".$sender->last_error."\n";
				$count_skip++;
			}
		}

		$this->output = "Relances envoyées : $count_sent — Ignorées : $count_skip\n".$this->output;
		return $count_sent;
	}

	protected function logActioncomm($facture, $step, $vars)
	{
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$ac = new ActionComm($this->db);
		$ac->type_code = 'AC_OTH_AUTO';
		$ac->label = 'Relance SMS palier '.$step->rank_order.' — '.$step->label;
		$ac->note = 'Envoi automatique par SMSHUB (palier '.$step->rank_order.', '.$step->days_offset.'j après échéance)';
		$ac->datep = dol_now();
		$ac->fk_element = $facture->id;
		$ac->elementtype = 'invoice';
		$ac->socid = $facture->socid;
		$ac->userownerid = $GLOBALS['user']->id ?? 0;
		$ac->percentage = -1;
		@$ac->create($GLOBALS['user']);
	}
}
