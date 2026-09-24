# Deploying CY-Market to a Hostinger VPS (Docker)

This app ships as a self-contained Docker Compose stack: one image (Apache +
PHP 8.3) reused by three containers — `app` (serves HTTP), `scheduler` (runs
Laravel's scheduled jobs), `queue` (idle safety net, nothing dispatches to it
yet) — plus a `db` container (MySQL 8). Everything below uses plain
`docker compose` commands over SSH, so it works the same whether you drive it
through Hostinger's Docker Manager UI or a terminal; where the panel offers a
point-and-click equivalent for a step, use whichever you prefer.

## What's in the repo for this

| File | Purpose |
|---|---|
| `Dockerfile` | Multi-stage build: composer install → npm build → Apache/PHP runtime image |
| `docker-compose.yml` | The `app` / `scheduler` / `queue` / `db` stack |
| `.env.docker.example` | Template for the real `.env` you create **on the server** |
| `docker/apache/000-default.conf` | Points Apache's docroot at `public/` |
| `docker/php/uploads.ini`, `opcache.ini` | Production PHP limits + opcache |
| `docker/entrypoint.sh` | Runs on every container start: fixes permissions, `storage:link`, config/route/view cache, optional migrations |
| `docker/scheduler.sh` | The loop the `scheduler` service runs instead of a real cron daemon |
| `.dockerignore` | Keeps `vendor/`, `node_modules/`, `.env`, and `.git` out of the image |

## Two ways to deploy

**A) Hostinger's "Docker Manager > Compose" URL-paste tool.** Point it at
this repo's URL and it clones + runs `docker compose up` for you. Every
setting in `docker-compose.yml` reads from an env var with a safe fallback
(`${DB_PASSWORD:-change-me}` etc.) rather than requiring a committed `.env`
file, specifically so this works on a fresh clone with nothing configured
yet. If the panel exposes an environment-variables form before/after
deploying, that's where to override the fallbacks with real values (see the
table in step 3 for the full list). If it doesn't, SSH in afterwards, drop a
real `.env` next to `docker-compose.yml` (step 3 below), and run
`docker compose up -d --build` again to pick it up.

**B) Plain SSH.** Steps 1–8 below, using `docker compose` directly. This is
the more predictable path since it doesn't depend on how any particular
panel's importer parses the compose file - if the Hostinger UI is fighting
you (e.g. a "Docker project not found" error, which usually means its
importer choked on something before ever running Compose), fall back to
this and come back to the UI later once the app is confirmed running.

## 1. Point your domain at the VPS

Create an `A` record for your domain pointing at the VPS's public IP. This
can take a while to propagate — do it first so it's ready by the time you
need it.

## 2. Get the code onto the VPS

SSH into the VPS (or use Docker Manager's terminal/git integration if it
offers one) and clone the repo into a working directory, e.g.:

```bash
git clone <your-repo-url> /opt/cy-market
cd /opt/cy-market
```

## 3. Create the production `.env`

```bash
cp .env.docker.example .env
nano .env   # or vi/your editor of choice
```

Fill in every placeholder — at minimum:

- `APP_URL` — your real `https://` domain
- `DB_PASSWORD` and `DB_ROOT_PASSWORD` — pick strong values, they're only used internally between the `app` and `db` containers
- Leave `MOMO_MODE=simulate` and `SMS_MODE=log` until you're actually ready to take real payments / send real SMS. For MTN MoMo, set `MOMO_MODE=live` plus the `MOMO_*` credentials (see below); `MOMO_CALLBACK_URL` must be your public https URL
- `GOOGLE_MAPS_API_KEY` — optional; the delivery-location picker and admin order map show a "not configured" notice until this is set

This `.env` stays only on the server — it's already in `.gitignore` and never gets built into the image (`.dockerignore` excludes it too).

## 4. Build and start the stack

```bash
docker compose up -d --build
```

First build takes a few minutes (composer install + npm build happen inside
the build). Watch it if you want:

```bash
docker compose logs -f app
```

## 5. Generate the app key (once, ever)

```bash
docker compose exec app php artisan key:generate
```

This writes `APP_KEY` into the running container's environment for this
session, but **won't persist across restarts** since `.env` on disk is what
actually gets loaded next time. Copy the key it prints into your `.env`
file's `APP_KEY=` line, then:

```bash
docker compose restart app scheduler queue
```

Do this exactly once. Regenerating the key later invalidates all existing
sessions and any encrypted data.

## 6. Run migrations and seed

Migrations are **not** run automatically on every restart (deliberately —
you don't want an untested migration firing off just because a container
rebooted). Run them by hand on first deploy and after every update that
includes new migrations:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force   # only if you want the demo catalogue/roles
```

If you'd rather migrations ran automatically on every container start
instead, set `RUN_MIGRATIONS=true` in `.env` and restart — `docker/entrypoint.sh`
checks for that flag.

## 7. Verify

- Storefront: `https://yourdomain.com/`
- Admin: `https://yourdomain.com/admin` (whatever `FILAMENT_PATH` is set to)
- `docker compose ps` — all four services should show `Up`/`healthy`
- `docker compose logs app` — should be quiet on 200s, no repeated errors

## 8. HTTPS

The `app` container serves plain HTTP on the port set by `APP_PORT` in
`.env` (default `80`). This repo doesn't include a TLS-terminating reverse
proxy, since that depends on what Hostinger's Docker Manager provides on
your plan — check whether it has a built-in "issue SSL for this
container/port" option first. If it doesn't, the common fallback is running
Caddy, Traefik, or nginx+certbot as one more service in front of `app`; ask
if you'd like that added once you know which path you're on.

## Redeploying after code changes

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force   # only if new migrations
```

`docker/entrypoint.sh` re-runs `config:cache`/`route:cache`/`view:cache` on
every container start, so stale cached config is never a concern after a
redeploy — just make sure `.env` itself is current before restarting.

## Troubleshooting

- **502 / connection refused** — `docker compose logs app`; usually a boot-time
  error (bad `.env` value, DB not reachable yet). `depends_on: condition:
  service_healthy` on `db` should prevent the app starting before MySQL is
  ready, but check `docker compose logs db` too.
- **500 error, blank page** — check `docker compose exec app tail -n 50 storage/logs/laravel.log`. With `APP_DEBUG=false` (correct for production) the browser won't show the real error, the log will.
- **Uploaded product/banner images 404** — confirm `public/storage` exists inside the container: `docker compose exec app ls -la public/storage`. `docker/entrypoint.sh` creates this symlink automatically if missing; if it's missing anyway, run `docker compose exec app php artisan storage:link` by hand.
- **Uploaded images disappear after a redeploy** — they shouldn't: `storage/` is a named Docker volume (`app-storage`) that survives `docker compose up -d --build`. It's only lost if you explicitly run `docker compose down -v`. Never use `-v` unless you mean to wipe the database and uploads.
- **Scheduled jobs (stock reservations, abandoned carts) not firing** — `docker compose logs scheduler` should show a `schedule:run` line every 60 seconds.

## MTN MoMo payments (pilot)

The checkout asks for an MTN MoMo number; after the order is placed MTN
sends a PIN prompt to that phone and the order page updates by itself once
it is approved. Settings (all in `.env`, see `.env.docker.example`):

- `MOMO_MODE=simulate` — local Approve/Decline page, no MTN account needed (default).
- `MOMO_MODE=live` — real API. Use `MOMO_ENVIRONMENT=sandbox` + `MOMO_CURRENCY=EUR` against MTN's developer sandbox first, then `MOMO_ENVIRONMENT=mtnghana`, `MOMO_BASE_URL=https://proxy.momoapi.mtn.com`, `MOMO_CURRENCY=GHS` with the credentials MTN Ghana issues.
- `MOMO_CALLBACK_URL=https://yourdomain/webhooks/mtn-momo` — MTN's status callback. It is never trusted on its own: the app re-checks the status with MTN, and the scheduler container also reconciles any payment still pending after a minute.

After deploying this update run the new migration once:

```bash
docker compose exec app php artisan migrate --force
```
