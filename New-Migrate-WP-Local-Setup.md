# Migrate an Existing WordPress Project into the Dev Framework

> Use this guide when you have an existing WordPress site (live or staging) with custom
> theme(s), plugins, and a database that you want to bring under the Git + Docker + GitHub
> methodology for the first time.  
> For a brand-new project with no prior code, use `New-WP-Local-Setup.md` instead.

---

## Project Structure for a Theme + Multi-Plugin Mono-Repo

All custom artifacts live together in one repo. Third-party premium plugins are **never** committed.

```
c:\dev\my-project\
├── theme/                ← your custom / child theme
├── plugin-a/             ← custom plugin 1
├── plugin-b/             ← custom plugin 2
├── plugin-c/             ← custom plugin 3
├── cp-plugin/            ← control-plane plugin (billing/licensing) — if applicable
├── deps/                 ← premium plugin ZIPs (gitignored — store privately)
│   ├── dokan-pro.zip
│   └── woocommerce-bookings.zip
├── db/
│   └── seed.sql          ← scrubbed dev DB export (no PII, no real orders)
├── docker-compose.yml
├── .env
├── scripts/
│   └── setup-deps.sh     ← installs free + premium dependency plugins
├── specs/
├── foundation/           ← pulled in by bootstrap-foundation.sh
└── .github/copilot-instructions.md
```

### What goes in the repo vs. not

| Artifact | In repo? | Notes |
|---|---|---|
| Custom theme | ✅ Yes | `theme/` |
| Custom plugins | ✅ Yes | one folder each |
| WooCommerce | ❌ No | Free — install via WP-CLI in `setup-deps.sh` |
| Dokan Free | ❌ No | Free — install via WP-CLI |
| Dokan Pro | ❌ No | **Premium** — store ZIP in `deps/` (gitignored), document download URL in `README-FIRST.md` |
| WooCommerce Bookings | ❌ No | **Premium** — same pattern as Dokan Pro |
| Live database dump | ❌ No | Contains real customer / order PII — never commit |
| Scrubbed dev DB seed | ✅ Optional | `db/seed.sql` — config + structure only, no PII |

---

## Step 1 — Create Repo from Template (GitHub)

Go to [esfih/wp-plugin-dev-template](https://github.com/esfih/wp-plugin-dev-template)  
→ **Use this template** → **Create a new repository** → name it (e.g. `my-marketplace`).

---

## Step 2 — Clone and Pull Foundation Layers

```bash
git clone https://github.com/<your-org>/my-marketplace.git
cd my-marketplace
./scripts/bootstrap-foundation.sh

# Enable repository-local policy hooks
./scripts/install-git-hooks.sh
```

---

## Step 3 — Import Your Existing Code

Copy your existing custom artifacts into the repo. Do **not** copy WooCommerce, Dokan, or Bookings.

```bash
# From your local backup / FTP download of the live site:
cp -r /path/to/live-backup/wp-content/themes/my-theme ./theme
cp -r /path/to/live-backup/wp-content/plugins/plugin-a ./plugin-a
cp -r /path/to/live-backup/wp-content/plugins/plugin-b ./plugin-b
cp -r /path/to/live-backup/wp-content/plugins/plugin-c ./plugin-c

# Place premium plugin ZIPs in deps/ (gitignored — never staged)
mkdir -p deps
cp /path/to/dokan-pro.zip deps/
cp /path/to/woocommerce-bookings.zip deps/
```

Add to `.gitignore`:
```
deps/
db/*.sql
```

Do an initial history-baseline commit before any refactoring:
```bash
git add theme/ plugin-a/ plugin-b/ plugin-c/
git commit -m "chore(import): initial import of existing custom theme and plugins"
```

---

## Step 4 — Configure Environment

```bash
cp .env.example .env
```

Edit `.env`:

```bash
COMPOSE_PROJECT_NAME=my_marketplace_dev
WP_IMAGE=wordpress:6.5-php8.1-apache   # match the PHP/WP version live is running
DB_NAME=wordpress
DB_USER=wp_user
DB_PASSWORD=wp_pass_dev
WP_PORT=8180
PMA_PORT=8181
PLUGIN_SLUG=plugin-a
CP_PLUGIN_SLUG=my-marketplace-cp       # only if using FluentCart control-plane
```

> **Multi-project port conflict:** WebMasterOS already uses `8080`, `8081`, `8090`, `8091`.
> Start new projects at `8180`/`8181` and increment by 100 for each additional project.
> Run `docker ps --format 'table {{.Ports}}'` to check what is currently occupied.

Update `docker-compose.yml` volume mounts — one entry per artifact:

```yaml
volumes:
  - ./theme:/var/www/html/wp-content/themes/my-theme
  - ./plugin-a:/var/www/html/wp-content/plugins/plugin-a
  - ./plugin-b:/var/www/html/wp-content/plugins/plugin-b
  - ./plugin-c:/var/www/html/wp-content/plugins/plugin-c
```

---

## Step 5 — Create the Dependency Setup Script

Create `scripts/setup-deps.sh` to install all third-party plugins in the right order:

```bash
#!/usr/bin/env bash
# Install third-party dependency plugins into the local WordPress container.
# Free plugins are pulled from wordpress.org; premium ZIPs must exist in deps/.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."

WP="./scripts/wp.sh"

echo "Installing free dependency plugins..."
$WP wp plugin install woocommerce --activate
$WP wp plugin install dokan-lite --activate

echo "Installing premium plugins from deps/ ..."
for zip in deps/dokan-pro.zip deps/woocommerce-bookings.zip; do
  if [[ -f "$zip" ]]; then
    $WP wp plugin install "$zip" --activate
  else
    echo "WARNING: $zip not found — place the ZIP in deps/ and re-run."
  fi
done

echo "Activating custom plugins and theme..."
$WP wp plugin activate plugin-a plugin-b plugin-c
$WP wp theme activate my-theme

echo "Done."
```

```bash
chmod +x scripts/setup-deps.sh
```

---

## Step 6 — Export and Scrub the Live Database

**On the live server** (via SSH + WP-CLI or phpMyAdmin), export a full dump:

```bash
wp db export live-full.sql --allow-root
```

**Scrub before using locally** — remove or anonymize PII:

```bash
# Remove real customer accounts and order data
wp db query "DELETE FROM wp_users WHERE ID > 1;" --allow-root
wp db query "DELETE FROM wp_usermeta WHERE user_id > 1;" --allow-root
wp db query "TRUNCATE TABLE wp_wc_orders;" --allow-root
wp db query "TRUNCATE TABLE wp_wc_order_items;" --allow-root
wp db query "TRUNCATE TABLE wp_wc_order_itemmeta;" --allow-root
wp db query "DELETE FROM wp_posts WHERE post_type = 'shop_order';" --allow-root
wp db query "DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT ID FROM wp_posts);" --allow-root

# Export the scrubbed version
wp db export db/seed.sql --allow-root
```

Keep only `db/seed.sql` in the repo. Never commit `live-full.sql`.

> **What to keep in the seed:** WooCommerce settings, Dokan settings, plugin options, menus,
> pages, product categories, shipping zones, tax rules, permalinks. These configure the app
> state without exposing customer data.

---

## Step 7 — Start WordPress, Import DB, Fix URLs

```bash
# Start containers
docker compose up -d

# Import the scrubbed seed DB
./scripts/wp.sh wp db import db/seed.sql

# Replace all live URLs with the local dev URL
./scripts/wp.sh wp search-replace 'https://yourlivesite.com' 'http://localhost:8180' --all-tables

# Reset the admin password to something known locally
./scripts/wp.sh wp user update 1 --user_pass=admin

# Set pretty permalinks (required for WooCommerce REST API)
./scripts/wp.sh wp rewrite structure '/%postname%/' --hard
```

---

## Step 8 — Install Dependencies and Activate Custom Code

```bash
./scripts/setup-deps.sh
```

If any premium ZIP is missing from `deps/`, the script will warn you — download it from your
vendor account and place it in `deps/` then re-run.

---

## Step 9 — Verify Everything

```bash
./scripts/check-local-wp.sh         # site URL, permalink, plugin activation
./scripts/run.sh check-prereqs      # Docker, WP-CLI, Git, bash
```

Also verify in browser:
- `http://localhost:8180/wp-admin` — WP admin loads, no fatal errors
- `http://localhost:8180/shop` — WooCommerce shop page renders
- `http://localhost:8180/dashboard` — Dokan vendor dashboard accessible (if applicable)

---

## Step 9.5 — Import Control-Plane FluentCart Baseline (Recommended)

If the project uses the private control-plane licensing model, import the reusable FluentCart/control-plane baseline fixture in one action:

```bash
./scripts/licensing/import-fluentcart-control-plane-seed.sh
```

This imports the committed SQL fixture `db/fluentcart-control-plane-seed.sql` using the active WordPress table prefix.

What it seeds:
- canonical product/licensing settings
- demo customer and 4 demo orders
- generated license keys and subscription graph
- FluentCart store/modules activation options required by the control-plane flow

---

## Step 10 — Set Up Specs and Copilot Instructions

```bash
cp foundation/core/templates/README-FIRST.template.md README-FIRST.md
cp foundation/core/templates/copilot-instructions.template.md .github/copilot-instructions.md
# Edit both — replace <PRODUCT_NAME>, <PRODUCT_REPO>, add WooCommerce/Dokan routing rules
```

---

## Step 11 — Release Workflow

Each custom artifact gets its own version bump and ZIP:

```bash
# Security pre-flight (required before every commit)
./scripts/security-validate-changed.sh --staged

# Bump all artifact versions together for a release
./scripts/bump-version.sh --plugin theme --to 1.2.0
./scripts/bump-version.sh --plugin plugin-a --to 1.2.0
./scripts/bump-version.sh --plugin plugin-b --to 1.2.0
./scripts/bump-version.sh --plugin plugin-c --to 1.2.0

# Commit (single-line only)
git add theme/ plugin-a/ plugin-b/ plugin-c/
git commit -m "feat(release): bump all artifacts to 1.2.0"

# Build release ZIPs
./scripts/build-release-zip.sh --plugin theme --version 1.2.0
./scripts/build-release-zip.sh --plugin plugin-a --version 1.2.0
./scripts/build-release-zip.sh --plugin plugin-b --version 1.2.0
./scripts/build-release-zip.sh --plugin plugin-c --version 1.2.0
```

Deploy to live: upload each ZIP via WP Admin → Plugins/Themes → Upload, or automate via
`release-plugin.sh` with a GitHub release step.

---

## Architecture Reference

| Topic | File |
|---|---|
| New blank project bootstrap | `New-WP-Local-Setup.md` |
| WP-CLI / Docker operations | `foundation/wp/docs/WP-LOCAL-OPS.md` |
| Docker image patterns | `foundation/wp/docs/WP-OVERLAY-README.md` |
| Two-plugin billing architecture | `foundation/wp/docs/licensing/BILLING-LICENSING-ARCHITECTURE.md` |

---

---

# EcomCine — Re-Setup on a New Computer

> Use this section when you already have the repo working on one machine and need to resume
> on a different computer. All custom code is in Git; the steps below cover the parts that
> are **not** — premium plugins, uploads, and the database.

---

## Prerequisites

Install these on the new machine before anything else:

- **Git** (with Git Bash on Windows)
- **Docker Desktop** (running, WSL2 backend on Windows)
- **GitHub CLI** (`gh`) — authenticated to your GitHub account

### Mandatory Workspace Baseline (WSL2-only)

- Use Ubuntu WSL2 as the primary development shell.
- Keep project folders under `/home/<user>/dev/` (Linux ext4), not `C:\dev` and not `/mnt/c/dev`.
- Run Docker/Compose commands from the WSL path of the repo.
- Validate with `./scripts/check-local-dev-infra.sh` before importing DB or running feature work.

---

## What to Transfer Manually (not in Git)

These items are gitignored and must be copied from your old machine (USB drive, network
share, or cloud storage) **before** running the setup steps:

| Item | Where to place it | Why it's not in Git |
|---|---|---|
| `deps/` folder | `/home/<you>/dev/EcomCine/deps/` | Premium plugins — cannot be redistributed |
| `castingagency-uploads.tar.gz` | `/home/<you>/dev/EcomCine/castingagency-uploads.tar.gz` | 198 MB binary, WP media library |
| `.env` file | `/home/<you>/dev/EcomCine/.env` | Contains credentials |
| `repeated-prompt.instructions.md` | `C:\Users\<you>\.copilot\instructions\` | User-level VS Code Copilot file — outside any repo |
| `~/.ssh/castingagency_debug` | `C:\Users\<you>\.ssh\` | SSH private key for live server |

> **Note — `foundation/` and `.github/copilot-instructions.md` are already in Git** and
> come down automatically with `git clone`. No manual action needed for those.

**`deps/` must contain exactly:**
```
deps/
├── dokan-lite/
├── dokan-pro/
├── woocommerce/
├── woocommerce-bookings/
└── greenshift/
```
Each is an extracted plugin folder (not a ZIP), matching the volume mounts in
`docker-compose.yml`.

> **Alternative for `.env`:** If you don't have the `.env` from the old machine, copy
> `.env.example` to `.env` — the default values (`wp_user` / `wp_pass_dev` / `wordpress`)
> are correct for local dev.

---

## Setup Steps

### 1. Clone the repo

```bash
mkdir -p ~/dev
cd ~/dev
git clone https://github.com/esfih/EcomCine
cd EcomCine
```

### 2. Place the manually transferred items

Confirm these exist before continuing:
```
/home/<you>/dev/EcomCine/deps/dokan-pro/        ← must be present
/home/<you>/dev/EcomCine/deps/woocommerce-bookings/  ← must be present
/home/<you>/dev/EcomCine/castingagency-uploads.tar.gz
/home/<you>/dev/EcomCine/.env
```

Run infrastructure validation before continuing:

```bash
./scripts/check-local-dev-infra.sh
```

### 3. Start the containers

```bash
docker compose up -d
```

All custom theme, plugins, and SVG icons are volume-mounted automatically from the repo.
The `deps/` plugins are also mounted — no install step needed.

### 4. Import the database

**Option A — re-export fresh from the live server (recommended):**

```bash
# Export from live via SSH (Git Bash)
ssh -i ~/.ssh/castingagency_debug -p 5022 efttsqrtff@209.16.158.249 \
  "wp db export /home/efttsqrtff/castingagency-db-fresh.sql --allow-root --path=/home/efttsqrtff/public_html"

scp -i ~/.ssh/castingagency_debug -P 5022 \
  efttsqrtff@209.16.158.249:/home/efttsqrtff/castingagency-db-fresh.sql \
  db/castingagency-live.sql
```

**Option B — copy the SQL file from your old machine:**

Place it at `/home/<you>/dev/EcomCine/db/castingagency-live.sql`.

**Then import and fix URLs:**

```bash
./scripts/wp.sh wp db import db/castingagency-live.sql
./scripts/wp.sh wp search-replace 'https://castingagency.co' 'http://localhost:8180' --all-tables
./scripts/wp.sh wp user update 1 --user_pass=admin
./scripts/wp.sh wp rewrite structure '/%postname%/' --hard
```

### 4.1 Decommission Windows Working Copy (Required)

After confirming the WSL stack is healthy, remove the Windows repo copy to prevent
accidental fallback to Windows-mounted binds:

```powershell
Remove-Item -Recurse -Force C:\dev\EcomCine
```

Do not run active project runtime from `C:\dev\...` after this point.

### 5. Extract the uploads archive

```bash
# Copy the archive into the container and extract it
docker cp castingagency-uploads.tar.gz ecomcine_dev-wordpress-1:/tmp/castingagency-uploads.tar.gz
MSYS_NO_PATHCONV=1 docker exec ecomcine_dev-wordpress-1 sh -c \
  "cd /var/www/html/wp-content && tar xzf /tmp/castingagency-uploads.tar.gz && chown -R www-data:www-data uploads"
```

### 6. Activate all plugins and theme

```bash
./scripts/setup-deps.sh
```

This activates plugins in the correct dependency order for legacy parity environments:
WooCommerce → Dokan Lite → Dokan Pro → WooCommerce Bookings → EcomCine plugins → `ecomcine-base` theme.

### 7. Verify

```bash
./scripts/check-local-dev-infra.sh
./scripts/check-local-wp.sh
```

Then open in browser:
- **http://localhost:8180** — homepage / showcase renders, account tab visible
- **http://localhost:8180/wp-admin** — admin loads, no fatal errors (user: `admin` / pass: `admin`)
- **http://localhost:8181** — phpMyAdmin

The "Book session" button on a vendor card should open the booking modal (look for
`tmVendorBookingModal` in page source to confirm the JS object is present).

---

## VS Code / GitHub Copilot Setup

The repo-level Copilot instructions (`/.github/copilot-instructions.md`) are committed and
require no action — VS Code picks them up automatically after `git clone`.

The **user-level** repeated-prompt instruction file lives outside the repo in your Windows
user profile. Copy it from the old machine:

```
Source:  C:\Users\rtxa4\.copilot\instructions\repeated-prompt.instructions.md
Dest:    C:\Users\<your-username>\.copilot\instructions\repeated-prompt.instructions.md
```

Create the folder if it doesn't exist:
```powershell
New-Item -ItemType Directory -Path "$env:USERPROFILE\.copilot\instructions" -Force
```

Content of the file (paste if you don't have the copy):
```
---
description: Global reinforcement prompt for repository-aware coding chats in VS Code.
---

Read README-FIRST.md first for every meaningful response. Follow ./.github/copilot-instructions.md. Treat the runtime baseline as always-on: Git Bash by default, PowerShell only for Windows-specific tasks, one host Python 3 interpreter only, no repo-local .venv unless the repo later explicitly requires it, and prefer host tools over WSL or container entry unless the task explicitly requires a different runtime. Treat /specs and /specs/app-features as canonical and load only the minimum files needed for the current task.
```

This file is automatically picked up by VS Code Copilot for all workspaces on the machine.

---

## SSH Key for Live Server Access

The SSH key used to connect to `castingagency.co` (`~/.ssh/castingagency_debug`) is stored
only on your local machine — it is never committed.

On the new machine, either:
- Copy `~/.ssh/castingagency_debug` and `~/.ssh/castingagency_debug.pub` from the old machine, **or**
- Generate a new key pair and add the public key via cPanel → SSH Access Manager at
  `https://castingagency.co:2083`

Connection command (once key is in place):
```bash
ssh -i ~/.ssh/castingagency_debug -p 5022 efttsqrtff@209.16.158.249 "echo connected"
```

---

## SSH Key — app.topdoctorchannel.us (N0C Hosting)

The key `~/.ssh/ecomcine_n0c` authorizes access to `app.topdoctorchannel.us` on N0C mutualized hosting.
It is stored only on the dev machine and is **never committed**.

| Detail | Value |
|---|---|
| Key file | `~/.ssh/ecomcine_n0c` (ed25519 private key) |
| Public key | `~/.ssh/ecomcine_n0c.pub` |
| Host | `209.16.158.249` |
| Port | `5022` |
| User | `efttsqrtff` |
| WP root | `/home/efttsqrtff/app.topdoctorchannel.us` |

On a new machine, either:
- Copy `~/.ssh/ecomcine_n0c` and `~/.ssh/ecomcine_n0c.pub` from the old machine (set permissions: `chmod 600 ~/.ssh/ecomcine_n0c`), **or**
- Generate a new key pair (`ssh-keygen -t ed25519 -f ~/.ssh/ecomcine_n0c -C "ecomcine-dev"`) and add the public key via the N0C hosting panel → SSH access.

Verify connection:
```bash
ssh -i ~/.ssh/ecomcine_n0c -p 5022 efttsqrtff@209.16.158.249 "echo connected"
```

All WP-CLI remote operations use the wrapper script (which reads this key by default):
```bash
./scripts/wp-remote.sh plugin list
./scripts/wp-remote.sh plugin get ecomcine --fields=name,version,status
```

For full SSH command reference, diagnostics, and ownership fix commands, see
`specs/GITHUB-AUTH-REFERENCE.md` → "SSH Connection Reference".

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| HTTP 500 on homepage | PHP parse error (often BOM in a PHP file) | Check `docker exec ecomcine_dev-wordpress-1 tail -20 /var/www/html/wp-content/debug.log` |
| Booking modal JS not present | `enqueue_assets()` gate failing | Confirm `WC_Booking_Form` class exists and container has `woocommerce-bookings` in `deps/` |
| Vendor cards show no images | Uploads not extracted | Repeat step 5 |
| `deps/` plugin not activating | Folder present but wrong structure | Plugin folder must contain the main `.php` file directly (not nested in a ZIP subfolder) |
| Port 8180 already in use | Port conflict with another project | Change `WP_PORT` in `.env` and restart containers |
| Infra check fails with Windows path warning | Repo started from Windows filesystem | Move repo to `/home/<you>/dev`, restart from WSL terminal, rerun checks |
| Control-plane reuse guide | `foundation/wp/docs/CONTROL-PLANE-REUSE-GUIDE.md` |
| Implementation rules | `foundation/core/docs/IMPLEMENTATION-RULES.md` |
| Security protocol | `foundation/core/docs/SECURITY-VALIDATION-PROTOCOL.md` |
| Terminal rules (WSL shell) | `foundation/core/docs/TERMINAL-RULES.md` |
