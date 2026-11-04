# Tests

Automated scenario tests for Arabic to Latin. Not part of the plugin package (the release is built from `arabic-to-lat/` only). Scenario IDs refer to [`../SCENARIOS.md`](../SCENARIOS.md).

## Run before every release

```bash
tests/run.sh                 # latest WordPress, all suites (≈ 5 min)
tests/run.sh --wp=6.4        # minimum supported WordPress
tests/run.sh --wpml=/path/to/sitepress-multilingual-cms   # + WPML (needs Docker running)
```

Exit code 0 = all checks passed. Anything else = do not release.

Other options: `--suite=titles,http` (some suites only), `--keep` (keep the site and server for debugging; the site is in `tests/.site-<version>`, the server on `http://localhost:8890`).

Needs PHP, WP-CLI, curl, unzip, python3; for `--wpml` also Docker. Nothing touches your own databases: every run builds a fresh SQLite site (WPML: a throwaway MySQL container). Plugin downloads are cached in `tests/.cache/`.

## What runs

| Suite | Scenarios | How |
|---|---|---|
| `titles` | A1–A15 | `wp_insert_post` with real titles; non-Arabic text compared with WordPress without the plugin |
| `editing` | A16–A17, B1–B9 | classic editor (`edit_post`), Quick Edit over HTTP, term edits, REST updates, bulk edit |
| `terms` | C1–C5 | categories, tags, custom taxonomy, REST tag creation |
| `media` | D1–D8 | real uploads (sideload and block editor multipart over HTTP) |
| `settings` | E1–E3, E11 | settings sanitizer, presets, developer filters, 0.3 filter |
| `converter` | E4 | 273 legacy items, batches with cursor, scope, idempotency |
| `security` | E5–E8 | admin, editor, author, subscriber, logged-out requests with real sessions and nonces |
| `lifecycle` | E9–E10 | deactivate / uninstall after converting |
| `http` | C6, F1–F8, G1–G3, J1–J2 | real HTTP: legacy URLs, 301s, query strings, pagination, feeds, public-page anchors, permalink structures |
| `import` | H1–H3 | `WP_IMPORTING` |
| `woocommerce` | I1–I2 | products, attributes, variations, cart, legacy products |
| `polylang` | I3 | languages, `/ar/` URLs, conversion |
| `translatepress` | I4 | with TranslatePress's own `sanitize_title` filter |
| `integrations` | I8 | Yoast Duplicate Post |
| `suites-wpml/wpml` | I5 | WPML on MySQL (optional) |

"Known limitations" in the summary are documented behaviour, not failures (each says why).

## Manual checks (browser; cannot be automated locally without a browser driver)

Run on the kept site (`tests/run.sh --suite=titles --keep`, then log in by running `wp --path=tests/.site-latest user update admin --user_pass=…` with a password of your choice).

| ID | Check | Expected |
|---|---|---|
| I7 | Posts → Add New, type `فنادق دبي الرخيصة` as the title, wait 1 s | Sidebar "Slug" shows `fnadq-dbi-alrkhisa` before saving; Save draft does not mark the post as changed |
| I7 | Type your own slug in the slug field, then change the title | Your slug stays |
| I6 | With ACF active: ACF → Field Groups → Add New, field label `الاسم الكامل`, Tab | Field name becomes `alasm_alkaml` |
| I9 | Multisite: network-activate, give site 2 the Egyptian style | `جمال` → `gmal` on site 2, `jmal` on site 1 |
| — | Settings → Arabic to Latin: untick Posts, Preview, Convert | Preview and conversion leave posts alone; progress bar; page reloads with the result |

Last manual pass: 2026-11-03 (all as expected).
