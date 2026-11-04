# Arabic to Latin — Arabic Slugs & Permalinks in English Letters

[![Tests](https://github.com/khusamalfas/Arabic-to-latin/actions/workflows/tests.yml/badge.svg)](https://github.com/khusamalfas/Arabic-to-latin/actions/workflows/tests.yml)
[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/arabic-to-lat?label=wordpress.org)](https://wordpress.org/plugins/arabic-to-lat/)
[![Active installs](https://img.shields.io/wordpress/plugin/installs/arabic-to-lat)](https://wordpress.org/plugins/arabic-to-lat/)
[![Tested up to](https://img.shields.io/wordpress/plugin/tested/arabic-to-lat)](https://wordpress.org/plugins/arabic-to-lat/)
[![Requires PHP](https://img.shields.io/wordpress/plugin/required-php/arabic-to-lat)](https://wordpress.org/plugins/arabic-to-lat/)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

WordPress turns an Arabic title like **مرحبا بالعالم** into a URL full of percent codes:

```
example.com/%d9%85%d8%b1%d8%ad%d8%a8%d8%a7-%d8%a8%d8%a7%d9%84%d8%b9%d8%a7%d9%84%d9%85/
```

This plugin writes the slug in Latin letters instead:

```
example.com/mrhba-balaalm/
```

It covers posts, pages, custom post types, WooCommerce products, categories, tags, custom
taxonomies and uploaded file names, in the block editor, the classic editor, Quick Edit, the REST
API, WP-CLI, imports and XML-RPC. Arabic, Persian (Farsi), Urdu, Kurdish and Maghrebi letters,
Arabic-Indic and Persian digits and diacritics are supported. No API key, no external service: the
transliteration runs inside WordPress.

**Install it from the directory:** <https://wordpress.org/plugins/arabic-to-lat/> — this repository
is for development, bug reports and patches.

## This repository

| Why you are here | Where to go |
|---|---|
| Install or update the plugin | [wordpress.org/plugins/arabic-to-lat](https://wordpress.org/plugins/arabic-to-lat/) |
| Ask a usage question | [Support forum](https://wordpress.org/support/plugin/arabic-to-lat/) |
| Report a bug or a wrong transliteration | [Issues](../../issues/new/choose) |
| Suggest a letter, a spelling style or a feature | [Issues](../../issues/new/choose) |
| Send a patch | [Pull requests](../../pulls) |
| Report a security problem | [SECURITY.md](SECURITY.md) — not a public issue |
| Translate the plugin or its listing | [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/arabic-to-lat/) |

> **Versions before 1.0.0 (0.1 – 0.3) are not supported and should not be used.** They contained an
> SQL injection in the term lookup, rewrote every existing slug on activation without a preview or
> redirects, and replaced non-Arabic characters (é, ü, Cyrillic) with dashes. Update to 1.0.0.

## How it works

The plugin is **script-based, not language-based**: it transliterates any Arabic-script text in the
`save` context, which is why it behaves the same under WPML, Polylang and TranslatePress, in every
language of a multilingual site.

It is also **caller-aware**. `sanitize_title()` in the `save` context is used both to *create* slugs
and to *look them up*, so `ArabicToLat\Plugin::is_lookup_or_internal_key()` decides from the nearest
caller:

| Caller | Behaviour | Why |
|---|---|---|
| `wp_insert_post`, `wp_insert_term`, `get_sample_permalink`, … | transliterate | these create slugs |
| `WP_Term_Query::get_terms`, `get_category_by_path` | skip | otherwise every existing Arabic category and tag URL 404s |
| `wc_*`, `WC_*`, `Automattic\WooCommerce\*` internals | skip | otherwise legacy variable products cannot be added to the cart |

Activation never changes an existing slug. Converting old slugs is opt-in, from
**Settings → Arabic to Latin** or `wp arabic-to-lat convert`, with a dry run, a progress bar and
301 redirects from the old URLs (including pages and terms, which core does not redirect).

### Layout

```
arabic-to-lat/                      The plugin, exactly as released to wordpress.org
  Arabic-to-lat.php                 Main file and bootstrap (do not rename: it would
                                    deactivate the plugin on every site at update)
  includes/class-transliterator.php The transliteration table, styles and normalisation
  includes/class-plugin.php         Hooks, caller detection, redirects, integrations
  includes/class-converter.php      Batch conversion of existing slugs
  includes/class-admin.php          Settings page
  includes/class-cli.php            wp arabic-to-lat convert | slug
  assets/js/                        Block editor preview, converter UI, ACF field names
  uninstall.php                     Option cleanup
tests/                              The scenario suite — see "Running the tests"
SCENARIOS.md                        Every scenario, its risk score and the test that proves it
```

The plugin sits in its own folder so the test suite can live beside it. Install from
wordpress.org rather than cloning into `wp-content/plugins`.

## Running the tests

Every change is checked against a suite that builds a throwaway WordPress on SQLite, starts a real
web server and drives the plugin the way a site does — the block editor over REST, Quick Edit,
WP-CLI, real uploads, real HTTP requests for the 301s, WooCommerce carts. Nothing touches your own
databases.

```bash
tests/run.sh
```

Needs `php`, `wp` (WP-CLI), `curl`, `unzip`, `python3`. Exit code 0 means everything passed. Useful
options: `--wp=6.4` (the oldest supported WordPress), `--suite=titles,http` (just those),
`--keep` (leave the site and server up at `http://localhost:8890` to poke at), `--php=7.4` (rerun
everything in Docker on an older PHP).

```bash
tests/run.sh --suite=titles,converter --keep
```

See [`tests/README.md`](tests/README.md) for what each suite covers and
[`SCENARIOS.md`](SCENARIOS.md) for the scenario catalogue the IDs refer to.

CI runs the whole suite on every push and pull request across four environments — newest WordPress
on PHP 8.4 with and without the `intl` extension, PHP 7.4, and WordPress 6.4 — plus the official
WordPress Plugin Check. It also runs every Monday, so a WordPress release that breaks the plugin
shows up without anyone pushing anything.

## For developers

```php
// Replace the result, for example with a translation.
add_filter( 'arabic_to_lat_pre_transliterate', function ( $out, $text ) { return $out; }, 10, 2 );

// Decide per slug. $context is 'save' for slugs, 'file' for uploaded file names.
add_filter( 'arabic_to_lat_should_transliterate', function ( $yes, $text, $raw, $context ) {
    return 'file' === $context ? false : $yes;
}, 10, 4 );

// Override single letters or whole words.
add_filter( 'atl_table', function ( $table ) { $table['ع'] = '3'; return $table; } );
```

Other filters: `arabic_to_lat_is_public_request`, `arabic_to_lat_convert_post_types`,
`arabic_to_lat_convert_taxonomies`, `arabic_to_lat_batch_seconds`.
Action: `arabic_to_lat_slug_converted`.
REST: `GET /wp-json/arabic-to-lat/v1/slug?title=…` (users who can edit posts).

WP-CLI:

```bash
wp arabic-to-lat convert --dry-run
wp arabic-to-lat convert --post_type=product --taxonomy=product_cat
wp arabic-to-lat slug "مرحبا بالعالم" --format=json
```

Composer (via [WPackagist](https://wpackagist.org/)):

```bash
composer require wpackagist-plugin/arabic-to-lat
```

## Reporting a wrong transliteration

Open an issue with the **Wrong transliteration** template and include:

1. the exact input (title, term name or file name), copied as text, not as a screenshot;
2. the slug the plugin produced;
3. the slug you expected, and why (regional spelling, common usage, a standard);
4. the spelling style set in **Settings → Arabic to Latin** (Standard, Egyptian, Maghreb, Arabizi);
5. whether the PHP `intl` extension is active on the site.

`wp arabic-to-lat slug "<your text>"` prints exactly what the plugin would produce.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). In short: WordPress coding standards, no new dependencies,
never rename the main file, never change behaviour for slugs that already exist, and say which
WordPress and PHP versions you tested against.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Credits

Written and maintained by [Khusam Alfas](https://profiles.wordpress.org/5vlast/), who works at
SEO Revolution, [WordPress Agentur in Berlin](https://seo-revolution.com/wordpress-agentur-berlin/).

Arabic to Latin is a personal project. It is not a SEO Revolution product, and SEO Revolution does
not provide support for it. Support goes through the
[wordpress.org forum](https://wordpress.org/support/plugin/arabic-to-lat/) and this repository.
