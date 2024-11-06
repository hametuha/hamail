# Hamail - Send Email via SendGrid

Tags: email, sendgrid, marketing  
Contributors: Takahashi_Fumiki, hametuha  
Tested up to: 6.6  
Requires at least: 5.9  
Requires PHP: 7.2  
Stable Tag: nightly  
License: GPLv3 or later  
License URI: http://www.gnu.org/licenses/gpl-3.0.txt

A WordPress plugin to send contact mail to your users via Sendgrid.

## Description

This plugin enables you to send emails to each of your users.
No more long **to** or BCCs.

### Features

- Use SMTP API to send emails. This affects all PHP Mailer in <code>wp_mail()</code> functions, 
  so you can use this plugin with other mail-delivering plugins like WooCommerce and Form plugins.
- User contact email.
- HTML SendGrid email Template.
- Override default email functions.
- Marketing Email creator
- Periodical Email(experimental)

## Installation

### From Plugin Repository

Click install and activate it.

### From GitHub

Composer and NPM are required.

<pre>
# Go to your wp-content/plugins and run git
cd wp-content/plugins
git clone https://github.com/hametuha/hamail.git hamail
# Then move into
cd hamail
# Install dependencies
composer install
npm run package
</pre>

### Enter API Key

You need [SendGrid API Key](https://sendgrid.com/docs/Classroom/Send/How_Emails_Are_Sent/api_keys.html).

For more details, go to hamail setting screen.

## FAQ

### Where can I get supported?

To get supported, please go to [Support Forum](https://wordpress.org/support/plugin/hamail/).

## Changelog

### 2.5.0

* Add preview feature for transaction mail.

### 2.4.2

* Bugfix: Fix error on the meta box data saving.

### 2.4.0

* Support SMTP API.
* Add marketing email logs.
* Marketing emails use excerpt as pre-header text.
* Arrange admin screen menu.

### 2.3.0

* Marketing mail is supported.

### 2.2.0

* Refactor recipient selector.
* Update syncing features.
* Add WP-CLI command for export users.

### 2.1.0

* Add reply feature. Jetpack contact is now supported.

### 2.0.1

* Fix `wp_mail` skipped.

### 2.0.0

* Add transactional email feature.
* Requires WordPress 5.0 and over.

### 1.0.0

* First release.
