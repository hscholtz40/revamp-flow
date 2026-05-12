# JobCardOnline

Laravel 12 + Vue 3 (Inertia) application for job cards, quotes, invoices, and related CRM workflows.

## Prerequisites

- **PHP** 8.2 or newer (see `composer.json` for `^8.2`)
- **Composer** 2.x
- **Node.js** 20+ (LTS recommended) and **npm**
- **MySQL** 8.x or **MariaDB** 10.5+ (local dev uses MySQL; this matches the shipped `.env.example`)

PHP extensions typically required by Laravel and this stack: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `bcmath` (add others if `composer install` or `php artisan` reports missing extensions).

## 1. Clone and install dependencies

```bash
git clone <repository-url> JobCardOnline
cd JobCardOnline

composer install
npm install
```

## 2. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` for your machine:

- **`APP_URL`** — usually `http://127.0.0.1:8000` or `http://localhost:8000` (must match how you open the app in the browser so Vite/Inertia assets resolve correctly).
- **Database** — configure MySQL in section 3 below (after creating an empty database).
- **Mail / cPanel / licensing** — use safe local placeholders unless you intentionally need those integrations; many features work with `QUEUE_CONNECTION=sync` and `MAIL_MAILER=log` for local testing.

> **Security note:** Treat `.env.example` as a template only. Do not commit real API keys, mail passwords, or tokens. Rotate any credentials that were ever committed to version control.

## 3. Database (MySQL)

1. Create an empty database (example name `jobcardonline_local`; use any name you prefer):

   ```sql
   CREATE DATABASE jobcardonline_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. In `.env`, set MySQL (adjust host, port, database name, user, and password for your machine):

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=jobcardonline_local
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. Ensure the MySQL user can connect from `DB_HOST` and has full privileges on `DB_DATABASE`.

## 4. Migrate (and optional seed)

```bash
php artisan migrate
```

Optional demo data (companies, groups, admin user, categories, products — see `database/seeders/DatabaseSeeder.php`):

```bash
php artisan db:seed
```

If seeding creates an admin user, use the credentials and flow documented in your team’s internal docs or `AdminUserSeeder` / installer behaviour for this project.

## 5. Storage link (uploads / public disk)

If the app serves user uploads from the `public` disk:

```bash
php artisan storage:link
```

## 6. Run the app (local dev)

**Recommended — one command** (PHP server, queue worker, and Vite from `composer.json`):

```bash
composer run dev
```

Then open **`APP_URL`** in the browser (e.g. `http://127.0.0.1:8000`).

**Manual alternative** (three terminals):

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

### Inertia SSR (optional)

Only if you need server-side rendering locally:

```bash
composer run dev:ssr
```

Ensure `INERTIA_SSR_ENABLED` and related settings in `.env` match your intent.

## 7. Tests and static analysis

```bash
composer test
npm run test:frontend
composer analyse   # PHPStan via Larastan (see composer.json)
```

## 8. Frontend quality (optional)

```bash
npm run lint
npm run typecheck
npm run format:check
```

## Troubleshooting

| Issue | What to try |
|--------|-------------|
| Blank page / wrong asset URLs | Set `APP_URL` to the same origin you use in the browser; clear config: `php artisan config:clear` |
| Database errors | Confirm MySQL is running, the database exists, and `DB_*` in `.env` matches (`php artisan migrate` will surface connection errors) |
| Permission / 419 errors | `php artisan key:generate`, ensure `APP_KEY` is set, session driver writable (`storage/framework/sessions`) |
| Stale config | `php artisan config:clear` and `php artisan cache:clear` |

## Project layout (high level)

| Path | Role |
|------|------|
| `app/` | Laravel application code |
| `routes/` | HTTP routes |
| `resources/js/` | Vue + Inertia SPA |
| `resources/views/` | Blade templates (including PDF views) |
| `database/migrations/` | Schema migrations |
| `tests/` | Pest / PHPUnit tests |

For product behaviour and API notes, see `app.md` and `CHANGELOG.md`.
