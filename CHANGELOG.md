# Changelog

## 2026-03-24

- Reduced Xero invoice/payment sync request volume by removing per-invoice pre-read calls before invoice export and payment export, so sync now writes directly and relies on write responses plus scheduled inbound sync/webhooks for reconciliation.
- Removed Xero single-invoice endpoint usage (`/Invoices/{id}`) by switching invoice-by-ID lookups to filtered list queries and eliminating import-time per-invoice detail hydration fetches to lower rate-limit pressure during high-volume sync runs.
- Updated Xero outbound invoice/payment error handling to treat specific non-retriable `ValidationException` responses (paid/allocated invoice update conflicts and overpaid/non-authorised payment conflicts) as synced locally by stamping `xero_updated_at`/`xero_synced_at`, preventing repeated retry loops in scheduled sync.

## 2026-03-23

- Reduced Xero API request pressure by lowering scheduler frequencies for non-critical sync jobs, moving payment-import execution from `xero:sync-invoices` to `xero:sync-invoices-from-xero`, and removing immediate payment-create sync from the UI path so payment export is handled by the scheduled `xero:sync-payments` command.
- Extended Xero customer/supplier import sync to also process Xero `ContactPersons`, creating/updating linked JCO contacts during import-only flow so customer and supplier contact people are available in JCO.
- Expanded JCO contacts schema to support supplier-linked contacts and persisted Xero contact-person linkage (`xero_contact_person_id`) for idempotent re-sync updates without duplicate contacts.

## 2026-03-20

- Kept quote PDF type selection in the quote print/email flow (Quotation vs Proforma Invoice) and removed the company-level default quote PDF type setting so this remains a per-action choice.
- Fixed quote PDF template filtering so quotation sends/downloads correctly target the `quote` template module (not `quotation`), ensuring Quote templates and defaults are selectable when printing/emailing.
- Updated Quote `Download PDF` UX to always open a selection modal with explicit PDF type choice (Quotation or Proforma Invoice) before downloading, preventing implicit default-to-Quote behavior.
- Set Quote `Download PDF` modal to preselect `Quotation` as the default PDF type while still allowing users to switch to `Proforma Invoice`.

## 2026-03-17 - version 1.7.7

- Added line groups to all document line items: quotes, jobcards, invoices, credit notes, and purchase orders. Each document gets a default "Items" group when created; line groups are displayed on PDFs with group headers. Document conversions (quote→jobcard, quote→invoice, jobcard→invoice) copy line groups and assign line items to the corresponding groups.
- Standardized document conversion traceability on Quote and Jobcard show pages: converted records now persist source metadata (`source_type`/`source_id`), display the original source document link, and hide only conversion actions already completed (showing a view link instead).
- Upgraded customer/contact email send flows with a manual-first composer: no template is preselected by default, users can enter/edit subject and HTML body directly, and selecting a template now prefills editable content with an on-screen preview.
- Added persistent email activity history for customers and contacts, including direct emails and document emails (invoices, quotes, and jobcards), with status tracking and timestamps shown in new Email Activity panels on customer/contact detail pages.
- Replaced the administration email-template editor implementation from GrapesJS to `vue-email-editor` (Unlayer), with a visual designer + manual HTML tabs and image upload support retained for template assets.
- Standardized outbound email identity defaults so sender display name uses the current company name and reply-to uses the current company email across direct customer/contact emails and core document email flows.
- Improved CRM detail pages: Email Activity now has explicit per-page pagination controls on customer and contact views, and the Customer Version History panel has been removed.
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
- Fixed invoice permission enforcement so users without `invoices.edit` can no longer access invoice edit/update/status actions; payment creation remains available to invoice-view users, and invoice edit controls are now hidden when edit permission is missing.
- Updated company switching behavior to always redirect to the dashboard after a successful switch, preventing record-page errors when the same URL is invalid in the newly selected company context.
- Added per-user, per-company list-view column preferences across index pages via a new `Edit Columns` control in the app header, supporting column show/hide and drag-to-reorder with server-side persistence.
- Fixed list column editor reordering instability where dragged columns could snap back due to over-aggressive DOM observer reloads; preferences now stay in place immediately after reorder.
- Fixed Xero payment export for fully paid local invoices: removed an incorrect cap that used JCO `remaining_balance` (0 on paid invoices), and expanded invoice export selection to include invoices with unsynced payment records so payment sync retries are not skipped.
- Fixed Vue template parse errors (`Element is missing end tag`) in credit-note edit and purchase-order create pages by restoring missing closing wrapper divs in grouped line-item sections.
- Extended invoice reports with payment-related columns from linked records (`Payments Count`, `Last Payment Date`, `Total Paid`, and `Balance Due`) so report builders can include payment context without leaving report views.
- Added an invoice report column for full payment breakdowns that lists each linked payment method and amount in one cell (e.g. `Cash: R100.00, Card: R50.00`) for clearer per-invoice payment visibility.
- Optimized report performance to reduce timeout risk by removing unnecessary eager-loaded relations and replacing grouped report per-group queries with a single record fetch plus in-memory bucketing.
- Standardized report date-range filtering to use each document's primary date field (including `invoice_date` for invoices) instead of relying on record creation timestamps.
- Fixed report sorting/grouping SQL errors for virtual columns (e.g. `formatted_date`) by mapping them to real database fields before query `ORDER BY`/`GROUP BY`.
- Added invoice UI rounding support to nearest `0.10` by automatically maintaining a `Rounding Adjustment` line item, and introduced a chart-of-accounts `Default Rounding` account flag so rounding lines are posted to the configured account with tax set to `None`.
- Updated invoice presentation to keep `Rounding Adjustment` lines operational but hidden from invoice create/edit/show screens and generated invoice PDFs, while preserving backend totals and Xero sync line exports.
- Added `Rounding Adjustment` breakdown rows to totals sections across document views and core PDFs (invoice, quote, proforma invoice, jobcard, purchase order, and credit note), while keeping rounding persisted as line items for backend calculations/integrations.
- Fixed invoice edit-page startup crash (`Cannot access 'ensureRoundingAdjustmentLine' before initialization`) by hoisting rounding helper functions used by immediate watchers.
- Restored automatic rounding behavior in quote and invoice editors: rounding adjustment lines are now actively maintained again (including fallback account assignment when a default rounding account is not configured) so totals round correctly to the nearest `0.10`.
- Updated all core PDF document tables to suppress `Rounding Adjustment` line items from printed line rows, while still showing rounding in totals sections.
- Enforced Xero export account mapping for `Rounding Adjustment` lines on invoices and quotes so outbound `AccountCode` uses the company’s configured default rounding account when set.
- Fixed Xero rounding-account export edge cases by resolving default rounding/sales account codes using the document’s company context (invoice/quote `company_id`) and matching rounding lines by both description and default-rounding-account assignment.
- Fixed Xero invoice/quote line tax mapping so lines with no `tax_rate_id` now export with `TaxType: NONE` (including rounding lines), instead of inheriting the default sales tax code.
- Improved Xero rounding-account resolution by falling back to any company account flagged `is_default_rounding` (even if inactive) and skipping `LineItemID` reuse for rounding lines on paid/allocated invoice updates so account-code corrections can apply.
- Restored automatic nearest-`0.10` rounding behavior on jobcard Create/Edit by auto-maintaining hidden `Rounding Adjustment` lines (assigned to default rounding account with sales-account fallback), and included rounding in jobcard totals calculations.
- Fixed invoice PDF print/download/email error (`Unknown column 'rounding_adjustment_total' in 'SET'`) by passing rounding totals via a non-persisted relation instead of mutating invoice attributes.
- Updated quote→invoice and jobcard→invoice conversions to enforce nearest-`0.10` rounding on the created invoice by auto-creating/updating a `Rounding Adjustment` line when needed.
- Aligned invoice save/update totals with rounding behavior by re-applying server-side `Rounding Adjustment` normalization before `calculateTotals()`, preventing post-save 1-cent drift from edit-view totals.
- Aligned invoice tax rounding with Xero by switching invoice line tax calculation from always-round-up (`ceil`) to standard 2-decimal rounding, including quote/jobcard-to-invoice conversion tax recalculation, reducing 1-cent VAT mismatches during sync.
- Fixed invoice UI tax totals (Create/Edit and POS) to use standard 2-decimal rounding instead of round-up-per-line, so on-screen VAT matches saved invoice values and Xero sync calculations.
- Standardized tax rounding across all remaining document types (quotes, jobcards, credit notes, and purchase orders) in both backend and UI calculations by replacing round-up (`ceil`) behavior with normal 2-decimal rounding to match Xero.
- Fixed invoice Edit view `NaN` totals on records without discounts by normalizing line-item numeric fields (`quantity`, `unit_price`, `discount_amount`, `discount_percentage`, and related IDs) before calculations, preventing string concatenation in discount/subtotal reducers.
- Fixed invoice Edit startup crash (`Cannot access 'getGroupValueByIndex' before initialization`) by converting `getGroupValueByIndex` to a hoisted function declaration so immediate watchers can safely call `normalizeLineItemOrder` during setup.
- Fixed quote→invoice and jobcard→invoice conversions triggered from document Show pages to assign the currently logged-in user as `salesperson_id` on the created invoice (model-level `convertToInvoice()` path), matching invoice controller conversion behavior.
- Updated Xero document sync payloads to include line-item `ItemCode` when a linked product has an SKU, covering invoices, quotes, credit notes, and purchase orders so item references are preserved in Xero.
- Updated invoice PDF line-item table to include a dedicated `Item Code` column (SKU/barcode fallback) instead of appending item codes to descriptions, improving readability and aligning output with Xero item-code usage.


## v1.8.2
- Expanded list-view column editing on core document index pages (invoices, quotes, jobcards, purchase orders, and credit notes) by exposing additional model-backed fields as hidden-by-default columns so users can add them to their table views without showing internal ID/Xero identifiers.
- Updated document Show pages to better match Edit behavior by rendering grouped line items (line-group headers + grouped rows) across invoices, quotes, jobcards, purchase orders, and credit notes; invoice/purchase-order show payloads now load line-group relations required for grouped display.
- Added `Description` as a selectable hidden-by-default column in the document list column editor for invoices, quotes, and jobcards so users can include document descriptions in index table views when needed.
- Fixed list column editor labels showing all-uppercase by switching header label extraction from rendered `innerText` to source `textContent`, preserving intended casing while still cleaning sort-indicator symbols.
- Fixed list column order/visibility drift after applying filters on the same index page by reloading and reapplying saved column preferences whenever the Inertia page URL/query changes (not only when component name changes).
- Added per-column table filtering on index list views by rendering a filter-input row directly beneath column headers (works with reordered/hidden/custom-added columns from `Edit Columns`) so users can filter each visible column independently.
- Added jobcard→quote conversion support: new backend endpoint/controller action plus Jobcard Show-page action button now create a draft quote from the jobcard (including copied line groups/line items, pricing, tax, notes, terms, and contact fields).
- Changed all document conversion actions (quote↔jobcard/invoice and jobcard→quote/invoice paths) to open the target Create screen with source data pre-populated instead of immediately creating records; saving now performs creation explicitly and invoice saves still apply conversion links/status updates to the source document.
- Added Administration Email Templates with a drag-and-drop editor (image uploads + source HTML editing), exposed customer/contact/company/user/date variable tokens in the template UI, and added template-driven `Email` actions on customer/contact list and detail screens.
- Removed user-level and company-level SMTP configuration from app workflows: outgoing mail now always uses `.env` mail settings, while all customer/contact/document/reminder emails explicitly set sender name to the active company and reply-to to the active company email.
- Added automated reminder SMS activity logging into `sms_activities` (not just reminder logs), including success/failure details and phone/message payloads, so reminder SMS now appears in customer/contact SMS activity tracking.
- Fixed a blank-render issue in the administration email template editor by hardening async `vue-email-editor` module resolution and adding a visible fallback state when the designer fails to initialize.
- Fixed email template editor viewport sizing by switching the designer wrapper to a fixed height and forcing the embedded editor to fill the container, preventing the collapsed "small bar" layout.
- Fixed outbound email delivery behavior by explicitly sending through the SMTP mailer in customer/contact/document/reminder flows (instead of the default mailer), preventing false "sent" success when `MAIL_MAILER=log`.
- Upgraded customer/contact email compose modals to include the visual Unlayer editor in-place (with manual HTML still available), while preserving live preview and stripping embedded design markers before send.
- Fixed Unlayer editor sync so changing template/body content after mount now reloads into the designer view, ensuring preview content is reflected inside the editor instead of remaining stale.
- Simplified the customer/contact email composer UI by removing the separate preview panel, giving the body editor full modal width for a cleaner compose experience.
- Fixed list-view column filters so they now apply across the full dataset (server-side via URL filter params) instead of only filtering the currently loaded page rows.
- Updated list-view column filter inputs to apply only on Enter key press, preventing disruptive table reload/filtering while users are still typing.
- Fixed post-filter table rendering so rows no longer remain hidden until manual refresh; clearing a column filter and pressing Enter now consistently reloads and restores results immediately.
- Hardened column-filter clearing behavior: clearing now triggers an immediate Inertia reload (including native search clear-button events) and forces fresh server-rendered rows to avoid stale filtered state.
- Fixed grouped line-item row identity drift in quote editors (and aligned the same safeguard in invoice, jobcard, credit-note, and purchase-order editors) by using stable per-row client IDs for Vue keys and excluding those IDs from payloads, preventing edits from applying to the wrong row after reordering/normalization.

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
