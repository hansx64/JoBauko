# JoBauko

JoBauko is the opportunity portal of the Municipality of Bauko, Mountain Province. It brings jobs, scholarships and livelihood programs into one place for the residents of Bauko's 22 barangays:

- seekers find opportunities and apply online;
- organizations publish opportunities and review applicants;
- the municipal administrators verify organizations, monitor activity, send announcements and produce reports.

Built with Laravel 12 (PHP 8.4), React 19 with Inertia 2, Tailwind 4 and shadcn/ui, and MySQL 8.4.

- **Architecture and design decisions:** [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- **Installing on a server:** [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)

## Who uses it

| Account | Can |
|---|---|
| Opportunity seeker | Build a profile and upload a resume. Browse and save opportunities. See explained match scores. Apply with documents, track status and withdraw. Choose email/SMS notifications. |
| Organization personnel | Register and verify the organization. Publish and manage opportunities. Review applicants by role (Admin, Recruitment Officer, Reviewer). Invite personnel. Use the organization dashboard. |
| System administrator | Verify or suspend organizations. Manage user accounts. Monitor and suspend opportunities. Handle flagged listings. Send announcements and edit SMS templates. View delivery logs, reports (PDF/Excel) and the audit log. |

Personal data is handled under the Data Privacy Act of 2012 (RA 10173). This covers versioned consent, separate SMS consent, data minimization, audited access to personal records, and erasure on account deletion. See *Security* and *Privacy* in the architecture document.

## Local development

Requirements:

- PHP 8.4 with `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `fileinfo` and `bcmath`
- Composer 2
- Node.js 22 or newer
- MySQL 8.4

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Then:

1. In `.env`, set `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`, and create that database in MySQL.
2. Set up the database and seed it:

   ```bash
   php artisan migrate
   php artisan db:seed          # 22 barangays, skills, and the first administrator
   php artisan storage:link
   ```

   `db:seed` prints a generated administrator password once, unless `ADMIN_PASSWORD` is set in `.env`.

3. Optional: add demo organizations, opportunities and accounts. Never do this on a live server.

   ```bash
   php artisan db:seed --class=DemoDataSeeder
   ```

   This creates `demo.seeker@jobauko.test`, `demo.student@jobauko.test` and `demo.org@jobauko.test`, all with the password `password`.

Run the app:

```bash
composer run dev   # web server, queue worker, log viewer and Vite together
```

Open http://localhost:8000. While developing:

- emails go to `storage/logs/laravel.log` (`MAIL_MAILER=log`);
- text messages are logged too (`SMS_DRIVER=log`).

## Tests and checks

Tests run against a separate MySQL database, `opportunity_portal_test`, set in `phpunit.xml`. Create it once, then:

```bash
npm run build            # pages must be built for the page tests
php artisan test
vendor/bin/pint          # PHP code style
npm run lint             # ESLint
npx tsc --noEmit         # TypeScript
npm run format:check     # Prettier
```

Security tests are in `tests/Feature/Security`:

- **Route protection:** every route must be public by design or require sign-in, and each area must check the account type.
- **Security headers:** pages are served with the expected browser security headers.
- **Account erasure:** deleting an account removes its data and files.
- **Production readiness:** the server configuration check below works.

## Useful commands

| Command | What it does |
|---|---|
| `php artisan jobauko:check` | Checks that the server is configured safely for production |
| `php artisan notifications:deadline-reminders` | Sends deadline reminders (scheduled daily at 07:00) |
| `php artisan db:seed --class=AdminUserSeeder` | Creates the first administrator if it does not exist |
| `php artisan queue:work` | Processes announcements and new-opportunity alerts |

## Project layout

| Path | Contents |
|---|---|
| `app/Http/Controllers/{Public,Seeker,Organization,Admin}` | One folder per area of the portal |
| `app/Services` | Business rules: applications, opportunities, recommendations, notifications, privacy |
| `app/Reports` | Administrator reports and their PDF/Excel export |
| `app/Policies`, `app/Http/Middleware` | Authorization |
| `resources/js/pages` | React pages, mirroring the routes |
| `routes/{web,seeker,organization,admin,settings,auth}.php` | Routes per area |
| `docs/` | Architecture and deployment documentation |
