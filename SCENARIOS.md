# Arabic to Latin — scenario catalogue (what users do, what can go wrong)

Every row is an action or input a real site can produce, the behaviour we promise, and the automated test that proves it. Run all of them with `tests/run.sh` (see `tests/README.md`).

**Risk** = Impact (1–5) × Likelihood (1–5). ≥ 15 high · 8–14 medium · < 8 low. A failing test becomes a fix task in `PLAN.md`, scored the same way.
**Last full run (2026-11-03):** WP 7.1 154/154 checks, WP 6.4 134/134 (integrations skipped: their current versions need newer WordPress), WPML 11/11 on MySQL.

**Status:** ✅ test passes · ❌ test fails → fix task · 📋 manual check (no automation possible locally) · ⛔ out of scope (verdict given).

## A. Titles: what people type

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| A1 | Title only in English (`Hello World`) | Plugin does nothing; WordPress slug `hello-world` | 4×5=20 | `titles.php` | ✅ |
| A2 | Mixed English + Arabic (`iPhone 17 مراجعة`) | English kept, Arabic converted: `iphone-17-mrajaa` | 3×4=12 | `titles.php` | ✅ |
| A3 | Arabic + emoji / symbols (`عرض 🔥 خاص!`) | Emoji and `!` removed by WordPress, rest Latin | 2×3=6 | `titles.php` | ✅ |
| A4 | Title is only Arabic punctuation / tatweel (`؟؟؟`, `ـــ`) | No empty or dash-only slug; WordPress falls back to the post ID | 3×2=6 | `titles.php` | ✅ |
| A5 | Very long Arabic title (300 letters) | Valid slug ≤ 200 bytes (`post_name` column limit) | 3×2=6 | `titles.php` | ✅ |
| A6 | Only Arabic-Indic digits (`٢٠٢٦`) | Numeric slug handled by WordPress (suffix if it clashes with date archives) | 2×2=4 | `titles.php` | ✅ |
| A7 | HTML / entities in title (`<b>عنوان</b> &amp; نص`) | Tags and entities stripped, Latin slug | 2×3=6 | `titles.php` | ✅ |
| A8 | Zero-width joiner, RTL marks, tatweel inside words (`مـــرحبا`) | Invisible characters dropped: `mrhba` | 2×3=6 | `titles.php` | ✅ |
| A9 | Ligatures / presentation forms (`ﻻ`, `ﷲ`) | Decomposed: `la`, `allh` | 2×2=4 | `titles.php` | ✅ |
| A10 | Same Arabic title twice | Second gets `-2` | 3×4=12 | `titles.php` | ✅ |
| A11 | Two different titles that sound alike (`صبر` / `سبر` → `sbr`) | Unique slugs (`sbr`, `sbr-2`), never overwritten | 3×3=9 | `titles.php` | ✅ |
| A12 | Persian / Urdu mixed into Arabic | All letters converted | 2×3=6 | `titles.php` | ✅ |
| A13 | Arabic + Cyrillic / Hebrew in one title | Arabic converted, the other script left to WordPress (encoded), not dashed | 2×1=2 | `titles.php` | ✅ |
| A14 | User types their own Latin slug | Kept exactly | 5×4=20 | `titles.php` | ✅ |
| A15 | User types their own Arabic slug | Converted to Latin (explicit new slug) | 3×3=9 | `titles.php` | ✅ |
| A16 | Title of a **published** post is edited later | Slug does not change (WordPress rule) | 5×5=25 | `editing.php` | ✅ |
| A17 | Draft saved, title changed, then published | Slug from the final title | 3×4=12 | `editing.php` | ✅ |

## B. Editing content that existed before the plugin

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| B1 | **Classic editor**: open an old post/page with an Arabic slug, change only the text, Update. The slug box sends the decoded Arabic slug back | Slug and URL unchanged | 5×4=20 | `editing.php` | ✅ |
| B2 | **Quick Edit** on an old post/page (e.g. change status). Sends decoded Arabic slug | Slug unchanged | 5×4=20 | `editing.php` | ✅ |
| B3 | **Category / tag edit screen**: change only the description of an old Arabic term | Slug unchanged | 5×4=20 | `editing.php` | ✅ |
| B4 | Term Quick Edit (name only) on an old Arabic term | Slug unchanged | 5×3=15 | `editing.php` | ✅ |
| B5 | Block editor update of an old post (slug not edited) | Slug unchanged | 5×5=25 | `editing.php` | ✅ |
| B6 | User **deliberately** retypes an old Arabic slug as different Arabic text | New slug is Latin (it is a real change) | 2×2=4 | `editing.php` | ✅ |
| B7 | Bulk edit of old posts (category / status) | Slugs unchanged | 4×3=12 | `editing.php` | ✅ |
| B8 | User deliberately changes the slug of an old Arabic **page** | Old URL redirects to the new one | 3×2=6 | `editing.php` | ✅ |
| B9 | User deliberately changes the slug of an old Arabic **category** | Old URL redirects to the new one | 3×2=6 | `editing.php` | ✅ |

## C. Terms

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| C1 | New Arabic category / tag / custom taxonomy term | Latin slug | 4×5=20 | `terms.php` | ✅ |
| C2 | Tag typed by name in the post editor (REST `tags` create) | Latin slug, no duplicate term | 4×4=16 | `terms.php` | ✅ |
| C3 | Existing Arabic tag typed again by name | Existing term reused, no duplicate | 4×4=16 | `terms.php` | ✅ |
| C4 | Same Arabic name under two different parents | Two unique slugs | 2×2=4 | `terms.php` | ✅ |
| C5 | English term name | Untouched | 3×5=15 | `terms.php` | ✅ |
| C6 | Old Arabic category URL while plugin active, not converted | 200 | 5×5=25 | `http.php` | ✅ |

## D. Media

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| D1 | Upload `صورة جميلة.jpg` | File `sura-jmila.jpg`; title stays Arabic | 3×5=15 | `media.php` | ✅ |
| D2 | Upload `؟.jpg` (no letters) | Original name kept, no `.jpg` without base | 2×1=2 | `media.php` | ✅ |
| D3 | Multiple dots `صورة.نسخة.jpg` | `sura.nskha.jpg` | 2×2=4 | `media.php` | ✅ |
| D4 | Uppercase extension `صورة.JPG` | Extension kept | 2×3=6 | `media.php` | ✅ |
| D5 | Same Arabic file name uploaded twice | Second gets `-1` (WordPress) | 2×4=8 | `media.php` | ✅ |
| D6 | English file name | Untouched | 3×5=15 | `media.php` | ✅ |
| D7 | File names setting off | Arabic file name kept | 2×2=4 | `media.php` | ✅ |
| D8 | Image dragged into the **block editor** (REST upload) | Latin file name, Arabic title kept | 3×4=12 | `media.php` | ✅ |

## E. Settings and admin actions

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| E1 | Custom replacements with junk lines, Latin keys, uppercase / symbols in values | Junk ignored, values cleaned to `a-z0-9-` | 2×3=6 | `settings.php` | ✅ |
| E2 | Replacement with empty value (`ء =`) | Letter removed | 1×2=2 | `settings.php` | ✅ |
| E3 | Change spelling style after content exists | Only new slugs change | 4×3=12 | `settings.php` | ✅ |
| E3 | Persian spelling style | Final ه is the vowel *e* (خانه → khane) but stays *h* after a long vowel (ماه → mah); و → v, ق → gh, ذ/ث/ض read the Persian way; the Standard style and all Arabic output are untouched; a custom ه beats the rule | 9 | `settings.php` | ✅ |
| E4 | Convert twice | Second run converts 0 (idempotent) | 3×3=9 | `converter.php` | ✅ |
| E12 | Re-convert slugs the plugin already made, after a style change | Off by default and a separate action; only slugs the plugin produced change (a hand-written one is recognised by running the title through every style and left alone); uploads never renamed; old URLs 301; idempotent | 16 | `converter.php` | ✅ |
| E5 | Convert without the backup tick (no JavaScript) | Refused | 3×2=6 | `security.php` | ✅ |
| E6 | Editor / author role opens the converter, calls the batch AJAX | Denied (403) | 4×2=8 | `security.php` | ✅ |
| E7 | Batch AJAX without a valid nonce | Denied | 4×2=8 | `security.php` | ✅ |
| E8 | REST slug endpoint: logged out → 401, subscriber → 403, author → allowed | As stated | 3×2=6 | `security.php` | ✅ |
| E9 | Plugin **deactivated** after converting | Converted slugs stay; posts still redirect (core); pages and terms stop redirecting. Documented | 4×2=8 | `lifecycle.php` | ✅ (+ documented limitation) |
| E10 | Plugin **uninstalled** | Settings removed; old-slug records kept | 3×2=6 | `lifecycle.php` | ✅ |
| E11 | Upgrade from 0.3 (`atl_table` filter in theme) | Filter still applied | 2×1=2 | `settings.php` | ✅ |

## F. Redirects from converted URLs

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| F1 | Old post / page / child page / category URL | 301 to the new URL | 5×4=20 | `http.php` | ✅ |
| F2 | Old URL with a query string (`?utm_source=x`) | 301 keeps the query string | 3×4=12 | `http.php` | ✅ |
| F3 | Old category URL with pagination (`/category/عربي/page/2/`) | 301 to `/category/new/page/2/` | 3×3=9 | `http.php` | ✅ |
| F4 | Old URL + `/feed/` | 301 to the new feed | 2×2=4 | `http.php` | ✅ |
| F5 | Old slug of a post that is now trashed / private | Visitors end on 404; the plugin never redirects to hidden content (WordPress core's own post redirect may 301 first) | 4×2=8 | `http.php` | ✅ |
| F6 | Someone creates a **new** post using the old Arabic slug | New post is shown (200), no redirect | 3×2=6 | `http.php` | ⚠️ known |
| F7 | Unknown Arabic URL | 404 | 2×3=6 | `http.php` | ✅ |
| F8 | Old URL with uppercase percent-encoding | 301 | 3×3=9 | `http.php` | ✅ |

## G. Public pages (front end)

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| G1 | Table-of-contents / theme builds `#anchor` from an Arabic heading | Anchor unchanged | 4×4=16 | `http.php` | ✅ |
| G2 | Front-end form creates a post | Latin slug | 3×2=6 | `http.php` | ✅ |
| G3 | REST `?slug=<arabic>` lookup of an old post / category (headless) | Found | 4×2=8 | `http.php` | ✅ |

## H. Moving content between sites

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| H1 | **WordPress Importer** (`WP_IMPORTING`) imports posts that already have Arabic slugs | Slugs kept as in the export, so the migrated site keeps its URLs | 5×3=15 | `import.php` | ✅ |
| H2 | Import of posts **without** slugs | Latin slugs from titles | 2×3=6 | `import.php` | ✅ |
| H3 | `wp post create --post_name=<arabic>` (explicit, not an import) | Latin | 1×2=2 | `import.php` | ✅ |

## I. Integrations

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| I1 | WooCommerce product, category, attribute term, variations, cart; legacy products | See `woocommerce.php` | 5×3=15 | `woocommerce.php` | ✅ (+ WooCommerce limitation) |
| I2 | New Arabic-named WooCommerce attribute | Latin `pa_` name, works in cart | 4×2=8 | `woocommerce.php` | ✅ |
| I3 | Polylang: Arabic translation, `/ar/` URLs, translated terms | Latin, 200 | 4×3=12 | `polylang.php` | ✅ |
| I4 | TranslatePress active (its own `sanitize_title` filter) | Same results as without it | 4×3=12 | `translatepress.php` | ✅ |
| I5 | WPML (needs a local WPML copy + Docker MySQL) | See `wpml.php` | 4×3=12 | `wpml.php` (optional) | ✅ |
| I6 | ACF field names from Arabic labels | 📋 browser check (ACF's editor is JavaScript) | 2×3=6 | manual | 📋 |
| I7 | Block editor Latin preview | 📋 browser check, steps in `tests/README.md` | 3×5=15 | manual | 📋 |
| I8 | Yoast Duplicate Post | Latin unique slug on publish | 2×3=6 | `integrations.php` | ✅ |
| I9 | Multisite: per-site settings, switch_to_blog | Each site its own style | 3×2=6 | `multisite` (optional) | ✅ |

## J. Environment

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| J1 | Plain permalinks (`?p=123`) | Slugs still created; no redirect errors | 2×2=4 | `http.php` | ✅ |
| J2 | `/%category%/%postname%/` permalinks | Old URLs redirect | 2×2=4 | `http.php` | ✅ |
| J3 | PHP without `intl` (official PHP Docker images, some hosts) | Same result as with intl: built-in table for presentation forms | 2×2=4 | `titles.php` (`--php=7.4` runs without intl) | ✅ |
| J4 | PHP without `mbstring` | WordPress polyfills `mb_strlen` | 1×1=1 | ⛔ code review: core compat | ⛔ |
| J5 | Database not utf8mb4 | ⛔ WordPress requirement, not plugin-specific | — | ⛔ | ⛔ |
| J6 | WP 6.4 (minimum) and current | Full suite passes on both | 4×2=8 | `run.sh --wp=6.4` |
| J7 | PHP 7.4 (minimum) | Full suite passes | 5×2=10 | `run.sh --php=7.4` (Docker) | ✅ |
