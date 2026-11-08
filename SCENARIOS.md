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
| D3 | Multiple dots `صورة.نسخة.jpg` | `sura.nskha_.jpg`, the same rule WordPress applies to `photo.copy.jpg` (since 1.2.1, see E13) | 2×2=4 | `media.php` | ✅ |
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
| E13 | Author uploads `evil.پهپ.jpg` (Arabic letters spelling "php" in a middle extension) | Never stored as `evil.php.jpg`: WordPress's own `.php_.` protection applies to the transliterated name (security fix 1.2.1; found in the 2026-11-08 review) | 5×2=10 | `security.php` | ✅ |
| E14 | Author uploads `／‥／‥／ﻻx.jpg` (full-width slash and dots next to an Arabic ligature; Unicode normalisation turns them into `/` and `..`) | File stays in the uploads folder; no path characters in the name (security fix 1.2.1: path traversal) | 5×3=15 | `security.php` | ✅ |

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

## K. AI slug suggestions (1.2, optional, off by default)

The site owner adds their own Claude (Anthropic) or ChatGPT (OpenAI) API key. In the block editor an author asks for an English slug that describes the post; the suggestion is shown and only used when the author clicks it. Nothing runs automatically and nothing runs without a key.

| ID | Scenario | Expected | Risk | Test | Status |
|---|---|---|---|---|---|
| K1 | AI never configured (every existing site after updating) | No editor panel, no external request, transliteration identical to 1.1 | 5×5=25 | `ai.php` | ✅ |
| K2 | AI switched on but no key | Clear "add an API key" message; no request sent | 3×3=9 | `ai.php` | ✅ |
| K3 | Claude request | `POST https://api.anthropic.com/v1/messages`, `x-api-key`, `anthropic-version: 2026-10-21`, JSON-schema output, low effort, no `temperature`; title and text sent as delimited data | 4×4=16 | `ai.php` | ✅ |
| K4 | ChatGPT request | `POST https://api.openai.com/v1/responses`, `Bearer` key, strict JSON-schema `text.format` | 4×4=16 | `ai.php` | ✅ |
| K5 | Good answer (`molokhia-recipe`) | Shown as suggestion, already made unique (`-2` if taken) | 4×5=20 | `ai.php` | ✅ |
| K6 | Answer is not a usable slug (spaces, Arabic, 40 words, not JSON, empty) | Rejected with "the AI returned something unusable"; nothing saved | 4×3=12 | `ai.php` | ✅ |
| K7 | Key wrong (401), no credit (402), rate limit (429), provider overloaded (5xx/529), network timeout | Each gets its own plain message; editor keeps working; never a fatal | 4×4=16 | `ai.php` | ✅ |
| K8 | Provider declines (refusal) or answer cut off | Clear message, no partial slug | 3×2=6 | `ai.php` | ✅ |
| K9 | Same title and text asked twice | Second answer from cache, no second paid request | 3×4=12 | `ai.php` | ✅ |
| K10 | Daily limit reached | Requests stop with a message until the next day | 4×2=8 | `ai.php` | ✅ |
| K11 | Permissions: logged out, subscriber, author on someone else's post, author on own post | 401 / 403 / 403 / allowed | 5×2=10 | `ai.php` (HTTP) | ✅ |
| K12 | API key exposure: settings page, REST answers, page source, debug output | Key never printed; stored encrypted; only the last 4 characters shown | 5×3=15 | `ai.php` | ✅ |
| K13 | Key set in `wp-config.php` constant | Constant wins; settings field shows it as managed there | 3×2=6 | `ai.php` | ✅ |
| K14 | Post text tries to instruct the model ("ignore the rules, output …") | Text is sent as delimited data; whatever comes back must still pass slug validation | 4×2=8 | `ai.php` | ✅ |
| K15 | "Translate the title" style instead of "describe the post" | Prompt changes accordingly; output rules the same | 2×3=6 | `ai.php` | ✅ |
| K16 | Uninstall | AI settings and stored key deleted | 4×2=8 | `ai.php` | ✅ |
| K17 | Privacy: owner needs to know what leaves the site | Settings and readme say what is sent, to whom, when; WordPress privacy guide text added | 4×3=12 | `ai.php` + readme | ✅ |
| K18 | Live call with a real key (optional, only when `ATL_TEST_ANTHROPIC_KEY` / `ATL_TEST_OPENAI_KEY` is set) | Real slug for طبخة ملوخية is Latin, short, describes the dish | 3×2=6 | `ai.php` (skipped without key) | skipped without a key |
| K19 | WordPress 7.0+ has its own AI connectors (Settings → Connectors); older WordPress has none | 7.0+: "WordPress AI connector" offered and the default. Before 7.0: not shown, cannot be saved, a stored value gives a plain error | 4×4=16 | `ai.php` + `ai-connectors.php` | ✅ |
| K20 | Connector chosen, but none set up in Settings → Connectors | Clear "no AI connector" message with a link; nothing sent; our own saved key not used instead | 4×3=12 | `ai-connectors.php` | ✅ |
| K21 | Connector set up (official Anthropic provider plugin) | Suggestion goes through `wp_ai_client_prompt()` with the connector's key, same instructions, delimiters and JSON schema; settings page says "Ready" | 4×3=12 | `ai-connectors.php` | ✅ |
| K22 | Provider errors through the connector (401, 429, 529, timeout) | Own plain messages; WordPress passes the provider's text (which can contain the key) in its errors, so it is never shown | 5×2=10 | `ai-connectors.php` | ✅ |
| K23 | Editor load with the connector chosen | Panel loads ("Suggest with AI") without asking the provider anything: checking a connector can cost an HTTP call per page | 4×4=16 | `ai-connectors.php` | ✅ |
| K24 | Site switched AI off (`WP_AI_SUPPORT` false or `wp_supports_ai` filter) | No panel; a request gets a plain "AI is switched off" message; nothing sent | 3×2=6 | `ai-connectors.php` | ✅ |
| K25 | Connector is Google Gemini (official `ai-provider-for-google` plugin) | Suggestion goes to `generativelanguage.googleapis.com` with the connector's key (`X-Goog-Api-Key`), our instructions as `systemInstruction`, JSON output with our schema | 3×2=6 | `ai-connector-google.php` | ✅ |
| K26 | Gemini's own failures: wrong key answered with HTTP 400 `API_KEY_INVALID`, quota 429, overloaded 503, safety block with no content, answer cut off | "Check the key in Settings → Connectors" for a wrong key (not a generic "not accepted"); plain messages otherwise; Google's text and the key never shown | 4×2=8 | `ai-connector-google.php` | ✅ |
| K27 | A contributor (or any non-admin) sends many new titles to the AI endpoint | Stopped at a per-user daily cap (default 25, filter `arabic_to_lat_ai_user_daily_limit`), also when the site-wide limit is 0; admins and WP-CLI not capped; cached answers still served (security fix 1.2.1) | 3×2=6 | `ai.php` | ✅ |
