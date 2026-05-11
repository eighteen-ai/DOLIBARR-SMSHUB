# Changelog

## 1.0.0 — 2026-05-12

- Initial release.
- Module descriptor with constants, permissions, menu, cron job.
- SmsHubApi (REST client), SmsHubTemplate (CRUD + variable rendering), SmsHubLog (journal), SmsHubSender (orchestrator), SmsHubRelance (reminder workflow).
- Trigger handler for BILL_VALIDATE, BILL_PAYED, TICKET_CREATE / MODIFY / CLOSE / ASSIGN.
- Admin pages: dashboard, setup (with connection test), templates CRUD, relance steps CRUD, log viewer with filters, manual send, about, update (GitHub auto-update).
- 9 default SMS templates (FR) and 3 default reminder steps (J+1, J+7, J+15).
- Hook integration on invoice / ticket / thirdparty cards (SMS button + history panel).
- GitHub auto-update via Personal Access Token, mirroring the convention of other modules in the eighteen-ai org.
