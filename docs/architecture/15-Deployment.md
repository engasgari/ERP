# Deployment

## Environment

Development

Testing

Staging

Production

---

## Docker topology (production)

- `aale-erp-app` — PHP 8.4-FPM image (`aale-erp:latest`), runs `npm ci && npm run build` at image build time.
- `aale-erp-nginx` — Nginx image (`aale-erp-nginx:latest`) that **copies `public/` from the app image**.
- Never bind-mount host `./public` onto Nginx. That hid Vite's hashed `public/build` assets and caused intermittent missing CSS after deploy.
- Persist only `storage/*` and `bootstrap/cache` on the host. Uploads are exposed to Nginx via `storage/app/public` → `/var/www/html/public/storage`.

## Deploy

```bash
chmod +x update.sh
./update.sh
```

`update.sh` fail-fast checks:

1. `git pull --ff-only`
2. rebuild app + nginx images
3. recreate containers (no DB/volume wipe)
4. `optimize:clear` then config/route/view cache
5. app↔nginx `manifest.json` byte equality
6. homepage HTML must reference the hashed CSS from the manifest
7. CSS URL must return HTTP 200 with a non-trivial ERP/Tailwind body

Optional: `BASE_URL=https://crm2.aale.ir ./update.sh` to verify via the public hostname from the ERP host.

---

## Configuration

Never commit

.env

---

## Cache

Managed by `update.sh` after containers are up:

optimize:clear

config:cache

route:cache

view:cache

---

## Queue

Queue must run using Supervisor.

---

## Scheduler

Laravel Scheduler must run every minute.

---

## Logs

Store logs.

Rotate logs.

Monitor errors.

---

## Backup

Daily Database Backup.

Weekly Full Backup.

---

## Storage

Use symbolic links.

Never store uploaded files inside public directly.

Host mount: `./storage/app/public` for durable uploads.

---

## Security

HTTPS Required.

Secure Cookies.

CSRF Enabled.

Authorization Required.

---

## Monitoring

Queue

Logs

Performance

Database

Storage

CSS/asset health (see `update.sh` verification)

---

## Rollback

Every deployment must support rollback.

Keep previous image tags or redeploy the last known-good git SHA with `./update.sh`.

---

## Production

APP_DEBUG=false

APP_ENV=production

Never change production manually.