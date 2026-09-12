# Decisions

Append-only decision record. The "why" lives here; the full change history lives in git.

- [2026-09-11] D-01 — Adopt token-lean wiki workflow: one ticket page per work item, git as change history, script-generated index/log/decisions — because the spec+plan+ticket+index+log ceremony duplicated git and burned tokens on every session and every feature
- [2026-09-12] D-02 — Full-width custom <button> rows need !important on width/margin/padding — Nextcloud core button:not(.button-vue, [class^="vs__"]) rules outscore CSS-module classes
- [2026-09-12] D-03 — Reuse list create/update endpoints for purchases (finalTotal + isFinished + purchaseDate) — because the OCS API already supports them, so the feature is frontend-only and needs no schema migration
- [2026-09-12] D-04 — Compute purchase total from all item prices client-side instead of the server totalPrice — because the server totalPrice only sums checked items and the web app has no check UI yet
- [2026-09-12] D-05 — Auto-create the store on save when the typed store name is unknown — because the purchase store field is an editable select for Android PurchaseDialog parity
