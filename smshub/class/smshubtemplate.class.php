<?php
/* Copyright (C) 2026 SMSHUB - SMS template handling with variable substitution */

class SmsHubTemplate
{
	public $db;
	public $id;
	public $code;
	public $label;
	public $content;
	public $context;
	public $active;
	public $datec;
	public $fk_user_creat;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function fetchByCode($code)
	{
		$sql = "SELECT rowid, code, label, content, context, active, datec, fk_user_creat";
		$sql .= " FROM ".MAIN_DB_PREFIX."smshub_template";
		$sql .= " WHERE code = '".$this->db->escape($code)."'";
		$sql .= " AND entity IN (".getEntity('smshub_template').")";
		$res = $this->db->query($sql);
		if (!$res) return -1;
		if ($obj = $this->db->fetch_object($res)) {
			$this->id = (int) $obj->rowid;
			$this->code = $obj->code;
			$this->label = $obj->label;
			$this->content = $obj->content;
			$this->context = $obj->context;
			$this->active = (int) $obj->active;
			$this->datec = $obj->datec;
			$this->fk_user_creat = (int) $obj->fk_user_creat;
			return 1;
		}
		return 0;
	}

	public function fetch($id)
	{
		$sql = "SELECT rowid, code, label, content, context, active, datec, fk_user_creat";
		$sql .= " FROM ".MAIN_DB_PREFIX."smshub_template";
		$sql .= " WHERE rowid = ".(int) $id;
		$res = $this->db->query($sql);
		if (!$res) return -1;
		if ($obj = $this->db->fetch_object($res)) {
			$this->id = (int) $obj->rowid;
			$this->code = $obj->code;
			$this->label = $obj->label;
			$this->content = $obj->content;
			$this->context = $obj->context;
			$this->active = (int) $obj->active;
			return 1;
		}
		return 0;
	}

	public function create($user)
	{
		$now = dol_now();
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."smshub_template (entity, code, label, content, context, active, datec, fk_user_creat)";
		$sql .= " VALUES (".((int) $GLOBALS['conf']->entity).", '".$this->db->escape($this->code)."', '".$this->db->escape($this->label)."',";
		$sql .= " '".$this->db->escape($this->content)."', '".$this->db->escape($this->context ?: 'manual')."',";
		$sql .= " ".((int) ($this->active ?: 1)).", '".$this->db->idate($now)."', ".(int) $user->id.")";
		if (!$this->db->query($sql)) return -1;
		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."smshub_template");
		return $this->id;
	}

	public function update($user)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."smshub_template SET";
		$sql .= " code = '".$this->db->escape($this->code)."',";
		$sql .= " label = '".$this->db->escape($this->label)."',";
		$sql .= " content = '".$this->db->escape($this->content)."',";
		$sql .= " context = '".$this->db->escape($this->context ?: 'manual')."',";
		$sql .= " active = ".((int) $this->active).",";
		$sql .= " fk_user_modif = ".(int) $user->id;
		$sql .= " WHERE rowid = ".(int) $this->id;
		return $this->db->query($sql) ? 1 : -1;
	}

	public function delete($id)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."smshub_template WHERE rowid = ".(int) $id;
		return $this->db->query($sql) ? 1 : -1;
	}

	public static function listAll($db, $context = null, $only_active = false)
	{
		$rows = array();
		$sql = "SELECT rowid, code, label, content, context, active FROM ".MAIN_DB_PREFIX."smshub_template";
		$sql .= " WHERE entity IN (".getEntity('smshub_template').")";
		if ($context) $sql .= " AND context = '".$db->escape($context)."'";
		if ($only_active) $sql .= " AND active = 1";
		$sql .= " ORDER BY context, label";
		$res = $db->query($sql);
		if (!$res) return $rows;
		while ($obj = $db->fetch_object($res)) $rows[] = $obj;
		return $rows;
	}

	/**
	 * Substitute {variable} placeholders in a string.
	 *
	 * @param string $content Template body
	 * @param array $vars Map of variable_name => value
	 * @return string Rendered text
	 */
	public static function render($content, array $vars)
	{
		if (empty($content)) return '';
		$keys = array();
		$vals = array();
		foreach ($vars as $k => $v) {
			$keys[] = '{'.$k.'}';
			$vals[] = (string) $v;
		}
		return str_replace($keys, $vals, $content);
	}

	/**
	 * Variables exposed by context. Used by admin UI to display the legend.
	 */
	public static function availableVariables($context)
	{
		$base = array(
			'client_name' => 'Nom du client',
			'company_name' => 'Nom de notre société',
			'date' => 'Date courante (JJ/MM/AAAA)',
		);
		switch ($context) {
			case 'bill':
			case 'relance':
				return array_merge($base, array(
					'ref' => 'Référence facture',
					'amount' => 'Montant TTC formaté',
					'amount_remaining' => 'Reste à payer',
					'due_date' => 'Date d\'échéance',
					'days_late' => 'Jours de retard',
					'payment_link' => 'Lien paiement en ligne',
				));
			case 'ticket':
				return array_merge($base, array(
					'ticket_ref' => 'Référence ticket',
					'ticket_subject' => 'Sujet ticket',
					'ticket_status' => 'Statut ticket',
					'technician' => 'Nom du technicien assigné',
				));
			case 'propal':
				return array_merge($base, array(
					'ref' => 'Référence devis',
					'amount' => 'Montant TTC',
					'amount_ht' => 'Montant HT',
					'valid_until' => 'Date de fin de validité',
					'days_remaining' => 'Jours restants avant expiration',
					'signature_link' => 'Lien signature en ligne',
				));
			case 'manual':
			default:
				return $base;
		}
	}
}
