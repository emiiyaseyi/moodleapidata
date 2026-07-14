# Learning Hub API

A Laravel middleware API that sits between your **Moodle LMS** and internal systems (SKULOS, HR/L&D portal, dashboards, and future platforms). Consuming systems never talk to Moodle directly — they authenticate to this API with a Sanctum token and get back clean, business-focused JSON.

```
Moodle LMS  <--REST/HTTPS-->  Learning Hub API  <--Sanctum token-->  SKULOS / HR Portal / other systems
```

## Requirements

- PHP 8.2+
- MySQL 8+
- Composer

## This machine's local dev environment

PHP 8.2 (`C:\PHP82`), Composer (`C:\Composer`), and MySQL 8.4 (`C:\MySQL84`) were installed as portable (non-service) copies for this project, since winget's installers weren't usable in this environment. PHP and Composer are on your user `PATH` already (new terminals will find `php`/`composer` directly). MySQL is **not** installed as a Windows service — start it manually when you resume work:

```
"C:\MySQL84\bin\mysqld.exe" --datadir="C:\MySQL84\data" --port=3306
```

Leave that running in its own terminal, then `php artisan serve` in another. The app database/user already exist (`moodleapidata` / `moodleapidata_dev_pw`, matching `.env`). If you'd rather have MySQL start automatically with Windows, install it as a service later (`mysqld --install` from an elevated prompt) or switch to XAMPP/Laragon.

## Local development setup

1. Copy `.env.example` to `.env` and fill in:
   - `DB_*` — your MySQL connection.
   - `MOODLE_BASE_URL` / `MOODLE_TOKEN` — see "Moodle-side setup" below.
2. Install dependencies and generate the app key:
   ```
   composer install
   php artisan key:generate
   ```
3. Run migrations:
   ```
   php artisan migrate
   ```
4. Create an API consumer (a system that's allowed to call this API) and issue it a token:
   ```
   php artisan api-consumers:create "hr-portal"
   ```
   The token is printed once — store it in the consuming system's secrets, not in source control.
5. Serve the app:
   ```
   php artisan serve
   ```
6. Call an endpoint:
   ```
   curl -H "Authorization: Bearer <token>" http://localhost:8000/api/v1/staff/michael@email.com
   ```

Run the test suite (uses an in-memory SQLite DB and faked Moodle responses — no live Moodle needed):
```
php artisan test
```

## Moodle-side setup

1. **Site administration → Advanced features** → enable "Enable web services".
2. **Site administration → Plugins → Web services → Manage protocols** → enable REST.
3. Create a dedicated service account (e.g. `api.user`) — never use an Administrator account for this.
4. **Site administration → Plugins → Web services → External services** → create a custom service, add the service account as an authorised user, and whitelist these functions:
   - `core_user_get_users`
   - `core_enrol_get_users_courses`
   - `core_course_get_courses`
   - `core_completion_get_course_completion_status`
   - `core_completion_get_activities_completion_status`
   - `gradereport_user_get_grade_items`
   - `gradereport_overview_get_course_grades`
   - `core_badges_get_user_badges`
   - `core_competency_list_user_plans`
5. **Site administration → Server → Web services → Manage tokens** → generate a token for the service account, scoped to the custom service above.
6. Put the site URL and token in `.env` as `MOODLE_BASE_URL` and `MOODLE_TOKEN`.

## API reference (Phase 1 — current)

All endpoints are versioned under `/api/v1`. Every response (success or error) follows the same envelope:

```json
{
  "success": true,
  "message": "Staff member retrieved successfully.",
  "data": { "...": "..." },
  "meta": { "timestamp": "2026-07-14T13:15:00+00:00", "version": "v1" }
}
```

Any `GET` endpoint that returns an object or list supports a `?fields=a,b,c` query param to whitelist top-level response keys.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/health` | Liveness check (no auth). |
| GET | `/staff/{email}` | Staff profile (Moodle user id, name, email, department). |
| GET | `/staff/{email}/courses` | All courses the staff member is enrolled in. |
| GET | `/staff/{email}/summary` | One-call dashboard summary: course counts, completion, average score. |
| GET | `/staff/{email}/transcript` | Full learning history: every course with progress, completion, and final grade. |
| GET | `/staff/{email}/badges` | Awarded badges with issue/expiry dates and verification hash. |
| GET | `/staff/{email}/competencies` | Learning plans with status and due date (requires competencies enabled in Moodle). |
| GET | `/courses/{courseId}` | Course details. |
| GET | `/staff/{email}/courses/{courseId}/progress` | Activity-completion progress percentage. |
| GET | `/staff/{email}/courses/{courseId}/grades` | Grade items and final grade/status for the course. |
| GET | `/staff/{email}/courses/{courseId}/completion` | Course completion status and date. |

All endpoints except `/health` require `Authorization: Bearer <token>` from an active API consumer, and are rate-limited (120 req/min per consumer).

## Managing API consumers

```
php artisan api-consumers:create "some-system-name"
```

Each consuming system (SKULOS, HR portal, etc.) should get its own named consumer/token, so access can be revoked per-system without affecting others. Revoke via `$consumer->tokens()->delete()` in `tinker`, or deactivate by setting `is_active` to false.

## Auditing

Every authenticated request is logged to `api_request_logs` (consumer, method, path, query params with tokens redacted, status code, duration). Use this table for troubleshooting and usage reporting.

## Caching

Moodle responses are cached per query (`MOODLE_CACHE_TTL`, default 900s) via Laravel's cache. Adjust the TTL based on how fresh grade/completion data needs to be for your consumers.

## Roadmap (not yet built)

- **Phase 2 (remaining)** — certificates. There is no core Moodle web service for certificates — the function names depend on which plugin the site uses (`mod_customcert`, `mod_certificate`, or `tool_certificate`). Pending confirmation of the plugin installed on the live site. Transcript, badges, and competencies are done.
- **Phase 3** — manager/HR reporting: department learning, course participants, completion/compliance/top-performer reports.
- **Phase 4** — administrative + inbound sync: provisioning users, enrolments, and scheduled syncs.
  - This includes **inbound, rule-driven enrolment**: other systems (HR) push staff data (join date, etc.) to this API, which evaluates business rules — e.g. a "NEO exam" window opening 4 months after join date — and calls Moodle to enrol the staff member into the right course with a computed start/end date. This will likely integrate with the custom **learnguard** / **learntrack** Moodle plugins. Not yet scoped — needs the plugins' web service/API surface confirmed before implementation.

### Architecture decision: this API stays standalone

This Laravel app remains the source of truth for the integration layer — it is **not** being rewritten as a Moodle plugin. A Moodle "local plugin" runs inside Moodle's own PHP codebase (no Laravel, no independent hosting/queues/scheduler, coupled to every Moodle upgrade, and hard to extend to non-Moodle systems later), which would give up most of what this design is for.

The one piece that may eventually need a thin **Moodle-side plugin** is the inbound auto-enrolment hook described above (Phase 4) — e.g. a small `local_learninghub` plugin that calls out to this API (or is called by it) purely to trigger enrolment at the right moment, working alongside `learnguard`/`learntrack`. Everything else — data retrieval, business rules, caching, auth, auditing — stays in this API.

## Deploying (cPanel / shared hosting)

1. Upload the app (excluding `vendor/`, `node_modules/`) and run `composer install --no-dev --optimize-autoloader` on the server, or upload `vendor/` built for the target PHP version.
2. Set `.env` for production (`APP_ENV=production`, `APP_DEBUG=false`, real `DB_*` and `MOODLE_*` values).
3. Point the domain's document root at `public/`.
4. Run `php artisan migrate --force` and `php artisan config:cache route:cache`.
5. Issue consumer tokens for each real consuming system with `api-consumers:create`.
