# Utmatra - UTM Attribution and Tracking

Self-hosted UTM and campaign tracking for WordPress: short links, QR codes, first/last-touch attribution, WooCommerce revenue attribution, form lead capture and a live dashboard. No third-party analytics service involved.

![Requires WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-21759b)
![Requires PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4)
![License GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

![Dashboard overview](.wordpress.org/screenshot-1.png)

## Features

**Tracking**
- Cache-proof front-end beacon that records the first page of every visit and every UTM-tagged visit, even behind a full-page cache, with a server-side fallback for visitors without JavaScript.
- Channel classification at write time: direct, organic search, paid search, paid social, organic social, email, referral, AI assistant, display and affiliate.
- UTM parameters, ad click IDs (`gclid`, `fbclid`, `msclkid`, `ttclid`, `li_fat_id`, `twclid`) and up to ten custom parameters of your own (coupon, affiliate ID, promo code).
- First- and last-touch attribution cookie, gated on the [WP Consent API](https://github.com/rlankhorst/wp-consent-level-api).
- Bot filtering and click deduplication.

**Links**
- Pretty short links at `/go/{slug}/` that count the click and forward with the UTM parameters appended.
- A QR code per link, generated in the browser.
- `[utmatra_link]` shortcode, UTM naming presets and a build-a-URL-only mode.

**Reporting**
- Chart.js dashboard: clicks over time, source, campaign, medium, device, channel, landing page, referrer and ad platform.
- WooCommerce revenue attribution, with first/last touch on every paid order.
- Customer journeys: every tagged visit that led to an order or form lead.
- CSV export, a weekly email summary and a read-only REST API (`utmatra/v1`).
- WordPress Abilities for AI agents, usable through the Abilities API or the WordPress MCP Adapter (see below).
- Tracking Doctor diagnostics for caching, consent, cookies, form coverage and WooCommerce attribution.

**Forms and integrations**
- Attribution capture for Contact Form 7, Gravity Forms, Ninja Forms, Elementor Pro Forms, WPForms, Fluent Forms, Formidable Forms, Forminator and Bricks, plus any form with a matching hidden field.
- Signed lead and order webhooks for Zapier, Make, HubSpot and GoHighLevel.

**Privacy**
- IP addresses stored only as salted SHA-256 hashes.
- Configurable data retention (180 days by default).
- No outbound requests unless you configure a webhook. Chart.js, the QR library and Action Scheduler are bundled.

## AI agents and WordPress Abilities

Utmatra registers its features with the [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/) (core since WordPress 6.9), in the `utmatra` category. AI agents reach them over the `wp-abilities/v1` REST namespace or through the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter), which Utmatra does not bundle. Abilities run as the authenticated user, with the same capability checks and sanitising as the dashboard. Core picks the HTTP method of `/wp-abilities/v1/abilities/<id>/run` from the annotations: GET for read abilities and DELETE for `utmatra/update-settings` (destructive and idempotent), both taking `input[...]` query arguments, and POST with a JSON `input` for the rest.

| Ability | Capability | Read-only | Destructive | MCP public |
| --- | --- | --- | --- | --- |
| `utmatra/get-stats` | `utmatra_view_reports` | yes | no | yes |
| `utmatra/list-links` | `utmatra_view_reports` | yes | no | yes |
| `utmatra/list-events` | `utmatra_view_reports` | yes | no | yes |
| `utmatra/list-journeys` | `utmatra_view_reports` | yes | no | yes |
| `utmatra/get-journey` | `utmatra_view_reports` | yes | no | yes |
| `utmatra/export-events` | `utmatra_view_reports` | yes | no | yes |
| `utmatra/get-diagnostics` | `utmatra_view_reports` | yes | no | yes |
| `utmatra/get-settings` | `utmatra_view_reports` | yes | no | yes |
| `utmatra/create-link` | `utmatra_manage_links` | no | no | no |
| `utmatra/update-link` | `utmatra_manage_links` | no | no | no |
| `utmatra/delete-link` | `utmatra_manage_links` | no | yes | no |
| `utmatra/create-preset` | `utmatra_manage_links` | no | no | no |
| `utmatra/delete-preset` | `utmatra_manage_links` | no | yes | no |
| `utmatra/update-settings` | `utmatra_manage_settings` + opt-in | no | yes | no |
| `utmatra/send-test-email` | `utmatra_manage_settings` + opt-in | no | no | no |
| `utmatra/send-test-webhook` | `utmatra_manage_settings` + opt-in | no | no | no |

| Capability | Grants |
| --- | --- |
| `utmatra_view_reports` | The dashboard, the admin Dashboard widget, every read REST route and every read ability |
| `utmatra_manage_links` | Create, update and delete links and presets (implies view) |
| `utmatra_manage_settings` | Save settings, send the test email and test webhook (implies view) |

Administrators get all three on activation and upgrade. Grant them to other roles with a role editor, or remap them with the `utmatra_capability_map` filter. Uninstall removes them from every role.

**MCP.** Only the read abilities carry `meta.mcp.public = true`, so the MCP Adapter's default server lists those and not the write abilities.

**Opt-in.** The three settings abilities fail with HTTP 403 (`utmatra_agent_settings_disabled`) until someone who can manage settings switches on **Allow AI agents to change settings** on the dashboard settings card. The switch can only be saved from the dashboard, never through an ability or an Application Password request; the `utmatra_abilities_allow_settings_writes` filter overrides it.

**Guard rails.** The webhook URL is not writable through `update-settings`; retention set by an agent is 0 or 30 to 3,650 days; `delete-link` and `delete-preset` need `confirm: true`; `export-events` returns at most 2,000 rows with a `truncated` flag; `get-settings` returns only the webhook host and never the secret. The opt-in and guard rails apply to abilities only; the `utmatra/v1` write routes the dashboard uses keep their behaviour for users with `utmatra_manage_links` or `utmatra_manage_settings`. Write abilities fire `utmatra_ability_executed` with the ability id, user id and input keys (never the values): link, preset and settings abilities after a successful change, the test email and webhook after every send attempt. Full details are in the AI agents FAQ in [readme.txt](readme.txt).

## Screenshots

**Short links and QR codes.** Every tracked link gets a `/go/` short link and a QR code you can download as a PNG.

![Links tab with a QR code open](.wordpress.org/screenshot-7.png)

**UTM naming presets.** Save a source and medium combination once, and apply it in one click. Values are normalized so `Paid Social` and `paid-social` never split your reports.

![Add tracked link with a preset applied](.wordpress.org/screenshot-8.png)

**Build a URL only.** Get a tagged URL to paste anywhere, without creating a short link.

![Build a tagged URL mode](.wordpress.org/screenshot-9.png)

**Customer journeys.** Every tagged visit that led to an order or form lead.

![Journeys tab](.wordpress.org/screenshot-3.png)

**Tracking Doctor.** Local checks for caching, consent, cookies, form coverage and WooCommerce attribution.

![Tracking Doctor diagnostics](.wordpress.org/screenshot-6.png)

The full documentation (FAQ including AI agents and abilities, filter reference, REST API reference, external services) is in [readme.txt](readme.txt).

## Requirements

- WordPress 6.9 or later
- PHP 8.0 or later
- WooCommerce is optional and enables revenue attribution

## Installation

Download the latest zip from [Releases](https://github.com/mahlamusa/utmatra-utm-attribution-and-tracking/releases), then upload it under **Plugins → Add New → Upload Plugin** and activate it. The dashboard is under **Utmatra** in the admin sidebar.

## Development

Clone the repository into `wp-content/plugins/utmatra-utm-attribution-and-tracking` and install the Composer dependency (Action Scheduler):

```bash
git clone https://github.com/mahlamusa/utmatra-utm-attribution-and-tracking.git
cd utmatra-utm-attribution-and-tracking
composer install
```

There is no JavaScript or CSS build step. The files in `assets/` ship as written.

Coding standards follow WordPress Core and are checked with PHP_CodeSniffer using `phpcs.xml`:

```bash
phpcs
phpcbf
```

Release scripts (they need `composer` and WP-CLI for the translations template):

| Command | What it does |
| --- | --- |
| `bash bin/build.sh` | Regenerates `languages/utmatra-utm-attribution-and-tracking.pot` |
| `bash bin/release.sh` | Builds the install zip in `releases/` |
| `bash bin/wp-org-publish.sh <version>` | Stages a WordPress.org SVN working copy, without committing |

The shipped plugin is built with `git archive`, so everything marked `export-ignore` in `.gitattributes` (this README, `bin/`, `specs/`, dev manifests) stays out of the zip.

## Bundled libraries

- [Chart.js](https://github.com/chartjs/Chart.js) 4.5.1, MIT
- [QR Code Generator](https://github.com/kazuhikoarase/qrcode-generator) by Kazuhiko Arase, MIT
- [Action Scheduler](https://github.com/woocommerce/action-scheduler), GPL-3.0, installed with Composer

## License

GPL-2.0-or-later. See [LICENSE.md](LICENSE.md).
