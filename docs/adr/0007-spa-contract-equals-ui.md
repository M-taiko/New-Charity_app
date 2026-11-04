# ADR 0007 — The JSON API contract equals what the SPA can do

Date: 2026-11-04
Status: accepted
Supersedes: ADR 0006 decision 2 (which kept the lazy expense-category reads)

Resolves: Task5 review item T5-08

## Context

The Task5 route/endpoint matcher (168 `api_v1` routes vs SPA calls) found
endpoints no screen reaches. After clearing the false positives
(`GET user-custodies`, `POST expense-categories` and its update/delete — all
called through dynamic path building — and `GET directional-stats`, used by
the reports dashboard), two real leftovers remained:

1. The lazy expense-category reads: `GET expense-categories/roots`,
   `GET expense-categories/{id}/children`, `GET expense-categories/{id}/items`,
   `GET expense-categories/{id}/ancestors` and `GET expense-categories/{id}`
   (`categoryShow`). The SPA loads the whole tree in one call
   (`GET /expense-items` for management, `direction_tree` from `GET /lookups`
   for pickers) and never fetches a node lazily.
2. `POST suppliers/{id}/update`. The SPA suppliers page can add and delete
   but has no supplier-edit dialog, so the route is unreachable.

ADR 0006 chose to keep the lazy reads as "cheap future helpers". A second
review round disagreed: an endpoint nothing calls is contract surface that
must be authorized, tested, documented and kept in sync — for a need that
has not materialized in two review cycles.

## Decision

Delete the unused routes from `routes/api_v1.php` so the JSON contract
equals the UI:

- `GET expense-categories/roots|{id}/children|{id}/items|{id}/ancestors|{id}`
- `POST suppliers/{id}/update`

Also removed the now-orphaned `Api\V1\ExpenseItemController::categoryShow`
(defined only for the deleted show route).

The shared controller methods behind the other removed routes stay, because
the Blade app still routes them: `routes/web.php` keeps
`/api/expense-categories/*` (named, used by the legacy directions page) and
the `suppliers` resource (`only index, store, update, destroy`).

## Consequences

- Every route in `routes/api_v1.php` is called by the Angular SPA; the
  Task5 unused-endpoint list is now empty.
- If a lazy category picker (or a supplier-edit dialog) is ever wanted,
  re-add the route — the controller methods are one `Route::` line away,
  or a fresh V1 action next to `index`.
- The Blade UI is unaffected: its routes live in `routes/web.php` and were
  not touched.
