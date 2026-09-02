# SVGFlow – Safe SVG Upload & Smart Inliner

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Plugin Check](https://img.shields.io/badge/Plugin%20Check-100%25%20Clean-brightgreen.svg)](https://wordpress.org/plugins/plugin-check/)

**SVGFlow** is an ultra-lightweight, high-performance, and secure WordPress plugin for uploading SVG/SVGZ vector graphics and converting `<img>` tags into live, styleable `<svg>` elements on the frontend.

---

## 🚀 Key Features

* **🛡️ Enterprise DOM Sanitization:** Powered by `enshrined/svg-sanitize` to strip malicious `<script>` tags, inline event listeners (`onload`, `onclick`), and XML entity attacks (XXE/SSRF) before files reach your disk.
* **🖼️ Native Media Library Previews:** Automatically fixes broken 1x1 or invisible vector thumbnails in both Grid and List views (`wp-admin/upload.php`).
* **📐 Automatic Dimension Detection:** Parses `viewBox` and `width`/`height` coordinates on upload and writes them to WordPress attachment metadata.
* **⚡ Zero-Overhead Client-Side Inliner:** Replaces target `<img>` tags with raw `<svg>` code using lightweight Vanilla JS (< 2KB), enabling direct CSS styling (`fill: currentColor`) and hover animations.
* **🧠 In-Memory Request Deduplication:** Fetches shared SVG assets once and clones them across repeated icons (e.g. social footer icons).
* **🔄 Dynamic DOM Observer:** Compatible with Gutenberg block inserters, Elementor, and AJAX pagination using `MutationObserver`.
* **🔒 Role-Based Permissions:** Restrict SVG upload privileges to Administrators only or extend permissions to all uploaders (Authors/Editors).
* **📦 Lossless Vector Scaling:** Skips unnecessary GD/Imagick raster thumbnail generation and disables redundant `srcset` attributes to conserve server disk space.

---

## 📂 Directory Structure

```text
svg-flow/
├── svg-flow.php                  # Main plugin entry point & bootstrap
├── readme.txt                     # Official WordPress.org repository documentation
├── README.md                      # GitHub documentation
├── .gitignore                     # Git ignore rules
├── composer.json                  # Composer dependencies declaration
├── uninstall.php                  # Database cleanup upon deletion
├── assets/
│   ├── css/
│   │   ├── admin.css              # Admin UI & media library preview styles
│   │   └── frontend.css           # Responsive SVG sizing safeguards
│   └── js/
│       ├── admin.js               # Admin settings 1-click clipboard helper
│       └── frontend.js            # Vanilla JS inliner with cache & MutationObserver
├── includes/
│   ├── class-svgflow-core.php     # Core coordinator singleton
│   ├── class-svgflow-sanitizer.php# DOM-level XML sanitization & .svgz handling
│   ├── class-svgflow-media.php    # Media library grid previews & dimension parser
│   ├── class-svgflow-admin.php    # Settings screen & options management
│   └── class-svgflow-frontend.php # Frontend script enqueuing & localization
├── languages/
│   └── svg-flow.pot               # Translation catalog template
└── vendor/                        # Bundled Composer dependencies (enshrined/svg-sanitize)
```

---

## 🛠️ Installation

1. Clone or download the repository into your WordPress plugins directory:
   ```bash
   git clone https://github.com/your-username/svg-flow.git wp-content/plugins/svg-flow
   ```
2. Activate **SVGFlow** in **WordPress Admin → Plugins**.
3. Go to **Settings → SVGFlow** to configure permissions and enable inlining.

---

## 🎨 How to Inline an SVG on the Frontend

1. Ensure **"Enable client-side inline SVG replacement"** is checked in **Settings → SVGFlow**.
2. Add the CSS class `style-svg` (or your custom selector) to any Image element in WordPress:
   * **Block Editor (Gutenberg):** Select the Image block → **Advanced → Additional CSS class(es)** → add `style-svg`.
   * **Page Builders (Elementor / Divi):** Add `style-svg` in the element's CSS class setting.
   * **Classic Editor / HTML:** `<img src="icon.svg" class="style-svg" />`
3. SVGFlow will automatically convert the `<img>` tag into an inline `<svg>` in the browser DOM.

---

## 💻 Developer Hooks & Filters

### 1. Filter Allowed SVG Tags
```php
add_filter( 'svg_flow_allowed_tags', function( $tags ) {
    $tags[] = 'animate';
    return $tags;
} );
```

### 2. Filter Allowed SVG Attributes
```php
add_filter( 'svg_flow_allowed_attributes', function( $attrs ) {
    $attrs[] = 'data-custom';
    return $attrs;
} );
```

### 3. Filter User Upload Capabilities
```php
add_filter( 'svg_flow_user_can_upload', function( $can_upload, $user_id ) {
    // Custom capability logic
    return $can_upload;
}, 10, 2 );
```

---

## 📄 License

SVGFlow is open-source software licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
