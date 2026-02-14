# WP-PDF-Guard

Protect your PDF downloads by requiring visitors to view your product page (and ads) before accessing the file.

## The Problem

You publish free PDF content monetized through ads on product pages. But once someone shares a direct PDF link, every subsequent visitor bypasses your page entirely — and your ad revenue disappears.

## The Solution

WP-PDF-Guard intercepts direct PDF requests and redirects users to the product page first. After viewing the page, they receive a short-lived cryptographic token that grants temporary PDF access.

**No database writes per visit. No performance impact. Just a stateless HMAC-SHA256 signed cookie.**

## How It Works

1. A visitor clicks a shared direct PDF link
2. WP-PDF-Guard intercepts the request and redirects them to your product page
3. The visitor sees your content and ads
4. A secure access cookie is automatically set (default: 10 minutes)
5. View/Download links on the page now work
6. After the token expires, they must revisit the page to download again

## Features

- **Stateless tokens** — HMAC-SHA256 signed cookies, no database writes per visit
- **Per-PDF protection** — Map individual PDFs to specific product pages
- **Auto-inject cookies** — Cookies are set automatically when visiting a mapped page
- **Shortcode** — `[pdf_guard_download id="123"]` renders View and Download links
- **Configurable TTL** — Set token duration from 10 seconds to 24 hours
- **Block All mode** — Optionally block all PDFs, not just mapped ones
- **Admin UI** — Search-based mapping management with autocomplete dropdowns
- **Apache auto-config** — `.htaccess` rules injected automatically on activation
- **Nginx support** — Admin notice with copy-paste configuration
- **Secure by default** — Path traversal protection, CSRF nonces, XSS escaping, timing-safe HMAC validation

## Installation

### From ZIP (recommended)

1. Download [wp-pdf-guard.zip](https://github.com/MichaelRyanWood/WP-PDF-Guard/raw/main/wp-pdf-guard.zip)
2. In WordPress, go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP and click **Install Now**
4. Click **Activate**

### From Source

```bash
git clone https://github.com/MichaelRyanWood/WP-PDF-Guard.git
```
Copy the folder into `wp-content/plugins/` and activate in WordPress.

## Quick Start

### 1. Upload a PDF
Go to **Media > Add New** and upload your PDF file.

### 2. Create a Product Page
Create the page you want visitors to see before downloading. Add your ads, content, and calls to action. Publish it.

### 3. Map the PDF to the Page
Go to **PDF Guard > Mappings**. Click into the PDF field to see all unmapped PDFs, select one, then select the product page. Click **Add Mapping**.

### 4. Add Download Links
Edit your product page and add the shortcode:

```
[pdf_guard_download id="123"]
```

Replace `123` with your PDF's attachment ID (shown in the mappings table). This renders **View PDF** and **Download PDF** links.

### 5. Done
Direct PDF links now redirect to your product page. Visitors see your ads before they can download.

## Settings

Go to **PDF Guard > Settings**:

| Setting | Default | Description |
|---|---|---|
| Token Duration | 600 seconds (10 min) | How long PDF access lasts after visiting the product page |
| Auto-inject Cookies | On | Automatically grant access when visiting a mapped product page |
| Block All PDFs | Off | Block all PDFs (not just mapped ones). Unmapped PDFs return 403 |

## Requirements

- WordPress 5.6+
- PHP 7.4+
- Apache with `mod_rewrite` (auto-configured) or Nginx (manual config)

## Nginx Configuration

If you use Nginx, add this to your server block:

```nginx
location ~* /wp-content/uploads/.*\.pdf$ {
    rewrite ^/wp-content/uploads/(.+\.pdf)$ /index.php?wpdfg_resolve_path=/wp-content/uploads/$1 last;
}
```

## License

GPL v2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
