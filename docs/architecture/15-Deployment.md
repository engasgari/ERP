# Deployment

## Environment

Development

Testing

Staging

Production

---

## Configuration

Never commit

.env

---

## Cache

Before deployment

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

---

## Rollback

Every deployment must support rollback.

---

## Production

APP_DEBUG=false

APP_ENV=production

Never change production manually.