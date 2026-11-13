---
created: 2026-11-13
type: ticket
status: implemented
summary: T50 — Purchase import review runs AI product matching automatically and falls back to create-new
---

# T50 — Automatic AI matching on purchase import review

Source request: the review showed dropdowns for unresolved products instead of
matching them, because AI suggestions only ran when the user pressed **AI suggest**.

## Spec

- Opening a reviewable purchase import runs AI matching automatically when at least
  one non-coupon product row is unresolved or only fuzzy-matched.
- Rows already handled by AI are not sent again; exact/barcode/alias matches are
  kept as-is.
- A missing/unavailable LLM profile must not block review: deterministic
  suggestions stay visible and the auto-run failure is silent.
- Rows with no matched product default to **Create new product** with the raw
  uploaded name prefilled.

## Plan

1. `src/components/imports/ImportReview.vue` — add an auto-run guard based on
   `suggestion.source`/`productId`, call `suggestImportAi` after catalog options
   load, apply the returned import, and disable actions while matching.
2. Show a short "Matching products with AI…" status during the automatic run.
3. Tests: auto-runs for unresolved rows, skips when every row is AI-suggested, and
   keeps the raw name prefilled when no profile exists.
4. l10n: add the new status string for en/de/uk.

## Outcome

- Review opens trigger `/suggest-ai` once for unresolved/fuzzy rows; results are
  applied immediately and create-new stays the no-match default.
- Auto-run failures (including `409 No active LLM profile`) are swallowed; the
  explicit **AI suggest** button still reports the missing-profile hint.
- Vitest 262/262 green; eslint and stylelint clean.
