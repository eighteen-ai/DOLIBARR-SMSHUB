-- Pre-1.1.5 templates → 1.1.5 templates migration.
-- Only updates rows whose content STILL matches the previous default — preserves
-- user customizations. Idempotent: re-running on already-migrated installs is a no-op.

UPDATE llx_smshub_template SET content = 'Bonjour {client_firstname}, votre facture {ref} de {amount} est disponible (échéance {due_date}). Consulter et payer : {payment_link}'
 WHERE code = 'bill_validated' AND content = 'Bonjour {client_name}, votre facture {ref} de {amount} est disponible. Échéance : {due_date}. {payment_link}';

UPDATE llx_smshub_template SET content = 'Bonjour {client_firstname}, nous avons bien reçu votre paiement pour la facture {ref}. Merci !'
 WHERE code = 'bill_payed' AND content = 'Bonjour {client_name}, nous avons bien reçu votre paiement pour la facture {ref}. Merci !';

UPDATE llx_smshub_template SET content = 'Bonjour {client_firstname}, votre demande #{ticket_ref} a été enregistrée : {ticket_subject}. Suivi : {ticket_link}'
 WHERE code = 'ticket_created' AND content = 'Bonjour {client_name}, votre demande #{ticket_ref} a été enregistrée : {ticket_subject}. Nous revenons vers vous rapidement.';

UPDATE llx_smshub_template SET content = 'Bonjour {client_firstname}, mise à jour de votre demande #{ticket_ref}. Statut : {ticket_status}. Détails : {ticket_link}'
 WHERE code = 'ticket_modified' AND content = 'Bonjour {client_name}, votre demande #{ticket_ref} a été mise à jour. Statut : {ticket_status}.';

UPDATE llx_smshub_template SET content = 'Bonjour {client_firstname}, votre demande #{ticket_ref} est résolue. {ticket_link}'
 WHERE code = 'ticket_closed' AND content = 'Bonjour {client_name}, votre demande #{ticket_ref} est résolue. N''hésitez pas à nous recontacter.';

UPDATE llx_smshub_template SET content = 'Bonjour {client_firstname}, votre devis {ref} ({amount}) est validé. Valable jusqu''au {valid_until}. {document_link}'
 WHERE code = 'propal_validated' AND content = 'Bonjour {client_name}, votre devis {ref} ({amount}) est validé. Valable jusqu''au {valid_until}.';

UPDATE llx_smshub_template SET content = 'Bonjour {client_firstname}, votre devis {ref} ({amount}) est consultable et signable en ligne : {signature_link} (validité {valid_until})'
 WHERE code = 'propal_sent' AND content = 'Bonjour {client_name}, votre devis {ref} ({amount}) vient de vous être adressé par mail. Validité : {valid_until}. {signature_link}';

UPDATE llx_smshub_template SET content = 'Bonjour {client_firstname}, votre devis {ref} est bien signé. Nous revenons vers vous sous peu. Merci !'
 WHERE code = 'propal_signed' AND content = 'Bonjour {client_name}, votre devis {ref} est bien signé. Nous vous contactons sous peu pour la suite. Merci !';

UPDATE llx_smshub_template SET content = 'Bonjour {client_firstname}, nous avons pris note de votre refus du devis {ref}. N''hésitez pas si nous pouvons l''ajuster.'
 WHERE code = 'propal_refused' AND content = 'Bonjour {client_name}, nous avons pris note de votre refus du devis {ref}. N''hésitez pas si nous pouvons l''ajuster.';
