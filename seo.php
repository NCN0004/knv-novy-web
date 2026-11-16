<?php
/**
 * SEO, GEO a meranie pre nový web Kam na vysokú.
 * Meta značky, Open Graph, štruktúrované dáta (JSON-LD), llms.txt, presmerovania starých adries,
 * Google Analytics 4 a Meta pixel (spúšťajú sa až po súhlase s cookies – lišta je v assets/knv.js).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'KNV_GA_DEFAULT', 'G-B0907XCLTP' );
define( 'KNV_PIXEL_DEFAULT', '902723201904765' );
define( 'KNV_GTM_DEFAULT', 'GTM-PJBJZ8B6' );
define( 'KNV_GA2_DEFAULT', 'G-9NG4LNVN5M' );
define( 'KNV_GSC_VERIFY', 'jV_ZYWMGTGLqxw2bP74libahSSYaIttqhpvdY5Dubn0' );
define( 'KNV_GSC_VERIFY2', 'y6ZLPCuOIiFxMi18_coCkjviwBUU5C0Z69WX8tU_JwA' );
define( 'KNV_FB_VERIFY', '6m8hhu1u8kdt83lz61w7bcwo1is5qn' );

function knv_ga_id() { return trim( (string) get_option( 'knv_ga_id', KNV_GA_DEFAULT ) ); }
function knv_pixel_id() { return trim( (string) get_option( 'knv_pixel_id', KNV_PIXEL_DEFAULT ) ); }
function knv_ga2_id() { return trim( (string) get_option( 'knv_ga2_id', KNV_GA2_DEFAULT ) ); }
function knv_gtm_id() { return trim( (string) get_option( 'knv_gtm_id', KNV_GTM_DEFAULT ) ); }

function knv_seo_all() {
	static $s = null;
	if ( null === $s ) {
		$f = knv_file( 'pages/seo.json' );
		$s = file_exists( $f ) ? json_decode( file_get_contents( $f ), true ) : array();
		if ( ! is_array( $s ) ) { $s = array(); }
	}
	return $s;
}
function knv_seo( $key ) {
	$s = knv_seo_all();
	return isset( $s[ $key ] ) ? $s[ $key ] : array();
}

/** Zastávky Roadshow – údaje pre Event schému. */
function knv_events() {
	return array(
		'Zastavka-Prievidza'  => array(
			'name'   => 'Kam na vysokú – výstava vysokých škôl Prievidza',
			'start'  => '2026-12-01T09:00:00+01:00',
			'end'    => '2026-12-01T12:30:00+01:00',
			'place'  => 'Hotel Magura',
			'street' => 'Námestie Jozefa Cígera Hronského 1',
			'zip'    => '971 01',
			'city'   => 'Prievidza',
		),
		'Zastavka-Bratislava' => array(
			'name'   => 'Kam na vysokú – výstava vysokých škôl Bratislava',
			'start'  => '2027-01-26T09:00:00+01:00',
			'end'    => '2027-01-26T12:30:00+01:00',
			'place'  => 'Športová hala Domu športu',
			'street' => 'Junácka 8',
			'zip'    => '832 80',
			'city'   => 'Bratislava',
		),
	);
}

/** Budúca (kanonická) adresa stránky – aj keď je ešte koncept. */
function knv_canonical( $knv ) {
	if ( ! empty( $knv['post'] ) ) { return get_permalink( (int) $knv['id'] ); }
	$key = $knv['key'];
	if ( 'Main' === $key ) { return home_url( '/' ); }
	$id = (int) $knv['id'];
	if ( $id && 'publish' === get_post_status( $id ) ) { return get_permalink( $id ); }
	$m    = knv_manifest();
	$path = $m[ $key ]['slug'];
	if ( ! empty( $m[ $key ]['parent'] ) && isset( $m[ $m[ $key ]['parent'] ] ) ) {
		$path = $m[ $m[ $key ]['parent'] ]['slug'] . '/' . $path;
	}
	return home_url( '/' . $path . '/' );
}

/** Počet nedoplnených miest v texte stránky ([doplniť], [čas] …). */
function knv_placeholders( $key ) {
	$f = knv_file( 'pages/' . $key . '.noscript.html' );
	if ( ! file_exists( $f ) ) { return 0; }
	$t = wp_strip_all_tags( file_get_contents( $f ) );
	return preg_match_all( '/\[[^\]\n]{1,90}\]/u', $t );
}

/** Smie stránka ísť do vyhľadávania? (publikovaná, bez noindex, bez nedoplneného obsahu) */
function knv_indexable( $key ) {
	$s = knv_seo( $key );
	if ( ! empty( $s['noindex'] ) ) { return false; }
	// Kľúčové stránky idú do Googlu vždy (nedoplnené miesta sa len hlásia v administrácii).
	$core = array( 'Main', 'Zastavka-Prievidza', 'Zastavka-Bratislava', 'Kontakt', 'Chcem-vystavovat', 'DOD', 'Co-studovat', 'Rady', 'Poradcovia', 'Casopis', 'Rodicia', 'Vystavovatelia', 'Zahranicie', 'Alternativy', 'Stipendia', 'Moj-plan' );
	if ( in_array( $key, $core, true ) || 0 === strpos( $key, 'Smer-' ) ) { return true; }
	return 0 === knv_placeholders( $key );
}

function knv_org_node() {
	$home = home_url( '/' );
	return array(
		'@type'              => 'Organization',
		'@id'                => $home . '#organization',
		'name'               => 'Kam na vysokú',
		'alternateName'      => array( 'Roadshow Kam na vysokú', 'Kam po strednej' ),
		'url'                => $home,
		'logo'               => array( '@type' => 'ImageObject', 'url' => KNV_URL . 'assets/logo-ikona.png' ),
		'description'        => 'Kam na vysokú je putovná výstava vysokých škôl (Roadshow) v mestách Slovenska a časopis Kam po strednej pre študentov stredných škôl, rodičov a výchovných poradcov. Vstup na výstavu je zadarmo.',
		'email'              => 'roadshow@kamnavysoku.sk',
		'areaServed'         => array( '@type' => 'Country', 'name' => 'Slovensko' ),
		'sameAs'             => array( 'https://www.facebook.com/kamnavysoku/', 'https://www.instagram.com/kamnavysoku/', 'https://www.youtube.com/@narodnekariernecentrum533' ),
		'parentOrganization' => array( '@type' => 'Organization', 'name' => 'Národné kariérne centrum SK, s. r. o.', 'url' => 'https://nakac.sk/' ),
	);
}

/** Celá SEO hlavička stránky. */
function knv_seo_head( $knv, $is_draft, $post_cfg = null ) {
	if ( ! empty( $knv['fak'] ) && ! empty( $knv['fakd'] ) ) {
		$fd   = $knv['fakd'];
		$t    = $fd['title'] . ( $fd['uni'] ? ' – ' . $fd['uni'] : '' ) . ' | Kam na vysokú';
		$desc = $fd['title'] . ': kontakty, deň otvorených dverí, prihláška a prijímačky, poplatky za internát a jedlo. Stretni fakultu na Roadshow Kam na vysokú.';
		$u    = get_permalink( (int) $fd['id'] );
		$h    = '<title>' . esc_html( $t ) . '</title><meta name="description" content="' . esc_attr( $desc ) . '">';
		$h   .= '<meta name="robots" content="' . ( $is_draft ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' ) . '">';
		$h   .= '<link rel="canonical" href="' . esc_url( $u ) . '"><meta name="theme-color" content="#2B7F29">';
		$h   .= '<meta property="og:type" content="website"><meta property="og:title" content="' . esc_attr( $t ) . '"><meta property="og:description" content="' . esc_attr( $desc ) . '"><meta property="og:url" content="' . esc_url( $u ) . '">' . ( $fd['logo'] ? '<meta property="og:image" content="' . esc_url( $fd['logo'] ) . '">' : '' );
		$h   .= '<meta name="google-site-verification" content="' . esc_attr( KNV_GSC_VERIFY ) . '"><meta name="google-site-verification" content="' . esc_attr( KNV_GSC_VERIFY2 ) . '">';
		$h   .= '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'CollegeOrUniversity', 'name' => $fd['title'], 'url' => $fd['web'] ? $fd['web'] : $u, 'address' => $fd['adresa'], 'telephone' => $fd['tel'], 'parentOrganization' => $fd['uni'] ? array( '@type' => 'CollegeOrUniversity', 'name' => $fd['uni'] ) : null ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
		return $h;
	}
	if ( ! empty( $knv['nf'] ) ) {
		if ( ! empty( $knv['demo'] ) ) {
			return '<title>' . ( 'Ukazka-maxi' === $knv['key'] ? 'Ukážkový profil fakulty MAXI' : 'Ukážkový profil fakulty' ) . ' – Kam na vysokú</title><meta name="robots" content="noindex, nofollow"><meta name="theme-color" content="#2B7F29">';
		}
		return '<title>Stránka sa nenašla – Kam na vysokú</title><meta name="robots" content="noindex, follow"><meta name="theme-color" content="#2B7F29">';
	}
	$key   = $knv['key'];
	$home  = home_url( '/' );
	$url   = knv_canonical( $knv );
	$s     = knv_seo( $key );
	$title = isset( $s['t'] ) ? $s['t'] : get_the_title( $knv['id'] ) . ' – Kam na vysokú';
	$desc  = isset( $s['d'] ) ? $s['d'] : '';
	$img   = KNV_URL . 'assets/og/' . ( file_exists( KNV_DIR . 'assets/og/' . $key . '.jpg' ) ? $key : 'default' ) . '.jpg';
	$type  = 'website';
	$index = ! $is_draft && knv_indexable( $key );

	if ( $post_cfg ) {
		$pid   = (int) $knv['id'];
		$rt    = get_post_meta( $pid, 'rank_math_title', true );
		$rd    = get_post_meta( $pid, 'rank_math_description', true );
		$title = $rt ? wp_strip_all_tags( str_replace( array( '%title%', '%sep%', '%sitename%' ), array( $post_cfg['title'], '–', 'Kam na vysokú' ), $rt ) ) : $post_cfg['title'] . ' – Kam na vysokú';
		$first = '';
		if ( preg_match_all( '#<p>(.*?)</p>#s', $post_cfg['html'], $ps ) ) {
			foreach ( $ps[1] as $p ) { $p = trim( wp_strip_all_tags( $p ) ); if ( mb_strlen( $p ) >= 80 ) { $first = $p; break; } }
		}
		$desc  = $rd ? $rd : ( $post_cfg['lead'] ? $post_cfg['lead'] : ( $first ? $first : wp_strip_all_tags( $post_cfg['html'] ) ) );
		if ( mb_strlen( $desc ) > 158 ) { $desc = rtrim( mb_substr( $desc, 0, 155 ), " ,.;:–-" ) . '…'; }
		if ( $post_cfg['img'] ) { $img = $post_cfg['img']; }
		$type  = 'article';
		$index = ! $is_draft;
	}
	$desc = trim( preg_replace( '/\s+/u', ' ', $desc ) );

	$h  = '<title>' . esc_html( $title ) . '</title>';
	$h .= '<meta name="description" content="' . esc_attr( $desc ) . '">';
	if ( ! empty( $s['k'] ) ) { $h .= '<meta name="keywords" content="' . esc_attr( implode( ', ', $s['k'] ) ) . '">'; }
	$h .= '<meta name="robots" content="' . ( $index ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' : 'noindex, follow' ) . '">';
	$h .= '<link rel="canonical" href="' . esc_url( $url ) . '">';
	$h .= '<meta name="theme-color" content="#2B7F29"><link rel="apple-touch-icon" href="' . esc_url( KNV_URL . 'assets/logo-ikona.png' ) . '">';
	$h .= '<meta name="google-site-verification" content="' . esc_attr( KNV_GSC_VERIFY ) . '">';
	$h .= '<meta name="google-site-verification" content="' . esc_attr( KNV_GSC_VERIFY2 ) . '">';
	$h .= '<meta name="facebook-domain-verification" content="' . esc_attr( KNV_FB_VERIFY ) . '">';
	$og = array(
		'og:locale' => 'sk_SK', 'og:type' => $type, 'og:site_name' => 'Kam na vysokú', 'og:title' => $title,
		'og:description' => $desc, 'og:url' => $url, 'og:image' => $img, 'og:image:width' => '1200', 'og:image:height' => '630',
		'og:image:alt' => $title,
	);
	foreach ( $og as $p => $v ) { $h .= '<meta property="' . $p . '" content="' . esc_attr( $v ) . '">'; }
	$h .= '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="' . esc_attr( $title ) . '"><meta name="twitter:description" content="' . esc_attr( $desc ) . '"><meta name="twitter:image" content="' . esc_url( $img ) . '">';

	// --- JSON-LD ---
	$g   = array( knv_org_node() );
	$g[] = array( '@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => 'Kam na vysokú', 'inLanguage' => 'sk-SK', 'publisher' => array( '@id' => $home . '#organization' ) );
	$ptype = 'WebPage';
	if ( in_array( $key, array( 'Rady', 'Vystavovatelia', 'Co-studovat', 'DOD' ), true ) ) { $ptype = 'CollectionPage'; }
	if ( 'Kontakt' === $key ) { $ptype = 'ContactPage'; }
	if ( ! empty( $s['faq'] ) ) { $ptype = array( 'WebPage', 'FAQPage' ); }
	$page = array(
		'@type' => $ptype, '@id' => $url . '#webpage', 'url' => $url, 'name' => $title, 'description' => $desc, 'inLanguage' => 'sk-SK',
		'isPartOf' => array( '@id' => $home . '#website' ), 'about' => array( '@id' => $home . '#organization' ),
		'primaryImageOfPage' => array( '@type' => 'ImageObject', 'url' => $img ), 'breadcrumb' => array( '@id' => $url . '#breadcrumb' ),
	);
	if ( ! empty( $s['faq'] ) ) {
		$page['mainEntity'] = array_map( function ( $f ) {
			return array( '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ) );
		}, $s['faq'] );
	}
	$g[] = $page;

	// Omrvinky
	$crumbs = array( array( 'Kam na vysokú', $home ) );
	$m      = knv_manifest();
	if ( $post_cfg ) {
		$crumbs[] = array( 'Rady', knv_canonical( array( 'key' => 'Rady', 'id' => 0 ) ) );
		$crumbs[] = array( $post_cfg['title'], $url );
	} elseif ( 'Main' !== $key ) {
		$par = isset( $s['crumb'] ) ? $s['crumb'] : ( isset( $m[ $key ]['parent'] ) ? $m[ $key ]['parent'] : null );
		if ( $par && isset( $m[ $par ] ) ) { $crumbs[] = array( preg_replace( '/ – .*/u', '', $m[ $par ]['title'] ), knv_canonical( array( 'key' => $par, 'id' => 0 ) ) ); }
		$crumbs[] = array( preg_replace( '/ – (Roadshow|aká).*/u', '', $m[ $key ]['title'] ), $url );
	}
	$items = array();
	foreach ( $crumbs as $i => $c ) { $items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] ); }
	$g[] = array( '@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $items );

	// Zastávka Roadshow = udalosť
	$ev = knv_events();
	if ( isset( $ev[ $key ] ) ) {
		$e   = $ev[ $key ];
		$g[] = array(
			'@type' => 'EducationEvent', '@id' => $url . '#event', 'name' => $e['name'], 'description' => $desc,
			'startDate' => $e['start'], 'endDate' => $e['end'], 'eventStatus' => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode', 'isAccessibleForFree' => true, 'inLanguage' => 'sk',
			'image' => array( $img ), 'url' => $url,
			'location' => array( '@type' => 'Place', 'name' => $e['place'], 'address' => array( '@type' => 'PostalAddress', 'streetAddress' => $e['street'], 'postalCode' => $e['zip'], 'addressLocality' => $e['city'], 'addressCountry' => 'SK' ) ),
			'offers' => array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/InStock', 'url' => $url, 'validFrom' => '2026-10-01T00:00:00+02:00' ),
			'organizer' => array( '@id' => $home . '#organization' ),
			'audience' => array( '@type' => 'EducationalAudience', 'educationalRole' => 'student', 'audienceType' => 'študenti 3. a 4. ročníka stredných škôl, rodičia a výchovní poradcovia' ),
			'superEvent' => array( '@type' => 'EventSeries', 'name' => 'Roadshow Kam na vysokú 2026/27', 'url' => $home ),
		);
	}
	if ( $post_cfg ) {
		$pid = (int) $knv['id'];
		$g[] = array(
			'@type' => 'Article', '@id' => $url . '#article', 'headline' => $post_cfg['title'], 'description' => $desc,
			'image' => array( $img ), 'datePublished' => get_the_date( 'c', $pid ), 'dateModified' => get_the_modified_date( 'c', $pid ),
			'author' => array( '@id' => $home . '#organization' ), 'publisher' => array( '@id' => $home . '#organization' ),
			'mainEntityOfPage' => array( '@id' => $url . '#webpage' ), 'articleSection' => $post_cfg['cat'], 'inLanguage' => 'sk-SK',
		);
	}
	$h .= '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $g ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
	return $h;
}

/** Presmerovanie starých adries po spustení nového webu (301). */
add_action( 'template_redirect', function () {
	if ( ! knv_is_live() ) { return; }
	$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore
	$map  = array(
		'nitra'                                   => 'Main',
		'vystava-vysokych-skol-nitra'             => 'Main',
		'dni-otvorenych-dveri-na-vysokych-skolach' => 'DOD',
		'dni-otvorenych-dveri-test'               => 'DOD',
		'domov'                                   => 'Main',
	);
	if ( ! isset( $map[ $path ] ) ) { return; }
	$id = get_queried_object_id();
	if ( is_404() || ! $id || ! get_post_meta( $id, '_knv_key', true ) || 'domov' === $path ) {
		wp_safe_redirect( 'Main' === $map[ $path ] ? home_url( '/' ) : knv_link( $map[ $path ] ), 301 );
		exit;
	}
}, 1 );

/** Nedokončené stránky (noindex) nepatria do sitemap Rank Math. */
add_filter( 'rank_math/sitemap/entry', function ( $url, $type, $object ) {
	if ( 'post' === $type && $object instanceof WP_Post && 'page' === $object->post_type ) {
		$key = get_post_meta( $object->ID, '_knv_key', true );
		if ( $key && ! knv_indexable( $key ) ) { return false; }
	}
	return $url;
}, 10, 3 );

/** /llms.txt – stručný popis webu pre AI vyhľadávače (GEO). Po spustení verejne, dovtedy len pre administrátora s ?knv=1. */
add_action( 'init', function () {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
	if ( '/llms.txt' !== strtok( $uri, '?' ) ) { return; }
	if ( ! knv_is_live() && ! ( isset( $_GET['knv'] ) && is_user_logged_in() && current_user_can( 'edit_pages' ) ) ) { return; } // phpcs:ignore
	$s   = knv_seo_all();
	$m   = knv_manifest();
	$ev  = knv_events();
	$ln  = function ( $k ) use ( $s, $m ) {
		if ( ! isset( $m[ $k ] ) || ! knv_indexable( $k ) ) { return ''; }
		$ids = (array) get_option( 'knv_page_ids', array() );
		$u   = knv_canonical( array( 'key' => $k, 'id' => isset( $ids[ $k ] ) ? (int) $ids[ $k ] : 0 ) );
		return '- [' . preg_replace( '/ \| Kam na vysokú$/u', '', $s[ $k ]['t'] ) . '](' . $u . '): ' . $s[ $k ]['d'] . "\n";
	};
	$o  = "# Kam na vysokú\n\n> " . knv_org_node()['description'] . " Organizátor: Národné kariérne centrum SK, s. r. o. (nakac.sk). Kontakt: roadshow@kamnavysoku.sk.\n\n";
	$o .= "## Roadshow 2026/27 – najbližšie zastávky\n";
	foreach ( $ev as $k => $e ) {
		$o .= '- ' . $e['city'] . ': ' . wp_date( 'j. n. Y', strtotime( $e['start'] ) ) . ', 9:00 – 12:30, ' . $e['place'] . ', ' . $e['street'] . ', ' . $e['city'] . '. Vstup zadarmo, pre 3. a 4. ročník SŠ. Registrácia: ' . knv_canonical( array( 'key' => $k, 'id' => 0 ) ) . "\n";
	}
	$groups = array(
		'Pre študentov'              => array( 'Main', 'Co-studovat', 'DOD', 'Zahranicie', 'Alternativy', 'Mesta', 'Kalkulacka', 'Stipendia', 'Slovnik', 'Moj-plan', 'Kviz', 'Roadshow-pas', 'Rady' ),
		'Smery štúdia'               => array_values( array_filter( array_keys( $m ), function ( $k ) { return 0 === strpos( $k, 'Smer-' ); } ) ),
		'Pre školy, rodičov a vysoké školy' => array( 'Poradcovia', 'Rodicia', 'Chcem-vystavovat', 'Vystavovatelia', 'Casopis', 'Kontakt' ),
	);
	foreach ( $groups as $name => $keys ) {
		$part = '';
		foreach ( $keys as $k ) { $part .= $ln( $k ); }
		if ( $part ) { $o .= "\n## " . $name . "\n" . $part; }
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo $o; // phpcs:ignore
	exit;
} );

/** Profily vysokých škôl, fakúlt, DOD a videá patria do sitemap (Rank Math ich inak vynecháva). */
add_filter( 'rank_math/sitemap/exclude_post_type', function ( $exclude, $type ) {
	return in_array( $type, array( 'vs', 'fakulty', 'tribe_events', 'videa' ), true ) ? false : $exclude;
}, 20, 2 );

/** URL súboru z pluginu (alebo jeho prepisu v uploads) s verziou proti cache. */
function knv_asset_url( $rel ) {
	$o   = knv_override_dir() . $rel;
	$url = file_exists( $o ) ? content_url( 'uploads/knv-novy-web/' . $rel ) : KNV_URL . $rel;
	return $url . '?v=' . KNV_VERSION . '-' . @filemtime( knv_file( $rel ) );
}

/* ---------- Starý web: lišta cookies a blokovanie meraní bez súhlasu (GDPR) ---------- */
function knv_consent_cookie() {
	if ( empty( $_COOKIE['knv_consent'] ) ) { return array(); }
	$c = json_decode( wp_unslash( $_COOKIE['knv_consent'] ), true ); // phpcs:ignore
	return is_array( $c ) ? $c : array();
}
function knv_is_knv_request() { return ! empty( $GLOBALS['knv_current'] ); }

// PixelYourSite (Meta pixel) – len po súhlase s marketingovými cookies.
foreach ( array( 'pys_disable_by_gdpr', 'pys_disable_facebook_by_gdpr' ) as $knv_f ) {
	add_filter( $knv_f, function ( $disabled ) {
		$c = knv_consent_cookie();
		return empty( $c['m'] ) ? true : $disabled;
	}, 99 );
}
add_filter( 'pys_disable_google_analytics_by_gdpr', function ( $disabled ) {
	$c = knv_consent_cookie();
	return empty( $c['s'] ) ? true : $disabled;
}, 99 );

add_action( 'wp_head', function () {
	if ( is_admin() || knv_is_knv_request() ) { return; }
	echo '<link rel="stylesheet" href="' . esc_url( knv_asset_url( 'assets/consent.css' ) ) . '">';
}, 1 );
add_action( 'wp_footer', function () {
	if ( is_admin() || knv_is_knv_request() ) { return; }
	$cfg = array( 'old' => true, 'track' => true, 'ga' => knv_ga_id(), 'gtm' => knv_gtm_id(), 'pixel' => '', 'page' => 'old', 'cookies' => home_url( '/cookies/' ) );
	echo '<script>window.KNV_CC=' . wp_json_encode( $cfg ) . ';</script><script src="' . esc_url( knv_asset_url( 'assets/consent.js' ) ) . '"></script>';
}, 99 );
// Poistka: bez marketingového súhlasu sa skript PixelYourSite vôbec nenačíta.
add_action( 'wp_enqueue_scripts', function () {
	if ( knv_is_knv_request() ) { return; }
	$c = knv_consent_cookie();
	if ( empty( $c['m'] ) ) { wp_dequeue_script( 'pys' ); }
}, 9999 );

/* ---------- Doplnková sitemap: profily VŠ, fakulty, DOD, videá ---------- */
function knv_extra_types() { return array( 'vs', 'fakulty', 'tribe_events', 'videa' ); }
/** Staré stránky v starom dizajne (DOD termíny, profily VŠ a fakúlt, videá) sú od 12. 11. 2026 archivované – obsah ostáva v databáze, návštevník je presmerovaný. Vrátiť: return false. */
function knv_archive_old() { return (bool) apply_filters( 'knv_archive_old', true ); }
add_action( 'init', function () {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
	if ( '/knv-sitemap.xml' !== strtok( $uri, '?' ) ) { return; }
	// Staré stránky sú archivované – doplnková sitemap už nemá čo ponúknuť, Google dostane 410 (natrvalo preč) namiesto prázdneho súboru.
	if ( knv_archive_old() ) { status_header( 410 ); header( 'X-Robots-Tag: noindex' ); exit; }
	$types = array_values( array_filter( knv_extra_types(), 'post_type_exists' ) );
	$posts = $types ? get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'numberposts' => 2000, 'orderby' => 'modified', 'order' => 'DESC', 'suppress_filters' => false ) ) : array();
	header( 'Content-Type: application/xml; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, follow' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( $posts as $p ) {
		$rb = (array) get_post_meta( $p->ID, 'rank_math_robots', true );
		if ( in_array( 'noindex', $rb, true ) ) { continue; }
		echo '<url><loc>' . esc_url( get_permalink( $p ) ) . '</loc><lastmod>' . esc_html( get_post_modified_time( 'c', true, $p ) ) . '</lastmod></url>' . "\n";
	}
	echo '</urlset>';
	exit;
}, 999 );
// Kategórie článkov (napr. /kategorie/ostatne/) sa presmerúvajú – nepatria do sitemap.
add_filter( 'rank_math/sitemap/exclude_taxonomy', function ( $exclude, $type ) {
	return ( 'category' === $type ) ? true : $exclude;
}, 10, 2 );
// Zaradiť do indexu Rank Math aj do robots.txt.
add_filter( 'rank_math/sitemap/index', function ( $xml ) {
	if ( knv_archive_old() ) { return $xml; }
	return $xml . '<sitemap><loc>' . esc_url( home_url( '/knv-sitemap.xml' ) ) . '</loc><lastmod>' . esc_html( gmdate( 'c' ) ) . '</lastmod></sitemap>';
} );
add_filter( 'robots_txt', function ( $txt ) {
	if ( knv_archive_old() ) { return $txt; }
	return rtrim( $txt ) . "\nSitemap: " . home_url( '/knv-sitemap.xml' ) . "\n";
}, 99 );
