# Progress and phase gates

## Phase 0 · 21 November 2026

The workspace started empty. The project directory and immediate parent contained no applicable project instruction file. No Git metadata, application code, dependency manifests, migrations, or tests were present. PowerShell could resolve `rg`, while Git, Docker, Node.js, PHP and Composer were not available on its PATH during inspection.

| Deliverable | Location |
|---|---|
| Runtime, monorepo, domain ownership and conventions | [Architecture](architecture.md) |
| Scope, acceptance and lifecycle definitions | [Requirements](requirements.md) |
| Entity model, ERD and persistence invariants | [Database](database.md) |
| Initial routes and request/response conventions | [API](api.md) |
| Roles, capabilities, review and object scoping | [Permissions](permissions.md) |
| Dependencies, assumptions and business decision owners | [Decisions](decisions.md) |
| Phases, initial backlog and release gates | [Implementation plan](implementation-plan.md) |

Phase 0 is a design deliverable. Runtime checks begin once the foundation exists. See the validation record below.

## Validation record

- Checked eight requested UTF-8 Markdown documents, 15 relative document links and paired code fences.
- Checked coverage of 37 required table names, 14 required API groups, eight roles, Phases 0–7 and ten tracked business decisions. Reviewed 30 logical ERD relationships against entity descriptions.
- Cross-checked lifecycle guards against permissions and posting flows for driver replay, mixed-load attribution, stock/QC and invoice/payment corrections.
- Reviewed upstream support for the proposed runtime lines and documented the source links. Container compatibility, SQL migrations, rendered ERD and application tests remain unverified at this design stage.

## Review handoff

Repository instructions are recorded in [AGENTS.md](../AGENTS.md). They apply to future tasks, including phase review and deployment approvals.

The Phase 0 review led to explicit authorization for Phase 1. Business questions B01–B10 remain tracked in [decisions](decisions.md).

## Phase 1 · Foundation

Phase 1 is complete. Laravel and Next.js started together on a temporary loopback origin with PostgreSQL 18.7 and Redis 8.10.3. The identity and queue tests used real services. The local proxy forwarded Laravel and Next.js on the same origin. The project owner reported a green Foundation checks run on GitHub on 22 November 2026, closing the container verification gate. The workflow checks Docker Compose startup, readiness and login through Nginx, backend tests and browser integration.

| Changed area | Files |
|---|---|
| Laravel and authenticated API | [Backend](../apps/api), with [identity](../apps/api/app/Modules/IdentityAccess), [infrastructure](../apps/api/app/Modules/SharedInfrastructure), [routes](../apps/api/routes/api.php), [config](../apps/api/config) and Composer manifests |
| Persistence | [Identity and infrastructure migrations](../apps/api/database/migrations), [reference and role seeder](../apps/api/database/seeders/DatabaseSeeder.php), and local/interactive provisioning in [console routes](../apps/api/routes/console.php) |
| Bahasa Indonesia UI | [Frontend](../apps/web), including the login, authenticated shell, dashboard, account management and audit screens, brand components and npm manifests |
| Automation and environment | [Compose](../compose.yaml), [Dockerfiles and Nginx](../infra), root and API environment examples, [CI](../.github/workflows/phase-1.yml), [PHPUnit](../apps/api/tests) and [Playwright](../apps/web/tests/e2e/foundation.spec.ts) |
| Documentation | [README](../README.md), [local development](development.md), [architecture](architecture.md), [database](database.md), [permissions](permissions.md), [API guide](api.md), and [OpenAPI](../contracts/openapi.yaml) with its lint config |

### Commands and results

Commands below omit generated credential values. Portable executables and local data used the ignored `.laras-tools/` directory.

| Area | Commands actually executed and outcomes |
|---|---|
| Workspace and tools | `Get-ChildItem`, `Get-Content`, `Get-Command`, `rg` and `wsl.exe --list --verbose` established the workspace and host baseline. `curl.exe`, `tar.exe` and `Get-FileHash` retrieved, extracted and checked portable dependencies where upstream checksums were available. Official registry queries returned HTTP 200 for all six referenced image tags. |
| PHP dependencies and routing | `php -v`, `php -m`, `composer update --no-interaction --no-scripts --prefer-dist`, `composer update --lock --no-install`, `composer validate --strict`, `composer check-platform-reqs`, `php artisan package:discover --ansi` and `php artisan route:list --except-vendor`. The restricted download context blocked Fileinfo and PDO PostgreSQL. The lock was resolved there with `--ignore-platform-req=ext-fileinfo`, then all platform requirements, including Fileinfo, were checked successfully in the local execution context, where PDO PostgreSQL also loaded. |
| PostgreSQL and readiness | Ran `initdb.exe`, `postgres.exe` and `psql.exe` with a dedicated local PostgreSQL cluster, a non-superuser app role and a separate test database. `php artisan migrate --seed --force`, `php artisan db:seed --force`, `php artisan identity:seed-demo` and `php artisan migrate:status` all passed on the real database. The first status attempt before the service started received connection refused. |
| Redis and local startup | Memurai standalone attempts did not listen. Both checksum-verified Redis 8 test instances started on loopback. Started `php -S 127.0.0.1:8081 -t public public/index.php`, `npm run start -- --hostname 127.0.0.1 --port 3000` and the temporary Node loopback proxy. `curl.exe` verified HTTP 200 for login, liveness and readiness on the shared origin. |
| Backend tests | `php vendor/bin/pint`, `composer lint`, filtered PHPUnit checks and `composer test`. The initial `php artisan test` command was unavailable with the installed packages, so the Composer script now starts PHPUnit directly. Fixed PHPUnit environment isolation, account session setup in the permission test, repeat migration behavior for the PostgreSQL trigger function, and UTC database connections. Final run passed **10 tests, 77 assertions**, including a real Redis enqueue/worker cycle. |
| Frontend and browser | `npm install`, clean-directory `npm install --package-lock-only --ignore-scripts`, `npm ci`, `npm ls --depth=0`, `npm run lint`, `npm run typecheck`, `npm run contract:lint`, `npm run build -- --webpack`, `npm run build`, `npm run test:e2e -- --list` and `npm run test:e2e`. Recovered from restricted native SWC loading with Next's supported fallback, then verified the default Turbopack build. Regenerated the lock with supported ESLint 10 after the existing ESLint 9 graph conflicted. Fixed browser assertions to target the page's denial notice and the specific audited account. Final browser run passed **3 of 3** in Chrome on the real local stack. |
| Dependency advisories | `npm audit --omit=dev --audit-level=high` found zero runtime vulnerabilities. `composer audit --locked --no-interaction` reported zero advisories. |
| Docker and CI | During local verification, standalone Docker Compose v5.7.1 passed `config --quiet` and `config --services`. At that time `up -d --build` failed because the Docker engine named pipe was absent. The project owner subsequently reported a successful GitHub Actions run. |
| Final checks and shutdown | `composer install --no-interaction --prefer-dist --no-progress` passed directly from the lockfile. PowerShell checked 45 local Markdown links and verified root environment template separation. Shut down the workspace-local processes with `redis-cli SHUTDOWN NOSAVE`, `pg_ctl stop -m fast` and `Stop-Process` restricted to the portable executable paths. |

Phase 2 followed after the owner authorized Customer and Contract Management. The five business modules remain scheduled for Phases 2–6.

## Git setup for CI

Initialized the local repository on `main` using Codex's bundled Git after Windows PowerShell could not find `git`. The owner completed the first push to GitHub, and local `main` tracks `origin/main`.

## Phase 2 · Customer and Contract Management · 22 November 2026

Implemented and verified locally. The Indonesian customer directory and detail pages support category and status filters, contacts, pickup locations and a finance-gated billing profile. The contract pages support drafts, services, effective-dated IDR rates, weekly schedules, activation, suspension, and status history. The application derives effective expiry from the Jakarta date. PostgreSQL enforces customer ownership of service locations, valid ranges and overlap protection.

| Area | Files |
|---|---|
| PostgreSQL | [Customer and contract migration](../apps/api/database/migrations/2026_11_22_000001_create_customers_and_contracts.php) and [reference seeder](../apps/api/database/seeders/DatabaseSeeder.php) |
| API and authorization | [Customers](../apps/api/app/Modules/Customers), [Contracts](../apps/api/app/Modules/Contracts), [route files](../apps/api/routes), [permission gates](../apps/api/app/Providers/AppServiceProvider.php) and [OpenAPI](../contracts/openapi.yaml) |
| Office UI | [Management pages and components](../apps/web/src/features/management), [customer routes](../apps/web/src/app/(workspace)/customers), [contract routes](../apps/web/src/app/(workspace)/contracts) and [workspace navigation](../apps/web/src/features/session/workspace.tsx) |
| Regression tests | [PHPUnit](../apps/api/tests/Feature/CustomerContractTest.php) and [Playwright](../apps/web/tests/e2e/customer-contract.spec.ts) |
| Documentation | [API](api.md), [database](database.md), [permissions](permissions.md) and [business assumptions](decisions.md) |

### Verification

- Reused workspace-local PostgreSQL 18.7 and Redis 8.10.3 services, with an isolated PostgreSQL test database. `php artisan migrate --seed --force` upgraded the existing local Phase 1 schema. `php artisan migrate:status` showed all three migrations applied. PHPUnit also exercised clean migrations.
- `php vendor/bin/phpunit --filter CustomerContractTest` passed 4 tests with 107 assertions. The full `php vendor/bin/phpunit` suite passed 14 tests with 184 assertions. Coverage includes forbidden pricing and profile access, cross-customer location ownership, database constraints, rate overlap, Jakarta expiry, and lifecycle history. Pint initially needed a writable scratch directory for its PHAR and then passed `--test`.
- `npm run lint`, `npm run typecheck`, `npm run contract:lint` and `npm run build` passed. Compared Laravel's route listing to all 29 documented Phase 2 OpenAPI operations. `git diff --check` passed.
- Started PHP and Next.js behind the temporary same-origin proxy and checked readiness over HTTP. `npm run test:e2e` passed all four Chromium tests, including customer registration through contract activation and suspension.

Phase 2 uses starter customer categories, contract-specific service names and explicit payment terms. Review the definitive company categories and service names before operational adoption. Customer pickup history follows in Phase 3 and invoice history follows in Phase 6. Weekly schedule definitions are available for Phase 3 task generation. Hosted CI for the Phase 2 changes remains pending a push. The next phase is Collection Management and the Driver PWA, subject to explicit authorization.

### Local preview

Started the Phase 2 application on loopback with workspace-local PostgreSQL and Redis. Created a dedicated local preview account for operations and billing. Verified the login page, authenticated session, and customer and contract API reads. The credentials file is kept in the ignored local tools directory.
