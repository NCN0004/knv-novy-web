<?php
/**
 * Plugin Name: Kam na vysokú – nový web
 * Description: Nový dizajn kamnavysoku.sk (Roadshow 2026/27). Pri aktivácii vytvorí nové stránky ako KONCEPTY (verejne sa nič nezmení) a zobrazuje ich z HTML šablón v tomto plugine. Prehľad nájdete v Nástroje → Nový web KNV.
 * Version: 0.9.27
 * Author: Národné kariérne centrum
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: knv-novy-web
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'KNV_VERSION', '0.9.27' );
define( 'KNV_MAIL', 'lucia@nakac.sk' );
define( 'KNV_GFORM', 'https://docs.google.com/forms/d/e/1FAIpQLSdEnEkV_r_lhc0yhIEHDeDRpybGUetH_nlA_8z3FoB6tVuUaw/viewform' );
define( 'KNV_DIR', plugin_dir_path( __FILE__ ) );
define( 'KNV_URL', plugin_dir_url( __FILE__ ) );
require_once KNV_DIR . 'seo.php';
require_once KNV_DIR . 'golive.php';
require_once KNV_DIR . 'fakulta.php';
require_once KNV_DIR . 'stats.php';
require_once KNV_DIR . 'updater.php';

function knv_manifest() {
	static $m = null;
	if ( null === $m ) {
		$m = json_decode( file_get_contents( knv_file( 'pages/manifest.json' ) ), true );
		if ( ! is_array( $m ) ) { $m = array(); }
	}
	return $m;
}

/** Vytvorí chýbajúce stránky ako koncepty. Existujúce stránky (aj staré) nechá bez zmeny. */
function knv_sync_pages() {
	$ids = get_option( 'knv_page_ids', array() );
	if ( ! is_array( $ids ) ) { $ids = array(); }
	foreach ( knv_manifest() as $key => $p ) {
		if ( ! empty( $ids[ $key ] ) && get_post( (int) $ids[ $key ] ) ) { continue; }
		$found = get_posts( array(
			'post_type'   => 'page',
			'post_status' => 'any',
			'meta_key'    => '_knv_key',
			'meta_value'  => $key,
			'numberposts' => 1,
			'fields'      => 'ids',
		) );
		if ( $found ) { $ids[ $key ] = (int) $found[0]; continue; }
		$parent = ( ! empty( $p['parent'] ) && ! empty( $ids[ $p['parent'] ] ) ) ? (int) $ids[ $p['parent'] ] : 0;
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => $p['title'],
			'post_name'    => $p['slug'],
			'post_parent'  => $parent,
			'post_content' => '<!-- Obsah tejto stránky zobrazuje plugin „Kam na vysokú – nový web“. Neupravujte ju v Elementore. -->',
		), true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_knv_key', $key );
			$ids[ $key ] = (int) $id;
		}
	}
	update_option( 'knv_page_ids', $ids, false );
	update_option( 'knv_version', KNV_VERSION, false );
	if ( class_exists( 'RankMath\\Sitemap\\Cache' ) ) { \RankMath\Sitemap\Cache::invalidate_storage(); }
}
register_activation_hook( __FILE__, 'knv_sync_pages' );
add_action( 'admin_init', function () {
	if ( get_option( 'knv_version' ) !== KNV_VERSION ) { knv_sync_pages(); }
} );

/** Odkaz na stránku podľa kľúča – publikovaná = trvalý odkaz, koncept = náhľad. */
function knv_link( $key ) {
	$ids = get_option( 'knv_page_ids', array() );
	if ( empty( $ids[ $key ] ) ) { return '#'; }
	$id = (int) $ids[ $key ];
	if ( (int) get_option( 'page_on_front' ) === $id && 'page' === get_option( 'show_on_front' ) ) { return home_url( '/' ); }
	if ( 'publish' === get_post_status( $id ) ) { return get_permalink( $id ); }
	return add_query_arg( array( 'page_id' => $id, 'preview' => 'true' ), home_url( '/' ) );
}

/** Je nový web spustený naostro? */
function knv_is_live() { return (bool) get_option( 'knv_live', false ); }

add_filter( 'template_include', function ( $template ) {
	if ( is_singular( 'post' ) && file_exists( knv_file( 'pages/Clanok.dc.html' ) ) ) {
		$preview = isset( $_GET['knv'] ) && current_user_can( 'edit_posts' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( knv_is_live() || $preview ) {
			$GLOBALS['knv_current'] = array( 'key' => 'Clanok', 'id' => get_queried_object_id(), 'post' => true );
			return KNV_DIR . 'render.php';
		}
	}
	// /dni-otvorenych-dveri/ si zaberá archív kalendára udalostí (The Events Calendar) – na novom webe tam patrí stránka DOD.
	if ( knv_is_live() && function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'tribe_events' ) ) {
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore
		$ids  = get_option( 'knv_page_ids', array() );
		if ( 'dni-otvorenych-dveri' === $path && ! empty( $ids['DOD'] ) && file_exists( knv_file( 'pages/DOD.dc.html' ) ) ) {
			status_header( 200 );
			$GLOBALS['knv_current'] = array( 'key' => 'DOD', 'id' => (int) $ids['DOD'] );
			return KNV_DIR . 'render.php';
		}
	}
	// Ukážkové profily fakulty (pre obchod s fakultami) – skryté adresy, neindexované.
	$demo_path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore
	$demos     = array( 'ukazkovy-profil-fakulty' => 'Ukazka-zakladny', 'ukazkovy-profil-fakulty-maxi' => 'Ukazka-maxi' );
	if ( knv_is_live() && isset( $demos[ $demo_path ] ) && file_exists( knv_file( 'pages/' . $demos[ $demo_path ] . '.dc.html' ) ) ) {
		status_header( 200 );
		$GLOBALS['wp_query']->is_404 = false;
		$GLOBALS['knv_current'] = array( 'key' => $demos[ $demo_path ], 'id' => 0, 'nf' => true, 'demo' => true );
		return KNV_DIR . 'render.php';
	}
	// Stránka sa nenašla (404) v novom dizajne.
	if ( knv_is_live() && is_404() && file_exists( knv_file( 'pages/Nenajdene.dc.html' ) ) ) {
		status_header( 404 );
		nocache_headers();
		$GLOBALS['knv_current'] = array( 'key' => 'Nenajdene', 'id' => 0, 'nf' => true );
		return KNV_DIR . 'render.php';
	}
	// Profil fakulty v novom dizajne (pilot).
	if ( knv_is_live() && is_singular( 'fakulty' ) && file_exists( knv_file( 'pages/Fakulta.dc.html' ) ) ) {
		$fid  = get_queried_object_id();
		$pub  = in_array( (int) $fid, knv_fak_live_ids(), true );
		$prev = ! $pub && isset( $_GET['knv_profil'] ) && current_user_can( 'edit_posts' ); // phpcs:ignore
		if ( $pub || $prev ) {
			$GLOBALS['knv_current'] = array( 'key' => 'Fakulta', 'id' => (int) $fid, 'fak' => true, 'fakprev' => $prev );
			return KNV_DIR . 'render.php';
		}
	}
	if ( is_singular( 'page' ) || is_front_page() ) {
		$id  = get_queried_object_id();
		$key = $id ? get_post_meta( $id, '_knv_key', true ) : '';
		$m   = knv_manifest();
		if ( $key && isset( $m[ $key ] ) && file_exists( knv_file( 'pages/' . $key . '.dc.html' ) ) ) {
			$GLOBALS['knv_current'] = array( 'key' => $key, 'id' => $id );
			return KNV_DIR . 'render.php';
		}
	}
	return $template;
}, 99 );

/** Admin prehľad: Nástroje → Nový web KNV */
add_action( 'admin_menu', function () {
	add_management_page( 'Nový web KNV', 'Nový web KNV', 'edit_pages', 'knv-novy-web', 'knv_admin_page' );
} );
function knv_admin_page() {
	$ids = get_option( 'knv_page_ids', array() );
	echo '<div class="wrap"><h1>Kam na vysokú – nový web</h1>';
	echo '<p>Verzia ' . esc_html( KNV_VERSION ) . '. ' . ( knv_is_live() ? 'Nový web beží naostro.' : 'Stránky sú zatiaľ <strong>koncepty</strong> – vidíte ich len vy po prihlásení. Starý web beží bez zmeny. <a href="#spustenie">Spustenie nového webu ↓</a>' ) . '</p>';
	if ( isset( $_POST['knv_track_save'] ) && check_admin_referer( 'knv_track' ) && current_user_can( 'manage_options' ) ) {
		update_option( 'knv_ga_id', sanitize_text_field( wp_unslash( $_POST['knv_ga_id'] ?? '' ) ) );
		update_option( 'knv_pixel_id', preg_replace( '/\D/', '', wp_unslash( $_POST['knv_pixel_id'] ?? '' ) ) );
		update_option( 'knv_ga2_id', strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', wp_unslash( $_POST['knv_ga2_id'] ?? '' ) ) ) );
		update_option( 'knv_gtm_id', strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', wp_unslash( $_POST['knv_gtm_id'] ?? '' ) ) ) );
		echo '<div class="notice notice-success"><p>Uložené.</p></div>';
	}
	echo '<h2>Meranie a remarketing</h2><p>Google Analytics 4 a Meta pixel sa na novom webe spustia až po súhlase návštevníka v lište cookies (štatistiky / marketing). Udalosti: registrácia na Roadshow (CompleteRegistration / sign_up), prihlásenie triedy a formuláre (Lead / generate_lead), kontakt (Contact), newsletter (Subscribe).</p>';
	echo '<form method="post">'; wp_nonce_field( 'knv_track' );
	echo '<table class="form-table"><tr><th>GA4 ID</th><td><input name="knv_ga_id" value="' . esc_attr( knv_ga_id() ) . '" class="regular-text"></td></tr><tr><th>Meta pixel ID</th><td><input name="knv_pixel_id" value="' . esc_attr( knv_pixel_id() ) . '" class="regular-text"></td></tr><tr><th>GA4 ID – vlastný účet</th><td><input name="knv_ga2_id" value="' . esc_attr( knv_ga2_id() ) . '" class="regular-text"><p class="description">Druhé meranie do účtu Národné kariérne centrum (Igor Holeczy). Spúšťa sa spolu s prvým, až po súhlase.</p></td></tr><tr><th>Google Tag Manager ID</th><td><input name="knv_gtm_id" value="' . esc_attr( knv_gtm_id() ) . '" class="regular-text"><p class="description">Načíta sa až po súhlase so štatistikami alebo marketingom (Consent Mode). GA4 do GTM nepridávajte – meria sa už priamo.</p></td></tr></table>';
	echo '<p><button class="button button-primary" name="knv_track_save" value="1">Uložiť</button></p></form>';
	echo '<h2>Stránky</h2><p>Stĺpec SEO: <strong>pripravená</strong> = pôjde do Googlu po zverejnení. <strong>doplniť obsah</strong> = stránka má ešte miesta v [hranatých zátvorkách]. Kľúčové stránky sa indexujú aj tak (treba ich doplniť pred spustením), ostatné majú do doplnenia noindex a nie sú v sitemap.</p>';
	echo '<table class="widefat striped"><thead><tr><th>Stránka</th><th>Stav</th><th>SEO</th><th>Budúca adresa</th><th></th></tr></thead><tbody>';
	foreach ( knv_manifest() as $key => $p ) {
		$id = isset( $ids[ $key ] ) ? (int) $ids[ $key ] : 0;
		$st = $id ? get_post_status( $id ) : '–';
		$ph = knv_placeholders( $key ); $sd = knv_seo( $key );
		$seo = ! empty( $sd['noindex'] ) ? 'noindex (šablóna)' : ( ! $ph ? '<span style="color:#2B7F29">pripravená</span>' : ( knv_indexable( $key ) ? '<span style="color:#b26200">indexuje sa, ale doplniť obsah (' . (int) $ph . ')</span>' : '<span style="color:#b26200">noindex – doplniť obsah (' . (int) $ph . ')</span>' ) );
		echo '<tr><td><strong>' . esc_html( $p['title'] ) . '</strong>' . ( isset( $sd['t'] ) ? '<br><small>' . esc_html( $sd['t'] ) . '</small>' : '' ) . '</td><td>' . esc_html( $st ) . '</td><td>' . $seo . '</td><td><code>/' . esc_html( ( $p['parent'] ? $knv_parent = knv_manifest()[ $p['parent'] ]['slug'] . '/' : '' ) . $p['slug'] ) . '/</code></td><td>';
		if ( $id ) { echo '<a class="button" target="_blank" href="' . esc_url( knv_link( $key ) ) . '">Náhľad</a>'; }
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	knv_golive_admin();
	echo '<h2 style="margin-top:28px">Články v novom dizajne</h2><p>Články ostávajú v <em>Príspevkoch</em> a upravujú sa ako doteraz. Nový dizajn sa im zapne automaticky po spustení nového webu – dovtedy ich vidíte cez Náhľad.</p>';
	echo '<table class="widefat striped"><tbody>';
	foreach ( get_posts( array( 'numberposts' => 50, 'post_status' => 'publish' ) ) as $po ) {
		echo '<tr><td>' . esc_html( get_the_title( $po ) ) . '</td><td><a class="button" target="_blank" href="' . esc_url( add_query_arg( 'knv', '1', get_permalink( $po ) ) ) . '">Náhľad</a></td></tr>';
	}
	echo '</tbody></table></div>';
}

/** Odoslanie formulárov z nového webu e-mailom. */
function knv_handle_form() {
	if ( ! empty( $_POST['hp'] ) ) { wp_send_json_success(); }
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'knv_rl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 8 ) { wp_send_json_error( 'limit', 429 ); }
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
	$page   = isset( $_POST['page'] ) ? sanitize_text_field( wp_unslash( $_POST['page'] ) ) : '';
	$url    = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
	$fields = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? wp_unslash( $_POST['f'] ) : array();
	$lines  = array();
	$reply  = '';
	foreach ( array_slice( $fields, 0, 40 ) as $row ) {
		if ( ! is_array( $row ) || count( $row ) < 2 ) { continue; }
		$label = sanitize_text_field( $row[0] );
		$value = sanitize_textarea_field( $row[1] );
		if ( '' === $value ) { continue; }
		if ( ! $reply && is_email( $value ) ) { $reply = $value; }
		$lines[] = $label . ': ' . $value;
	}
	if ( ! $lines ) { wp_send_json_error( 'empty', 400 ); }
	$titles  = array( 'Chcem-vystavovat' => 'Záujem o vystavovanie', 'Kontakt' => 'Správa z kontaktného formulára', 'Casopis' => 'Objednávka časopisu', 'Pytaj-sa' => 'Otázka pre študentov', 'Main' => 'Prihlásenie na newsletter', 'Rady' => 'Prihlásenie na newsletter' );
	$subject = '[kamnavysoku.sk] ' . ( isset( $titles[ $page ] ) ? $titles[ $page ] : 'Formulár ' . $page );
	$body    = implode( "\n", $lines ) . "\n\n---\nStránka: " . $url . "\nOdoslané: " . wp_date( 'j. n. Y H:i' );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( $reply ) { $headers[] = 'Reply-To: ' . $reply; }
	$ok = wp_mail( KNV_MAIL, $subject, $body, $headers );
	$ok ? wp_send_json_success() : wp_send_json_error( 'mail', 500 );
}
add_action( 'wp_ajax_knv_form', 'knv_handle_form' );
add_action( 'wp_ajax_nopriv_knv_form', 'knv_handle_form' );

/**
 * Priebežné úpravy bez nahrávania ZIP: administrátor môže cez REST nahrať novú verziu súboru stránky
 * (pages/*.dc.html, pages/*.noscript.html, assets/knv.css, assets/knv.js). Prepísané súbory sa ukladajú
 * do wp-content/uploads/knv-novy-web/ a majú prednosť pred súbormi v plugine.
 */
function knv_override_dir() {
	$u = wp_upload_dir();
	return trailingslashit( $u['basedir'] ) . 'knv-novy-web/';
}
function knv_file( $rel ) {
	$o = knv_override_dir() . $rel;
	return file_exists( $o ) ? $o : KNV_DIR . $rel;
}
function knv_allowed_rel( $rel ) {
	return (bool) preg_match( '#^(pages/[A-Za-z0-9-]+\.(dc|noscript)\.html|pages/(manifest|seo)\.json|assets/(knv|consent)\.(css|js))$#', $rel );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'knv/v1', '/file', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function ( WP_REST_Request $r ) {
			$rel = (string) $r->get_param( 'path' );
			$b64 = (string) $r->get_param( 'gzb64' );
			if ( ! knv_allowed_rel( $rel ) ) { return new WP_Error( 'knv_path', 'Nepovolená cesta', array( 'status' => 400 ) ); }
			$raw = base64_decode( $b64, true );
			$txt = false !== $raw ? @gzdecode( $raw ) : false;
			if ( false === $txt || '' === $txt ) { return new WP_Error( 'knv_data', 'Chybné dáta', array( 'status' => 400 ) ); }
			$dest = knv_override_dir() . $rel;
			wp_mkdir_p( dirname( $dest ) );
			$ok = file_put_contents( $dest, $txt );
			if ( 'pages/manifest.json' === $rel ) { delete_option( 'knv_version' ); }
			return array( 'ok' => (bool) $ok, 'bytes' => strlen( $txt ), 'path' => $rel );
		},
	) );
	register_rest_route( 'knv/v1', '/file', array(
		'methods'             => 'DELETE',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function ( WP_REST_Request $r ) {
			$rel = (string) $r->get_param( 'path' );
			if ( ! knv_allowed_rel( $rel ) ) { return new WP_Error( 'knv_path', 'Nepovolená cesta', array( 'status' => 400 ) ); }
			$dest = knv_override_dir() . $rel;
			return array( 'ok' => file_exists( $dest ) ? unlink( $dest ) : true );
		},
	) );
} );

/** Šablóny JetEngine (výpisy) nepatria do mapy webu. */
add_filter( 'rank_math/sitemap/exclude_post_type', function ( $exclude, $type ) {
	return 'jet-engine' === $type ? true : $exclude;
}, 10, 2 );

/** Presmerovania premenovaných a zlúčených starých stránok (12. 11. 2026). */
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$map  = array(
		'vs/test-vs' => '/vs/univerzita-komenskeho-v-bratislave/',
		'clanky/fakulty/https-kamnavysoku-sk-clanky-fakulty-materialovotechnologicka-fakulta-stu' => '/clanky/fakulty/materialovotechnologicka-fakulta-stu/',
		'clanky/fakulty/pedagogicka-fakulta-ukba-2' => '/clanky/fakulty/pedagogicka-fakulta-ukba/',
		'clanky/fakulty/fakulta-elektrotechniky-a-informatiky-vsb-technicka-univerzita-ostrava-2' => '/clanky/fakulty/fakulta-elektrotechniky-a-informatiky-vsb-technicka-univerzita-ostrava/',
	);
	// Staré menu v starom dizajne (DOD, profily škôl a fakúlt, videá) odkazuje na ?page_id= starých stránok, ktoré sú po spustení v konceptoch.
	$ids = array( 75 => '/', 5557 => '/vystava-vysokych-skol-bratislava/', 5328 => '/vystava-vysokych-skol-trnava/', 5546 => '/vystava-vysokych-skol-trencin/', 5506 => '/vystava-vysokych-skol-zilina/', 5511 => '/vystava-vysokych-skol-banska-bystrica/', 5516 => '/vystava-vysokych-skol-poprad/', 5521 => '/vystava-vysokych-skol-presov/', 5526 => '/vystava-vysokych-skol-kosice/', 7712 => '/vystava-vysokych-skol-prievidza/', 6443 => '/clanky/', 125 => '/kontakt/', 463 => '/ochrana-osobnych-udajov/', 2431 => '/cookies/', 6478 => '/dni-otvorenych-dveri/' );
	$pid = isset( $_GET['page_id'] ) ? (int) $_GET['page_id'] : 0; // phpcs:ignore
	if ( $pid && isset( $ids[ $pid ] ) ) {
		wp_safe_redirect( home_url( $ids[ $pid ] ), 301 );
		exit;
	}
	$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore
	if ( isset( $map[ $path ] ) ) {
		wp_safe_redirect( home_url( $map[ $path ] ), 301 );
		exit;
	}
}, 1 );

/** Stránky, ktoré len presmerúvajú, nepatria do mapy webu. */
add_filter( 'rank_math/sitemap/entry', function ( $url, $type, $obj ) {
	if ( is_array( $url ) && ! empty( $url['loc'] ) ) {
		$p = trim( (string) wp_parse_url( $url['loc'], PHP_URL_PATH ), '/' );
		if ( in_array( $p, array( 'nitra', 'dni-otvorenych-dveri-na-vysokych-skolach' ), true ) ) {
			return false;
		}
	}
	return $url;
}, 10, 3 );

/** Archív starých stránok: termíny DOD, profily VŠ a fakúlt, videá, staré archívy → 301 na nové stránky. */
add_action( 'template_redirect', function () {
	if ( ! function_exists( 'knv_archive_old' ) || ! knv_archive_old() || ! knv_is_live() || is_admin() ) {
		return;
	}
	if ( is_user_logged_in() && isset( $_GET['knv_archiv'] ) ) { // phpcs:ignore – náhľad archivovanej stránky pre prihláseného
		return;
	}
	if ( is_singular( 'fakulty' ) && ( in_array( (int) get_queried_object_id(), knv_fak_live_ids(), true ) || ( isset( $_GET['knv_profil'] ) && current_user_can( 'edit_posts' ) ) ) ) { // phpcs:ignore
		return;
	}
	$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore
	$to   = '';
	if ( is_singular( array( 'tribe_events', 'tribe_venue', 'tribe_organizer' ) ) || is_tax( array( 'tribe_events_cat' ) ) ) {
		$to = '/dni-otvorenych-dveri/';
	} elseif ( is_post_type_archive( 'tribe_events' ) && 'dni-otvorenych-dveri' !== $path ) {
		$to = '/dni-otvorenych-dveri/';
	} elseif ( is_singular( array( 'vs', 'fakulty' ) ) || is_post_type_archive( array( 'vs', 'fakulty' ) ) ) {
		$to = '/co-studovat/';
	} elseif ( is_singular( 'videa' ) || is_post_type_archive( 'videa' ) || is_category() || is_tag() || is_author() ) {
		$to = '/clanky/';
	}
	if ( $to ) {
		wp_safe_redirect( home_url( $to ), 301 );
		exit;
	}
}, 2 );
