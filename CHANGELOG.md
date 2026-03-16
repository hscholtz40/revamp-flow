# Changelog

## 2026-03-17 - version 1.7.7

- Added line groups to all document line items: quotes, jobcards, invoices, credit notes, and purchase orders. Each document gets a default "Items" group when created; line groups are displayed on PDFs with group headers. Document conversions (quote→jobcard, quote→invoice, jobcard→invoice) copy line groups and assign line items to the corresponding groups.
- Added line group controls to document create/edit forms (quotes, invoices, jobcards, credit notes, and purchase orders): users can add/remove group names and assign each line item to a group from the form UI.
- Updated PDF rendering for historical documents so line items still print when line groups are missing or mismatched; ungrouped items now fall back into an "Items" section.
- Reworked quote line-group editing UX in Create/Edit: line items now render under their group sections (no per-line group dropdown), and dragging a line item between positions/groups updates both order and group assignment.
- Applied the same grouped line-item + drag/drop UX to invoice, jobcard, credit note, and purchase order forms so line items are managed directly within each group and can be reordered or moved between groups by drop position.
- Fixed edit screens for historic documents so line items with missing/invalid `line_group_id` are auto-assigned to the default group on load and no longer disappear.
- Added drag/drop UI affordances in grouped line-item editors (quotes, invoices, jobcards, credit notes, and purchase orders): visible grab handles plus highlighted drop targets for clearer reordering/move feedback.
- Improved grouped line-item drag UX: custom drag preview chip now appears while dragging, rows get active drag styling, and grab handles use higher-contrast bordered badges for better visibility.
- Fixed Xero invoice export validation for rounding adjustment lines by reconciling outbound `UnitAmount` with stored `LineAmount` on non-discounted lines when totals differ, preventing "line total does not match expected line total" sync failures.
- Simplified PDF line-item tax columns across invoice, quote, proforma invoice, jobcard, and purchase order templates by removing the secondary tax name/rate text under each tax amount.
- Updated the invoice PDF company information block to use aligned label/value rows (address, city, VAT number, phone, email, fax) for a cleaner, more consistent header layout.
- Refined the invoice PDF company information block to use a true HTML table for label/value pairs so value alignment stays consistent across PDF renderers.
- Adjusted the invoice PDF company details table to auto-size its columns/overall width based on content instead of forcing a fixed full-width layout.
- Added trailing colons to invoice PDF company details labels (e.g. `Email:`) for clearer label/value separation in the table layout.
- Applied the same auto-width, table-based company details format (with colon-suffixed labels) to quote, proforma invoice, jobcard, and purchase-order PDFs for consistent header alignment across all core documents.
- Updated all core PDF templates to suppress placeholder migration emails containing `sage-migration.local` (company and document/customer/supplier email fields) so non-production addresses are hidden in printed output.
- Refreshed the app sidebar visual styling with a more modern look (softer card-like header, improved section label/menu item hierarchy, and updated hover/active states) while keeping existing navigation behavior intact.
- Updated the app theme sidebar base color to a clearer light grey for better visual separation from main page content.
- Replaced the credit-note invoice dropdown on Create/Edit with a searchable select-style picker (matching customer lookup behavior) while intentionally excluding quick-create actions for invoices.
- Improved credit-note Create/Edit load performance by removing large preloaded invoice/product datasets from Inertia props and switching to debounced server-side invoice/product search endpoints.
- Fixed Xero payment export for fully paid local invoices: removed an incorrect cap that used JCO `remaining_balance` (0 on paid invoices), and expanded invoice export selection to include invoices with unsynced payment records so payment sync retries are not skipped.
- Fixed Vue template parse errors (`Element is missing end tag`) in credit-note edit and purchase-order create pages by restoring missing closing wrapper divs in grouped line-item sections.

## 2026-03-16 - version 1.7.6

- Document line items: show item code (SKU/barcode) in brackets with product name/description in PDFs, Show pages, and PDF template editor presets.
- Jobcards list: hide completed and cancelled jobcards by default; add "Show closed" checkbox in filters to include them.
- Mark quote as accepted when converted to jobcard or invoice.
- Quotes list: hide accepted/rejected quotes by default; add "Show closed" checkbox in filters to include them.
- Added Invoice column to jobcards list view (after Customer) showing linked invoice number with link when jobcard was converted to an invoice.
- Added approval row at bottom of quote and proforma invoice PDFs with Received by, Date, and Signature fields (dashed lines for handwritten completion).
- Fixed quote and jobcard Edit pages so line item tax rate and account selections are retained when editing: pass document as array to ensure tax_rate_id and account_id are included, and coerce values to numbers for correct select binding.
- Retained tax_rate_id and account_id on line items when converting quote→jobcard, quote→invoice, and jobcard→invoice; also copy discount_amount and discount_percentage for consistency.
- Added Job Card column to invoices list (after Customer) showing linked jobcard number when invoice was converted from a jobcard.
- Added Date column and sortable columns (Invoice, Customer, Date, Unit Price) to Recent Invoice Usage panel on product Show page.
- Paginated "Recent Invoice Usage" panel on product Show page (10 per page, Previous/Next).
- Added "Recent Invoice Usage" panel on product Show page listing latest invoice line items (invoice number, customer, unit price) where the product was used.
- Moved document description to appear just below customer/contact information in PDFs (quote, proforma invoice, invoice, jobcard); was previously at the bottom in terms/notes section.
- Show linked contact on quote, invoice, and jobcard Show pages (Customer Information section) with link to contact detail.
- Fixed ContactSelector showing empty when editing a document with a contact selected: added `initialContact` prop and ensured Edit controllers load the contact relation.
- Replaced comma-separated email field in quote/invoice/jobcard email modals with a tag-style recipient list: pre-filled from document email, "Add email/contact" button to pick a contact or enter an email manually, and add/remove recipients like tags.
- Removed the purchase-order create line-item overflow wrapper that was clipping product suggestion dropdowns and raised dropdown stacking so product search results stay visible while typing.
- Fixed purchase-order create product lookup dropdown visibility by allowing suggestion menus to overflow above the horizontal line-item scroll container.
- Updated purchase-order create line-item UI rows to match the invoice-style line layout (compact grid columns, aligned field sizing, and consistent per-row totals/actions) instead of card-style blocks.
- Further aligned purchase-order PDF line rows with invoice-style structure by switching to item-code + description columns and matching numeric/tax cell formatting across blade, default templates, and template editor presets.
- Fixed remaining purchase-order line issues by improving PO product search feedback while typing (including SKU matches) and aligning purchase-order PDF/default template line-item columns and styling with invoice-style output.
- Updated outgoing document emails to set `Reply-To` to the active company email (when configured), covering manual sends and automated reminder/confirmation emails.
- Fixed purchase-order line-item behavior by correcting product search input binding so typed text and SKU suggestions display properly, and aligned purchase-order PDF line-item table styling with invoice table styling.
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
- Reduced quote and proforma PDF header spacing and line-item table padding/margins so more document content fits on a single page before overflowing.
- Added a customer "Resync All from Xero" action in Xero settings that bypasses `If-Modified-Since` and refreshes all Xero customer records into JobCardOnline.
- Fixed invoice PDFs so the Description block renders when only `description` is populated (without requiring `notes` or `terms`).
- Allowed negative line-item unit amounts/costs across quotes, jobcards, invoices, credit notes, and purchase orders by removing positive-only validation/UI constraints and preserving negative totals in calculations.
- Added the customer VAT number to the customer detail view so VAT/tax registration information is visible on the show page.
- Fixed POS invoice creation to consistently persist the currently logged-in user as `salesperson_id`.
- Fixed invoice PDFs to render the customer's VAT number in the VAT box, and updated the invoice default template editor preset to include `{{invoice.customer.vat_number}}`.
- Fixed quote/jobcard-to-invoice conversion to persist the currently logged-in user as `salesperson_id` on the created invoice.
- Updated document list defaults to sort by `created_at` descending (newest first) for quotes, invoices, jobcards, purchase orders, and credit notes.
- Updated invoice line fallback account selection to prefer account code `1000` whenever no account is selected, with default sales account as a secondary fallback.
- Fixed invoice list sort persistence by sending resolved sort filters back from the controller and adding a sortable `Created` column in the invoices table.
- Fixed Xero sales-document exports (invoices/quotes/credit notes) to default missing line account codes to `1000`/default-sales-account instead of product-level fallback values like archived `200`, and aligned imported product sales-account fallback to `1000`.
- Added an optional `Order Number` field to POS invoice creation and persist it on the generated invoice.
- Added contact linking to quotes, invoices, and jobcards: `contact_id` stored on documents, searchable contact selector with quick-create on create/edit forms, contact info ("Attn:") in PDFs, recipient_email/recipient_phone preferring linked contact, and send-email supporting multiple comma-separated addresses.
- Fixed Xero payment sync: treat AmountDue ≤ 0.01 as fully paid (rounding), use AmountOwing fallback, invalidate invoice cache after each successful payment so batch syncs get fresh AmountDue, and always fetch fresh invoice per payment instead of reusing stale cached data.
- Capped Xero payment creation to the amount due on the invoice (min of JCO remaining balance and Xero AmountDue) to avoid "Payment amount exceeds the amount outstanding" validation errors.
- Fixed contact_id not persisting on document save by explicitly including it in the form payload via transform on quote, jobcard, and invoice create/edit submits.

## 2026-03-13 - version 1.7.5

- Added a global toast notification system for Inertia flash messages and removed page-specific flash banners so success, error, warning, info, and status messages display consistently across the app.
- Fixed the dashboard `Your Sales Performance` revenue figures to subtract allocated, non-voided credit notes from invoice totals so sales trends reflect net invoice value.
- Fixed credit notes so opening an invoice-linked note no longer downgrades a `paid` status back to `authorised` during balance refresh.
- Fixed Xero chart-of-accounts imports to relink existing local accounts by code, map more Xero account types correctly, and scope account-code uniqueness per company to prevent duplicate-key sync failures.
- Scoped document-number uniqueness per company for quotes, invoices, jobcards, purchase orders, and credit notes, and updated related Xero matching plus product SKU/barcode validation to avoid cross-company duplicate conflicts.

## 2026-03-14

- Increased the default PDF template logo cap from `200x100` to `220x110` so company branding appears slightly larger in built-in templates and template editor presets.
