# Wiki Schema

Full conventions for this wiki. [AGENTS.md](AGENTS.md) is the short, always-loaded
summary — read this file only when you need the details.

## Layout

```
wiki/
├── AGENTS.md      # short summary (auto-loaded on every session)
├── SCHEMA.md      # this file — full conventions
├── index.md       # generated catalog — never edit by hand
├── log.md         # append-only activity log
├── decisions.md   # append-only decision record (the "why")
└── pages/
    ├── project-overview.md   # tech stack, architecture, folder structure
    ├── build-deploy.md       # build, test, lint, deploy
    ├── debugging.md          # debugging the dev instance
    ├── epics/                # large features broken into tickets (optional)
    ├── tickets/              # default artifact for a work item
    ├── specs/                # only for large/ambiguous epics
    ├── plans/                # only for large/ambiguous epics
    └── research/             # investigations and analysis
```

## Token budget (read this first)

- **Git is the change history.** Do not duplicate it in pages: no `## Updates`
  sections, no "Created &lt;date&gt;" in index entries, no restating old content.
  Use `git log -p wiki/pages/<file>` to see how a page changed.
- [AGENTS.md](AGENTS.md) is auto-loaded on every session — keep it short; detail
  belongs here.
- Use `node scripts/wiki.mjs` for mechanical work instead of reading and rewriting
  `index.md` / `log.md` by hand.

## Frontmatter

Minimal — only what tooling or future readers need:

```yaml
---
created: YYYY-MM-DD
type: overview | build-deploy | epic | ticket | spec | plan | research
status: proposed | in-progress | implemented | superseded   # when meaningful
summary: One-line description used to build index.md
---
```

- `created` never changes after creation.
- `summary` must be present on new pages so `wiki.mjs index` can catalog the page
  without reading its body.
- Do **not** add `tags` or `related` — link in the body instead. `grep` covers
  search; body links cover relationships. Legacy pages may still carry them.

## Links

Relative markdown links, never wikilinks (`[[...]]`). Paths are relative to the
file that contains the link:

| From | To | Link |
|---|---|---|
| `wiki/index.md` | any page | `pages/<folder>/<slug>.md` |
| `pages/<sub>/x.md` | `pages/<other>/y.md` | `../<other>/y.md` |
| `pages/<sub>/x.md` | `pages/root-page.md` | `../root-page.md` |
| `pages/root-page.md` | `pages/<folder>/y.md` | `<folder>/y.md` |

## Slug conventions

- Tickets: `<ticket-id>-<brief>` (e.g. `ticket-15-localization-infra`).
  Brief = 2-4 words, lowercase, hyphen-separated.
- Specs and plans share one `<feature-slug>` so they cross-link.
- Research: `<topic>` (e.g. `syncronisation`, `database-migration-strategy`).

## Workflow

### Default: one page per work item

Create a single `pages/tickets/<slug>.md` and fill in as the work progresses:

```markdown
# T<n> — <Title>

Part of the [epic](../epics/<slug>.md).   # if applicable

## Spec
What and why: requirements, scope, design decisions, constraints.

## Plan
Ordered steps, files to create/modify, migrations, tests, risks.

## Outcome
What actually shipped, deviations from the plan, files changed.
```

Set `status:` in frontmatter accordingly (`in-progress` → `implemented`).

### Large epic: optional spec + plan pages

Only when work is genuinely large/ambiguous or one spec is shared by many
tickets, create `pages/specs/<slug>.md` and `pages/plans/<slug>.md` and link the
tickets to them. Do not create spec/plan pages by default.

### Research

Create `pages/research/<slug>.md` with findings, conclusions, and recommended
actions.

## Operations

### Activity log

One line per event, appended without reading the file:

```
node scripts/wiki.mjs log <action> "<description>"
```

Writes `## [YYYY-MM-DD] <action> | <description>`. Actions: `init`, `ticket`,
`spec`, `plan`, `research`, `update`, `decision`.

### Decisions

Every non-obvious choice gets a durable one-liner:

```
node scripts/wiki.mjs decision "<decision> — because <reason>"
```

Writes `- [YYYY-MM-DD] D-nn — <decision> — because <reason>`. Reference the id
(e.g. `D-07`) from the relevant ticket instead of restating the rationale.

### Regenerate index

```
node scripts/wiki.mjs index
```

Scans `pages/**`, reads frontmatter `summary` (falling back to a legacy index
summary or the H1), and rewrites `index.md`. Never edit `index.md` by hand.

### Lint

```
node scripts/wiki.mjs lint
```

Checks for missing frontmatter, dangling relative links, and pages missing from
the index.

### Query

Read `index.md` first, drill into relevant pages, and answer with relative links.
File valuable answers back as a research page.

### Batching

Do wiki writes in one pass at the end of a work session rather than interleaving
them with implementation — fewer context reloads.

## Migration from .design-specs

If the project has an existing `.design-specs/` directory with specs/plans, offer
to migrate them into the wiki on init or when asked:

1. For each `.design-specs/specs/` file, create `pages/specs/<slug>.md`; use the
   file modification date as `created`.
2. For each `.opencode/plans/` file, create `pages/plans/<slug>.md`.
3. Run `node scripts/wiki.mjs index`.
4. Append migration entries with `wiki.mjs log`.
5. Do not delete the originals unless the user explicitly asks.
