# GitHub Release Asset Issue - v0.1.54

## Problem

The initial GitHub release for v0.1.54 was created **without uploading the distribution zip file** as a release asset. WordPress plugin update mechanism requires the zip file to be attached to the GitHub release.

### Error Received
```
Update failed: The package could not be installed.
You have to study how the earlier version package was created and follow the exact same approach and then update this version or bump and create a new corrected version
```

### Root Cause
- GitHub release was created using `gh release create` command
- **No zip file was attached** to the release
- WordPress plugin updater cannot find a downloadable asset

## Solution

### Step 1: Build the Distribution Zip
Use the canonical build script to create the plugin package:

```bash
cd /root/dev/EcomCine
./scripts/build-ecomcine-release.sh
```

This script:
1. Reads version from `ecomcine/ecomcine.php`
2. Rsyncs plugin files to `dist/ecomcine/`
3. Creates `dist/ecomcine-<version>.zip` with correct structure
4. Generates manifest file

### Step 2: Upload Zip to GitHub Release
```bash
gh release upload v0.1.54 dist/ecomcine-0.1.54.zip --clobber
```

### Step 3: Verify Asset
```bash
gh release view v0.1.54 --json assets
```

## Correct Zip Structure

The zip file must have this structure for WordPress compatibility:

```
ecomcine-0.1.54.zip
└── ecomcine/
    ├── ecomcine.php          ← Main plugin file (required)
    ├── bundled-theme/
    ├── modules/
    ├── includes/
    ├── mu-plugins/
    └── runtime/
```

**Key requirement**: The `ecomcine/` folder must contain `ecomcine.php` at the root level.

## Verification Checklist

- [ ] Version bumped in `ecomcine/ecomcine.php`
- [ ] Build script executed: `./scripts/build-ecomcine-release.sh`
- [ ] Zip file created: `dist/ecomcine-0.1.54.zip`
- [ ] Zip uploaded to GitHub: `gh release upload v0.1.54 dist/ecomcine-0.1.54.zip`
- [ ] Asset visible in release: `gh release view v0.1.54 --json assets`
- [ ] Zip structure verified: `unzip -l dist/ecomcine-0.1.54.zip`

## Related Documentation

- `scripts/build-ecomcine-release.sh` - Canonical build script
- `specs/GITHUB-AUTH-REFERENCE.md` - GitHub release workflow
- `README-FIRST.md` - Repository context

## Prevention

Always attach the distribution zip when creating a GitHub release:

```bash
# Correct workflow
gh release create v<version> --title "Release v<version>" --notes "<notes>"
gh release upload v<version> dist/ecomcine-<version>.zip
```

**Never** create a release without the zip asset if WordPress update is expected.
