<?php
/**
 * The FCC EEO Public File Report screen.
 *
 * This is the point of the whole plugin. Everything else exists so that this
 * screen can be a query instead of a reconstruction.
 *
 * WHAT 47 CFR 73.2080(c)(6) ACTUALLY ASKS FOR, and where each answer comes from:
 *
 *   (i)   every full-time vacancy filled in the period, by job title
 *         -> applications carrying _flxlm_hired_at inside the period
 *   (ii)  the recruitment sources used for each vacancy
 *         -> the distinct sources of everyone who applied to that vacancy
 *   (iii) the source that referred the person hired
 *         -> the hired applicant's own recruitment source
 *   (iv)  the total number of interviewees per vacancy, and the number each
 *         source referred
 *         -> the one-way _flxlm_interviewed_at stamp, counted per vacancy and
 *            grouped by source
 *   (v)   the Master Recruitment Source List
 *         -> every source used in the period, with its totals
 *
 * THE TWO DEFECTS THIS SCREEN IS BUILT TO PREVENT
 *
 * The currently posted report states "Total Number of Persons Interviewed
 * During This Period: 0" while listing five vacancies filled and thirteen
 * interviewees by source. A self-contradicting zero is the most common EEO
 * audit finding there is. Here the interviewee count is derived from recorded
 * facts, so it cannot contradict the hire count, and the screen explicitly
 * flags any hire with no interview recorded rather than quietly reporting a
 * zero that happens to be true.
 *
 * The same report put five employees' NAMES in the column that is supposed to
 * name a recruitment source, publishing them in an FCC public file for no
 * reason. Nothing on this screen or in its export emits a person's name. That
 * is a structural property, not a habit: the export builds its rows from job
 * titles, source labels, counts and dates, and there is no code path that puts
 * a name into one.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the report screen.
 */
function flxlm_ats_add_report_screen() {
	add_submenu_page(
		'edit.php?post_type=flxlm_application',
		'EEO Report',
		'EEO Report',
		'flxlm_view_eeo_report',
		'flxlm-ats-eeo',
		'flxlm_ats_render_report_screen'
	);
}
add_action( 'admin_menu', 'flxlm_ats_add_report_screen' );

/**
 * The company's EEO reporting year.
 *
 * The posted reports run January 22 to January 21, so the period is expressed
 * that way rather than as a calendar year. Passing a year means the period
 * STARTING in that year: 2026 gives 2026-01-22 to 2027-01-21.
 *
 * @param int $start_year Year the period opens in.
 * @return array{from:string,to:string,label:string}
 */
function flxlm_ats_report_period( $start_year ) {
	$start_year = (int) $start_year;
	$from       = sprintf( '%04d-01-22 00:00:00', $start_year );
	$to         = sprintf( '%04d-01-21 23:59:59', $start_year + 1 );

	return array(
		'from'  => $from,
		'to'    => $to,
		'label' => sprintf( 'January 22, %d to January 21, %d', $start_year, $start_year + 1 ),
	);
}

/**
 * Gather every application that is relevant to a reporting period.
 *
 * An application counts toward the period if it was SUBMITTED in it. That is
 * the population the recruitment-source figures describe.
 *
 * @param array $period From flxlm_ats_report_period().
 * @return int[] Application IDs.
 */
function flxlm_ats_applications_in_period( $period ) {
	return get_posts(
		array(
			'post_type'      => 'flxlm_application',
			'post_status'    => array_keys( flxlm_ats_stages() ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => '_flxlm_submitted_at',
					'value'   => array( $period['from'], $period['to'] ),
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				),
			),
		)
	);
}

/**
 * Build the whole report as data.
 *
 * Returns plain arrays so the screen and the CSV export read from exactly the
 * same numbers. Two renderings of one query, never two queries.
 *
 * @param int $start_year Year the period opens in.
 * @return array
 */
function flxlm_ats_build_report( $start_year ) {
	$period = flxlm_ats_report_period( $start_year );
	$ids    = flxlm_ats_applications_in_period( $period );

	$vacancies = array();  // job title => figures
	$sources   = array();  // source key => figures
	$warnings  = array();

	$total_applicants   = 0;
	$total_interviewees = 0;
	$total_hires        = 0;

	foreach ( $ids as $id ) {
		$job         = flxlm_ats_job_title( $id );
		$source      = (string) get_post_meta( $id, '_flxlm_source', true );
		$interviewed = flxlm_ats_was_interviewed( $id );
		$hired_at    = (string) get_post_meta( $id, '_flxlm_hired_at', true );
		$hired       = ( '' !== $hired_at && $hired_at >= $period['from'] && $hired_at <= $period['to'] );

		++$total_applicants;

		if ( ! isset( $vacancies[ $job ] ) ) {
			$vacancies[ $job ] = array(
				'title'            => $job,
				'applicants'       => 0,
				'interviewees'     => 0,
				'hires'            => 0,
				'sources_used'     => array(),
				'hire_source'      => '',
				'interview_by_src' => array(),
			);
		}

		++$vacancies[ $job ]['applicants'];
		$vacancies[ $job ]['sources_used'][ $source ] = true;

		if ( ! isset( $sources[ $source ] ) ) {
			$sources[ $source ] = array(
				'key'          => $source,
				'applicants'   => 0,
				'interviewees' => 0,
				'hires'        => 0,
			);
		}
		++$sources[ $source ]['applicants'];

		if ( $interviewed ) {
			++$total_interviewees;
			++$vacancies[ $job ]['interviewees'];
			++$sources[ $source ]['interviewees'];

			if ( ! isset( $vacancies[ $job ]['interview_by_src'][ $source ] ) ) {
				$vacancies[ $job ]['interview_by_src'][ $source ] = 0;
			}
			++$vacancies[ $job ]['interview_by_src'][ $source ];
		}

		if ( $hired ) {
			++$total_hires;
			++$vacancies[ $job ]['hires'];
			++$sources[ $source ]['hires'];
			$vacancies[ $job ]['hire_source'] = $source;

			// The defect this plugin exists to prevent, caught at report time:
			// a hire with no interview recorded produces a truthful-looking
			// zero that an auditor reads exactly like the false one already in
			// the posted report.
			if ( ! $interviewed ) {
				$warnings[] = sprintf(
					'A hire for "%s" has no interview recorded. Either the interview was never logged, '
						. 'or the candidate was hired without one. Fix the record before filing: an '
						. 'interviewee count that disagrees with the hire count is the single most common '
						. 'EEO audit finding.',
					$job
				);
			}
		}
	}

	// Applicants whose source nobody has established yet. Recorded honestly at
	// intake rather than guessed, and surfaced here because the Master
	// Recruitment Source List is wrong until they are resolved. This is a task
	// for whoever files the report, not a defect in the data.
	if ( isset( $sources['unknown'] ) ) {
		$warnings[] = sprintf(
			'%d application(s) are recorded as "Not known yet" for recruitment source. Ask whoever '
				. 'passed them on where they came from and set it, or the Master Recruitment Source '
				. 'List under 73.2080(c)(6)(v) will undercount whichever source actually referred them.',
			$sources['unknown']['applicants']
		);
	}

	// Applications with no recruitment source at all. Cannot happen through any
	// intake path here, but a record imported or edited by hand could carry a blank.
	if ( isset( $sources[''] ) ) {
		$warnings[] = sprintf(
			'%d application(s) have no recruitment source recorded. The Master Recruitment Source List '
				. 'will be incomplete until those are filled in.',
			$sources['']['applicants']
		);
	}

	ksort( $vacancies );
	uasort(
		$sources,
		function ( $a, $b ) {
			return $b['applicants'] <=> $a['applicants'];
		}
	);

	return array(
		'period'             => $period,
		'vacancies'          => $vacancies,
		'sources'            => $sources,
		'warnings'           => $warnings,
		'total_applicants'   => $total_applicants,
		'total_interviewees' => $total_interviewees,
		'total_hires'        => $total_hires,
	);
}

/**
 * Render the report screen.
 */
function flxlm_ats_render_report_screen() {
	if ( ! current_user_can( 'flxlm_view_eeo_report' ) ) {
		wp_die( 'You do not have permission to view the EEO report.', 'Not allowed', array( 'response' => 403 ) );
	}

	// The current period is the one that has not closed yet. Before January 22
	// the open period is the one that started the previous calendar year.
	$now      = (int) current_time( 'Y' );
	$month    = (int) current_time( 'n' );
	$day      = (int) current_time( 'j' );
	$default  = ( 1 === $month && $day < 22 ) ? $now - 1 : $now;
	$selected = isset( $_GET['period'] ) ? (int) $_GET['period'] : $default;

	$report = flxlm_ats_build_report( $selected );
	?>
	<div class="wrap">
		<h1>FCC EEO Public File Report</h1>

		<form method="get" style="margin:1rem 0">
			<input type="hidden" name="post_type" value="flxlm_application" />
			<input type="hidden" name="page" value="flxlm-ats-eeo" />
			<label for="period"><strong>Reporting period</strong></label>
			<select name="period" id="period" onchange="this.form.submit()">
				<?php for ( $y = $now; $y >= $now - 5; $y-- ) : ?>
					<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $y, $selected ); ?>>
						<?php echo esc_html( flxlm_ats_report_period( $y )['label'] ); ?>
					</option>
				<?php endfor; ?>
			</select>
			<a class="button" style="margin-left:.5rem"
				href="<?php echo esc_url( flxlm_ats_export_url( $selected ) ); ?>">Download as CSV</a>
		</form>

		<?php foreach ( $report['warnings'] as $warning ) : ?>
			<div class="notice notice-warning"><p><?php echo esc_html( $warning ); ?></p></div>
		<?php endforeach; ?>

		<div style="display:flex;gap:1rem;margin:1.5rem 0;flex-wrap:wrap">
			<?php
			$tiles = array(
				'Applicants'   => $report['total_applicants'],
				'Interviewed'  => $report['total_interviewees'],
				'Hired'        => $report['total_hires'],
			);
			foreach ( $tiles as $label => $value ) :
				?>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:1rem 1.5rem;min-width:9rem">
					<div style="font-size:2rem;font-weight:600;line-height:1"><?php echo esc_html( $value ); ?></div>
					<div style="color:#666"><?php echo esc_html( $label ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<h2>Vacancies filled, and how</h2>
		<p class="description" style="max-width:46rem">
			Section 73.2080(c)(6)(i)&ndash;(iv). One row per job people applied for in this period.
			Only rows with a hire count above zero are vacancies "filled" for reporting purposes.
		</p>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th>Job title</th>
					<th>Applicants</th>
					<th>Interviewed</th>
					<th>Hires</th>
					<th>Source that referred the hire</th>
					<th>Recruitment sources used</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $report['vacancies'] ) : ?>
				<tr><td colspan="6">No applications in this period.</td></tr>
			<?php endif; ?>
			<?php foreach ( $report['vacancies'] as $vacancy ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $vacancy['title'] ); ?></strong></td>
					<td><?php echo esc_html( $vacancy['applicants'] ); ?></td>
					<td><?php echo esc_html( $vacancy['interviewees'] ); ?></td>
					<td><?php echo esc_html( $vacancy['hires'] ); ?></td>
					<td>
						<?php
						echo $vacancy['hire_source']
							? esc_html( flxlm_ats_source_label( $vacancy['hire_source'] ) )
							: '<span style="color:#999">—</span>';
						?>
					</td>
					<td>
						<?php
						$labels = array_map( 'flxlm_ats_source_label', array_keys( $vacancy['sources_used'] ) );
						echo esc_html( implode( ', ', array_filter( $labels ) ) );
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:2rem">Master Recruitment Source List</h2>
		<p class="description" style="max-width:46rem">
			Section 73.2080(c)(6)(v). Every source that produced an applicant in this period, and what it
			produced. This is also the honest answer to whether paid job boards are earning their cost.
		</p>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th>Recruitment source</th>
					<th>Applicants referred</th>
					<th>Interviewees referred</th>
					<th>Hires</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $report['sources'] ) : ?>
				<tr><td colspan="4">No applications in this period.</td></tr>
			<?php endif; ?>
			<?php foreach ( $report['sources'] as $source ) : ?>
				<tr>
					<td><?php echo esc_html( flxlm_ats_source_label( $source['key'] ) ); ?></td>
					<td><?php echo esc_html( $source['applicants'] ); ?></td>
					<td><?php echo esc_html( $source['interviewees'] ); ?></td>
					<td><?php echo esc_html( $source['hires'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:2rem">Recruitment sources</h2>
		<p class="description" style="max-width:46rem">
			The list applicants choose from. Add a source when the company starts using a new channel:
			a college, a job board, a jobs fair. Sources are never removed, because an application filed
			last year still has to report the source it actually came from.
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'flxlm_ats_add_source', 'flxlm_ats_source_nonce' ); ?>
			<input type="hidden" name="action" value="flxlm_ats_add_source" />
			<input type="text" name="source_label" class="regular-text" placeholder="e.g. Ithaca College" />
			<?php submit_button( 'Add source', 'secondary', 'submit', false ); ?>
		</form>

		<p class="description" style="max-width:46rem;margin-top:2rem">
			<?php echo esc_html( flxlm_ats_retention_note() ); ?>
		</p>
	</div>
	<?php
}

/**
 * Nonced URL for the CSV export.
 *
 * @param int $start_year Period.
 * @return string
 */
function flxlm_ats_export_url( $start_year ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'flxlm_ats_eeo_csv',
				'period' => (int) $start_year,
			),
			admin_url( 'admin-post.php' )
		),
		'flxlm_ats_eeo_csv'
	);
}

/**
 * Stream the report as CSV.
 *
 * Every column here is a job title, a source label, a count or a date. There is
 * deliberately no code path that can emit an applicant's or an employee's name,
 * because this file is destined for a public FCC filing.
 */
function flxlm_ats_export_eeo_csv() {
	check_admin_referer( 'flxlm_ats_eeo_csv' );

	if ( ! current_user_can( 'flxlm_view_eeo_report' ) ) {
		wp_die( 'You do not have permission to export the EEO report.', 'Not allowed', array( 'response' => 403 ) );
	}

	$start_year = isset( $_GET['period'] ) ? (int) $_GET['period'] : (int) current_time( 'Y' );
	$report     = flxlm_ats_build_report( $start_year );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="flx-eeo-report-' . $start_year . '.csv"' );

	$out = fopen( 'php://output', 'w' );

	fputcsv( $out, array( 'FLX Local Media — EEO Public File Report data' ) );
	fputcsv( $out, array( 'Reporting period', $report['period']['label'] ) );
	fputcsv( $out, array( 'Total applicants', $report['total_applicants'] ) );
	fputcsv( $out, array( 'Total interviewed', $report['total_interviewees'] ) );
	fputcsv( $out, array( 'Total hired', $report['total_hires'] ) );
	fputcsv( $out, array() );

	fputcsv( $out, array( 'Vacancies — 73.2080(c)(6)(i)-(iv)' ) );
	fputcsv(
		$out,
		array( 'Job title', 'Applicants', 'Interviewed', 'Hires', 'Source referring hire', 'Sources used' )
	);
	foreach ( $report['vacancies'] as $vacancy ) {
		$labels = array_map( 'flxlm_ats_source_label', array_keys( $vacancy['sources_used'] ) );
		fputcsv(
			$out,
			array(
				$vacancy['title'],
				$vacancy['applicants'],
				$vacancy['interviewees'],
				$vacancy['hires'],
				$vacancy['hire_source'] ? flxlm_ats_source_label( $vacancy['hire_source'] ) : '',
				implode( '; ', array_filter( $labels ) ),
			)
		);
	}

	fputcsv( $out, array() );
	fputcsv( $out, array( 'Master Recruitment Source List — 73.2080(c)(6)(v)' ) );
	fputcsv( $out, array( 'Recruitment source', 'Applicants referred', 'Interviewees referred', 'Hires' ) );
	foreach ( $report['sources'] as $source ) {
		fputcsv(
			$out,
			array(
				flxlm_ats_source_label( $source['key'] ),
				$source['applicants'],
				$source['interviewees'],
				$source['hires'],
			)
		);
	}

	if ( $report['warnings'] ) {
		fputcsv( $out, array() );
		fputcsv( $out, array( 'Check before filing' ) );
		foreach ( $report['warnings'] as $warning ) {
			fputcsv( $out, array( $warning ) );
		}
	}

	fclose( $out );
	exit;
}
add_action( 'admin_post_flxlm_ats_eeo_csv', 'flxlm_ats_export_eeo_csv' );

/**
 * Handle adding a recruitment source.
 */
function flxlm_ats_handle_add_source() {
	if ( ! isset( $_POST['flxlm_ats_source_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['flxlm_ats_source_nonce'] ) ), 'flxlm_ats_add_source' ) ) {
		wp_die( 'Security check failed.', 'Error', array( 'response' => 403 ) );
	}

	if ( ! current_user_can( 'flxlm_view_eeo_report' ) ) {
		wp_die( 'You do not have permission to manage sources.', 'Not allowed', array( 'response' => 403 ) );
	}

	flxlm_ats_add_source( isset( $_POST['source_label'] ) ? wp_unslash( $_POST['source_label'] ) : '' );

	wp_safe_redirect( admin_url( 'edit.php?post_type=flxlm_application&page=flxlm-ats-eeo' ) );
	exit;
}
add_action( 'admin_post_flxlm_ats_add_source', 'flxlm_ats_handle_add_source' );
