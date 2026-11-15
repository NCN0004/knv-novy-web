<?php
/**
 * Automatické aktualizácie pluginu z GitHubu (NCN0004/knv-novy-web).
 * Plugin si raz za hodinu prečíta update.json; ak je tam vyššia verzia,
 * WordPress ju ponúkne v Aktualizáciách a sám ju nainštaluje (auto-update).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'KNV_UPD_JSON', 'https://raw.githubusercontent.com/NCN0004/knv-novy-web/main/update.json' );
define( 'KNV_UPD_BASENAME', 'knv-novy-web/knv-novy-web.php' );

function knv_upd_remote( $force = false ) {
	$c = get_site_transient( 'knv_upd_info' );
	if ( ! $force && is_array( $c ) ) { return $c; }
	$r = wp_remote_get( KNV_UPD_JSON . '?t=' . time(), array( 'timeout' => 10 ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		set_site_transient( 'knv_upd_info', array(), 15 * MINUTE_IN_SECONDS );
		return array();
	}
	$d = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $d ) || empty( $d['version'] ) || empty( $d['package'] ) ) { $d = array(); }
	set_site_transient( 'knv_upd_info', $d, HOUR_IN_SECONDS );
	return $d;
}

add_filter( 'pre_set_site_transient_update_plugins', function ( $t ) {
	if ( ! is_object( $t ) ) { return $t; }
	$d = knv_upd_remote();
	if ( ! $d ) { return $t; }
	$o = (object) array(
		'id'          => 'github.com/NCN0004/knv-novy-web',
		'slug'        => 'knv-novy-web',
		'plugin'      => KNV_UPD_BASENAME,
		'new_version' => $d['version'],
		'url'         => 'https://github.com/NCN0004/knv-novy-web',
		'package'     => $d['package'],
		'tested'      => get_bloginfo( 'version' ),
	);
	if ( version_compare( $d['version'], KNV_VERSION, '>' ) ) {
		if ( ! isset( $t->response ) ) { $t->response = array(); }
		$t->response[ KNV_UPD_BASENAME ] = $o;
	} else {
		if ( ! isset( $t->no_update ) ) { $t->no_update = array(); }
		$t->no_update[ KNV_UPD_BASENAME ] = $o;
	}
	return $t;
} );

// Zip z GitHubu sa rozbalí do priečinka "knv-novy-web-v0.9.x" – premenujeme ho na "knv-novy-web".
add_filter( 'upgrader_source_selection', function ( $source, $remote_source, $upgrader, $extra = array() ) {
	global $wp_filesystem;
	$is_ours = ( isset( $extra['plugin'] ) && KNV_UPD_BASENAME === $extra['plugin'] ) || false !== strpos( basename( $source ), 'knv-novy-web' );
	if ( ! $is_ours || ! $wp_filesystem ) { return $source; }
	$want = trailingslashit( $remote_source ) . 'knv-novy-web/';
	if ( trailingslashit( $source ) === $want ) { return $source; }
	if ( $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $want ) ) ) { return $want; }
	return $source;
}, 10, 4 );

// Tento plugin sa aktualizuje automaticky (bez klikania).
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	if ( isset( $item->plugin ) && KNV_UPD_BASENAME === $item->plugin ) { return true; }
	return $update;
}, 20, 2 );

// Ďalšia kontrola aktualizácie každú hodinu (nie len 2× denne).
add_action( 'knv_upd_hourly', function () {
	delete_site_transient( 'knv_upd_info' );
	// WordPress inak kontroluje len raz za 12 hodín – vynútime čerstvú kontrolu.
	$t = get_site_transient( 'update_plugins' );
	if ( is_object( $t ) ) { $t->last_checked = 0; set_site_transient( 'update_plugins', $t ); }
	if ( ! function_exists( 'wp_update_plugins' ) ) { require_once ABSPATH . 'wp-includes/update.php'; }
	wp_update_plugins();
	if ( ! function_exists( 'wp_maybe_auto_update' ) ) { require_once ABSPATH . 'wp-includes/update.php'; }
	wp_maybe_auto_update();
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'knv_upd_hourly' ) ) { wp_schedule_event( time() + 300, 'hourly', 'knv_upd_hourly' ); }
} );
register_deactivation_hook( KNV_DIR . 'knv-novy-web.php', function () { wp_clear_scheduled_hook( 'knv_upd_hourly' ); } );

// Detail verzie v okne „Zobraziť podrobnosti“.
add_filter( 'plugins_api', function ( $res, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'knv-novy-web' !== $args->slug ) { return $res; }
	$d = knv_upd_remote();
	if ( ! $d ) { return $res; }
	return (object) array(
		'name'          => 'Kam na vysokú – nový web',
		'slug'          => 'knv-novy-web',
		'version'       => $d['version'],
		'author'        => 'Národné kariérne centrum',
		'download_link' => $d['package'],
		'sections'      => array( 'changelog' => isset( $d['notes'] ) ? wp_kses_post( $d['notes'] ) : '' ),
	);
}, 10, 3 );
