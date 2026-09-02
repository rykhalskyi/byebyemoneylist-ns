### The problem: Category, Store, Products and Shopping Lists synchronization between android client and nextcloud app.

I want to have a possibility of synchronization of my data between android client and my NextCloud app (NCA).
The problem is every client and every NCA has own ids for entities. I want to find a way to synchronize them.

Phase 1. Client -> Server. I add data from clients to server.
Phase 2. Server -> Client. Client saves new data from the server.

### Simple approach for Category mapping:

1. **Keep client/server IDs separate.** Create a mapping between them.
2. **Compare categories using their full path**, not just the name:

   * `Food > Bread`
   * `Food > Bakery > Bread`
3. **First use simple matching + embeddings** to find likely candidates.
4. **Use an LLM only for ambiguous cases** to decide whether categories are equivalent.
5. Allow different relationships:

   * `EQUIVALENT`
   * `BROADER`
   * `NARROWER`
   * `NO_MATCH`
6. **Use the hierarchy as a constraint** to improve matching.
7. Store the result with a **confidence score** and send uncertain matches for human review.
8. If you have multiple systems, consider a **canonical taxonomy**:

```text
        Canonical
       /    |     \
   Client Server  Other
```

**In short:** don't let the LLM merge the trees directly. Use it as a semantic matcher inside a controlled mapping process.

Yes. That's a perfectly reasonable architecture, especially if you already have the LLM on the client.

### Option: client does the matching

```text
CLIENT                              SERVER
  │                                    │
  │──── request server categories ────►│
  │◄──── server category tree ─────────│
  │                                    │
  │  LLM + matching                    │
  │  Client tree ↔ Server tree         │
  │                                    │
  │──── mappings / matched data ──────►│
  │                                    │
```

For example, the client sends:

```text
client_id: 123
server_id: 987
relation: EQUIVALENT
confidence: 0.96
```

The server then stores:

```text
client_category_id = 123
server_category_id = 987
canonical_id = ...
```

### This has some advantages

* No LLM infrastructure needed on the server.
* Client controls the matching process.
* Easy to add initially.
* The client already has the context needed to understand its own categories.

### But there is one important issue

**Don't let the client be the ultimate authority over canonical IDs.**

If you have multiple clients:

```text
Client A ──┐
Client B ──┼──► Server
Client C ──┘
```

they could produce different interpretations:

```text
Client A: "Wash" → Car Wash
Client B: "Wash" → Laundry
```

So I'd separate **matching** from **identity**.

The client can say:

```json
{
  "client_category_id": "123",
  "server_category_id": "987",
  "relation": "EQUIVALENT",
  "confidence": 0.96
}
```

The server decides whether to **accept and persist that mapping**.

### A good architecture for your current situation

Since you already have LLM on the client, I'd start with:

```text
                 SERVER (NextCloud App)
                   │
            category database
                   │
                   │ categories
                   ▼
                 CLIENT
                   │
             LLM matching
                   │
                   │ proposed mappings
                   ▼
                 SERVER
                   │
          validate + persist
                   │
                   ▼
              synchronization
```

Later, if you add LLM capability to the server, you can move the matching there **without changing the fundamental data model**.

So yes: **client-side matching is a good option for your situation**, provided the server treats the client's matches as *proposals* and remains the authoritative owner of the resulting mappings.

**Validate and Persist** stage can be supervised by user. User approves it.


The same technique can be used for Stores, products and product lists.

What if:
- if products have the same not-null barcode value - it's perfect match.
- in the list or product to save clients ids in similar way as in aliases. Each product on NCA will have mapping ids of clients products. and then client can understand if this product is in its catalog. 
- after synchronization when some cases are not clear mark products, shops or categories like imported and demand user's review. User cab edit product, category or store.
- add merge function for category, store, product. User can merge duplicates in one item. At merge user can edit item. For product it is possible to see all aliases and client ids (if client ids are introduced)

## Phase One: NextCloud App API

NextCloud App Repo: ~/Source/byebyemoneylist-ns/
Client Repo: ~/Source/byebyemoneylist/

1. Develop API methods:

  Methods for client to request NextCLoud App data

  - get categories
  - get stores
  - get products
  - get lists

  Methods to send clients data to NextCloud App

  - set categories
  - set stores
  - set products
  - set lists

  All APIs must require authorization

2. Client changes 
  
  - Add clientId UUID to the client. Is is constant for every client.
  - I want to have a feature flag on client to swith this feature on/off.
  - store feature flag in /home/admin/Source/byebyemoneylist/local.properties
  - feature flag unhides the menu in client settings (make new screen, the same like for LLM connection) /home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/settings/SettingsScreen.kt
  - on this screen user can input nextcloud credentials.

### Phase One A: Category Synchronization (Multi-Language & Client-Driven)

The first phase focuses on Category synchronization across devices and languages.

#### Architectural Principles:
1. **Client Priority**: The Android Client is the primary app (used more often and has richer custom categories).
2. **Multi-Language LLM Matching**: Client LLM handles semantic cross-language matching (e.g. `Groceries` = `Lebensmittel` = `Продукти`).
3. **Canonical Server ID**: Nextcloud App assigns and owns UUIDs (`id`). Android client adds a `serverId: String?` column in Room `categories` table.
4. **Interactive Review**: Client displays proposed translations/matches, allowing the user to approve, merge, or create categories manually before committing.

#### API Endpoints Implemented on Nextcloud App (`byebyemoneylist-ns`):
- `GET /api/categories` -> Returns all user categories.
- `POST /api/categories` -> Creates a single category.
- `POST /api/categories/batch` -> Creates multiple categories in a single transaction. Accepts `list<array{name: string, color?: ?string, emoji?: ?string, parentId?: ?string, income?: bool, tempId?: ?string}>` and returns created objects with server UUIDs and optional `tempId` mapping.

#### Next Steps for Android Client (`byebyemoneylist`):
1. Enable feature flag `NEXTCLOUD_SYNC_ENABLED=true` in `local.properties`.
2. Add Room migration (v23 -> v24) adding `serverId` to `CategoryEntity`.
3. Create `NextcloudApiClient` (Basic Auth against Nextcloud REST API).
4. Create `MultiLanguageCategoryMatcher` (Exact match + LLM semantic translation match).
5. Create Category Sync Review UI in Settings to inspect and approve sync actions.

