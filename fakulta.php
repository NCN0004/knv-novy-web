<?php
/**
 * Profil fakulty v novom dizajne – základný profil napojený na údaje JetEngine (CPT „fakulty“).
 * Pilot: zverejnené sú len fakulty v zozname knv_fak_live; ostatné vidí prihlásený správca cez ?knv_profil=1.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** ID fakúlt, ktorých nový profil je verejný (ostatné ostávajú v archíve). */
function knv_fak_live_ids() {
	return array_map( 'intval', (array) apply_filters( 'knv_fak_live_ids', get_option( 'knv_fak_live', array() ) ) );
}

/** Nadradená vysoká škola cez väzbu JetEngine (vs ↔ fakulty). */
function knv_fak_parent_vs( $pid ) {
	if ( ! function_exists( 'jet_engine' ) || empty( jet_engine()->relations ) ) {
		return 0;
	}
	try {
		foreach ( (array) jet_engine()->relations->get_active_relations() as $rel ) {
			$args = method_exists( $rel, 'get_args' ) ? (array) $rel->get_args() : array();
			$p    = isset( $args['parent_object'] ) ? (string) $args['parent_object'] : '';
			$c    = isset( $args['child_object'] ) ? (string) $args['child_object'] : '';
			if ( preg_match( '/(^|::)vs$/', $p ) && preg_match( '/(^|::)fakulty$/', $c ) ) {
				$ids = (array) $rel->get_parents( $pid, 'ids' );
				if ( $ids ) { return (int) reset( $ids ); }
			}
			if ( preg_match( '/(^|::)vs$/', $c ) && preg_match( '/(^|::)fakulty$/', $p ) ) {
				$ids = (array) $rel->get_children( $pid, 'ids' );
				if ( $ids ) { return (int) reset( $ids ); }
			}
		}
	} catch ( \Throwable $e ) {
		return 0;
	}
	return 0;
}

/** Najbližšie DOD z kalendára DOD nového webu (pages/DOD.dc.html), podľa univerzity a názvu fakulty. */
function knv_fak_dod_from_calendar( $uni, $fak_title ) {
	$file = knv_file( 'pages/DOD.dc.html' );
	if ( ! $uni || ! file_exists( $file ) ) { return array(); }
	$src = file_get_contents( $file );
	preg_match_all( "/([A-Z][A-Z0-9_]*) = '([^']+)'/", $src, $vm, PREG_SET_ORDER );
	$vars = array();
	foreach ( $vm as $m ) { $vars[ $m[1] ] = $m[2]; }
	$norm = function ( $s ) { return trim( preg_replace( '/\s+/u', ' ', mb_strtolower( (string) $s ) ) ); };
	$ft   = $norm( preg_replace( '/\s+(STU|UK|UKBA|TUKE|UNIZA|UMB|SPU|UKF|PU|TnUAD|TNUNI|VŠB.*)$/u', '', $fak_title ) );
	$out  = array();
	$months = array( 0 => array( 2026, 11, 'nov' ), 1 => array( 2026, 12, 'dec' ) );
	foreach ( preg_split( '/\n/', $src ) as $line ) {
		if ( ! preg_match( "/^\s*\[(\d), (\d+), '([A-ZÁ-Ž]+)', (.*)\],?\s*$/u", $line, $lm ) ) { continue; }
		preg_match_all( "/\[(\w+), '(\w\w)', \[(.*?)\]\]\]/u", $lm[4], $orgs, PREG_SET_ORDER );
		foreach ( $orgs as $o ) {
			if ( ! isset( $vars[ $o[1] ] ) || $norm( $vars[ $o[1] ] ) !== $norm( $uni ) ) { continue; }
			preg_match_all( "/\[\s*'([^']+)'(?:,\s*'([^']*)')?/u", $o[3], $fs, PREG_SET_ORDER );
			foreach ( $fs as $f ) {
				$fn = $norm( $f[1] );
				if ( $fn === $ft || 0 === strpos( $ft, $fn ) || 0 === strpos( $fn, $ft ) || preg_match( '/celá|všetk/u', $fn ) ) {
					$mo  = $months[ (int) $lm[1] ];
					$ts  = mktime( 0, 0, 0, $mo[1], (int) $lm[2], $mo[0] );
					$out[] = array( 'ts' => $ts, 'day' => (int) $lm[2], 'mon' => $mo[2], 'dow' => $lm[3], 'note' => isset( $f[2] ) ? $f[2] : '' );
				}
			}
		}
	}
	usort( $out, function ( $a, $b ) { return $a['ts'] - $b['ts']; } );
	return $out;
}


/**
 * Overené doplnky k údajom v databáze (pilot, kým fakulty nemajú vlastné účty).
 * Zdroj FEI STU: fei.stuba.sk – prijímacie konanie na Bc. štúdium a stránka DOD (overené 12. 11. 2026).
 */
function knv_fak_extra( $pid ) {
	$x = array(
		7106 => array(
			'popis'    => 'Fakulta elektrotechniky a informatiky STU v Bratislave ponúka 9 bakalárskych študijných programov – od aplikovanej informatiky a robotiky cez návrh čipov a automobilovú mechatroniku až po elektroenergetiku a jadrové inžinierstvo.',
			'programs' => array( 'Aplikovaná informatika', 'Digitálne technológie', 'Elektroenergetika', 'Elektronické systémy a návrh čipov', 'Elektrotechnika', 'Informačné a komunikačné technológie', 'Inteligentné technológie a automobilová mechatronika', 'Jadrové a fyzikálne inžinierstvo', 'Robotika a kybernetika' ),
			'dod_next' => array( 'ts' => mktime( 0, 0, 0, 1, 28, 2027 ), 'day' => 28, 'mon' => 'jan', 'label' => 'štvrtok 28. 1. 2027 · areál Ilkovičova, blok A · program fakulta zverejní pár dní pred podujatím', 'url' => 'https://www.fei.stuba.sk/dod' ),
			'pps'      => '1. kolo 5. 1. – 31. 3. 2026, 2. kolo 25. 5. – 30. 6. 2026',
			'pps_popis'=> 'termíny pre akademický rok 2026/27 – termíny na 2027/28 zverejní fakulta',
			'kps'      => 'z matematiky pri programoch Aplikovaná informatika, Digitálne technológie, Elektronické systémy a návrh čipov, Informačné a komunikačné technológie a Robotika a kybernetika',
			'kps_popis'=> 'odpúšťa sa pri aspoň 70. percentile z externej maturity z matematiky; ostatné programy bez prijímačiek',
			'kps_url'  => 'https://www.fei.stuba.sk/sk/uchadzaci/ako-zacat-studovat/prijimacie-konanie/prijimacie-konanie-na-bc-studium',
			'pzp'      => '30 €',
			'pzp_popis'=> 'akademický rok 2026/27',
			'prihlaska_url' => 'https://www.fei.stuba.sk/sk/3759', 'geo' => array( 48.1524, 17.0732 ),
		),
	);
	return isset( $x[ $pid ] ) ? $x[ $pid ] : array();
}

function knv_fak_data( $pid ) {
	$g   = function ( $k ) use ( $pid ) { return trim( (string) get_post_meta( $pid, $k, true ) ); };
	$vs  = knv_fak_parent_vs( $pid );
	$uni = $vs ? html_entity_decode( get_the_title( $vs ), ENT_QUOTES, 'UTF-8' ) : '';
	$t   = html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' );
	$logo = (int) $g( 'logo-fakulty' );
	$d = array(
		'id' => $pid, 'title' => $t, 'uni' => $uni, 'uni_url' => '',
		'logo' => $logo ? wp_get_attachment_image_url( $logo, 'medium' ) : '',
		'popis' => $g( 'kratky-popis' ),
		'adresa' => $g( 'adresa_fakulta' ), 'tel' => $g( 'telefon_fakulta' ), 'email' => $g( 'email' ), 'web' => $g( 'web' ),
		'fb' => $g( 'fb' ), 'ig' => $g( 'ig' ), 'yt' => $g( 'yt' ), 'li' => $g( 'li' ), 'tt' => $g( 'tiktok' ),
		'pri' => $g( 'pri_stud' ), 'prij' => $g( 'pocet-prijatych' ), 'celk' => $g( 'celkem-pocet' ),
		'prihlaska_url' => $g( 'odkaz-na-prihlasku' ),
		'dod_old' => $g( 'dod' ), 'dod_popis' => $g( 'dod_popis' ), 'dod_url' => $g( 'dod_tlacitko' ),
		'pps' => $g( 'podanie-termin-prihlasky' ), 'pps_popis' => $g( 'pps_popis' ),
		'kps' => $g( 'konanie-termin-prihlasky' ), 'kps_popis' => $g( 'kps_popis' ), 'kps_url' => $g( 'kps_tlacitko' ),
		'pzp' => $g( 'poplatok' ), 'pzp_popis' => $g( 'pzp_popis' ),
		'pzu' => $g( 'poplatok_ubyt' ), 'pzu_popis' => $g( 'pzu_popis' ),
		'pzj' => $g( 'poplatok_jedlo' ), 'pzj_popis' => $g( 'pzj_popis' ),
		'dod' => knv_fak_dod_from_calendar( $uni, $t ),
		'programs' => array(),
	);
	$d = array_merge( $d, knv_fak_extra( $pid ) );
	if ( ! empty( $d['dod_next'] ) ) { array_unshift( $d['dod'], $d['dod_next'] ); }
	return $d;
}

/** Vráti HTML <article> profilu – štýl podľa návrhu „Profil fakulty – základný“. */
function knv_fak_html( $d, $preview = false ) {
	$e    = function ( $s ) { return esc_html( $s ); };
	$INK  = '#393536'; $SUB = '#4A4546'; $LINE = '#E6E3E1'; $GREEN = '#2B7F29'; $DG = '#1F6A1E';
	$card = function ( $inner, $extra = '' ) use ( $LINE ) { return '<div style="background: #FFFFFF; border: 1.5px solid ' . $LINE . '; border-radius: 22px; padding: 26px 28px; ' . $extra . '">' . $inner . '</div>'; };
	$more = function ( $u ) use ( $GREEN ) { return $u ? '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener" style="font-size: 15px; font-weight: 700; color: ' . $GREEN . '; white-space: nowrap; text-decoration: none">Viac info »</a>' : ''; };
	$h2   = function ( $t, $u = '' ) use ( $more ) { return '<div style="display: flex; justify-content: space-between; align-items: baseline; gap: 16px; margin: 0 0 16px"><h2 style="font-size: 26px; font-weight: 800; margin: 0; line-height: 1.2">' . esc_html( $t ) . '</h2>' . $more( $u ) . '</div>'; };
	$web  = $d['web'];
	$miss = function ( $t ) use ( $preview ) { return $preview ? '<span style="background: #FFF7E0; color: #7A5A00; border: 1.5px dashed #E2C46A; border-radius: 8px; padding: 1px 8px; font-weight: 600">' . esc_html( $t ) . '</span>' : ''; };
	$btn  = function ( $t, $u, $primary = false ) use ( $INK, $GREEN ) {
		return '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener" style="' . ( $primary ? 'background: ' . $GREEN . '; color: #FFFFFF; border: 2px solid ' . $GREEN : 'background: #FFFFFF; color: ' . $INK . '; border: 2px solid ' . $INK ) . '; text-decoration: none; font-weight: 700; padding: 10px 18px; border-radius: 999px; white-space: nowrap">' . esc_html( $t ) . ' ↗</a>';
	};
	$dl = function ( $rows ) use ( $LINE ) {
		$h = '';
		foreach ( $rows as $r ) {
			if ( '' === $r[1] ) { continue; }
			$h .= '<div class="stackm" style="display: flex; gap: 16px; padding: 12px 0; border-top: 1px solid ' . $LINE . '; font-size: 16px; line-height: 1.45"><span style="font-weight: 700; flex: 0 0 110px">' . esc_html( $r[0] ) . '</span><span style="flex: 1 1 auto; min-width: 0">' . $r[1] . '</span></div>';
		}
		return $h;
	};
	$withp = function ( $v, $p ) use ( $SUB ) { return esc_html( $v ) . ( $p ? '<br><span style="color: ' . $SUB . '; font-size: 14px">' . esc_html( $p ) . '</span>' : '' ); };

	// Hlavička.
	$logo = $d['logo'] ? '<img src="' . esc_url( $d['logo'] ) . '" alt="Logo – ' . esc_attr( $d['title'] ) . '" style="width: 96px; height: 96px; object-fit: contain; border-radius: 24px; background: #FFFFFF; border: 1.5px solid ' . $LINE . '; padding: 8px; box-sizing: border-box; flex: none">' : '';
	$head = '<nav aria-label="Omrvinky" style="font-size: 14px; color: ' . $SUB . '; margin-bottom: 18px"><a href="Co-studovat.dc.html">Čo študovať</a> › <a href="DOD.dc.html">Fakulty a DOD</a> › ' . $e( $d['title'] ) . '</nav>'
		. '<div class="stackm" style="display: flex; gap: 24px; align-items: center">' . $logo . '<div style="min-width: 0">'
		. '<h1 class="h1m" style="font-size: 48px; line-height: 1.06; letter-spacing: -0.03em; font-weight: 800; margin: 0">' . $e( $d['title'] ) . '</h1>'
		. ( $d['uni'] ? '<p style="font-size: 19px; color: ' . $SUB . '; margin: 8px 0 0">' . $e( $d['uni'] ) . '</p>' : '' ) . '</div></div>';

	// O fakulte + ilustrácia (autor Claude).
	$illus = '<figure style="margin: 18px 0 0; text-align: center"><svg role="img" aria-label="Ilustrácia: budova fakulty" width="100%" viewBox="0 0 360 200" style="max-width: 340px; display: block; margin: 0 auto"><rect width="360" height="200" rx="22" fill="#F0F5EE"/><rect x="70" y="64" width="220" height="112" rx="6" fill="#FFFFFF" stroke="#393536" stroke-width="3"/><path d="M60 68 L180 26 L300 68 Z" fill="#45B541" stroke="#393536" stroke-width="3" stroke-linejoin="round"/><g fill="#DDE8DA" stroke="#393536" stroke-width="2.5"><rect x="92" y="84" width="34" height="26" rx="3"/><rect x="163" y="84" width="34" height="26" rx="3"/><rect x="234" y="84" width="34" height="26" rx="3"/><rect x="92" y="128" width="34" height="26" rx="3"/><rect x="234" y="128" width="34" height="26" rx="3"/></g><rect x="163" y="124" width="34" height="52" rx="3" fill="#393536"/><rect x="40" y="174" width="280" height="8" rx="4" fill="#B5D3B2"/></svg><figcaption style="font-size: 12px; color: #575152; margin-top: 6px">Ilustrácia: Claude</figcaption></figure>';
	$about = $card( $h2( 'O fakulte', $web ) . ( $d['popis'] ? '<p style="font-size: 17px; line-height: 1.6; color: ' . $SUB . '; margin: 0">' . $e( $d['popis'] ) . '</p>' : '<p style="font-size: 17px; line-height: 1.6; color: ' . $SUB . '; margin: 0">' . ( $preview ? $miss( 'Krátky popis fakulty – doplní fakulta' ) : 'Viac o fakulte, jej programoch a živote študentov nájdeš na jej webe.' ) . '</p>' ) . $illus );

	// Kontakt.
	$rows = array(
		array( 'Adresa', $e( $d['adresa'] ) ),
		array( 'Telefón', $d['tel'] ? '<a href="tel:' . esc_attr( preg_replace( '/\s+/', '', $d['tel'] ) ) . '">' . $e( $d['tel'] ) . '</a>' : '' ),
		array( 'E-mail', $d['email'] ? '<a href="mailto:' . esc_attr( $d['email'] ) . '">' . $e( $d['email'] ) . '</a>' : $miss( 'doplní fakulta' ) ),
		array( 'Web', $d['web'] ? '<a href="' . esc_url( $d['web'] ) . '" target="_blank" rel="noopener">' . $e( preg_replace( '#^https?://(www\.)?|/$#', '', $d['web'] ) ) . '</a>' : '' ),
	);
	$soc = '';
	foreach ( knv_fak_soc_icons() as $k => $ic ) {
		if ( ! empty( $d[ $k ] ) ) { $soc .= '<a href="' . esc_url( $d[ $k ] ) . '" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 8px; background: #FFFFFF; color: ' . $INK . '; border: 1.5px solid ' . $LINE . '; text-decoration: none; font-weight: 700; font-size: 14px; padding: 7px 14px 7px 9px; border-radius: 999px"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24"><path fill="' . $ic[1] . '" d="' . $ic[2] . '"/></svg>' . $ic[0] . '</a>'; }
	}
	$map = '';
	if ( $d['adresa'] ) {
		$q   = rawurlencode( $d['adresa'] );
		$bt  = function ( $t, $u, $pr = false ) use ( $INK, $GREEN ) { return '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener" style="' . ( $pr ? 'background: ' . $GREEN . '; color: #FFFFFF; border: 2px solid ' . $GREEN : 'background: #FFFFFF; color: ' . $INK . '; border: 2px solid ' . $INK ) . '; text-decoration: none; font-weight: 700; font-size: 14px; padding: 8px 14px; border-radius: 999px; white-space: nowrap">' . esc_html( $t ) . '</a>'; };
		if ( ! empty( $d['geo'] ) ) {
			$la = (float) $d['geo'][0]; $lo = (float) $d['geo'][1];
			$src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . ( $lo - 0.006 ) . '%2C' . ( $la - 0.0028 ) . '%2C' . ( $lo + 0.006 ) . '%2C' . ( $la + 0.0028 ) . '&layer=mapnik&marker=' . $la . '%2C' . $lo;
			$map = '<div style="margin-top: 18px; border-radius: 16px; border: 1.5px solid #DDE8DA; overflow: hidden; height: 240px; background: #F4F1EC"><iframe title="Mapa – ' . esc_attr( $d['title'] ) . '" src="' . esc_url( $src ) . '" loading="lazy" referrerpolicy="no-referrer" style="border: 0; width: 100%; height: 100%; display: block"></iframe></div>';
		} else {
			$map = '<a href="' . esc_url( 'https://www.google.com/maps/search/?api=1&query=' . $q ) . '" target="_blank" rel="noopener" aria-label="Otvoriť adresu v Mapách Google" style="display: block; margin-top: 18px; border-radius: 16px; border: 1.5px solid #DDE8DA; overflow: hidden">' . knv_fak_map_svg() . '</a>';
		}
		$map .= '<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px">' . $bt( 'Navigovať ↗', 'https://www.google.com/maps/dir/?api=1&destination=' . $q, true ) . $bt( 'Otvoriť v Mapách Google ↗', 'https://www.google.com/maps/search/?api=1&query=' . $q ) . '</div>'
			. ( ! empty( $d['doprava'] ) ? '<p style="font-size: 13px; color: ' . $SUB . '; margin: 10px 0 0">' . esc_html( $d['doprava'] ) . '</p>' : '' );
	}
	$contact = $card( $h2( 'Kontakt', $web ) . $dl( $rows ) . ( $soc ? '<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px">' . $soc . '</div>' : '' ) . $map );

	// DOD.
	$now  = current_time( 'timestamp' );
	$next = null;
	foreach ( $d['dod'] as $x ) { if ( $x['ts'] >= strtotime( 'today', $now ) ) { $next = $x; break; } }
	if ( $next ) {
		$dod_in = '<div style="display: flex; gap: 18px; align-items: center; margin: 14px 0 16px"><div style="background: ' . $GREEN . '; color: #FFFFFF; border-radius: 16px; width: 74px; padding: 10px 0; text-align: center; flex: none"><div style="font-size: 13px; font-weight: 700; text-transform: uppercase">' . $e( $next['mon'] ) . '</div><div style="font-size: 32px; font-weight: 800; line-height: 1">' . (int) $next['day'] . '</div></div>'
			. '<div><div style="font-weight: 800; font-size: 18px">Deň otvorených dverí</div><div style="color: ' . $SUB . '; font-size: 15px; margin-top: 4px">' . ( ! empty( $next['label'] ) ? $e( $next['label'] ) : $e( strtr( $next['dow'], array( 'PON' => 'pondelok', 'UTO' => 'utorok', 'STR' => 'streda', 'ŠTV' => 'štvrtok', 'STV' => 'štvrtok', 'PIA' => 'piatok', 'SOB' => 'sobota', 'NED' => 'nedeľa' ) ) ) . ' ' . (int) $next['day'] . '. ' . ( 'nov' === $next['mon'] ? '11' : ( 'jan' === $next['mon'] ? '1' : '12' ) ) . '. 2026' . ( $next['note'] ? ' · ' . $e( $next['note'] ) : '' ) ) . '</div></div></div>'
			. ( ! empty( $next['url'] ) ? '<p style="margin: 0 0 14px"><a href="' . esc_url( $next['url'] ) . '" target="_blank" rel="noopener">Viac o DOD na webe fakulty ↗</a></p>' : '' ) . '<div style="display: flex; gap: 10px; flex-wrap: wrap"><a href="Moj-plan.dc.html" style="background: ' . $GREEN . '; color: #FFFFFF; text-decoration: none; font-weight: 700; padding: 12px 18px; border-radius: 999px; font-size: 15px">+ Do môjho plánu</a><a href="DOD.dc.html" style="color: ' . $INK . '; font-weight: 700; padding: 12px 6px; font-size: 15px">Celý kalendár DOD →</a></div>';
	} else {
		$dod_in = '<p style="font-size: 16px; color: ' . $SUB . '; margin: 14px 0 16px">Termín ďalšieho dňa otvorených dverí fakulta zatiaľ nezverejnila.' . ( $d['dod_old'] ? ' Posledný bol ' . $e( $d['dod_old'] ) . '.' : '' ) . '</p><a href="DOD.dc.html" style="font-weight: 700">Pozri kalendár DOD →</a>';
	}
	$dod = $card( '<div style="display: flex; justify-content: space-between; align-items: baseline; gap: 12px"><span style="font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: ' . $DG . '">Najbližší deň otvorených dverí</span>' . $more( ( $next && ! empty( $next['url'] ) ) ? $next['url'] : ( $d['dod_url'] ? $d['dod_url'] : $web ) ) . '</div>' . $dod_in );

	// Zachytenie e-mailu záujemcu (ide do SmartEmailingu + uloží sa s fakultou).
	$lead = $card( '<div style="font-weight: 800; font-size: 20px; line-height: 1.25">Nezmeškaj DOD ani prihlášku</div><p style="color: ' . $SUB . '; font-size: 15px; margin: 6px 0 14px">Pošleme ti pripomienku pred dňom otvorených dverí a termínom prihlášok na túto fakultu a tipy, ako si vybrať vysokú.</p>'
		. '<form class="knv-fak-lead" novalidate style="display: flex; flex-direction: column; gap: 10px"><label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; font-size: 14px">Tvoj e-mail<input id="nl-email" type="email" autocomplete="email" required placeholder="meno@email.sk" style="height: 48px; border: 1.5px solid #CFCAC8; border-radius: 12px; padding: 0 14px; font-size: 16px"></label>'
		. '<label style="display: flex; gap: 10px; align-items: flex-start; font-size: 13px; color: ' . $SUB . '; line-height: 1.4"><input type="checkbox" id="nl-suhlas" style="width: 20px; height: 20px; margin: 0; flex: none; accent-color: ' . $GREEN . '"><span>Súhlasím so zasielaním informácií o štúdiu od Kam na vysokú. Odhlásiť sa dá kedykoľvek. <a href="Ochrana-udajov.dc.html">Ochrana údajov</a></span></label>'
		. '<button type="submit" style="height: 50px; border: 0; border-radius: 999px; background: ' . $GREEN . '; color: #FFFFFF; font-size: 16px; font-weight: 800; cursor: pointer">Chcem pripomienku</button></form>', 'background: #F0F5EE; border-color: #B5D3B2' );

	// Termíny a prijímačky.
	$kps = $d['kps'] ? $withp( $d['kps'], $d['kps_popis'] ) . ( $d['kps_url'] ? ' · <a href="' . esc_url( $d['kps_url'] ) . '" target="_blank" rel="noopener">viac ↗</a>' : '' ) : '';
	$terms = $card( $h2( 'Prihláška a prijímačky', $d['kps_url'] ? $d['kps_url'] : ( $d['prihlaska_url'] ? $d['prihlaska_url'] : $web ) ) . $dl( array(
		array( 'Prihlášky', $d['pps'] ? $withp( $d['pps'], $d['pps_popis'] ) . ( $preview && ! $d['pps_popis'] ? ' ' . $miss( 'skontrolovať rok' ) : '' ) : $miss( 'doplní fakulta' ) ),
		array( 'Prijímačky', $kps ? $kps : $miss( 'doplní fakulta' ) ),
	) ) );

	// Poplatky.
	$fees_rows = array(
		array( 'Poplatok za prihlášku', $d['pzp'] ? $withp( $d['pzp'], $d['pzp_popis'] ) : '' ),
		array( 'Internát', $d['pzu'] ? $withp( $d['pzu'] . ' / mesiac', $d['pzu_popis'] ) : '' ),
		array( 'Jedlo', $d['pzj'] ? $withp( $d['pzj'], $d['pzj_popis'] ) : '' ),
	);
	$fees = $dl( $fees_rows ) ? $card( $h2( 'Poplatky a život', $web ) . $dl( $fees_rows ) ) : '';

	// Počty študentov.
	$stats = '';
	$tiles = array( array( $d['pri'], 'prihlásených uchádzačov' ), array( $d['prij'], 'prijatých do 1. ročníka' ), array( $d['celk'], 'študentov na fakulte spolu' ) );
	if ( $d['pri'] || $d['prij'] || $d['celk'] || $preview ) {
		$stats = '<div class="stackm" style="display: flex; gap: 16px; margin-top: 24px">';
		foreach ( $tiles as $tl ) {
			$stats .= '<div style="flex: 1 1 0; min-width: 0; background: #FFFFFF; border: 1.5px solid ' . $LINE . '; border-radius: 18px; padding: 18px 20px"><div style="font-size: 30px; font-weight: 800; line-height: 1">' . ( $tl[0] ? $e( $tl[0] ) : ( $preview ? $miss( 'číslo' ) : '–' ) ) . '</div><div style="color: ' . $SUB . '; font-size: 14px; margin-top: 8px">' . $tl[1] . '</div></div>';
		}
		$stats .= '</div>';
	}

	// Programy (údaje zatiaľ nie sú v databáze – odkaz na web fakulty).
	if ( ! empty( $d['programs'] ) ) {
		$li = '';
		foreach ( $d['programs'] as $pg ) { $li .= '<li style="display: flex; justify-content: space-between; gap: 16px; padding: 12px 0; border-top: 1px solid ' . $LINE . '"><span style="font-weight: 700">' . $e( $pg ) . '</span><span style="color: ' . $SUB . '; font-size: 15px; white-space: nowrap">Bc. · denná</span></li>'; }
		$progs = $card( $h2( 'Študijné programy', $web ) . '<div style="font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: ' . $DG . '; margin-bottom: 4px">Bakalárske (' . count( $d['programs'] ) . ')</div><ul style="list-style: none; margin: 0; padding: 0; columns: 2 320px; column-gap: 32px">' . $li . '</ul>' . ( $d['web'] ? '<div style="margin-top: 16px">' . $btn( 'Inžinierske a doktorandské programy na webe fakulty', $d['web'] ) . '</div>' : '' ), 'margin-top: 24px' );
	} else {
	$progs = $card( $h2( 'Študijné programy', $web ) . '<p style="font-size: 16px; color: ' . $SUB . '; margin: 0">' . ( $preview ? $miss( 'Zoznam bakalárskych a inžinierskych programov doplní fakulta' ) . ' ' : '' ) . 'Aktuálny zoznam bakalárskych aj inžinierskych programov nájdeš na webe fakulty.</p>' . ( $d['web'] ? '<div style="margin-top: 16px">' . $btn( 'Študijné programy na webe fakulty', $d['web'] ) . '</div>' : '' ), 'margin-top: 24px' );
	}

	// Roadshow.
	$road = $card( '<div class="stackm" style="display: flex; justify-content: space-between; align-items: center; gap: 16px"><div><div style="font-weight: 800; font-size: 18px">Stretni fakultu na Roadshow</div><div style="color: ' . $SUB . '; font-size: 15px; margin-top: 4px">' . ( $d['uni'] ? $e( $d['uni'] ) . ' vystavuje na Roadshow Kam na vysokú. ' : '' ) . 'Najbližšie zastávky: Prievidza 1. 12. 2026 · Bratislava 26. 1. 2027 · vstup zadarmo</div></div><a href="Zastavka-Prievidza.dc.html" style="background: ' . $GREEN . '; color: #FFFFFF; text-decoration: none; font-weight: 700; padding: 12px 18px; border-radius: 999px; white-space: nowrap">Bezplatná vstupenka</a></div>', 'margin-top: 24px' );

	$links = '<div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px">' . ( $d['web'] ? $btn( 'Web fakulty', $d['web'] ) : '' ) . ( $d['prihlaska_url'] ? $btn( 'Elektronická prihláška', $d['prihlaska_url'] ) : '' ) . '</div>';
	$note  = '<p style="font-size: 14px; color: ' . $SUB . '; margin: 28px 0 0">Údaje poskytla fakulta. Našli ste chybu? <a href="Kontakt.dc.html">Napíšte nám</a>.</p>';

	return '<article><div style="background: #FFFFFF; border-bottom: 1px solid ' . $LINE . '"><div class="wrap" style="padding-top: 36px; padding-bottom: 40px; max-width: 1120px">' . $head . '</div></div>'
		. '<div class="wrap padm" style="max-width: 1120px; padding-top: 36px; padding-bottom: 64px">'
		. '<div class="stackm" style="display: flex; gap: 24px; align-items: stretch"><div style="flex: 1 1 60%; min-width: 0; display: flex; flex-direction: column; gap: 24px">' . $about . $contact . '</div>'
		. '<div style="flex: 1 1 40%; min-width: 0; display: flex; flex-direction: column; gap: 24px">' . $dod . $lead . $terms . '</div></div>'
		. $stats . $progs . $road . $links . $note . '</div></article>';
}

/** Značkové ikonky sociálnych sietí (Simple Icons, CC0). */
function knv_fak_soc_icons() {
	return array(
		'fb' => array( 'Facebook', '#0866FF', 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z' ),
		'ig' => array( 'Instagram', '#E4405F', 'M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077' ),
		'yt' => array( 'YouTube', '#FF0000', 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z' ),
		'tt' => array( 'TikTok', '#000000', 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z' ),
		'li' => array( 'LinkedIn', '#0A66C2', 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z' ),
	);
}

/** Ilustračná mapa (bez názvov ulíc) – keď fakulta nemá súradnice. Autor ilustrácie: Claude. */
function knv_fak_map_svg() {
	return '<svg aria-hidden="true" width="100%" height="200" viewBox="0 0 480 200" preserveAspectRatio="xMidYMid slice" style="display: block"><rect width="480" height="200" fill="#F4F1EC"/><path d="M-10 162 C120 140 240 180 360 156 S470 130 490 144 L490 210 L-10 210Z" fill="#CFE3F2"/><path d="M18 22 h120 a10 10 0 0 1 10 10 v52 a10 10 0 0 1 -10 10 h-120 a10 10 0 0 1 -10 -10 v-52 a10 10 0 0 1 10 -10z" fill="#D6EBD2"/><circle cx="45" cy="50" r="7" fill="#B5D3B2"/><circle cx="70" cy="66" r="9" fill="#B5D3B2"/><circle cx="105" cy="44" r="8" fill="#B5D3B2"/><circle cx="120" cy="72" r="6" fill="#B5D3B2"/><g fill="#E6E1DA"><rect x="176" y="20" width="58" height="34" rx="4"/><rect x="244" y="20" width="40" height="34" rx="4"/><rect x="340" y="18" width="70" height="40" rx="4"/><rect x="420" y="18" width="48" height="40" rx="4"/><rect x="20" y="118" width="54" height="36" rx="4"/><rect x="84" y="118" width="62" height="36" rx="4"/><rect x="340" y="112" width="56" height="44" rx="4"/><rect x="406" y="112" width="62" height="44" rx="4"/></g><rect x="178" y="94" width="130" height="66" rx="6" fill="#CFE7CB" stroke="#45B541" stroke-width="2"/><rect x="190" y="104" width="46" height="46" rx="3" fill="#B5D3B2"/><rect x="244" y="104" width="52" height="20" rx="3" fill="#B5D3B2"/><rect x="244" y="130" width="52" height="20" rx="3" fill="#B5D3B2"/><g fill="none" stroke="#FFFFFF" stroke-linecap="round"><path d="M-10 104 H490" stroke-width="16"/><path d="M160 -10 V210" stroke-width="12"/><path d="M322 -10 V160" stroke-width="10"/><path d="M160 172 H490" stroke-width="8"/></g><g fill="none" stroke="#E2DCD4" stroke-width="1"><path d="M-10 96 H490 M-10 112 H490"/></g><path d="M-10 104 H490" stroke="#8C8486" stroke-width="2" stroke-dasharray="6 5"/><circle cx="118" cy="104" r="7" fill="#FFFFFF" stroke="#393536" stroke-width="2"/><text x="118" y="108" font-family="Inter, system-ui, sans-serif" font-size="9" font-weight="800" fill="#393536" text-anchor="middle">T</text><g transform="translate(243 88)"><ellipse cx="0" cy="44" rx="12" ry="4" fill="#393536" opacity=".18"/><path d="M0 -6 c-15 0 -26 11 -26 25 c0 19 26 45 26 45 s26 -26 26 -45 c0 -14 -11 -25 -26 -25z" transform="translate(0 -20)" fill="#2B7F29" stroke="#FFFFFF" stroke-width="3"/><circle cx="0" cy="-1" r="9" fill="#FFFFFF"/></g></svg>';
}
