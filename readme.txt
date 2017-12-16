 === Email verification on signups ===
Contributors: dornaweb
Tags: email verification, confirm email address, verify email address
Requires at least: 4
Tested up to: 4.7
Requires PHP: 5.6
License: MIT

Send verification links to newly registered users and ask them to confirm their email address to activate their account.

== Description ==
# Send Email verification links on Wordpress
This plugin sends verification emails to newly registered wordpress users and asks them to verify their email address.

***
## Adding your own localized email templates
The email templates are located under `tpl/emails` directory, and the file for verification email is called `verify.php` which is the default template for `english` language
however you can add your own language-specific template file by duplicating `verify.php` and renaming it to `verify-LOCALE_CODE.php`

for example for Portuguese ( Brazil ) you can add a file named `verify-pt_BR.php` ( you can get your wordpress installation locale code with [get_locale()](https://developer.wordpress.org/reference/functions/get_locale/) )

There are also `dw_verify_email_template_path` and `dw_verify_email_template_args` filters available if you want to make modifications from your theme\'s `functions.php`
***
## 3rd-party scripts included
[WP_MAIL](https://github.com/anthonybudd/WP_Mail) ( for sending templated emails )

== Installation ==
Seach the plugin's name in the install plugin section in your dashboard
Or upload the plugin and extract it to wp-content/plugins/ and then activate it in your dashboard, under the plugins page

== Frequently Asked Questions ==

= How does this plugin work? =

Once you activate it, new users that register on your site must verify their email address by a confirmation link which is sent to their email address, otherwise they won't be able to log-in.

== Screenshots ==
1. Settings pages
2. Add user
3. Wp-Login

== Changelog ==

= 1.1 =
* Added ability to ignore verification for admins in dashboard.
* Added ability to request for re-sending the verification link.
* Added Max re-send verification link requests
* Fixed some issues
* Persian language updated
* Multisite integration

= 1.1.1 =
* Some tiny bugs fixed
* Translations updated

= 1.1.2 =
* Translations updated
* Bug fixed
