# Deployment Checklist

Hand-list for first-time and recurring deploys. Order matters: each step
assumes the previous ones already completed successfully.

## Prerequisites on the host

- PHP 8.2+ with the same extensions Laravel 13 needs (mbstring, intl, pdo_mysql, openssl, zip, gd or imagick).
- Composer 2.6+.
- Node 20+ and npm (build-time only — production server does not need Node at runtime).
- A user with permissions to write to `storage/` and `bootstrap/cache/`.
- MySQL 8 or MariaDB 10.6+ reachable from the app server.
- Outbound access to `smtp-relay.brevo.com:587` for the invite emails.

## 1 — Initial setup

```bash
git clone <repo>
cd <repo>

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.production.example .env
php artisan key:generate
```

Now open `.env` and fill in **every** blank value. Pay particular attention to:

- `APP_URL` — must be the public HTTPS URL.
- `DB_*` — pointed at the production database.
- `MAIL_USERNAME` / `MAIL_PASSWORD` — Brevo SMTP key (NOT account password).
- `MAIL_FROM_ADDRESS` — must belong to a domain whose DKIM + SPF + return-path are verified in Brevo.
- `SESSION_SECURE_COOKIE=true` and `SESSION_DOMAIN` set to the apex.
- `TRUSTED_PROXIES` set to your load balancer's IP range.

## 2 — Migrations

```bash
php artisan migrate --force
```

`--force` is required in non-interactive contexts. The flag is safe here
because the deploy pipeline is the only path that runs migrations in prod.

## 3 — Storage symlink

```bash
php artisan storage:link
```

Creates `public/storage` -> `storage/app/public` so material uploads served
through the `public` disk are reachable.

## 4 — Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run **after** `.env` is finalized — `config:cache` snapshots the env values.
If you change `.env` later, re-run all three (or `php artisan optimize:clear`).

## 5 — Queue worker (Brevo dispatch + future async jobs)

`UserInviteMail` is dispatched synchronously today, but Laravel queues are
already configured for `database`. Run a Supervisor-managed worker so
future async jobs do not back up:

```ini
; /etc/supervisor/conf.d/edugest-worker.conf
[program:edugest-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/edugest/artisan queue:work --tries=3 --timeout=90 --sleep=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/edugest/worker.log
stopwaitsecs=120
```

Reload supervisor: `supervisorctl reread && supervisorctl update`.

## 6 — Schedule cron

```cron
* * * * * cd /var/www/edugest && php artisan schedule:run >> /dev/null 2>&1
```

Even if `bootstrap/app.php` does not yet schedule anything, install the
cron now so future schedules work without a deployment step.

## 7 — Brevo (Sendinblue) configuration

1. Log in to Brevo → SMTP & API → generate a new SMTP key. Treat it as a
   secret and store in your secrets manager.
2. Senders & IP → Domains → add the sending domain and follow the DKIM /
   SPF / return-path instructions. Wait for all three to show "verified"
   before sending real invites — Gmail/Outlook will silently bin
   unverified mail.
3. Smoke test:
   ```bash
   php artisan tinker
   >>> Mail::raw('hello', fn ($m) => $m->to('your.address@example.com')->subject('Brevo smoke'));
   ```
   The message should arrive within ~30 seconds. Check Brevo's logs
   panel if it does not.

## 8 — Post-deploy verification

```bash
php artisan migrate:status   # every migration listed as Ran
php artisan about            # framework + env summary, sanity check
php artisan route:list | grep invite   # invite routes registered
```

Smoke test the user-facing invite flow once with a throwaway email:

1. As school_admin: `Usuários → Novo usuário`, fill name + email, submit.
2. Confirm the audit log gets a `user.invited` entry:
   `tail -n 20 storage/logs/audit-$(date +%F).log`.
3. Open the email, click the link, define a 12+ character password, confirm
   you land on `/dashboard` logged in.

## 9 — Rollback notes

The Wave 9 migration `2026_04_27_170858_add_invite_token_to_users_table`
adds three nullable columns and one unique index. `php artisan migrate:rollback --step=1`
removes them safely; existing users are unaffected (their rows simply lose
the new columns). Pending invites become unredeemable, so prefer rolling
forward with a fix when possible.
