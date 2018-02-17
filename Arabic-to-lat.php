<?php
/*
Plugin Name: ArabicToLat
Plugin URI: http://wordpress.org/extend/plugins/arabic-to-lat/
Description: This plugin converts Arabic characters in post slugs to Latin characters. Very useful for Arab-speaking users of WordPress. You can use this plugin for creating human-readable links. Send your suggestions and critics to <a href="mailto:cf@5vlast.ru">cf@5vlast.ru</a>.
Author: Husam Alfas <cf@5vlast.ru>
Author URI: http://www.5vlast.ru/
Version: 0.2
*/ 

function atl_sanitize_title($title) {
	global $wpdb;

	$iso8859 = array(
"ا"=> "a","أ"=> "a","إ"=> "ie","آ"=> "aa",
"ب"=> "b","ت"=> "t","ث"=> "th","ج"=> "j",
"ح"=> "h","خ"=> "kh","د"=> "d","ذ"=> "thz",
"ر"=> "r","ز"=> "z","س"=> "s","ش"=> "sh",
"ص"=> "ss","ض"=> "dt","ط"=> "td","ظ"=> "thz",
"ع"=> "a","غ"=> "gh","ف"=> "f","ق"=> "q",
"ك"=> "k","ل"=> "l","م"=> "m","ن"=> "n",
"ه"=> "h","و"=> "w","ي"=> "e","اي"=> "i",
"ة"=> "tt","ئ"=> "ae","ى"=> "a","ء"=> "aa",
"ؤ"=> "uo","َ"=> "a","ُ"=> "u","ِ"=> "e",
" ٌ"=> "on","ٍ"=> "en","ً"=> "an","تش"=> "tsch",
  );

	$term = $wpdb->get_var("SELECT slug FROM {$wpdb->terms} WHERE name = '$title'");
	if ( empty($term) ) {
		$title = strtr($title, apply_filters('atl_table', $iso8859));
		$title = preg_replace("/[^A-Za-z0-9`'_\-\.]/", '-', $title);
	} else {
		$title = $term;
	}

	return $title;
}

if ( !empty($_POST) || !empty($_GET['action']) && $_GET['action'] == 'edit' || defined('XMLRPC_REQUEST') && XMLRPC_REQUEST ) {
	add_filter('sanitize_title', 'atl_sanitize_title', 9);
	add_filter('sanitize_file_name', 'atl_sanitize_title');
}


function atl_convert_existing_slugs() {
	global $wpdb;

	$posts = $wpdb->get_results("SELECT ID, post_name FROM {$wpdb->posts} WHERE post_name REGEXP('[^A-Za-z0-9\-]+') AND post_status = 'publish'");
	foreach ( (array) $posts as $post ) {
		$sanitized_name = atl_sanitize_title(urldecode($post->post_name));
		if ( $post->post_name != $sanitized_name ) {
			add_post_meta($post->ID, '_wp_old_slug', $post->post_name);
			$wpdb->update($wpdb->posts, array( 'post_name' => $sanitized_name ), array( 'ID' => $post->ID ));
		}
	}

	$terms = $wpdb->get_results("SELECT term_id, slug FROM {$wpdb->terms} WHERE slug REGEXP('[^A-Za-z0-9\-]+') ");
	foreach ( (array) $terms as $term ) {
		$sanitized_slug = atl_sanitize_title(urldecode($term->slug));
		if ( $term->slug != $sanitized_slug ) {
			$wpdb->update($wpdb->terms, array( 'slug' => $sanitized_slug ), array( 'term_id' => $term->term_id ));
		}
	}
}

function atl_schedule_conversion() {
	add_action('shutdown', 'atl_convert_existing_slugs');
}
register_activation_hook(__FILE__, 'atl_schedule_conversion');
?>
