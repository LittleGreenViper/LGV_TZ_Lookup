#!/bin/sh
############################################################################################################################
#
#   © Copyright 2023-2026, [Little Green Viper Software Development LLC](https://littlegreenviper.com)
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
#   Usage: ./generate-docs.sh
#
#   Uses docs/Doxyfile and builds static HTML for GitHub Pages. DOXYGEN may select another executable. The build runs
#   in a temporary directory, and replaces docs/html only after Doxygen succeeds. Project source files are not changed.
#
############################################################################################################################
set -eu

if [ "$#" -ne 0 ]; then
    printf 'Usage: %s\n' "$0" >&2
    exit 2
fi
project_directory=$(CDPATH= cd -P "$(dirname "$0")" && pwd)
docs_directory=$project_directory/docs

if [ -n "${DOXYGEN:-}" ]; then
    doxygen_command=$DOXYGEN
elif command -v doxygen >/dev/null 2>&1; then
    doxygen_command=doxygen
elif [ -x /Applications/Doxygen.app/Contents/Resources/doxygen ]; then
    doxygen_command=/Applications/Doxygen.app/Contents/Resources/doxygen
else
    printf 'Doxygen is required. Install it or set DOXYGEN to its executable path.\n' >&2
    exit 2
fi
if ! command -v "$doxygen_command" >/dev/null 2>&1; then
    printf 'Cannot run Doxygen: %s\n' "$doxygen_command" >&2
    exit 2
fi
if ! command -v php >/dev/null 2>&1; then
    printf 'PHP is required for the documentation Markdown link filter.\n' >&2
    exit 2
fi

work_directory=$(mktemp -d "${TMPDIR:-/tmp}/lgv-tz-docs.XXXXXXXX")
cleanup() {
    result=$?
    trap - 0 1 2 15
    if [ -d "$work_directory/previous-html" ] && [ ! -d "$docs_directory/html" ]; then
        if ! mv "$work_directory/previous-html" "$docs_directory/html"; then
            printf 'Previous documentation retained for recovery: %s/previous-html\n' "$work_directory" >&2
            exit 1
        fi
    fi
    rm -rf "$work_directory"
    exit "$result"
}
trap cleanup 0
trap 'exit 129' 1
trap 'exit 130' 2
trap 'exit 143' 15

printf 'Generating LGV_TZ_Lookup documentation...\n'
(
    cd "$docs_directory"
    {
        cat Doxyfile
        printf '\nOUTPUT_DIRECTORY = "%s"\n' "$work_directory"
        if ! command -v dot >/dev/null 2>&1; then
            printf 'HAVE_DOT = NO\n'
        fi
    } | "$doxygen_command" -
)
test -f "$work_directory/html/index.html"
cp -R "$project_directory/img" "$work_directory/html/img"
php "$docs_directory/finalize-html.php" "$work_directory/html"

if [ -d "$docs_directory/html" ]; then
    mv "$docs_directory/html" "$work_directory/previous-html"
fi
if ! mv "$work_directory/html" "$docs_directory/html"; then
    if [ -d "$work_directory/previous-html" ]; then
        mv "$work_directory/previous-html" "$docs_directory/html"
    fi
    exit 1
fi
printf 'HTML documentation: %s/html/index.html\n' "$docs_directory"
