# Deploying JoBauko

This guide installs JoBauko on a single Linux server. The commands are for Ubuntu Server 24.04 LTS; other distributions need equivalent packages. Every step is run as a user with `sudo`, unless it says to run as `www-data`.

Throughout, `jobauko.bauko.gov.ph` stands for your domain and `/var/www/jobauko` for the install folder. Replace them with your own.

## 1. Before you start

You need:

- **A server** with at least 2 CPU cores, 4 GB RAM and 40 GB disk. Uploaded resumes and documents grow over time, so plan disk space accordingly.
- **A domain name** pointing to the server's public IP address.
- **An email account for sending** (SMTP host, port, username and password). Invitations, password resets and notifications depend on it.
- **A Semaphore account** (https://semaphore.co) with credit, if SMS will be used. Note its API key and approved sender name.
- **The Data Protection Officer's email address.** It is shown in the privacy notice.
- **Privacy notice approval.** Have the DPO review the privacy notice at `/privacy` before launch. Its text is in `resources/js/pages/privacy.tsx`.

## 2. Install server software

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl unzip git nginx
sudo add-apt-repository -y ppa:ondrej/php
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl
```

**Composer**

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Node.js**, used only to build the frontend:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

**MySQL 8.4.** JoBauko is developed and tested on MySQL 8.4. Install it from the MySQL APT repository (https://dev.mysql.com/downloads/repo/apt/), then secure it:

```bash
sudo mysql_secure_installation
```

**PHP upload limits.** One upload can be up to 5 MB, and an application can include several documents. Edit `/etc/php/8.4/fpm/php.ini` and set:

```ini
upload_max_filesize = 6M
post_max_size = 50M
expose_php = Off
```

Then restart PHP:

```bash
sudo systemctl restart php8.4-fpm
```

## 3. Create the database

```bash
sudo mysql
```

```sql
CREATE DATABASE opportunity_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'jobauko'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON opportunity_portal.* TO 'jobauko'@'localhost';
FLUSH PRIVILEGES;
```

The audit log is protected by database triggers. The app user creates them during the first migration. If MySQL refuses because binary logging is on, run this once as root, then migrate again:

```sql
SET GLOBAL log_bin_trust_function_creators = 1;
```

## 4. Install the application

```bash
sudo mkdir -p /var/www/jobauko && sudo chown $USER:www-data /var/www/jobauko
git clone <repository-url> /var/www/jobauko
cd /var/www/jobauko
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

Edit `.env`. These are the values that matter on a live server:

| Setting | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://jobauko.bauko.gov.ph` |
| `APP_TIMEZONE` | `Asia/Manila` (dates, deadlines and the daily schedule use it) |
| `LOG_LEVEL` | `warning` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | the database from step 3 |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_ENCRYPT` | `true` |
| `QUEUE_CONNECTION` | `database` |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` | your SMTP account (e.g. `smtp`, port `587`, `tls`) |
| `MAIL_FROM_ADDRESS` | an address of the municipality, e.g. `no-reply@bauko.gov.ph` |
| `SMS_DRIVER` | `semaphore` (or `log` to keep SMS off) |
| `SEMAPHORE_API_KEY`, `SEMAPHORE_SENDER_NAME` | from your Semaphore account |
| `PRIVACY_CONTACT_EMAIL` | the Data Protection Officer's address |
| `ADMIN_NAME`, `ADMIN_EMAIL` | the first administrator; leave `ADMIN_PASSWORD` empty to have one generated |
| `TRUSTED_PROXIES` | only if a load balancer or proxy terminates HTTPS in front of this server: its IP addresses |

Keep `.env` private:

```bash
chmod 640 .env && sudo chown $USER:www-data .env
```

The `APP_KEY` in it encrypts sessions. Back up the whole `.env` file (see section 9).

Set up the database, storage and caches:

```bash
php artisan migrate --force
php artisan db:seed --force          # 22 barangays, skills, first administrator
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

`db:seed` prints the administrator's generated password once. Write it down, then change it after the first sign-in under *Settings → Password*.

**Do not run `DemoDataSeeder` on a live server.**

## 5. Web server

Create `/etc/nginx/sites-available/jobauko`:

```nginx
server {
    listen 80;
    server_name jobauko.bauko.gov.ph;
    root /var/www/jobauko/public;
    index index.php;

    client_max_body_size 50M;
    server_tokens off;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Never serve dotfiles such as .env
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/jobauko /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Uploaded resumes and documents are stored in `storage/app/private`. That folder is outside `public/`, so the web server cannot serve those files directly. JoBauko only sends them after checking that the person may see them.

## 6. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d jobauko.bauko.gov.ph --redirect
```

Certbot renews the certificate automatically. JoBauko sends HSTS over HTTPS, which tells browsers to always use HTTPS for the site.

## 7. Background work

Two things must run all the time:

- **The queue worker** sends announcements and new-opportunity alerts.
- **The scheduler** sends the daily deadline reminders at 07:00.

**Queue worker.** Create `/etc/systemd/system/jobauko-queue.service`:

```ini
[Unit]
Description=JoBauko queue worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/jobauko
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=1 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now jobauko-queue
```

**Scheduler.** Add this line to `sudo crontab -u www-data -e`:

```cron
* * * * * cd /var/www/jobauko && php artisan schedule:run >> /dev/null 2>&1
```

The schedule follows `APP_TIMEZONE` (`Asia/Manila` in `.env.example`), so reminders go out at 07:00 Philippine time whatever the server clock is set to.

## 8. Check and go live

```bash
cd /var/www/jobauko && sudo -u www-data php artisan jobauko:check
```

Every line should say **OK**:

- **FAIL** lines tell you exactly what to change.
- **WARN** lines (for example SMS still set to `log`) are allowed, but read them before launch.

Then:

1. Sign in at `https://jobauko.bauko.gov.ph/login` as the administrator and change the password.
2. Register a test organization, approve it under *Organizations*, and publish a test opportunity.
3. Apply to it with a test seeker account. Check that the emails arrive, and SMS if enabled; *Delivery logs* shows every message.
4. Delete the test accounts afterwards. For a seeker this is under *Settings → Profile → Delete account*.

Other checks:

- **Firewall:** allow only SSH and web traffic.

  ```bash
  sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
  ```

- **MySQL:** must not accept connections from outside. The default `bind-address = 127.0.0.1` ensures that.

## 9. Backups

Back up all three of these together. A database backup without the files, or the files without `.env`, is not enough to restore the portal.

| What | Where | Why |
|---|---|---|
| Database | `opportunity_portal` | All accounts, profiles, opportunities, applications, consents and the audit log |
| Uploaded files | `/var/www/jobauko/storage/app/private` and `storage/app/public` | Resumes, application documents, organization verification documents, logos |
| Settings | `/var/www/jobauko/.env` | Database and mail credentials, and `APP_KEY` |

Example nightly script, `/usr/local/bin/jobauko-backup`:

```bash
#!/bin/bash
set -euo pipefail
STAMP=$(date +%F)
DEST=/var/backups/jobauko
mkdir -p "$DEST"
mysqldump --single-transaction --routines --triggers opportunity_portal | gzip > "$DEST/db-$STAMP.sql.gz"
tar -czf "$DEST/files-$STAMP.tar.gz" -C /var/www/jobauko storage/app/private storage/app/public .env
find "$DEST" -type f -mtime +30 -delete    # keep 30 days
```

```bash
sudo chmod 700 /usr/local/bin/jobauko-backup
sudo crontab -e    # add: 30 1 * * * /usr/local/bin/jobauko-backup
```

`mysqldump` needs credentials. Put them in `/root/.my.cnf`, readable only by root.

The backups contain personal data:

- copy them off the server to storage the municipality controls;
- encrypt them;
- limit who can read them, as required by the Data Privacy Act.

**Test a restore at least once.** Restore into a second database and folder and sign in.

## 10. Updating

```bash
cd /var/www/jobauko
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan queue:restart
php artisan up
```

Take a backup first. Then run `php artisan jobauko:check` again.

## 11. Logs and troubleshooting

| Where to look | What you find |
|---|---|
| `storage/logs/laravel.log` | Application errors |
| `sudo journalctl -u jobauko-queue` | The queue worker's output |
| `/var/log/nginx/error.log` | Web server errors |
| *Admin → Delivery logs* | Every SMS and notification email, with the provider's error for failures |
| *Admin → Audit log* | Who did what, and when |

| Problem | Likely cause |
|---|---|
| Announcements stay on "Sending" | The queue worker is not running: `sudo systemctl status jobauko-queue` |
| No deadline reminders | The scheduler cron line is missing (`sudo crontab -u www-data -l`) |
| SMS show "failed" in Delivery logs | Semaphore credit, API key or sender name. The error from Semaphore is shown next to each message |
| Emails do not arrive | SMTP settings; check `laravel.log` for mail errors |
| Organization logos do not show | `php artisan storage:link` was not run |
| "419 Page Expired" on forms | `APP_URL` does not match the address in the browser, or `SESSION_SECURE_COOKIE=true` without HTTPS |
| Uploads rejected as too large | `upload_max_filesize` / `post_max_size` in php.ini, or `client_max_body_size` in Nginx |
| Changes to `.env` have no effect | Run `php artisan config:cache` again |
