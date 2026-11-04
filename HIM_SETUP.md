# HIM Health Information System

Laravel 12 API and React 19/Vite client for a barangay health center. Patient data and clinical records are protected by authenticated API routes; use HTTPS in every deployed environment.

## Local setup

1. Install PHP 8.2+, Composer, Node.js 20+, and MySQL/MariaDB.
2. Install PHP and frontend dependencies with `composer install` and `npm install`.
3. Copy `.env.example` to `.env`, generate `APP_KEY`, and configure `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for a dedicated database.
4. Run `php artisan migrate --seed` to create the EHR schema and development user/catalog records.
5. Run `npm run build`, then serve the application with `php artisan serve`.

The seeded development accounts are `admin@bhc.gov.ph` and `nurse.maria@bhc.gov.ph`, both with the requested initial password `password`. Change these credentials immediately and remove or replace the development seeder before production deployment.

## Implementation map

- `database/migrations/2026_11_02_000000_create_him_ehr_tables.php` creates the eight clinical application tables; the original Laravel users migration includes the role enum.
- `database/migrations/2026_11_02_000001_create_personal_access_tokens_table.php` stores Sanctum tokens.
- `app/Models` contains the Eloquent models and relationships.
- `database/seeders` seeds two staff accounts, 15 vaccine schedule doses, and common ICD-10 diagnoses.
- `routes/api.php` defines the `/api/v1` API; `app/Http/Controllers/Api` contains auth and EHR handlers. Staff administration is restricted by `EnsureSuperAdmin`.
- `resources/js/app.jsx` is the React interface; Dexie stores the offline queue and directory/catalog caches. `public/service-worker.js` caches only the application shell and built static assets, never `/api/` responses.

## API summary

- Auth: `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`
- Staff: `GET|POST /api/v1/users`, `PUT /api/v1/users/{user}` (super admin)
- Households/patients: `GET|POST /api/v1/households`, `GET|POST /api/v1/patients`, `PUT|DELETE /api/v1/patients/{patient}`
- Clinical: `GET|POST /api/v1/encounters`, `GET /api/v1/icd10`
- Immunization: `GET /api/v1/vaccine-schedules`, `GET|POST /api/v1/immunizations`
- Offline sync: `POST /api/v1/sync` accepts up to 100 create transactions per request and de-duplicates by device and transaction ID.
- Reporting: `GET /api/v1/reports/fhsis/diagnoses?from=YYYY-MM-DD&to=YYYY-MM-DD`

All endpoints other than login require `Authorization: Bearer <token>`.

## Production deployment checklist

- Replace the seeded passwords and restrict account provisioning; configure token expiration and regularly revoke unused tokens.
- Use TLS, secure server headers, least-privilege database credentials, encrypted backups, tested restoration, access auditing, monitoring, and an incident response process.
- Review role permissions and the applicable Philippine Data Privacy Act/health-record obligations with the health center's privacy officer. Use a formal threat model and security review before storing real patient information.
- The SMS feature only builds a message preview; it does not send SMS. Connect an approved, consent-aware SMS provider before enabling delivery.
- The dashboard is an operational starting point, not a substitute for clinical validation of vaccine eligibility or diagnosis coding. Confirm schedules and clinical rules against current DOH guidance.

## Validation

Run `php artisan test` and `npm run build` from the Laravel project directory.


## UI and offline reliability updates

- The interface uses a consistent, restrained **glassmorphism** design across the sidebar, sticky top navigation, dashboard metric cards, content panels, schedule cards, status chips, and login form. Clinical text, table content, and form fields retain high-contrast surfaces for legibility.
- Page and card motion is subtle and respects the user's reduced-motion preference.
- Offline cache versioning is updated by the service worker; only the HTML shell and successful same-origin Vite build assets are cached. API responses are never cached by the service worker.
- Sync retries compare a transaction fingerprint and only acknowledge transactions already marked as processed. Pending and failed transactions can be retried, and client entity IDs are checked before creating duplicate patient, encounter, or immunization rows.
- The date used by the report form is calculated in the browser's local timezone.

## Browser console troubleshooting

Requests such as `/hybridaction/...` are not application routes in this repository. If those requests appear together with errors from a `chrome-extension://` URL, reproduce the issue in a private window with browser extensions disabled before changing Laravel routes. The service worker does not intercept `/api/` requests.
