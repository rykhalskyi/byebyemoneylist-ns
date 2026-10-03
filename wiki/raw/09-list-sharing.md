## List Sharing Idea

Sharing to other users. Phase 1 is sharing a single list between two users.
Phase 2 generalizes it to family groups.

### Phase 1: Single-list sharing

The sharing unit is the **shopping list**, but a shared list carries the owner's
catalog with it: the owner's products, categories and stores become visible to
the guest. Scope is therefore bigger than "one list" — it is effectively a
per-owner data grant.

#### States

1. `NO_SHARING` — List is not shared.
2. `SHARED_READONLY` — Guest can see the list and the owner's
   products/categories/stores, and can copy them into their own DB. Guest cannot
   edit the originals and cannot use the owner's items directly; they are only
   shown. The list is clearly marked as belonging to another user.
3. `SHARED_READWRITE` — Guest can use the list and its products/categories/
   stores as their own: purchase in the list, add items, change category. Guest
   cannot edit or delete the owner's products/categories/stores. If the guest
   adds one of their own products/categories/stores to the shared list, confirm
   first, because it republishes that item to the list owner.

#### Visibility

- A guest sees shared lists alongside their own, marked as another user's list.
- Owner sees guest-added items.
- In the guest's catalog, own items appear in their own expander; shared items
  of each sharing user appear in separate expanders.
- In read/write, adding the guest's own item to the owner's list publishes that
  item into the owner's catalog (marked as shared). Ownership stays with the
  guest; if the guest later edits or deletes it, the change is reflected in the
  owner's catalog.

#### Rules

- During recognition and purchase, own items have priority. Newly recognized
  items are added to the guest's own catalog.
- The list is counted toward the expenses and analytics of the user who made the
  purchase, not the list owner.
- Revoking a share keeps the list as a name-only, greyed-out placeholder; no list
  data is retained.

### Phase 2: Family Groups

- Create a group that shares lists with all group members.
- Same rules as Phase 1, but with many members.
