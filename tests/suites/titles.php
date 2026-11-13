<?php
// Scenarios A1–A15: what people type as titles and slugs.
require __DIR__ . '/../lib/bootstrap.php';

wp_set_current_user( 1 );
$slug = function ( $title, array $args = array() ) {
	$id = wp_insert_post( array_merge( array( 'post_title' => $title, 'post_status' => 'publish' ), $args ), true );
	return is_wp_error( $id ) ? 'WP_Error: ' . $id->get_error_message() : get_post( $id )->post_name;
};
$baseline = function ( $text ) {
	return t_without_plugin( function () use ( $text ) { return sanitize_title( $text ); } );
};

// A1: English-only titles behave exactly as without the plugin.
t_check( 'A1', 'English title slug', $slug( 'Hello World' ), 'hello-world' );
$same = array();
foreach ( array( 'Hello World', 'Café über Größe', 'C++ & C# — 2026!', 'Привет мир', 'שלום', '你好', 'naïve résumé', '   spaced   out  ' ) as $text ) {
	if ( sanitize_title( $text ) !== $baseline( $text ) ) {
		$same[] = $text;
	}
}
t_check( 'A1', 'Non-Arabic text: identical to WordPress without the plugin', $same, array() );

// A2–A3.
t_check( 'A2', 'Mixed English + Arabic', $slug( 'iPhone 17 مراجعة' ), 'iphone-17-mrajaa' );
$emoji = $slug( 'عرض 🔥 خاص!' );
t_true( 'A3', 'Arabic + emoji: Arabic converted, emoji handled as WordPress does', 0 === strpos( $emoji, 'ard-' ) && 'khas' === substr( $emoji, -4 ), $emoji );

// A4: nothing but punctuation / tatweel.
foreach ( array( '؟؟؟', 'ـــ', '، ؛ ؟' ) as $title ) {
	$s = $slug( $title );
	t_true( 'A4', "Only punctuation \"$title\": slug not empty, no stray dashes", '' !== $s && ! preg_match( '/^-|-$|^-+$/', $s ), var_export( $s, true ) );
}

// A5: very long title → valid slug within the 200-byte column.
$long = trim( str_repeat( 'تجربة عنوان طويل جدا ', 25 ) );
$s    = $slug( $long );
t_true( 'A5', 'Long Arabic title (' . mb_strlen( $long ) . ' letters): post saved, slug ≤ 200 bytes', 0 !== strpos( $s, 'WP_Error' ) && strlen( $s ) <= 200 && '' !== $s, strlen( $s ) . ' bytes: ' . substr( $s, 0, 80 ) );
$long_en = $slug( trim( str_repeat( 'a very long english title ', 12 ) ) );
t_true( 'A5', 'Long English title saved (core behaviour reference)', 0 !== strpos( $long_en, 'WP_Error' ), $long_en );

// A6: digits only.
$s = $slug( '٢٠٢٦' );
t_true( 'A6', 'Arabic-Indic digits only → numeric slug (WordPress may add a suffix)', (bool) preg_match( '/^2026(-\d+)?$/', $s ), $s );

// A7–A9.
$s = $slug( '<b>عنوان</b> &amp; نص' );
t_true( 'A7', 'HTML and entities stripped', (bool) preg_match( '/^[a-z0-9-]+$/', $s ) && false !== strpos( $s, 'ns' ), $s );
t_check( 'A8', 'Tatweel inside a word', $slug( 'مـــرحبا' ), 'mrhba' );
t_check( 'A8', 'Zero-width joiner and RTL mark', sanitize_title( "مر\u{200D}حبا\u{200F}" ), 'mrhba' );
t_check( 'A9', 'Ligature ﻻ', sanitize_title( 'ﻻ' ), 'la' );
t_check( 'A9', 'Ligature ﷲ', sanitize_title( 'ﷲ' ), 'allh' );
t_check( 'A9', 'Word typed in presentation forms (copied from a PDF)', sanitize_title( 'ﻣﺮﺣﺒﺎ ﺑﺎﻟﻌﺎﻟﻢ' ), 'mrhba-balaalm' );

// A9 (S10): Persian and Urdu letter shapes (پ چ ژ ک گ ی) copied from a PDF: same slug as the ordinary letters, with or without intl.
t_check( 'A9', 'Persian letters in presentation forms', sanitize_title( "\u{FB58}\u{FBFE}\u{FB8F} \u{FB7C}\u{FB94}\u{FB8B}" ), sanitize_title( 'پیک چگژ' ) );
t_true( 'A9', 'No Arabic-script letter left in a slug from Persian presentation forms', ! preg_match( '/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', rawurldecode( sanitize_title( "\u{FB56}\u{FB57}\u{FB7A}\u{FB8A}\u{FB8E}\u{FB92}\u{FBFC}\u{FBFF}" ) ) ), sanitize_title( "\u{FB56}\u{FB7A}" ) );

// J3: hosts without the intl extension use a built-in table. Where intl exists, the table must match it exactly.
if ( class_exists( 'Normalizer' ) ) {
	$table = ( new ReflectionMethod( 'ArabicToLat\\Transliterator', 'presentation_forms' ) );
	$table->setAccessible( true );
	$diff = array();
	foreach ( $table->invoke( null ) as $form => $letters ) {
		if ( Normalizer::normalize( $form, Normalizer::FORM_KC ) !== $letters ) {
			$diff[] = sprintf( 'U+%04X', mb_ord( $form ) );
		}
	}
	t_check( 'J3', 'Fallback table (no intl) matches intl for all ' . count( $table->invoke( null ) ) . ' forms', $diff, array() );
} else {
	t_true( 'J3', 'Running without intl: fallback table used (A9 above covers it)', true );
}

// A10–A11: uniqueness.
$first  = $slug( 'سياسة الخصوصية' );
$second = $slug( 'سياسة الخصوصية' );
t_check( 'A10', 'Same title twice: second gets -2', array( $first, $second ), array( 'siyasa-alkhsusiya', 'siyasa-alkhsusiya-2' ) );
t_check( 'A11', 'Different titles with the same transliteration stay unique', array( $slug( 'صبر' ), $slug( 'سبر' ) ), array( 'sbr', 'sbr-2' ) );

// A12–A13.
$s = $slug( 'كتاب فارسی پیشرفته گام به گام' );
t_true( 'A12', 'Persian letters mixed into Arabic: all Latin', (bool) preg_match( '/^[a-z0-9-]+$/', $s ), $s );
$s = $slug( 'مرحبا Привет' );
t_true( 'A13', 'Arabic + Cyrillic: Arabic converted, Cyrillic left encoded (not dashed)', 0 === strpos( $s, 'mrhba-' ) && false !== strpos( $s, '%d0%bf' ), $s );

// A14–A15: slugs typed by the user.
t_check( 'A14', 'User-typed Latin slug kept', $slug( 'عنوان عربي', array( 'post_name' => 'my-own-slug' ) ), 'my-own-slug' );
t_check( 'A15', 'User-typed Arabic slug converted', $slug( 'أي عنوان', array( 'post_name' => 'رابط خاص' ) ), 'rabt-khas' );
