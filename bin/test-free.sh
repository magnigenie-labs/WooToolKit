#!/usr/bin/env bash
# Tests the free plugin working tree on the test VPS in its own directory and table prefix, so several
# runs (e.g. one per git worktree) can go at once: bin/test-free.sh <run-name> [phpunit --filter]
# Runs php -l (7.4 + 8.4), PHPCS, PHPStan and PHPUnit on PHP 7.4 (posts orders) and 8.4 (HPOS).
set -euo pipefail
cd "$(dirname "$0")/.."
name=$(echo "${1:?run name}" | tr -cd 'a-z0-9-'); filter=${2:-}
KEY=/Users/nirmalram/Documents/Hostinger/live-work/magnikart-ssh/codex_magnikart_vps_ed25519
SSH=(-i "$KEY" -o IdentitiesOnly=yes -o IdentityAgent=none)
tmp=$(mktemp -d)
git ls-files -z --cached --others --exclude-standard | xargs -0 sh -c 'for f; do [ -e "$f" ] && printf "%s\0" "$f"; done' _ | COPYFILE_DISABLE=1 xargs -0 tar czf "$tmp/src.tgz"
scp -q "${SSH[@]}" "$tmp/src.tgz" "root@31.97.239.106:/root/wtk-freesrc-$name.tgz"
scp -q "${SSH[@]}" bin/vps-free-ci.sh "root@31.97.239.106:/root/wtk-free-ci-$name.sh"
rm -rf "$tmp"
ssh "${SSH[@]}" root@31.97.239.106 "bash /root/wtk-free-ci-$name.sh '$name' '$filter'"
