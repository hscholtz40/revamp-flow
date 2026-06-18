# Public query API — local setup & testing

How to run the public query API (`POST /api/queries`) locally and test it,
including pointing the Revamp landing page at a local JobCardOnline (JCO).

The endpoint, controller, middleware, config and migrations are already in the
codebase — see [query-form-integration.md](../../revamp/docs/query-form-integration.md)
for the full contract. This doc only covers **getting it running locally**.

## Ports

The two Laravel apps both default `APP_URL` to port 8000, so they collide.
For local dev we split them:

| App             | Port | Started with                                    |
|-----------------|------|-------------------------------------------------|
| Revamp          | 8000 | `php artisan serve --port=8000` (from `revamp/`) |
| JobCardOnline   | 8001 | `php artisan serve --port=8001` (from `JobCardOnline/`) |

> Port 8080 is taken by Apache (the `bizcircle.local` vhost), so don't use it
> for `php artisan serve`.

## 1. Create the shared API key

The key is a shared secret. JCO checks the inbound `X-Api-Key` header against it
with `hash_equals`; Revamp sends the same value. Generate any high-entropy
string — for example:

```bash
php -r "echo bin2hex(random_bytes(24)).PHP_EOL;"
```

(or `openssl rand -hex 24`). Use the **same value** on both sides.

## 2. Configure JobCardOnline (`JobCardOnline/.env`)

```env
APP_URL=http://127.0.0.1:8001

# Shared secret for POST /api/queries (sent by external sites as X-Api-Key).
QUERY_API_KEY=<the key from step 1>
```

Then clear the config cache so the new value is picked up:

```bash
php artisan config:clear
```

> Without `QUERY_API_KEY` the endpoint returns **503** ("Query API is not
> configured"). With a wrong/missing header it returns **401**.

## 3. Configure Revamp (`revamp/.env`)

```env
JOBCARDONLINE_URL=http://127.0.0.1:8001   # where JCO is listening
JOBCARDONLINE_API_KEY=<same key as JCO QUERY_API_KEY>
JOBCARDONLINE_COMPANY_ID=                 # blank = JCO's default company
```

```bash
php artisan config:clear
```

`JOBCARDONLINE_URL` is the single lever for testing against a different target —
point it at a local JCO (`http://127.0.0.1:8001`) or a deployed one
(`https://jco.example.com`).

## 4. Run both apps

```bash
# terminal 1 — JobCardOnline
cd D:/Apache24/htdocs/JobCardOnline && php artisan serve --port=8001

# terminal 2 — Revamp
cd D:/Apache24/htdocs/revamp && php artisan serve --port=8000
```

Submit the landing-page query form on Revamp (http://127.0.0.1:8000); it forwards
to JCO, and the query appears under JCO's **Queries** module.

## 5. Test the API directly (no Revamp needed)

`scripts/test-query-api.sh` posts a sample query (with a generated 1×1 PNG
attachment) to any JCO instance. It reads `QUERY_API_KEY` from `.env` by default.

```bash
# default: http://127.0.0.1:8001, key from .env
bash scripts/test-query-api.sh

# point at a different URL (this is how you test other targets)
BASE_URL=https://jco.example.com API_KEY=xxxxx bash scripts/test-query-api.sh

# skip the file upload, or attach to a specific company
NO_ATTACHMENT=1 bash scripts/test-query-api.sh
COMPANY_ID=1 bash scripts/test-query-api.sh
```

A success looks like:

```
--- HTTP 201 ---
{"message":"Your query has been submitted. We will get back to you shortly.","id":12}
```

Equivalent raw curl:

```bash
curl -X POST http://127.0.0.1:8001/api/queries \
  -H "X-Api-Key: $QUERY_API_KEY" -H 'Accept: application/json' \
  -F name=Test -F surname=User -F email=test@example.com \
  -F cell=+27123456789 -F 'description=Hello' \
  -F 'attachments[0]=@/path/to/image.png'
```

## Response codes

| Code | Meaning |
|------|---------|
| 201  | Created — `{ "message": "...", "id": <queryId> }` |
| 422  | Validation failed — `{ "message": "...", "errors": { field: [...] } }` |
| 401  | Missing/invalid `X-Api-Key` |
| 404  | No company available to receive the query |
| 503  | `QUERY_API_KEY` not configured on JCO |
