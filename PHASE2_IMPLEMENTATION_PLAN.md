# Phase 2 — Implementation Plan

## Phase Goal

Deliver the tenant-scoped document workflow on top of the Phase 1 client and matter foundation:

- S3-backed uploads
- document authorization
- audit logging
- document CRUD pages
- matter-to-document navigation
- document activity visibility

This phase intentionally skips the obsolete LocalStack branch history and moves directly toward the rebuild target.

---

## PR 1: Document CRUD Backend

**Branch:** `feat/document-crud-backend`

### Scope

- add the Laravel S3 adapter dependencies needed for real S3-compatible storage
- extend `.env.example` with any missing S3 fields without changing the broader runtime defaults
- add `AuditLog` model, migration, relationships, and factory support
- add `DocumentPolicy`
- add `DocumentValidationRules`, `StoreDocumentRequest`, and `UpdateDocumentRequest`
- add `DocumentUploadService` for tenant-scoped upload, download, and delete behavior
- add `DocumentController` and `routes/documents.php`
- add minimal Inertia page shims for document index/create/show/edit routes
- add document CRUD, authorization, storage, audit, and tenancy coverage

### Verification

- authorized users can upload documents to S3 under tenant-scoped paths
- document downloads redirect through an authorized controller flow
- upload, view, download, and delete actions create audit entries
- unauthorized roles are denied for protected document actions
- cross-tenant document access fails cleanly

---

## PR 2: Document Vue Pages

**Branch:** `feat/document-vue-pages`

### Scope

- replace the minimal document shims with full document index/create/show/edit pages
- add matter-page entry points and shell navigation for documents
- use Wayfinder-backed route/action imports in document pages
- apply intentional frontend treatment using the frontend-design, Inertia, and Tailwind project conventions
- strengthen Inertia assertions for document and related matter flows

### Verification

- users can navigate into document flows from matters and the shared app shell
- document create, index, show, and edit pages render with production-ready frontend behavior
- frontend routing stays type-safe through generated Wayfinder helpers

---

## PR 3: Document Phase 2 Polish

**Branch:** `feat/document-phase2-polish`

### Scope

- add the document activity timeline on the detail surface
- seed more realistic document demo data and audit activity
- align shell, document, client, and matter surfaces to the same visual system
- add focused regression coverage for seeded document payloads, counts, and detail data

### Verification

- seeded demo tenants expose realistic document examples
- document detail shows richer metadata and recent activity
- shared workspace styling feels cohesive across document, client, and matter surfaces

## Phase Completion

After PR 3 merges:

1. the full Phase 2 document workflow is present on `master`
2. document upload, download, and audit behavior are covered by focused tests
3. planning can move to Phase 2.5 tenancy hardening without carrying forward the skipped LocalStack detour
