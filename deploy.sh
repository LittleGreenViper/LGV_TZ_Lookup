#!/bin/sh
############################################################################################################################
#
#   One-command server installation. Run ./deploy.sh, optionally with --no-secret. Settings are requested through
#   the terminal; credentials are never shell arguments. A downloaded standalone script bootstraps the same project
#   from GitHub. The worker installs dependencies, loads boundaries, tests lookups, then publishes the endpoint.
#
############################################################################################################################
set -eu
umask 077
php_command=${LGV_TZ_DEPLOY_PHP:-php}
command -v "$php_command" >/dev/null 2>&1 || { printf 'PHP 8.0 or later is required.\n' >&2; exit 2; }
case "${1:-}" in
    '') test "$#" -eq 0 ;;
    --no-secret) test "$#" -eq 1 ;;
    *) printf 'Usage: %s [--no-secret]\n' "$0" >&2; exit 2 ;;
esac
work_directory=$(mktemp -d "${TMPDIR:-/tmp}/lgv-tz-deploy.XXXXXXXX")
project_directory=$(CDPATH= cd -P "$(dirname "$0")" && pwd)
worker="$project_directory/tools/deploy.php"
cleanup() {
    result=$?
    trap - 0 1 2 15
    if [ ! -f "$worker" ] || "$php_command" "$worker" cleanup "$work_directory"; then
        rm -rf "$work_directory" || result=1
    else
        printf 'Installation recovery information remains in %s\n' "$work_directory" >&2
        result=1
    fi
    exit "$result"
}
trap cleanup 0
trap 'exit 129' 1
trap 'exit 130' 2
trap 'exit 143' 15
if [ ! -f "$worker" ]; then
    command -v curl >/dev/null 2>&1 || { printf 'curl is required to download the installer.\n' >&2; exit 2; }
    printf 'Downloading the LGV_TZ_Lookup installer...\n'
    curl -fsSL --proto '=https' --proto-redir '=https' https://codeload.github.com/LittleGreenViper/LGV_TZ_Lookup/tar.gz/refs/heads/main -o "$work_directory/source.tar.gz"
    tar -xzf "$work_directory/source.tar.gz" -C "$work_directory"
    project_directory="$work_directory/LGV_TZ_Lookup-main"
    worker="$project_directory/tools/deploy.php"
    test -f "$worker" || { printf 'This installer has not been published on main yet.\n' >&2; exit 1; }
fi
"$php_command" -d "memory_limit=${LGV_TZ_DEPLOY_MEMORY_LIMIT:-1G}" "$worker" run "$work_directory" "${1:-}"
