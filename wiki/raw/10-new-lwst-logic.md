### New List Logic

The changes in new List logic 

- The List in 'New' state can contain "Placeholders" of real product.
- User Just can add "Milk", "Bread", "Cheese". They are just strings. no item from the db.
- After Purchase LLM matches these Items with real purchased products and replace to purchased. If somethis is missed it is hown as not bought.

- User can just scna a list written on paper and create new list fith placeholders from it.
- But of course user can add specific product from catalog. this product will be tracked in purchased list. If insead of this conrete poduc was bought analog it must be some how marked.
- if user manually add list without any receipt scan, all items, "paceholders" or from catalog just stays there. 

### Hypothesis 2

- Remove complenetly isNew state from Shopping list
- Remove inStore and checkbox logic.
- Remove ordinary recurring list
- User can Add Income and Subscription list. They can be reccuring.
- Purchase Adds allways new list in Purchased state.
- Income and Subscription also are treated as purchased.


**New list logic transforms into needToBuy logic.**

- needToBuy list cannot be purchased or in inStore mode.
- needToBuy list contains just strings no items from catalog. treat them as "placeholders"
- need to buy list has no store or category
- needToBuy list is just representation of handwritten list note what to buy.
- user can manually mark items from needToBuy list as purchased. no price or store
- Feature: logic checks all purchased list after needToBuy list, tries to match actuallye purchased items with it and marks goods in needToBuy list as purchased.
- new widget on dashboard can add needTobuy list
- it can be only one active needTobuy list. if user creates new and there's still one active with not purchased items, these items can be moved to the new one or reset.

- unactive need to buy list can be shown in general shoppinglist view. they have own cart style for active and unactive one 

- copy list feature can be applied for ntb lists. there no need to copy purchased lists.

needToBuy list is work name. maybe them can be called in other way
