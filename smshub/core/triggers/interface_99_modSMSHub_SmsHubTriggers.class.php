<?php
/* Copyright (C) 2026 SMSHUB - Dolibarr trigger handler */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceSmsHubTriggers extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'notification';
		$this->description = "Déclencheurs SMSHUB : envoi automatique de SMS sur événements Dolibarr";
		$this->version = '1.0.0';
		$this->picto = 'phoning';
	}

	public function getName() { return $this->name; }
	public function getDesc() { return $this->description; }

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->smshub) || empty($conf->smshub->enabled)) return 0;

		require_once DOL_DOCUMENT_ROOT.'/custom/smshub/class/smshubsender.class.php';
		$sender = new SmsHubSender($this->db);

		switch ($action) {
			case 'BILL_VALIDATE':
				if (!getDolGlobalString('SMSHUB_ENABLE_BILL_VALIDATE')) return 0;
				return $this->fireBill($sender, $object, 'bill_validated');

			case 'BILL_PAYED':
			case 'BILL_PAID':
				if (!getDolGlobalString('SMSHUB_ENABLE_BILL_PAYED')) return 0;
				return $this->fireBill($sender, $object, 'bill_payed');

			case 'TICKET_CREATE':
				if (!getDolGlobalString('SMSHUB_ENABLE_TICKET_CREATE')) return 0;
				return $this->fireTicket($sender, $object, 'ticket_created');

			case 'TICKET_MODIFY':
				if (!getDolGlobalString('SMSHUB_ENABLE_TICKET_MODIFY')) return 0;
				return $this->fireTicket($sender, $object, 'ticket_modified');

			case 'TICKET_CLOSE':
				if (!getDolGlobalString('SMSHUB_ENABLE_TICKET_CLOSE')) return 0;
				return $this->fireTicket($sender, $object, 'ticket_closed');

			case 'TICKET_ASSIGN':
				if (!getDolGlobalString('SMSHUB_ENABLE_TICKET_ASSIGN')) return 0;
				return $this->fireTicketTech($sender, $object, 'ticket_assigned_tech');
		}
		return 0;
	}

	protected function fireBill($sender, $facture, $template_code)
	{
		if (empty($facture->id)) return 0;
		if (empty($facture->thirdparty)) $facture->fetch_thirdparty();
		$phone = SmsHubSender::thirdpartyPhone($facture->thirdparty);
		if (empty($phone)) return 0;
		$vars = SmsHubSender::buildBillVars($facture);
		$ok = $sender->sendFromTemplate($template_code, $phone, $vars, 'bill', $facture->id);
		return $ok ? 1 : 0;
	}

	protected function fireTicket($sender, $ticket, $template_code)
	{
		if (empty($ticket->id)) return 0;
		if (empty($ticket->thirdparty) && !empty($ticket->fk_soc)) {
			require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
			$soc = new Societe($this->db);
			$soc->fetch($ticket->fk_soc);
			$ticket->thirdparty = $soc;
		}
		$phone = SmsHubSender::thirdpartyPhone($ticket->thirdparty ?? null);
		if (empty($phone)) return 0;
		$vars = SmsHubSender::buildTicketVars($ticket);
		$ok = $sender->sendFromTemplate($template_code, $phone, $vars, 'ticket', $ticket->id);
		return $ok ? 1 : 0;
	}

	protected function fireTicketTech($sender, $ticket, $template_code)
	{
		if (empty($ticket->id) || empty($ticket->fk_user_assign)) return 0;
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		$u = new User($this->db);
		if ($u->fetch($ticket->fk_user_assign) <= 0) return 0;
		$phone = !empty($u->user_mobile) ? $u->user_mobile : ($u->office_phone ?? '');
		if (empty($phone)) return 0;
		$vars = SmsHubSender::buildTicketVars($ticket);
		$ok = $sender->sendFromTemplate($template_code, $phone, $vars, 'ticket', $ticket->id);
		return $ok ? 1 : 0;
	}
}
