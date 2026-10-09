#!/bin/sh
# © Copyright 2023-2026, Little Green Viper Software Development LLC.
#
# MIT License
#
# Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
# files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
# modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
# Software is furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
# OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
# IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
# CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
#
# Report the latest boundary version, or reload an existing database using its private PHP configuration.
set -eu
umask 077
php_command=${LGV_TZ_UPDATE_PHP:-php}
command -v "$php_command" >/dev/null 2>&1 || { printf 'PHP 8.0 or later is required.\n' >&2; exit 2; }
project_directory=$(CDPATH= cd -P "$(dirname "$0")" && pwd)
check=0
if [ "${1:-}" = --check ]; then
    check=1
    shift
fi
if [ "$#" -gt 1 ]; then
    printf 'Usage: %s [--check] [CONFIG_FILE]\n' "$0" >&2
    exit 2
fi
config=${1:-$project_directory/config.php}
if [ "$check" -eq 0 ] && [ ! -f "$config" ]; then
    printf 'Supply the existing private configuration: %s /path/to/config.php\n' "$0" >&2
    exit 2
fi
work_directory=$(mktemp -d "${TMPDIR:-/tmp}/lgv-tz-update.XXXXXXXX")
cleanup() {
    result=$?
    trap - 0 1 2 15
    if "$php_command" "$project_directory/tools/update.php" cleanup "$work_directory"; then
        rm -rf "$work_directory" || result=1
    else
        printf 'Update recovery information remains in %s\n' "$work_directory" >&2
        result=1
    fi
    if [ "$result" -eq 0 ] && [ "$check" -eq 0 ]; then
        printf 'Boundary downloads deleted.\n'
    fi
    exit "$result"
}
trap cleanup 0
trap 'exit 129' 1
trap 'exit 130' 2
trap 'exit 143' 15
"$php_command" -d "memory_limit=${LGV_TZ_UPDATE_MEMORY_LIMIT:-1G}" "$project_directory/tools/update.php" "$work_directory" "$config" "$check"
