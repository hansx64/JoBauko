# Deploying JoBauko on Render and Aiven (free tiers)

This guide runs JoBauko on a free Render web service with a free Aiven for MySQL database. It suits a pilot or demonstration. For the municipality's live service, use the single-server setup in `DEPLOYMENT.md` or paid plans.

## What the free tiers mean

| Limit | Effect on JoBauko | What this setup does |
|---|---|---|
| Render's disk is erased on every restart and deploy | Files saved on the server disappear | All uploads, including logos, go to Google Drive (`UPLOADS_DISK=google`) |
| Render sleeps after 15 minutes without visitors | The next visit waits about a minute; the queue worker and scheduler stop while asleep | A free uptime monitor visits `/up` every 10 minutes to keep it awake |
| Render has no shell, background workers or cron jobs on the free plan | Commands cannot be run on the server | `docker/start.sh` migrates, seeds and runs the queue worker and scheduler inside the web service |
| Render blocks outgoing ports 25, 465 and 587 | Ordinary SMTP does not work | Mail is sent with the Gmail API over HTTPS (`MAIL_MAILER=gmail`), as the same Google account that holds the uploads |
| Render free instances have 512 MB of memory | Too many Apache workers would run out of memory | `docker/mpm.conf` limits Apache to 8 workers |
| Aiven free MySQL has 1 GB of storage, one node and may be powered off when unused | Enough for a pilot; no failover | Keep the site awake; watch storage in the Aiven console |
| Render gives 750 free hours a month per workspace | One always-awake service uses about 744 | Run only this one free service in the workspace |

## 1. Database (Aiven)

1. Sign up at https://console.aiven.io and create a service: **MySQL**, plan **Free**, in the region closest to the Philippines that is offered.
2. Wait until the service is **Running**. On its **Overview** page, note the **Host**, **Port**, **User** (`avnadmin`), **Password** and **Database name** (`defaultdb`).
3. Click **Download** next to **CA certificate** and keep the `ca.pem` file.

## 2. Google account (uploads and mail)

Follow "Storing uploads in Google Drive" in `DEPLOYMENT.md`, with two additions so the same sign-in can also send email:

1. In the Google Cloud project, also enable the **Gmail API** (*APIs & Services → Library*).
2. Under *Google Auth Platform → Data Access*, add the `.../auth/gmail.send` scope next to `.../auth/drive.file`.
3. Run `php artisan jobauko:google-drive` on your computer and tick every box Google shows. If the account was connected before Gmail was added, run it again; it keeps the same uploads folder and saves a new refresh token.

Mail is sent as that Google account, so `MAIL_FROM_ADDRESS` must be its address. A personal Gmail account can send about 500 messages a day.

## 3. Values to prepare

- `APP_KEY`: run `php artisan key:generate --show` on your computer and copy the output, including `base64:`.
- `GOOGLE_DRIVE_*`: the four values from your local `.env` after `php artisan jobauko:google-drive`.
- `ADMIN_EMAIL` and `ADMIN_PASSWORD`: the first administrator. Set a password here so none is generated and printed in the logs.

## 4. Web service (Render)

1. Push the repository to GitHub.
2. Sign up at https://render.com with GitHub, then **New → Blueprint**, and choose the repository. Render reads `render.yaml`.
3. Fill in each value it asks for:

| Key | Value |
|---|---|
| `APP_KEY` | from step 3 |
| `APP_URL` | `https://jobauko.onrender.com` for now; correct it after the first deploy if Render gives another address |
| `DB_HOST`, `DB_PORT`, `DB_PASSWORD` | from Aiven |
| `DB_CA_CERT` | the whole text of `ca.pem`, including the `BEGIN` and `END` lines |
| `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, `GOOGLE_DRIVE_REFRESH_TOKEN`, `GOOGLE_DRIVE_FOLDER_ID` | from your local `.env` |
| `MAIL_FROM_ADDRESS` | the address of the connected Google account |
| `SEMAPHORE_API_KEY`, `SEMAPHORE_SENDER_NAME` | from Semaphore; leave the sender empty until one is approved |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | from step 3 |
| `PRIVACY_CONTACT_EMAIL` | the Data Protection Officer's address |

4. Click **Apply**. The first build takes about 10 minutes.
5. Open the service's **Logs**. A good start shows the migrations, `Admin account created`, the `jobauko:check` table and then Apache starting. Every check should be **OK** except the warnings you expect.

If the address Render shows differs from `APP_URL`, update `APP_URL` under **Environment** and save; Render restarts the service.

## 5. Keep it awake

Create a free monitor at https://uptimerobot.com (or cron-job.org): type **HTTP(s)**, URL `https://<your-service>.onrender.com/up`, every **10 minutes**. Without it, the site sleeps, the daily 07:00 reminders are skipped, and queued alerts wait until the next visitor.

## 6. Go live checks

Follow section 8 of `DEPLOYMENT.md` from "Then:": sign in as the administrator, register and approve a test organization, apply with a test seeker, check the emails and SMS under *Delivery logs*, and delete the test accounts.

## Updating

Push to the `main` branch. Render rebuilds and restarts automatically, and `docker/start.sh` runs any new migrations.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Deploy fails at `migrate` with an SSL or connection error | `DB_CA_CERT` incomplete, or `DB_HOST` / `DB_PORT` wrong |
| Deploy fails creating the `audit_logs` triggers | The database user cannot create triggers while binary logging is on; ask Aiven support, or set `log_bin_trust_function_creators` if the plan allows it |
| "419 Page Expired" on forms | `APP_URL` does not match the address in the browser |
| Uploads or emails fail with `invalid_grant` | The Google connection was removed or expired; run `php artisan jobauko:google-drive` locally and copy the new `GOOGLE_DRIVE_REFRESH_TOKEN` to Render |
| Emails do not arrive | The error is in the Logs. "insufficient authentication scopes": run `jobauko:google-drive` again and allow sending email, then copy the new refresh token to Render. Mail from another address than the Google account is rewritten or refused by Gmail |
| Site takes a minute to open | It was asleep; set up the monitor in section 5 |
