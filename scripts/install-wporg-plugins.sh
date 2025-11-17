#!/usr/bin/env bash
set -euo pipefail

PLUGINS_FILE=${PLUGINS_FILE:-config/wp-plugins-wporg.txt}

if [ ! -f "$PLUGINS_FILE" ]; then
  echo "Plugins file not found: $PLUGINS_FILE" >&2
  exit 1
fi

while IFS= read -r slug; do
  if [[ -z "$slug" ]] || [[ "$slug" =~ ^# ]]; then
    continue
  fi
  echo "Installing plugin: $slug"
  wp plugin install "$slug" --activate
done < "$PLUGINS_FILE"
