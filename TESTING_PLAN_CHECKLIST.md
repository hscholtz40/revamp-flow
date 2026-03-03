# JobCardOnline - End-to-End Testing Plan (Checklist)

Use this as a release/UAT checklist. Tick each item only after validating expected behavior and capturing evidence (screenshots, logs, IDs, exported files).

---

## 1) Test Run Metadata

- **Build/Commit**: ____________________
- **Environment**: Local / Staging / Production-like
- **Database snapshot used**: ____________________
- **Tester**: ____________________
- **Date**: ____________________
- **Browsers tested**: Chrome / Edge / Firefox / Safari
- **Devices tested**: Desktop / Tablet / Mobile

---

## 2) Pre-Flight Setup

- App boots without errors (web + queue + scheduler + assets).
- Migrations run successfully.
- Seed or baseline data loaded (customers, products, taxes, accounts, users, groups, companies).
- Mail config valid (for email invoice/quote flows).
- PDF generation works (preview/download/print).
- Company defaults configured:
  - Default sales tax
  - Default sales account
  - Default purchasing tax
  - Default purchasing account
  - Default sales customer (for POS)
- Dark mode toggle available and persistent.
- Required permissions/groups configured for role testing.

---

## 3) Authentication, Authorization, Company Context

- Login/logout/password reset basic flow.
- Unauthorized users cannot access protected routes.
- Module permissions enforced per role (list/view/create/edit/delete).
- Company switcher works and data isolation is correct per company.
- Shared state (current company, abilities) matches visible UI actions.
- Forbidden actions return friendly messages (no raw 500 pages).

---

## 4) Core UI Quality (Cross-Cutting)

- Search/filter/sort works on all list views.
- Pagination works on all list views:
  - Previous/Next
  - Page number buttons
  - Disabled state behavior
- Status button bars render correctly (light + dark).
- Dropdown menus and hover states render correctly (light + dark).
- Modals/backdrops render correctly (light + dark).
- Form validation messages are clear and positioned correctly.
- No `toFixed`/type errors in browser console.
- No major console errors during normal navigation.

---

## 5) Master Data Modules

### Customers

- Create/Edit/Delete customer.
- `terms` dropdown works (COD / Net options).
- Customer search + quick create works.
- Customer terms appear in dependent forms (invoice/POS where applicable).

### Contacts

- CRUD works.
- Pagination/search/filter works.

### Products & Services

- CRUD works.
- Pricing/tax/account defaults respected.
- Product search in line item description fields works.

### Suppliers

- CRUD works.
- Supplier linking in purchase orders works.

### Tax Rates / Chart of Accounts / Bank Accounts

- CRUD + default flags work.
- Defaults are consumed by invoice/POS/PO flows.

---

## 6) Operational Modules

### Jobcards

- CRUD works.
- Status changes work.
- Convert quote -> jobcard works.
- Team/user assignment works.

### Quotes

- CRUD works.
- Status changes via status controls.
- Convert quote -> invoice works.
- Convert quote -> jobcard works.
- PDF download and email flows work.

### Invoices

- CRUD works.
- Invoice title optional (blank falls back to generated invoice number).
- Due date auto-calculates from customer terms.
- Payments add/remove works.
- Status bar and transitions work.
- PDF download + print (inline tab) + email work.

### Credit Notes

- CRUD works.
- Top status bar works (quote-style status buttons).
- Link to invoice works.
- Refund payments (optional) add/remove works.
- Remaining credit recalculates correctly.
- Status auto-updates when fully refunded.

### Purchase Orders

- CRUD works.
- Supplier + line items + totals work.
- Status transitions (draft/sent/received/cancelled) work.
- PO numbering matches configured prefix/next number.

### POS (Point of Sale)

- POS visibility obeys company setting + permissions.
- POS button appears on Dashboard + Invoices list only when allowed.
- Default sales customer auto-selected.
- Customer type-to-search works.
- Invoice details/line items left, payment section right.
- Payment method uses buttons (Cash/Card/EFT), no default selected.
- Complete button disabled until all required fields are valid:
  - Customer selected
  - Payment method selected
  - At least one valid line item
- Cash tender/change calculation works.
- Successful sale opens invoice PDF in new tab and resets POS form.

### Stock Movements / Timesheet / Teams / Reports / Audit Logs / Backups

- Each module loads without errors.
- CRUD/filters/pagination/export flows work where applicable.
- Report templates save/edit/delete/export work.
- Backup creation/list/download/restore flow (if enabled) works.
- Audit log entries recorded for critical actions.

---

## 7) Administration & Settings

- Company settings CRUD works.
- Enable/disable POS per company works.
- Module visibility settings work.
- Document numbering updates apply to:
  - Invoices
  - Quotes
  - Jobcards
  - Credit notes
  - Purchase orders
- Database upgrade action works and logs output.
- PDF templates CRUD/upload works.
- SMS settings CRUD works.
- WhatsApp settings CRUD works.

---

## 8) Integrations - Xero (Critical)

## 8.1 Connectivity & OAuth

- OAuth authorize/callback/tenant selection works.
- Disconnect works and revokes effective access.
- Reconnect/switch tenant/company works.

## 8.2 Outbound Sync (JCO -> Xero)

- Customers
- Products
- Suppliers
- Quotes
- Invoices
- Payments (invoice payments)
- Credit notes
- Credit note refund payments
- Purchase orders

## 8.3 Inbound Sync (Xero -> JCO)

- Customers
- Products
- Suppliers
- Quotes
- Invoices
- Payments (invoice + credit note linked)
- Credit notes (+ allocations)
- Purchase orders (+ line items)
- Tax rates
- Bank accounts
- Chart of accounts

## 8.4 Sync Conflict & Loop Prevention

- Updated-at conflict logic behaves correctly (newer side wins where expected).
- No infinite update loops between systems.
- No unnecessary writes when payload unchanged.
- Idempotency checks prevent duplicate records/payments.
- Scheduler/manual command overlap does not duplicate work.

## 8.5 Known Edge Cases (must pass)

- Paid invoice update does not fail due to forced `AUTHORISED` status.
- Paid JCO invoice exports payment in same run (not delayed to next run).
- Quote update handles Xero status restrictions safely.
- Credit note update handles non-editable allocated/applied credit notes.
- Purchase order update handles status-change restrictions safely.
- Purchase order sync handles Xero account code validity constraints safely.
- Initial/backfill sync progresses and does not stall.

## 8.6 Operational & Observability

- Sync summary counters make sense (created/updated/skipped/error).
- Error logs contain enough context (entity id/number, reason, response snippets).
- Long-running sync jobs eventually complete and release process.
- Xero rate limit behavior is controlled (throttle/delay/retry where expected).

---

## 9) Licensing & License API

## 9.1 Instance Licensing (non-licensing instances)

- Invalid/missing license redirects to Administration License page.
- License validation against server works with URL matching.
- Version reporting to license server works (uses app version from `package.json`).
- HMAC-secured license API calls validate correctly.
- User type limits enforced on create/edit:
  - Standard limit
  - Limited limit
- Over-limit restriction behavior:
  - Access restricted to Users module until compliant
  - Professional warning message shown

## 9.2 Licensing Instance Mode (`IS_LICENSING_INSTANCE=true`)

- License CRUD works.
- Deployment actions work:
  - Deploy
  - Upgrade
  - Force SSL
- cPanel deployment path works end-to-end (if enabled).
- Docker deployment path works end-to-end (if enabled):
  - Host selection
  - Host connection test
  - Per-domain nginx config creation
  - Multi-host selection behavior

---

## 10) Notifications & Messaging

- Invoice email send works with expected templates.
- Quote email send works.
- Payment confirmation/reminder flows work (if enabled).
- SMS messages send with correct variables and formatting.
- WhatsApp messages send with correct template/variables.

---

## 11) Data Integrity Checks

- Totals consistency:
  - Line totals = subtotal logic
  - Tax totals
  - Discount handling
  - Grand totals
- Invoice `remaining_balance` reflects payments + credit notes.
- Credit note `remaining_credit` reflects refund payments.
- Number sequences increment correctly and do not collide.
- Referential integrity: deleted/updated records do not orphan critical data.

---

## 12) Security & Hardening

- Role/permission bypass attempts are blocked.
- Sensitive endpoints require auth and expected middleware.
- License API rejects missing/invalid HMAC headers.
- CSRF protection works for web forms.
- File uploads validate type/size.

---

## 13) Performance & Reliability

- Key list pages load within acceptable threshold.
- Large pagination pages remain responsive.
- Bulk sync operations finish within expected window.
- No memory/CPU runaway for scheduled sync commands.

---

## 14) Final Release Gate

- All critical/high issues closed.
- Medium issues triaged with owners/dates.
- Rollback plan verified.
- Monitoring/log alerts prepared for release window.
- Stakeholder sign-off complete.

---

## 15) Sign-Off

- QA Sign-off: ____________________
- Product Sign-off: ____________________
- Technical Sign-off: ____________________
- Go-live approved: Yes / No

