# Server deployment

This is separate from the local Sail stack. Production uses PHP/Apache with built assets, an immutable application image tagged with the Git commit, persistent named volumes, a queue worker, scheduler, private fake infrastructure, and MySQL. Only web is published, on **127.0.0.1:8085**. No phpMyAdmin, Vite, database, or fake-infrastructure public ports.

The supplied 512 MB VPS is below a sensible production size. 2 GB swap permits a small demonstration; it is slower and does not replace RAM. Prefer at least 2 GB RAM. Container memory limits total more than physical RAM intentionally for this constrained demo.

## Bootstrap

Install Docker Engine and Compose from Docker's official apt repository. Clone as a dedicated unprivileged source owner into `/var/www/minicloud`. For this public repository HTTPS needs no credentials:

```sh
sudo -u minicloud git clone https://github.com/mahdi-mh/mini-cloud-billing.git /var/www/minicloud
```

For a private repository, generate a repository-specific Ed25519 deploy key on the server and register **only its public key**, read-only, in GitHub Deploy Keys. Never copy a personal private key or forward an agent. Do not place a token in a remote URL. Docker access is root-equivalent; the source owner is deliberately not in the docker group.

Create `.env.production` from `deploy/env.production.example` with fresh independent database secrets and an application key (`base64:` + 32 random bytes base64 encoded). Keep this file outside Git, mode 0600. Never regenerate APP_KEY on updates.

```sh
sudo ./deploy/deploy.sh
```

On the first deployment only, seed the demo data and immediately replace all three demo staff passwords before providing access. `DatabaseSeeder` uses public demo passwords; never run it unattended on a public installation.

## Access and TLS

Until a DNS name and certificate are configured, use an encrypted SSH tunnel:

```sh
ssh -N -L 8085:127.0.0.1:8085 root@SERVER_IP
# then open http://localhost:8085
```

For public access, add an HTTPS reverse proxy on the host, set APP_URL to its HTTPS origin, enable SESSION_SECURE_COOKIE, and configure trusted proxies correctly if TLS terminates before PHP. For explicitly requested direct public HTTP access, set `WEB_BIND_ADDRESS=0.0.0.0` and `APP_URL=http://SERVER_IP:8085` in `.env.production`, then recreate web. This sends credentials over unencrypted HTTP; prefer HTTPS for ongoing use. The default remains loopback-only. Existing SSH access is preserved; changing the company root credentials or SSH policy needs coordination with the server owner.

## Updates

```sh
cd /var/www/minicloud
sudo -u minicloud git status --short
sudo -u minicloud git pull --ff-only origin main
sudo ./deploy/deploy.sh
```

Deploy is serialized with flock. It builds before stopping the previous services, pauses writers during migrations and recreates all services from the same image. It is not zero-downtime. A failed migration deliberately leaves writers stopped for investigation. Back up before schema changes.

## Operations

Use the same flags for all commands (the deploy script sets RELEASE from the current Git commit):

```sh
export RELEASE="$(git -c safe.directory="$PWD" rev-parse --short=12 HEAD)"
docker compose --env-file .env.production -f compose.production.yaml ps
docker compose --env-file .env.production -f compose.production.yaml logs --tail=100 web queue scheduler infra mysql
curl --fail http://127.0.0.1:8085/up
```

Health `/up` verifies application boot, not full database or billing correctness. Also inspect migration status, queue failures, scheduler logs and an end-to-end create/bill/terminate flow. Log rotation is limited to three 10 MB files per service.

Back up the MySQL database with `mysqldump --single-transaction --no-tablespaces --routines --triggers`, the infra SQLite database with SQLite's backup API or `VACUUM INTO`, and securely preserve APP_KEY. Pause web/queue/scheduler during a coordinated backup of both databases; store encrypted copies outside this VPS and test restoration. No automated off-server backup is configured.

Retain the previous image tag and Git SHA. To roll back application code, check out the recorded commit and redeploy; **schema rollback is not automatic**. Determine database compatibility first or restore a tested backup. Never use `docker compose down -v` on persistent data.

Image base tags track supported release lines; pin digests and add vulnerability scanning for a longer-lived production environment. No unattended pull/deploy cron is installed.
