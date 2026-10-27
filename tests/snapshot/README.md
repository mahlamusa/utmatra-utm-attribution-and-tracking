# REST snapshot harness

Records the response (status and body) of every `utmatra/v1` route, including the 400, 404 and 409 error cases, a 401/403 check of every route and method, and every route and method as a viewer holding only `utmatra_view_reports` (200 on reads, 403 on writes), so a refactor can prove the REST output did not change. It is developer tooling: `tests/` is export-ignored and never ships.

## Requirements

- [echowp](https://github.com/okky-rymera/echowp). Set `ECHOWP` when the `echowp` on your `PATH` is not the one you want (for example, an old build that cannot read the registry).
- A Utmatra checkout with `vendor/` present. `vendor/` is git-ignored, so a fresh worktree has none: run `composer install` in it, or symlink a checkout's `vendor/` into it. The harness refuses to run without it, because Action Scheduler loading or not would change the output.

## Usage

```sh
tests/snapshot/snapshot.sh run     <sandbox> <plugin-path> <out-dir>
tests/snapshot/snapshot.sh compare <dir-a> <dir-b>
tests/snapshot/snapshot.sh down    <sandbox>
```

- `run` creates the echowp sandbox `<sandbox>` on first use (project files in `~/.cache/utmatra-snapshot/<sandbox>`, override with `UTMATRA_SNAPSHOT_HOME`), symlinks `<plugin-path>` in as the plugin, installs `mu-plugin.php` (stubs outgoing HTTP and `wp_mail()`), activates the plugin, reseeds with `seed.php` and records every call from `calls.php` as `<out-dir>/NNN-<name>.json`. Each run reseeds from scratch, so runs can repeat in the same sandbox, against any working tree.
- `compare` runs `diff -ru` and exits non-zero on any difference.
- `down` removes the sandbox and its project directory.

The harness scripts are taken from the tree you run `snapshot.sh` from; only the plugin under test comes from `<plugin-path>`. Use one sandbox name per parallel job so jobs never share a database.

## Baseline and compare (typical refactor check)

```sh
export ECHOWP=/path/to/echowp
git worktree add --detach /tmp/utmatra-main origin/main
ln -s "$PWD/vendor" /tmp/utmatra-main/vendor   # or composer install there

tests/snapshot/snapshot.sh run refactor-check /tmp/utmatra-main /tmp/snap-main
tests/snapshot/snapshot.sh run refactor-check "$PWD"            /tmp/snap-branch
tests/snapshot/snapshot.sh compare /tmp/snap-main /tmp/snap-branch

tests/snapshot/snapshot.sh down refactor-check
git worktree remove /tmp/utmatra-main
```

## Determinism

- The seed is fixed: three links, two presets, the settings options, a subscriber user, a `snapshot_viewer` user whose role holds only `utmatra_view_reports`, and 20 events (click, visit, order and lead rows over six visitors plus visitor-less rows; the sixth visitor's four rows, 800 days back, carry WooCommerce order keys and are returned only by the `*-order-keys` calls, though they still count toward `all_time_total`) at fixed site-local times on days counted back from today. The site timezone is Africa/Johannesburg (no DST). The events table is truncated first, so event ids always start at 1.
- The site URL is masked as `{home}` and every `Y-m-d` date, including inside datetimes and keys, as `D-<n>` (days before today, site-local).
- Rows of the ranked stats breakdowns (`ORDER BY count DESC`, or revenue) that tie have no defined order in MySQL, so ties are ordered by label. Only tied rows move, so a change in the ranking still shows.
- The order-key rows are older than the seeded 180-day retention; the harness never runs the retention cleanup, so they stay for the run.
- No seeded event falls on the day the 7- or 30-day previous-period window ends, since that cut-off depends on the time of day.

## Changing the calls

The calls run in order and the write calls change state, so add new calls at the end of their group and re-record the baseline from `main` with the same harness version before comparing.
