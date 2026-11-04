<?php
// Scenarios A16–A17, B1–B7: editing content, especially content created before the plugin.
require __DIR__ . '/../lib/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/post.php';

wp_set_current_user( 1 );

// A16: editing the title of a published post never changes its slug.
$id = wp_insert_post( array( 'post_title' => 'عنوان أول', 'post_status' => 'publish' ) );
$before = get_post( $id )->post_name;
wp_update_post( array( 'ID' => $id, 'post_title' => 'عنوان مختلف تماما' ) );
t_check( 'A16', 'Published post: title edited, slug unchanged', get_post( $id )->post_name, $before );

// A17: draft → title changed → published.
$id = wp_insert_post( array( 'post_title' => 'مسودة أولى', 'post_status' => 'draft' ) );
wp_update_post( array( 'ID' => $id, 'post_title' => 'العنوان النهائي' ) );
wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
t_check( 'A17', 'Draft published: slug from the final title', get_post( $id )->post_name, sanitize_title( 'العنوان النهائي' ) );
t_true( 'A17', 'Final-title slug is Latin', (bool) preg_match( '/^[a-z0-9-]+$/', get_post( $id )->post_name ), get_post( $id )->post_name );

// Content created before the plugin was active.
$legacy_page = t_without_plugin( function () { return wp_insert_post( array( 'post_title' => 'من نحن', 'post_status' => 'publish', 'post_type' => 'page' ) ); } );
$legacy_post = t_without_plugin( function () { return wp_insert_post( array( 'post_title' => 'مقالة قديمة', 'post_status' => 'publish' ) ); } );
$legacy_cat  = t_without_plugin( function () { return wp_insert_term( 'أخبار', 'category' ); } );
$legacy_tag  = t_without_plugin( function () { return wp_insert_term( 'رياضة', 'post_tag' ); } );
$page_slug   = get_post( $legacy_page )->post_name;
$post_slug   = get_post( $legacy_post )->post_name;
$cat_slug    = get_term( $legacy_cat['term_id'] )->slug;
$tag_slug    = get_term( $legacy_tag['term_id'] )->slug;

// B1: classic editor. Its slug box shows the decoded slug (editable_slug → urldecode) and posts it back.
$_POST = array(
	'post_ID'     => $legacy_page,
	'post_type'   => 'page',
	'post_title'  => 'من نحن',
	'content'     => 'نص جديد',
	'post_name'   => apply_filters( 'editable_slug', $page_slug, get_post( $legacy_page ) ),
	'post_status' => 'publish',
	'action'      => 'editpost',
);
edit_post();
t_check( 'B1', 'Classic editor update of an old page keeps its slug', get_post( $legacy_page )->post_name, $page_slug );

$_POST = array(
	'post_ID'     => $legacy_post,
	'post_type'   => 'post',
	'post_title'  => 'مقالة قديمة',
	'content'     => 'نص',
	'post_name'   => apply_filters( 'editable_slug', $post_slug, get_post( $legacy_post ) ),
	'post_status' => 'publish',
	'action'      => 'editpost',
);
edit_post();
t_check( 'B1', 'Classic editor update of an old post keeps its slug', get_post( $legacy_post )->post_name, $post_slug );

// B2: Quick Edit, over HTTP exactly as the list table sends it.
$cookies = t_login( 1 );
$r = t_http(
	'wp-admin/admin-ajax.php',
	array(
		'method'  => 'POST',
		'cookies' => $cookies,
		'body'    => array(
			'action'          => 'inline-save',
			'_inline_edit'    => wp_create_nonce( 'inlineeditnonce' ),
			'post_ID'         => $legacy_page,
			'post_type'       => 'page',
			'screen'          => 'edit-page',
			'post_view'       => 'list',
			'post_title'      => 'من نحن',
			'post_name'       => urldecode( $page_slug ),
			'_status'         => 'publish',
			'post_author'     => 1,
			'comment_status'  => 'closed',
			'ping_status'     => 'closed',
			'post_password'   => '',
			'page_template'   => 'default',
		),
	)
);
clean_post_cache( $legacy_page );
t_true( 'B2', 'Quick Edit request accepted', 200 === $r['code'], 'HTTP ' . $r['code'] . ' ' . substr( wp_strip_all_tags( $r['body'] ), 0, 80 ) );
t_check( 'B2', 'Quick Edit of an old page keeps its slug', get_post( $legacy_page )->post_name, $page_slug );

// B3: term edit screen posts name, decoded slug, description.
wp_update_term( $legacy_cat['term_id'], 'category', array( 'name' => 'أخبار', 'slug' => urldecode( $cat_slug ), 'description' => 'وصف جديد' ) );
t_check( 'B3', 'Category edit (description only) keeps its slug', get_term( $legacy_cat['term_id'] )->slug, $cat_slug );

// B4: term Quick Edit posts name and decoded slug.
wp_update_term( $legacy_tag['term_id'], 'post_tag', array( 'name' => 'رياضة وصحة', 'slug' => urldecode( $tag_slug ) ) );
t_check( 'B4', 'Tag Quick Edit (rename) keeps its slug', get_term( $legacy_tag['term_id'] )->slug, $tag_slug );

// B5: block editor update (REST) without touching the slug.
wp_set_current_user( 1 );
$req = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $legacy_post );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array( 'content' => 'محتوى محدث' ) ) );
rest_do_request( $req );
t_check( 'B5', 'Block editor update of an old post keeps its slug', get_post( $legacy_post )->post_name, $post_slug );

// B6: a deliberate new Arabic slug is a real change → Latin.
wp_update_term( $legacy_cat['term_id'], 'category', array( 'slug' => 'قسم جديد' ) );
t_check( 'B6', 'Deliberately changed term slug becomes Latin', get_term( $legacy_cat['term_id'] )->slug, 'qsm-jdid' );
$_POST = array(
	'post_ID'     => $legacy_post,
	'post_type'   => 'post',
	'post_title'  => 'مقالة قديمة',
	'content'     => 'نص',
	'post_name'   => 'رابط جديد',
	'post_status' => 'publish',
	'action'      => 'editpost',
);
edit_post();
t_check( 'B6', 'Deliberately changed post slug becomes Latin', get_post( $legacy_post )->post_name, 'rabt-jdid' );

// B7: bulk edit.
$legacy_two = t_without_plugin( function () { return wp_insert_post( array( 'post_title' => 'مقالة ثانية', 'post_status' => 'publish' ) ); } );
$two_slug   = get_post( $legacy_two )->post_name;
$_REQUEST   = array( 'post' => array( $legacy_two ), 'post_type' => 'post', '_status' => 'publish', 'post_category' => array( 1 ), 'comment_status' => '', 'ping_status' => '', 'post_author' => -1, 'sticky' => '', 'post_format' => '-1' );
bulk_edit_posts( $_REQUEST );
t_check( 'B7', 'Bulk edit keeps old slugs', get_post( $legacy_two )->post_name, $two_slug );

// B8 / B9: a deliberate slug change on old Arabic content keeps the old URL working.
$_POST = array(
	'post_ID'     => $legacy_page,
	'post_type'   => 'page',
	'post_title'  => 'من نحن',
	'content'     => 'نص',
	'post_name'   => 'about-us',
	'post_status' => 'publish',
	'action'      => 'editpost',
);
edit_post();
t_check( 'B8', 'Old page slug deliberately changed', get_post( $legacy_page )->post_name, 'about-us' );
$r = t_http( '/' . $page_slug . '/' );
t_true( 'B8', 'Old page URL redirects to the new one', 301 === $r['code'] && false !== strpos( $r['location'], '/about-us/' ), $r['code'] . ' ' . $r['location'] );
$r = t_http( '/category/' . $cat_slug . '/' );
t_true( 'B9', 'Old category URL redirects after a deliberate slug change (B6)', 301 === $r['code'] && false !== strpos( $r['location'], '/category/qsm-jdid/' ), $r['code'] . ' ' . $r['location'] );
