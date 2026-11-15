<?php
/**
 * Spustenie nového webu jedným tlačidlom (a návrat späť).
 * Staré stránky s rovnakou adresou sa presunú do konceptov s príponou „-stary“, nové sa zverejnia,
 * nová hlavná stránka sa nastaví ako úvodná a zapne sa režim „naostro“ (články v novom dizajne, presmerovania, llms.txt).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Zoznam plánovaných zmien (bez vykonania). */
function knv_golive_plan() {
	$ids  = (array) get_option( 'knv_page_ids', array() );
	$m    = knv_manifest();
	$plan = array( 'publish' => array(), 'move' => array(), 'skip' => array(), 'front' => null );
	foreach ( $m as $key => $p ) {
		if ( 'Clanok' === $key ) { $plan['skip'][] = $key; continue; } // len šablóna
		$id = isset( $ids[ $key ] ) ? (int) $ids[ $key ] : 0;
		if ( ! $id || ! get_post( $id ) ) { $plan['skip'][] = $key; continue; }
		$path = ( ! empty( $p['parent'] ) && isset( $m[ $p['parent'] ] ) ? $m[ $p['parent'] ]['slug'] . '/' : '' ) . $p['slug'];
		if ( 'Main' !== $key ) {
			$parent = ( ! empty( $p['parent'] ) && ! empty( $ids[ $p['parent'] ] ) ) ? (int) $ids[ $p['parent'] ] : 0;
			$olds   = get_posts( array( 'post_type' => 'page', 'name' => $p['slug'], 'post_parent' => $parent, 'post_status' => array( 'publish', 'private', 'future', 'pending' ), 'numberposts' => 5, 'suppress_filters' => true ) );
			foreach ( $olds as $old ) {
				if ( (int) $old->ID === $id || get_post_meta( $old->ID, '_knv_key', true ) ) { continue; }
				$plan['move'][] = array( 'id' => (int) $old->ID, 'title' => $old->post_title, 'path' => $path, 'status' => $old->post_status, 'slug' => $old->post_name );
			}
		}
		$plan['publish'][] = array( 'key' => $key, 'id' => $id, 'path' => 'Main' === $key ? '' : $path );
	}
	$plan['front'] = array( 'old' => (int) get_option( 'page_on_front' ), 'old_show' => get_option( 'show_on_front' ), 'new' => isset( $ids['Main'] ) ? (int) $ids['Main'] : 0 );
	return $plan;
}

function knv_golive_run() {
	$plan   = knv_golive_plan();
	$backup = array( 'time' => current_time( 'mysql' ), 'moved' => array(), 'published' => array(), 'front' => $plan['front'], 'page_for_posts' => (int) get_option( 'page_for_posts' ) );
	// 1) Uvoľniť adresy – staré stránky do konceptov s príponou -stary.
	foreach ( $plan['move'] as $o ) {
		wp_update_post( array( 'ID' => $o['id'], 'post_status' => 'draft', 'post_name' => $o['slug'] . '-stary' ) );
		$backup['moved'][] = $o;
		if ( (int) get_option( 'page_for_posts' ) === $o['id'] ) { update_option( 'page_for_posts', 0 ); }
	}
	// 2) Zverejniť nové stránky (najprv rodičov).
	$m = knv_manifest();
	usort( $plan['publish'], function ( $a, $b ) use ( $m ) { return empty( $m[ $a['key'] ]['parent'] ) ? -1 : 1; } );
	foreach ( $plan['publish'] as $p ) {
		$post = get_post( $p['id'] );
		$backup['published'][] = array( 'id' => $p['id'], 'status' => $post->post_status );
		wp_update_post( array( 'ID' => $p['id'], 'post_status' => 'publish', 'post_name' => $m[ $p['key'] ]['slug'] ) );
	}
	// 3) Úvodná stránka.
	if ( $plan['front']['new'] ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $plan['front']['new'] );
	}
	update_option( 'knv_golive_backup', $backup, false );
	update_option( 'knv_live', 1 );
	flush_rewrite_rules( false );
	if ( class_exists( 'RankMath\Sitemap\Cache' ) ) { \RankMath\Sitemap\Cache::invalidate_storage(); }
	// Kontrola: nová stránka nesmie dostať „-2“.
	$bad = array();
	foreach ( $plan['publish'] as $p ) {
		$n = get_post_field( 'post_name', $p['id'] );
		if ( $n !== $m[ $p['key'] ]['slug'] ) { $bad[] = $m[ $p['key'] ]['title'] . ' → ' . $n; }
	}
	return $bad;
}

function knv_golive_rollback() {
	$b = get_option( 'knv_golive_backup' );
	if ( ! is_array( $b ) ) { return false; }
	update_option( 'knv_live', 0 );
	foreach ( $b['published'] as $p ) { wp_update_post( array( 'ID' => $p['id'], 'post_status' => $p['status'] ) ); }
	foreach ( $b['moved'] as $o ) { wp_update_post( array( 'ID' => $o['id'], 'post_status' => $o['status'], 'post_name' => $o['slug'] ) ); }
	update_option( 'show_on_front', $b['front']['old_show'] );
	update_option( 'page_on_front', $b['front']['old'] );
	update_option( 'page_for_posts', $b['page_for_posts'] );
	delete_option( 'knv_golive_backup' );
	flush_rewrite_rules( false );
	if ( class_exists( 'RankMath\Sitemap\Cache' ) ) { \RankMath\Sitemap\Cache::invalidate_storage(); }
	return true;
}

/** Sekcia v Nástroje → Nový web KNV. */
function knv_golive_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$msg = '';
	if ( isset( $_POST['knv_golive'] ) && check_admin_referer( 'knv_golive' ) ) {
		if ( empty( $_POST['knv_golive_ok'] ) ) {
			$msg = '<div class="notice notice-error"><p>Najprv zaškrtnite potvrdenie.</p></div>';
		} else {
			$bad = knv_golive_run();
			$msg = $bad ? '<div class="notice notice-warning"><p>Web je spustený, ale tieto stránky majú inú adresu: ' . esc_html( implode( ', ', $bad ) ) . '</p></div>' : '<div class="notice notice-success"><p><strong>Nový web je spustený.</strong> <a href="' . esc_url( home_url( '/' ) ) . '" target="_blank">Otvoriť web</a></p></div>';
		}
	}
	if ( isset( $_POST['knv_rollback'] ) && check_admin_referer( 'knv_golive' ) ) {
		$msg = knv_golive_rollback() ? '<div class="notice notice-success"><p>Starý web je späť.</p></div>' : '<div class="notice notice-error"><p>Nie je čo vrátiť.</p></div>';
	}
	echo $msg; // phpcs:ignore
	echo '<h2 id="spustenie" style="margin-top:28px">Spustenie nového webu</h2><form method="post">';
	wp_nonce_field( 'knv_golive' );
	if ( knv_is_live() ) {
		$b = get_option( 'knv_golive_backup' );
		echo '<p><strong style="color:#2B7F29">Nový web beží naostro</strong>' . ( is_array( $b ) ? ' od ' . esc_html( $b['time'] ) : '' ) . '.</p>';
		echo '<p>Ak treba, tlačidlo vráti všetko do stavu pred spustením (staré stránky, úvodná stránka, adresy).</p>';
		echo '<p><button class="button" name="knv_rollback" value="1" onclick="return confirm(\'Naozaj vrátiť starý web?\')">Vrátiť starý web</button></p></form>';
		return;
	}
	$plan = knv_golive_plan();
	echo '<p>Po kliknutí sa naraz: (1) staré stránky s rovnakou adresou presunú do konceptov s príponou <code>-stary</code> (nič sa nezmaže), (2) zverejní sa ' . count( $plan['publish'] ) . ' nových stránok, (3) nová hlavná stránka sa nastaví ako úvodná, (4) články sa prepnú do nového dizajnu, zapnú sa presmerovania starých adries a súbor llms.txt. Všetko sa dá vrátiť jedným tlačidlom.</p>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Stará stránka (pôjde do konceptov)</th><th>Adresa, ktorú preberie nový web</th></tr></thead><tbody>';
	foreach ( $plan['move'] as $o ) { echo '<tr><td>' . esc_html( $o['title'] ) . ' (#' . (int) $o['id'] . ')</td><td><code>/' . esc_html( $o['path'] ) . '/</code></td></tr>'; }
	if ( ! $plan['move'] ) { echo '<tr><td colspan="2">Žiadne kolízie.</td></tr>'; }
	echo '</tbody></table>';
	$front = $plan['front']['old'] ? get_the_title( $plan['front']['old'] ) : '–';
	echo '<p>Úvodná stránka: <strong>' . esc_html( $front ) . '</strong> → <strong>Hlavná stránka (nový web)</strong>. Presmerovania: <code>/nitra/</code>, <code>/dni-otvorenych-dveri-na-vysokych-skolach/</code>, <code>/dni-otvorenych-dveri-test/</code>, <code>/domov/</code>.</p>';
	echo '<p><label><input type="checkbox" name="knv_golive_ok" value="1"> Mám zálohu (UpdraftPlus) a chcem spustiť nový web.</label></p>';
	echo '<p><button class="button button-primary button-hero" name="knv_golive" value="1">Spustiť nový web</button></p></form>';
}
