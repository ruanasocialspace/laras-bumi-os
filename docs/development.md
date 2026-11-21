# Local development

Phase 1 runs Laravel 13 on PHP 8.5, Next.js 16 on Node.js 24, PostgreSQL 18, Redis 8 and an Nginx edge on one loopback origin. Install Docker with Compose v2 for the stack. Node.js 24 is only needed on the host for frontend development checks and Playwright. Docker builds the exact Composer and npm dependency locks.

## Generate local settings

Create a root `.env` using `.env.example` as the variable reference. Generate unique values for both database passwords and the Laravel application key. Keep the file local. Local demo accounts default to disabled. Do not rotate `LARAS_APP_KEY` during a session test or change PostgreSQL initialization credentials against an existing volume without updating the database roles too.

PowerShell, from the repository root. The command refuses to replace an existing `.env`.

```powershell
if (Test-Path -LiteralPath .env) { throw '.env already exists' }
function New-LarasLocalValue {
    $bytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    [Convert]::ToBase64String($bytes)
}
@(
    'LARAS_PORT=8080'
    "LARAS_APP_KEY=base64:$(New-LarasLocalValue)"
    "LARAS_DB_ADMIN_PASSWORD=$(New-LarasLocalValue)"
    "LARAS_DB_PASSWORD=$(New-LarasLocalValue)"
    'LARAS_DEMO_ACCOUNTS=false'
    "LARAS_DEMO_PASSWORD=$(New-LarasLocalValue)"
) | Set-Content -Encoding ASCII .env
```

## Start the stack

```sh
docker compose config --quiet
docker compose up -d --build
docker compose ps -a
curl --fail --retry 20 --retry-delay 2 --retry-all-errors http://localhost:8080/api/v1/health/ready
```

On Windows invoke `curl.exe` to use curl. The one-shot `migrate` service applies migrations and seeds the role and reference catalogs before starting Laravel, the worker and scheduler. On first startup, PostgreSQL creates `laras` and the isolated `laras_test` database and gives the non-superuser `laras` role ownership. Nginx is reachable only on loopback. Set `LARAS_PORT` if 8080 is busy and keep the same host and port for the session, CSRF bootstrap and browser tests.

Create the first administrator interactively from the local stack, then visit [Laras Bumi OS](http://localhost:8080/login).

```sh
docker compose exec api php artisan identity:bootstrap
```

For automated browser tests using a fresh local database, enable `LARAS_DEMO_ACCOUNTS=true` in `.env` and retain the generated `LARAS_DEMO_PASSWORD`. Run `docker compose up -d` to apply the environment change, then provision the fixtures. The development command creates `admin@laras.test` and `driver@laras.test` on demand. A repeated seed leaves existing passwords and grants intact. Use these accounts only in a disposable development or CI database.

```sh
docker compose exec api php artisan identity:seed-demo
```

The Compose development stack is loopback HTTP. For any future nonlocal deployment, terminate HTTPS at the edge, require secure session cookies, remove demo settings, use environment-specific origin settings, and use managed secrets and separate runtime and migration database roles. Deployment requires explicit approval.

## Run the checks

Run these commands from the repository root for the backend. The PHPUnit suite redirects its connection to the test database named by `DB_TEST_DATABASE` and refuses to migrate if its name does not end in `_test`. The Compose environment uses `laras_test`.

```sh
docker compose exec -T api composer validate --strict
docker compose exec -T api composer lint
docker compose exec -T api composer test
docker compose exec -T api php artisan migrate:status
docker compose exec -T api php artisan db:seed --force
```

Run these commands from `apps/web` and set the host environment variable `LARAS_DEMO_PASSWORD` from the local `.env` before the browser test. Set `LARAS_E2E_URL` if you changed ports. Chromium installs into Playwright's local browser cache. Set `LARAS_BROWSER_CHANNEL=chrome` if testing against an existing Google Chrome installation.

```sh
npm ci
npm run lint
npm run typecheck
npm run contract:lint
npm run build
npx playwright install chromium
npm run test:e2e
```

Review `docker compose logs migrate api worker scheduler edge web` if readiness or startup fails. Rebuild after source changes with `docker compose up -d --build`. Use `docker compose down` when finished to preserve named volumes. The contract is in [OpenAPI](../contracts/openapi.yaml) and CI is in [Foundation checks](../.github/workflows/phase-1.yml).
