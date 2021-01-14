# Hamail - Send Email via SendGrid

Tags: email, sendgrid, marketing  
Contributors: Takahashi_Fumiki, hametuha  
Tested up to: 5.6  
Requires at least: 5.0  
Requires PHP: 5.6  
Stable Tag: nightly  
License: GPLv3 or later  
License URI: http://www.gnu.org/licenses/gpl-3.0.txt

A WordPress plugin to send contact mail to your users via Sendgrid.

## Description

This plugin enables you to send emails to each of your users.
No more long **to** or BCCs.

### Features

- User contact email.
- HTML SendGrid email Template.
- Override default email functions.
- Marketing Email creator **[PRO]**
- Periodical Email **[PRO]**

Pro version is available at [Kunoichi Market](https://kunoichiwp.com/product/plugin/hamail-pro).

## Installation

### From Plugin Repository

Click install and activate it.

### From Github

Composer and NPM are required.

```
 # Go to your wp-content/plugins and run git
cd wp-content/plugins
git clone https://github.com/hametuha/hamail.git hamail
 # Then move into
cd hamail
 # Install dependencies
composer install
npm install && npm start
```

### Enter API Key

You need [SendGrid API Key](https://sendgrid.com/docs/Classroom/Send/How_Emails_Are_Sent/api_keys.html).

For more details, go to hamail setting screen.

## FAQ

### Where can I get supported?

<!-- only:pro>
Please Go to [Kunoichi Market](https://kunoichiwp.com/product/plugin/hamail-pro) to get supported.
</only:pro -->

<!-- only:light>
To get supported, please go to [Kunoichi Market](https://kunoichiwp.com/product/plugin/hamail-pro).
</only:light -->

## Changelog

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
