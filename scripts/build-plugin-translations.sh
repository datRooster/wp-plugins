#!/usr/bin/env bash

set -euo pipefail

slug="${1:-}"

if [[ -z "$slug" ]]; then
	echo "Usage: bash scripts/build-plugin-translations.sh <plugin-slug>" >&2
	exit 1
fi

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_dir="${repo_root}/plugins/${slug}"
plugin_main_file="${plugin_dir}/${slug}.php"
languages_dir="${plugin_dir}/languages"
pot_file="${languages_dir}/${slug}.pot"

if [[ ! -d "$plugin_dir" ]]; then
	echo "Plugin directory not found: ${plugin_dir}" >&2
	exit 1
fi

if [[ ! -f "$plugin_main_file" ]]; then
	echo "Plugin main file not found: ${plugin_main_file}" >&2
	exit 1
fi

mkdir -p "$languages_dir"

plugin_version="$(sed -nE 's/^ \* Version:[[:space:]]*([0-9]+(\.[0-9]+)*)/\1/p' "$plugin_main_file" | head -n 1)"

if [[ -z "$plugin_version" ]]; then
	echo "Unable to detect plugin version in ${plugin_main_file}" >&2
	exit 1
fi

php_files=()

while IFS= read -r file; do
	php_files+=("$file")
done < <(find "$plugin_dir" -name '*.php' -not -path '*/vendor/*' | sort)

if [[ "${#php_files[@]}" -eq 0 ]]; then
	echo "No PHP files found under ${plugin_dir}" >&2
	exit 1
fi

xgettext \
	--language=PHP \
	--from-code=UTF-8 \
	--package-name="DatRooster Partial Payments" \
	--package-version="${plugin_version}" \
	--msgid-bugs-address="https://github.com/datRooster/wp-plugins/issues" \
	--add-comments=translators \
	--keyword=__ \
	--keyword=_e \
	--keyword=_x:1,2c \
	--keyword=_ex:1,2c \
	--keyword=_n:1,2 \
	--keyword=_n_noop:1,2 \
	--keyword=_nx:1,2,4c \
	--keyword=esc_html__ \
	--keyword=esc_html_e \
	--keyword=esc_html_x:1,2c \
	--keyword=esc_attr__ \
	--keyword=esc_attr_e \
	--keyword=esc_attr_x:1,2c \
	-o "$pot_file" \
	"${php_files[@]}"

for po_file in "$languages_dir"/"$slug"-*.po; do
	if [[ ! -f "$po_file" ]]; then
		continue
	fi

	msgmerge --backup=none --update --quiet "$po_file" "$pot_file"
	msgfmt -o "${po_file%.po}.mo" "$po_file"
done

printf '%s\n' "$pot_file"
