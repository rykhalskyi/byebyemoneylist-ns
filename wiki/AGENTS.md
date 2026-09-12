# Project Wiki

Knowledge base in `wiki/`, version-controlled with the code. **Git is the change
history** — pages hold current knowledge and decisions, not a duplicate changelog.

Details live in [SCHEMA.md](SCHEMA.md). This file is auto-loaded every session, so
keep it short; read SCHEMA.md only when you need the full conventions.

## Layout

`wiki/`: `AGENTS.md` (this), `SCHEMA.md` (full rules), `index.md` (generated),
`log.md` (activity), `decisions.md` (the "why"), `pages/` (overview, build-deploy,
debugging, tickets/, specs/, plans/, research/, epics/).

## Rules

- Default artifact for a work item is **one page**: `pages/tickets/<slug>.md` with
  `## Spec`, `## Plan`, `## Outcome` sections. Separate `specs/` + `plans/` pages
  only for large/ambiguous epics shared by many tickets.
- Frontmatter is minimal: `created`, `type`, `status`, `summary`. No `tags`/`related`.
- Every non-obvious decision gets a one-liner in `decisions.md`; full history is git.
- **No `## Updates` sections** — `git log -p` is the update history.
- Links are relative markdown, never wikilinks.
- Never hand-edit `index.md`; regenerate it.

## Helper

Use the script instead of reading/rewriting index and log by hand:

```
node scripts/wiki.mjs log <action> "<description>"
node scripts/wiki.mjs decision "<decision> — because <reason>"
node scripts/wiki.mjs index     # regenerate index.md
node scripts/wiki.mjs lint
```

Full schema: [SCHEMA.md](SCHEMA.md)
