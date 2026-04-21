#!/usr/bin/env bash
# Build a production-ready PrestaShop module ZIP.
# Run from the module root: bash tools/build.sh
# Output: prestaform.zip in the current directory

set -euo pipefail

MODULE="prestaform"
OUT="${MODULE}.zip"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

echo "Building ${OUT} from ${ROOT}"

cd "$(dirname "${ROOT}")"

zip -r "${ROOT}/${OUT}" "${MODULE}" \
    --exclude "${MODULE}/.git/*" \
    --exclude "${MODULE}/.git" \
    --exclude "${MODULE}/.claude/*" \
    --exclude "${MODULE}/.claude" \
    --exclude "${MODULE}/.bg-shell/*" \
    --exclude "${MODULE}/.bg-shell" \
    --exclude "${MODULE}/.superpowers/*" \
    --exclude "${MODULE}/.superpowers" \
    --exclude "${MODULE}/docs/*" \
    --exclude "${MODULE}/docs" \
    --exclude "${MODULE}/tests/*" \
    --exclude "${MODULE}/tests" \
    --exclude "${MODULE}/tools/*" \
    --exclude "${MODULE}/tools" \
    --exclude "${MODULE}/vendor/*" \
    --exclude "${MODULE}/vendor" \
    --exclude "${MODULE}/phpunit.xml" \
    --exclude "${MODULE}/composer.lock" \
    --exclude "${MODULE}/*.zip" \
    --exclude "*.DS_Store"

echo "Done: ${ROOT}/${OUT} ($(du -sh "${ROOT}/${OUT}" | cut -f1))"
