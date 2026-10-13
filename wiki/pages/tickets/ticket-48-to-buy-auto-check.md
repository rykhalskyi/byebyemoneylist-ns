---
created: 2026-10-13
type: ticket
status: proposed
summary: T48 — Server-side LLM auto-check of pending To Buy items after a purchase
---

# T48 — LLM auto-check after purchase

Backend for the [To Buy list redesign](../specs/to-buy-list-redesign.md)
([plan](../plans/to-buy-list-redesign.md)). Needs [T43](ticket-43-to-buy-api.md) and an
active LLM profile.

## Spec

After a purchase is finalized, if an active LLM profile is configured, match the
purchased item names against the pending items of the active To Buy list and check the
ones the model is confident about. Silent, best-effort, never blocks the purchase
(R9; D-31 keeps the call server-side).

## Plan

1. `lib/Service/ToBuy/ToBuyAutoMatchService.php` (new) — gate on the active profile
   (`DefaultLlmProfileService`); prompt for `{"matchedIds":[...]}` across
   languages/synonyms; accept only supplied pending ids; never throw; re-verify the list
   and unchecked state before writing.
2. Hook: `lib/Controller/ListController.php` (when `isFinished` becomes true) and
   `lib/Service/Receipt/ReceiptCommitService.php` — resolve purchased names (receipt
   items, else the finished list's checked items), then check matches.
3. `tests/unit/Service/ToBuy/ToBuyAutoMatchServiceTest.php` (new) — matching, filtering,
   active-profile gate, failure/CancellationException handling.

## Outcome

Not started.
