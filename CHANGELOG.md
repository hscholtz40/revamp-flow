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

## [v0.1.3] - 2025-10-03

### Added
- Web-based installer at `/install` for no-SSH deployments.
- Version display in app footer (reads from package.json or git).
- Admin user seeder with configurable credentials via env vars.
- Fallback UserFactory for production environments without Faker.

### Fixed
- Dashboard null company access with safe fallback UI.
- File permission errors in production with improved error handling.

### Notes
- Installer configures .env, runs migrations/seeders, creates admin user.
- Version shows in footer: package.json version, git tag, or commit hash.

### Fixed
- Migrations: ensured `company_id` columns and foreign keys are consistent across `customers`, `contacts`, and `xero_settings` with safe ordering.
- Hardened Xero settings migration to handle missing default company and ensure column existence before constraints.
- Added `doctrine/dbal` to support column changes in migrations.

### Notes
- No breaking schema changes; fresh migrate recommended for new deployments.

