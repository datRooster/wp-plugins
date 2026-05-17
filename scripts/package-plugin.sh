#!/usr/bin/env bash

set -euo pipefail

slug="${1:-}"
version="${2:-}"

if [[ -z "$slug" || -z "$version" ]]; then
	echo "Usage: bash scripts/package-plugin.sh <plugin-slug> <version>" >&2
	exit 1
fi

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_dir="${repo_root}/plugins/${slug}"
plugin_main_file="${plugin_dir}/${slug}.php"
plugin_readme_file="${plugin_dir}/readme.txt"
normalized_version="${version#v}"

if [[ ! -d "$plugin_dir" ]]; then
	echo "Plugin directory not found: ${plugin_dir}" >&2
	exit 1
fi

if [[ ! -f "$plugin_main_file" ]]; then
	echo "Plugin main file not found: ${plugin_main_file}" >&2
	exit 1
fi

if [[ ! -f "$plugin_readme_file" ]]; then
	echo "Plugin readme not found: ${plugin_readme_file}" >&2
	exit 1
fi

plugin_version="$(sed -nE 's/^ \* Version:[[:space:]]*([0-9]+(\.[0-9]+)*)/\1/p' "$plugin_main_file" | head -n 1)"
stable_tag="$(sed -nE 's/^Stable tag:[[:space:]]*([0-9]+(\.[0-9]+)*)/\1/p' "$plugin_readme_file" | head -n 1)"

if [[ -z "$plugin_version" ]]; then
	echo "Unable to detect plugin version in ${plugin_main_file}" >&2
	exit 1
fi

if [[ -z "$stable_tag" ]]; then
	echo "Unable to detect stable tag in ${plugin_readme_file}" >&2
	exit 1
fi

if [[ "$plugin_version" != "$normalized_version" ]]; then
	echo "Plugin version ${plugin_version} does not match requested version ${normalized_version}" >&2
	exit 1
fi

if [[ "$stable_tag" != "$normalized_version" ]]; then
	echo "Stable tag ${stable_tag} does not match requested version ${normalized_version}" >&2
	exit 1
fi

build_dir="$(mktemp -d "${TMPDIR:-/tmp}/wp-plugin-release.XXXXXX")"
stage_dir="${build_dir}/${slug}"
archive_path="${build_dir}/${slug}-${normalized_version}.zip"

mkdir -p "$stage_dir"
cp -R "${plugin_dir}/." "$stage_dir/"

(
	cd "$build_dir"
	zip -qr "$archive_path" "$slug"
)

printf '%s\n' "$archive_path"
