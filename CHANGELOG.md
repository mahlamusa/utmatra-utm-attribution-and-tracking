# Changelog

All notable changes to Utmatra - UTM Attribution and Tracking are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - Unreleased

### Added

- WordPress Abilities for every Utmatra feature: 16 abilities in the `utmatra` category, usable by AI agents through `wp-abilities/v1` or the WordPress MCP Adapter.
  - Read (`utmatra_view_reports`, public to MCP): `get-stats`, `list-links`, `list-events`, `list-journeys`, `get-journey`, `export-events`, `get-diagnostics`, `get-settings`.
  - Links and presets (`utmatra_manage_links`): `create-link`, `update-link`, `delete-link`, `create-preset`, `delete-preset`.
  - Settings (`utmatra_manage_settings` plus opt-in): `update-settings`, `send-test-email`, `send-test-webhook`.
- Three capabilities, `utmatra_view_reports`, `utmatra_manage_links` and `utmatra_manage_settings` (each manage capability implies view), granted to administrators on activation and upgrade and removed from every role on uninstall.
- `utmatra_capability_map` filter to remap a Utmatra capability to another capability.
- "Allow AI agents to change settings" switch on the dashboard settings card (option `utmatra_allow_agent_settings`, off by default), required for the settings, test email and test webhook abilities. The `utmatra_abilities_allow_settings_writes` filter overrides it.
- Guard rails for agents: the webhook URL cannot be changed through an ability; retention set through an ability is 0 or 30 to 3,650 days; `delete-link` and `delete-preset` require `confirm: true`; `export-events` returns at most 2,000 rows with `returned`, `total_rows` and `truncated`; the descriptions of read abilities that return visitor-recorded data mark it as untrusted.
- `utmatra_ability_executed` action, fired after a write ability runs with the ability id, user id and input keys (never the values): after a successful change for links, presets and settings, after every send attempt for the test email and test webhook.

### Changed

- The dashboard menu, the admin Dashboard widget and the `utmatra/v1` REST routes check the new capabilities instead of `manage_options`. Administrators keep full access.
- The REST handlers were refactored into a service layer shared with the abilities. REST routes, response bodies and status codes are unchanged apart from the order key removal listed under Security; `POST /settings` accepts one new optional argument, `allow_agent_settings`, saved only from the dashboard.
- The AI agent settings opt-in error and the settings, test email and test webhook ability descriptions now name a user who can manage Utmatra settings instead of an administrator. The untrusted-data note on read abilities also lists the click ids and the custom and meta values.
- The `list-events` ability accepts only the known channel slugs (the `label` values in `get-stats` `by_channel`) or an empty string for `channel`; an unknown value such as `Paid Search` is rejected as invalid input instead of returning no events. The REST route is unchanged.
- The `list-journeys` ability returns 10 journeys per page by default instead of 50, since each journey can carry up to 100 touches; `per_page` still accepts up to 100. The REST route default stays 50.

### Fixed

- UTM presets with non-ASCII names (for example Chinese, Japanese or emoji) can now be deleted. Their id is limited to lowercase letters, digits, dashes and underscores. A preset saved before 1.1.0 with such a name cannot be deleted from the dashboard; save it again under the same name and the new entry replaces the old one.
- Ability input is coerced to its schema types before use, so `false` sent as a query string or a string (WordPress 6.9 and 7.0 run endpoints, direct `execute()` callers) stays false: `export-events` honours `include_visits: false`, `update-settings` no longer switches booleans on, and `send-test-webhook` honours `leads: false`. The input schema of `get-settings`, `send-test-email` and `get-diagnostics` now serialises as a JSON object.
- An administrator who loads a page on the request that grants the new capabilities (the first request after upgrading, or the first load of a new multisite subsite) now has them on that request, instead of seeing no Utmatra menu or a 403 from the dashboard REST routes and abilities until the next page load.
- Parallel link and preset changes (for example several AI agent calls at once) no longer lose updates: creating, updating and deleting links and presets now runs one at a time under a database lock, and two creates of the same slug return one 201 and one 409. A change that cannot get the lock within 3 seconds returns a 503 `utmatra_busy` error.

### Security

- The webhook URL, signing secret and last webhook delivery detail are no longer sent to the dashboard for users who cannot manage settings.
- `allow_agent_settings` can only be saved from the dashboard (a request with a valid `wp_rest` nonce), so an ability or an Application Password request cannot switch on agent settings access.
- Events no longer return the WooCommerce order key: the `key` argument is removed from order destinations, and any query argument whose value holds a WooCommerce order key (`wc_order_...`) is removed from every destination and referrer (for example after a landing on, or a form or link on, an order-received, order-pay or download page), in `GET /events`, `/journeys`, `/journeys/<visitor>` and `/export` and in the `list-events`, `list-journeys`, `get-journey` and `export-events` abilities. Stored rows are unchanged.

### Upgrade notes

- Custom roles that are not administrator but hold `manage_options` lose access to the Utmatra screens and REST routes unless they are granted one of the new capabilities (or the capability is remapped with `utmatra_capability_map`).

## [1.0.0] - 2026-10-23

### Added

- Initial release.
- Cache-proof tracking beacon with a no-JS PHP fallback, bot filtering and click deduplication.
- Ad click-ID capture (`gclid`, `fbclid`, `msclkid`, `ttclid`, `li_fat_id`, `twclid`) alongside UTM tags.
- First/last-touch attribution cookie, gated on the WP Consent API.
- Channel classification, including AI assistants, and untagged landing-visit recording.
- Pretty short links with QR codes, the `[utmatra_link]` shortcode, UTM naming presets and a build-a-URL mode.
- Custom URL parameters captured alongside UTM tags.
- WooCommerce revenue attribution with an attribution metabox on the order screen, and form lead capture for Contact Form 7, Gravity Forms, Ninja Forms, Elementor Pro, WPForms, Fluent Forms, Formidable, Forminator and Bricks.
- Lead and order webhooks for Zapier, Make, HubSpot and GoHighLevel.
- Customer journeys built from a consent-gated visitor id, and a read-only REST API.
- Tabbed Chart.js dashboard with breakdowns by source, campaign, medium, channel, device, landing page, referrer and ad platform, plus a revenue-by-campaign chart.
- Recent leads widget on the WordPress admin Dashboard.
- CSV export, weekly email summary, Tracking Doctor diagnostics and data retention.
- IP addresses stored only as salted SHA-256 hashes, and suggested privacy policy text.
- Background jobs on bundled Action Scheduler, and no external services or CDN calls by default.

[1.1.0]: https://github.com/mahlamusa/utmatra-utm-attribution-and-tracking/releases/tag/v1.1.0
[1.0.0]: https://github.com/mahlamusa/utmatra-utm-attribution-and-tracking/releases/tag/v1.0.0
