# Send Email verification links on Wordpress
This plugin sends verification emails to newly registered wordpress users and asks them to verify their email address.

***
## Adding your own localized email templates
The email templates are located under `tpl/emails` directory, and the file for verification email is called `verify.php` which is the default template for `english` language
however you can add your own language-specific template file by duplicating `verify.php` and renaming it to `verify-LOCALE_CODE.php`

for example for Portuguese ( Brazil ) you can add a file named `verify-pt_BR.php` ( you can get your wordpress installation locale code with [get_locale()](https://developer.wordpress.org/reference/functions/get_locale/) )

There are also `dw_verify_email_template_path` and `dw_verify_email_template_args` filters available if you want to make modifications from your theme's `functions.php`
***
## 3rd-party scripts included
[WP_MAIL](https://github.com/anthonybudd/WP_Mail) ( for sending templated emails )

## Changelog

[See changelog for all versions](https://raw.githubusercontent.com/amiut/wp-email-verification/main/changelog.txt).
