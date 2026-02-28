# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A [Source-to-Image (S2I)](https://github.com/openshift/source-to-image) repository that builds a Nextcloud container image for OpenShift. The builder image is `quay.io/cuppett/ubi-php:83-ubi10` (UBI 10, PHP 8.3, Apache/FPM). There is no application source code here — only the S2I scripts and configuration that layer Nextcloud on top of the builder image.

Current Nextcloud version: **33.0.0** — set in `.s2i/bin/assemble`.

## Build & Test

```bash
# Build (--copy uses the working tree; without it s2i clones from git HEAD)
s2i build --copy . quay.io/cuppett/ubi-php:83-ubi10 nextcloud-s2i-test

# Run
podman run -d -p 8080:8080 --name nc-test nextcloud-s2i-test

# Verify (use -4; IPv6 loopback causes connection resets on this host)
curl -4 -sI http://localhost:8080/
podman exec nc-test php -r 'require "/var/www/html/version.php"; echo implode(".", $OC_Version)."\n";'
podman exec nc-test php -m | grep -E 'redis|igbinary|apcu|opcache'
podman exec nc-test php -i | grep 'opcache.jit'

# Cleanup
podman stop nc-test && podman rm nc-test && podman rmi nextcloud-s2i-test
```

## Architecture

### Two-Phase Execution

**Assemble phase** (`.s2i/bin/assemble`) — runs once at image build time:
1. Calls the base sclorg assemble (copies source, processes `httpd-cfg/`, runs `php-post-assemble/`)
2. Downloads and SHA256-verifies Nextcloud to `$HOME` (`/opt/app-root/src`)
3. Moves `autoconfig/*.php` → `config/` so they ship inside the image
4. Writes `/etc/php.d/20-opcache-nextcloud.ini` (JIT + interned strings settings not handled by sclorg templating)

**Run phase** (`.s2i/bin/run`) — runs on every container start:
1. Optionally writes `/etc/php.d/50-redis-session.ini` from `REDIS_HOST*` env vars
2. Acquires a `flock` on `/var/www/html/nextcloud-init-sync.lock` to serialize init across replicas
3. Detects installed vs. image version; runs rsync to populate `/var/www/html/` from `/opt/app-root/src/`
4. On first start: runs `occ maintenance:install`; on upgrade: runs `occ upgrade`
5. Calls lifecycle hooks from `/docker-entrypoint-hooks.d/{pre-installation,post-installation,pre-upgrade,post-upgrade,before-starting}` (these dirs are volume-mount points, not created in the image)
6. Sources the base sclorg run script (starts Apache + FPM)

### File Layout

```
.s2i/bin/assemble      Build-time script
.s2i/bin/run           Runtime entrypoint
.s2i/environment       PHP/opcache env vars consumed by sclorg templating
autoconfig/            PHP config fragments — moved to /opt/app-root/src/config/ at build time,
                       then rsync'd to /var/www/html/config/ on first run
httpd-cfg/             Apache config snippets processed by sclorg assemble
php-post-assemble/     Runs after sclorg assemble; patches httpd.conf DocumentRoot
upgrade.exclude        rsync exclude list — paths preserved across upgrades
```

### Data Persistence Model

`/opt/app-root/src/` — image-baked Nextcloud (read-only reference)
`/var/www/html/` — persistent volume; rsync'd from src on first run, then preserved across upgrades
`config/`, `data/`, `custom_apps/`, `themes/` — excluded from rsync delete so user data survives upgrades

### autoconfig Files

Each `autoconfig/*.php` exports a `$CONFIG` array (or `$AUTOCONFIG` for `autoconfig.php`). They are loaded by Nextcloud's config system automatically. Key files:
- `autoconfig.php` — database connection from env vars (supports `*_FILE` variants for secrets)
- `apcu.config.php` — enables APCu as local memory cache
- `redis.config.php` — enables Redis as distributed cache/locking when `REDIS_HOST` is set
- `s3.config.php` — S3 object store when `OBJECTSTORE_S3_BUCKET` is set
- `reverse-proxy.config.php` — overwrite settings + trusted proxies + forwarded-for headers
- `upgrade-disable-web.config.php` — disables web-based upgrades (appropriate for containers)

## Important Constraints

**UBI 10 container tooling gaps** — `diff` and `cmp` are not present; use `sha256sum` for file comparison. `grep`, `sed`, `awk`, `sha256sum`, `flock` are all available.

**PHP config path** — writable PHP INI directory is `/etc/php.d/` (world-writable by design in this builder). The PHP-FPM path `/usr/local/etc/php/` does not exist.

**Hook directories** — `/docker-entrypoint-hooks.d/` lives at the filesystem root, which user 1001 cannot create. The run script handles missing hook dirs gracefully. Users provide hooks via volume mounts at deploy time.

**Nextcloud SHA256 file format** — `nextcloud-X.Y.Z.tar.bz2.sha256` contains two lines (tarball hash and metadata hash). Always filter with `awk '/\.tar\.bz2$/'` before piping to `sha256sum`.

**Bumping Nextcloud version** — change `NEXTCLOUD_VERSION` in `.s2i/bin/assemble`. Verify the tarball exists at `https://download.nextcloud.com/server/releases/`.
