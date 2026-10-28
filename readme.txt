=== Pavel Silinskii Maintenance Mode ===
Contributors: pavelsilinskii
Tags: maintenance mode, coming soon, under construction, 503, maintenance
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight maintenance mode with a proper 503 status and Retry-After header. SEO-safe, no external scripts, no tracking.

== Description ==

Pavel Silinskii Maintenance Mode shows visitors a clean maintenance page while you work on your site, and tells search engines the downtime is temporary.

Features:

* HTTP 503 Service Unavailable + Retry-After header — search engines keep your rankings
* Optional expected end date with a live countdown
* Auto-disable: the site goes live by itself when the countdown ends
* Customizable title, message, logo, background and text colors
* Administrators always see the live site
* Access for selected user roles
* IP whitelist
* Secret bypass link for clients and testers (valid for 7 days, revocable)
* Preview button for the maintenance page
* Red "Maintenance Mode: ON" indicator in the admin bar
* wp-admin, login, REST API, AJAX and cron keep working
* Theme override via psmm-maintenance.php
* No external scripts — no CDN, no tracking

== Installation ==

1. Upload plugin files to /wp-content/plugins/pavel-silinskii-maintenance-mode/
2. Activate through the Plugins screen.
3. Go to Settings → Maintenance Mode, configure the page and enable maintenance mode.

== Frequently Asked Questions ==

= Will maintenance mode hurt my SEO? =

No. The page is served with HTTP status 503 and a Retry-After header, which tells search engines the outage is temporary and when to come back.

= I enabled maintenance mode but I still see my site. =

Administrators always see the live site. Use the "Preview maintenance page" button or open the site in a private window.

= Visitors still see the live site. =

A page cache (plugin, server or CDN) may be serving cached pages. Purge the cache after enabling maintenance mode.

= How do I give a client access without an account? =

Copy the secret bypass link from the Access section. Clear the key and save to revoke all shared links.

= Can I use my own template? =

Yes. Copy templates/maintenance.php to your theme as psmm-maintenance.php, or use the psmm_template filter.

== Changelog ==

= 1.0.0 =
* Initial release
