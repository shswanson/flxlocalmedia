<?php
/**
 * Careers: the blocks every job posting shares.
 *
 * Ported from fldn-theme 2026-09-28, closing the half of fldn#1623 that had
 * teeth. Until now flxlocalmedia rendered a job posting with no pay figure at
 * all, because the salary chip lived only in the news site's theme. New York
 * Labor Law 194-b wants a good faith range on any posting for work performed in
 * the state, so every posting on this site was publishing without one.
 *
 * Function names carry the flxlm_ prefix rather than fldn_ so the two themes can
 * never collide, but the BODY of each function is byte identical to FLDN's on
 * purpose: the whole point is that a benefit changed once changes everywhere,
 * and two subtly different copies of the benefits list is the exact failure this
 * file exists to prevent.
 *
 * About us, the benefits list, and the salary display live here rather than being
 * retyped into each posting. One definition means a posting cannot quietly claim a
 * benefit we do not offer, which is exactly what happened on 2026-08-25 when a
 * Marketing Manager posting went live promising three weeks of starting vacation
 * against an actual two.
 *
 * Benefits verified against the employee handbook and the full-time welcome letter,
 * 2026-08-26. When a benefit changes, change it HERE and every posting follows.
 * See memory `reference_flx_benefits` and shswanson/fldn#1604.
 *
 * @package flxlm
 */

defined( 'ABSPATH' ) || exit;

/**
 * The company blurb shown on every posting.
 *
 * @return string HTML.
 */
function flxlm_careers_about_html() {
	$html = '<p>FLX Local Media owns seven radio stations and Finger Lakes Daily News. We are the '
		. 'local media company for the Finger Lakes: the stations people wake up to, the newsroom '
		. 'that covers their village board, and the place local businesses come when they want to '
		. 'reach their neighbors. Our offices are in Geneva, Auburn, and Penn Yan.</p>';

	/**
	 * Filter the standard "About FLX Local Media" copy on job postings.
	 *
	 * @param string $html
	 */
	return apply_filters( 'flxlm_careers_about_html', $html );
}

/**
 * Whether a posting is for an employee (and therefore benefits-eligible) rather
 * than a freelancer or contractor.
 *
 * Benefits eligibility requires being scheduled 30+ hours per week, so contract and
 * per-story postings must never render the employee benefits list.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function flxlm_careers_is_benefits_eligible( $post_id ) {
	$type = strtolower( (string) get_post_meta( $post_id, 'job_type', true ) );

	$excluded = array( 'freelance', 'contract', 'per story', 'stringer', 'intern', 'temporary' );
	foreach ( $excluded as $needle ) {
		if ( false !== strpos( $type, $needle ) ) {
			return false;
		}
	}

	// Fail closed: with no job type set we cannot claim someone is benefits eligible.
	return '' !== $type;
}

/**
 * The standard employee benefits, as <li> rows.
 *
 * Deliberately describes dental and vision as employee-paid access rather than a
 * company benefit, because that is what they are.
 *
 * @return string HTML list items.
 */
function flxlm_careers_standard_benefits_items() {
	$items = array(
		'Health insurance through United HealthCare with three plan levels to choose from. The company pays half the cost of a single policy, beginning after a 30 day waiting period.',
		'Dental and vision available through Guardian, paid by the employee through payroll deduction. Enrollment is within 30 days of hire.',
		'Company contributions to an IRA retirement program, once eligibility is met.',
		'Paid vacation that begins accruing after 90 days, and 40 hours of paid sick leave per year.',
		'A real audience and real impact from day one, across seven radio stations and a growing digital platform.',
	);

	/**
	 * Filter the standard benefits shown on benefits-eligible postings.
	 *
	 * @param string[] $items Plain-text benefit lines.
	 */
	$items = apply_filters( 'flxlm_careers_standard_benefits', $items );

	$html = '';
	foreach ( $items as $item ) {
		$html .= '<li>' . esc_html( $item ) . '</li>';
	}

	return $html;
}

/**
 * Format the posted salary range for display.
 *
 * New York Labor Law 194-b requires a good-faith minimum and maximum on every
 * posting for a job performed in New York, for employers with four or more
 * employees. Rendering it here is what makes the requirement structural instead of
 * something each posting has to remember. 13 live postings were missing it.
 *
 * @param int $post_id Post ID.
 * @return string Display string, or '' when no range is set.
 */
function flxlm_careers_salary_display( $post_id ) {
	$min  = get_post_meta( $post_id, 'job_salary_min', true );
	$max  = get_post_meta( $post_id, 'job_salary_max', true );
	$unit = strtoupper( (string) get_post_meta( $post_id, 'job_salary_unit', true ) );

	if ( ! is_numeric( $min ) || (float) $min <= 0 ) {
		return '';
	}

	$suffix = array(
		'HOUR'  => 'per hour',
		'DAY'   => 'per day',
		'WEEK'  => 'per week',
		'MONTH' => 'per month',
		'YEAR'  => 'per year',
	);
	$per = isset( $suffix[ $unit ] ) ? $suffix[ $unit ] : 'per year';

	$fmt = static function ( $n ) {
		$n = (float) $n;
		return '$' . number_format( $n, ( floor( $n ) === $n ) ? 0 : 2 );
	};

	$range = ( is_numeric( $max ) && (float) $max > (float) $min )
		? $fmt( $min ) . ' to ' . $fmt( $max )
		: $fmt( $min );

	return $range . ' ' . $per;
}

/**
 * Return a posting's own "What We Offer" lines as bare <li> items.
 *
 * The meta field is inconsistent by history, not by design: the twelve
 * correspondent postings and a few others were written with a full <ul> wrapper
 * inside the field, while newer ones store bare <li> rows. The template now
 * composes one list from the posting's lines plus the shared benefits, so it
 * has to supply the <ul> itself, and an already wrapped value would nest a list
 * inside a list on fifteen of eighteen live postings.
 *
 * Unwrapping here rather than rewriting eighteen rows of postmeta keeps the fix
 * in one place and cannot corrupt anyone's copy.
 *
 * @param string $html Raw job_offer meta.
 * @return string <li> items, or '' when empty.
 */
function flxlm_careers_offer_items( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return '';
	}

	// Strip a single outer <ul>...</ul> when the field carries one.
	if ( preg_match( '#^<ul[^>]*>(.*)</ul>\s*$#is', $html, $m ) ) {
		return trim( $m[1] );
	}

	return $html;
}
