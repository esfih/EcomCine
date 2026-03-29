# WordPress Self-Hosted Plugin Updates (Reusable Pattern)

## Scope

Use this pattern for private/commercial plugins that should update inside WordPress admin without exposing GitHub credentials to end users.

Important policy note for this repository:
- Do not edit foundation subtree files directly.
- Keep canonical project guidance in specs and operational runbooks, then upstream to foundation via its own source workflow.

## Architecture

1. Update server endpoint (for example updates.example.com/update-server.php) exposes:
- action=info: returns JSON metadata (latest version, requirements, changelog, signed package URL)
- action=download: validates signed URL and streams release ZIP from private release storage

2. WordPress plugin integrates updater client:
- hook pre_set_site_transient_update_plugins to inject available update
- hook plugins_api to provide plugin details modal data
- cache info responses with site transient

3. Security model:
- API/GitHub token stored only on update server
- short-lived signed download URLs
- no secrets embedded in distributed plugin ZIP

## Server Contract (JSON)

The action=info response should include at least:
- version
- download_url
- requires
- requires_php
- tested
- sections.changelog

Optional but recommended:
- name
- slug
- homepage
- author
- last_updated
- icons / banners

## Deployment Steps

1. Build release ZIP where top-level folder matches plugin slug exactly.
2. Publish release artifact to private source (for example GitHub Releases).
3. Build clean updater deployment bundle (EcomCine repo):
- ./scripts/run-catalog-command.sh updates.package.clean
4. Deploy update-server.php and config.php to updates host.
5. Put real secrets only in server-side config.php.
6. Verify endpoint:
- GET /update-server.php?action=info&slug=<plugin-slug>
7. In WordPress admin, open Dashboard > Updates and check for updates.

## EcomCine Defaults

- Plugin slug: ecomcine
- Endpoint base: https://updates.ecomcine.com/update-server.php
- Info URL: https://updates.ecomcine.com/update-server.php?action=info&slug=ecomcine

## Operational Checklist

- Confirm new plugin version is greater than installed version.
- Confirm update ZIP folder name is ecomcine/.
- Confirm action=download signed URL is reachable by WordPress host.
- Confirm upgrader can write wp-content/plugins.
- Confirm plugin details modal loads changelog and metadata.
