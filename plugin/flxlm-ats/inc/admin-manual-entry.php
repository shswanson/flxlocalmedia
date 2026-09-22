<?php
/**
 * Adding an applicant by hand.
 *
 * WHY THIS SCREEN EXISTS, AND WHY IT IS NOT OPTIONAL.
 *
 * A system that can only see people who used the web form produces an EEO
 * report that is wrong in exactly the direction the company's current one is
 * wrong. 47 CFR 73.2080(c)(6)(iv) asks for the total number of persons
 * interviewed for each vacancy, and for how many interviewees each recruitment
 * source referred. It does not ask "of the ones who used your website."
 *
 * In a seven-station radio company in a small market, a real share of applicants
 * walk into the Geneva office, call the station, hand a resume to someone at a
 * remote broadcast, or get referred by an employee over coffee. The posted 2025
 * report names "Employee Referral" as the source for five of thirteen
 * interviewees. If those five have no way into this system, the report goes out
 * saying five people were interviewed when thirteen were, and the company has
 * simply swapped one undercount for another while believing the software fixed
 * it.
 *
 * So: any staff member who can manage applications can enter one in under a
 * minute, with the same required fields as the public form, and it lands in the
 * same pipeline and the same report.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the screen under Applications.
 */
function flxlm_ats_add_manual_screen() {
	// Drop the post type's own "Add New" first. It points at post-new.php, which
	// would create a blank record with no applicant details and no recruitment
	// source, and leaving it in place puts two identically-labelled "Add
	// Applicant" items in the menu, one of which does the wrong thing.
	remove_submenu_page( 'edit.php?post_type=flxlm_application', 'post-new.php?post_type=flxlm_application' );

	add_submenu_page(
		'edit.php?post_type=flxlm_application',
		'Add Applicant',
		'Add Applicant',
		'flxlm_manage_applications',
		'flxlm-ats-add',
		'flxlm_ats_render_manual_screen'
	);
}
// Priority 11: the post type registers its own submenu at the default 10, so
// this has to run after it exists in order to remove it.
add_action( 'admin_menu', 'flxlm_ats_add_manual_screen', 11 );

/**
 * Replace the post type's own "Add New" with this screen.
 *
 * The native Add New would create an empty record with no applicant fields and
 * no recruitment source, which is precisely the kind of half-filled row that
 * makes a compliance report untrustworthy.
 *
 * @param string $url     Default URL.
 * @param string $path    Requested path.
 * @return string
 */
function flxlm_ats_redirect_add_new( $url, $path ) {
	if ( 'post-new.php?post_type=flxlm_application' === $path ) {
		return admin_url( 'edit.php?post_type=flxlm_application&page=flxlm-ats-add' );
	}
	return $url;
}
add_filter( 'admin_url', 'flxlm_ats_redirect_add_new', 10, 2 );

/**
 * Send anyone who reaches post-new.php directly to the proper form.
 */
function flxlm_ats_block_native_add_new() {
	global $pagenow;
	if ( 'post-new.php' !== $pagenow ) {
		return;
	}
	$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	if ( 'flxlm_application' !== $type ) {
		return;
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=flxlm_application&page=flxlm-ats-add' ) );
	exit;
}
add_action( 'admin_init', 'flxlm_ats_block_native_add_new' );

/**
 * Render the manual entry form.
 */
function flxlm_ats_render_manual_screen() {
	if ( ! current_user_can( 'flxlm_manage_applications' ) ) {
		wp_die( 'You do not have permission to add applicants.', 'Not allowed', array( 'response' => 403 ) );
	}

	$error = isset( $_GET['flxlm_error'] ) ? sanitize_text_field( wp_unslash( $_GET['flxlm_error'] ) ) : '';

	$jobs = get_posts(
		array(
			'post_type'      => 'flxlm_job',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	?>
	<div class="wrap">
		<h1>Add Applicant</h1>
		<p style="max-width:42rem">
			For someone who applied without using the website: walked in, called, emailed, or was
			referred by a colleague. They count on the FCC EEO report exactly like a web applicant,
			so recording them here is what keeps that report honest.
		</p>

		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'flxlm_ats_manual_add', 'flxlm_ats_manual_nonce' ); ?>
			<input type="hidden" name="action" value="flxlm_ats_manual_add" />

			<table class="form-table">
				<tr>
					<th><label for="m-first">First name <span class="description">(required)</span></label></th>
					<td><input type="text" id="m-first" name="first_name" class="regular-text" required /></td>
				</tr>
				<tr>
					<th><label for="m-last">Last name <span class="description">(required)</span></label></th>
					<td><input type="text" id="m-last" name="last_name" class="regular-text" required /></td>
				</tr>
				<tr>
					<th><label for="m-email">Email <span class="description">(required)</span></label></th>
					<td><input type="email" id="m-email" name="email" class="regular-text" required /></td>
				</tr>
				<tr>
					<th><label for="m-phone">Phone</label></th>
					<td><input type="text" id="m-phone" name="phone" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="m-job">Job they applied for</label></th>
					<td>
						<select id="m-job" name="job_id">
							<option value="0">General application (no specific posting)</option>
							<?php foreach ( $jobs as $job ) : ?>
								<option value="<?php echo esc_attr( $job->ID ); ?>">
									<?php echo esc_html( $job->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="m-source">How they heard about us <span class="description">(required)</span></label></th>
					<td>
						<select id="m-source" name="source" required>
							<option value="">Choose one...</option>
							<?php foreach ( flxlm_ats_manual_only_sources() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" selected>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
							<?php foreach ( flxlm_ats_sources() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>">
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">This is the field the FCC EEO report is built from.</p>
					</td>
				</tr>
				<tr>
					<th><label for="m-date">Date they applied</label></th>
					<td>
						<input type="date" id="m-date" name="applied_on"
							value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" />
						<p class="description">Set this to the real date if you are catching up on someone from a while ago.</p>
					</td>
				</tr>
				<tr>
					<th><label for="m-resume">Resume</label></th>
					<td>
						<input type="file" id="m-resume" name="resume" accept=".pdf,.doc,.docx,.odt,.rtf,.txt" />
						<p class="description">Optional. PDF or Word, up to 8 MB.</p>
					</td>
				</tr>
				<tr>
					<th><label for="m-links">Links to their work</label></th>
					<td><textarea id="m-links" name="links" rows="3" class="large-text"></textarea></td>
				</tr>
				<tr>
					<th><label for="m-message">Notes</label></th>
					<td><textarea id="m-message" name="message" rows="4" class="large-text"></textarea></td>
				</tr>
			</table>

			<?php submit_button( 'Add Applicant' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Save a manually entered applicant.
 */
function flxlm_ats_handle_manual_add() {
	if ( ! isset( $_POST['flxlm_ats_manual_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['flxlm_ats_manual_nonce'] ) ), 'flxlm_ats_manual_add' ) ) {
		wp_die( 'Security check failed.', 'Error', array( 'response' => 403 ) );
	}

	if ( ! current_user_can( 'flxlm_manage_applications' ) ) {
		wp_die( 'You do not have permission to add applicants.', 'Not allowed', array( 'response' => 403 ) );
	}

	$back = admin_url( 'edit.php?post_type=flxlm_application&page=flxlm-ats-add' );

	$fields = flxlm_ats_validate_fields( wp_unslash( $_POST ) );
	if ( is_wp_error( $fields ) ) {
		wp_safe_redirect( add_query_arg( 'flxlm_error', rawurlencode( $fields->get_error_message() ), $back ) );
		exit;
	}

	// Resume is optional here: someone who phoned in may not have sent one yet,
	// and refusing the record would lose the EEO data point over a missing file.
	$resume = null;
	if ( ! empty( $_FILES['resume']['name'] ) ) {
		$stored = flxlm_ats_store_resume( $_FILES['resume'] );
		if ( is_wp_error( $stored ) ) {
			wp_safe_redirect( add_query_arg( 'flxlm_error', rawurlencode( $stored->get_error_message() ), $back ) );
			exit;
		}
		$resume = $stored;
	}

	$job = flxlm_ats_resolve_job( isset( $_POST['job_id'] ) ? (int) $_POST['job_id'] : 0 );

	$applied_on = isset( $_POST['applied_on'] ) ? sanitize_text_field( wp_unslash( $_POST['applied_on'] ) ) : '';
	$submitted  = $applied_on ? $applied_on . ' 12:00:00' : gmdate( 'Y-m-d H:i:s' );

	$application_id = flxlm_ats_create_application(
		array(
			'fields'       => $fields,
			'job_id'       => $job['id'],
			'job_title'    => $job['title'],
			'resume'       => $resume,
			'entered_by'   => 'manual',
			'source_site'  => 'flxlocalmedia',
			'submitted_at' => $submitted,
		)
	);

	if ( is_wp_error( $application_id ) ) {
		wp_safe_redirect( add_query_arg( 'flxlm_error', rawurlencode( 'Could not save that applicant.' ), $back ) );
		exit;
	}

	wp_safe_redirect( admin_url( 'post.php?post=' . $application_id . '&action=edit' ) );
	exit;
}
add_action( 'admin_post_flxlm_ats_manual_add', 'flxlm_ats_handle_manual_add' );
