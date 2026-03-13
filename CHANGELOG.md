# Changelog

## 2026-03-13 - version 1.7.5

- Added a global toast notification system for Inertia flash messages and removed page-specific flash banners so success, error, warning, info, and status messages display consistently across the app.
- Fixed the dashboard `Your Sales Performance` revenue figures to subtract allocated, non-voided credit notes from invoice totals so sales trends reflect net invoice value.
- Fixed credit notes so opening an invoice-linked note no longer downgrades a `paid` status back to `authorised` during balance refresh.
- Fixed Xero chart-of-accounts imports to relink existing local accounts by code, map more Xero account types correctly, and scope account-code uniqueness per company to prevent duplicate-key sync failures.
- Scoped document-number uniqueness per company for quotes, invoices, jobcards, purchase orders, and credit notes, and updated related Xero matching plus product SKU/barcode validation to avoid cross-company duplicate conflicts.
