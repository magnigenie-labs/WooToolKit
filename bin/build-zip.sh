#!/usr/bin/env bash
# Builds dist/wootoolkit-<version>.zip from the working tree, excluding everything in .distignore.
set -euo pipefail
cd "$(dirname "$0")/.."

version=$(sed -n 's/^[ *]*Version:[[:space:]]*//p' wootoolkit.php | head -1 | tr -d '\r')
rm -rf build && mkdir -p build/wootoolkit dist
rsync -a --exclude-from=.distignore ./ build/wootoolkit/
( cd build && rm -f "../dist/wootoolkit-${version}.zip" && zip -qrX "../dist/wootoolkit-${version}.zip" wootoolkit -x '*.DS_Store' )
rm -rf build
echo "dist/wootoolkit-${version}.zip"
