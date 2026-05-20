# Changelog

## 1.1.17 — 2026-05-20

- Fix: the SMS row was never injected on ticket cards because the ticket "Add or send a message" form has completely different markup from the standard FormMail:
  - Form id/name is `ticket` (not `mailform`).
  - No `id` input is exposed; only `track_id` (URL) and `trackid` (form, value `tic<rowid>`) are present.
  - Submit action is `add_message` (button `btn_add_message`), URL action is `presend_addmessage`.
- The JS now selects `#ticket, form[name="ticket"]` when type=ticket, resolves the object identifier from `track_id` instead of the rowid, and sends it to the AJAX endpoint as `&track_id=...` (vs `&id=...` for facture/propal). The AJAX endpoint dispatches `Ticket->fetch(0, '', $track_id)` when a track_id is provided.

## 1.1.16 — 2026-05-20

- Fix: on ticket cards the SMS row was not injected at all (not just unchecked as previously thought). Dropped the URL-level `action=presend` gate that filtered out ticket variants like `action=presend_addmessage`. Detection now relies solely on the presence of `#mailform` on the page combined with the card-path match (`/ticket/card.php`).
- Added structured `[SMSHUB]` console log lines at every decision point (script load, no-form, no-id, injection) so it's straightforward to diagnose why the row would be missing on a given page. Look in the browser console (F12).
- Object id resolution falls back to `track_id` for tickets when neither `id` nor `facid` is present in the URL/form.

## 1.1.15 — 2026-05-20

- Fix: on ticket cards, the SMS row's destination phone was empty and the checkbox never pre-checked. The AJAX endpoint's manual `!empty($o->fk_soc)` check failed when Dolibarr's Ticket class exposed the third-party id via `socid` instead. Switched to `fetch_thirdparty()` (CommonObject standard) for consistency with facture/propal. Added a fallback that uses the SUPPORTCLI contact's mobile/pro/personal phone when the thirdparty itself has no phone — often more relevant on support tickets.
- JS: auto-checks the SMS box when the user types a phone manually (and unchecks it when the field is cleared) so the case where the thirdparty has no phone is still usable in one click.

## 1.1.14 — 2026-05-20

- Log viewer (`admin/log.php`) was visually truncating the message column to 80 chars (full text only available in the cell `title` tooltip). Now the full message is rendered with `white-space:pre-wrap` inside a 480px-max wrapper so long multi-segment SMS are entirely readable. Same treatment for the error column (280px wrapper). Storage was — and is — `TEXT`, no length cap; only the display was the issue.

## 1.1.13 — 2026-05-20

- Mail-form SMS textarea now auto-resizes to fit its content (initial fill + every keystroke). Long SMS look truncated otherwise because the visible portion was capped to `rows=5` while the rest scrolled out of view — there was never any actual cap. SMSHUB sends long SMS as multi-segment messages, no length limit imposed by this module.
- Counter wording clarified: `X caractères · Y segments SMS (Unicode)` instead of `X caractères · Y SMS (160 / segment)` which could be misread as a per-message limit.

## 1.1.12 — 2026-05-19

- Mail-form row : the editable textarea introduced in 1.1.11 was hidden by default and made visible by jQuery only when the AJAX preview arrived. On installs where the cached JS was still v1.1.10 the user saw the old read-only div instead. Now the textarea is rendered visible from the start (jQuery just fills it once the AJAX returns), so an out-of-date cached JS is immediately distinguishable from the new layout. Added a `[SMSHUB] mailform JS 1.1.12` console.log + a small `vN` version tag next to the label so users can confirm which version is loaded.

## 1.1.11 — 2026-05-19

- Editable SMS body on the mail-form row: the rendered template preview now lands in a `<textarea>` (`smshub_send_sms_message`) that the user can tweak before submitting. A small live counter shows length + estimated SMS segments (160 chars GSM-7 / 70 chars Unicode).
- Trigger handler honors the posted body verbatim through a new `dispatch()` helper that calls `SmsHubSender::sendDirect()` when a custom message is present, falling back to `sendFromTemplate()` (re-render) otherwise. The template code is still recorded in the log + agenda event so the origin is traceable.

## 1.1.10 — 2026-05-19

- Each successful SMS send (skipping dry-runs) now records a Dolibarr agenda event in `llx_actioncomm` with code `AC_BILL_SENTBYSMS` / `AC_PROPAL_SENTBYSMS` / `AC_TICKET_SENTBYSMS` (depending on source), `elementtype` matching the source, `fk_element` = the document id, `socid` = the thirdparty, label "SMS envoyé via SMSHUB — <ref>" and the message + destination + task_id in `note_private`. Mirrors the pattern used by `DOLIBARR-CHORUSPRO`.
- Enables sibling modules like `DOLIBARR-FILTRABLENOTIFICATION` to surface SMS as a notification type (via `code LIKE '%SENTBYSMS'`) alongside email and Chorus depots.

## 1.1.9 — 2026-05-19

- Fix: Dolibarr's SMS test page (Outils → SMS) errored out with `The SMS manager '1' defined into SMS setup MAIN_MODULE_SMSHUB_SMS is not found`. The `'sms' => 1` entry I added to `module_parts` in 1.1.5 was incorrect — Dolibarr interprets the value as the SMS manager class name. The supported way for third-party modules to integrate with Dolibarr's SMS layer is via the `sendsms` hook (which the module already provides through `SMSHUB_INTERCEPT_DOLIBARR_SMS`), not via `MAIN_MODULE_*_SMS`. Removed the broken module_parts entry, and `modSMSHub::init()` now deletes the stale `MAIN_MODULE_SMSHUB_SMS` constant on reactivation so existing installs are repaired.
- The About page no longer claims native operator registration — it now accurately describes the hook-based integration (and explains why SMSHUB does not appear in the "Fournisseur de SMS" dropdown).

## 1.1.8 — 2026-05-19

- Editable destination phone on the mail-form row: the number pre-filled from the client record can now be modified inline before sending. Posted as `smshub_send_sms_phone`; the trigger handler honors it for `BILL_SENTBYMAIL`, `PROPAL_SENTBYMAIL` and ticket message-sent variants (falls back to the thirdparty's phone for triggers fired outside the mail form, e.g. `BILL_PAYED`).
- Auto-seeding of default SMS templates: `SmsHubTemplate::seedDefaults()` inserts any missing template (`bill_validated`, `bill_payed`, `propal_sent`, `propal_signed`, `propal_validated`, `propal_refused`, `ticket_created`, `ticket_modified`, `ticket_closed`, `ticket_assigned_tech`). Called from `modSMSHub::init()` (module activation) and lazily from `ajax/mailform_data.php` (so installs without templates get them on first mail-form load). Idempotent — never overwrites a customized template, only fills the gaps. Default bodies include the rich variables (`{client_firstname}`, `{payment_link}`, `{signature_link}`, `{ticket_link}`, etc.).

## 1.1.7 — 2026-05-19

- Fix (suite): the `printCommonFooter` hook approach from 1.1.6 still produced no output on Dolibarr 23.0.3 in `action=presend` mode. The checkbox injection now bypasses page-level hooks entirely:
  - New JS file `js/smshub_mailform.js` loaded globally via `module_parts['js']` (same pattern as `aitext`). The script detects the mail-form context client-side and only acts on `action=presend` on facture / propal / ticket cards.
  - New AJAX endpoint `ajax/mailform_data.php` returns `{phone, template, preview}` so the row can show the real destination number and the rendered SMS preview.
  - `printCommonFooter` + `renderMailCheckbox` methods removed from `ActionsSmshub` — dead code.
- **Important** : la prise en compte du nouveau JS nécessite une désactivation/réactivation du module SMSHUB (sinon `module_parts['js']` n'est pas relu).

## 1.1.6 — 2026-05-19

- Fix: the "Envoyer aussi un SMS au client" checkbox introduced in 1.1.5 was never displayed on the mail send form. `formObjectOptions` is not fired by Dolibarr's card pages when `action=presend` (the regular display path is bypassed for the mail form). The checkbox is now injected from `printCommonFooter`, which runs unconditionally on every page, with URL-based detection of the facture / propal / ticket context to load the right object and render the SMS preview.

## 1.1.5 — 2026-05-19

- **Case à cocher "Envoyer aussi un SMS au client" sur le formulaire d'envoi de mail** (cartes facture / devis / ticket). Affiche l'aperçu rendu du SMS (template `bill_validated` / `propal_sent` / `ticket_modified`), le numéro destinataire, et déclenche l'envoi automatiquement après l'envoi du mail. Cochage par défaut quand un numéro mobile est présent sur le client; désactivé sinon. Quand la case est explicitement décochée, elle prend le dessus sur les déclencheurs globaux (`SMSHUB_ENABLE_PROPAL_SENT`) pour éviter les doubles envois.
- **Enregistrement comme opérateur SMS Dolibarr** : `module_parts['sms'] = 1`, et nouveau hook `sendsms` qui intercepte `CSMSFile::sendfile()` pour router les SMS Dolibarr standards (module Notifications, etc.) via SMSHUB. Activé par la nouvelle constante `SMSHUB_INTERCEPT_DOLIBARR_SMS` (setup, désactivée par défaut pour ne pas casser une config OVH/CMTelecom existante).
- **Nouvelle variable `{document_link}`** pour les contextes `bill` et `propal` (lien public Dolibarr : page de paiement / page de signature). Nouvelle variable `{ticket_link}` pour le contexte `ticket` (interface publique de suivi). Apparaissent dans la légende de l'éditeur de modèles.
- Modèles par défaut enrichis : utilisent `{client_firstname}` (au lieu de `{client_name}`), incluent `{payment_link}` (factures), `{signature_link}` (devis), `{ticket_link}` (tickets). Migration idempotente : les modèles encore au contenu d'usine sont mis à jour automatiquement à la réactivation; les modèles personnalisés sont préservés.

## 1.1.4 — 2026-05-14

- Enriched variable dictionary for `bill`, `propal`, `ticket` contexts: `{client_firstname}`, `{client_lastname}`, `{client_civility}`, `{client_address}`, `{client_zip}`, `{client_town}`, `{client_country}`, `{client_email}`, `{client_phone}`. Values prefer the billing/customer contact on the document; fall back to the third-party fields. Best-effort firstname/lastname split when only `name` is available.
- New variable `{payment_methods_text}` for `bill` and `propal` contexts — content configurable via `SMSHUB_PAYMENT_METHODS_TEXT` constant (setup page). Default: "virement, chèque ou carte bancaire". Lets templates mention payment options textually alongside the existing `{payment_link}`.
- Interactive editor on templates page: variable list rerenders live when the context dropdown changes (no save needed). Click on any variable to insert it at the cursor position in the content textarea.
- New **Prévisualisation** admin page: pick a real facture/devis/ticket by id + a template, see the rendered SMS in a WhatsApp-style bubble with character count + the full resolved variable map. No SMS sent.

## 1.1.3 — 2026-05-14

- Fix: a SMS send call returning a non-JSON body or an `ok:true` response without `task_id` was silently logged as `sent`/`scheduled` while no SMS actually went out. Now treated as a failure with the raw body captured in `error_message` so the journal makes the root cause visible.
- `SmsHubApi`: expose `last_raw_body` and `last_request_url` properties for diagnostics; reject non-JSON responses early.
- `test_connection` now also exercises the API key against `/clients` (auth check), not just the public `/version` route.
- Add **Diagnostic d'envoi** button in setup: sends a real SMS to `SMSHUB_TEST_PHONE` and displays the full HTTP exchange (URL, status code, raw body, decoded response, extracted task_id) so users can see exactly what SMSHUB returns.

## 1.1.2 — 2026-05-13

- Add `SMSHUB_TEST_PHONE` setting: when set, any SMS sent to this number bypasses the global dry-run flag and reaches SMSHUB for real. Purpose: validate end-to-end behaviour including scheduling (`scheduled_at`) without disabling dry-run for production triggers. Test sends get a `[TEST]` prefix in the log message body.

## 1.1.1 — 2026-05-13

- Fix: admin users were denied access to module pages (templates, send, log, dashboard) even when their account is flagged as Dolibarr admin. All page-level checks and menu visibility rules now accept `$user->admin` as a bypass alongside `hasRight()`. Matches the convention of sibling modules (dolisirene, sumup). Menu items refresh on module deactivate/reactivate; page checks apply immediately on auto-update.

## 1.1.0 — 2026-05-12

- Add support for commercial proposals (devis) — triggers `PROPAL_VALIDATE`, `PROPAL_SENTBYMAIL`, `PROPAL_CLOSE_SIGNED`, `PROPAL_CLOSE_REFUSED`; 4 default templates; `propalcard` added to hook contexts (button + history panel on proposal card); `buildPropalVars()` with `{signature_link}` and `{valid_until}`.
- **BREAKING**: Remove unpaid invoice reminder workflow (deduplicated against `DOLIBARR-RELANCEAUTO`). Removed: `SmsHubRelance` class, `relances.php` admin page, `llx_smshub_relance_step` and `llx_smshub_relance_status` tables, cron `runDailyReminders`, default templates `relance_doux`/`relance_ferme`/`relance_med`, constant `SMSHUB_ENABLE_RELANCES`, menu entry, lib tab.
- Add `SmsHubBridge` class: public static API for other modules to route SMS through SMSHUB (`SmsHubBridge::isAvailable()`, `SmsHubBridge::send()`). Constant `SMSHUB_BRIDGE_PUBLIC` (default 1) to enable/disable exposure.
- RelanceAuto integration: when SMSHUB is active, RelanceAuto automatically routes its SMS through SMSHUB instead of `CSMSFile` (transparent, with CSMSFile fallback).

## 1.0.0 — 2026-05-12

- Initial release.
- Module descriptor with constants, permissions, menu, cron job.
- SmsHubApi (REST client), SmsHubTemplate (CRUD + variable rendering), SmsHubLog (journal), SmsHubSender (orchestrator), SmsHubRelance (reminder workflow).
- Trigger handler for BILL_VALIDATE, BILL_PAYED, TICKET_CREATE / MODIFY / CLOSE / ASSIGN.
- Admin pages: dashboard, setup (with connection test), templates CRUD, relance steps CRUD, log viewer with filters, manual send, about, update (GitHub auto-update).
- 9 default SMS templates (FR) and 3 default reminder steps (J+1, J+7, J+15).
- Hook integration on invoice / ticket / thirdparty cards (SMS button + history panel).
- GitHub auto-update via Personal Access Token, mirroring the convention of other modules in the eighteen-ai org.
