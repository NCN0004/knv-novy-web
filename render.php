<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$knv  = $GLOBALS['knv_current'];
$key  = $knv['key'];
$html = file_get_contents( knv_file( 'pages/' . $key . '.dc.html' ) );
$ns   = file_exists( knv_file( 'pages/' . $key . '.noscript.html' ) ) ? file_get_contents( knv_file( 'pages/' . $key . '.noscript.html' ) ) : '';
$live = knv_is_live();
$fix  = function ( $s ) use ( $live ) {
	$s = preg_replace_callback( '/href="([A-Za-z0-9-]+)\\.dc\\.html(#[^"]*)?"/', function ( $m ) {
		return 'href="' . esc_url( knv_link( $m[1] ) ) . ( isset( $m[2] ) ? $m[2] : '' ) . '"';
	}, $s );
	// Odkazy zapísané v skriptoch ako reťazce ('Smer-IT.dc.html', "Vysledky-Trencin.dc.html").
	$s = preg_replace_callback( '/(["\'])([A-Za-z0-9-]+)\\.dc\\.html(#[^"\']*)?\\1/', function ( $m ) {
		return $m[1] . esc_url_raw( knv_link( $m[2] ) ) . ( isset( $m[3] ) ? $m[3] : '' ) . $m[1];
	}, $s );
	// Odkazy na články (v šablónach sú ako https://kamnavysoku.sk/clanky/slug/).
	$s = preg_replace_callback( '#https://kamnavysoku\\.sk/clanky/([a-z0-9-]+)/#', function ( $m ) use ( $live ) {
		$u = home_url( '/clanky/' . $m[1] . '/' );
		return $live ? $u : $u . '?knv=1';
	}, $s );
	$s = preg_replace_callback( '/\\{\\{KNV:([A-Za-z0-9-]+)\\}\\}/', function ( $m ) { return esc_url( knv_link( $m[1] ) ); }, $s );
	return str_replace( '{{KNV_ASSETS}}', esc_url( KNV_URL . 'assets' ), $s );
};
if ( ! empty( $knv['fak'] ) ) {
	$fakd   = knv_fak_data( (int) $knv['id'] );
	$fakart = knv_fak_html( $fakd, ! empty( $knv['fakprev'] ) );
	$html   = str_replace( '<!--KNV_FAKULTA-->', $fakart, $html );
	$ns     = $fakart;
	$knv['fakd'] = $fakd;
}
$html = $fix( $html );
$ns   = $fix( $ns );
$ver  = KNV_VERSION;
$html = str_replace( '<script src="./support.js"></script>', '<script src="' . esc_url( KNV_URL . 'assets/support.js?v=' . $ver ) . '"></script>', $html );

$stops = array(
	'Zastavka-Prievidza'  => array( 'code' => '26', 'date' => array( 2026, 11, 1 ) ),
	'Zastavka-Bratislava' => array( 'code' => '29', 'date' => array( 2027, 0, 26 ) ),
);
$cd  = isset( $stops[ $key ] ) ? $stops[ $key ]['date'] : array( 2026, 11, 1 );
$cfg = array(
	'page'       => $key,
	'fak'        => ! empty( $knv['fak'] ) ? (int) $knv['id'] : 0,
	'ajax'       => admin_url( 'admin-ajax.php' ),
	'zastavka'   => knv_link( 'Zastavka-Prievidza' ),
	'vystavovat' => knv_link( 'Chcem-vystavovat' ),
	'regForm'    => in_array( $key, array( 'Zastavka-Prievidza', 'Zastavka-Bratislava', 'Poradcovia' ), true ),
	'stopCode'   => isset( $stops[ $key ] ) ? $stops[ $key ]['code'] : '26',
);

$title_tag = '';
if ( ! empty( $knv['post'] ) ) {
	$pid  = (int) $knv['id'];
	$post = get_post( $pid );
	setup_postdata( $post );
	$raw  = apply_filters( 'the_content', $post->post_content );
	$clean = wp_kses( $raw, array(
		'p' => array(), 'br' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(),
		'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'blockquote' => array(), 'figure' => array(), 'figcaption' => array(),
		'a' => array( 'href' => true, 'target' => true, 'rel' => true ),
		'img' => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'loading' => true ),
		'iframe' => array( 'src' => true, 'width' => true, 'height' => true, 'allow' => true, 'allowfullscreen' => true, 'title' => true ),
		'table' => array(), 'thead' => array(), 'tbody' => array(), 'tr' => array(), 'th' => array(), 'td' => array(),
	) );
	$clean = preg_replace( '#<(p|h2|h3|h4|li|strong|em)>\\s*(&nbsp;)?\\s*</\\1>#u', '', $clean );
	$clean = preg_replace( '#<iframe([^>]*)src="(?!https://(www\\.)?(youtube\\.com|youtube-nocookie\\.com|player\\.vimeo\\.com)/)[^"]*"[^>]*></iframe>#', '', $clean );
	// GDPR: YouTube bez sledovacích cookies.
	$clean = str_replace( array( 'https://www.youtube.com/embed/', 'https://youtube.com/embed/' ), 'https://www.youtube-nocookie.com/embed/', $clean );
	$cats  = get_the_category( $pid );
	$words = str_word_count( wp_strip_all_tags( $clean ) );
	$img   = get_the_post_thumbnail_url( $pid, 'large' );
	$cfg['post'] = array(
		'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
		'lead'  => has_excerpt( $pid ) ? wp_strip_all_tags( get_the_excerpt( $pid ) ) : '',
		'cat'   => $cats ? $cats[0]->name : 'Články',
		'meta'  => get_the_date( 'j. n. Y', $pid ) . ' · ' . max( 1, (int) round( $words / 200 ) ) . ' min čítania',
		'img'   => $img ? $img : '',
		'html'  => $clean,
		'url'   => get_permalink( $pid ),
	);
	wp_reset_postdata();
	$ns        = '<article><h1>' . esc_html( $cfg['post']['title'] ) . '</h1>' . $clean . '</article>';
}

$is_draft = ! empty( $knv['fak'] ) ? ! empty( $knv['fakprev'] ) : ( ! empty( $knv['nf'] ) ? false : ( empty( $knv['post'] ) ? ( 'publish' !== get_post_status( $knv['id'] ) ) : ! $live ) );
$cfg['ga']      = knv_ga_id();
$cfg['pixel']   = knv_pixel_id();
$cfg['gtm']     = knv_gtm_id();
$cfg['ga2']     = knv_ga2_id();
$cfg['track']   = ! $is_draft || isset( $_GET['knvtrack'] ); // phpcs:ignore WordPress.Security.NonceVerification
$cfg['cookies'] = knv_link( 'Cookies' );
// Odpočet v noscript verzii zastaráva – vyhľadávačom ho nedávame.
$ns   = preg_replace( '/už o \d+ dní ·?|\d[\d\s]*dní do zastávky presne[^<]*/u', '', $ns );
$html = preg_replace( '#<title>[^<]*</title>#', '', $html, 1 );
// GDPR: písma z vlastného servera namiesto Google Fonts.
$html = preg_replace( '#<link[^>]+fonts\.(googleapis|gstatic)\.com[^>]*>\s*#', '', $html );

$head  = '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
$head .= '<link rel="stylesheet" href="' . esc_url( KNV_URL . 'assets/fonts/fonts.css?v=' . KNV_VERSION ) . '">';
$head .= '<link rel="stylesheet" href="' . esc_url( knv_asset_url( 'assets/consent.css' ) ) . '">';
$head .= knv_seo_head( $knv, $is_draft, isset( $cfg['post'] ) ? $cfg['post'] : null );
$head .= '<link rel="icon" href="' . esc_url( KNV_URL . 'assets/logo-ikona.png' ) . '">';
$head .= '<link rel="stylesheet" href="' . esc_url( ( file_exists( knv_override_dir() . 'assets/knv.css' ) ? content_url( 'uploads/knv-novy-web/assets/knv.css' ) : KNV_URL . 'assets/knv.css' ) . '?v=' . $ver . '-' . @filemtime( knv_file( 'assets/knv.css' ) ) ) . '">';
$foot  = '<noscript>' . $ns . '</noscript>';
$foot .= '<script>window.KNV=' . wp_json_encode( $cfg ) . ';</script><script src="' . esc_url( knv_asset_url( 'assets/consent.js' ) ) . '"></script><script src="' . esc_url( ( file_exists( knv_override_dir() . 'assets/knv.js' ) ? content_url( 'uploads/knv-novy-web/assets/knv.js' ) : KNV_URL . 'assets/knv.js' ) . '?v=' . $ver . '-' . @filemtime( knv_file( 'assets/knv.js' ) ) ) . '"></script>';
$foot .= '<script>(function(){var t=new Date(' . (int) $cd[0] . ',' . (int) $cd[1] . ',' . (int) $cd[2] . ');var d=Math.max(0,Math.ceil((t-new Date())/864e5));function f(){var w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);var n;while(n=w.nextNode()){if(/už o \\d+ dní/.test(n.nodeValue)&&n.nodeValue.indexOf("už o "+d+" dní")<0)n.nodeValue=n.nodeValue.replace(/už o \\d+ dní/g,"už o "+d+" dní");}}setTimeout(f,900);})();</script>';
if ( function_exists( 'knv_stats_js' ) ) {
	$skey = ! empty( $knv['fak'] ) ? 'fakulta-' . (int) $knv['id'] : ( ! empty( $knv['post'] ) ? 'clanok-' . get_post_field( 'post_name', (int) $knv['id'] ) : $key );
	$foot .= knv_stats_js( $skey, ! empty( $knv['fak'] ) ? (int) $knv['id'] : 0 );
}
if ( $is_draft ) {
	$foot .= '<div style="position:fixed;left:16px;bottom:16px;z-index:99999;background:#393536;color:#fff;font:600 13px/1.3 system-ui,sans-serif;padding:10px 14px;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,.25)">Náhľad nového webu – koncept<br><a style="color:#CFE7CB" href="' . esc_url( admin_url( 'tools.php?page=knv-novy-web' ) ) . '">Prehľad stránok</a></div>';
}
$html = preg_replace( '/<head>/', '<head>' . $head, $html, 1 );
$html = preg_replace( '/<\\/body>/', $foot . '</body>', $html, 1 );
header( 'Content-Type: text/html; charset=utf-8' );
echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
