#!/usr/bin/env bash
# Runs a command; if it fails, posts the last lines of its output as GitHub Actions
# error annotations. Annotations are readable through the public API without signing
# in, unlike job logs.
# Usage: bin/ci-run.sh <label> <command...>
label=$1; shift
log=$(mktemp)
"$@" 2>&1 | tee "$log"
status=${PIPESTATUS[0]}
if [ "$status" -ne 0 ] && [ -n "${GITHUB_ACTIONS:-}" ]; then
	tail -n 60 "$log" | sed -e 's/\x1b\[[0-9;]*m//g' | awk 'NF' | split -l 12 - "$log.part."
	for part in "$log".part.*; do
		msg=$(sed -e 's/%/%25/g' "$part" | sed -e ':a;N;$!ba;s/\n/%0A/g')
		echo "::error title=${label} failed (exit ${status})::${msg}"
	done
fi
exit "$status"
