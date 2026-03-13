# Security Remediation Plan

## Goal
Reduce the highest-risk security issues in the current multi-tenant Laravel/Vue application, starting with tenant isolation, permission enforcement, and secret handling.

## Current Risk Summary
- Overall application risk: High
- Primary concern: inconsistent authorization and tenant scoping
- Secondary concerns: secret exposure, weak OAuth/webhook verification, and sensitive logging

## Immediate Priorities

### 1. Fix tenant isolation everywhere
- Introduce company-scoped route model binding or policies for all tenant-owned models.
- Ensure every controller action that accepts route-bound models verifies `company_id` against the authenticated user's current company.
- Review all CRUD controllers for `Customer`, `Contact`, `Product`, `Quote`, `Invoice`, `Jobcard`, `CreditNote`, `Payment`, `TimeEntry`, `PurchaseOrder`, and PDF template access.

### 2. Correct permission middleware per action
- Stop applying `view` permission to entire resource routes.
- Split route protection by action:
- `index`, `show` -> `view`
- `create`, `store` -> `create`
- `edit`, `update`, status changes, email/send, convert actions -> `edit`
- `destroy` -> `delete`
- Review quote, invoice, credit note, jobcard, and time-entry routes first.

### 3. Scope validation rules to the current company
- Replace plain `exists:table,id` validation with company-scoped `Rule::exists(...)->where(...)`.
- Apply this to all foreign keys such as:
- `customer_id`
- `invoice_id`
- `jobcard_id`
- `product_id`
- `tax_rate_id`
- `account_id`
- `supplier_id`
- `serial_number_ids`
- Validate parent-child ownership as well, not just existence.

### 4. Lock down licensing and deployment actions
- Restrict all licensing deploy, upgrade, and force-SSL actions to administrators only.
- Add explicit permissions for deployment/infrastructure operations.
- Review whether non-admin users should see license keys or deployment controls at all.

## Short-Term Hardening

### 5. Stop exposing secrets to the frontend
- Do not send `client_secret`, `access_token`, or `refresh_token` to Inertia props.
- Return masked values or booleans such as `has_client_secret`, `has_refresh_token`, and `is_connected`.
- Add hidden attributes or dedicated DTO/transformer responses for settings models.

### 6. Improve secret storage
- Encrypt stored third-party credentials and tokens at rest.
- Review Xero, SMTP, SMS, WhatsApp, and any other integration secrets.
- Audit model serialization to ensure secrets cannot leak through API or Inertia responses.

### 7. Fix OAuth `state` validation
- Generate a random state value per auth request.
- Store it in session.
- Verify exact match in the callback before exchanging the code.
- Invalidate the state value after successful or failed callback handling.

### 8. Verify inbound webhook authenticity
- Add Xero webhook signature verification before any sync processing.
- Reject unsigned or invalid webhook requests before touching application data.

### 9. Remove sensitive logging
- Remove logs that capture full request payloads, OAuth codes, tokens, SMTP details, bank details, or customer contact data unless strictly required.
- Replace them with redacted structured logs.
- Define a small shared helper for safe logging of integration events.

## Medium-Term Improvements

### 10. Add formal authorization policies
- Move authorization logic out of ad hoc controller checks and into Laravel policies.
- Cover all tenant-owned models and all state-changing actions.
- Use policies consistently in controllers and tests.

### 11. Tighten time-entry security
- Add dedicated permission middleware for time-entry routes.
- Enforce company ownership and, where appropriate, user ownership on `update` and `destroy`.
- Ensure limited users cannot infer company-wide totals from summary queries.

### 12. Harden PDF/template handling
- Escape template values by default.
- Allow raw HTML only when explicitly required and tightly controlled.
- Reassess whether JavaScript in generated PDFs is necessary.
- Add company checks to every PDF template action.

### 13. Remove bootstrap credential exposure
- Stop storing initial admin passwords in `.env` after installation.
- Use one-time setup flow or immediately clear bootstrap secrets after seeding.

## Suggested Delivery Phases

### Phase 1: Critical fixes
- Tenant scoping
- Route permission mapping
- Company-scoped validation rules
- Licensing route lockdown

### Phase 2: Secret and integration hardening
- Frontend secret removal
- Encrypted secret storage
- OAuth `state` validation
- Webhook signature verification
- Sensitive log removal

### Phase 3: Structural improvements
- Authorization policies
- Time-entry hardening
- PDF/template safety
- Installer/bootstrap secret cleanup

## Testing Plan
- Add feature tests for cross-company access attempts on all major modules.
- Add tests proving view-only users cannot create, edit, delete, convert, email, or change status.
- Add tests for invalid foreign-company IDs in create/update payloads.
- Add tests for OAuth callback rejecting invalid `state`.
- Add tests for webhook signature rejection.
- Add regression tests for any controller that previously relied on global route-model binding.

## Success Criteria
- A user cannot access or mutate another company's records by changing IDs in URLs or payloads.
- View-only users cannot perform write operations through direct requests.
- Third-party secrets are never present in frontend page props.
- OAuth callbacks reject mismatched `state` values.
- Sensitive request data no longer appears in application logs.

## Recommended First Work Items
1. Patch quote, invoice, credit note, and jobcard routes to use correct permission middleware.
2. Add company checks or scoped model binding for route-bound records in those same modules.
3. Refactor create/update validation rules to use company-scoped `exists` constraints.
4. Remove Xero secret fields from Inertia responses.
5. Lock licensing deploy/upgrade actions behind admin-only access.
