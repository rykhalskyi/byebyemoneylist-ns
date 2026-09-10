---
created: 2026-09-10
type: ticket
tags: [ticket, ui, catalog, products, pictures, appdata, backend, frontend]
related:
  - "../epics/nextcloud-web-app.md"
  - "../tickets/ticket-12-product-last-price-info-dialog.md"
---

# T13 — Products: image upload, deletion and display

Part of [epic](../epics/nextcloud-web-app.md). Source: GitHub issue #24, extended
per request to also allow uploading from the product create/edit dialog.

## Summary

Each product can now store one image. Images are uploaded through a new
owner-scoped OCS API, stored in the app's private appdata, and served back as a
base64 data URL for the product info dialog and the create/edit dialog. The
`bbml_products.picture_path` column and `ProductEntity::getPicturePath()` already
existed.

## API design

```
POST   /api/products/{id}/picture   (multipart "picture"; MIME + 4 MB validated → data URL)
DELETE /api/products/{id}/picture   (remove appdata file, clear picture_path)
GET    /api/products/{id}/picture   ({ picture: { dataUrl, mime } | null })
```

All owner-scoped; 401 unauthenticated, 404 foreign/missing product, 422 missing /
oversized / unsupported file, 500 storage failure.

## Description

### Backend

- `ProductPictureService` (new): stores/reads/deletes files under
  `appdata_<instance>/byebyemoneylist/product-pictures/<owner>/<productId>.<ext>`
  using `IAppDataFactory`; `picture_path` stores the appdata-relative path.
- `ProductImageController` (new): `upload`/`destroy`/`show`; validates the real
  detected MIME (`image/jpeg|png|webp|gif`) and a 4 MB cap; replaces the file and
  its extension on re-upload.
- `ProductController`: injects `ProductPictureService`, serializes
  `hasPicture: bool`, and deletes the picture file when a product is deleted.
- `ProductImageControllerTest` (new) + `ProductControllerTest` mock update;
  `openapi.json` regenerated (16 routes).

### Frontend

- `types.ts`: `Product.hasPicture`; new `ProductPicture` type.
- `listsApi.ts`: `uploadProductPicture` (multipart), `deleteProductPicture`,
  `fetchProductPicture`.
- `ProductInfoDialog.vue`: shows the image plus Upload/Replace and Delete
  (Delete reuses the T10 `ConfirmDialog`); emits `updated` so the list refreshes.
- `NewProductDialog.vue`: picture preview + Upload/Replace/Remove; uploads after
  create/update. A newly created product is rolled back if its image upload fails.

## Design decisions

- **Private appdata storage**, not user Files: images are not visible in the Files
  app and are only reachable through the API / web UI.
- Only the appdata-relative path is persisted, so no absolute host paths leak.
- Server sniffs the real MIME from file content (`IMimeTypeDetector`) rather than
  trusting the client-provided type.
- **Deviation from the ticket:** the create/edit-dialog upload was added on request
  (originally listed out of scope).

## Scope

- **Out:** capture/scanning, server-side crop/resize, list thumbnails.
- **Extended in:** image upload in the product create/edit dialog.

## Acceptance criteria

- [x] A user can upload an image for a product from the info dialog; it displays immediately.
- [x] Re-uploading replaces the image; deleting removes it.
- [x] Only allowed image types within the size limit are accepted; others return 422.
- [x] A product's image file is removed when the product is deleted.
- [x] `hasPicture` is exposed on the product payload and drives the UI.
- [x] `composer run test:unit`, `composer run openapi`, `composer run psalm`,
  `npm run lint`, `npm run build` pass.
- [x] (Extension) Images can be uploaded/replaced/removed from the create/edit dialog.

## Files (changed)

- Backend: `lib/Service/ProductPictureService.php` (new),
  `lib/Controller/ProductImageController.php` (new),
  `lib/Controller/ProductController.php`,
  `tests/unit/Controller/ProductImageControllerTest.php` (new),
  `tests/unit/Controller/ProductControllerTest.php`, `openapi.json`
- Frontend: `src/types.ts`, `src/services/listsApi.ts`,
  `src/components/ProductInfoDialog.vue`, `src/components/NewProductDialog.vue`,
  `src/views/Catalog.vue`

## Status

**Implemented (2026-09-10).** `composer lint`, `cs:check`, `test:unit` (128 tests),
`psalm`, `openapi`; `npm run lint`, `stylelint`, `build` all pass.
