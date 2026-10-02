---
created: 2026-10-02
type: ticket
status: proposed
summary: T39 — Family groups (Phase 2: share lists with all group members)
---

# T39 — Family groups (Phase 2)

Part of [epic](../epics/nextcloud-web-app.md) (T12). Design: [specs/list-sharing](../specs/list-sharing.md).

## Spec

Phase 2 generalizes Phase 1 sharing to many members.

- Create a group; share a list with the whole group; same read-only/read/write rules
  as [T35](ticket-35-sharing-foundation.md)–[T38](ticket-38-sharing-purchaser-attribution.md).
- Generalize `bbml_list_shares.shared_with` to a group reference (new group +
  membership tables, or Nextcloud groups) instead of a single uid.
- Group budget view (epic goal) becomes possible once attribution is per purchaser.

## Plan

1. Decide group source: app-managed `bbml_groups`/`bbml_group_members` vs Nextcloud
   group backend.
2. Migration + entities + mappers; extend `ListAccessService` with group membership.
3. Share-to-group API + UI.
4. Tests: multi-member access, per-member attribution.

## Outcome

Not started.
