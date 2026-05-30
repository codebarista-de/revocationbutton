#!/bin/sh

set -e
set -v

version=$(jq -r ".version" < composer.json)
root_folder="RevocationButton"
filename="codebarista_revocationbutton_$version.zip"
out_file="$PWD/$filename"

rm -rf "/tmp/$root_folder"
mkdir -p "/tmp/$root_folder"
cp -r . "/tmp/$root_folder/"

rm -f "$out_file"

( cd /tmp && zip -r "$out_file" "$root_folder" \
 -x "$root_folder/.*" \
 -x "$root_folder/*.xcf" \
 -x "$root_folder/bin*" \
 -x "$root_folder/*.sh" \
 -x "$root_folder/*.zip" \
 -x "$root_folder/*.md" \
 -x "$root_folder/*.png")
