=== Agencia RSE Staging Guard ===
Contributors: dmeijide
Tags: staging, noindex, development, analytics, email
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

For staging copies: hides the site from search engines and stops the real analytics, ad tags, WooCommerce webhooks and, if you want, email.

== Description ==

A staging copy cloned from the live site brings everything with it: the Google Tag
Manager container, GA4, Google Ads conversions, the Meta pixel, WooCommerce webhooks
and every email the site sends. Those tags count even on a different domain (GA4 does
not filter by hostname by default), so every test visit and every test order ends up
in the client's real reports, audiences and conversions.

Install this plugin on the staging copy and, by default, it:

* **Hides the site from search engines**: noindex on every page (WordPress core, Yoast
  SEO, Rank Math and SEOPress), an `X-Robots-Tag: noindex, nofollow` header on every PHP
  response (feeds, sitemaps, REST and AJAX included). robots.txt is left open on
  purpose, so search engines can see the noindex.
* **Blocks analytics and ad tags**: PixelYourSite, GTM4WP, Site Kit, MonsterInsights,
  Elementor Pro custom code with tracking tags and, as a safety net, any `<script>` or
  `<noscript>` of Google Tag Manager, Google Analytics, Google Ads, Meta, Microsoft
  Clarity, Hotjar, LinkedIn, TikTok or Bing left in the HTML.
* **Pauses WooCommerce webhooks**, so test orders do not reach your ERP, CRM, shipping
  or invoicing service.
* **Stops pings and pingbacks** to update services and linked sites.
* **Shows a red STAGING label** in the toolbar.

Email is left alone by default, because many staging copies need to test forms and
orders. One checkbox stops it, or sends every message to a single address with the
original recipient in the subject. The settings screen lists the last 20 stopped
emails.

**It never changes saved settings of other plugins.** Everything is done with
filters at run time: when the database is copied back to the live site, its
analytics, indexing and email keep working as before.

**It does nothing on a live site.** In automatic mode it only acts when:

* the domain looks like staging: `staging.`, `dev.`, `test.`, `demo.`, `preprod.`…,
  `localhost`, an IP address, `.local`, `.test`, or the staging domains of hosts such
  as WP Engine, Kinsta, Pantheon, Flywheel, Cloudways, WordPress.com, InstaWP,
  SiteGround or GoDaddy;
* `WP_ENVIRONMENT_TYPE` is `staging`, `development` or `local`;
* or "Discourage search engines" is checked in Settings > Reading.

If the plugin is active on a domain that looks live, it does nothing and shows a
warning in the dashboard. You can also force it with "Always on", which only applies
to the domain where you chose it, or with the `AGRSE_STAGING` constant in
`wp-config.php`.

The plugin makes no external requests and stores no visitor data.

== Installation ==

1. Install the plugin from *Plugins > Add New*, or upload the `agencia-rse-staging-guard` folder to `/wp-content/plugins/`.
2. Activate it on the staging copy.
3. Check *Tools > Staging Guard*: it says whether it is active and why.
4. Clear the page cache so the noindex and the tag blocking reach cached pages.

== Frequently Asked Questions ==

= What happens if the plugin reaches the live site? =

If you push the staging copy to the live domain with the plugin active, automatic
mode sees a live domain and does nothing, and the dashboard shows a warning so you
can deactivate it. "Always on" is tied to the staging domain, so it does not follow
the database either. The only exception is the `AGRSE_STAGING` constant, which is in
`wp-config.php` and is usually not copied.

= Does it work with page caching? =

Yes, but clear the cache after activating it, and test with a real browser after
interacting with the page: plugins such as WP Rocket delay JavaScript until the first
click or scroll.

= Does it block email sent through an SMTP plugin? =

It uses the `pre_wp_mail` filter, which WP Mail SMTP and any plugin that sends through
`wp_mail()` respect. An SMTP plugin that replaces `wp_mail()` with its own copy may
skip it: in that case, use that plugin's own option to turn email off.

= How do I allow the analytics on a staging copy? =

Untick "Block analytics and ad tags". The noindex stays on.

= How do I check that it works? =

Run `curl -sI https://your-staging-site/` and look for `X-Robots-Tag: noindex, nofollow`.
The HTML has a comment like `<!-- agencia-rse-staging-guard 1.0.0: staging, 3 tracking tags removed -->`.
In the browser console, after accepting cookies and clicking around:

    performance.getEntriesByType('resource').map(e => e.name).filter(n => /googletagmanager|google-analytics|facebook|doubleclick|clarity|hotjar/.test(n))

It must return an empty list.

== Changelog ==

= 1.0.0 =
* Initial release.
