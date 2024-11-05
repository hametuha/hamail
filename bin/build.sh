#!/usr/bin/env bash

set -e

if [ $# -lt 1 ]; then
  echo "usage: $0 <version>"
  exit 1
fi

# Set variables.
PREFIX="refs/tags/"
VERSION=${1#"$PREFIX"}

echo "Building Hamail v${VERSION}..."


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
