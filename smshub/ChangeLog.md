# Changelog

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
