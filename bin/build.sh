#!/usr/bin/env bash

set -e

if [ $# -lt 2 ]; then
  echo "usage: $0 <version> <light or pro>"
  exit 1
fi

# Set variables.
PREFIX="refs/tags/"
VERSION=${1#"$PREFIX"}

WP_README_ENV=$2

echo "Building Hamail ${WP_README_ENV} v${VERSION}..."


# Install packages.
composer install --no-dev --prefer-dist --no-suggest

# Install NPM.
npm install
npm run package

# Create README.txt
export WP_README_ENV
curl -L https://raw.githubusercontent.com/fumikito/wp-readme/master/wp-readme.php | php

# Change version string.
sed -i.bak "s/^Version: .*/Version: ${VERSION}/g" ./hamail.php
sed -i.bak "s/^Stable Tag: .*/Stable Tag: ${VERSION}/g" ./readme.txt

# Tweaks for pro/light
if [ "pro" = $2 ]; then
  # If pro, change name.
  sed -i.bak "s/^Plugin Name: .*/Plugin Name: Hamail PRO/g" ./hamail.php
  sed -i.bak "s/^Plugin URI: .*/Plugin URI: https:\/\/kunoichiwp.com\/product\/plugin\/hamail-pro/g" ./hamail.php
else
  # IF light version, remove pro dir.
  rm -rf ./app/Hametuha/Hamail/Pro
fi
