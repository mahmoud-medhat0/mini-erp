# DEPLOYMENT ENVIRONMENT & SECRETS CHECKLIST

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


**Target Stack:** Laravel 13.x + Inertia.js + React + PostgreSQL 15+  
**Scope:** Variable name documentation, deployment environment rules, validation methods, and audit rules.  
**Security Policy:** Variable names only. Zero private values, secrets, or production credentials stored in repository documentation.

---

## 1. Application Identity & Base Settings

| Variable Name | Purpose | Required Environments | Value Category / Format | Owner/Operator Notes | Validation Method |
|---|---|---|---|---|---|
| `APP_NAME` | Defines application name for UI headers, page titles, and system emails. | Local, Staging, Production | String (e.g. `"Mini ERP"`) | Must match organization branding. | Verify application UI page title and header branding. |
| `VITE_APP_NAME` | Exposes application name to Inertia React frontend bundle. | Local, Staging, Production | String reference (e.g. `"${APP_NAME}"`) | Loaded by Vite at asset build time (`npm run build`). | Inspect compiled HTML title in browser DOM. |
| `APP_ENV` | Sets execution environment mode (`local`, `staging`, `production`). | Local, Staging, Production | Enum string (`local` / `staging` / `production`) | Must be set to `staging` or `production` on target servers. | Run `php artisan env` on host server. |
| `APP_KEY` | 32-byte encryption key for session cookies, encrypted model attributes, and signed URLs. | Local, Staging, Production | Base64 string (`base64:...`) | Must be generated unique per environment host. NEVER reuse across hosts. | Run `php artisan key:generate` during deployment setup. |
| `APP_DEBUG` | Controls detailed error stacktrace display in web responses. | Local (`true`), Staging (`false`), Production (`false`) | Boolean string (`true` / `false`) | **MUST BE `false` IN STAGING & PRODUCTION.** | Trigger 404/500 error; verify generic error page is shown without stacktrace. |
| `APP_URL` | Canonical HTTP/HTTPS root URL of the application. | Local, Staging, Production | Full URL string (`https://erp.example.com`) | Must use `https://` protocol in staging and production environments. | Verify generated notification links and asset URLs. |

---

## 2. Localization & Timezone

| Variable Name | Purpose | Required Environments | Value Category / Format | Owner/Operator Notes | Validation Method |
|---|---|---|---|---|---|
| `APP_LOCALE` | Default application UI display language. | Local, Staging, Production | Two-letter ISO code (`en` / `ar`) | System default is `en` or `ar`. Users can toggle in UI header. | Check initial page load language context. |
| `APP_FALLBACK_LOCALE` | Fallback language when translation keys are missing. | Local, Staging, Production | Two-letter ISO code (`en`) | Defaults to `en`. | Verify missing key fallback rendering. |
| `APP_FAKER_LOCALE` | Locale for demo data generation and seeders. | Local, Staging | Locale string (`en_US` / `ar_SA`) | Used only during test/seeder execution. | Run `php artisan db:seed` in test env. |

---

## 3. Database Connection (PostgreSQL)

| Variable Name | Purpose | Required Environments | Value Category / Format | Owner/Operator Notes | Validation Method |
|---|---|---|---|---|---|
| `DB_CONNECTION` | Primary database driver name. | Local, Staging, Production | Driver name (`pgsql`) | Must be `pgsql` for production transactional lock compliance. | Execute `php artisan db:show`. |
| `DB_HOST` | Hostname or IP address of PostgreSQL server. | Local, Staging, Production | Hostname or IP (`127.0.0.1` / RDS endpoint) | Private network hostname or localhost loopback. | Execute `php artisan db:monitor`. |
| `DB_PORT` | Port number of PostgreSQL service. | Local, Staging, Production | Integer port (`5432` / `55432`) | Standard PostgreSQL port is 5432. | Test TCP port connectivity (`nc -zv $DB_HOST 5432`). |
| `DB_DATABASE` | PostgreSQL database instance name. | Local, Staging, Production | String name (`mini_erp_production`) | Isolated database name per environment host. | Verify connected DB name via `php artisan db:show`. |
| `DB_USERNAME` | Database service account username. | Local, Staging, Production | String username (`erp_user`) | Requires DDL and DML privileges on `public` schema. | Test database migration run (`php artisan migrate:status`). |
| `DB_PASSWORD` | Database service account authentication password. | Local, Staging, Production | Secret String | Secure random string; keep in host secrets manager. | Test database query execution via `/health`. |
| `DB_SSLMODE` | SSL/TLS mode for PostgreSQL connection. | Staging, Production | SSL Mode string (`prefer` / `require` / `verify-full`) | Set to `require` or `verify-full` for managed cloud DBs. | Inspect database connection handshake logs. |

---

## 4. Security & Password Hashing

| Variable Name | Purpose | Required Environments | Value Category / Format | Owner/Operator Notes | Validation Method |
|---|---|---|---|---|---|
| `HASH_DRIVER` | Primary password hashing algorithm. | Local, Staging, Production | Driver name (`argon2id` / `bcrypt`) | Default is `argon2id` for enterprise password security. | Create user and verify hashed password prefix (`$argon2id$`). |
| `ARGON_MEMORY` | Memory cost limit for Argon2id hashing in kibibytes. | Local, Staging, Production | Integer KiB (`19456` = 19MB) | Tune according to server RAM allocation. | Test password hashing execution time. |
| `ARGON_THREADS` | Thread concurrency count for Argon2id hashing. | Local, Staging, Production | Integer count (`1`) | Match available CPU core threads. | Test user login under load. |
| `ARGON_TIME` | Time cost / iteration count for Argon2id hashing. | Local, Staging, Production | Integer count (`2`) | Controls computational complexity. | Verify login latency remains under 200ms. |

---

## 5. Bootstrap Seeding (Optional Release Setup)

| Variable Name | Purpose | Required Environments | Value Category / Format | Owner/Operator Notes | Validation Method |
|---|---|---|---|---|---|
| `ERP_SEED_BOOTSTRAP_USER` | Enables automatic Super Admin user creation during seeding. | Local, Staging (Initial setup) | Boolean string (`true` / `false`) | Set to `false` in production after initial user creation. | Run `php artisan db:seed --class=UserSeeder`. |
| `ERP_BOOTSTRAP_USER_NAME` | Display name for initial bootstrap admin user. | Local, Staging | String (`"System Admin"`) | Used only during bootstrap seeding. | Check seeded user in `users` table. |
| `ERP_BOOTSTRAP_USER_EMAIL` | Email address for initial bootstrap admin user. | Local, Staging | Email string (`admin@example.com`) | Valid corporate email address. | Attempt login with bootstrap email. |
| `ERP_BOOTSTRAP_USER_PASSWORD` | Password for initial bootstrap admin user. | Local, Staging | Secret String | Temporary strong password; force change on first login. | Test initial admin user authentication. |
| `ERP_BOOTSTRAP_USER_ASSIGN_ROLE` | Automatically assigns Super Admin role to bootstrap user. | Local, Staging | Boolean string (`true`) | Assigns non-tenant global Super Admin role. | Verify Spatie role assignment in DB. |
| `ERP_BOOTSTRAP_USER_ROLE` | Spatie role name for bootstrap user. | Local, Staging | Role name (`SUPER_ADMIN`) | Must match allowlisted Spatie role. | Verify permission gates for bootstrap user. |

---

## 6. Session, Cache, & Queue Backends

| Variable Name | Purpose | Required Environments | Value Category / Format | Owner/Operator Notes | Validation Method |
|---|---|---|---|---|---|
| `SESSION_DRIVER` | Driver for user session persistence. | Local, Staging, Production | Driver name (`database` / `redis`) | Default is `database` using PostgreSQL `sessions` table. | Log in, inspect `sessions` table row creation. |
| `SESSION_LIFETIME` | Session inactivity timeout in minutes. | Local, Staging, Production | Integer minutes (`120` = 2 hours) | Configures security session expiration window. | Test automatic logout after inactivity window. |
| `SESSION_ENCRYPT` | Encrypts session data payload in storage backend. | Staging, Production | Boolean string (`false` / `true`) | Recommended `true` for high-security environments. | Inspect raw session table payload byte format. |
| `CACHE_STORE` | Backend store for application caching and rate limiting. | Local, Staging, Production | Store name (`database` / `redis`) | Default is `database` using PostgreSQL `cache` table. | Execute `php artisan cache:clear`. |
| `QUEUE_CONNECTION` | Background job processing queue backend driver. | Local, Staging, Production | Driver name (`database` / `redis`) | Default is `database` using PostgreSQL `jobs` table. | Dispatch test job and verify `jobs` table processing. |

---

## 7. Storage, Filesystem, & Cloud Attachments

| Variable Name | Purpose | Required Environments | Value Category / Format | Owner/Operator Notes | Validation Method |
|---|---|---|---|---|---|
| `FILESYSTEM_DISK` | Default disk for document attachments and exports. | Local, Staging, Production | Disk name (`local` / `s3`) | `local` uses `storage/app/private`; `s3` uses object storage. | Upload document attachment via UI. |
| `FILESYSTEM_LOCAL_SERVE` | Controls Laravel framework direct serving routes for the local private disk. | Local, Staging, Production | Boolean string (`false` recommended) | Keep `false` so private attachments are delivered only through authenticated application routes. | Confirm `/storage/*` routes are absent from `php artisan route:list`. |
| `AWS_ACCESS_KEY_ID` | Access key for AWS S3 object storage (if used). | Staging, Production *(if S3 enabled)* | AWS Key ID string | Required only if `FILESYSTEM_DISK=s3`. | Upload test file to S3 bucket. |
| `AWS_SECRET_ACCESS_KEY` | Secret key for AWS S3 object storage (if used). | Staging, Production *(if S3 enabled)* | AWS Secret String | Keep in host secrets manager. | Upload test file to S3 bucket. |
| `AWS_DEFAULT_REGION` | AWS region hosting S3 storage bucket. | Staging, Production *(if S3 enabled)* | Region string (`us-east-1` / `eu-central-1`) | Match cloud provider bucket region. | Verify region S3 endpoint resolution. |
| `AWS_BUCKET` | AWS S3 storage bucket name. | Staging, Production *(if S3 enabled)* | Bucket name string | Bucket must be private with no public read access. | Download document attachment via signed route. |

---

## 8. Mail & Notification Delivery

| Variable Name | Purpose | Required Environments | Value Category / Format | Owner/Operator Notes | Validation Method |
|---|---|---|---|---|---|
| `MAIL_MAILER` | Mail delivery transport driver. | Local (`log`), Staging (`log` / `smtp`), Production (`smtp` / `ses`) | Driver name (`log` / `smtp` / `ses`) | `log` writes emails to `storage/logs/laravel.log`. | Trigger password reset; verify delivery. |
| `MAIL_HOST` | Hostname of SMTP relay server. | Staging, Production *(if SMTP)* | Hostname string | Provider SMTP endpoint hostname. | Test SMTP socket connection. |
| `MAIL_PORT` | Port number of SMTP relay server. | Staging, Production *(if SMTP)* | Integer port (`587` / `465` / `2525`) | 587 (TLS) or 465 (SSL). | Test SMTP connection handshake. |
| `MAIL_USERNAME` | Authentication username for SMTP service. | Staging, Production *(if SMTP)* | String username | Transactional mail account username. | Verify SMTP authentication response. |
| `MAIL_PASSWORD` | Authentication password for SMTP service. | Staging, Production *(if SMTP)* | Secret String | Secure SMTP password / API token. | Verify SMTP authentication response. |
| `MAIL_ENCRYPTION` | Transport encryption protocol for SMTP (`tls` / `ssl`). | Staging, Production *(if SMTP)* | Protocol string (`tls` / `ssl`) | Set `tls` for port 587. | Verify TLS handshake during mail send. |
| `MAIL_FROM_ADDRESS` | Sender email address for outgoing system emails. | Local, Staging, Production | Email string (`noreply@example.com`) | Must match verified domain SPF/DKIM records. | Inspect `From` header on received email. |
| `MAIL_FROM_NAME` | Sender display name for outgoing system emails. | Local, Staging, Production | String (`"${APP_NAME}"`) | Defaults to `APP_NAME`. | Inspect sender name on received email. |

---

## 9. Logging & Observability

| Variable Name | Purpose | Required Environments | Value Category / Format | Owner/Operator Notes | Validation Method |
|---|---|---|---|---|---|
| `LOG_CHANNEL` | Primary log handling channel. | Local, Staging, Production | Channel name (`stack` / `daily` / `syslog`) | `stack` combines channels defined in `LOG_STACK`. | Trigger test error; check log output. |
| `LOG_STACK` | Comma-separated list of log channels for stack driver. | Local, Staging, Production | String list (`single` / `daily`) | `daily` rotates log files automatically. | Inspect `storage/logs/` file creation. |
| `LOG_LEVEL` | Minimum severity level logged to log channel. | Local (`debug`), Staging (`info`), Production (`info` / `warning`) | Log level (`debug` / `info` / `notice` / `warning` / `error`) | `info` or `warning` prevents disk bloat in production. | Log `debug` message; verify suppression in production. |

---

## 10. Audit & Checklist Summary

1. **Secrets Isolation**: All credentials (`DB_PASSWORD`, `APP_KEY`, `MAIL_PASSWORD`, `AWS_SECRET_ACCESS_KEY`, `ERP_BOOTSTRAP_USER_PASSWORD`) must be injected into the server environment via system environment variables, Docker secrets, or systemd environment files. NEVER commit actual secret values into git repository files.
2. **Template Completeness**: `laravel/.env.example` has been audited and updated to contain placeholder references for all supported configuration options (including `DB_SSLMODE` and `MAIL_ENCRYPTION`).
3. **Validation Routine**: Run `php artisan config:show` or `php artisan env` during server deployment setup to confirm all environment variables are correctly parsed.
