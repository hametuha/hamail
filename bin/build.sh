#!/usr/bin/env bash

set -e

if [ $# -lt 2 ]; then
  echo "usage: $0 <version> <light or pro>"
  exit 1
fi

# Set variables.
VERSION=$1

echo $VERSION

WP_README_ENV=$2
export WP_README_ENV


# Install packages.
composer install --no-dev --prefer-dist --no-suggest

# Install NPM.
npm install
npm run package

# Create README.txt
curl -L https://raw.githubusercontent.com/fumikito/wp-readme/master/wp-readme.php | php

# Change version string.
sed -i "s/^Version: .*/Version: ${VERSION}/g" ./hamail.php
sed -i "s/^Stable Tag: .*: .*/Stable Tag: ${VERSION}/g" ./readme.txt


if [ "pro" = $2 ]; then
  # If pro, change name.
  sed -i "s/^Plugin Name: .*/Plugin Name: Hamail PRO/g" ./hamail.php
  sed -i "s/^Plugin URI: .*/Plugin URI: https:\/\/kunoichiwp.com\/product\/plugin\/hamail-pro/g" ./hamail.php
else
  # IF light version, remove pro dir.
  rm -rf ./app/Hametuha/Hamail/Pro
fi


# Remove unwanted files.
rm -rf .git
rm -rf .github
rm -rf .gitignore
rm -rf .browserslistrc
rm -rf .eslintrc
rm -rf .phpcs.xml
rm -rf bin
rm -rf node_modules
rm -rf tests
rm -rf phpunit.xml.dist
rm -rf stylelint.config.js
rm -rf webpack.config.js
rm -rf .wp-env.json
