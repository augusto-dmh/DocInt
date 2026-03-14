# Phase 2 — Branching Strategy

## Branch Model

Phase 2 continues from `master` using one focused feature branch per PR.

- No integration branch
- No LocalStack or S3-emulation branch
- Each PR stays deployable and scoped to a single feature increment

## Branch Naming

Use conventional feature branch names tied to delivered behavior:

```text
feat/document-crud-backend
feat/document-vue-pages
feat/document-phase2-polish
```

## Phase 2 PR Order

1. `feat/document-crud-backend`
2. `feat/document-vue-pages`
3. `feat/document-phase2-polish`

## Dependency Rules

- PR 1 branches from `master`
- PR 2 branches from the merged PR 1 baseline
- PR 3 branches from the merged PR 2 baseline
- Do not recreate the superseded upstream LocalStack detour
- Keep Docker unchanged during this phase unless a later document workflow requirement makes a runtime fix necessary

## Merge Flow

```text
master
  ├── feat/document-crud-backend
  ├── feat/document-vue-pages
  └── feat/document-phase2-polish
```

## Quality Gate

Before merge, each PR must satisfy `PR_GUIDELINES.md` and `agent.md`:

- backend formatting
- frontend linting
- frontend formatting
- relevant backend tests
- frontend build
