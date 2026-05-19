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
		// Common base, always available
		$base = array(
			'client_name' => 'Nom (raison sociale) du client',
			'company_name' => 'Nom de notre société',
			'date' => 'Date courante (JJ/MM/AAAA)',
		);

		// Third-party / contact fields (added for bill/propal/ticket contexts)
		$thirdparty = array(
			'client_firstname' => 'Prénom (depuis contact ou nom)',
			'client_lastname' => 'Nom de famille',
			'client_civility' => 'Civilité',
			'client_address' => 'Adresse postale',
			'client_zip' => 'Code postal',
			'client_town' => 'Ville',
			'client_country' => 'Pays',
			'client_email' => 'Email',
			'client_phone' => 'Téléphone',
		);

		switch ($context) {
			case 'bill':
			case 'relance':
				return array_merge($base, $thirdparty, array(
					'ref' => 'Référence facture',
					'amount' => 'Montant TTC formaté',
					'amount_remaining' => 'Reste à payer',
					'due_date' => 'Date d\'échéance',
					'days_late' => 'Jours de retard',
					'payment_link' => 'Lien paiement en ligne (SumUp/Stripe/virement/chèque selon config Dolibarr)',
					'document_link' => 'Lien public vers la facture (page de paiement Dolibarr)',
					'payment_methods_text' => 'Liste textuelle des moyens de paiement (configurable)',
				));
			case 'ticket':
				return array_merge($base, $thirdparty, array(
					'ticket_ref' => 'Référence ticket',
					'ticket_subject' => 'Sujet ticket',
					'ticket_status' => 'Statut ticket',
					'ticket_link' => 'Lien public vers le ticket (interface publique Dolibarr)',
					'technician' => 'Nom du technicien assigné',
				));
			case 'propal':
				return array_merge($base, $thirdparty, array(
					'ref' => 'Référence devis',
					'amount' => 'Montant TTC',
					'amount_ht' => 'Montant HT',
					'valid_until' => 'Date de fin de validité',
					'days_remaining' => 'Jours restants avant expiration',
					'signature_link' => 'Lien signature en ligne',
					'document_link' => 'Lien public vers le devis (page de signature Dolibarr)',
					'payment_methods_text' => 'Liste textuelle des moyens de paiement (configurable)',
				));
			case 'manual':
			default:
				return $base;
		}
	}

	/**
	 * Returns the full variables map for all contexts — used by the JS editor
	 * to update the variables list live when the user switches context.
	 */
	public static function allVariablesByContext()
	{
		$out = array();
		foreach (array('manual', 'bill', 'propal', 'ticket', 'relance') as $ctx) {
			$out[$ctx] = self::availableVariables($ctx);
		}
		return $out;
	}

	/**
	 * Built-in default templates for every supported event. Kept in PHP so the
	 * module can seed missing rows at activation time (or lazily from any code
	 * path that needs them) without depending on the SQL data file having run.
	 */
	public static function defaultTemplates()
	{
		return array(
			array(
				'code' => 'bill_validated',
				'label' => 'Facture émise',
				'context' => 'bill',
				'content' => 'Bonjour {client_firstname}, votre facture {ref} ({amount}) est disponible. Échéance : {due_date}. Consulter et régler en ligne : {payment_link} — Merci !',
			),
			array(
				'code' => 'bill_payed',
				'label' => 'Paiement reçu',
				'context' => 'bill',
				'content' => 'Bonjour {client_firstname}, nous avons bien reçu votre règlement pour la facture {ref}. Merci de votre confiance !',
			),
			array(
				'code' => 'ticket_created',
				'label' => 'Ticket créé',
				'context' => 'ticket',
				'content' => 'Bonjour {client_firstname}, votre demande #{ticket_ref} a bien été enregistrée : {ticket_subject}. Suivi en ligne : {ticket_link}',
			),
			array(
				'code' => 'ticket_modified',
				'label' => 'Ticket modifié',
				'context' => 'ticket',
				'content' => 'Bonjour {client_firstname}, mise à jour de votre demande #{ticket_ref} (statut : {ticket_status}). Détails et historique : {ticket_link}',
			),
			array(
				'code' => 'ticket_closed',
				'label' => 'Ticket résolu',
				'context' => 'ticket',
				'content' => 'Bonjour {client_firstname}, votre demande #{ticket_ref} est résolue. Voir la solution : {ticket_link}. À très bientôt !',
			),
			array(
				'code' => 'ticket_assigned_tech',
				'label' => 'Ticket assigné (technicien)',
				'context' => 'ticket',
				'content' => 'Nouveau ticket #{ticket_ref} assigné. Client : {client_name}. Sujet : {ticket_subject}.',
			),
			array(
				'code' => 'propal_validated',
				'label' => 'Devis validé',
				'context' => 'propal',
				'content' => 'Bonjour {client_firstname}, votre devis {ref} ({amount}) est validé. Valable jusqu\'au {valid_until}. Consultable ici : {signature_link}',
			),
			array(
				'code' => 'propal_sent',
				'label' => 'Devis envoyé',
				'context' => 'propal',
				'content' => 'Bonjour {client_firstname}, votre devis {ref} ({amount}) vient de vous être adressé. Consultable et signable en ligne : {signature_link} (validité {valid_until})',
			),
			array(
				'code' => 'propal_signed',
				'label' => 'Devis signé',
				'context' => 'propal',
				'content' => 'Bonjour {client_firstname}, votre devis {ref} est bien signé. Nous revenons vers vous très vite pour la suite. Merci !',
			),
			array(
				'code' => 'propal_refused',
				'label' => 'Devis refusé',
				'context' => 'propal',
				'content' => 'Bonjour {client_firstname}, nous avons pris note de votre refus du devis {ref}. N\'hésitez pas à nous recontacter si nous pouvons l\'ajuster.',
			),
		);
	}

	/**
	 * Idempotent: inserts every default template that is currently missing.
	 * Returns the number of rows actually created.
	 *
	 * Safe to call from module init() AND from runtime code paths (AJAX, send
	 * page, etc.) — fetchByCode short-circuits when the row already exists, so
	 * customized templates are never overwritten.
	 */
	public static function seedDefaults($db, $user = null)
	{
		if (empty($user)) { global $user; }
		$created = 0;
		foreach (self::defaultTemplates() as $def) {
			$existing = new self($db);
			if ($existing->fetchByCode($def['code']) > 0) continue;
			$tpl = new self($db);
			$tpl->code = $def['code'];
			$tpl->label = $def['label'];
			$tpl->content = $def['content'];
			$tpl->context = $def['context'];
			$tpl->active = 1;
			if ($tpl->create($user) > 0) $created++;
		}
		return $created;
	}
}
