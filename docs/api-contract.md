
## Endpoints with no SPA caller (Task5 §T5-08 audit, 2026-11-04)

Every route in `routes/api_v1.php` now has a live SPA call site (verified by
static route/call comparison). The lazy expense-category reads
(`roots·{id}·ancestors·children·items`), `GET expense-categories/{id}` and
`POST suppliers/{id}/update` were deleted — see ADR 0007. The remaining
caller-less reads are deliberate, with the reason:

| Endpoint | Why it has no caller |
|---|---|
| `GET profile` | the SPA reads the whole profile from `GET me` |
| `GET treasury/transfer/form` | the transfer form is built from the shared lookups |
| `GET chat-polls/{poll}` | poll state arrives inside the chat feed payload |
