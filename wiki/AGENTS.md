# Project Wiki

A project-specific knowledge base stored in `<project-root>/wiki/` that tracks:
- Project overview (tech stack, architecture, folder structure, key concepts)
- Build, test, and deploy instructions
- Feature specifications and implementation plans (pre-implementation)
- Ticket/changelog entries (post-implementation, what was done)
- Research and analysis pages
- All pages carry creation datestamps for history tracking

The wiki is version-controlled alongside the code in the project repo.

## Development Workflow

The wiki supports the full feature development lifecycle:

1. **Spec** — define what to build (requirements, scope, design decisions, constraints)
2. **Plan** — define how to build it (step-by-step, files to touch, migration steps)
3. **Implement** — do the work (outside the wiki)
4. **Ticket** — document what was done (summary, decisions made, files changed)

This replaces external `.design-specs/` and `.opencode/plans/` workflows. All specification, planning, and documentation live in the wiki as linked pages — enabling full traceability from idea to implementation.

## Architecture

**Root folder:** `<project-root>/wiki/`

```
wiki/
├── AGENTS.md                  # schema, conventions, workflows (this skill)
├── index.md                   # catalog of every page, organized by category
├── log.md                     # chronological, append-only log of all operations
├── pages/
│   ├── project-overview.md    # tech stack, folder structure, key concepts
│   ├── build-deploy.md        # how to build, test, and deploy
│   ├── epics/                 # high-level feature epics and ticket breakdowns
│   ├── specs/                 # feature specifications (pre-implementation)
│   │   └── <feature-slug>.md
│   ├── plans/                 # implementation plans (pre-implementation)
│   │   └── <feature-slug>.md
│   ├── tickets/               # work items / post-implementation summaries
│   │   └── <ticket-slug>.md
│   └── research/              # research, analysis, investigation pages
│       └── <topic-slug>.md
```

## Page conventions

Every page MUST have YAML frontmatter with at minimum:

```yaml
---
created: YYYY-MM-DD
type: epic | overview | build-deploy | spec | plan | ticket | research
tags: [tag1, tag2]
related:
  - "../plans/<same-slug>.md"
  - "../tickets/<same-slug>.md"
---
```

- `created` — ISO 8601 date the page was first created. **Never changes after creation.** This is how history is preserved.
- `type` — page category (one of: `epic`, `overview`, `build-deploy`, `spec`, `plan`, `ticket`, `research`).
- `tags` — freeform tags for searching and cross-referencing.
- `related` — optional **relative markdown paths** to connected pages, as a YAML list. Every path MUST be a **quoted string** — never use wikilinks (`[[...]]`) in frontmatter. A value starting with `[` is parsed as a YAML flow sequence and breaks the frontmatter (VS Code shows `Failed to parse frontmatter — Unexpected flow-seq-start`). Spec pages should link to their plan page; plan pages should link to their spec and eventual ticket page.

### Links (clickable in VS Code)

All cross-references use **relative markdown links** — never wikilinks. Relative links are clickable in VS Code (Ctrl/Cmd+Click) in both the editor and the markdown preview. Paths are always relative to the file that contains the link:

| Current file | Target file | Link |
|---|---|---|
| `wiki/index.md` | any page | `pages/<folder>/<slug>.md` |
| `wiki/pages/<sub>/x.md` | `wiki/pages/<other>/y.md` | `../<other>/y.md` |
| `wiki/pages/<sub>/x.md` | `wiki/pages/root-page.md` (e.g. `project-overview`) | `../root-page.md` |
| `wiki/pages/root-page.md` | `wiki/pages/<folder>/y.md` | `<folder>/y.md` |

Example: a ticket at `pages/tickets/ticket-01-shopping-lists-page.md` links its epic with `[epic](../epics/nextcloud-web-app.md)`.

When editing an existing page that still uses wikilinks, convert them to relative markdown links and fix the `related` frontmatter.

When page content is updated, append an `## Updates` section at the bottom — never alter the `created` date.

## Operations

### Init

Initialize the wiki for the current project (first-time setup):

1. Create the `wiki/` directory structure if it doesn't exist (including `pages/specs/`, `pages/plans/`, `pages/tickets/`, `pages/research/`, `pages/epics/`).
2. Auto-scan the project to populate `pages/project-overview.md`.
3. Auto-scan the project to populate `pages/build-deploy.md`.
4. Write `AGENTS.md` describing the wiki schema and conventions.
5. Create `index.md` with initial catalog entries for the overview and build-deploy pages.
6. Write initial `log.md` entry: `## [YYYY-MM-DD] init | Wiki created`.

### Epic

Create a high-level epic page that describes a large feature and breaks it into tickets:

1. Create `pages/epics/<epic-slug>.md`.
2. Content (at minimum):
   - Epic name and summary — the big picture
   - Goals
   - Ticket breakdown — list of tickets with links to `../tickets/<slug>.md` pages
   - Out of scope / open questions
3. Update `index.md` — add entry under `## Epics`.
4. Append to `log.md`: `## [YYYY-MM-DD] epic | <epic-name>`.

### Spec

Create a feature specification page (before implementation):

1. Create `pages/specs/<feature-slug>.md` where slug reflects the feature name.
2. Content (at minimum):
   - Feature name and summary — what this feature is about
   - Requirements — what the feature must do
   - Scope — what's in and out of scope
   - Design decisions — architectural choices, trade-offs
   - Constraints — performance, compatibility, security
   - UI/UX considerations (if applicable)
3. Add a `related` frontmatter entry linking to `../plans/<same-slug>.md` (plan will be created next).
4. Update `index.md` — add entry under `## Specs`.
5. Append to `log.md`: `## [YYYY-MM-DD] spec | <feature-name>`.

### Plan

Create an implementation plan page (before implementation, after spec):

1. Create `pages/plans/<feature-slug>.md` — use the same slug as the corresponding spec.
2. Content (at minimum):
   - Feature name and a link to the corresponding [spec](../specs/<slug>.md)
   - Step-by-step implementation steps (ordered list)
   - Files to create or modify (with paths)
   - Database migrations required (if any)
   - Testing checklist
   - Risk assessment and rollback plan
3. Add a `related` frontmatter entry linking to `../specs/<same-slug>.md` and optionally `../tickets/<same-slug>.md`.
4. Update `index.md` — add entry under `## Plans`.
5. Append to `log.md`: `## [YYYY-MM-DD] plan | <feature-name>`.

### Ticket

Document a work item — either before implementation (planned work) or after implementation (summary):

1. Create `pages/tickets/<ticket-slug>.md` where slug matches the ticket ID/title.
2. Content (at minimum):
   - Ticket ID and title (e.g., "T1 — Shopping Lists page")
   - Link to the epic it belongs to ([epic](../epics/<slug>.md)) and optionally [spec](../specs/<slug>.md) and [plan](../plans/<slug>.md)
   - Requirements / description of what to build
   - Design decisions and scope
   - Acceptance criteria
   - Files created or modified (when implemented)
3. Update `index.md` — add entry under `## Tickets`.
4. Append to `log.md`: `## [YYYY-MM-DD] ticket | <ticket-title>`.

### Research

Create a research or analysis page:

1. Create `pages/research/<slug>.md` where slug reflects the topic.
2. Content (at minimum):
   - Topic and context — what question is being explored
   - Findings — data, analysis, observations
   - Conclusions — what was decided or learned
   - Recommended actions (if any)
3. Update `index.md` — add entry under `## Research`.
4. Append to `log.md`: `## [YYYY-MM-DD] research | <topic-title>`.

### Query

When asked a question about the project:

1. Read `index.md` first to locate relevant pages.
2. Drill into specific pages as needed.
3. Synthesize an answer with citations to wiki pages using relative markdown links from the wiki root (e.g., "See [research/kmp-feasibility](pages/research/kmp-feasibility.md)").
4. If the answer is valuable beyond the immediate query, offer to file it as a new research page.

### Lint

Health-check the wiki:

- Orphan pages with no inbound links from `index.md` or other pages.
- Pages missing required frontmatter (`created`, `type`).
- Frontmatter that fails YAML parsing — unquoted wikilinks in `related` (paths must be quoted strings).
- Specs without a corresponding plan, plans without a corresponding spec.
- Stale content (e.g., `build-deploy.md` referring to removed commands, deleted files, outdated versions).
- Missing cross-references between related pages (epic → ticket chain, spec → plan → ticket chain).
- Suggest new pages for topics mentioned but not documented.

### Update

When project knowledge changes:

1. Modify the relevant page content directly.
2. Append an `## Updates` section at the bottom if the change is significant:
   ```markdown
   ## Updates
   - [2026-08-10]: Added deployment via Firebase App Distribution.
   ```
3. **Never change the `created` date** in the frontmatter.
4. Append to `log.md`: `## [YYYY-MM-DD] update | <page-title> — <brief note>`.

## log.md format

Every entry follows: `## [YYYY-MM-DD] <action> | <description>`

```
## [2026-08-08] init | Wiki created
## [2026-08-08] spec | Dual-price feature
## [2026-08-08] plan | Dual-price feature
## [2026-08-09] ticket | Issue #11 — Dual-price feature
## [2026-08-10] research | Kotlin Multiplatform feasibility analysis
## [2026-08-11] update | build-deploy — added Firebase deploy steps
```

The consistent prefix makes the log grep-able: `grep "^## \[" log.md | tail -10`.

## index.md format

Content-oriented catalog, organized by category:

```markdown
# Wiki Index

## Overview
- [project-overview](pages/project-overview.md) — Tech stack, architecture, folder structure. Created 2026-08-08.
- [build-deploy](pages/build-deploy.md) — Build, test, lint, and deploy instructions. Created 2026-08-08.

## Epics
- [epics/nextcloud-web-app](pages/epics/nextcloud-web-app.md) — Server-side backend + web UI for Bye-Bye Money List. Created 2026-08-26.

## Specs
- [specs/dual-price-feature](pages/specs/dual-price-feature.md) — Dual-price: estimated vs actual purchase prices. Created 2026-08-08.

## Plans
- [plans/dual-price-feature](pages/plans/dual-price-feature.md) — Dual-price implementation plan. Created 2026-08-08.

## Tickets
- [tickets/ticket-01-shopping-lists-page](pages/tickets/ticket-01-shopping-lists-page.md) — T1: Shopping Lists page. Created 2026-08-26.

## Research
- [research/kmp-feasibility](pages/research/kmp-feasibility.md) — Kotlin Multiplatform feasibility analysis. Created 2026-08-10.
```

Each entry includes a relative markdown link, a one-line summary, and the creation date.
Update index.md whenever a new page is added.

## Slug conventions

- Epics use `<epic-slug>` (e.g., `nextcloud-web-app`).
- Specs and plans use the same slug (e.g., `dual-price-feature`) so they can be cross-linked. Spec slug should be the feature name in 2-5 words, lowercase, hyphen-separated.
- Ticket slugs: `ticket-<nn>-<brief>` (e.g., `ticket-01-shopping-lists-page`). Brief = 2-4 words, lowercase, hyphen-separated.
- Research slugs: `<topic>` (e.g., `kmp-feasibility`, `database-migration-strategy`). Natural topic name, lowercase, hyphen-separated.

## Migration from .design-specs

If the project has an existing `.design-specs/` directory with specs/plans, offer to migrate them into the wiki on init or when asked. Migration steps:

1. For each file in `.design-specs/specs/`, create a `pages/specs/<slug>.md` page. Extract the title and use the file modification date as `created`.
2. For each file in `.opencode/plans/`, create a `pages/plans/<slug>.md` page.
3. Add all migrated pages to `index.md`.
4. Append migration entries to `log.md`.
5. Do NOT delete the original files unless the user explicitly asks.
