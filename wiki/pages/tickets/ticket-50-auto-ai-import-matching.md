---
created: 2026-11-13
type: ticket
status: implemented
summary: T50 — Purchase import review keeps AI product matching user-triggered via Suggest and explains it in the UI
---

# T50 — Explicit AI matching on purchase import review

Source request: the review showed dropdowns for unresolved products instead of
matching them, because AI suggestions only ran when the user pressed a separate
**AI suggest** button that was easy to miss.

## Spec

- The review never calls the LLM on open; the user triggers matching with
  **Suggest**.
- A visible hint explains that **Suggest** maps products and categories
  automatically.
- **Suggest** uses AI when an active (or single configured) profile exists and the
  deterministic matcher otherwise.
- Rows already matched by exact/barcode/alias suggestions stay authoritative even
  when the user runs suggestions; AI only fills unresolved or fuzzy rows.
- A missing/unavailable LLM profile must not block review: deterministic
  suggestions stay visible and failures do not break the screen.
- Rows with no matched product default to **Create new product** with the raw
  uploaded name prefilled.

## Plan

1. `src/components/imports/ImportReview.vue` — drop the automatic `suggestImportAi`
   call on mount, keep a single **Suggest** button that picks AI vs deterministic
   from `hasLlmProfile`, and show an explanatory hint above the sections.
2. `PurchaseImportAiService` — keep exact/barcode/alias product matches
   authoritative and let AI fill only unresolved or fuzzy rows.
3. Tests: assert nothing runs on mount, the hint is shown, and clicking Suggest
   hits the AI or deterministic endpoint as appropriate; PHP unit test covers the
   deterministic-match guard.
4. Remove the auto-run-only state (`autoSuggesting`, `needsAiSuggestions`,
   `isReviewable`) and the inline "Matching products with AI…" status.
5. l10n: replace the auto-run status string with the new hint for en/de/uk.

## Outcome

- Matching is now fully user-triggered; the hint tells users to press **Suggest**.
- No request races the user on open, so deterministic matches and in-progress edits
  are not overwritten.
- Vitest 264/264 green; eslint and stylelint clean.
