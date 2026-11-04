# Contributing

Thank you for helping. Bug reports and transliteration corrections are as useful as code.

## Before you open a pull request

* **Never rename `Arabic-to-lat.php`.** WordPress identifies the plugin by that path; renaming it
  deactivates the plugin on every site at the next update.
* **Never change the slug an existing site already produced** without a migration path. People have
  published URLs. A change to the transliteration table is a new spelling style or an opt-in
  setting, not a silent edit of the default table.
* **No new runtime dependencies.** The plugin must keep working on shared hosting without Composer
  and without the PHP `intl` extension.
* **WordPress coding standards** (`WordPress-Extra`), and the `arabic_to_lat_` prefix for every new
  global function, option, hook and class outside the `ArabicToLat` namespace.
* **Escape and sanitise at the boundary**, and prepare every database query.

## Run the tests

```bash
tests/run.sh
```

It builds a throwaway WordPress on SQLite, starts a real web server and drives the plugin the way a
site does. Exit code 0 means everything passed. `tests/run.sh --wp=6.4` runs it against the oldest
supported WordPress, and `tests/run.sh --suite=titles,http --keep` leaves the site up at
`http://localhost:8890` so you can look at it. Needs `php`, `wp` (WP-CLI), `curl`, `unzip` and
`python3`; see [`tests/README.md`](tests/README.md).

CI runs the same suite on your pull request across four PHP and WordPress combinations, so you do
not have to reproduce all of them locally — but run it once before you open the PR.

**A bug fix comes with a test.** Add the scenario to [`SCENARIOS.md`](SCENARIOS.md) with a risk
score and a check in the matching suite, so it cannot come back.

## What to test, and tell us you tested

The plugin runs in a lot of places. In the pull request, say which of these you covered:

| Area | Minimum |
|---|---|
| Editors | block editor (REST), classic editor, Quick Edit |
| Entities | post, page, child page, custom post type, category, tag, custom taxonomy |
| Other paths | WP-CLI, WordPress Importer, XML-RPC, uploaded file name |
| Versions | the lowest supported WordPress (6.4) and PHP (7.4), and the newest |
| `intl` | with and without the PHP `intl` extension |
| Multilingual | WPML, Polylang or TranslatePress if your change touches terms or URLs |
| WooCommerce | legacy variable products still add to the cart, if your change touches terms |

A change to the transliteration table must come with the input, the old output, the new output and
a reason (a standard, a regional spelling, common usage) — not just a preference.

## Transliteration decisions

The default table aims at **readable**, not at a strict academic standard: و and ي become the
vowels `u` and `i` inside words (سعيد → `said`, not `sayd`). Regional alternatives belong in a
spelling style (Standard, Egyptian, Maghreb, Arabizi) or in the user's own replacement table, not in
the default.

## Translations

Translations are not accepted through pull requests. They go through
[translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/arabic-to-lat/), which
ships them to every site automatically. Arabic, Persian and Urdu reviewers are especially welcome.

## Releases

wordpress.org is the source of truth and is published over SVN. This repository mirrors the
released source; the maintainer syncs it after each release.
