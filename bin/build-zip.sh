#!/bin/sh
# Build the release zip from the committed tree: top folder chattermate-chat/,
# without .git, the WordPress.org listing art or repo tooling (.gitattributes).
# Usage: bin/build-zip.sh [output.zip]   (default: ../chattermate-chat.zip)
set -e
cd "$(dirname "$0")/.."
out="${1:-../chattermate-chat.zip}"
git archive --format=zip --prefix=chattermate-chat/ -o "$out" HEAD
echo "Built $out from $(git rev-parse --short HEAD)"
