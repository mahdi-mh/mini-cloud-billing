#!/usr/bin/env sh
# Renders the Mermaid diagrams and builds presentation.pdf (needs node and Chrome).
set -e
cd "$(dirname "$0")"

CHROME="${CHROME_PATH:-/usr/bin/google-chrome}"
CONFIG="$(mktemp)"
printf '{"executablePath":"%s","args":["--no-sandbox"]}' "$CHROME" > "$CONFIG"

mkdir -p diagrams/png
for file in diagrams/*.mmd; do
    npx -y @mermaid-js/mermaid-cli -p "$CONFIG" -i "$file" -o "diagrams/png/$(basename "$file" .mmd).png" -b white -s 2 -q
done

npx -y @marp-team/marp-cli --no-stdin slides.md --pdf --allow-local-files --browser-path "$CHROME" -o presentation.pdf
