# DOLIBARR-SMSHUB

Module **Dolibarr 18+** (testé sur 23) intégrant la passerelle SMSHUB : envoi de SMS via routeurs 4G locaux (Huawei / Cudy / Capcom6), notifications automatiques sur factures, devis et tickets, modèles SMS avec variables dynamiques, et **bridge** permettant à d'autres modules (RelanceAuto…) d'utiliser SMSHUB comme transport SMS.

Service SMSHUB : <https://smshub.siliteo.com>

## Fonctionnalités

| Domaine | Apport SMSHUB |
|---|---|
| **Factures** | SMS automatique à la validation, à l'enregistrement du paiement, lien court vers paiement en ligne |
| **Devis (propositions commerciales)** | SMS à la validation, à l'envoi par mail, à la signature / au refus, lien signature en ligne |
| **Tickets** | SMS au client à la création / modification / clôture, notification du technicien assigné |
| **Templates** | Modèles SMS avec variables dynamiques `{client_name}`, `{ref}`, `{amount}`, `{due_date}`, `{payment_link}`, `{signature_link}`, `{ticket_ref}`, `{technician}`… |
| **Envoi manuel** | Page "Envoi rapide" + bouton "📱 SMS via SMSHUB" injecté sur cartes facture/devis/ticket/tiers |
| **Programmation** | Envoi différé via le paramètre natif SMSHUB `scheduled_at` (`+15m`, `+2h`, ISO 8601…) |
| **Journal** | Toutes les tentatives loguées (envoyé / programmé / échoué / dryrun) avec filtres |
| **Dry-run** | Mode test pour valider la config sans envoi réel |
| **Bridge inter-modules** | `SmsHubBridge::send($phone, $body, $source, $fk)` exposé aux autres modules custom (RelanceAuto utilise ce bridge automatiquement quand SMSHUB est actif) |

## ⚠️ Relances clients impayés

**Cette fonctionnalité n'est pas couverte par SMSHUB.** Elle est déléguée au module dédié [`DOLIBARR-RELANCEAUTO`](https://github.com/eighteen-ai/DOLIBARR-RELANCEAUTO) qui :
- s'appuie sur le cron natif Dolibarr de relances
- gère le multi-canal (email + SMS + courrier postal PDF)
- regroupe les factures par client (un seul envoi pour N factures impayées)
- gère les promesses de paiement (gel des relances)
- injecte les méthodes de paiement (RIB, SumUp, chèque) dans les templates

Quand SMSHUB et RelanceAuto sont tous deux activés, RelanceAuto route automatiquement ses SMS via SMSHUB.

## Installation

```bash
cd /var/www/dolibarr/htdocs/custom/
git clone https://github.com/eighteen-ai/DOLIBARR-SMSHUB.git
mv DOLIBARR-SMSHUB/smshub .
rm -rf DOLIBARR-SMSHUB
```

Puis :

1. Dolibarr → **Configuration → Modules** → activer **SMSHUB**
2. Menu **Outils → SMSHUB → Configuration**
3. Renseigner :
   - URL serveur : `https://smshub.siliteo.com/SERVER`
   - Clé API : la clé fournie dans le dashboard SMSHUB
   - Indicatif pays par défaut : `+33`
4. Cocher les déclencheurs souhaités (validation facture, paiement, tickets, relances…)
5. Cliquer **Tester la connexion** pour vérifier.

## Mise à jour depuis GitHub

Le module s'auto-met à jour comme nos autres modules :

1. Menu **Outils → SMSHUB → Configuration → onglet "Mise à jour"**
2. Renseigner un *Personal Access Token* GitHub avec scope `repo` (lecture)
3. Cliquer **Vérifier les mises à jour** puis **Mettre à jour maintenant**

Le module récupère le ZIP de la branche `master`, écrase les fichiers existants, conserve les constantes et données. Aucun cron ou serveur externe nécessaire.

## Permissions

| Permission | Description |
|---|---|
| `smshub.send` | Envoyer des SMS (page "Envoi rapide" + bouton sur cartes) |
| `smshub.admin` | Administrer (templates, paliers de relance, config) |
| `smshub.read` | Voir le journal et le tableau de bord |

## Workflow de relances impayés

Activé par défaut. Le cron `SMSHUB - Relances factures impayées` tourne quotidiennement et :

1. Liste toutes les factures **validées et impayées** dont la date d'échéance est dépassée.
2. Pour chaque facture, détermine le palier le plus avancé applicable et **non encore envoyé**.
3. Vérifie le seuil `min_amount` du palier et la présence d'un téléphone sur le tiers.
4. Envoie le SMS via le modèle du palier, enregistre dans `smshub_relance_status` et trace une `actioncomm`.

Paliers livrés par défaut : J+1 amiable, J+7 ferme, J+15 mise en demeure. Modifiables dans **SMSHUB → Relances**.

## Variables disponibles dans les modèles

### Contexte `bill` / `relance`
`{client_name}`, `{company_name}`, `{ref}`, `{amount}`, `{amount_remaining}`, `{due_date}`, `{days_late}`, `{payment_link}`, `{date}`

### Contexte `ticket`
`{client_name}`, `{company_name}`, `{ticket_ref}`, `{ticket_subject}`, `{ticket_status}`, `{technician}`, `{date}`

## Compatibilité

- Dolibarr **18+** (développé sur 23)
- PHP **7.4+**
- Module Ticket activé pour les notifications tickets
- Module Société (tiers) activé (obligatoire)

## Licence

GPL v3+

## Auteur

[eighteen-ai](https://github.com/eighteen-ai) — Siliteo
