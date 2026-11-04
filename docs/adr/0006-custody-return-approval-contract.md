# ADR 0006 — Custody return-approval contract and unused-endpoint decisions

Date: 2026-11-03
Status: accepted
Supersedes: none (resolves Task4 items T4-03 and T4-04.3)

## Context

Task4's route/field comparison found API endpoints that no screen reaches:

1. `POST custodies/{id}/approve-return` and `POST custodies/{id}/direct-return`.
   The old Blade UI defines an `#approveReturnModal` posting to
   `custodies.approveReturn` (treasury picker for a custody's pending-return
   amount), but **no button anywhere opens that modal** — it is dead markup.
   The live Blade flow (and the Angular SPA) approve pending returns per
   request via `custody-return-requests/{id}/approve[-with-treasury]`.
2. Lazy expense-category endpoints (`expense-categories/roots|children|ancestors`,
   `expense-categories/{id}`) are unused: the directions page loads the full
   tree in one call.

## Decision

1. **Delete the two dead custody routes from `routes/api_v1.php`** so the
   JSON contract matches what a screen can actually do — a custody with a
   pending return is resolved through the per-return-request approval flow
   (`custody-return-requests/*`), which the SPA implements for both approval
   variants. The `routes/web.php` names stay: the legacy Blade view builds
   the dead modal's form action with `route('custodies.approveReturn')` at
   render time, and removing the name would 500 the whole custody page for a
   modal nothing opens. The controller methods remain so a rollback (or a
   future batch "approve whole pending return" action) is a one-line re-add.
2. **Keep the lazy expense-category endpoints** and this decision documented:
   they are cheap read-only helpers that avoid shipping the whole tree to
   pick one node, a likely need for a future category picker. The SPA using
   the full tree today is fine at current data volumes.

## Consequences

- The API contract matches what a screen can actually do — the
  unused-endpoint list from the Task4 review is now empty-or-documented.
- Removing the routes does not migrate data; nothing referenced them.
- If the batch approval is ever wanted, restore the routes and build the
  treasury-picker dialog against them.
