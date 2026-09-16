## Scan receipt Epic

*This epic is about automated scan of receipts and creating products, stores and lists. For this purposes the 3rd part LLM API is used. User can create LLM Provider, configure access and send data to LLM for OCR*

### Setting and LLM providers

- Setting page contain LLM Profiles menu.
- User can create LLM Profile for different LLM
- For the begining I want to use DeepSeek and SiliconFLow providers. Then It will be expanded to other more popular: OpenAI, Gemini, Grok, Antropic
- User can enter Model name and own api key 
- Api key is stored in encrypted way and is visible masked. only the last 4 symbols

**example** /home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/settings/LlmSettingsScreen.kt

- But make no predefined profile.

### Shopping lists
- Add receipt filed to shopping list. I want to have a possibility to attach a photo of reciept to the list. it is optional field.

### Purchase Dialog
- In Scan receipt tab user can upload the receipt and send it to the LLM of choice for scan.
- It's nice to remove all EXIF metadata from image
- With the receipt image is sent a list of categories and top 5 stores.
- LLM must assign category to each scaned product and sec store to the list.
- A name of scanned product is actually it's alias. The same products can be scanned little bit in another way so user can manage them later.
- after successful scan LLM returns a well defined JSON file with results.
- Scanned products are searched by names and aliases.
- The scan result must be shown to user as a list for review and approve.
- very important to have summ of Purchase
- After user's confirmation the list is saved as isFinished list
- dialog has checked checkbox "Save receipt" to save also the source image.

**example of imlementation of LLM logic** /home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/data/LlmProfile.kt

/home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/scanner/DeepSeekScanner.kt

/home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/scanner/SiliconFlowScanner.kt

/home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/scanner/ReceiptReviewDialog.kt

/home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/scanner/LlmScannerConstants.kt

### Product Catalog
- Add Merge function to merge to products.
- Very often LLM created duplicates. The dialog help user to marge duplicates in one product
- User can choose name and other fileds, the aliases are concatenated to one list.

**Example:**
/home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/catalog/ProductMergeScreen.kt
