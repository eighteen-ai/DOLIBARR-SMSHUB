-- Default SMS templates inserted at module installation.
-- Uses INSERT IGNORE so re-install does not duplicate.

INSERT IGNORE INTO llx_smshub_template (entity, code, label, content, context, active, datec) VALUES
(1, 'bill_validated', 'Facture émise', 'Bonjour {client_name}, votre facture {ref} de {amount} est disponible. Échéance : {due_date}. {payment_link}', 'bill', 1, NOW()),
(1, 'bill_payed', 'Paiement reçu', 'Bonjour {client_name}, nous avons bien reçu votre paiement pour la facture {ref}. Merci !', 'bill', 1, NOW()),
(1, 'ticket_created', 'Ticket créé', 'Bonjour {client_name}, votre demande #{ticket_ref} a été enregistrée : {ticket_subject}. Nous revenons vers vous rapidement.', 'ticket', 1, NOW()),
(1, 'ticket_modified', 'Ticket modifié', 'Bonjour {client_name}, votre demande #{ticket_ref} a été mise à jour. Statut : {ticket_status}.', 'ticket', 1, NOW()),
(1, 'ticket_closed', 'Ticket résolu', 'Bonjour {client_name}, votre demande #{ticket_ref} est résolue. N\'hésitez pas à nous recontacter.', 'ticket', 1, NOW()),
(1, 'ticket_assigned_tech', 'Ticket assigné (technicien)', 'Nouveau ticket #{ticket_ref} assigné. Client : {client_name}. Sujet : {ticket_subject}.', 'ticket', 1, NOW()),
(1, 'propal_validated', 'Devis validé', 'Bonjour {client_name}, votre devis {ref} ({amount}) est validé. Valable jusqu''au {valid_until}.', 'propal', 1, NOW()),
(1, 'propal_sent', 'Devis envoyé', 'Bonjour {client_name}, votre devis {ref} ({amount}) vient de vous être adressé par mail. Validité : {valid_until}. {signature_link}', 'propal', 1, NOW()),
(1, 'propal_signed', 'Devis signé', 'Bonjour {client_name}, votre devis {ref} est bien signé. Nous vous contactons sous peu pour la suite. Merci !', 'propal', 1, NOW()),
(1, 'propal_refused', 'Devis refusé', 'Bonjour {client_name}, nous avons pris note de votre refus du devis {ref}. N''hésitez pas si nous pouvons l''ajuster.', 'propal', 1, NOW());

