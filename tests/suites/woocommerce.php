<?php
// requires: woocommerce
// Scenarios I1–I2: WooCommerce products, attributes, variations and the cart.
require __DIR__ . '/../lib/bootstrap.php';

wp_set_current_user( 1 );
$fresh = function () { delete_transient( 'wc_attribute_taxonomies' ); WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' ); };

$product = new WC_Product_Simple();
$product->set_name( 'قميص قطن' );
$product->set_status( 'publish' );
$product->save();
t_check( 'I1', 'Product slug', get_post( $product->get_id() )->post_name, 'qmis-qtn' );
$cat = wp_insert_term( 'ملابس رجالية', 'product_cat' );
t_check( 'I1', 'Product category slug', get_term( $cat['term_id'] )->slug, 'mlabs-rjaliya' );

/**
 * Variable product with a global attribute (created without a slug) and a local
 * attribute; returns what the cart needs. Keys are built with WooCommerce's
 * own functions, as its admin and front end do.
 */
$make = function ( $suffix ) use ( $fresh ) {
	$attr_id = wc_create_attribute( array( 'name' => 'اللون' . $suffix ) );
	$fresh();
	$tax = wc_attribute_taxonomy_name_by_id( $attr_id );
	register_taxonomy( $tax, 'product' );
	$red    = get_term( wp_insert_term( 'أحمر', $tax )['term_id'] );
	$global = new WC_Product_Attribute();
	$global->set_id( $attr_id ); $global->set_name( $tax ); $global->set_options( array( $red->term_id ) ); $global->set_variation( true ); $global->set_visible( true );
	$local = new WC_Product_Attribute();
	$local->set_name( 'الحجم' ); $local->set_options( array( 'كبير', 'صغير' ) ); $local->set_variation( true ); $local->set_visible( true );
	$p = new WC_Product_Variable();
	$p->set_name( 'قميص ' . $suffix ); $p->set_status( 'publish' ); $p->set_attributes( array( $global, $local ) ); $p->save();
	$local_key = substr( wc_variation_attribute_name( 'الحجم' ), strlen( 'attribute_' ) );
	$v = new WC_Product_Variation();
	$v->set_parent_id( $p->get_id() ); $v->set_regular_price( '10' ); $v->set_attributes( array( $tax => $red->slug, $local_key => 'كبير' ) ); $v->save();
	return array( $p->get_id(), $v->get_id(), $tax, $red );
};
$in_cart = function ( $data ) {
	list( $pid, $vid, $tax, $red ) = $data;
	$posted = array( 'attribute_' . $tax => $red->slug, wc_variation_attribute_name( 'الحجم' ) => 'كبير' );
	$match  = WC_Data_Store::load( 'product' )->find_matching_product_variation( wc_get_product( $pid ), $posted );
	wc_load_cart();
	WC()->cart->empty_cart();
	$key = WC()->cart->add_to_cart( $pid, 1, $vid, wc_get_product_variation_attributes( $vid ) );
	wc_clear_notices();
	return array( $match === $vid, (bool) $key );
};

$new = $make( '' );
t_check( 'I2', 'New Arabic-named attribute: Latin taxonomy name', $new[2], 'pa_allun' );
t_check( 'I2', 'Attribute term slug Latin', $new[3]->slug, 'ahmr' );
t_check( 'I1', 'New variable product: variation found and added to cart', $in_cart( $new ), array( true, true ) );

$legacy = t_without_plugin( function () use ( $make ) { return $make( ' قديم' ); } );
$fresh();
t_true( 'I1', 'Legacy product (created before the plugin): attribute stays Arabic', 0 === strpos( $legacy[2], 'pa_' ) && ! preg_match( '/^pa_[a-z0-9_-]+$/', $legacy[2] ), $legacy[2] );
$legacy_cart = $in_cart( $legacy );
t_check( 'I1', 'Legacy product: variation still found', $legacy_cart[0], true );
t_known( 'I1', 'Legacy product with an Arabic-named attribute taxonomy: add to cart ' . ( $legacy_cart[1] ? 'works' : 'fails' ), 'same result with the plugin removed: WooCommerce limitation for Arabic attribute taxonomy names (new attributes avoid it, see I2)' );

// Long Arabic attribute names are rejected by WooCommerce without the plugin.
$long = wc_create_attribute( array( 'name' => 'المقاس والحجم والطول للمنتج' ) );
t_true( 'I2', 'Long Arabic attribute name accepted', ! is_wp_error( $long ), is_wp_error( $long ) ? $long->get_error_code() : '' );

// Editing an existing Arabic attribute keeps its slug.
$old = t_without_plugin( function () { return wc_create_attribute( array( 'name' => 'المقاس' ) ); } );
$fresh();
$old_slug = wc_get_attribute( $old )->slug;
wc_update_attribute( $old, array( 'name' => 'المقاس الجديد', 'slug' => urldecode( substr( $old_slug, 3 ) ) ) );
$fresh();
t_check( 'I2', 'Editing a legacy attribute keeps its slug', wc_get_attribute( $old )->slug, $old_slug );
