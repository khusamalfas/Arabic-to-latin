=== Arabic to Latin – Arabic Slugs & Permalinks in English Letters ===
Contributors: 5vlast
Tags: arabic, slug, permalink, transliteration, persian
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn Arabic, Persian and Urdu slugs into clean URLs in English letters: posts, pages, products, categories, files. Works with multilingual plugins.

== Description ==

Write titles in Arabic, publish with clean, readable URLs in English (Latin) letters.

WordPress turns an Arabic title like **مرحبا بالعالم** into a link full of percent codes:

`example.com/%d9%85%d8%b1%d8%ad%d8%a8%d8%a7-%d8%a8%d8%a7%d9%84%d8%b9%d8%a7%d9%84%d9%85/`

Paste that link into an email, a WhatsApp message or a spreadsheet and that is exactly what people see. Arabic to Latin creates this slug instead:

`example.com/mrhba-balaalm/`

It works automatically for every new post, page, product, category, tag and uploaded file. No API key, no external service, no AI credits: the transliteration runs inside WordPress.

= What it does =

* **Transliterates new slugs** of posts, pages, custom post types, WooCommerce products, categories, tags and custom taxonomies from Arabic script to Latin letters.
* **Works in the block editor (Gutenberg)**, the classic editor, Quick Edit, the REST API, WP-CLI, imports and XML-RPC.
* **Transliterates uploaded file names**: `صورة.jpg` becomes `sura.jpg` (optional).
* **Supports Arabic, Persian (Farsi), Urdu, Kurdish and Maghrebi letters**, Arabic-Indic and Persian digits (٢٠٢٦ → 2026) and diacritics (مُحَمَّد → muhammad).
* **Handles text pasted from PDFs and Word**: Arabic letter shapes and ligatures such as ﻻ and ﷲ are read as ordinary letters, also on servers without the PHP intl extension.
* **Readable results**: و and ي become the vowels u and i inside words, so سعيد becomes *said*, not *sayd*, and يوسف becomes *yusf*.
* **Regional spelling styles**: Standard, Egyptian (جمال → *gmal*), Maghreb / French (شكور → *chkour*, وجدة → *oujda*), Franco-Arabic / Arabizi chat numbers (حبيبي → *7bibi*) or **Persian (Farsi)**, which reads the letters the Persian way: خانه → *khane* not *khanh*, وردپرس → *vrdprs*, قیمت → *ghimt*, while ماه stays *mah*.
* **Editable table**: override any letter, for example ع = 3 for Franco-Arabic (Arabizi) style, or whole words (محمد = mohammed) from the settings page.
* **Latin slug preview in the block editor** while you type the title, before the first save.
* **Converts existing Arabic slugs on request**: preview first, choose post types and taxonomies, and follow a progress bar; any number of posts, no timeouts. Old URLs keep working: they redirect (301) to the new ones, for posts, pages, categories and tags.
* **Never touches what isn't a slug**: heading anchors from table-of-contents plugins and CSS classes on your public pages keep their Arabic, so links people already shared still work.
* **Never changes existing links on its own.** Activating the plugin only affects new slugs.
* **Leaves other languages alone**: accented letters, Cyrillic and other scripts are handled by WordPress exactly as before.

= Works with multilingual plugins =

The plugin detects Arabic **script**, not the site or post language. So it behaves the same whichever multilingual plugin you use, and in every language of the site:

* **WPML**: tested with WPML 4.9.7: Arabic translations from the block editor, WPML's Taxonomy Translation screen, duplicates, `/ar/` URLs, and conversion of existing Arabic slugs with redirects.
* **Polylang**: tested with Arabic, English and German languages, including `/ar/` URLs and translated categories.
* **TranslatePress**: tested together with its own slug filter; `/ar/` URLs and redirects keep the language prefix.
* Any other plugin that creates posts and terms through the standard WordPress functions.

Need native Arabic slugs in a specific case? The `arabic_to_lat_should_transliterate` filter lets you decide per slug (see FAQ).

= Works with WooCommerce, ACF and multisite =

* **WooCommerce**: product, product category, product tag and attribute term slugs are transliterated. New global attributes get a Latin name (اللون → *pa_allun*), which also avoids WooCommerce's limits on long Arabic attribute names. Existing attributes and internal keys stay exactly as they are, so variable products created before you installed the plugin keep working in the cart.
* **Advanced Custom Fields**: new fields get Latin field names from Arabic labels (الاسم الكامل → *alasm_alkaml*). Existing fields are never renamed.
* **Multisite**: network-activate it; every site keeps its own spelling style and replacements.

= For developers =

* WP-CLI: `wp arabic-to-lat convert --dry-run`, `wp arabic-to-lat convert --post_type=product --taxonomy=product_cat`, `wp arabic-to-lat slug "مرحبا بالعالم"` (formats: table, csv, json, count)
* Filters: `arabic_to_lat_pre_transliterate` (replace the result, e.g. with a translation), `arabic_to_lat_should_transliterate`, `arabic_to_lat_is_public_request`, `atl_table`, `arabic_to_lat_convert_post_types`, `arabic_to_lat_convert_taxonomies`, `arabic_to_lat_batch_seconds`
* Action: `arabic_to_lat_slug_converted`
* REST: `GET /wp-json/arabic-to-lat/v1/slug?title=…` (users who can edit posts)
* Source, issues and pull requests: [github.com/khusamalfas/Arabic-to-latin](https://github.com/khusamalfas/Arabic-to-latin). Wrong transliteration, a missing letter or a bug: open an issue there with the input and the slug you expected. Install the plugin from here, not from GitHub.

= بالعربية =

إضافة لتحويل روابط ووردبريس العربية (الاسم اللطيف أو الـ slug، والروابط الدائمة) إلى حروف إنجليزية (لاتينية) مقروءة تلقائياً، بدلاً من الرموز الطويلة مثل %d9%85%d8%b1. تعمل مع المقالات والصفحات والمنتجات والتصنيفات والوسوم وأسماء الملفات المرفوعة، وتدعم الحروف الفارسية والأردية والأرقام العربية. متوافقة مع WPML و Polylang و TranslatePress و WooCommerce ومحرر المكوّنات (غوتنبرغ). لا تغيّر الروابط القديمة إلا عند طلبك، مع تحويل 301 من الرابط القديم إلى الجديد.

= به فارسی =

افزونه‌ای برای تبدیل خودکار نامک‌های فارسی، عربی و اردو در وردپرس به حروف لاتین (انگلیسی) خوانا، به‌جای کدهای طولانی مانند %d9%85%d8%b1. برای نوشته‌ها، برگه‌ها، محصولات، دسته‌ها، برچسب‌ها و نام فایل‌ها کار می‌کند و با WPML، Polylang، TranslatePress و ووکامرس سازگار است. پیوندهای یکتای قدیمی فقط به درخواست شما تغییر می‌کنند و با ریدایرکت 301 به نشانی جدید می‌روند.

= Who makes it =

Arabic to Latin is a personal project by Khusam Alfas, who works at SEO Revolution, [WordPress Agentur in Berlin](https://seo-revolution.com/wordpress-agentur-berlin/). It is not a SEO Revolution product, and SEO Revolution does not provide support for it. For questions and bug reports, please use the support forum here on WordPress.org.

== Installation ==

1. In your WordPress admin go to **Plugins → Add New**, search for **Arabic to Latin** and click **Install**, then **Activate**.
2. That's it: new slugs are transliterated from now on.
3. Optional: go to **Settings → Arabic to Latin** to try a title, add your own replacements, or convert slugs that already exist.

Manual installation: upload the `arabic-to-lat` folder to `/wp-content/plugins/` and activate the plugin in the **Plugins** menu.

== Frequently Asked Questions ==

= Will activating the plugin change or break my existing links? =

No. Activation only affects slugs created from then on. Existing Arabic URLs keep working exactly as before. Converting existing slugs is a separate, optional step under **Settings → Arabic to Latin**, with a preview.

= What happens to old URLs if I convert existing slugs? =

They redirect with a 301 to the new URL, for posts, pages (including child pages), custom post types, categories, tags and custom taxonomies, including paginated archives (`/page/2/`), feeds and tracking parameters like `?utm_source=`. Back up your database first, and update links in menus or other sites where you can.

= I edit an old post or category whose slug is still Arabic. Will its URL change? =

No. Saving an old post, page, category or tag, from the block editor, the classic editor, Quick Edit or the term edit screen, keeps its slug. Only a slug you actually retype changes, and then the old URL redirects to the new one.

= What if I deactivate the plugin after converting? =

Your new Latin slugs stay. Old Arabic post URLs keep redirecting (WordPress does that itself), but old page and category URLs only redirect while the plugin is active.

= I'm moving my site with an importer. Will my Arabic URLs change? =

No. When an importer brings in posts and terms with their slugs (the WordPress Importer and others that use WordPress's import mode), those slugs are kept, so the moved site keeps its URLs. Items imported without a slug get a Latin one.

= Are Latin slugs better for SEO than Arabic slugs? =

Google can read Arabic URLs, so they are not a ranking problem in themselves. Latin slugs are shorter, look clean when shared in email, chat, social media and printed material, and don't get cut off or garbled by tools that don't handle encoded URLs. Choose what fits your audience. You can also keep Arabic slugs for one language of a multilingual site with a filter.

= Why transliterate instead of translating the slug to English? =

Translation needs an external service or an AI API key, costs money per post, sends your content to a third party and can produce a slug that doesn't match the page. Transliteration is instant, free, private and predictable. You can always type an English slug yourself; the plugin never overwrites a slug you entered in Latin letters.

Optional AI-written English slugs, using your own API key, are planned for a later version. Transliteration will stay the default and will keep working without any key.

= Can I change how a letter is written? =

Yes. Under **Settings → Arabic to Latin → Custom replacements**, add one line per change, for example `ع = 3` or `ة = h`. You can also replace whole words: `محمد = mohammed`. Changes apply to new slugs.

= Does it work with WPML, Polylang and TranslatePress? =

Yes. The plugin works from the characters in the title, not from the language setting, so every language of a multilingual site behaves the same. WPML, Polylang and TranslatePress were tested with `/ar/` language URLs, translated posts and translated categories.

= How do I keep Arabic in some slugs or file names? =

Use the `arabic_to_lat_should_transliterate` filter. It receives the text and a context (`save` for slugs, `file` for file names); return false to keep the Arabic. For example, to keep Arabic file names but transliterate slugs, add this to your theme's functions.php or a code snippets plugin:

`add_filter( 'arabic_to_lat_should_transliterate', function ( $yes, $text, $raw, $context ) { return 'file' === $context ? false : $yes; }, 10, 4 );`

You can also simply untick **Transliterate file names** in the settings.

= Which spelling style should I choose? =

**Standard** fits most sites. Choose **Egyptian** if your readers write ج as g (Gamal, Giza), **Maghreb** for French-style spellings common in Morocco, Algeria and Tunisia (ch, ou), or **Arabizi** if your audience writes Franco-Arabic with chat numbers (3 for ع, 7 for ح). The settings page shows the same street name in every style. It applies to new slugs only.

= Will it change the anchors of my table of contents? =

No. On public pages the plugin only transliterates when a post or term is actually being created. Heading anchors and CSS classes that themes and table-of-contents plugins build from Arabic text stay as they were, so links with #anchors that people shared keep working.

= Does it work with WooCommerce? =

Yes. Product, category, tag and attribute term slugs are transliterated. Existing variable products keep working, and WooCommerce attribute terms are never touched by the converter, because existing variations depend on them.

= Does it support Persian (Farsi) and Urdu? =

Yes: پ چ ژ گ ک ی and the Urdu letters ٹ ڈ ڑ ں ے ہ are included, as are Persian digits.

= Does it work with ACF (Advanced Custom Fields)? =

Yes. When you add a field with an Arabic label, the generated field name is Latin, so you can use `get_field( 'alasm_alkaml' )` instead of an Arabic key. Existing fields keep their names, because your content is stored under them.

= My site has thousands of posts. Will the conversion time out? =

No. It runs in short batches with a progress bar until everything is done, and you can limit it to some post types or taxonomies. On the command line, use `wp arabic-to-lat convert`.

= I used version 0.3. Anything to do? =

No. Your existing slugs stay as they are and the `atl_table` filter still works. Version 1.0 fixes slugs not being converted in the block editor and no longer turns non-Arabic characters into dashes.

== Screenshots ==

1. Settings → Arabic to Latin: try a title, choose a spelling style, add your own replacements.
2. Existing Arabic slugs: choose what to convert and preview the changes.
3. A Latin slug generated in the block editor from an Arabic title.

== Changelog ==

= 1.1.0 =
* New: a **Persian (Farsi) spelling style**. The default table reads the letters as an Arabic speaker would, which is wrong for Persian: خانه became *khanh* instead of *khane*, وردپرس became *wrdprs* instead of *vrdprs*, and قیمت became *qimt* instead of *ghimt*. The new style fixes those, and keeps the h where Persian keeps it: ماه is still *mah*, دانشگاه still *danshgah*.
* The Standard style and every Arabic slug it produces are unchanged, so no existing site's URLs move. The Persian style is opt-in, in Settings → Arabic to Latin.
* Known limit: short vowels are still not written, so مشهد is *mshhd*, not *mashhad*. A letter table cannot supply vowels that the script does not write. Reports from Persian speakers are welcome on GitHub.

= 1.0.0 =
* Rewritten and tested with WordPress 6.4 – 7.1 and PHP 7.4 – 8.4 (with and without the intl extension).
* Fixed: slugs were not transliterated in the block editor (Gutenberg / REST API).
* Fixed: a security issue in the term lookup query.
* Fixed: non-Arabic characters (accents, Cyrillic, ...) were replaced with dashes.
* Changed: activating the plugin no longer rewrites existing slugs. Converting them is optional, with a preview, from the settings page or WP-CLI.
* New: 301 redirects from old Arabic URLs of converted posts, pages and terms.
* New: Persian, Urdu, Kurdish and Maghrebi letters, Arabic-Indic and Persian digits, diacritics and shadda.
* New: more readable results (و and ي as vowels inside words).
* New: settings page with a live test, regional spelling styles (Standard, Egyptian, Maghreb, Arabizi) and custom replacements.
* New: Latin slug preview in the block editor before the first save.
* New: converter runs in batches with a progress bar and can be limited to chosen post types and taxonomies (also `--post_type` / `--taxonomy` in WP-CLI).
* New: Latin field names for new ACF fields, Latin taxonomy names for new WooCommerce attributes.
* New: heading anchors and CSS classes on public pages are never changed.
* New: multisite support (per-site settings).
* New: compatibility with WPML, Polylang, TranslatePress and WooCommerce (all tested).
* New: developer filter `arabic_to_lat_pre_transliterate`.
* New: Arabic letter shapes and ligatures from PDFs and word processors (ﻻ, ﷲ) are converted, also without the PHP intl extension.
* Safe editing: saving old posts, pages, categories and tags never changes their Arabic slug; deliberate slug changes on pages and terms redirect from the old URL.
* Imports keep the slugs of the source site.
* Redirects keep query strings and work for paginated archives and feeds.
* Uploaded images keep their Arabic title; only the file name becomes Latin.
* New: WP-CLI commands `wp arabic-to-lat convert` and `wp arabic-to-lat slug`.

= 0.3 =
* Fixed description, tested with WordPress 5.7.1, added license.

= 0.2 =
* Updated description.

= 0.1 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Major update: block editor support with live Latin preview, spelling styles, Persian/Urdu, multilingual, WooCommerce and ACF support, safe batch conversion. Existing slugs are not changed.
