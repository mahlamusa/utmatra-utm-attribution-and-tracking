#!/usr/bin/env bash
# Utmatra REST snapshot harness. See tests/snapshot/README.md.
#
#   snapshot.sh run     <sandbox> <plugin-path> <out-dir>
#   snapshot.sh compare <dir-a> <dir-b>
#   snapshot.sh down    <sandbox>
#
# Environment:
#   ECHOWP                 echowp binary (default: echowp on PATH)
#   UTMATRA_SNAPSHOT_HOME  where sandbox projects live (default: ~/.cache/utmatra-snapshot)

set -euo pipefail

HARNESS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
ECHOWP="${ECHOWP:-echowp}"
SNAPSHOT_HOME="${UTMATRA_SNAPSHOT_HOME:-$HOME/.cache/utmatra-snapshot}"
PLUGIN_SLUG="utmatra-utm-attribution-and-tracking"

usage() {
    sed -n '2,10p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
    exit 2
}

die() {
    echo "snapshot: $*" >&2
    exit 1
}

check_echowp() {
    "$ECHOWP" ls > /dev/null 2>&1 || die "'$ECHOWP ls' failed; set ECHOWP to a working echowp binary."
}

check_sandbox_name() {
    [[ "$1" =~ ^[a-z][a-z0-9-]{1,30}$ ]] || die "sandbox name must be lowercase letters, digits and dashes (got '$1')."
}

# wp-cli against the sandbox.
ewp() {
    "$ECHOWP" --dir "$PROJECT" wp "$SANDBOX" -- "$@"
}

cmd_run() {
    [[ $# -eq 3 ]] || usage
    SANDBOX="$1"
    check_sandbox_name "$SANDBOX"
    local plugin out
    plugin="$( cd "$2" &> /dev/null && pwd )" || die "plugin path '$2' does not exist."
    [[ -f "$plugin/$PLUGIN_SLUG.php" ]] || die "'$plugin' is not a Utmatra checkout."
    [[ -f "$plugin/vendor/woocommerce/action-scheduler/action-scheduler.php" ]] || die "'$plugin/vendor' is missing (git-ignored, so absent in fresh worktrees). Run 'composer install' there or symlink another checkout's vendor/ into it, so every run loads Action Scheduler the same way."
    mkdir -p "$3"
    out="$( cd "$3" && pwd )"
    find "$out" -maxdepth 1 -name '*.json' -delete
    check_echowp

    PROJECT="$SNAPSHOT_HOME/$SANDBOX"
    mkdir -p "$PROJECT"
    printf 'version: 1\nhostnames: false\nhttps: false\nsites:\n  - name: %s\n' "$SANDBOX" > "$PROJECT/echowp.yml"
    "$ECHOWP" --dir "$PROJECT" up --silent > /dev/null

    local content
    # Last line only: a sandbox with WP_DEBUG on prints notices first.
    content="$( ewp eval 'echo WP_CONTENT_DIR;' | tail -n 1 )"
    [[ -d "$content/plugins" ]] || die "could not resolve wp-content for sandbox '$SANDBOX'."
    if [[ -e "$content/plugins/$PLUGIN_SLUG" && ! -L "$content/plugins/$PLUGIN_SLUG" ]]; then
        die "$content/plugins/$PLUGIN_SLUG is a real directory, not the harness symlink."
    fi
    ln -sfn "$plugin" "$content/plugins/$PLUGIN_SLUG"
    mkdir -p "$content/mu-plugins"
    cp "$HARNESS_DIR/mu-plugin.php" "$content/mu-plugins/utmatra-snapshot-stubs.php"

    ewp plugin activate "$PLUGIN_SLUG" > /dev/null
    ewp eval-file "$HARNESS_DIR/seed.php"
    ewp eval-file "$HARNESS_DIR/calls.php" "$out"
}

cmd_compare() {
    [[ $# -eq 2 ]] || usage
    [[ -d "$1" && -d "$2" ]] || die "both snapshot directories must exist."
    if diff -ru "$1" "$2"; then
        echo "snapshot: identical ($( find "$1" -maxdepth 1 -name '*.json' | wc -l ) responses)."
    else
        echo "snapshot: DIFFERENT (see diff above)." >&2
        exit 1
    fi
}

cmd_down() {
    [[ $# -eq 1 ]] || usage
    SANDBOX="$1"
    check_sandbox_name "$SANDBOX"
    check_echowp
    PROJECT="$SNAPSHOT_HOME/$SANDBOX"
    [[ -f "$PROJECT/echowp.yml" ]] || die "no sandbox '$SANDBOX' under $SNAPSHOT_HOME."
    "$ECHOWP" --dir "$PROJECT" rm "$SANDBOX"
    rm -rf "$PROJECT"
}

[[ $# -ge 1 ]] || usage
command="$1"
shift
case "$command" in
    run) cmd_run "$@" ;;
    compare) cmd_compare "$@" ;;
    down) cmd_down "$@" ;;
    *) usage ;;
esac
