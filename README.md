# EduSharing

# Purpose

EduSharing is a MediaWiki extension that integrates **edu-sharing repositories** into your wiki. It adds a selection dialogue to the **VisualEditor**, allowing users to embed educational resources from an edu-sharing repository directly into wiki pages.

**Features:**

- Seamless integration with **VisualEditor**.
- Direct access to resources from **edu-sharing repository**.
- Compatible with **MediaWiki 1.43 and higher**

---

# How does it work

The extension enables users to search and select resources from an edu-sharing repository via a dedicated dialogue in the editor. Selected resources are embedded as links or previews, depending on the repository’s configuration and the resource type.

---

# Requirements

- **MediaWiki 1.43+**
- **PHP 8.0** (or higher, depending on your MediaWiki version).
- Access to the **edu-sharing repository** (e.g., [edu-sharing Network](http://www.edu-sharing.com/)).
- **VisualEditor** must be installed to use the selection dialogue.

---

# Usage

The extension adds a new button to the VisualEditor toolbar, which opens a dialogue to search and select resources from the configured edu-sharing repository.

## Selecting Resources

1. Edit your wiki page.
2. Choose **Insert** -> **edu-sharing Media** in the toolbar.
3. Search for resources in the dialogue and select the desired item.
4. Add description (caption) and choose styling options
5. The resource will be embedded as a preview in the wiki page.

## Configuration

The extension requires configuration in your `LocalSettings.php`:

| Variable                      | Description                                                                           | Required | Default   |
| ----------------------------- | ------------------------------------------------------------------------------------- | -------- | --------- |
| `$wgEduSharingAppId`          | Your wiki’s application ID for the edu-sharing repository.                            | Yes      | -         |
| `$wgEduSharingAppDomain`      | The domain of your wiki (e.g., `yourwiki.domain.tld`).                                | Yes      | -         |
| `$wgEduSharingAppHost`        | The IP or hostname of your wiki server.                                               | Yes      | -         |
| `$wgEduSharingBaseUrl`        | The base URL of your edu-sharing repository (e.g., `https://repository.example.org`). | Yes      | -         |
| `$wgEduSharingForceGuestUser` | Force anonymous access for all users.                                                 | No       | `false`   |
| `$wgEduSharingGuestUserName`  | Username for anonymous access to the repository.                                      | No       | `esguest` |
| `$wgEduSharingUsageCleanupJobFallback` | Retry failed repository usage deletions through MediaWiki's JobQueue.       | No       | `true`    |

### Example Configuration

```php
# EduSharing Extension Configuration
wfLoadExtension( 'EduSharing' );

# Required: Repository connection settings
$wgEduSharingAppId = "your-wiki-app-id";
$wgEduSharingAppDomain = "yourwiki.domain.tld";
$wgEduSharingAppHost = "12.345.67.89";
$wgEduSharingBaseUrl = 'https://redaktion-staging.openeduhub.net/edu-sharing';

# Optional: Force anonymous access
$wgEduSharingForceGuestUser = true;

# Optional: Custom guest username
$wgEduSharingGuestUserName = 'YourGuestUserName';

# Optional: Disable JobQueue retries for failed usage deletions
$wgEduSharingUsageCleanupJobFallback = false;
```

### Configuration Notes:

- **Repository Registration**: After configuring the above, you must register your wiki with the edu-sharing repository. Run the following command to generate a key pair and retrieve the repository’s public key:
  ```bash
  php [MEDIAWIKI_INSTALL_DIR]/extensions/EduSharing/maintenance/createKeys.php [--regenerate-key-pair] [--get-repo-key-only]
  ```
- **Manual Registration**: Visit your repository’s admin page and enter the following URL to complete the registration:
  ```
  https://yourwiki.domain.tld/index.php?title=Special:EduSharingRegister
  ```

---

# Installation

### Download the Extension

Clone the extension into your MediaWiki `extensions/` directory:

```bash
cd extensions/
git clone <repository-url> EduSharing
```

### Install Dependencies via Composer

Install dependencies with Composer in the extension directory:

```bash
cd extensions/EduSharing
composer install --no-dev
```

If you only want to update dependencies later:

```bash
composer update
```

### Enable the Extension

Add the following line to your `LocalSettings.php`:

```php
wfLoadExtension( 'EduSharing' );
```

### Set Up the Database

Run the following command to create or update the required database table:

```bash
php maintenance/update.php
```

Alternatively, upgrade your wiki via `[MEDIAWIKI_INSTALL_URL]/mw-config`.

### Register the Extension

Follow the [Configuration](#configuration) steps to register your wiki with the edu-sharing repository.

---

# Features in Detail

## Resource Selection Dialogue

- Search and filter resources directly from the editor.
- Supports preview and metadata display for most resource types.
- Resources are embedded as links or interactive previews, depending on the repository’s capabilities.

## Anonymous Access

- By default, logged-out users access the repository as a guest.
- You can enforce anonymous access for all users with `$wgEduSharingForceGuestUser = true;`.
- Customize the guest username with `$wgEduSharingGuestUserName`.

## Database Integration

- The extension creates a `edusharing_resource` table to store metadata and references.
- This table is automatically updated during installation or upgrades.

## Usage Cleanup and JobQueue Fallback

When an embedded resource or an entire wiki page is deleted, the extension first tries to
delete the corresponding usage synchronously in the edu-sharing repository. If that request
fails and `$wgEduSharingUsageCleanupJobFallback` is enabled, a persistent
`eduSharingUsageCleanup` job is queued and the local resource record is removed. The job only
contains the old node and usage IDs, so restoring the page can safely create a new usage while
the old deletion is still pending. Deleting an already missing usage is treated as successful.

The fallback requires a persistently configured MediaWiki JobQueue and a regularly running job
runner. For example:

```bash
php maintenance/run.php runJobs --type=eduSharingUsageCleanup
```

Failed jobs return an error and remain eligible for retry according to the configured JobQueue
backend. Set `$wgEduSharingUsageCleanupJobFallback = false;` to retain the previous synchronous-only
behavior. If enqueueing itself fails, the local resource record is retained for manual recovery.

---

# Troubleshooting

### No resources appear in the dialogue

- Verify `$wgEduSharingBaseUrl` is correct and accessible.
- Check that your wiki is properly registered with the repository.
- Ensure the repository’s public key is correctly imported.

### Registration fails

- Double-check the URL provided to the repository admin (`Special:EduSharingRegister`).
- Verify the key pair was generated successfully (`createKeys.php`).

### Database errors

- Run `php maintenance/update.php` to ensure the `edusharing_resource` table exists.
- Check your database user permissions.

---

# Compatibility

- **Tested with**: MediaWiki 1.43, PHP 8.0+.
- **Repositories**: Compatible with edu-sharing 6.x+ repository.

---

# License

The software is licensed under the **MIT License**. For details, see [LICENSE](https://opensource.org/license/mit).

---

# History

- **2025-12-12**: Update for MediaWiki 1.43
