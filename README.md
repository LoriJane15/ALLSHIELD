# SHIELD

Laravel 12 rebuild of the legacy raw-PHP SHIELD platform — a Philippine multi-agency
ELCAC portal (Davao del Sur) tracking **Former Rebel (FR)** reintegration and the
**RCSP / IMPLAN** barangay community-support workflow.

Legacy app being replaced: `../shield` (procedural PHP + PDO, SkyDash Bootstrap template).

## Stack

Laravel 12 · PHP 8.2 · Blade · Tailwind v3 + Vite · Leaflet · MySQL/MariaDB.
Auth is **username-based** (Breeze, adapted) — there is no self-registration,
no email on file, and no email password reset. The Super Admin provisions accounts.

## Setup

Requires PHP 8.2+, Composer, Node 18+, and MySQL/MariaDB (XAMPP is fine).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set the database block in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=shield_db
DB_USERNAME=root
DB_PASSWORD=
FILESYSTEM_DISK=public

# Source DB for the one-off legacy import
LEGACY_DB_DATABASE=kp_datacenter
LEGACY_DB_USERNAME=root
LEGACY_DB_PASSWORD=
```

Then:

```bash
mysql -u root -e "CREATE DATABASE shield_db CHARACTER SET utf8mb4"
mysql -u root -e "CREATE DATABASE kp_datacenter CHARACTER SET utf8mb4"
mysql -u root kp_datacenter < "../shield/Database/kp_datacenter (6).sql"

php artisan migrate
php artisan import:legacy --fresh   # transforms kp_datacenter -> shield_db
php artisan storage:link
npm run build
php artisan serve
```

## Roles and areas

Each role area mirrors one legacy `accounts/<role>/` folder and is gated by the
`role` middleware.

| Role | Prefix | Legacy folder |
|---|---|---|
| `super_admin` | `/super-admin` | `super_admin/` |
| `admin` (Katuparan Center) | `/katuparan` | `katuparan_center/` |
| `lgu` (DILG) | `/lgu` | `dilg_lgu/` |
| `gov_agency` | `/agency` | `gov_agency/` |
| `mblrc` | `/mblrc` | `mblrc/` |
| `39th_ib` | `/39th-ib` | `39th-IB/` |
| `afp` | `/afp` | `afp/` |

## File layout

Uploads live on the `public` disk (`storage/app/public`, served via `storage:link`):

| Path | Contents | Legacy source |
|---|---|---|
| `rcsp/{rcsp_barangay_id}/` | RCSP monitoring form files | `accounts/dilg_lgu/file_uploads/` |
| `rcsp/_unmatched/` | Form files with no row in the April-2025 dump | same |
| `implan/agenda/` | IMPLAN agenda documents | `accounts/gov_agency/agendaFile_bank/` |
| `implan/photos/` | Implementation documentation photos | `accounts/gov_agency/documentation_photos/`, `uploads/documentation/` |
| `fr/certificates/` | FR assistance certificates | `accounts/mblrc/certificates/` |

Static assets are under `public/assets/` (SkyDash theme, images, vendors), plus:

| Path | Contents |
|---|---|
| `public/assets/uploadLogo/` | Per-user logos — referenced by `users.logo` |
| `public/assets/logoAgency/` | Agency logos — referenced by `gov_agencies.profile` |
| `public/assets/mapping/` | QGIS barangay polygon layers + styles (from `39th-IB/final_mapping/`) |
| `public/landing/` | The original static landing site |

## Tests

```bash
php artisan test
```

The `Smoke*`, `Ib39Map` and `RcspBroadcast` tests exercise real routes against the
**imported legacy dataset**, so they skip on the default in-memory SQLite suite.
To run them:

```bash
DB_CONNECTION=mysql DB_DATABASE=shield_db DB_USERNAME=root DB_PASSWORD= \
  php vendor/bin/phpunit
```

> `tests/TestCase.php` refuses to let `RefreshDatabase` run against any database
> outside `WIPEABLE`. Without that guard, running the suite with
> `DB_CONNECTION=mysql` lets the Breeze tests `migrate:fresh` your working
> `shield_db` and destroy the import. Those tests skip themselves instead.

## Real-time comments

RCSP comment threads broadcast over **Laravel Reverb**, replacing the legacy
Ratchet daemon (`websocket_server.php`). Comments are still saved over HTTP; the
broadcast only pushes them to the other party.

```bash
php artisan reverb:start     # alongside `php artisan serve`
```

`RcspCommentPosted` publishes on the private channel `rcsp-form.{id}` — the
legacy "room" keyed by form id. `routes/channels.php` applies the same
municipality rule the HTTP endpoints use, so an LGU can only listen to threads
for its own municipality (the legacy server let any client join any room).
With `BROADCAST_CONNECTION` unset the app runs normally, just without live push.

## Migration status

Working: all 7 role areas, auth, RCSP review + monitoring, IMPLAN lifecycle
(create → agency response → verify → reassign), FR registration and profiles,
file upload/serving, and the legacy data import.

Everything with real behaviour in the legacy app is now migrated, including the
39th-IB choropleth map, the branded error pages, and the real-time comment threads.

### UI

Every page uses the original **SkyDash** Bootstrap theme from `public/assets/`,
matching the legacy chrome per role:

| Layout | Roles | Legacy equivalent |
|---|---|---|
| `layouts/skydash-h` | Katuparan Center, AFP | `_navbar-top.php` + `_navbar-bottom.php` |
| `layouts/skydash-v` | all other roles | `_sidebar.php` |

There is **no Tailwind in any view** — `tailwind.config.js` remains only for the
build pipeline. The Breeze scaffolding views and components that shipped with the
starter kit were removed once every screen had been rebuilt on SkyDash.

Destructive actions use SweetAlert (`data-confirm` on the form, handled in
`resources/js/confirm-dialog.js`), as the legacy app did, falling back to the
native `confirm()` if the vendor script fails to load.

Three legacy jQuery plugins were **not** carried over, replaced by native
equivalents rather than dropped: `bootstrap-datepicker` → `<input type="date">`,
`dropify` → plain styled file inputs, `select2` → plain selects (it was used on
exactly one legacy page). DataTables, lightGallery, x-editable and bar-rating
were loaded by the legacy template but never actually called.

### Security parity with the legacy app

The legacy root `.htaccess` set `X-Content-Type-Options`, `X-Frame-Options` and
`X-XSS-Protection`; Apache is no longer in the picture, so
`App\Http\Middleware\SecurityHeaders` sets them (plus `Referrer-Policy`) instead.
`X-XSS-Protection` is sent as `0` — the old auditor is deprecated and itself
exploitable.

`config/security_helpers.php` has no direct port; Laravel covers it natively:

| Legacy helper | Now |
|---|---|
| `sanitizeInput`, `escapeOutput` | Blade escaping + form-request validation |
| `generateCSRFToken`, `validateCSRFToken` | Laravel CSRF middleware |
| `checkRateLimit` | `RateLimiter` in `LoginRequest` (5 attempts) |
| `validateFileUpload` | `mimes:` + `max:` rules on every upload |

### Legacy pages deliberately not ported

These are static mockups in the legacy app — hardcoded rows, no queries, and in
several cases a `foreach` over a variable that is never assigned. They render but
do nothing, so there is no behaviour to reproduce:

| Legacy file | Why |
|---|---|
| `katuparan_center/page_fr.php`, `page_fr_profile.php` | Hardcoded names + stock template faces |
| `katuparan_center/page_agency_implan_{afp,dilg,dnd,doh,dole}.php` | Static "Status of Accomplishment" markup |
| `katuparan_center/page_services.php` | Client-side navigation menu only |
| `dilg_lgu/page_rcsp_implementation_status.php` | 1528 lines, six copy-pasted phase tables, `$distinctForms` never assigned |
| `39th-IB/former-rebels.php`, `former-rebels-profile.php` | `$approvedForms` never assigned; "Pending" tab is fake data |

### Legacy pages that map to a differently-named route

| Legacy file | Now |
|---|---|
| `dilg_lgu/page_rscp_eval.php` | `/lgu/rcsp` (view is titled "RCSP Evaluation") |
| `katuparan_center/page_report.php` | `/katuparan/rcsp` ("Bulk File Submission") |
| `katuparan_center/page_view_completed_docu.php` | `/lgu/rcsp/{id}/monitoring` (compliance report) |
| `mblrc/fr_monitoring.php` | `/mblrc/former-rebels` |
| `39th-IB/add_rcsp.php` | `/39th-ib/areas` (same `frmap_barangays` update + colour history) |

## Local RCSP demonstration data

The application includes an explicitly invoked, non-official RCSP demonstration
catalog. It never runs from `DatabaseSeeder` and refuses production. After applying
the RCSP hardening migration in a local environment, set a strong password and run:

```powershell
$env:RCSP_DEMO_PASSWORD = 'ChooseYourOwnStrong123'
php artisan db:seed --class=RcspDemoSeeder
```

The password must be at least 12 characters and contain uppercase, lowercase, and
a number. Sign in as `rcsp_lgu_demo` for the municipality-scoped LGU workflow or
`katuparan_demo` for Katuparan review. All `DEMO` phases, activities, locations,
remarks, users, and records are synthetic test content—not official RCSP requirements.

Seeded examples appear under **RCSP Barangays** (LGU) and **RCSP Forms**
(Katuparan): pending, submitted, in-progress/returned, and completed. `DEMO Manual
Workflow Barangay` is intentionally not enrolled. Use **Add RCSP Barangay** and
**Submit**, then **View Form** to submit each phase. Katuparan opens the clickable
row in **Bulk File Submission**, reviews every activity, adds required return
remarks, and clicks **Submit**. The LGU corrects returned activities and submits
again. After all activities are approved, use **Proceed to Phase N** through Phase
5 and finally **Complete**. **View Phases** shows approved history.


## Known data caveat

`Database/kp_datacenter (6).sql` was generated **24 April 2025**, but the legacy app
kept changing until September 2025. The import therefore reflects an old snapshot:
`import:legacy` skips 166 `agency_implan_responses` rows and all
`implementation_files` / `implementation_photos` rows because they reference
IMPLAN ids absent from `fr_rscp_implementation` in that dump. Re-run the import
against a current dump to pick them up.
