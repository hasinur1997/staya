#!/usr/bin/env bash
#
# Uploads the built application to the cPanel server using only ssh, tar and
# gzip on the server (cPanel hosts often have no rsync).
#
# Only changed files are sent. A checksum manifest of the previous deploy is
# kept on the server in .deploy-manifest; files that are new or whose content
# changed are sent in one tar stream, and files that disappeared from the
# build are deleted. Anything that was never in a manifest (.env, storage/,
# uploads, logs) is never touched.
#
# Required environment: SSH_HOST, SSH_USER, DEPLOY_PATH.
# Optional: SSH_PORT (22), SSH_KEY (~/.ssh/deploy_key).
# DEPLOY_SSH replaces the ssh command; it exists so the script can be tested
# without a server.

set -euo pipefail
export LC_ALL=C

: "${SSH_HOST:?}" "${SSH_USER:?}" "${DEPLOY_PATH:?}"
SSH_PORT="${SSH_PORT:-22}"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/deploy_key}"
MANIFEST=".deploy-manifest"

remote() {
    if [ -n "${DEPLOY_SSH:-}" ]; then
        "$DEPLOY_SSH" "$@"
    else
        ssh -i "$SSH_KEY" -p "$SSH_PORT" -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes \
            "${SSH_USER}@${SSH_HOST}" "$@"
    fi
}

checksum() {
    if command -v sha256sum >/dev/null 2>&1; then
        xargs -0 sha256sum
    else
        xargs -0 shasum -a 256
    fi
}

path_q="$(printf '%q' "$DEPLOY_PATH")"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

echo "Hashing the build..."
find . \
    \( -path ./.git -o -path ./.github -o -path ./node_modules -o -path ./tests -o -path ./docs \
    -o -path ./storage -o -path ./public/storage -o -path ./public/hot \) -prune -o \
    -type f ! -name '.env' ! -name '.env.*' ! -name "$MANIFEST" ! -path './database/*.sqlite' \
    -print0 | sort -z | checksum > "$work/new.manifest"

remote "cat ${path_q}/${MANIFEST} 2>/dev/null || true" > "$work/old.manifest"

sort -o "$work/new.sorted" "$work/new.manifest"
sort -o "$work/old.sorted" "$work/old.manifest"
strip_hash() { sed 's/^[0-9a-f]\{64\}  //'; }

# New or changed: lines only in the new manifest.
comm -13 "$work/old.sorted" "$work/new.sorted" | strip_hash > "$work/changed.txt"
# Gone: paths only in the old manifest.
strip_hash < "$work/new.sorted" | sort > "$work/new.paths"
strip_hash < "$work/old.sorted" | sort > "$work/old.paths"
comm -23 "$work/old.paths" "$work/new.paths" > "$work/deleted.txt"

changed="$(wc -l < "$work/changed.txt" | tr -d ' ')"
deleted="$(wc -l < "$work/deleted.txt" | tr -d ' ')"
total="$(wc -l < "$work/new.manifest" | tr -d ' ')"
echo "${total} files in the build: ${changed} to upload, ${deleted} to delete."

remote "mkdir -p ${path_q}"

if [ "$changed" -gt 0 ]; then
    tr '\n' '\0' < "$work/changed.txt" \
        | tar -czf - --null -T - \
        | remote "tar -xzf - -C ${path_q}"
fi

if [ "$deleted" -gt 0 ]; then
    remote "cd ${path_q} && while IFS= read -r f; do rm -f -- \"\$f\"; done" < "$work/deleted.txt"
fi

# Written last, so a failed upload is retried in full on the next deploy.
remote "cat > ${path_q}/${MANIFEST}" < "$work/new.manifest"
echo "Upload finished."
