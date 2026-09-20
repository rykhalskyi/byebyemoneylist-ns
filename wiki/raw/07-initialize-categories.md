## Default category initalize.

As a user, I want to see a dialog: Do you want to create initial categories set if I nave no categories.

Dialog must be shown on the first screen that is opened in the app. Normally it is "Dashboard".

- User clicks on byebyemoneylist app and if there's no categories dialog is shown.
- "Yes" on the dialog leads to Catalog - Categories and creates Default category set.
- Categories support localizations and are created on three languages: en, de, uk. It depends on user nextcloud language. For other languages: en.

**Example on implementation:**  

createDefaultCategories(context: android.content.Context) in /home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/data/local/repository/CategoryRepository.kt
