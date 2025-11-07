#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT_DIR/inovapin-woo-sync"
OUTPUT="$ROOT_DIR/inovapin-woo-sync.zip"

if [[ ! -d "$PLUGIN_DIR" ]]; then
  echo "Plugin directory not found: $PLUGIN_DIR" >&2
  exit 1
fi

rm -f "$OUTPUT"
(cd "$ROOT_DIR" && zip -r "${OUTPUT##$ROOT_DIR/}" "$(basename "$PLUGIN_DIR")" -x "*.DS_Store" "__MACOSX/*")

echo "Created package: $OUTPUT"
