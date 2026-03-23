# Security Remediation Rollout (Compatibility-First)

## Migration and deploy order

1. Deploy code and run migrations normally (`php artisan migrate --force`).
2. Run cache refresh commands (`php artisan config:cache`, `php artisan route:cache`) after deploy.
3. Do not remove legacy Xero settings fields or existing defaults in this rollout.

## Compatibility defaults

- Xero OAuth callback state validation remains additive and only blocks invalid/missing state callbacks.
- Xero webhook signature verification rejects invalid signatures and safely ignores unknown tenants.
- Existing Xero sync flow shape is preserved (no strict field cutover semantics introduced).
- PDF custom templates still support existing placeholders; rendered HTML now strips executable script/event patterns before PDF generation.

## Post-deploy smoke checklist

- Xero:
  - Authorize flow completes and callback succeeds with valid state.
  - Invalid callback state is rejected with a user-facing error.
  - Webhook with valid signature returns success/ignored as expected.
  - Webhook with invalid signature returns `401`.
  - Invoice sync (export + import) still runs for a connected tenant.
  - Purchase order sync path still executes without payload regressions.
- Tenant/permissions:
  - Limited user cannot convert time entries on jobcards they cannot update.
  - Admin/authorized user can still convert line items for assigned jobcards.
- PDF/template:
  - Existing custom templates still render expected escaped variables.
  - Malicious script/event payloads do not appear in generated PDF output.

