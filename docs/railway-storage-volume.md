# Railway — Persistent storage volume for uploaded files

## Problem

Railway's container filesystem is **ephemeral**. Anything written at runtime is
discarded on every redeploy/restart. Uploaded files — process documents,
avatars, feed attachments — are stored on the `public` disk
(`storage/app/public`) and served through the public `/storage/...` URL
(`file_url = APP_URL + /storage/...`, see `config/filesystems.php`).

Without persistent storage, those files disappear after a deploy and the
`/storage/...` URL returns **403/404** (the file is gone, so Nginx's
`try_files` previously fell through to Laravel and surfaced a confusing 403).

## Fix — attach a Railway volume

A Railway volume cannot be defined in `railway.toml`; it is attached in the
dashboard.

1. Railway dashboard → **backend service** → **Settings** → **Volumes** →
   **New Volume**.
2. **Mount path:**
   ```
   /var/www/html/storage/app/public
   ```
3. Save and **redeploy** the service once.

On the next boot, `docker/railway/start.sh`:
- `mkdir -p` + `chmod -R 777` the mounted path so PHP-FPM (`www-data`) can
  write to it (Railway mounts volumes owned by root).
- runs `php artisan storage:link --force`, recreating
  `public/storage -> storage/app/public`.

`docker/railway/nginx.conf` serves `/storage` directly from disk
(`location ^~ /storage { try_files $uri =404; }`), so files on the volume are
returned by Nginx and missing files give a clean 404.

## Verify

After the redeploy:

1. Upload a process document (as superadmin or admin_sm).
2. Open its `file_url` (e.g. `https://<app>.up.railway.app/storage/process-documents/<hash>.pdf`)
   → the PDF opens (HTTP 200).
3. Trigger another deploy (e.g. push a no-op change) and re-open the same URL
   → it **still** opens. Before the volume, this step returned 403/404.

## Notes

- Only `storage/app/public` needs to persist. The rest of `storage/`
  (framework cache, sessions, views, logs, api-docs) is intentionally
  ephemeral and rebuilt each boot.
- `preDeployCommand` runs `php artisan db:seed --force` on every deploy. This
  is **safe**: `DatabaseSeeder` is idempotent (`firstOrCreate` roles + upsert
  superadmin) and never truncates or deletes document records.
- Uploaded files are currently served via **public** URLs (no auth). If
  document access must be restricted, layer an authenticated Laravel route
  with a signed/temporary URL on top — tracked separately from this volume fix.
