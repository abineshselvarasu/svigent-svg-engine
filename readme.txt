=== WP SVG Engine ===
Contributors: abineshselvarasu
Donate link: https://wordpress.org/plugins/svigent-tools/
Tags: svg, safe svg, inline svg, svg tools, vector
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable safe SVG & SVGZ uploads with automated DOM sanitization, Media Library preview fixes, dimension detection, and fast client-side inlining.

== Description ==

**WP SVG Engine** is a lightweight, modern, and secure solution for using SVG (Scalable Vector Graphics) in WordPress. 

By default, WordPress restricts SVG uploads due to potential XML-based vulnerabilities (such as Cross-Site Scripting and XML External Entity attacks). WP SVG Engine solves this by implementing enterprise-grade DOM-level sanitization on upload, giving you complete peace of mind while designing beautiful, resolution-independent vector graphics.

= Key Features =

* **Enterprise-Grade SVG & SVGZ Sanitization:** Automated XML inspection powered by `enshrined/svg-sanitize` to strip malicious scripts, event listeners, and dangerous entities before files reach your disk.
* **Native Media Library Previews:** Automatically fixes broken 1x1 or invisible SVG thumbnails in both Grid and List views in `wp-admin/upload.php`.
* **Automatic Dimension Detection:** Parses `viewBox` and `width`/`height` coordinates on upload and writes them to WordPress attachment metadata to preserve natural aspect ratios.
* **Zero-Overhead Frontend Inlining:** Replaces target `<img>` elements with raw `<svg>` using lightweight client-side Vanilla JS (< 2KB), enabling dynamic CSS styling (`fill: currentColor`) and hover animations.
* **Smart In-Memory Caching:** Deduplicates identical SVG network requests so repeated icons on a page are downloaded only once.
* **Dynamic DOM Observer:** Fully compatible with Gutenberg block insertion, Elementor, and AJAX infinite scroll using `MutationObserver`.
* **Role-Based Upload Permissions:** Restrict SVG upload privileges to Administrators only or extend permissions to all users with upload capabilities (Authors/Editors).
* **Lossless Vector Scaling:** Skips unnecessary raster thumbnail generation (GD/Imagick) and disables redundant `srcset` generation to conserve server disk space.

== Installation ==

1. Log in to your WordPress Admin Dashboard.
2. Navigate to **Plugins → Add New**.
3. Search for **WP SVG Engine** or upload the `svigent-tools.zip` archive.
4. Click **Install Now** and activate the plugin.
5. Go to **Settings → WP SVG Engine** to configure permissions and inlining preferences.

== Frequently Asked Questions ==

= Why does WordPress restrict SVG uploads by default? =
SVG files are XML documents, meaning they can contain executable JavaScript and external entity references that could lead to Cross-Site Scripting (XSS) or XXE attacks. WP SVG Engine strips all hazardous elements during upload while preserving all visual graphics, gradients, and shapes.

= How do I render an SVG inline on the frontend? =
1. Enable **Frontend Inline Rendering** under **Settings → WP SVG Engine**.
2. Add the class `style-svg` (or your custom selector) to any Image block or element.
3. WP SVG Engine will automatically convert the `<img>` tag into an inline `<svg>` element on the frontend.

= Does WP SVG Engine work with page builders? =
Yes! WP SVG Engine works out of the box with the WordPress Block Editor (Gutenberg), Elementor, Beaver Builder, Divi, and classic themes.

= Do I need to write code to use WP SVG Engine? =
No! WP SVG Engine works automatically. Once enabled, you can upload SVGs and style them using simple settings in your WordPress Admin.

== Screenshots ==

1. **WP SVG Engine Settings Screen:** Intuitive configuration with live security status badge and 1-click selector copy helper.
2. **Media Library Grid Preview:** Crisp, native vector thumbnails in the WordPress Media Library.
3. **Block Editor Integration:** Seamless inline SVG styling using standard WordPress image classes.

== Changelog ==

= 1.0.1 =
* UI & Responsiveness: Redesigned the settings screen layout into a clean, single-column workflow with enhanced mobile and tablet responsiveness.
* UI: Redesigned the Quick Inlining Guide copy-class box with a primary action button and border styling.
* Directory Assets: Added high-resolution icons, banners, and WordPress Playground blueprint configuration.

= 1.0.0 =
* Initial release.
* Added safe SVG and SVGZ upload handling.
* Integrated DOM-level XML sanitization via `enshrined/svg-sanitize`.
* Added Media Library grid and list thumbnail preview styling.
* Added automatic `viewBox` dimension extraction and attachment metadata normalization.
* Added client-side Vanilla JS inlining engine with in-memory caching and `MutationObserver`.
* Added role-based upload restriction settings.
