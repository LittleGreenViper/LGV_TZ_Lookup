#!/bin/sh
############################################################################################################################
#
#   © Copyright 2023-2026, [Little Green Viper Software Development LLC](https://littlegreenviper.com)
#
#   LICENSE:
#
#   MIT License
#
#   Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
#   files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
#   modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
#   Software is furnished to do so, subject to the following conditions:
#
#   The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
#
#   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
#   OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
#   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
#   CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
#
#   [Little Green Viper Software Development LLC](https://littlegreenviper.com)
#
############################################################################################################################
#
#   This is the public entrypoint for the disposable Composer demo.
#
#   Usage: ./demo/run.sh
#
#   The script uses POSIX sh, and takes no command-line arguments. Connection settings and test options come from
#   the LGV_TZ_DEMO_* environment variables documented in README.md. PHP and a running MySQL or PostgreSQL server are required.
#   The PHP worker obtains its own Composer executable and installs the package into a private temporary directory.
#
#   The exit trap runs cleanup in a separate PHP process, so it also works after a loader memory-limit failure.
#   A successful cleanup drops this run's database and removes the directory. If database cleanup fails, the record
#   is retained for recovery. Large boundary files are removed by the cleanup worker before it contacts the database server.
#
#   Exit status: 0 means all tests and cleanup succeeded. Nonzero means a test, setup, interruption, or cleanup failed.
#
############################################################################################################################
set -eu
umask 077

if [ "$#" -ne 0 ]; then
    printf 'Usage: %s\nConfigure LGV_TZ_DEMO_DRIVER (mysql or pgsql), HOST, PORT, USER, and PASSWORD environment variables.\n' "$0" >&2
    exit 2
fi

php_command=${LGV_TZ_DEMO_PHP:-php}
if ! command -v "$php_command" >/dev/null 2>&1; then
    printf 'PHP is required. Install PHP 8.0 or later first.\n' >&2
    exit 2
fi
demo_directory=$(CDPATH= cd -P "$(dirname "$0")" && pwd)
work_directory=$(mktemp -d "${TMPDIR:-/tmp}/lgv-tz-demo.XXXXXXXX")

############################################################################################################################
#
#   This runs when the shell exits. We preserve the worker's status, disable traps to avoid cleanup recursion, and
#   remove the private working directory only after the database/download cleanup succeeds. Cleanup failure changes
#   the status to 1, and prints the location of the retained database.json record.
#
############################################################################################################################
cleanup() {
    result=$?
    trap - 0 1 2 15
    if "$php_command" "$demo_directory/demo.php" cleanup "$work_directory"; then
        if ! rm -rf "$work_directory"; then
            result=1
        fi
    else
        printf 'Cleanup needs attention. Database information is retained in %s/database.json\n' "$work_directory" >&2
        result=1
    fi
    exit "$result"
}
trap cleanup 0
trap 'exit 129' 1
trap 'exit 130' 2
trap 'exit 143' 15

"$php_command" -d "memory_limit=${LGV_TZ_DEMO_MEMORY_LIMIT:-1G}" "$demo_directory/demo.php" run "$work_directory"
