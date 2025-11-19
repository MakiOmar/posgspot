# Installation Fix for Missing ext-sodium Extension

## Problem
`lcobucci/jwt 5.6.0` (required by `laravel/passport 12.2`) requires the `ext-sodium` PHP extension, which is not enabled on your system.

## Solutions

### Option 1: Enable Sodium Extension (Recommended)

#### For cPanel/Shared Hosting:
1. Log into cPanel
2. Go to **Select PHP Version** or **MultiPHP Manager**
3. Enable the **sodium** extension
4. Save changes

#### For Linux Server (if you have SSH access):
```bash
# For PHP 8.3 (based on your path)
sudo apt-get install php8.3-sodium  # Ubuntu/Debian
# OR
sudo yum install php83-sodium       # CentOS/RHEL

# Then enable it
sudo phpenmod sodium
```

#### For WAMP (Windows):
1. Open `php.ini` file
2. Find the line: `;extension=sodium`
3. Remove the semicolon: `extension=sodium`
4. Restart WAMP services

### Option 2: Temporary Workaround (Not Recommended for Production)

I've updated `composer.json` to ignore the platform requirement temporarily. You can now run:

```bash
composer install --ignore-platform-req=ext-sodium
```

**Note:** This may cause runtime issues if Passport actually needs sodium for JWT operations. It's better to enable the extension.

### Option 3: Check if Sodium is Available

Run this to check:
```bash
php -m | grep sodium
```

If it shows `sodium`, the extension is installed but may need to be enabled in `php.ini`.

### Verification

After enabling sodium, verify it's loaded:
```bash
php -r "echo extension_loaded('sodium') ? 'Sodium is loaded' : 'Sodium is NOT loaded';"
```

## Updated composer.json

I've added a platform configuration to `composer.json` that allows installation without sodium, but **you should still enable the extension** for proper functionality.

## Next Steps

1. Enable the sodium extension using one of the methods above
2. Run `composer install` again
3. If you still have issues, run: `composer install --ignore-platform-req=ext-sodium`

