# Pavel Silinskii Maintenance Mode

![WordPress](https://img.shields.io/badge/WordPress-5.9%2B-21759B?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPL%20v2-green)
![Version](https://img.shields.io/badge/Version-1.0.0-blue)

Lightweight maintenance mode for WordPress. Serves a customizable maintenance page with **HTTP 503 + Retry-After**, an optional countdown, and access by role, IP address or secret link. No external scripts, no tracking.

---

## Table of Contents

- [Features](#features)
- [How It Works](#how-it-works)
- [Installation](#installation)
- [Settings Reference](#settings-reference)
- [Access Rules](#access-rules)
- [Template Override](#template-override)
- [Hooks](#hooks)
- [Security](#security)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Changelog](#changelog)

---

## Features

- **SEO-safe** — `503 Service Unavailable` with a `Retry-After` header
- **Countdown** — optional expected end date with a live timer
- **Auto-disable** — the site goes live by itself at the expected end
- **Customizable page** — title, rich-text message, logo, background and text colors
- **Access control** — administrators always, plus selected roles, an IP whitelist and a secret bypass link
- **Preview** — see the page without enabling the mode
- **Admin bar indicator** — red "Maintenance Mode: ON" so it's never forgotten
- **Doesn't break the backend** — wp-admin, `wp-login.php`, REST API, `admin-ajax.php`, WP-Cron and WP-CLI keep working
- **Cache-aware** — sends no-cache headers and defines `DONOTCACHEPAGE`
- **Theme override** — drop `psmm-maintenance.php` into your theme

## How It Works

The plugin hooks into `template_redirect` (priority `0`). On every front-end request, while maintenance mode is active:

1. If the URL contains a valid `?psmm_bypass=<key>`, a bypass cookie is set and the visitor is redirected to the clean URL.
2. `robots.txt`, `favicon.ico` and visitors who pass an [access rule](#access-rules) get the live site.
3. Everyone else gets the maintenance page:

```http
HTTP/1.1 503 Service Unavailable
Retry-After: 3600
Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private
Content-Type: text/html; charset=UTF-8
```

`Retry-After` is the number of seconds until the expected end if one is set, otherwise the **Retry-After** setting (minutes × 60).

Requests that never reach `template_redirect` — wp-admin, login, REST API, AJAX, cron, CLI — are not affected.

## Installation

1. Copy the plugin folder to `wp-content/plugins/pavel-silinskii-maintenance-mode/`.
2. Activate it on the **Plugins** screen. Maintenance mode stays **off** after activation.
3. Go to **Settings → Maintenance Mode**, set up the page, check **Enable maintenance mode** and save.

## Settings Reference

All settings are stored in a single option, `psmm_settings`.

| Key                | Type     | Default                  | Description                                                |
| ------------------ | -------- | ------------------------ | ---------------------------------------------------------- |
| `enabled`          | bool     | `false`                  | Maintenance mode on/off                                    |
| `end_datetime`     | string   | `''`                     | Expected end, `Y-m-d\TH:i` in the site timezone            |
| `auto_disable`     | bool     | `false`                  | Treat the mode as off once `end_datetime` has passed       |
| `retry_after`      | int      | `60`                     | Minutes for `Retry-After` when no end is set (1–10080)     |
| `title`            | string   | `We'll be back soon`     | Page heading and `<title>`                                 |
| `message`          | string   | maintenance text         | Rich-text message (`wp_kses_post`)                         |
| `show_countdown`   | bool     | `true`                   | Show the countdown when an end date is in the future       |
| `logo_url`         | string   | `''`                     | Logo image URL                                             |
| `background_color` | string   | `#1e1e2e`                | Page background (hex)                                      |
| `text_color`       | string   | `#ffffff`                | Text color (hex)                                           |
| `bypass_roles`     | string[] | `[]`                     | Roles that see the live site                               |
| `ip_whitelist`     | string[] | `[]`                     | IPv4/IPv6 addresses that see the live site                 |
| `bypass_key`       | string   | random, 20 chars         | Secret for the bypass link                                 |

## Access Rules

A visitor sees the live site if **any** of these is true:

- They have the `manage_options` capability (administrators).
- They are logged in with one of the `bypass_roles`.
- Their `REMOTE_ADDR` is in `ip_whitelist`.
- They hold a valid bypass cookie, set by opening `https://example.com/?psmm_bypass=<key>`. The cookie lasts 7 days.

Administrators can preview the page at any time with `?psmm_preview=1` (served with status 200, not 503).

## Template Override

Copy `templates/maintenance.php` to your theme as `psmm-maintenance.php`. Available variables:

| Variable          | Type       | Description                       |
| ----------------- | ---------- | --------------------------------- |
| `$settings`       | `array`    | All plugin settings               |
| `$end_timestamp`  | `int\|null` | Expected end as Unix time         |
| `$show_countdown` | `bool`     | Whether the countdown is rendered |

The template prints its assets with `wp_print_styles( 'psmm-frontend' )` and `wp_print_scripts( 'psmm-countdown' )`. Colors are exposed as CSS custom properties `--psmm-bg` and `--psmm-text`.

## Hooks

```php
// Let a custom rule bypass maintenance mode.
add_filter( 'psmm_can_bypass', function ( bool $allowed ): bool {
	return $allowed || current_user_can( 'edit_posts' );
} );

// Use a template from anywhere.
add_filter( 'psmm_template', function ( string $template ): string {
	return get_stylesheet_directory() . '/templates/maintenance.php';
} );
```

## Security

- Settings page requires `manage_options`; the form uses the Settings API nonce.
- Every field is sanitized and whitelisted on save; all output is escaped.
- The bypass key is compared with `hash_equals()`. The cookie stores an HMAC of the key (`wp_salt( 'auth' )`), not the key itself, so changing the key revokes every issued cookie.
- The bypass cookie is `HttpOnly` and `Secure` on HTTPS.
- The IP whitelist checks `REMOTE_ADDR` only — `X-Forwarded-For` and similar headers are client-controlled and are ignored.

> **Behind a reverse proxy or CDN?** `REMOTE_ADDR` will be the proxy address. Use roles or the bypass link instead, or set `REMOTE_ADDR` correctly at the server level.

## Project Structure

```
pavel-silinskii-maintenance-mode/
├── pavel-silinskii-maintenance-mode.php   # Bootstrap, constants, activation
├── uninstall.php                          # Deletes psmm_settings
├── includes/
│   ├── class-psmm-settings.php            # Settings: defaults, is_active(), end time
│   ├── class-psmm-frontend.php            # Frontend: access rules, 503 page
│   └── class-psmm-admin-bar.php           # AdminBar: "Maintenance Mode: ON"
├── admin/
│   └── class-psmm-admin.php               # Admin: Settings → Maintenance Mode
├── templates/
│   └── maintenance.php                    # Maintenance page markup
└── assets/
    ├── css/psmm-frontend.css
    ├── css/psmm-admin.css
    ├── js/psmm-countdown.js
    └── js/psmm-admin.js                   # Color pickers, media library logo
```

All classes live in the `PavelSilinskii\MaintenanceMode` namespace.

## Requirements

- WordPress 5.9+
- PHP 8.0+

## Changelog

### 1.0.0

- Initial release.
