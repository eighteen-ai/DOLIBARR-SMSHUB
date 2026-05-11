-- Default SMS templates inserted at module installation.
-- Uses INSERT IGNORE so re-install does not duplicate.

INSERT IGNORE INTO llx_smshub_template (entity, code, label, content, context, active, datec) VALUES
(1, 'bill_validated', 'Facture émise', 'Bonjour {client_name}, votre facture {ref} de {amount} est disponible. Échéance : {due_date}. {payment_link}', 'bill', 1, NOW()),
(1, 'bill_payed', 'Paiement reçu', 'Bonjour {client_name}, nous avons bien reçu votre paiement pour la facture {ref}. Merci !', 'bill', 1, NOW()),
(1, 'relance_doux', 'Relance amiable', 'Bonjour {client_name}, votre facture {ref} ({amount_remaining}) est arrivée à échéance le {due_date}. Merci de procéder au règlement : {payment_link}', 'relance', 1, NOW()),
(1, 'relance_ferme', 'Relance ferme', 'Bonjour {client_name}, votre facture {ref} reste impayée ({days_late} jours de retard, montant {amount_remaining}). Merci de régulariser rapidement : {payment_link}', 'relance', 1, NOW()),
(1, 'relance_med', 'Mise en demeure', '{company_name} : votre facture {ref} ({amount_remaining}) est impayée depuis {days_late} jours. Sans règlement sous 8 jours, le dossier sera transmis au contentieux.', 'relance', 1, NOW()),
(1, 'ticket_created', 'Ticket créé', 'Bonjour {client_name}, votre demande #{ticket_ref} a été enregistrée : {ticket_subject}. Nous revenons vers vous rapidement.', 'ticket', 1, NOW()),
(1, 'ticket_modified', 'Ticket modifié', 'Bonjour {client_name}, votre demande #{ticket_ref} a été mise à jour. Statut : {ticket_status}.', 'ticket', 1, NOW()),
(1, 'ticket_closed', 'Ticket résolu', 'Bonjour {client_name}, votre demande #{ticket_ref} est résolue. N\'hésitez pas à nous recontacter.', 'ticket', 1, NOW()),
(1, 'ticket_assigned_tech', 'Ticket assigné (technicien)', 'Nouveau ticket #{ticket_ref} assigné. Client : {client_name}. Sujet : {ticket_subject}.', 'ticket', 1, NOW());

INSERT IGNORE INTO llx_smshub_relance_step (entity, rank_order, label, days_offset, template_code, min_amount, active) VALUES
(1, 1, 'Relance amiable (J+1)', 1, 'relance_doux', 0, 1),
(1, 2, 'Relance ferme (J+7)', 7, 'relance_ferme', 0, 1),
(1, 3, 'Mise en demeure (J+15)', 15, 'relance_med', 0, 1);
