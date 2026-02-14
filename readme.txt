=== WP-PDF-Guard ===
Contributors: michaelryanwood
Tags: pdf, protection, download, guard, ads
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect PDF downloads by requiring users to visit a product page before accessing the file.

== Description ==

WP-PDF-Guard prevents direct linking to your PDF files. When someone tries to access a PDF directly, they are redirected to the designated product page first. This ensures visitors see your ads and content before downloading.

**How it works:**

1. Map each PDF to a product page in the admin UI
2. Direct PDF links are intercepted and redirected to the product page
3. Visiting the product page grants a short-lived cryptographic token (cookie)
4. The token allows PDF access for a configurable duration (default: 10 minutes)
5. After the token expires, users must visit the product page again

**Features:**

* Stateless HMAC-SHA256 signed cookies — no database writes per visit
* Automatic cookie injection on product pages
* Shortcode for View/Download links: `[pdf_guard_download id="123"]`
* Configurable token duration
* Admin UI with AJAX-powered PDF and page search
* Path traversal protection
* Apache .htaccess rules auto-injected; Nginx config provided via admin notice

== Installation ==

1. Upload the `wp-pdf-guard` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **PDF Guard > Mappings** to map PDFs to product pages
4. Optionally adjust settings under **PDF Guard > Settings**

**Nginx users:** Add the rewrite rule shown in the admin notice to your Nginx configuration.

== Frequently Asked Questions ==

= Does this work with Nginx? =

Yes, but you need to manually add a rewrite rule to your Nginx configuration. An admin notice will show you the exact rule to add.

= How long does PDF access last? =

By default, 10 minutes. You can change this under PDF Guard > Settings.

= Does this require a database for every visit? =

No. Tokens are stateless HMAC-signed cookies. The only database table stores the PDF-to-page mappings.

= What happens to unmapped PDFs? =

PDFs without a mapping will return a 403 error when accessed directly. Map all PDFs you want to protect.

== Changelog ==

= 1.0.0 =
* Initial release
