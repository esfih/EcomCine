# Phase 4 - WMOS FluentCart Parity Seed

This document captures the canonical WMOS billing stack mapping that EcomCine now mirrors before deeper control-plane work.

## Canonical 4 Offers

- freemium: product_id 2566, variation_id 1, max_site_activations 1
- solo: product_id 2569, variation_id 2, max_site_activations 3
- maestro: product_id 2571, variation_id 3, max_site_activations 10
- agency: product_id 2573, variation_id 4, max_site_activations 100

## Billing Metadata Source Keys

- license settings key: license_settings
- allowances key: wmos_allowances_v1
- activation storage option: wmos_cp_activations

## Clone Workflow

1. Extract billing seed from WMOS SQL dump:

```bash
php scripts/licensing/extract-wmos-billing-seed.php /root/dev/WebMasterOS-main/WebMasterOS-DB.sql /tmp/ecomcine-wmos-seed.json
```

2. Import seed into local EcomCine options:

```bash
./scripts/wp.sh wp eval-file scripts/licensing/import-billing-seed.php -- /tmp/ecomcine-wmos-seed.json
```

3. Validate in WP CLI:

```bash
./scripts/wp.sh wp eval 'print_r( get_option("ecomcine_offer_catalog_overrides") );'
./scripts/wp.sh wp eval 'print_r( ecomcine_get_license_status_snapshot() );'
```

## Reusable SQL Seed (One-Action Import)

The finalized FluentCart/control-plane baseline is committed as:

- `db/fluentcart-control-plane-seed.sql`

This seed includes:

- product settings and variation pricing payloads
- licensing product meta and allowances
- demo customer + 4 orders + subscriptions
- generated licenses and compatibility license mirror rows
- required FluentCart store/modules activation options

To import in one action on any project/environment:

```bash
./scripts/licensing/import-fluentcart-control-plane-seed.sh
```

The importer auto-detects the active WordPress table prefix and applies the SQL safely using the current database container credentials.

To regenerate the SQL fixture from a validated local baseline:

```bash
./scripts/licensing/export-fluentcart-control-plane-seed.sh
```

## Notes

- This parity map is intentionally seeded before a dedicated private ecomcine-control-plane plugin is finalized.
- Imported activation records are stored in option ecomcine_cp_seed_activations for migration continuity.
