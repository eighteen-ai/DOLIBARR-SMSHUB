# Changelog

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
