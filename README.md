# HSE

An open-source health, safety and environment (HSE) management system built around ISO 45001. It links the records most tools keep in separate registers, so every report is followed through to a checked fix:

```
observation / near miss → incident → investigation (5 Whys) → corrective action (verified by someone else) → risk assessment review → toolbox talk
```

## Features

- **Observations and near misses** from a phone: one screen, photos (shrunk before upload), GPS, anonymous reporting. **Works without signal**: reports queue on the phone and send when the signal returns, never twice.
- **Incidents**: people involved, classification worked out from the worst treatment (first aid → fatality), recordability, 5-Why investigation with root-cause category, and the **OSHA 300 log and 300A summary** (illness types, privacy cases, 180-day cap), printable. Optional **AI drafting** of the report from a plain-words account (Claude).
- **Corrective actions**: one list for every source; the owner marks an action done and **someone else must verify it**; daily reminder emails.
- **Risk assessments** (HIRA, JSA, COSHH, manual handling): 5×5 matrix before and after controls, approval by someone other than the author, nothing left at 15+, numbered revisions, and **review required** when an incident is linked.
- **Inspections**: checklist templates (yes/no/N/A, 1–5 rating, number with limits, text, critical items), scores, and an action raised for every failed answer.
- **Permits to work** with **LOTO** and **gas tests**: per-type precautions, signed approval and closure, blocked while a worker's ticket is expired, the contractor isn't approved or insured, or (confined space) there is no passing gas test; no closing with a lock still on; conflicting hot work and confined-space work flagged; **shift handovers**.
- **Training matrix** (valid / expiring / expired) and **toolbox talks** with attendees.
- **Internal audits** (ISO 45001 §9.2) where nonconformities raise actions, and **document control** (§7.5) with signed approval, read-and-acknowledge and periodic review.
- **Environment**: monthly waste, water, energy and fuel per site with the recycling rate; **chemical register** with GHS hazards, safety data sheets and COSHH links.
- **Dashboard**: LTIFR, TRIR, days since the last lost-time injury, safety pyramid, leading indicators and a "needs you" list per person.
- **Sites** with per-user site scoping, **contractors**, six roles, **electronic signatures** and an **append-only audit trail** of every change.
- A built-in **user guide** (`/guide`).

## Stack

Laravel 13 · Inertia 3 · React 19 · TypeScript · Tailwind CSS 4 · shadcn/ui · Fortify (login, 2FA, passkeys) · Spatie Permission · Wayfinder · Pest · Larastan (level 7) · MySQL (SQLite for tests).

## Getting started

Requirements: PHP 8.4, Composer, Node 22, MySQL 8 (or SQLite).

```bash
git clone https://github.com/pasupathy-manikam-jr/hse.git
cd hse
composer setup          # install, .env, key, migrate, npm install, build
php artisan db:seed     # demo data: a tower project and a stamping plant
composer dev            # app, queue, logs and Vite together
```

`.env.example` uses SQLite; for MySQL set the `DB_*` values.

Demo accounts (password `DEMO_PASSWORD`, default `Zx123456`; `DEMO_LOGINS=true` lists them on the login page): `admin@example.com`, `hse-manager@example.com`, `supervisor@example.com`, `permit-issuer@example.com`, `worker@example.com`, `auditor@example.com`. Change or remove them before going live.

## Configuration

| Variable            | Default           | What it does                                                                                                                        |
| ------------------- | ----------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `ANTHROPIC_API_KEY` | empty             | Switches on AI drafting of incident reports. Leave empty to keep it off. Only the typed account and the site's area names are sent. |
| `ANTHROPIC_MODEL`   | `claude-opus-5-5` | The Claude model used for drafting.                                                                                                 |
| `MAIL_*`            | `log`             | Mail transport for the daily reminder emails.                                                                                       |

### Scheduled jobs

The daily reminders need Laravel's scheduler, run every minute by cron:

```
* * * * * php /path/to/hse/artisan schedule:run >> /dev/null 2>&1
```

| Command         | When            | What                                                                                                                    |
| --------------- | --------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `hse:reminders` | daily 07:00 UTC | Emails each person their actions due or overdue, competencies expiring within 30 days, and permits past their end time. |

## Development

```bash
composer ci:check       # lint, TypeScript, Pint, Larastan and the Pest suite: exactly what CI runs
php artisan test --compact --filter=Permit
```

- Roles and permissions are defined in code (`database/seeders/RolesSeeder.php`). After changing them run `php artisan db:seed --class="Database\Seeders\RolesSeeder"`.
- Each module has a model in `app/Models`, a controller, a route file in `routes/modules/` and pages in `resources/js/pages/<module>/`. Run `php artisan wayfinder:generate --with-form` after changing routes.
- The user guide is `resources/guide/en.md`.

## Deployment

CI (`.github/workflows/tests.yml`) runs `composer ci:check` on every push. On pushes to `main`, `.github/workflows/deploy-assets.yml` builds the frontend and publishes a `deploy` branch (`main` plus compiled `public/build`), so servers never need Node.

On the server:

```bash
cd ~/hse && bash scripts/deploy.sh   # pull deploy branch, composer install, migrate, cache
```

To serve from a subfolder (e.g. `https://example.com/hse`), build with `APP_PATH_PREFIX=hse` and set `APP_URL`, `ASSET_URL`, `SESSION_PATH` and `SESSION_COOKIE` to match.

## Licence

[MIT](LICENSE).
