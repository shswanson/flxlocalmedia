<?php
/**
 * Recruitment sources: the controlled vocabulary.
 *
 * "How did you hear about us?" is the single highest-value field on the whole
 * application form, for two unrelated reasons.
 *
 * COMPLIANCE. 47 CFR 73.2080(c)(6) requires the annual EEO Public File Report
 * to list, for each full-time vacancy filled, the recruitment sources used, the
 * number of interviewees each source referred, and the source that referred the
 * person actually hired. None of that is answerable unless every applicant
 * carries a source, recorded at intake.
 *
 * BUSINESS. Indeed costs $500 a month and nobody can currently say whether it
 * works, because nothing counts where applicants come from. This field answers
 * that question as a side effect of collecting it. It is also the only way the
 * company will ever learn what its own seven radio stations and news site are
 * worth as a recruiting channel, which is the one channel it owns outright and
 * pays nothing for.
 *
 * WHY IT IS A FIXED LIST AND NOT A TEXT BOX
 *
 * A free-text field produces "indeed", "Indeed.com", "saw it on indeed" and
 * "IND" and none of them aggregate. The report needs counts per source, so the
 * source has to be a value from a known set. This is also the fix for Defect 4
 * in the currently posted report, where the "source that referred the hire"
 * column was filled in with five employees' NAMES and published in a public FCC
 * file. A dropdown of sources cannot contain a person's name.
 *
 * WHO OWNS THIS LIST
 *
 * The defaults below are the sources the company actually uses today, taken
 * from the Master Recruitment Source List in the posted EEO report. Recruiting
 * channels change: a job board gets dropped, a college gets added. Rather than
 * requiring a developer for that, extra sources are stored in an option and
 * edited from the EEO Report screen by whoever files the report. Sources are
 * only ever ADDED, never removed, because an old application still has to
 * report the source it actually came from.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

const FLXLM_ATS_SOURCES_OPTION = 'flxlm_ats_extra_sources';

/**
 * The sources shipped with the plugin.
 *
 * Keys are stored in the database and must never change once in use; labels are
 * display only and can be reworded freely.
 *
 * @return array<string,string>
 */
function flxlm_ats_default_sources() {
	return array(
		'indeed'           => 'Indeed',
		'employee_referral' => 'Someone who works here',
		'linkedin'         => 'LinkedIn',
		'flx_station'      => 'Heard it on one of our radio stations',
		'fldn'             => 'Finger Lakes Daily News',
		'flxlocalmedia'    => 'FLX Local Media website',
		'facebook'         => 'Facebook',
		'college'          => 'A college or school',
		'walk_in'          => 'Walked in or called',
		'other'            => 'Somewhere else',
	);
}

/**
 * The full source list: shipped defaults plus anything added from the admin.
 *
 * @return array<string,string>
 */
function flxlm_ats_sources() {
	$extra = get_option( FLXLM_ATS_SOURCES_OPTION, array() );
	$extra = is_array( $extra ) ? $extra : array();

	/**
	 * Filter the recruitment source vocabulary.
	 *
	 * @param array<string,string> $sources key => label.
	 */
	return apply_filters( 'flxlm_ats_sources', array_merge( flxlm_ats_default_sources(), $extra ) );
}

/**
 * Whether a key names a real source.
 *
 * @param string $key Source key.
 * @return bool
 */
function flxlm_ats_is_source( $key ) {
	return array_key_exists( (string) $key, flxlm_ats_sources() );
}

/**
 * Display label for a source key.
 *
 * Falls back to the raw key rather than an empty string, so a source retired
 * from the list still reports as something legible on an old application.
 *
 * @param string $key Source key.
 * @return string
 */
function flxlm_ats_source_label( $key ) {
	$sources = flxlm_ats_sources();
	if ( isset( $sources[ $key ] ) ) {
		return $sources[ $key ];
	}
	return $key ? (string) $key : 'Not recorded';
}

/**
 * Add a source from the admin.
 *
 * Additive only. Removing a source would orphan the applications that carry it
 * and silently change historical report numbers.
 *
 * @param string $label Human label, e.g. "Ithaca College".
 * @return string|WP_Error The new key.
 */
function flxlm_ats_add_source( $label ) {
	$label = trim( wp_strip_all_tags( (string) $label ) );
	if ( '' === $label ) {
		return new WP_Error( 'flxlm_ats_empty_source', 'A source needs a name.' );
	}

	$key = sanitize_key( str_replace( array( ' ', '-' ), '_', strtolower( $label ) ) );
	if ( '' === $key ) {
		return new WP_Error( 'flxlm_ats_bad_source', 'That name cannot be used as a source.' );
	}

	$sources = flxlm_ats_sources();
	if ( isset( $sources[ $key ] ) ) {
		return $key; // Already present; adding again is a no-op, not an error.
	}

	$extra         = get_option( FLXLM_ATS_SOURCES_OPTION, array() );
	$extra         = is_array( $extra ) ? $extra : array();
	$extra[ $key ] = $label;
	update_option( FLXLM_ATS_SOURCES_OPTION, $extra );

	return $key;
}
