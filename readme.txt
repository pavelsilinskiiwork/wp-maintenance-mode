=== Pavel Silinskii Maintenance Mode ===
Contributors: pavelsilinskii
Tags: maintenance mode, wp-cli, deployment, 503, maintenance
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Maintenance mode for deploy scripts: toggle it from WP-CLI with a safety timeout. Branded 503 page, admins keep working.

== Description ==

Pavel Silinskii Maintenance Mode is built for developers and agencies who deploy WordPress sites from scripts and CI/CD pipelines.

Wrap your deploy in two commands:

`wp psmm on --for=15m`
`# git pull, composer install, wp core update-db ...`
`wp psmm off`

If the deploy script fails halfway, the site comes back by itself when the timeout ends — no more sites left in maintenance overnight.

Why not core `wp maintenance-mode`? Core's mode locks out everyone, administrators included, shows a plain "Briefly unavailable" message, and has a fixed 10-minute window. This plugin:

* Serves a branded page with HTTP 503 and a Retry-After header matching your timeout
* Keeps the site available to administrators, chosen roles, whitelisted IPs and bypass-link holders, so you can check the deploy before visitors do
* Lets you choose the timeout per deploy: 90s, 15m, 2h, 1h30m
* Never blocks wp-admin, login, the REST API, AJAX, cron or WP-CLI itself

WP-CLI commands:

* `wp psmm on [--for=<duration>]` — enable, optionally with an automatic timeout
* `wp psmm off` — disable
* `wp psmm status [--format=table|json|yaml]` — state, end time and bypass link
* `wp psmm is-active` — exit code 0 when active, 1 otherwise, for use in scripts

Also works without the command line, from Settings → Maintenance Mode:

* Expected end date with a live countdown and optional auto-disable
* Customizable title, message, logo, background and text colors
* Secret bypass link for clients and testers (valid for 7 days, revocable)
* Preview button and a red "Maintenance Mode: ON" admin bar indicator
* Theme override via psmm-maintenance.php
* No external scripts — no CDN, no tracking

== Installation ==

1. Upload plugin files to /wp-content/plugins/pavel-silinskii-maintenance-mode/
2. Activate through the Plugins screen.
3. Go to Settings → Maintenance Mode to customize the page.
4. Enable maintenance mode there, or run `wp psmm on --for=15m` from your deploy script.

== Frequently Asked Questions ==

= What happens if my deploy script fails before `wp psmm off`? =

With `--for`, maintenance mode turns off by itself when the timeout ends. Without it, the mode stays on until you turn it off.

= Will maintenance mode hurt my SEO? =

No. The page is served with HTTP status 503 and a Retry-After header, which tells search engines the outage is temporary and when to come back.

= I enabled maintenance mode but I still see my site. =

Administrators always see the live site. Use the "Preview maintenance page" button or open the site in a private window.

= Visitors still see the live site. =

A page cache (plugin, server or CDN) may be serving cached pages. Purge the cache after enabling maintenance mode.

= How do I give a client access without an account? =

Copy the secret bypass link from the Access section, or run `wp psmm status`. Clear the key and save to revoke all shared links.

= Can I use my own template? =

Yes. Copy templates/maintenance.php to your theme as psmm-maintenance.php, or use the psmm_template filter.

== Changelog ==

= 1.0.0 =
* Initial release
