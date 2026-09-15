# Decisions

Append-only decision record. The "why" lives here; the full change history lives in git.

- [2026-09-11] D-01 — Adopt token-lean wiki workflow: one ticket page per work item, git as change history, script-generated index/log/decisions — because the spec+plan+ticket+index+log ceremony duplicated git and burned tokens on every session and every feature
- [2026-09-12] D-02 — Full-width custom <button> rows need !important on width/margin/padding — Nextcloud core button:not(.button-vue, [class^="vs__"]) rules outscore CSS-module classes
- [2026-09-12] D-03 — Reuse list create/update endpoints for purchases (finalTotal + isFinished + purchaseDate) — because the OCS API already supports them, so the feature is frontend-only and needs no schema migration
- [2026-09-12] D-04 — Compute purchase total from all item prices client-side instead of the server totalPrice — because the server totalPrice only sums checked items and the web app has no check UI yet
- [2026-09-12] D-05 — Auto-create the store on save when the typed store name is unknown — because the purchase store field is an editable select for Android PurchaseDialog parity
- [2026-09-13] D-06 — Store the dashboard widget layout in localStorage instead of the server DB for now — because widgets are non-critical and easily re-created, and persistence lives behind a composable so it can move to the backend later
- [2026-09-13] D-07 — Compute all dashboard spending totals server-side via one range endpoint — because the client must not download all lists and paged history is planned
- [2026-09-13] D-08 — Dashboard spending sums finished lists by created_at, excluding isIncome and isSubscription — because that reflects actual expenses and keeps income/subscription views separate
- [2026-09-13] D-09 — Inject the app version through PageController + templates/index.php data attribute instead of a new endpoint — because info.xml stays the single source of truth with no extra API
- [2026-09-14] D-10 — Run receipt OCR server-side against the active LLM profile — because API keys are stored encrypted server-side and must never reach the browser
- [2026-09-14] D-11 — Strip receipt EXIF client-side via canvas downscale to 1024px/JPEG q0.6 — because it removes metadata and shrinks the upload, mirroring the Android scanner
- [2026-09-14] D-12 — Scan endpoint is read-only; a separate transactional commit endpoint writes store/list/product/item/price — because a partial save would corrupt the catalog
