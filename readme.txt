=== ScoreBox ===
Contributors: jeangalea
Tags: review, rating, schema, structured data, stars
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight review boxes with structured data (JSON-LD) for WordPress. Star ratings, pros/cons, and schema markup that Google understands.

== Description ==

ScoreBox adds a clean, responsive review box to your posts and pages with proper schema.org structured data. Built as a modern replacement for WP Review Pro.

**Features:**

* Block editor "Review Box" block with live preview
* Classic editor meta box fallback
* Star ratings (0-5, half-star increments)
* Pros and cons lists
* Summary text and CTA button
* Clean JSON-LD structured data output
* Product, SoftwareApplication, and Thing schema types
* [scorebox] shortcode support
* Configurable review box position (auto-insert before/after content, or manual via block/shortcode)
* One-click migration from WP Review Pro
* REST API endpoint for headless use
* CSS custom properties for easy theming

**Schema types supported:**

* Product (with offers)
* SoftwareApplication
* Thing

== Installation ==

1. Upload the `scorebox` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to ScoreBox > Settings to configure defaults
4. Add a Review Box block to any post or page

== Frequently Asked Questions ==

= How do I use the shortcode? =

Use `[scorebox]` to display the review box for the current post, or `[scorebox id="123"]` to display a specific post's review.

= How do I migrate from WP Review Pro? =

Go to ScoreBox > Migration in the admin menu. The tool will scan for posts with WP Review Pro data and let you migrate them one by one or all at once.

= Does this work with the classic editor? =

Yes. If you use the classic editor, a meta box will appear below the post editor with all the same fields.

= How do I customize the review box colors? =

Go to ScoreBox > Settings > Appearance. You can set the star/accent color, background color, and border color. These are applied via CSS custom properties so they can also be overridden in your theme CSS.

= How do I control where the review box appears? =

Set a global default position in ScoreBox > Settings (before content, after content, both, or manual). Override per-post in the block sidebar or meta box. "Manual" means you must place a Review Box block or [scorebox] shortcode yourself.

== Changelog ==

= 1.2.0 =
* Block editor sidebar now shows type-specific schema fields (Book author, Recipe ingredients, etc.) matching the classic meta box.
* Migration page rebuilt: shows every supported source up front with clear "Data detected" / "Nothing to migrate" status and a running count.
* JSON-LD now emits aggregateRating alongside review for better compatibility with Google rich results.
* Offers are only emitted when a price is actually set — no more placeholder price=0 offers that could hurt rich result eligibility.
* Nested Review object now includes name and itemReviewed fields — fixes "Missing field name" errors reported by Google Search Console for Review and Product snippets.

= 1.1.0 =
* Appearance preview now updates live when style or colors change.
* Schema types expanded from 3 to 14 (Book, Movie, TV Series, Video Game, Music Album, Recipe, Course, Event, Local Business, Restaurant, Creative Work, plus Product, Software Application, Thing).
* Per-type schema fields: optional Book author/ISBN, Recipe ingredients/prep time, Movie director, Event start date, Local Business address/telephone, etc.
* Migration page now hides empty sources and shows a single empty state when nothing is migratable.
* Dashboard cards reworked: "Ready to Migrate" and "Latest Review" replace the two setting-echo cards.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
Live appearance preview, 11 new schema types with optional per-type fields, cleaner migration and dashboard UI.

= 1.0.0 =
Initial release.
