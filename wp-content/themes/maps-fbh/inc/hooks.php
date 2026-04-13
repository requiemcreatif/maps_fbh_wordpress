<?php

/*-------------------------------------- SEARCH BY TITLE HELPER -------------------------------------*/
// Filter to be added during the search
function cc_post_title_filter($where, &$wp_query)
{
	global $wpdb;
	if ($search_term = $wp_query->get('cc_search_post_title')) {
		$where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'%' . $wpdb->esc_like($search_term) . '%\'';
	}
	return $where;
}



/*-------------------------------------- MODIFY IMAGE PERMALINKS -------------------------------------*/
function append_to_post_name()
{
	// Checks to see if the option images_updated has been set or has a value of true.
	if (get_option('images_updated') === 'true') :
		return;
	endif;

	// get all attachment posts.
	$attachments = get_posts([
		'post_type' => 'attachment',
		'post_status' => 'inherit',
		'name' => $p->slug,
		'posts_per_page' => -1,
	]);

	// For each attachment, loop and update the post_name
	foreach ($attachments as $p) {

		$attachment = array(
			'ID'           => $p->ID,
			'post_name' => 'media-' . $p->post_name
		);
		// Update the post into the database
		wp_update_post($attachment);
	}

	// Once everything is looped, add the option to the database.
	add_option('images_updated', 'true');
}
add_action('after_setup_theme', 'append_to_post_name');



/*-------------------------------------- APPROVER META BOX -------------------------------------*/
/**
 * Responsible for rendering a select field in the post editor's meta box for selecting an approver.
 * @param $post
 * @return void
 */
function render_post_approver_meta_box($post)
{
	// Get the list of authors
	$authors = get_users();

	// Get the currently selected approver for the post
	$selected_approver = get_post_meta($post->ID, 'approver', true);

	// Render the select field
	echo '<select name="approver">';
	// Add an option for null value
	$null_selected = ($selected_approver === null) ? 'selected' : '';
	echo '<option value="" ' . $null_selected . '>None</option>';
	foreach ($authors as $author) {
		$author_id = $author->ID;
		$author_name = $author->display_name;

		// Check if the current option is selected
		$selected = ($selected_approver == $author_id) ? 'selected' : '';

		echo '<option value="' . $author_id . '" ' . $selected . '>' . $author_name . '</option>';
	}
	echo '</select>';
}

function add_post_approver_meta_box()
{
	$post_types = array('post', 'page'); // Replace 'custom_post_type' with your actual custom post type(s)

	add_meta_box(
		'post_approver_meta_box',
		'Approver',
		'render_post_approver_meta_box',
		$post_types,
		'side',
		'default'
	);
}
add_action('add_meta_boxes', 'add_post_approver_meta_box');

/**
 * This function is triggered when a post is saved,
 * It updates the "approver" meta field with the selected value from the post editor's meta box.
 * @param $post_id
 * @return void
 */
function save_post_approver_meta_field($post_id)
{
	if (isset($_POST['approver'])) {
		// Update the "approver" meta field with the selected value
		update_post_meta($post_id, 'approver', sanitize_text_field($_POST['approver']));
	}
}
add_action('save_post', 'save_post_approver_meta_field');

/**
 * Adds a column for default post types
 * @param $column_array
 * @return mixed
 */
function maps_fbh_approver_columns($column_array)
{
	$column_array['approver'] = 'Approver';
	return $column_array;
}
add_filter('manage_posts_columns', 'maps_fbh_approver_columns');
add_filter('manage_pages_columns', 'maps_fbh_approver_columns');

/**
 * Populate the "approver" column in the manage posts screen.
 * @param string $column_name The name of the column being populated.
 * @param int    $post_id     The ID of the current post.
 */
function maps_fbh_populate_approver_columns($column_name, $post_id)
{
	switch ($column_name) {
		case 'approver':
			$approver = get_post_meta($post_id, 'approver', true);
			$approver_name = ($approver) ? get_the_author_meta('display_name', $approver) : 'None';
			$post_type = get_post_type($post_id);
			echo '<a href="/wp-admin/edit.php?post_type=' . $post_type . '&approver=' . $approver . '">' . $approver_name . '</a>';
			break;
	}
}
add_action('manage_posts_custom_column', 'maps_fbh_populate_approver_columns', 10, 2);
add_action('manage_pages_custom_column', 'maps_fbh_populate_approver_columns', 10, 2);



/*--------------------------------------- POST MANIPULATIONS ---------------------------------------*/
/**
 * Save the bulk edit data for the "approver" meta field.
 * @param int $post_id The ID of the post being saved.
 */
function maps_fbh_bulk_edit_save($post_id)
{
	// Check the bulk edit nonce
	if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-posts')) {
		return;
	}

	// Check if the "approver" value is set in the request
	if (isset($_REQUEST['approver'])) {
		$approver = sanitize_text_field($_REQUEST['approver']);

		// Update the "approver" meta field with the selected value
		update_post_meta($post_id, 'approver', $approver);
	}
}
add_action('save_post', 'maps_fbh_bulk_edit_save');

/**
 * Add the quick edit custom box for the "approver" column.
 * @param string $column_name The name of the column being edited.
 * @param string $post_type   The post type of the column being edited.
 */
function maps_fbh_pro_add_quick_edit_custom_box($column_name, $post_type)
{
	if ($column_name === 'approver') {
?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label class="inline-edit-group">
					<span class="title">Approver</span>
					<span class="input-text-wrap">
						<select name="approver">
							<option value="">None</option>
							<?php
							$authors = get_users();
							foreach ($authors as $author) {
								$author_name = $author->display_name;
								$author_ID = $author->ID;
							?>
								<option value="<?php echo esc_attr($author_ID); ?>"><?php echo esc_html($author_name); ?></option>
							<?php
							}
							?>
						</select>
					</span>
				</label>
			</div>
		</fieldset>
<?php
	}
}
//enable if you want quick edit for single custom box
add_action('quick_edit_custom_box', 'maps_fbh_pro_add_quick_edit_custom_box', 10, 2);
add_action('bulk_edit_custom_box', 'maps_fbh_pro_add_quick_edit_custom_box', 10, 2);

/**
 * Custom filter for parsing query in the admin area.
 * @param WP_Query $query The WP_Query instance.
 */
function prefix_parse_filter($query)
{
	global $pagenow;
	// Check if we are in the admin area and on the "Posts" page
	if (is_admin() && 'edit.php' == $pagenow && isset($_GET['approver']) && $_GET['approver'] != '') {
		$approver = sanitize_text_field($_GET['approver']);

		// Set the meta query variables
		$query->query_vars['meta_key']     = 'approver';
		$query->query_vars['meta_value']   = $approver;
		$query->query_vars['meta_compare'] = '=';
	}
}
add_filter('parse_query', 'prefix_parse_filter');



/*--------------------------------------- CANONICAL REDIRECTS ---------------------------------------*/
// Disable the canonical Wordpress redirection for pagination URLs
function disable_paged_canonical_redirect($redirect_url, $requested_url)
{
	$page_num = (int)filter_input(INPUT_GET, 'paged');
	$canonical_paged = preg_match('/\/page\/[0-9]+/i', $redirect_url);
	if ($canonical_paged && $page_num) {
		$redirect_url = preg_replace('/\/page\/[0-9]+/i', '', $redirect_url);
		$redirect_url .= '&paged=' . $page_num;
	}
	return $redirect_url;
}
add_filter('redirect_canonical', 'disable_paged_canonical_redirect', 10, 2);

function generate_block_id($block_atts) {
	if (empty($block_atts['id'])) {
		$block_atts['id'] = 'block_id_' . uniqid();
    }
    return $block_atts;
}
add_filter('acf/pre_save_block','generate_block_id');