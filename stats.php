<?php
/**
 * Štatistiky návštevnosti bez cookies (nový web + profily fakúlt).
 * Ukladajú sa len denné súčty. Unikátny návštevník = denný hash (IP + prehliadač + soľ),
 * ktorý sa po 2 dňoch maže – žiadne cookies, žiadne osobné údaje natrvalo.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function knv_stats_tables() {
	global $wpdb;
	return array( $wpdb->prefix . 'knv_stats', $wpdb->prefix . 'knv_stats_uniq' );
}

add_action( 'admin_init', function () {
	if ( '3' === get_option( 'knv_stats_db' ) ) { return; }
	global $wpdb;
	list( $t, $u ) = knv_stats_tables();
	$c = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( "CREATE TABLE $t (
		day date NOT NULL,
		obj varchar(64) NOT NULL,
		ev varchar(16) NOT NULL,
		cnt int unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (day,obj,ev)
	) $c;" );
	dbDelta( "CREATE TABLE $u (
		day date NOT NULL,
		h char(32) NOT NULL,
		PRIMARY KEY  (day,h)
	) $c;" );
	$l = $wpdb->prefix . 'knv_leads';
	dbDelta( "CREATE TABLE $l (
		id bigint unsigned NOT NULL AUTO_INCREMENT,
		created datetime NOT NULL,
		email varchar(190) NOT NULL,
		fakulta bigint unsigned NOT NULL DEFAULT 0,
		url varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY fakulta (fakulta),
		KEY email (email)
	) $c;" );
	if ( ! get_option( 'knv_stats_salt' ) ) { update_option( 'knv_stats_salt', wp_generate_password( 32, false ), false ); }
	update_option( 'knv_stats_db', '3', false );
} );

function knv_stats_inc( $day, $obj, $ev ) {
	global $wpdb;
	list( $t ) = knv_stats_tables();
	$wpdb->query( $wpdb->prepare( "INSERT INTO $t (day,obj,ev,cnt) VALUES (%s,%s,%s,1) ON DUPLICATE KEY UPDATE cnt=cnt+1", $day, $obj, $ev ) ); // phpcs:ignore
}

/** Vráti true, ak tohto návštevníka dnes v danom rozsahu ešte nemáme. */
function knv_stats_first( $day, $scope ) {
	global $wpdb;
	list( , $u ) = knv_stats_tables();
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore
	$h  = substr( hash( 'sha256', $day . '|' . $ip . '|' . $ua . '|' . $scope . '|' . get_option( 'knv_stats_salt' ) ), 0, 32 );
	return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $u (day,h) VALUES (%s,%s)", $day, $h ) ); // phpcs:ignore
}

function knv_stats_hit() {
	if ( ! in_array( get_option( 'knv_stats_db' ), array( '2', '3' ), true ) || is_user_logged_in() ) { wp_die( '', '', array( 'response' => 204 ) ); }
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|headless|lighthouse|monitor|curl|wget|python|facebookexternalhit/i', $ua ) ) { wp_die( '', '', array( 'response' => 204 ) ); }
	$k  = isset( $_POST['k'] ) ? preg_replace( '/[^A-Za-z0-9-]/', '', (string) wp_unslash( $_POST['k'] ) ) : ''; // phpcs:ignore
	$f  = isset( $_POST['f'] ) ? (int) $_POST['f'] : 0; // phpcs:ignore
	$e  = isset( $_POST['e'] ) ? (string) $_POST['e'] : 'view'; // phpcs:ignore
	$ok = array( 'view', 'web', 'prihlaska', 'plan', 'nav', 'mapa', 'soc', 'more', 'tel', 'mail', 'roadshow', 'otazka', 'lead', 'klik' );
	if ( '' === $k || ! in_array( $e, $ok, true ) ) { wp_die( '', '', array( 'response' => 204 ) ); }
	$day = current_time( 'Y-m-d' );
	if ( 'klik' === $e ) {
		$cid = isset( $_POST['c'] ) ? preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) wp_unslash( $_POST['c'] ) ) ) : ''; // phpcs:ignore
		if ( '' !== $cid ) { knv_stats_inc( $day, substr( 'c:' . substr( $k, 0, 30 ) . ':' . substr( $cid, 0, 24 ), 0, 64 ), 'klik' ); }
		wp_die( '', '', array( 'response' => 204 ) );
	}
	if ( 'view' === $e ) {
		knv_stats_inc( $day, 'site', 'pv' );
		knv_stats_inc( $day, 'p:' . substr( $k, 0, 60 ), 'pv' );
		if ( knv_stats_first( $day, 'site' ) ) { knv_stats_inc( $day, 'site', 'uv' ); }
		if ( isset( $_POST['r'] ) ) { // phpcs:ignore
			$r = (string) $_POST['r']; // phpcs:ignore
			$src = '' === $r ? 'priamo' : ( preg_match( '/google\./', $r ) ? 'google' : ( preg_match( '/facebook|instagram|tiktok|youtube|linkedin|t\.co/', $r ) ? 'siete' : ( false !== strpos( $r, wp_parse_url( home_url(), PHP_URL_HOST ) ) ? '' : 'odkazy' ) ) );
			if ( $src ) { knv_stats_inc( $day, 'src', $src ); }
		}
	}
	if ( $f > 0 && 'fakulty' === get_post_type( $f ) ) {
		knv_stats_inc( $day, 'f:' . $f, $e );
		if ( 'view' === $e && knv_stats_first( $day, 'f' . $f ) ) { knv_stats_inc( $day, 'f:' . $f, 'uv' ); }
	}
	// Upratovanie denných hashov (raz denne).
	if ( get_option( 'knv_stats_purge' ) !== $day ) {
		global $wpdb;
		list( , $u ) = knv_stats_tables();
		$wpdb->query( $wpdb->prepare( "DELETE FROM $u WHERE day < %s", gmdate( 'Y-m-d', strtotime( $day . ' -2 days' ) ) ) ); // phpcs:ignore
		update_option( 'knv_stats_purge', $day, false );
	}
	wp_die( '', '', array( 'response' => 204 ) );
}
add_action( 'wp_ajax_knv_stat', 'knv_stats_hit' );
add_action( 'wp_ajax_nopriv_knv_stat', 'knv_stats_hit' );

/** Skript pre stránky nového webu – vkladá ho render.php. */
function knv_stats_js( $key, $fid ) {
	$cfg = wp_json_encode( array( 'u' => admin_url( 'admin-ajax.php' ), 'k' => $key, 'f' => (int) $fid ) );
	return '<script>(function(c){function s(e,id){try{var d=new FormData();d.append("action","knv_stat");d.append("k",c.k);d.append("f",c.f);d.append("e",e);if(id)d.append("c",id);if(e==="view")d.append("r",document.referrer||"");if(navigator.sendBeacon){navigator.sendBeacon(c.u,d);}else{fetch(c.u,{method:"POST",body:d,keepalive:true});}}catch(x){}}'
		. 's("view");document.addEventListener("click",function(ev){var a=ev.target.closest&&ev.target.closest("a");if(!a||(c.f&&a.closest("article")))return;var h=(a.getAttribute("href")||"").toLowerCase(),t=(a.textContent||"").toLowerCase(),id=a.getAttribute("data-knv")||"";if(!id&&/zastavka|roadshow\\/|vstupenk|registr/.test(h+" "+t))id="roadshow";if(id)s("klik",id);},true);if(!c.f)return;document.addEventListener("click",function(ev){var a=ev.target.closest&&ev.target.closest("article a, article button");if(!a)return;var h=(a.getAttribute("href")||"").toLowerCase(),t=(a.textContent||"").toLowerCase(),e="";'
		. 'if(/maps\\/dir/.test(h))e="nav";else if(/google\\.[a-z]+\\/maps|openstreetmap/.test(h))e="mapa";else if(/facebook|instagram|youtube|tiktok|linkedin/.test(h))e="soc";else if(/^tel:/.test(h))e="tel";else if(/^mailto:/.test(h))e="mail";else if(/viac info/.test(t))e="more";else if(/prihl/.test(t))e="prihlaska";else if(/plánu|planu|moj-plan/.test(t+h))e="plan";else if(/zastavka|lístok|listok|vstupenk/.test(h+t))e="roadshow";else if(/^https?:/.test(h)&&h.indexOf(location.host)<0)e="web";if(e)s(e);},true);})(' . $cfg . ');</script>';
}

/* ---------- Záujemcovia z profilov fakúlt ---------- */
function knv_lead_save() {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$rk  = 'knv_rl_' . md5( $ip );
	$n   = (int) get_transient( $rk );
	if ( $n >= 8 ) { wp_send_json_error( 'limit', 429 ); }
	set_transient( $rk, $n + 1, 10 * MINUTE_IN_SECONDS );
	$em = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore
	$f  = isset( $_POST['fak'] ) ? (int) $_POST['fak'] : 0; // phpcs:ignore
	$u  = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : ''; // phpcs:ignore
	if ( ! is_email( $em ) || ( $f && 'fakulty' !== get_post_type( $f ) ) || '3' !== get_option( 'knv_stats_db' ) ) { wp_send_json_error( 'bad', 400 ); }
	global $wpdb;
	$wpdb->insert( $wpdb->prefix . 'knv_leads', array( 'created' => current_time( 'mysql' ), 'email' => strtolower( $em ), 'fakulta' => $f, 'url' => substr( $u, 0, 255 ) ) );
	if ( $f ) { knv_stats_inc( current_time( 'Y-m-d' ), 'f:' . $f, 'lead' ); }
	wp_send_json_success();
}
add_action( 'wp_ajax_knv_lead', 'knv_lead_save' );
add_action( 'wp_ajax_nopriv_knv_lead', 'knv_lead_save' );

add_action( 'admin_post_knv_leads_csv', function () {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Bez oprávnenia' ); }
	check_admin_referer( 'knv_leads_csv' );
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT created, email, fakulta, url FROM {$wpdb->prefix}knv_leads ORDER BY created DESC", ARRAY_A ); // phpcs:ignore
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=zaujemcovia-fakulty-' . current_time( 'Y-m-d' ) . '.csv' );
	$o = fopen( 'php://output', 'w' );
	fwrite( $o, "\xEF\xBB\xBF" );
	fputcsv( $o, array( 'datum', 'email', 'fakulta', 'univerzita', 'stranka' ), ';' );
	foreach ( $rows as $r ) {
		$vs = $r['fakulta'] && function_exists( 'knv_fak_parent_vs' ) ? knv_fak_parent_vs( (int) $r['fakulta'] ) : 0;
		fputcsv( $o, array( $r['created'], $r['email'], $r['fakulta'] ? html_entity_decode( get_the_title( (int) $r['fakulta'] ) ) : '', $vs ? html_entity_decode( get_the_title( $vs ) ) : '', $r['url'] ), ';' );
	}
	exit;
} );

/* ---------- Admin: Nástroje → Štatistiky KNV ---------- */
add_action( 'admin_menu', function () {
	add_management_page( 'Štatistiky KNV', 'Štatistiky KNV', 'edit_pages', 'knv-stats', 'knv_stats_page' );
} );

function knv_stats_sum( $from, $obj_like, $ev = null ) {
	global $wpdb;
	list( $t ) = knv_stats_tables();
	$sql = "SELECT obj, ev, SUM(cnt) c FROM $t WHERE day >= %s AND obj LIKE %s" . ( $ev ? ' AND ev = %s' : '' ) . ' GROUP BY obj, ev';
	$args = $ev ? array( $from, $obj_like, $ev ) : array( $from, $obj_like );
	return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore
}

function knv_stats_page() {
	global $wpdb;
	list( $t ) = knv_stats_tables();
	$today = current_time( 'Y-m-d' );
	$d7  = gmdate( 'Y-m-d', strtotime( $today . ' -6 days' ) );
	$d30 = gmdate( 'Y-m-d', strtotime( $today . ' -29 days' ) );
	$site = function ( $from, $ev ) use ( $wpdb, $t ) { return (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(cnt) FROM $t WHERE day >= %s AND obj='site' AND ev=%s", $from, $ev ) ); }; // phpcs:ignore
	$n = function ( $x ) { return number_format_i18n( (int) $x ); };
	echo '<div class="wrap"><h1>Štatistiky KNV</h1><p>Merané bez cookies na stránkach nového webu. Prihlásení používatelia a roboty sa nepočítajú. Meranie beží od nahratia verzie 0.8.6.</p>';
	echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th></th><th>Dnes</th><th>7 dní</th><th>30 dní</th></tr></thead><tbody>';
	echo '<tr><td><strong>Návštevníci (unikátni za deň)</strong></td><td>' . $n( $site( $today, 'uv' ) ) . '</td><td>' . $n( $site( $d7, 'uv' ) ) . '</td><td>' . $n( $site( $d30, 'uv' ) ) . '</td></tr>';
	echo '<tr><td><strong>Zobrazenia stránok</strong></td><td>' . $n( $site( $today, 'pv' ) ) . '</td><td>' . $n( $site( $d7, 'pv' ) ) . '</td><td>' . $n( $site( $d30, 'pv' ) ) . '</td></tr></tbody></table>';

	// Zdroje návštev.
	$src = $wpdb->get_results( $wpdb->prepare( "SELECT ev, SUM(cnt) c FROM $t WHERE day >= %s AND obj='src' GROUP BY ev ORDER BY c DESC", $d30 ), ARRAY_A ); // phpcs:ignore
	if ( $src ) {
		echo '<h2>Odkiaľ prišli (30 dní)</h2><p>';
		$lab = array( 'google' => 'Google', 'siete' => 'Sociálne siete', 'odkazy' => 'Iné weby', 'priamo' => 'Priamo / e-mail' );
		foreach ( $src as $r ) { echo esc_html( isset( $lab[ $r['ev'] ] ) ? $lab[ $r['ev'] ] : $r['ev'] ) . ': <strong>' . $n( $r['c'] ) . '</strong> &nbsp; '; }
		echo '</p>';
	}

	// Po dňoch.
	$days = $wpdb->get_results( $wpdb->prepare( "SELECT day, SUM(CASE WHEN ev='uv' THEN cnt ELSE 0 END) uv, SUM(CASE WHEN ev='pv' THEN cnt ELSE 0 END) pv FROM $t WHERE obj='site' AND day >= %s GROUP BY day ORDER BY day DESC", gmdate( 'Y-m-d', strtotime( $today . ' -13 days' ) ) ), ARRAY_A ); // phpcs:ignore
	echo '<h2>Posledných 14 dní</h2><table class="widefat striped" style="max-width:720px"><thead><tr><th>Deň</th><th>Návštevníci</th><th>Zobrazenia</th></tr></thead><tbody>';
	foreach ( $days as $r ) { echo '<tr><td>' . esc_html( date_i18n( 'j. n. Y', strtotime( $r['day'] ) ) ) . '</td><td>' . $n( $r['uv'] ) . '</td><td>' . $n( $r['pv'] ) . '</td></tr>'; }
	if ( ! $days ) { echo '<tr><td colspan="3">Zatiaľ žiadne údaje.</td></tr>'; }
	echo '</tbody></table>';

	// Profily fakúlt.
	$rows = knv_stats_sum( $d30, 'f:%' );
	$fak = array();
	foreach ( $rows as $r ) { $fak[ (int) substr( $r['obj'], 2 ) ][ $r['ev'] ] = (int) $r['c']; }
	uasort( $fak, function ( $a, $b ) { return ( isset( $b['view'] ) ? $b['view'] : 0 ) - ( isset( $a['view'] ) ? $a['view'] : 0 ); } );
	$cols = array( 'lead' => 'Záujemcovia (e-mail)', 'uv' => 'Návštevníci', 'view' => 'Zobrazenia', 'web' => 'Web fakulty', 'prihlaska' => 'Prihláška', 'more' => 'Viac info', 'plan' => 'DOD do plánu', 'nav' => 'Navigovať', 'mapa' => 'Mapa', 'soc' => 'Siete', 'tel' => 'Telefón', 'mail' => 'E-mail', 'roadshow' => 'Roadshow' );
	echo '<h2>Profily fakúlt (30 dní)</h2><table class="widefat striped"><thead><tr><th>Fakulta</th>';
	foreach ( $cols as $c ) { echo '<th>' . esc_html( $c ) . '</th>'; }
	echo '</tr></thead><tbody>';
	foreach ( $fak as $id => $v ) {
		echo '<tr><td><a href="' . esc_url( get_permalink( $id ) ) . '" target="_blank">' . esc_html( get_the_title( $id ) ) . '</a></td>';
		foreach ( array_keys( $cols ) as $c ) { echo '<td>' . ( isset( $v[ $c ] ) ? $n( $v[ $c ] ) : '–' ) . '</td>'; }
		echo '</tr>';
	}
	if ( ! $fak ) { echo '<tr><td colspan="' . ( count( $cols ) + 1 ) . '">Zatiaľ žiadne údaje – profily sa začnú počítať po zverejnení.</td></tr>'; }
	echo '</tbody></table>';

	// Záujemcovia.
	$lt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}knv_leads" ); // phpcs:ignore
	echo '<h2>Záujemcovia z profilov fakúlt</h2><p>Spolu zachytených e-mailov: <strong>' . $n( $lt ) . '</strong>. E-maily idú aj do SmartEmailingu (zoznam newslettera); tu je navyše fakulta, o ktorú sa zaujímajú.</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=knv_leads_csv' ), 'knv_leads_csv' ) ) . '">Stiahnuť záujemcov (CSV)</a></p>';

	// Kliknutia na tlačidlá (pozvánky na výstavu a pod.).
	$kl = $wpdb->get_results( $wpdb->prepare( "SELECT obj, SUM(cnt) c FROM $t WHERE day >= %s AND obj LIKE 'c:%%' AND ev='klik' GROUP BY obj ORDER BY c DESC LIMIT 30", $d30 ), ARRAY_A ); // phpcs:ignore
	$lab = array( 'dod-pruh' => 'Žltý pruh „Registrácia na výstavu“', 'vysledky-pruh' => 'Pozvánka „Táto zastávka už prebehla“', 'dod-kalendar' => 'Pozvánka v kalendári DOD', 'dod-skola' => 'Pri škole v DOD: „Túto školu stretneš aj na výstave“', 'smer-pruh' => 'Pruh „Stretni tieto fakulty osobne“', 'costudovat-pruh' => 'Pruh „Opýtaj sa priamo fakúlt“', 'roadshow' => 'Odkaz na výstavu / vstupenku' );
	echo '<h2>Kliknutia na výstavu (30 dní)</h2><p>Koľkokrát ľudia klikli z danej stránky na pozvánku alebo odkaz na výstavu Kam na vysokú.</p><table class="widefat striped" style="max-width:720px"><thead><tr><th>Stránka</th><th>Tlačidlo</th><th>Kliknutia</th></tr></thead><tbody>';
	foreach ( $kl as $r ) {
		$pp = explode( ':', $r['obj'] );
		$id = isset( $pp[2] ) ? $pp[2] : '';
		echo '<tr><td>' . esc_html( isset( $pp[1] ) ? $pp[1] : '' ) . '</td><td>' . esc_html( isset( $lab[ $id ] ) ? $lab[ $id ] : $id ) . '</td><td>' . $n( $r['c'] ) . '</td></tr>';
	}
	if ( ! $kl ) { echo '<tr><td colspan="3">Zatiaľ žiadne kliknutia.</td></tr>'; }
	echo '</tbody></table>';

	// Najnavštevovanejšie stránky.
	$top = $wpdb->get_results( $wpdb->prepare( "SELECT obj, SUM(cnt) c FROM $t WHERE day >= %s AND obj LIKE 'p:%%' AND ev='pv' GROUP BY obj ORDER BY c DESC LIMIT 25", $d30 ), ARRAY_A ); // phpcs:ignore
	echo '<h2>Najnavštevovanejšie stránky (30 dní)</h2><table class="widefat striped" style="max-width:720px"><thead><tr><th>Stránka</th><th>Zobrazenia</th></tr></thead><tbody>';
	foreach ( $top as $r ) { echo '<tr><td>' . esc_html( substr( $r['obj'], 2 ) ) . '</td><td>' . $n( $r['c'] ) . '</td></tr>'; }
	if ( ! $top ) { echo '<tr><td colspan="2">Zatiaľ žiadne údaje.</td></tr>'; }
	echo '</tbody></table></div>';
}
