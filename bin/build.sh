#!/usr/bin/env bash

set -e

# Install packages.
composer install --no-dev --prefer-dist --no-suggest

# Install NPM.
npm install
npm run package

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

curl -L https://raw.githubusercontent.com/fumikito/wp-readme/master/wp-readme.php | php
