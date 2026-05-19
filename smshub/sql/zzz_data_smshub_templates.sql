-- Default SMS templates inserted at module installation.
-- INSERT IGNORE keeps re-install / module reactivation safe (UNIQUE on (code, entity)).
-- The companion file zzz_migrate_smshub_templates.sql upgrades older installs
-- whose templates still match the pre-1.1.5 defaults.

INSERT IGNORE INTO llx_smshub_template (entity, code, label, content, context, active, datec) VALUES
(1, 'bill_validated', 'Facture émise', 'Bonjour {client_firstname}, votre facture {ref} de {amount} est disponible (échéance {due_date}). Consulter et payer : {payment_link}', 'bill', 1, NOW()),
(1, 'bill_payed', 'Paiement reçu', 'Bonjour {client_firstname}, nous avons bien reçu votre paiement pour la facture {ref}. Merci !', 'bill', 1, NOW()),
(1, 'ticket_created', 'Ticket créé', 'Bonjour {client_firstname}, votre demande #{ticket_ref} a été enregistrée : {ticket_subject}. Suivi : {ticket_link}', 'ticket', 1, NOW()),
(1, 'ticket_modified', 'Ticket modifié', 'Bonjour {client_firstname}, mise à jour de votre demande #{ticket_ref}. Statut : {ticket_status}. Détails : {ticket_link}', 'ticket', 1, NOW()),
(1, 'ticket_closed', 'Ticket résolu', 'Bonjour {client_firstname}, votre demande #{ticket_ref} est résolue. {ticket_link}', 'ticket', 1, NOW()),
(1, 'ticket_assigned_tech', 'Ticket assigné (technicien)', 'Nouveau ticket #{ticket_ref} assigné. Client : {client_name}. Sujet : {ticket_subject}.', 'ticket', 1, NOW()),
(1, 'propal_validated', 'Devis validé', 'Bonjour {client_firstname}, votre devis {ref} ({amount}) est validé. Valable jusqu''au {valid_until}. {document_link}', 'propal', 1, NOW()),
(1, 'propal_sent', 'Devis envoyé', 'Bonjour {client_firstname}, votre devis {ref} ({amount}) est consultable et signable en ligne : {signature_link} (validité {valid_until})', 'propal', 1, NOW()),
(1, 'propal_signed', 'Devis signé', 'Bonjour {client_firstname}, votre devis {ref} est bien signé. Nous revenons vers vous sous peu. Merci !', 'propal', 1, NOW()),
(1, 'propal_refused', 'Devis refusé', 'Bonjour {client_firstname}, nous avons pris note de votre refus du devis {ref}. N''hésitez pas si nous pouvons l''ajuster.', 'propal', 1, NOW());
