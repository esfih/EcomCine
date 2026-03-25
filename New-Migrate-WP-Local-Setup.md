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
| Control-plane reuse guide | `foundation/wp/docs/CONTROL-PLANE-REUSE-GUIDE.md` |
| Implementation rules | `foundation/core/docs/IMPLEMENTATION-RULES.md` |
| Security protocol | `foundation/core/docs/SECURITY-VALIDATION-PROTOCOL.md` |
| Terminal rules (Git Bash) | `foundation/core/docs/TERMINAL-RULES.md` |
