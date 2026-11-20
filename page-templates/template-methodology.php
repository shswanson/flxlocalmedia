<?php
/**
 * Template Name: Methodology
 *
 * How we measure our audience, linked from station pages and Why Radio but not
 * in the main navigation. For people who want to check the math: the market,
 * signal coverage, the weekly listener estimates, streaming, the Finger Lakes
 * Daily News website and newsletter, every data source, and what we do not claim.
 *
 * Every figure comes from the methodology data folder and its adversarial check
 * (2026-11-20): only metrics marked verified, or corrected (the corrected value),
 * are used. Unverifiable metrics and the unverified "210K monthly users" figure
 * are left out on purpose. No listener estimate is stated here that is not already
 * published on a station page.
 *
 * @package flxlm
 */

$d = array(
	'counties' => array(
		'head' => array( 'County', 'Population', 'Share of total', 'Adults 18+', 'Adults, % of people', 'Land (sq mi)', 'People per sq mi', 'Workers 16+', 'Drive alone to work', 'Drive alone, % of workers', 'Households', 'Own their home', 'Median household income' ),
		'rows' => array(
			array( 'Ontario', 112288, '16.1%', 90387, '80.5%', 644, 174, 54283, 42486, '78.3%', 47080, '72.5%', '$76,603' ),
			array( 'Tompkins', 102555, '14.7%', 87685, '85.5%', 475, 216, 48267, 26214, '54.3%', 42984, '54.3%', '$69,995' ),
			array( 'Steuben', 93584, '13.4%', 73538, '78.6%', 1391, 67, 41256, 32768, '79.4%', 39165, '72.7%', '$62,506' ),
			array( 'Wayne', 91324, '13.1%', 72033, '78.9%', 604, 151, 42736, 34254, '80.2%', 37756, '79.8%', '$71,007' ),
			array( 'Chemung', 83584, '12.0%', 65995, '79.0%', 407, 205, 35625, 28788, '80.8%', 34779, '68.4%', '$61,358' ),
			array( 'Cayuga', 76171, '10.9%', 61361, '80.6%', 692, 110, 34081, 27355, '80.3%', 30910, '70.0%', '$63,227' ),
			array( 'Livingston', 61980, '8.9%', 50932, '82.2%', 632, 98, 28236, 21572, '76.4%', 24071, '77.0%', '$70,443' ),
			array( 'Seneca', 33651, '4.8%', 26908, '80.0%', 324, 104, 14480, 11078, '76.5%', 13622, '72.5%', '$64,050' ),
			array( 'Yates', 24713, '3.5%', 19276, '78.0%', 338, 73, 10662, 7630, '71.6%', 8882, '77.3%', '$63,974' ),
			array( 'Schuyler', 17855, '2.6%', 14490, '81.2%', 328, 54, 7982, 6034, '75.6%', 7446, '76.9%', '$61,316' ),
			array( 'Ten-county total', 697705, '100.0%', 562605, '80.6%', 5834, 120, 317608, 238179, '75.0%', 286695, '70.6%', 'n/a' ),
		),
	),
	'coverage' => array(
		'head' => array( 'Station', 'County sum (registry counties)', 'FCC 60 dBu, FM only', 'FCC 54 dBu, FM only', '54 dBu as % of county sum', '54 dBu area (sq mi)' ),
		'rows' => array(
			array( 'Mix 98.5', 338147, 115382, 210419, '62.2%', 1761 ),
			array( 'Classic Hits 99.3', 313434, 142854, 236956, '75.6%', 1855 ),
			array( '101.7 The Wall', 237263, 74012, 131174, '55.3%', 1076 ),
			array( 'Finger Lakes Country', 365675, 45574, 96630, '26.4%', 948 ),
			array( 'WGVA (estimate)', 112288, 29524, 29524, '26.3%', 177 ),
			array( 'WAUB (estimate)', 76171, 42732, 42732, '56.1%', 177 ),
			array( 'The Lake', 930674, 238486, 578282, '62.1%', 854 ),
		),
	),
	'overlap' => array(
		'head' => array( 'Stations that reach the same spot', 'People, FCC 60 dBu', '% of ten-county people', 'People, FCC 54 dBu', '% of ten-county people' ),
		'rows' => array(
			array( '1 station', 134091, '19.2%', 128167, '18.4%' ),
			array( '2 stations', 34853, '5.0%', 59294, '8.5%' ),
			array( '3 stations', 50193, '7.2%', 74160, '10.6%' ),
			array( '4 stations', 33543, '4.8%', 60033, '8.6%' ),
			array( '5 stations', 6303, '0.9%', 26316, '3.8%' ),
			array( 'At least one station', 258984, '37.1%', 347971, '49.9%' ),
			array( 'Seven stations counted separately, then added', 520066, '74.5%', 840950, '120.5%' ),
		),
	),
	'towns' => array(
		'head' => array( 'Station', 'Places with 50%+ of their area inside 60 dBu', 'Places with 50%+ inside 54 dBu' ),
		'rows' => array(
			array( 'Mix 98.5', 16, 34 ),
			array( 'Classic Hits 99.3', 19, 41 ),
			array( '101.7 The Wall', 13, 24 ),
			array( 'Finger Lakes Country', 9, 17 ),
			array( 'WGVA', 4, 4 ),
			array( 'WAUB', 4, 4 ),
			array( 'The Lake', 15, 32 ),
		),
	),
	'commute' => array(
		'head' => array( 'Station', 'Counties counted', 'Average commute each way (min)', 'Workers commuting 30+ min', 'Work from home' ),
		'rows' => array(
			array( 'Mix 98.5', 'Ontario, Cayuga, Seneca, Wayne, Yates', '22.7', '31.6%', '10.9%' ),
			array( 'Classic Hits 99.3', 'Ontario, Cayuga, Seneca, Wayne', '22.8', '31.7%', '10.7%' ),
			array( '101.7 The Wall', 'Ontario, Seneca, Wayne', '22.8', '31.9%', '11.6%' ),
			array( 'Finger Lakes Country', 'Yates, Schuyler, Steuben, Chemung, Seneca, Ontario', '21.8', '27.2%', '10.5%' ),
			array( 'WGVA', 'Ontario', '22.3', '30.6%', '13.6%' ),
			array( 'WAUB', 'Cayuga', '22.8', '31.0%', '7.7%' ),
			array( 'The Lake', 'Ontario, Livingston, Monroe', '20.5', '21.0%', '14.9%' ),
		),
	),
	'adults' => array(
		'head' => array( 'Station', 'Counties the model counts', 'Population (ACS 2022)', 'Adults 18+ (Census count)', 'Adult share', 'Adults if we assume 78%' ),
		'rows' => array(
			array( 'Mix 98.5', 'Cayuga, Ontario, Seneca, Wayne, Yates', 338147, 269965, '79.8%', 263755 ),
			array( 'Classic Hits 99.3', 'Cayuga, Ontario, Seneca, Wayne', 313434, 250689, '80.0%', 244479 ),
			array( '101.7 The Wall', 'Ontario, Yates, Schuyler, Steuben', 248440, 197691, '79.6%', 193783 ),
			array( 'Finger Lakes Country', 'Ontario, Yates, Schuyler, Steuben, Chemung, Seneca', 365675, 290594, '79.5%', 285226 ),
			array( 'WGVA', 'Ontario, Seneca, Wayne', 237263, 189328, '79.8%', 185065 ),
			array( 'WAUB', 'Cayuga', 76171, 61361, '80.6%', 59413 ),
			array( 'The Lake', 'Monroe, Ontario, Livingston', 930674, 742756, '79.8%', 725926 ),
		),
	),
	'stations30' => array(
		'head' => array( 'Station', 'Listening hours', 'Share of all hours', 'Sessions', 'Unique devices', 'Hours from New York', 'Hours from home markets', 'Average people listening at any moment' ),
		'rows' => array(
			array( 'The Lake', 15629, '33.3%', 18663, 3746, '64.1%', '48.6%', '21.7' ),
			array( '101.7 The Wall', 6967, '14.9%', 10551, 1754, '86.0%', '62.6%', '9.7' ),
			array( 'Classic Hits 99.3', 6354, '13.6%', 6210, 1416, '73.5%', '52.6%', '8.8' ),
			array( 'Mix 98.5', 5346, '11.4%', 6470, 1117, '81.7%', '68.1%', '7.4' ),
			array( 'Finger Lakes Country', 5178, '11.0%', 7500, 1516, '73.7%', '54.0%', '7.2' ),
			array( 'WGVA', 4314, '9.2%', 8557, 1934, '54.5%', '37.8%', '6.0' ),
			array( 'WAUB', 3083, '6.6%', 7031, 1832, '64.8%', '42.5%', '4.3' ),
			array( 'All seven stations', 46871, '100.0%', 64982, 11628, '70.9%', '52.7%', '65.1' ),
		),
	),
	'localnon' => array(
		'head' => array( 'Where the device is', 'Unique devices', 'Listening hours', 'Hours per device', 'Sessions', 'Average session (min)' ),
		'rows' => array(
			array( 'Home markets (Rochester, Syracuse, Elmira)', 3379, 24678, '7.3', 29785, '49.7' ),
			array( 'Everywhere else', 8249, 22194, '2.7', 35197, '37.8' ),
		),
	),
	'dmas' => array(
		'head' => array( 'Market (Nielsen area)', 'Listening hours', 'Unique devices' ),
		'rows' => array(
			array( 'Rochester NY', 17049, 2357 ),
			array( 'Syracuse', 6814, 947 ),
			array( 'Unknown location', 4125, 1168 ),
			array( 'Buffalo', 3005, 1219 ),
			array( 'Albany-Schenectady-Troy', 2669, 1231 ),
			array( 'New York', 2459, 1122 ),
			array( 'Cleveland-Akron', 1138, 237 ),
			array( 'Elmira', 814, 75 ),
			array( 'Boston (Manchester)', 564, 251 ),
			array( 'Charlotte', 539, 210 ),
		),
	),
	'devices' => array(
		'head' => array( 'Device or app', 'Listening hours', 'Share of hours', 'Unique devices' ),
		'rows' => array(
			array( 'Web browser (computer)', 11189, '23.9%', 1026 ),
			array( 'Phone app (iPhone/iPad)', 10257, '21.9%', 3527 ),
			array( 'Alexa / smart speaker', 8880, '18.9%', 1483 ),
			array( 'TuneIn', 4503, '9.6%', 916 ),
			array( 'Web browser (phone)', 4341, '9.3%', 2234 ),
			array( 'Phone app (Android)', 4041, '8.6%', 2166 ),
			array( 'Radio-directory apps', 1477, '3.2%', 715 ),
			array( 'Web browser (other)', 851, '1.8%', 123 ),
			array( 'Other devices', 701, '1.5%', 256 ),
			array( 'Desktop media player', 359, '0.8%', 67 ),
			array( 'Other smart speaker', 219, '0.5%', 13 ),
		),
	),
	'daypart' => array(
		'head' => array( 'Time of day (Eastern)', 'Listening hours', 'Share of hours' ),
		'rows' => array(
			array( 'Midnight to 6 AM', 2315, '4.9%' ),
			array( '6 AM to 10 AM', 8864, '18.9%' ),
			array( '10 AM to 3 PM', 15806, '33.7%' ),
			array( '3 PM to 7 PM', 14194, '30.3%' ),
			array( '7 PM to midnight', 5692, '12.1%' ),
		),
	),
	'monthly' => array(
		'head' => array( 'Month', 'Days', 'Listening hours', 'Unique devices', 'Home-market hours', 'Home-market share', 'Hours per day' ),
		'rows' => array(
			array( 'May 2026', 31, 40257, 8517, 25906, '64.4%', 1299 ),
			array( 'June 2026', 30, 42880, 9710, 26796, '62.5%', 1429 ),
			array( 'July 2026', 31, 42525, 8563, 26762, '62.9%', 1372 ),
			array( 'August 2026', 31, 42645, 9818, 24437, '57.3%', 1376 ),
			array( 'September 2026', 30, 46669, 11094, 23493, '50.3%', 1556 ),
			array( 'October 2026', 31, 52864, 12046, 24749, '46.8%', 1705 ),
			array( 'November 2026 (1 to 19)', 19, 28510, 7830, 15504, '54.4%', 1501 ),
		),
	),
	'filters' => array(
		'head' => array( 'Window', 'Raw logged hours', 'Removed by bot and quality filters', 'After filters', 'WAUB and WGVA midnight to 6 AM removed', 'Reported hours' ),
		'rows' => array(
			array( 'Last 30 days (Oct 21 to Nov 19)', 59121, 10515, 48606, 1734, 46871 ),
			array( 'Last 90 days (Aug 22 to Nov 19)', 179975, 33820, 146156, 5175, 140980 ),
		),
	),
	'overnight' => array(
		'head' => array( 'Station', 'Last 28 days, all hours', 'Last 28 days, 6 AM to midnight (what we report)', 'Overnight hours removed', 'Share removed' ),
		'rows' => array(
			array( 'WAUB', 3567, 2944, 623, '17.5%' ),
			array( 'WGVA', 5021, 4042, 979, '19.5%' ),
		),
	),
	'webmonthly' => array(
		'head' => array( 'Month', 'Pageviews', 'Sessions', 'Distinct active users', 'Pageviews per session' ),
		'rows' => array(
			array( 'Nov 2025', 527651, 216300, 91073, '2.36' ),
			array( 'Dec 2025', 537824, 228319, 89262, '2.43' ),
			array( 'Jan 2026', 570120, 262509, 102852, '2.15' ),
			array( 'Feb 2026', 563500, 252565, 105010, '2.19' ),
			array( 'Mar 2026', 590602, 266232, 105180, '2.21' ),
			array( 'Apr 2026', 639995, 340362, 154304, '1.83' ),
			array( 'May 2026', 676057, 348820, 163860, '1.91' ),
			array( 'Jun 2026', 709261, 386158, 179559, '1.84' ),
			array( 'Jul 2026', 848124, 493732, 251552, '1.73' ),
			array( 'Aug 2026', 735187, 394317, 192465, '1.85' ),
			array( 'Sep 2026', 694115, 377897, 175143, '1.89' ),
			array( 'Oct 2026', 717725, 377535, 179822, '1.89' ),
			array( '12 months', 7810161, 3972178, 1619488, '1.97' ),
		),
	),
	'botfilter' => array(
		'head' => array( 'Measure, 12 months', 'Google Analytics raw', 'After our filter', 'Removed', 'Removed share' ),
		'rows' => array(
			array( 'Sessions', 4382620, 3972178, 410442, '9.4%' ),
			array( 'Active users (distinct)', 1962017, 1619488, 342529, '17.5%' ),
			array( 'Total users (distinct)', 1974365, 1631482, 342883, '17.4%' ),
			array( 'Pageviews', 8317410, 7810161, 507249, '6.1%' ),
		),
	),
	'webgeo' => array(
		'head' => array( 'Where visits come from, 12 months', 'Visits', 'Share' ),
		'rows' => array(
			array( 'United States', 3903058, '98.7%' ),
			array( 'New York State', 2826631, '69.4%' ),
			array( 'Named cities inside the ten counties (a floor, see below)', 1355264, '33.2%' ),
			array( 'New York visits with no city on record', 374213, '9.2%' ),
		),
	),
	'cities' => array(
		'head' => array( 'City', 'Visits', 'Distinct active users', 'Pageviews', 'County' ),
		'rows' => array(
			array( 'Geneva', 192205, 50581, 461261, 'Ontario' ),
			array( 'Auburn', 188460, 68928, 378319, 'Cayuga' ),
			array( 'New York', 161747, 81853, 309192, 'Outside the ten counties' ),
			array( 'Canandaigua', 152926, 52176, 335461, 'Ontario' ),
			array( 'Rochester', 138297, 56742, 297506, 'Outside the ten counties' ),
			array( 'Penn Yan', 136901, 31080, 339990, 'Yates' ),
			array( 'Seneca Falls', 105727, 27222, 246283, 'Seneca' ),
			array( 'Albany', 86428, 35878, 154774, 'Outside the ten counties' ),
			array( 'Buffalo', 79087, 36841, 143329, 'Outside the ten counties' ),
			array( 'Waterloo', 73828, 21020, 174791, 'Seneca' ),
			array( 'Victor', 55546, 22481, 106456, 'Ontario' ),
			array( 'Cheektowaga', 55498, 27366, 97296, 'Outside the ten counties' ),
		),
	),
	'channels' => array(
		'head' => array( 'Channel, 12 months', 'Visits', 'Share of visits', 'Distinct active users', 'Engaged visits' ),
		'rows' => array(
			array( 'Organic Search', 1842861, '46.3%', 709111, '67.3%' ),
			array( 'Organic Social', 1051379, '26.4%', 546578, '25.0%' ),
			array( 'Direct', 811192, '20.4%', 292717, '52.3%' ),
			array( 'Email', 212237, '5.3%', 61908, '53.0%' ),
			array( 'Referral', 42757, '1.1%', 14863, '67.9%' ),
		),
	),
	'sources' => array(
		'head' => array( 'Source and medium', 'Visits', 'Share of visits' ),
		'rows' => array(
			array( 'google / organic', 1487890, '37.6%' ),
			array( '(direct) / (none)', 833202, '21.1%' ),
			array( 'm.facebook.com / referral', 264434, '6.7%' ),
			array( 'lm.facebook.com / referral', 250648, '6.3%' ),
			array( 'facebook.com / referral', 212206, '5.4%' ),
			array( 'facebook / social', 200215, '5.1%' ),
			array( 'fldn / email', 162031, '4.1%' ),
			array( 'news.google.com / referral', 150289, '3.8%' ),
			array( 'bing / organic', 102229, '2.6%' ),
			array( 'l.facebook.com / referral', 84173, '2.1%' ),
		),
	),
	'webdevice' => array(
		'head' => array( 'Device', 'Visits (after our filter)', 'Share' ),
		'rows' => array(
			array( 'Mobile', 2938177, '73.9%' ),
			array( 'Desktop', 890604, '22.4%' ),
			array( 'Tablet', 145886, '3.7%' ),
		),
	),
	'sections' => array(
		'head' => array( 'Section, 12 months', 'Pageviews', 'Share of pageviews' ),
		'rows' => array(
			array( 'Local and regional news', 3560206, '45.6%' ),
			array( 'Police blotter', 1538530, '19.7%' ),
			array( 'Homepage', 1443208, '18.5%' ),
			array( 'Obituaries', 881899, '11.3%' ),
			array( 'Radio stations, events and contests', 202189, '2.6%' ),
			array( 'Weather and closings', 96540, '1.2%' ),
			array( 'Everything else', 87589, '1.1%' ),
		),
	),
	'news' => array(
		'head' => array( 'Month', 'Sends', 'Subscribers at last send', 'Net change from prior month', 'Average click rate', 'Average open rate' ),
		'rows' => array(
			array( 'Oct 2025 (starting point)', 31, 2656, 'n/a', '16.8%', '54.5%' ),
			array( 'Nov 2025', 29, 2719, 63, '14.2%', '54.2%' ),
			array( 'Dec 2025', 31, 2726, 7, '14.6%', '52.8%' ),
			array( 'Jan 2026', 32, 2835, 109, '14.7%', '53.1%' ),
			array( 'Feb 2026', 33, 2972, 137, '16.2%', '53.1%' ),
			array( 'Mar 2026', 33, 3140, 168, '15.2%', '51.6%' ),
			array( 'Apr 2026', 34, 3387, 247, '14.1%', '50.4%' ),
			array( 'May 2026', 36, 3762, 375, '13.5%', '46.2%' ),
			array( 'Jun 2026', 33, 4138, 376, '13.1%', '48.6%' ),
			array( 'Jul 2026', 34, 4347, 209, '13.4%', '48.5%' ),
			array( 'Aug 2026', 31, 4655, 308, '11.8%', '48.2%' ),
			array( 'Sep 2026', 32, 4787, 132, '12.0%', '47.1%' ),
			array( 'Oct 2026', 33, 4967, 180, '11.8%', '46.1%' ),
			array( 'Nov 2026 (1 to 20)', 21, 5066, 99, '10.6%', '43.9%' ),
		),
	),
	'subgeo' => array(
		'head' => array( 'Where subscribers live (by ZIP on file)', 'Subscribers', 'Share of all subscribers', 'Share of those with a ZIP' ),
		'rows' => array(
			array( 'Ten Finger Lakes counties (by ZIP)', 1961, '38.7%', '73.2%' ),
			array( 'Other New York ZIP', 426, '8.4%', '15.9%' ),
			array( 'ZIP outside New York', 293, '5.8%', '10.9%' ),
			array( 'No ZIP on file', 2386, '47.1%', 'n/a' ),
		),
	),
);

/**
 * Print one data table inside the scrolling wrapper. Cells that are numbers (or look
 * like one, such as "62.2%" or "$76,603") are right-aligned; $total marks a closing row.
 */
$tbl = function ( $caption, $table, $total = false ) {
	$head = $table['head'];
	$rows = $table['rows'];
	$last = count( $rows ) - 1;
	echo '<div class="flx-table-wrap"><table class="flx-table"><caption>' . esc_html( $caption ) . '</caption><thead><tr>';
	foreach ( $head as $h ) {
		echo '<th scope="col">' . esc_html( $h ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $rows as $i => $row ) {
		echo ( $total && $i === $last ) ? '<tr class="flx-table__total">' : '<tr>';
		foreach ( $row as $j => $cell ) {
			$is_num = ( $j > 0 ) && ( is_int( $cell ) || is_float( $cell ) || preg_match( '#^[$]?[\d][\d,.]*[%]?$|^n/a$#u', (string) $cell ) );
			$shown  = ( is_int( $cell ) || is_float( $cell ) ) ? number_format_i18n( $cell ) : (string) $cell;
			echo '<td' . ( $is_num ? ' class="flx-table__num"' : '' ) . '>' . esc_html( $shown ) . '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table></div>';
};

flxlm_pg_begin( array(
	'accent'  => 'navy',
	'eyebrow' => 'Methodology',
	'title'   => 'How we count our audience',
	'lead'    => 'Every number on this site, where it comes from, how it is built, and what it cannot tell you.',
	'cta'     => false,
) );
get_header();
flxlm_pg_open();
?>

<section class="section sol-sec" aria-labelledby="m-intro">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="flx-eyebrow">Read this first</span>
			<h2 class="section__title" id="m-intro">Some numbers are counted. Some are modeled. We label each one.</h2>
			<p class="sol-head__text">The Finger Lakes is not a Nielsen-rated radio market, so no station here, ours or anyone else's, has official listener counts. What we can do is count what can be counted, measure what can be measured, and show our work on the rest. This page is the work.</p>
		</div>

		<div class="stats-row">
			<div class="stats-row__inner">
				<div class="stats-row__item">
					<div class="stats-row__number">697,705</div>
					<div class="stats-row__label">People in the ten counties</div>
				</div>
				<div class="stats-row__item">
					<div class="stats-row__number">825K</div>
					<div class="stats-row__label">People inside our seven FM signal areas</div>
				</div>
				<div class="stats-row__item">
					<div class="stats-row__number">46,871</div>
					<div class="stats-row__label">Hours of streaming in the last 30 days</div>
				</div>
				<div class="stats-row__item">
					<div class="stats-row__number">717,725</div>
					<div class="stats-row__label">Finger Lakes Daily News pageviews in October</div>
				</div>
			</div>
		</div>

		<div class="methodology-sources">
			<div class="methodology-source">
				<h3>Counted or measured</h3>
				<p class="methodology-source__detail">Exact, from records</p>
				<p>Census population and commuting, streaming listening hours from our stream logs, Finger Lakes Daily News traffic from Google Analytics, and newsletter subscribers from Mailchimp. Each has its own section below, with the filters we apply.</p>
			</div>
			<div class="methodology-source">
				<h3>Calculated from maps</h3>
				<p class="methodology-source__detail">Exact method, predicted signal</p>
				<p>How many people live inside each station's FCC signal area. The map lines are official and the Census counts are exact, but a signal is a prediction and people are not spread evenly, so we give a margin of error.</p>
			</div>
			<div class="methodology-source">
				<h3>Modeled</h3>
				<p class="methodology-source__detail">An estimate, with judgment in it</p>
				<p>The weekly listener numbers on station pages. They are a formula, not a survey. We show every input, which parts are sourced, which are assumed, and which are our own judgment.</p>
			</div>
		</div>

		<div class="post-content">
			<p><strong>On this page:</strong>
				<a href="#m-market">The market</a>,
				<a href="#m-coverage">signal coverage</a>,
				<a href="#m-radio">radio listening</a>,
				<a href="#m-streaming">streaming</a>,
				<a href="#m-web">website and newsletter</a>,
				<a href="#m-sources">data sources</a> and
				<a href="#m-limits">what we do not claim</a>.</p>
		</div>
	</div>
</section>

<section class="section section--gray sol-sec" id="m-market" aria-labelledby="m-market-h">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="flx-eyebrow">The market</span>
			<h2 class="section__title" id="m-market-h">Ten counties, 697,705 people</h2>
			<p class="sol-head__text">Everything local on this site starts from the same ten New York counties: Ontario, Cayuga, Seneca, Wayne, Yates, Schuyler, Steuben, Chemung, Livingston and Tompkins.</p>
		</div>

		<?php $tbl( 'The ten counties, U.S. Census Bureau American Community Survey 5-year estimates, 2018 to 2022', $d['counties'], true ); ?>
		<p class="methodology-link">Census tables B01003 (population), B01001 (age and sex, summed for ages 18 and up), B08301 (how workers get to work), B25003 (own or rent) and B19013 (median household income). The ten-county median income is not a sum of county medians, so it is not shown. We re-pulled every county from the Census API on November 20, 2026 and the totals match.</p>

		<div class="post-content">
			<h3>Adults are about 80% of the population, not 78%</h3>
			<p>Across the counties each of our stations uses, adults 18 and older are 79.5% to 80.6% of the population (80.6% for all ten counties: 562,605 of 697,705). Tompkins County, home to Cornell and Ithaca College, is 85.5%. Our listener model assumes 78%, which is slightly low. The effect is in the radio listening section below.</p>
			<h3>Four versions of the same population</h3>
			<p>The ten-county total depends on which Census product you use. They agree within 1.3%, and we use the first one everywhere so that every figure on the site shares one basis.</p>
		</div>
		<?php
		$tbl( 'Population of the ten counties, by Census product', array(
			'head' => array( 'Census product', 'Years', 'Population', 'Used on this site' ),
			'rows' => array(
				array( 'American Community Survey 5-year', '2018 to 2022', 697705, 'Yes, everywhere' ),
				array( 'Decennial Census head count', 'April 2020', 701781, 'No, shown for comparison' ),
				array( 'American Community Survey 5-year', '2020 to 2024', 694434, 'Commute minutes on station pages' ),
				array( 'Census population estimate', 'July 2024', 692787, 'No, shown for comparison' ),
			),
		) );
		?>

		<div class="post-content">
			<h3>How long people drive</h3>
			<p>The ten counties have 317,608 workers. 238,179 of them (75.0%) drive alone to work, and 78,777 (28.3%) travel 30 minutes or more each way. The average commute is 22.2 minutes. Commute by station market:</p>
		</div>
		<?php $tbl( 'Commuting by station market, American Community Survey 2020 to 2024', $d['commute'] ); ?>
		<p class="methodology-link">Census tables B08013 (total travel time) and B08303 (travel time to work). The minutes match the figures printed on each station page.</p>
	</div>
</section>

<section class="section sol-sec" id="m-coverage" aria-labelledby="m-coverage-h">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="flx-eyebrow">Signal coverage</span>
			<h2 class="section__title" id="m-coverage-h">Three ways to say how many people a station reaches</h2>
			<p class="sol-head__text">"Coverage" can mean three different things, and the numbers are far apart. We show all three side by side so you can pick the one that fits your question. Coverage is who can hear a signal. It is not who listens.</p>
		</div>

		<div class="methodology-sources">
			<div class="methodology-source">
				<h3>County sum</h3>
				<p class="methodology-source__detail">The simplest, and the biggest</p>
				<p>Add up the population of every county we list for the station. A county counts in full whether the signal reaches 5% of it or all of it, so this is the most generous definition.</p>
			</div>
			<div class="methodology-source">
				<h3>FCC 60 dBu</h3>
				<p class="methodology-source__detail">The strong signal</p>
				<p>People inside the line where the FCC predicts a strong signal, the level it protects from interference. Solid in a car and usually inside buildings. This is the smallest area.</p>
			</div>
			<div class="methodology-source">
				<h3>FCC 54 dBu</h3>
				<p class="methodology-source__detail">The signal area on our maps</p>
				<p>People inside the weaker outer line. A car radio usually still plays fine there, but buildings and hills hurt more. FM signals only, because AM is a different calculation. This is the area drawn on our station maps.</p>
			</div>
		</div>

		<?php $tbl( 'People inside each station\'s coverage, three definitions (Census 2022 population)', $d['coverage'] ); ?>
		<p class="methodology-link">WGVA and WAUB are estimates. The FCC has no licensed contour record for their translators (W292FG on 106.3 and W242DC on 96.3), so we drew a 7.5-mile circle around an assumed location instead, and the 60 and 54 dBu columns are the same number. Treat those two rows as rough. Finger Lakes Country broadcasts on FM through four translators, which is why its FM area is much smaller than the six counties it is listed under.</p>

		<div class="post-content">
			<p>The spread is wide. For Mix 98.5 the same station is 115,000, 210,000 or 338,000 people depending on the definition. That is why every coverage number on this site names its definition beside it.</p>
			<h3>All seven stations together</h3>
			<p>Combine the FM signal areas of all seven stations at 54 dBu and about 825,000 people live inside at least one of them (824,952). That is more than the ten-county population because The Lake's signal reaches Monroe County, which is outside our ten counties. Of the 697,705 people in the ten counties, 347,971 (49.9%) are inside at least one of our signals at 54 dBu, and 258,984 (37.1%) at 60 dBu.</p>
		</div>
		<?php $tbl( 'How many of our seven stations reach the same person, ten-county population', $d['overlap'] ); ?>
		<p class="methodology-link">Adding the seven stations one at a time gives 840,950 station reaches at 54 dBu among 347,971 people, so a person we reach is typically reached by 2.4 of our stations. That is why we do not add station audiences together.</p>

		<div class="post-content">
			<h3>How the signal areas were built</h3>
			<ol>
				<li><strong>The contour.</strong> For each FM signal we asked the FCC's public contour service (geo.fcc.gov) for the 54 and 60 dBu lines. It returns a shape built from 360 straight lines out from the antenna, each using the licensed power and the antenna height above the terrain along that line. It had a record for 10 of our 12 FM signals.</li>
				<li><strong>An independent check.</strong> Our own terrain model, built from the same FCC curves over 30-meter elevation data, reaches within 0.2 km of the FCC lines for Mix 98.5 and 101.7 The Wall.</li>
				<li><strong>The people.</strong> We took every Census block group in 21 central and western New York counties (the smallest area the Census publishes, about 600 to 3,000 people), removed lakes of a quarter of a square kilometer or more so Seneca and Cayuga Lakes are not credited with residents, and spread each block group's people evenly over its land.</li>
				<li><strong>The count.</strong> For each contour we add up the share of every block group that falls inside it. Adding the block groups back into whole counties reproduces the Census county totals exactly, in all 21 counties.</li>
			</ol>
			<p><strong>The margin of error.</strong> Step 3 is the weak point, because people are not spread evenly across a block group. Any single contour count is good to roughly plus or minus 20%. Counts over large areas are tighter than counts over one small town. As a cross-check, the three full-power FM stations combined cover 169,324 people at 60 dBu by this method, against 164,130 in an earlier internal terrain study, a 3% difference.</p>
			<h3>Towns inside the signal</h3>
			<p>We also count the cities, villages and Census-designated places with at least half their area inside each contour. These are Census places, not New York "towns", so a rural town with no village in it will not appear.</p>
		</div>
		<?php $tbl( 'Places mostly inside each station\'s FM signal area', $d['towns'] ); ?>
		<p class="methodology-link">The 54 dBu column is the area drawn on our station maps. WGVA and WAUB again rest on estimated circles.</p>
	</div>
</section>

<section class="section section--gray sol-sec" id="m-radio" aria-labelledby="m-radio-h">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="flx-eyebrow">Radio listening</span>
			<h2 class="section__title" id="m-radio-h">How a weekly listener estimate is built</h2>
			<p class="sol-head__text">The weekly listener numbers on station pages are modeled, not measured. Here is the formula, each input, and where each one comes from.</p>
		</div>

		<div class="post-content">
			<p>"Weekly listeners" means how many different adults tune in at least once in a week. The radio industry calls this the weekly cume. We calculate it in six steps, then compare it with an older generic figure and keep the higher of the two.</p>
		</div>
		<?php
		$tbl( 'The formula, step by step', array(
			'head' => array( 'Step', 'What we do', 'Source', 'What kind of input' ),
			'rows' => array(
				array( '1. Pick the counties', 'Add up the Census population of the counties the signal reaches.', 'Census ACS table B01003, 2022', 'Sourced' ),
				array( '2. Find the adults', 'Multiply by 78%, the assumed share of people aged 18 and up.', 'Our assumption. Actual is 79.5% to 80.6%.', 'Assumed, slightly low' ),
				array( '3. Find radio listeners', 'Multiply by 92%, the share of adults who listen to radio.', 'Nielsen Comparable Metrics. This is a monthly figure, see below.', 'Sourced, but mislabeled as weekly' ),
				array( '4. Apply the format share', 'Multiply by the national audience share of the station\'s format (country, adult contemporary, classic rock, news and talk), times 1.5 for a small market, times a local dominance factor of 1.2 to 1.6 for stations that are the only one of their format here.', 'National shares from Nielsen. The last two factors are our judgment.', 'Part sourced, part judgment' ),
				array( '5. Compare with the older figure', 'Where an older generic format estimate from the Radio Advertising Bureau and GfK MRI exists, keep whichever number is higher.', 'RAB and GfK, origin not recorded', 'Third party and old' ),
				array( '6. Round and publish', 'The result is the weekly estimate.', 'Run on April 10, 2026', 'Modeled' ),
			),
		) );
		?>

		<div class="post-content">
			<h3>A worked example: Mix 98.5</h3>
			<p>Mix 98.5's five counties are Cayuga, Ontario, Seneca, Wayne and Yates. Here is every step of the arithmetic.</p>
		</div>
		<?php
		$tbl( 'Mix 98.5 (WNYR), step by step', array(
			'head' => array( 'Step', 'Value' ),
			'rows' => array(
				array( 'People in the five counties (Census 2022)', '338,147' ),
				array( 'Adults at 78%', '263,755' ),
				array( 'Radio listeners at 92%', '242,654' ),
				array( 'Effective share: 7.2% national adult contemporary share x 1.5 small market x 1.3 local dominance', '14.0%' ),
				array( 'Formula result', '33,972' ),
				array( 'Older RAB and GfK figure for the same format', '44,622' ),
				array( 'Kept: the higher of the two, 16.9% of adults', '44,622' ),
			),
		) );
		?>
		<p class="methodology-link">101.7 The Wall works the same way and used the formula: 248,440 people, 193,783 adults, 178,281 radio listeners, times an 11.0% share, gives 19,611 (published as 19,610). Its older figure of 17,500 was lower, so the formula won. Classic Hits 99.3 kept the older figure, which was higher than its formula result.</p>

		<div class="post-content">
			<h3>The counties the model counts, and their adults</h3>
			<p>The model counts adults from the counties it assigns to each station. For some stations those differ from the counties on the station page. We show the model's set here so the inputs are visible.</p>
		</div>
		<?php $tbl( 'Census inputs by station, Census 2022', $d['adults'] ); ?>
		<p class="methodology-link">Adult counts are the sum of Census table B01001 age groups from 18 up, re-pulled from the Census API on November 20, 2026.</p>

		<div class="post-content">
			<h3>Two corrections we made to our own method</h3>
			<ul>
				<li><strong>Nielsen's 92% is a monthly number.</strong> Nielsen reports that radio reaches 92% of U.S. adults in a month (Nielsen Comparable Metrics, Q2 2025, as reported by Radio Ink). Weekly reach was 87% in Q1 2026 (Nielsen Audience Insights via Katz Radio Group, as reported by Radio Ink). Our model applies 92% as if it were weekly. At 87%, every formula result would be 5.4% lower. Both are trade-press reports of Nielsen figures, not the Nielsen releases themselves.</li>
				<li><strong>The adult share is higher than we assumed.</strong> Actual adults are 79.5% to 80.6%, against our 78%. Using real counts would raise each formula result by 2.2% to 3.3%. The two corrections point in opposite directions and are of similar size.</li>
			</ul>
			<p>We have not changed any published listener estimate. Station pages show the numbers as originally calculated.</p>
			<h3>Commuters: car, truck or van, not drive alone</h3>
			<p>Where a station page shows the share of workers who commute by car, the figure is the Census count of workers who travel by "car, truck or van" (table B08301, line 2). That includes carpools. The narrower count of workers who drive alone (line 3) is lower. For the ten counties it is 238,179 against 265,140, about 10% fewer. We label the station-page figure "commute by car, truck or van" for that reason. The 75.0% drive-alone share in the market section above is the narrower count.</p>
		</div>

		<?php
		$tbl( 'Check: weekly estimate against the people inside the FM signal area', array(
			'head' => array( 'Station', 'People inside the 54 dBu FM area', 'Weekly estimate as a share of those people', 'Reading' ),
			'rows' => array(
				array( 'Mix 98.5 (44,622)', 210418, '21.2%', 'Fits' ),
				array( 'Classic Hits 99.3 (38,423)', 236744, '16.2%', 'Fits' ),
				array( '101.7 The Wall (19,610)', 131042, '15.0%', 'Fits' ),
				array( 'Finger Lakes Country (not published here)', 96570, '54.3%', 'Would need over half of all adults inside its four-translator FM area to tune in each week. AM adds reach but cannot close a gap this size.' ),
				array( 'WGVA (not published here)', 29475, '115.5%', 'Exceeds every resident inside its estimated FM circle. AM adds reach but cannot close a gap this size.' ),
			),
		) );
		?>
		<p class="methodology-link">This is a limitation of the model, stated plainly. The three published estimates fit inside their FM signal areas. For the other stations the model and the coverage map do not reconcile, and estimates for those stations are not published on this site. People inside are from the 54 dBu FM outlines (WGVA's is an estimated circle).</p>

		<div class="post-content">
			<h3>What is measured, what is assumed, what is judgment</h3>
			<ul>
				<li><strong>Measured:</strong> county population and workers (Census), and streaming listening (our stream logs).</li>
				<li><strong>Assumed:</strong> adults at 78% of people, and 92% as the share who listen in a week.</li>
				<li><strong>Judgment:</strong> the small-market multiplier of 1.5, the local dominance factor, and the rounding of the effective share. Together they roughly double or triple the national format share, and they move the result more than any other input.</li>
				<li><strong>Third party and old:</strong> the RAB and GfK figures behind Mix 98.5, Classic Hits 99.3 and WAUB, whose original basis is not recorded.</li>
				<li><strong>Derived figures:</strong> the daily, monthly and in-car numbers on some station pages are multiples of the weekly figure that rest on national rules of thumb we could not trace to a published source. Treat them as rough.</li>
			</ul>
		</div>
	</div>
</section>

<section class="section sol-sec" id="m-streaming" aria-labelledby="m-streaming-h">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="flx-eyebrow">Streaming</span>
			<h2 class="section__title" id="m-streaming-h">Streaming is the one audience we can count exactly</h2>
			<p class="sol-head__text">Our seven stations stream through SecureNet. Every press of play writes one log line: when, which station, how long, the device's internet address and the app or browser. A nightly job copies those lines into BigQuery and we add up the seconds. That is listening time, measured, not surveyed.</p>
		</div>

		<div class="stats-row">
			<div class="stats-row__inner">
				<div class="stats-row__item">
					<div class="stats-row__number">46,871</div>
					<div class="stats-row__label">Listening hours, last 30 days</div>
				</div>
				<div class="stats-row__item">
					<div class="stats-row__number">64,982</div>
					<div class="stats-row__label">Listening sessions</div>
				</div>
				<div class="stats-row__item">
					<div class="stats-row__number">11,628</div>
					<div class="stats-row__label">Unique listening devices</div>
				</div>
				<div class="stats-row__item">
					<div class="stats-row__number">70.9%</div>
					<div class="stats-row__label">Of hours from New York State</div>
				</div>
			</div>
		</div>

		<?php $tbl( 'Listening by station, last 30 days (October 21 to November 19, 2026)', $d['stations30'], true ); ?>
		<p class="methodology-link">A unique device is one distinct internet address. A household counts once, and one person on a phone and on home wifi counts twice, so it is not a head count. The total is not the sum of the rows because a device that listens to two stations is counted once. Location is guessed from the internet address: accurate to the state and market, rough at town level. Home markets are the Rochester, Syracuse and Elmira areas.</p>

		<div class="post-content">
			<h3>WAUB and WGVA: 6 AM to midnight only</h3>
			<p>For WAUB and WGVA we leave out midnight to 6 AM everywhere. About half of those two stations' overnight sessions came from automated players, which inflates their numbers. Over the last 28 days (October 22 to November 18) that removed 623 hours from WAUB and 979 hours from WGVA. Station pages show the 6 AM to midnight figures: <strong>WAUB 2,944 hours and WGVA 4,042 hours per 28 days</strong>. In the seven-station totals, the rule removed 1,734 hours from the 30-day figure and 5,175 hours from the 90-day figure.</p>
		</div>
		<?php $tbl( 'WAUB and WGVA streaming hours, last 28 days', $d['overnight'] ); ?>
		<p class="methodology-link">The 30-day table above covers a slightly longer window (WAUB 3,083 hours, WGVA 4,314 hours), which is why those figures are higher than the 28-day figures here. Earlier station pages printed 3,571 and 4,976, which included the overnight hours.</p>

		<div class="post-content">
			<h3>Local listeners listen longer</h3>
			<p>Listeners in our home markets average 7.3 hours each over 30 days. Listeners elsewhere average 2.7 hours, 2.7 times less. At any moment, about 65 people are listening across all seven streams, 34 of them in the home markets.</p>
		</div>
		<?php $tbl( 'Home markets and everywhere else, last 30 days', $d['localnon'] ); ?>

		<div class="post-content">
			<h3>Where listening comes from</h3>
		</div>
		<?php $tbl( 'Top ten markets by listening hours, last 30 days', $d['dmas'] ); ?>
		<p class="methodology-link">"Unknown location" is a device whose internet address could not be placed.</p>

		<div class="post-content">
			<h3>How people listen, and when</h3>
		</div>
		<?php $tbl( 'Device and app, last 30 days (devices under 100 hours not shown)', $d['devices'] ); ?>
		<p class="methodology-link">Device type is read from the player's self-reported name. Phone-app hours include FLX.fm and the older station apps, which cannot be separated this way.</p>
		<?php $tbl( 'Time of day, last 30 days', $d['daypart'] ); ?>
		<p class="methodology-link">The midnight to 6 AM row excludes WAUB and WGVA, per the rule above.</p>

		<div class="post-content">
			<h3>The honest trend</h3>
			<p>Home-market listening has held steady at roughly 23,500 to 26,800 hours a month since May. Total hours grew, so the home-market share slid from 64% in May to 47% in October, because listening from outside the area grew faster. Local listening did not shrink. A small group of heavy listeners (54 home devices at 100 or more hours each) supplies about a third of home-market hours. These are likely offices, shops and always-on devices.</p>
		</div>
		<?php $tbl( 'Monthly trend, May to November 2026', $d['monthly'] ); ?>

		<div class="post-content">
			<h3>How raw logs become reported hours</h3>
			<p>Only sessions of 60 seconds or more count. We remove known bots, monitoring tools, re-streamers and blank-device entries, and any device that logged more than 24 hours of audio in one day, which is physically impossible. Together the filters removed 17.8% of raw hours over 30 days.</p>
		</div>
		<?php $tbl( 'From raw logged hours to reported hours', $d['filters'] ); ?>
		<p class="methodology-link">November 13 had a reconnect spike (5,950 sessions at a 22-minute average, instead of about 2,100 at 48 minutes), adding roughly 450 hours. We left it in. Town-level rows for places such as Geneva and Seneca Falls have few devices and many hours each, so they likely include businesses and always-on players.</p>
	</div>
</section>

<section class="section section--gray sol-sec" id="m-web" aria-labelledby="m-web-h">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="flx-eyebrow">Website and newsletter</span>
			<h2 class="section__title" id="m-web-h">Finger Lakes Daily News: measured, then filtered for robots</h2>
			<p class="sol-head__text">The website and newsletter are measured, not estimated. Website numbers come from Google Analytics 4. Newsletter numbers come from Mailchimp, copied into BigQuery four times a day.</p>
		</div>

		<div class="stats-row">
			<div class="stats-row__inner">
				<div class="stats-row__item">
					<div class="stats-row__number">717,725</div>
					<div class="stats-row__label">Pageviews, October 2026</div>
				</div>
				<div class="stats-row__item">
					<div class="stats-row__number">7.81M</div>
					<div class="stats-row__label">Pageviews, last 12 months</div>
				</div>
				<div class="stats-row__item">
					<div class="stats-row__number">69.4%</div>
					<div class="stats-row__label">Of visits from New York State</div>
				</div>
				<div class="stats-row__item">
					<div class="stats-row__number">5,000<span>+</span></div>
					<div class="stats-row__label">Newsletter subscribers (5,066 today)</div>
				</div>
			</div>
		</div>

		<?php $tbl( 'Finger Lakes Daily News by month, November 2025 to October 2026 (robot traffic removed)', $d['webmonthly'], true ); ?>
		<p class="methodology-link">Pageviews: August 735,187, September 694,115, October 717,725. July was the peak at 848,124, and the 12-month average is 650,847 a month. A pageview is one page loaded. A session is one reader's trip to the site, ending after 30 quiet minutes. A user is one browser or device, so a person on a phone and a laptop counts twice. Monthly users cannot be built by adding daily users, since a reader who visits on 10 days would count 10 times, so we ask Google for each whole month. The 12-month user row is distinct users across the full year, not the sum of the months.</p>

		<div class="post-content">
			<h3>How automated traffic is removed</h3>
			<p>Since November 13, 2026 the website tables hold Google's numbers minus two kinds of desktop traffic that behave like scrapers: desktop computers running Linux, and first-time desktop visitors with no referring source. Phones and tablets are never filtered. Some real first-time desktop visitors who typed the address are removed too, so the filter errs on the cautious side. Both filtered and raw numbers are kept.</p>
		</div>
		<?php $tbl( 'What the robot filter removes, November 2025 to October 2026', $d['botfilter'] ); ?>

		<div class="post-content">
			<h3>Where readers are</h3>
			<p>Google reports city, not county, so we count a town as Finger Lakes only if every ZIP code with that name is in one of the ten counties (plus 16 smaller towns mapped by hand). Rochester and Syracuse are not counted. Because 9.2% of visits carry no city, the Finger Lakes share is a floor, not an exact value. Named Finger Lakes towns alone are at least 33% of all visits, and 47.9% of New York visits with a city.</p>
		</div>
		<?php $tbl( 'Where visits come from, November 2025 to October 2026', $d['webgeo'] ); ?>
		<?php $tbl( 'Top twelve New York cities, November 2025 to October 2026', $d['cities'] ); ?>

		<div class="post-content">
			<h3>How readers arrive, and what they read</h3>
			<p>Facebook is the largest single referrer. All of its referral and social sources combined are 25.8% of visits. Mobile is 73.9% of visits, desktop 22.4% and tablet 3.7%.</p>
		</div>
		<?php $tbl( 'Traffic by channel, November 2025 to October 2026', $d['channels'] ); ?>
		<?php $tbl( 'Top ten sources, November 2025 to October 2026', $d['sources'] ); ?>
		<?php $tbl( 'Visits by device, November 2025 to October 2026', $d['webdevice'] ); ?>
		<?php $tbl( 'Pageviews by section, November 2025 to October 2026', $d['sections'] ); ?>

		<div class="post-content">
			<h3>Where two sources disagree</h3>
			<p>Cloudflare, our website's network layer, logged 2,158,243 human page loads for October 2026, against 717,725 pageviews in Google Analytics, a 3.0 times gap. Cloudflare still counts scrapers that disguise themselves (42% of its October visits come from outside the U.S.), while Google Analytics misses people who block tracking. We report Google Analytics, the lower and more conservative number.</p>
			<h3>The newsletter</h3>
			<p>The newsletter went from 2,656 subscribers at the end of October 2025 to 4,967 at the end of October 2026, up 2,311 or 87.0%. It has 5,066 subscribers today. It sends one issue a day around noon Eastern plus occasional extras: 391 sends in 12 months, about 7.5 a week. Growth is the change in list size between sends, net of unsubscribes. Best month: June 2026, with 376 net new subscribers.</p>
			<p><strong>Click rate is the number to trust.</strong> Open rate is inflated, because Apple Mail Privacy Protection pre-loads images and Mailchimp records that as an open even if nobody read the email. The 12-month average open rate is 49.9% per send (49.3% weighted by list size), falling to 46.1% in October as the list doubled. On average 13.7% of subscribers click a link in each send, 11.8% in October, about 576 people per send.</p>
		</div>
		<?php $tbl( 'Newsletter by month', $d['news'] ); ?>
		<p class="methodology-link">The campaign data also shows zero unsubscribes and bounces for every send. That is a gap in the data sync, not a real zero, so we do not quote it.</p>
		<?php $tbl( 'Where subscribers live, November 20, 2026', $d['subgeo'] ); ?>
		<p class="methodology-link">Only 53% of subscribers have a ZIP code on file. Of those, 73.2% live in the ten Finger Lakes counties.</p>
	</div>
</section>

<section class="section sol-sec" id="m-sources" aria-labelledby="m-sources-h">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="flx-eyebrow">Data sources</span>
			<h2 class="section__title" id="m-sources-h">Where every number comes from</h2>
		</div>
		<?php
		$tbl( 'Data sources', array(
			'head' => array( 'Source', 'Table or system', 'What we take from it', 'Vintage or refresh' ),
			'rows' => array(
				array( 'U.S. Census Bureau, American Community Survey 5-year', 'B01003, B01001, B08301, B08303, B08013, B25003, B19013', 'Population, adults 18 and up, how workers get to work, travel time, homeownership, income', '2018 to 2022 (market figures); 2020 to 2024 (commute minutes)' ),
				array( 'U.S. Census Bureau, 2020 Census', 'P1_001N', 'Head count, shown for comparison', '2020' ),
				array( 'U.S. Census Bureau, boundary files', 'TIGER block groups, counties, water', 'Where people live, for counting inside a signal area', '2024 geometry, 2022 population' ),
				array( 'FCC contour service', 'geo.fcc.gov, 54 and 60 dBu FM contours', 'The shape of each licensed FM signal', 'Pulled November 2026; reflects current licenses' ),
				array( 'Nielsen, as reported by Radio Ink', 'Comparable Metrics Q2 2025; Audience Insights Q1 2026', 'National radio reach, monthly 92% and weekly 87%', 'Trade-press reports, not the releases' ),
				array( 'Radio Advertising Bureau and GfK MRI', 'Generic format estimates', 'The older figure behind three estimates', 'Origin not recorded' ),
				array( 'SecureNet stream logs, in BigQuery', 'v_streaming_raw_sessions', 'Listening hours, sessions, devices, location, device type', 'Copied nightly' ),
				array( 'Google Analytics 4', 'Finger Lakes Daily News property, with our robot filter', 'Pageviews, sessions, users, sources, geography, devices', 'Daily; filter since November 13, 2026' ),
				array( 'Cloudflare', 'Human-traffic view of edge logs', 'A cross-check on website traffic only', 'Daily' ),
				array( 'Mailchimp, in BigQuery', 'Campaign and subscriber tables', 'Subscribers, sends, open and click rates, ZIP codes', 'Four times a day' ),
				array( 'NL9, our ad traffic system', 'Orders and spot schedules', 'Not used for any audience figure on this page', 'Not applicable' ),
			),
		) );
		?>
	</div>
</section>

<section class="section section--gray sol-sec" id="m-limits" aria-labelledby="m-limits-h">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="flx-eyebrow">What we do not claim</span>
			<h2 class="section__title" id="m-limits-h">The limits of these numbers</h2>
			<p class="sol-head__text">A number you cannot argue with is usually one you cannot trust. These are the places where ours are weaker than they might look.</p>
		</div>
		<div class="post-content">
			<ul>
				<li><strong>We do not claim official ratings.</strong> No Nielsen survey covers this market. Weekly listener estimates are a model with assumptions and judgment in it, shown above.</li>
				<li><strong>Coverage is not listening.</strong> People inside a signal area can hear the station. Only some do. Reach is the ceiling and listening is a share of it.</li>
				<li><strong>Signal counts carry about 20% uncertainty.</strong> A predicted signal is not a guarantee, and people are not spread evenly across the land inside a block group.</li>
				<li><strong>WGVA and WAUB signal areas are estimates.</strong> They rest on 7.5-mile circles because the FCC has no record for their translators.</li>
				<li><strong>AM is not included in coverage.</strong> AM groundwave is a different calculation. Stations that also broadcast on AM (Finger Lakes Country, WGVA, WAUB and The Lake) are understated against what listeners can receive.</li>
				<li><strong>Two estimates do not reconcile with their coverage.</strong> WGVA and Finger Lakes Country, as shown in the check above.</li>
				<li><strong>The model's inputs are dated.</strong> The estimates were last run on April 10, 2026 with 2022 Census data.</li>
				<li><strong>Streaming is a small slice of radio.</strong> The streaming numbers measure streaming only. A unique device is not a person.</li>
				<li><strong>Streaming location is approximate.</strong> It is guessed from the internet address.</li>
				<li><strong>Website users are browsers, not people.</strong> The robot filter is cautious and may remove some real first-time desktop readers. The Finger Lakes share of visits is a lower bound.</li>
				<li><strong>Newsletter open rate is inflated.</strong> Click rate is the reliable measure.</li>
				<li><strong>The page is a snapshot.</strong> Streaming figures are for the 30 days to November 19, 2026, website figures for November 2025 to October 2026, and Census figures for 2018 to 2022.</li>
			</ul>
			<p>Found something that does not add up? Tell us. We would rather fix it than defend it.</p>
			<p><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="flx-btn">Get in touch</a></p>
		</div>
	</div>
</section>

<?php
flxlm_pg_close();
get_footer();
