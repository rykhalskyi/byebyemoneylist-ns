## Shopping List Improvements

### List grouping 

- group lists by month and year. Make groups expandable. year -> month -> list
- calculate sum of lists in the group for month and year. take final price of isFinished lists. Show sum in month and year groups on the right.

### New purchase

- Add new Action button Add Purchase next to "Add List"
- The "Add Purchase" button opens "Add Purchase" dialog
- The dialog contains:
  - Listname editable select. User can choose among isNew lists or enter name of new not existed yet list.
  - two tabs "Manual input" and "Scan receipt"
  - Scan receipt is placeholder. does yet nothing
  - "Manual input" tab has:
    - Select "Store" to choose a Store
    - Total price input. for existing isNew list it is set with sum of price of its product.
    - Category selector. Only one Category can be assigned
- "Save" button to create purchase.

If Purchase is created for existing isNew list, the final price of this list is set and list moved to isFinished.

If user creates new list, this list is created empty without items but with price.

**Reference** /home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/product/PurchaseDialog.kt

### New Income List

- Add new "Add Income" button next to "Add List" and "Purchase"
- the "Add Income" adds new Income List.
- change icon for isSubscription and is Income lists.
- change "Add Product" button header for is Income and is Subscription lists to "Add subscription" and "Add Income Source  "

### New List dialog

- add isReccuring and is Subscription toggles. It sets this booleand for the list.

### Sorting, filtering and search
- I want to have possibility to search through the all list. some search input must be implemented
- Search in Shopping list names, stores
- Filter is the next option, user can filter lists by category and/or the list type: isNew, isFinished, isSubscription, isIncome, is Recurring
- Sort list by date, two modes: ascending and descending. default: newer on the top.
