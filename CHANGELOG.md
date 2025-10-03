## [v0.1.0] - 2025-10-02

### Added
- Initial public release of JobCardOnline.
- Laravel 12 backend with Fortify authentication and Inertia.js Vue 3 frontend.
- Core modules: Users, Groups & Permissions, Customers, Contacts, Products, Quotes, Invoices, Jobcards, Payments, Settings.
- Bulk SMS integration and Xero sync services.
- Vite-based build with SSR bundle.

### Notes
- Build assets are generated into `public/build` and SSR bundle into `bootstrap/ssr`.
- Package includes production Composer autoload (no dev dependencies).


## [v0.1.1] - 2025-10-03

## [v0.1.2] - 2025-10-03

### Fixed
- Xero settings migration: avoid duplicate foreign key creation on production upgrades.

### Notes
- Fresh installs and upgrades are now consistent for `xero_settings` constraints.

### Fixed
- Migrations: ensured `company_id` columns and foreign keys are consistent across `customers`, `contacts`, and `xero_settings` with safe ordering.
- Hardened Xero settings migration to handle missing default company and ensure column existence before constraints.
- Added `doctrine/dbal` to support column changes in migrations.

### Notes
- No breaking schema changes; fresh migrate recommended for new deployments.

