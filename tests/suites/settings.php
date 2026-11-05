<?php
// Scenarios E1–E3, E11 and the spelling presets.
require __DIR__ . '/../lib/bootstrap.php';
require_once WP_PLUGIN_DIR . '/arabic-to-lat/includes/class-admin.php';

wp_set_current_user( 1 );
$admin = new ArabicToLat\Admin();

// E1: what the settings form saves.
$saved = $admin->sanitize_settings(
	array(
		'files'      => '1',
		'preset'     => 'does-not-exist',
		'custom_raw' => "ع = 3\nمحمد => Mohammed!!\nbad = x\nno separator\n\n  ق\t=\tQ  \nالشرق الأوسط = Middle East\n",
	)
);
t_check( 'E1', 'Junk lines ignored, values cleaned', $saved['custom'], array( 'ع' => '3', 'محمد' => 'mohammed', 'ق' => 'q', 'الشرق الأوسط' => 'middle east' ) );
t_check( 'E1', 'Unknown preset falls back to standard', $saved['preset'], 'standard' );
t_settings( array( 'custom' => $saved['custom'] ) );
t_check( 'E1', 'Phrase with a space in the value becomes a dash', sanitize_title( 'أخبار الشرق الأوسط' ), 'akhbar-middle-east' );

// E2: empty value removes the letter.
t_settings( array( 'custom' => array( 'ع' => '' ) ) );
t_check( 'E2', 'Empty replacement removes the letter', sanitize_title( 'عمر' ), 'mr' );

// E3: changing the style later only affects new slugs.
t_reset_settings();
$old = wp_insert_post( array( 'post_title' => 'جمال', 'post_status' => 'publish' ) );
t_settings( array( 'preset' => 'egyptian' ) );
$new = wp_insert_post( array( 'post_title' => 'جمال', 'post_status' => 'publish' ) );
t_check( 'E3', 'Existing slug unchanged after switching style', get_post( $old )->post_name, 'jmal' );
t_check( 'E3', 'New slug uses the new style', get_post( $new )->post_name, 'gmal' );

// Presets.
$p = function ( $preset, $text ) { return sanitize_title_with_dashes( ( new ArabicToLat\Transliterator( array(), $preset ) )->transliterate( $text ), '', 'save' ); };
t_check( 'E3', 'Maghreb: شكور', $p( 'maghreb', 'شكور' ), 'chkour' );
t_check( 'E3', 'Maghreb: وجدة', $p( 'maghreb', 'وجدة' ), 'oujda' );
t_check( 'E3', 'Arabizi: حبيبي عمر', $p( 'arabizi', 'حبيبي عمر' ), '7bibi-3mr' );
t_check( 'E3', 'Persian: خانه keeps its vowel', $p( 'persian', 'خانه' ), 'khane' );
t_check( 'E3', 'Persian: ماه keeps its h after a long vowel', $p( 'persian', 'ماه' ), 'mah' );
t_check( 'E3', 'Persian: دانشگاه keeps its h after a long vowel', $p( 'persian', 'دانشگاه' ), 'danshgah' );
t_check( 'E3', 'Persian: و is v', $p( 'persian', 'وردپرس' ), 'vrdprs' );
t_check( 'E3', 'Persian: ق is gh', $p( 'persian', 'قیمت' ), 'ghimt' );
t_check( 'E3', 'Persian: ذ ث ض read the Persian way', $p( 'persian', 'ذرت ثروت ضرر' ), 'zrt-srut-zrr' );
t_check( 'E3', 'Standard style is unchanged by the Persian one', $p( 'standard', 'خانه' ), 'khanh' );
t_check( 'E3', 'Arabic is unchanged by the Persian one', $p( 'standard', 'مدرسة جميلة' ), 'mdrsa-jmila' );
t_check( 'E3', 'Custom ه beats the Persian rule', sanitize_title_with_dashes( ( new ArabicToLat\Transliterator( array( 'ه' => 'H' ), 'persian' ) )->transliterate( 'خانه' ), '', 'save' ), 'khanh' );
t_check( 'E3', 'Custom replacement beats preset', sanitize_title_with_dashes( ( new ArabicToLat\Transliterator( array( 'ج' => 'dj' ), 'egyptian' ) )->transliterate( 'جمال' ), '', 'save' ), 'djmal' );
t_reset_settings();

// E11: 0.3's atl_table filter (themes may still use it).
$f = function ( $t ) { $t['ق'] = 'g'; return $t; };
add_filter( 'atl_table', $f );
ArabicToLat\Plugin::instance()->reset();
t_check( 'E11', 'atl_table filter from 0.3 still applied', sanitize_title( 'قمر' ), 'gmr' );
remove_filter( 'atl_table', $f );
ArabicToLat\Plugin::instance()->reset();

// Developer filters.
add_filter( 'arabic_to_lat_should_transliterate', '__return_false' );
t_check( 'E11', 'arabic_to_lat_should_transliterate false keeps Arabic', sanitize_title( 'عربي' ), '%d8%b9%d8%b1%d8%a8%d9%8a' );
remove_filter( 'arabic_to_lat_should_transliterate', '__return_false' );
$pre = function ( $r, $text ) { return false !== strpos( $text, 'قهوة' ) ? 'coffee' : $r; };
add_filter( 'arabic_to_lat_pre_transliterate', $pre, 10, 2 );
t_check( 'E11', 'arabic_to_lat_pre_transliterate replaces the result', sanitize_title( 'قهوة' ), 'coffee' );
remove_filter( 'arabic_to_lat_pre_transliterate', $pre, 10 );
