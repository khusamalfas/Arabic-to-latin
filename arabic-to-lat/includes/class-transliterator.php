<?php
/**
 * Arabic-script to Latin transliteration.
 *
 * @package ArabicToLat
 */

namespace ArabicToLat;

defined( 'ABSPATH' ) || exit;

/**
 * Converts Arabic, Persian and Urdu letters to URL-friendly Latin letters.
 *
 * Detection is by script, not by site or post language, so it behaves the
 * same under WPML, Polylang, TranslatePress or any other multilingual setup.
 */
final class Transliterator {

	/**
	 * Arabic, Arabic Supplement, Arabic Extended-A and both presentation-form blocks.
	 */
	const ARABIC_PATTERN = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

	/**
	 * Arabic-script letters (no diacritics, digits or punctuation).
	 */
	const LETTER = '[\x{0621}-\x{063A}\x{0641}-\x{064A}\x{066E}-\x{06D3}\x{06D5}\x{06FA}-\x{06FF}\x{0750}-\x{077F}]';

	/**
	 * Internal markers the context rules emit for و / ي read as vowels, so a
	 * preset can spell them (u / ou, i). Private-use characters never occur in titles.
	 */
	const VOWEL_U = "\u{E000}";
	const VOWEL_I = "\u{E001}";

	/**
	 * Marker for a final ه read as the vowel e rather than the consonant h,
	 * which is how Persian writes it: خانه is khane, not khanh.
	 */
	const VOWEL_E = "\u{E002}";

	/**
	 * Single-character map applied after the context rules.
	 *
	 * @var array<string,string>
	 */
	private $table;

	/**
	 * Multi-character custom entries (whole words or letter pairs), applied first.
	 *
	 * @var array<string,string>
	 */
	private $phrases = array();

	/**
	 * Whether the context-sensitive waw / yaa rules apply. A custom entry for
	 * either letter switches its rule off so the user's choice always wins.
	 *
	 * @var array<string,bool>
	 */
	private $rules = array(
		'waw'      => true,
		'yaa'      => true,
		'he_final' => false,
	);

	/**
	 * @param array<string,string> $custom User overrides, letter or word => Latin.
	 * @param string               $preset Key of presets(); unknown keys fall back to standard.
	 */
	public function __construct( array $custom = array(), $preset = 'standard' ) {
		$presets = self::presets();
		$chosen  = isset( $presets[ $preset ] ) ? $presets[ $preset ] : array( 'table' => array() );
		$table   = array_merge( self::default_table(), $chosen['table'] );

		// A preset may also switch a context rule on, which a letter table cannot express.
		if ( ! empty( $chosen['rules'] ) ) {
			$this->rules = array_merge( $this->rules, $chosen['rules'] );
		}

		foreach ( $custom as $from => $to ) {
			$from = (string) $from;
			if ( '' === $from ) {
				continue;
			}
			if ( mb_strlen( $from, 'UTF-8' ) > 1 ) {
				$this->phrases[ $from ] = (string) $to;
				continue;
			}
			$table[ $from ] = (string) $to;
			if ( 'و' === $from ) {
				$this->rules['waw'] = false;
			} elseif ( 'ي' === $from || 'ی' === $from ) {
				$this->rules['yaa'] = false;
			} elseif ( 'ه' === $from ) {
				$this->rules['he_final'] = false;
			}
		}

		/**
		 * Filters the transliteration table.
		 *
		 * Kept from version 0.3 for backward compatibility.
		 *
		 * @param array<string,string> $table Arabic character => Latin replacement.
		 */
		$this->table = (array) apply_filters( 'atl_table', $table ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook since 0.3; renaming it would break existing customisations.
	}

	/**
	 * Whether a string contains any Arabic-script character.
	 *
	 * @param string $text Text to test.
	 */
	public static function has_arabic( $text ) {
		return is_string( $text ) && '' !== $text && 1 === preg_match( self::ARABIC_PATTERN, $text );
	}

	/**
	 * Transliterates the Arabic-script parts of a string; everything else is left
	 * untouched for WordPress core to handle (accents, other scripts, punctuation).
	 *
	 * @param string $text Text to transliterate.
	 */
	public function transliterate( $text ) {
		if ( ! self::has_arabic( $text ) ) {
			return $text;
		}

		// Presentation forms (e.g. "ﻻ", "ﷲ") decompose to ordinary letters.
		if ( preg_match( '/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text ) ) {
			$normalized = class_exists( 'Normalizer' ) ? \Normalizer::normalize( $text, \Normalizer::FORM_KC ) : false;
			// Without the intl extension, the common forms are decomposed from a built-in table.
			$text = false !== $normalized ? $normalized : strtr( $text, self::presentation_forms() );
		}

		if ( $this->phrases ) {
			$text = strtr( $text, $this->phrases );
		}

		$text = $this->apply_context_rules( $text );

		$text = strtr( $text, $this->table );

		// Markers a filtered table no longer maps fall back to the plain vowels.
		return strtr(
			$text,
			array(
				self::VOWEL_U => 'u',
				self::VOWEL_I => 'i',
				self::VOWEL_E => 'e',
			)
		);
	}

	/**
	 * Handles what a one-letter-at-a-time table cannot: shadda, long vowels and
	 * waw / yaa used as vowels rather than consonants.
	 *
	 * @param string $text Text containing Arabic script.
	 */
	private function apply_context_rules( $text ) {
		$letter = self::LETTER;

		// Tatweel and invisible joiners / direction marks carry no sound.
		$text = preg_replace( '/[\x{0640}\x{200C}\x{200D}\x{200E}\x{200F}\x{061C}]/u', '', $text );

		// Shadda doubles its consonant; a short vowel may be written on either side of it.
		$text = preg_replace( '/(' . $letter . ')([\x{064B}-\x{0650}]?)\x{0651}([\x{064B}-\x{0650}]?)/u', '$1$1$2$3', $text );

		// A short vowel followed by its own long vowel is written once: كِتَاب → kitab, not kitaab.
		$text = preg_replace( '/\x{064E}(?=[\x{0627}\x{0649}])/u', '', $text );
		$text = preg_replace( '/\x{064F}(?=\x{0648}(?![\x{064B}-\x{0652}]))/u', '', $text );
		$text = preg_replace( '/\x{0650}(?=[\x{064A}\x{06CC}](?![\x{064B}-\x{0652}]))/u', '', $text );

		$vowel_next = '[\x{0627}\x{0649}\x{0629}\x{0648}\x{064A}\x{06CC}\x{064B}-\x{0652}]';

		if ( $this->rules['yaa'] ) {
			// Between a consonant and alef / taa marbuta: عربية → arabiya, رياض → riyad.
			$text = preg_replace( '/(?<=' . $letter . ')(?<![\x{0622}-\x{0627}\x{0648}\x{064A}\x{06CC}\x{0649}])[\x{064A}\x{06CC}](?=[\x{0627}\x{0629}])/u', self::VOWEL_I . 'y', $text );
			// Inside or at the end of a word and not followed by a vowel, yaa is the vowel i.
			$text = preg_replace( '/(?<=' . $letter . '|[\x{064B}-\x{0652}])[\x{064A}\x{06CC}](?!' . $vowel_next . ')/u', self::VOWEL_I, $text );
		}

		if ( $this->rules['waw'] ) {
			// Inside or at the end of a word and not followed by a vowel, waw is the vowel u.
			$text = preg_replace( '/(?<=' . $letter . '|[\x{064B}-\x{0652}])\x{0648}(?!' . $vowel_next . ')/u', self::VOWEL_U, $text );
		}

		if ( $this->rules['he_final'] ) {
			/*
			 * Persian: a word-final ه is the vowel e (خانه → khane, نامه → name).
			 * After a long vowel it stays a consonant and keeps its h
			 * (ماه → mah, راه → rah, گناه → gonah), so alef, waw and the
			 * long-vowel markers are excluded.
			 */
			$text = preg_replace(
				'/(?<=' . $letter . ')(?<![\x{0622}-\x{0627}\x{0648}' . self::VOWEL_U . self::VOWEL_I . '])\x{0647}(?!' . $letter . ')/u',
				self::VOWEL_E,
				$text
			);
		}

		return $text;
	}

	/**
	 * Fallback for hosts without the intl extension: Arabic presentation forms
	 * (letter shapes and ligatures copied from PDFs and word processors) mapped
	 * to ordinary letters, as Normalizer::FORM_KC would.
	 *
	 * @return array<string,string>
	 */
	private static function presentation_forms() {
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}

		// U+FE80–U+FEF4: each letter has 1, 2 or 4 shapes (isolated, final, initial, medial), in this order.
		$shapes = array(
			array( 'ء', 1 ), array( 'آ', 2 ), array( 'أ', 2 ), array( 'ؤ', 2 ), array( 'إ', 2 ), array( 'ئ', 4 ), array( 'ا', 2 ),
			array( 'ب', 4 ), array( 'ة', 2 ), array( 'ت', 4 ), array( 'ث', 4 ), array( 'ج', 4 ), array( 'ح', 4 ), array( 'خ', 4 ),
			array( 'د', 2 ), array( 'ذ', 2 ), array( 'ر', 2 ), array( 'ز', 2 ), array( 'س', 4 ), array( 'ش', 4 ), array( 'ص', 4 ),
			array( 'ض', 4 ), array( 'ط', 4 ), array( 'ظ', 4 ), array( 'ع', 4 ), array( 'غ', 4 ), array( 'ف', 4 ), array( 'ق', 4 ),
			array( 'ك', 4 ), array( 'ل', 4 ), array( 'م', 4 ), array( 'ن', 4 ), array( 'ه', 4 ), array( 'و', 2 ), array( 'ى', 2 ),
			array( 'ي', 4 ),
		);
		$map  = array();
		$code = 0xFE80;
		foreach ( $shapes as $shape ) {
			for ( $i = 0; $i < $shape[1]; $i++ ) {
				$map[ mb_chr( $code++, 'UTF-8' ) ] = $shape[0];
			}
		}

		// Lam-alef ligatures (isolated and final), and common word ligatures.
		$map += array(
			"\u{FEF5}" => 'لآ',
			"\u{FEF6}" => 'لآ',
			"\u{FEF7}" => 'لأ',
			"\u{FEF8}" => 'لأ',
			"\u{FEF9}" => 'لإ',
			"\u{FEFA}" => 'لإ',
			"\u{FEFB}" => 'لا',
			"\u{FEFC}" => 'لا',
			"\u{FDF2}" => 'الله',
			"\u{FDFA}" => 'صلى الله عليه وسلم',
			"\u{FDFB}" => 'جل جلاله',
		);

		return $map;
	}

	/**
	 * Regional spelling presets, applied on top of the default table.
	 * Labels live in preset_labels(): this runs before translations may load.
	 *
	 * @return array<string, array{table: array<string,string>}>
	 */
	public static function presets() {
		return array(
			'standard' => array(
				'table' => array(),
			),
			'egyptian' => array(
				'table' => array(
					'ج' => 'g',
					'ث' => 's',
					'ذ' => 'z',
					'ظ' => 'z',
				),
			),
			'maghreb'  => array(
				'table' => array(
					'ش'           => 'ch',
					'و'           => 'ou',
					self::VOWEL_U => 'ou',
					'ُ'           => 'ou',
					'ث'           => 't',
					'ذ'           => 'd',
					'ظ'           => 'd',
				),
			),
			'arabizi'  => array(
				'table' => array(
					'ء' => '2',
					'ع' => '3',
					'خ' => '5',
					'ط' => '6',
					'ح' => '7',
				),
			),
			'persian'  => array(
				/*
				 * Persian reads the Arabic-only letters with Persian sounds, and
				 * واو is the consonant v, not w. See issue #1 on GitHub: the
				 * default table is an Arabic reading and gets these wrong.
				 */
				'table' => array(
					'و' => 'v',
					'ث' => 's',
					'ذ' => 'z',
					'ض' => 'z',
					'ق' => 'gh',
				),
				'rules' => array(
					'he_final' => true,
				),
			),
		);
	}

	/**
	 * @return array<string,string> Preset key => translated label.
	 */
	public static function preset_labels() {
		return array(
			'standard' => __( 'Standard', 'arabic-to-lat' ),
			'egyptian' => __( 'Egyptian', 'arabic-to-lat' ),
			'maghreb'  => __( 'Maghreb (French spelling)', 'arabic-to-lat' ),
			'arabizi'  => __( 'Franco-Arabic / Arabizi (chat numbers)', 'arabic-to-lat' ),
			'persian'  => __( 'Persian (Farsi) spelling', 'arabic-to-lat' ),
		);
	}

	/**
	 * Default table: a simplified, keyboard-friendly romanisation of the kind
	 * people actually type into search engines, not an academic standard.
	 *
	 * @return array<string,string>
	 */
	public static function default_table() {
		return array(
			// Arabic letters.
			'ا' => 'a',
			'أ' => 'a',
			'إ' => 'i',
			'آ' => 'a',
			'ٱ' => 'a',
			'ء' => '',
			'ؤ' => 'o',
			'ئ' => 'e',
			'ى' => 'a',
			'ة' => 'a',
			'ب' => 'b',
			'ت' => 't',
			'ث' => 'th',
			'ج' => 'j',
			'ح' => 'h',
			'خ' => 'kh',
			'د' => 'd',
			'ذ' => 'dh',
			'ر' => 'r',
			'ز' => 'z',
			'س' => 's',
			'ش' => 'sh',
			'ص' => 's',
			'ض' => 'd',
			'ط' => 't',
			'ظ' => 'z',
			'ع' => 'a',
			'غ' => 'gh',
			'ف' => 'f',
			'ق' => 'q',
			'ك' => 'k',
			'ل' => 'l',
			'م' => 'm',
			'ن' => 'n',
			'ه' => 'h',
			'و' => 'w',
			'ي' => 'y',
			self::VOWEL_U => 'u',
			self::VOWEL_I => 'i',
			self::VOWEL_E => 'e',

			// Persian, Urdu, Kurdish and Maghrebi letters.
			'پ' => 'p',
			'چ' => 'ch',
			'ژ' => 'zh',
			'گ' => 'g',
			'ک' => 'k',
			'ی' => 'y',
			'ڤ' => 'v',
			'ڥ' => 'v',
			'ڨ' => 'g',
			'ڭ' => 'ng',
			'ۀ' => 'h',
			'ہ' => 'h',
			'ھ' => 'h',
			'ۃ' => 'a',
			'ە' => 'e',
			'ے' => 'e',
			'ۓ' => 'e',
			'ٹ' => 't',
			'ڈ' => 'd',
			'ڑ' => 'r',
			'ں' => 'n',
			'ۆ' => 'o',
			'ێ' => 'e',
			'ڵ' => 'l',
			'ڕ' => 'r',

			// Diacritics (harakat).
			'َ' => 'a',
			'ُ' => 'u',
			'ِ' => 'i',
			'ً' => 'an',
			'ٌ' => 'un',
			'ٍ' => 'in',
			'ْ' => '',
			'ّ' => '',
			'ٰ' => 'a',
			'ٓ' => '',
			'ٔ' => '',
			'ٕ' => '',

			// Arabic-Indic and Persian digits.
			'٠' => '0',
			'١' => '1',
			'٢' => '2',
			'٣' => '3',
			'٤' => '4',
			'٥' => '5',
			'٦' => '6',
			'٧' => '7',
			'٨' => '8',
			'٩' => '9',
			'۰' => '0',
			'۱' => '1',
			'۲' => '2',
			'۳' => '3',
			'۴' => '4',
			'۵' => '5',
			'۶' => '6',
			'۷' => '7',
			'۸' => '8',
			'۹' => '9',

			// Punctuation becomes a word break.
			'،' => '-',
			'؛' => '-',
			'؟' => '-',
			'۔' => '-',
			'٫' => '-',
			'٬' => '',
			'٪' => '',
			'«' => '',
			'»' => '',
		);
	}
}
