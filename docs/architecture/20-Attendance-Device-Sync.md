# Attendance Device Sync (Piofy)

## Purpose

Import fingerprint/attendance punches from the Piofy WebPortal into Work Logs without requiring Excel upload.

## Entry points

- UI: `worklog/import` — button **همسان‌سازی با دستگاه**
- Route: `POST worklog.sync-device` (permission `worklogs.create`)
- Excel/csv upload remains available on the same page

## Config

`config/piofy.php` / `.env`:

| Key | Meaning |
|-----|---------|
| `PIOFY_ENABLED` | Show sync UI / allow sync route |
| `PIOFY_BASE_URL` | Device portal base URL (e.g. `http://192.168.10.102:5000`) |
| `PIOFY_USERNAME` / `PIOFY_PASSWORD` | Portal credentials |
| `PIOFY_TIMEOUT` | HTTP timeout seconds |

## Flow

```
Route → WorkLogExcelController::syncFromDevice
     → PiofyAttendanceSyncService::sync
     → PiofyDeviceClient::fetchAttendanceRows  (login + scrape Logs/Users)
     → WorkLogAttendanceImportService::importAttendanceRows
     → WorkLog rows (project_id null)
```

## Matching employees

Order of match for each punch:

1. `user_id` / card against `employees.id`, `employee_code`, `personnel_code`, `personnel_number`, `attendance_card_number`, `national_code`
2. Full name against `first_name + last_name` or related `party.name`

## Import rules

- Punches are grouped per employee per calendar day
- First punch = check-in, last punch = check-out
- Existing WorkLog for that employee+date is skipped (no overwrite)
- Incomplete days (single punch) are stored with `is_incomplete = true`
- `attendance_source` = `device_sync` (direct) or `device_import` (file)

## Since date

- Optional Jalali `since_date` on the form
- Default: last WorkLog date with source `device_import` / `device_sync`, else last 30 days

## Device note

Piofy WebPortal embeds recent logs as `logArray.push({...})` in `/Dashboard/Logs`. The portal may only expose a limited recent window; historical bulk still uses Excel export from the device.

## Architecture fit

- Business logic in Services (not Livewire / Blade)
- Controllers only validate and respond
- Accounting / payroll unchanged — sync only creates Work Logs
