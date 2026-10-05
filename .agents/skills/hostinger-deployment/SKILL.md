---
name: hostinger-deployment
description: Instructions and operational protocol for deploying Kudos DOES to Hostinger Cloud Hosting. Activate whenever the user asks to deploy, release, check deployment status, or inspect production.
---

# Hostinger Deployment Protocol for Kudos DOES

This skill governs the entire deployment lifecycle to Hostinger Cloud Hosting for **Kudos DOES™**.

## Server Context

- **SSH Destination:** `u483747408@185.211.7.113:65002`
- **Domain Webroot:** `/home/u483747408/domains/gold-trout-815009.hostingersite.com`
- **Public URL:** `https://gold-trout-815009.hostingersite.com`
- **PHP 8.4 Binary:** `/opt/alt/php84/usr/bin/php` (CloudLinux PHP 8.4)
- **Database:** SQLite at `database/database.sqlite` (Protected on the server)
- **Authentication:** `HOSTINGER_SSH_PASSWORD` in `.env` or SSH key

## Step-by-Step Deployment Routine

Whenever requested to deploy or inspect production, follow this 4-step checklist:

### 1. Pre-flight Check (Git & Status)
1. Check working directory status:
   ```bash
   git status --short
   ```
   **Rule:** Never deploy uncommitted code without committing it first or getting explicit user consent.
2. Check differences with production:
   ```bash
   npm run deploy:status
   ```
   Examine:
   - What commit is currently active on the server (`deployed.json`).
   - Which local commits are pending deployment.

### 2. Quality Assurance
1. Run Pint to ensure code styling:
   ```bash
   vendor/bin/pint --dirty --format agent
   ```
2. Run test suite:
   ```bash
   php artisan test --compact
   ```
   Ensure all tests pass before deploying.

### 3. Execution
Run the deployment script:
```bash
# Standard deploy
npm run deploy

# Or deploy with a semantic version tag
npm run deploy -- --tag=v1.0.1

# Or simulate first if there are uncertain file changes
npm run deploy:dry
```

The script automatically:
- Compiles Vite assets (`npm run build`).
- Synchronizes changed files via differential `rsync` over SSH port 65002.
- **Protects** remote `.env`, `database/*.sqlite*`, and user uploads in `storage/` from deletion or overwrite.
- Runs remote artisan commands with PHP 8.4:
  - `php artisan migrate --force`
  - `php artisan storage:link`
  - `php artisan optimize:clear`
  - `php artisan optimize`
- Writes `deployed.json` for version tracking.

### 4. Verification
Verify that the production application responds correctly:
```bash
curl -s -o /dev/null -w "%{http_code}\n" https://gold-trout-815009.hostingersite.com/login
```
Expected output: `200`.

Report the deployed version, commit hash, and health confirmation to the user.
