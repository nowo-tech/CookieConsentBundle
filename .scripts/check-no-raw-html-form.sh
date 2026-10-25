#!/usr/bin/env bash
# REQ-TWIG-005 — fail when bundle or demo Twig uses raw <form> / <input> / <textarea> / <select>.
# Dashboard dialogs and forms render through Symfony Form views (form_start / form_row).
# Allowlist (REQ-TWIG-005 "Form themes" exception): the two form-theme files below implement Symfony's
# checkbox_widget block, which emits <input> by design.
ALLOWLIST=(
  src/Resources/views/form/cookie_consent_theme.html.twig
  src/Resources/views/form/cookie_consent_theme.tailwind.html.twig
)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

paths=()
[[ -d src/Resources/views ]] && paths+=(src/Resources/views)
for dir in demo/*/templates; do
  [[ -d "$dir" ]] && paths+=("$dir")
done
if [[ ${#paths[@]} -eq 0 ]]; then
  echo "check-no-raw-html-form: no Twig directories — OK"
  exit 0
fi

matches="$(grep -RInE '<(form|input|textarea|select)[[:space:]>]' "${paths[@]}" --include='*.twig' || true)"
for allowed in "${ALLOWLIST[@]}"; do
  matches="$(printf '%s\n' "$matches" | grep -v "^${allowed}:" || true)"
done
matches="$(printf '%s' "$matches" | sed '/^$/d')"
if [[ -n "$matches" ]]; then
  echo "ERROR: raw <form> / <input> / <textarea> / <select> in Twig (REQ-TWIG-005):" >&2
  echo "$matches" >&2
  exit 1
fi

echo "check-no-raw-html-form: OK"
