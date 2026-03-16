# Changelog

## 2026-03-16 - version 1.7.6

- Added company email output to default PDF headers (invoice, quote, jobcard, proforma invoice, and purchase order) so sender contact details print from the active company profile.
- Added an `Account` payment option to POS that creates the invoice without recording an immediate payment, while still requiring payment capture for cash/card/EFT sales.
- Added Xero contact VAT sync so `vat_number` now maps bidirectionally with Xero `TaxNumber` for both customers and suppliers during import and export.
- Tightened line-item table spacing in PDF outputs and adjusted header proportions by slightly reducing document title size while increasing logo size for better visual balance.
- Fixed invoice PDFs to resolve `Job No.` from the linked source jobcard, added editable document-level phone/email fields on quotes, jobcards, and invoices with customer defaults, routed reminder/SMS lookups through those saved document contacts, and allowed users with invoice view access to add payments without needing full invoice edit permission.
- Updated index/list screens so records open from a clickable row or card instead of separate View buttons, while preserving inline edit/delete and other action controls.
- Fixed quote line-item column alignment so the desktop header now matches the actual row layout in both create and edit screens.
- Fixed line-item product search so SKU lookups work in quote and jobcard forms, and switched product suggestion matching to a shared literal string matcher that safely handles special characters in SKUs.
- Fixed invoice, quote, and proforma PDF templates so document-level descriptions and notes print correctly in both built-in PDFs and existing saved default templates after migration.
- Fixed quote, invoice, and jobcard PDFs to print notes correctly, use product SKU/item codes on invoice and jobcard line items, and hide item codes on quote-style documents while keeping older custom PDF templates compatible.
- Added `order_number` fields to quotes, jobcards, and invoices, surfaced them in the create/edit/show UIs and PDFs, and preserved the value when converting quotes or jobcards into downstream documents.
- Default the jobcard create form to the company's default sales customer when one is configured, matching the existing quote creation flow.
- Fixed company switching for non-admin users by moving the switch endpoint out of the admin-only route group while keeping the controller's per-company access check in place.

## 2026-03-13 - version 1.7.5

- Added a global toast notification system for Inertia flash messages and removed page-specific flash banners so success, error, warning, info, and status messages display consistently across the app.
- Fixed the dashboard `Your Sales Performance` revenue figures to subtract allocated, non-voided credit notes from invoice totals so sales trends reflect net invoice value.
- Fixed credit notes so opening an invoice-linked note no longer downgrades a `paid` status back to `authorised` during balance refresh.
- Fixed Xero chart-of-accounts imports to relink existing local accounts by code, map more Xero account types correctly, and scope account-code uniqueness per company to prevent duplicate-key sync failures.
- Scoped document-number uniqueness per company for quotes, invoices, jobcards, purchase orders, and credit notes, and updated related Xero matching plus product SKU/barcode validation to avoid cross-company duplicate conflicts.

## 2026-03-14

- Increased the default PDF template logo cap from `200x100` to `220x110` so company branding appears slightly larger in built-in templates and template editor presets.
