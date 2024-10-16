<?php
/*
Plugin Name: Frontend Post Submission Time-Based Post Limit
Description: Adds a time-based limit to post submissions for logged-in users in the Frontend Post Submission Manager.
Version: 1.0
Author: Talha Ansari
Author URI:  https://www.fiverr.com/ansari_talha?up_rollout=true
Text Domain: fpsm-time-limit
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Function to get the last post time by author
function fpsm_get_last_post_date_by_author($post_author_id) {
    $args = array(
        'author' => $post_author_id,
        'post_status' => array('publish', 'draft', 'pending', 'future'),
        'orderby' => 'date',
        'order' => 'DESC',
        'posts_per_page' => 1,
    );

    // Custom query to get the latest post
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $query->the_post(); // Set up post data
        return get_the_date('Y-m-d') . ' ' . get_the_time('H:i:s');
    } else {
        return false; // Return false if no posts found
    }

    // Reset post data
    wp_reset_postdata();
}

// Custom function to check time-based post submission limit
function fpsm_check_time_based_post_limit() {
    global $fpsm_library_obj;

    // Fetch form data from the POST request
    $form_data = $_POST['form_data'];
    $form_data = stripslashes_deep($form_data);
    parse_str($form_data, $form_data);

    $form_alias = sanitize_text_field($form_data['form_alias']);
    $form_row = $fpsm_library_obj->get_form_row_by_alias($form_alias);
    $form_details = maybe_unserialize($form_row->form_details);

    // Check for time-based post limit if enabled
    if ($form_row->form_type == 'login_require' && !empty($form_details['basic']['limit_post_time'])) {

        // If the user is logged in
        if (is_user_logged_in()) {
            $post_author_id = get_current_user_id();

            // Get the last post time for the author (convert it to GMT for accurate comparison)
            $last_post_time = fpsm_get_last_post_date_by_author($post_author_id);
            
            if ($last_post_time) {
                // Convert the last post time to GMT if it's in local time
                $last_post_time_gmt = get_gmt_from_date($last_post_time);

                // Get the current time in GMT
                $current_time = current_time('timestamp', true); // GMT timestamp

                // Convert last post time to UNIX timestamp
                $last_post_time_unix = strtotime($last_post_time_gmt);

                // For Debugging, log this time:
                //print_r('Last post time: ' . $last_post_time_gmt);
                //print_r('Current time: ' . gmdate("Y-m-d H:i:s", $current_time));

                // Get allowable time interval
                $time_limit_value = intval($form_details['basic']['time_limit_value']);
                $time_limit_unit = $form_details['basic']['time_limit_unit'];

                // Convert time units into seconds
                switch ($time_limit_unit) {
                    case 'hours':
                        $time_limit_seconds = $time_limit_value * HOUR_IN_SECONDS;
                        break;
                    case 'days':
                        $time_limit_seconds = $time_limit_value * DAY_IN_SECONDS;
                        break;
                    case 'weeks':
                        $time_limit_seconds = $time_limit_value * WEEK_IN_SECONDS;
                        break;
                    case 'months':
                        $time_limit_seconds = $time_limit_value * 30 * DAY_IN_SECONDS;
                        break;
                    default:
                        $time_limit_seconds = 0;
                        break;
                }

                // Calculate the time difference in seconds
                $time_difference = $current_time - $last_post_time_unix;

                // Debugging outputs
               // print_r('Time difference: ' . $time_difference);
               // print_r('Time limit seconds: ' . $time_limit_seconds);

                // Check if the user has to wait more time
                if ($time_difference < $time_limit_seconds) {
    // Calculate remaining time in seconds
    $remaining_time = $time_limit_seconds - $time_difference;

    // Calculate time in days, hours, and minutes accurately
    $days = floor($remaining_time / DAY_IN_SECONDS);
    $remaining_time -= $days * DAY_IN_SECONDS;

    $hours = floor($remaining_time / HOUR_IN_SECONDS);
    $remaining_time -= $hours * HOUR_IN_SECONDS;

    $minutes = floor($remaining_time / MINUTE_IN_SECONDS);

    // Prepare the message with the remaining time
    $time_to_wait = '';
    if ($days > 0) {
        $time_to_wait .= $days . ' ' . __('day', 'fpsm-time-limit') . ($days > 1 ? 's' : '') . ' ';
    }
    if ($hours > 0) {
        $time_to_wait .= $hours . ' ' . __('hour', 'fpsm-time-limit') . ($hours > 1 ? 's' : '') . ' ';
    }
    if ($minutes > 0) {
        $time_to_wait .= $minutes . ' ' . __('minute', 'fpsm-time-limit') . ($minutes > 1 ? 's' : '');
    }

    // Return the message with the remaining time
    $response['status'] = 403;
    $response['message'] = sprintf(
        esc_html__('You can submit a new post again after %s.', 'fpsm-time-limit'),
        trim($time_to_wait)
    );
    die(json_encode($response));
}

            }
        }
    }
}

// Hook to check time limit before form processing
add_action('fpsm_before_form_process', 'fpsm_check_time_based_post_limit');

// Custom function to add the time limit setting fields
function fpsm_add_limit_post_time_settings($form_row) {
    // Access form details (if available)
    $form_details = maybe_unserialize($form_row->form_details);
    $basic_settings = (!empty($form_details['basic'])) ? $form_details['basic'] : array();
    ?>
    <div class="fpsm-field-wrap">
        <label><?php esc_html_e('Limit Post Submission by Time', 'fpsm-time-limit'); ?></label>
        <div class="fpsm-field">
            <input type="checkbox" name="form_details[basic][limit_post_time]" value="1" 
            <?php echo (!empty($basic_settings['limit_post_time'])) ? 'checked="checked"' : ''; ?> 
            class="fpsm-checkbox-toggle-trigger" data-toggle-class="fpsm-limit-post-time-ref" />
            <p class="description"><?php esc_html_e('Check if you want to limit the post submission by a specific time period.', 'fpsm-time-limit'); ?></p>
        </div>
    </div>

    <div class="fpsm-field-wrap fpsm-limit-post-time-ref" 
    <?php echo (!empty($basic_settings['limit_post_time'])) ? '' : 'style="display:none;"'; ?>>
        <label><?php esc_html_e('Set Time Limit for Post Submission', 'fpsm-time-limit'); ?></label>
        <div class="fpsm-field">
            <input type="number" name="form_details[basic][time_limit_value]" 
            value="<?php echo (!empty($basic_settings['time_limit_value'])) ? esc_attr($basic_settings['time_limit_value']) : ''; ?>" 
            min="1" />
            <select name="form_details[basic][time_limit_unit]">
                <option value="hours" <?php selected($basic_settings['time_limit_unit'], 'hours'); ?>><?php esc_html_e('Hours', 'fpsm-time-limit'); ?></option>
                <option value="days" <?php selected($basic_settings['time_limit_unit'], 'days'); ?>><?php esc_html_e('Days', 'fpsm-time-limit'); ?></option>
                <option value="weeks" <?php selected($basic_settings['time_limit_unit'], 'weeks'); ?>><?php esc_html_e('Weeks', 'fpsm-time-limit'); ?></option>
                <option value="months" <?php selected($basic_settings['time_limit_unit'], 'months'); ?>><?php esc_html_e('Months', 'fpsm-time-limit'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Specify the time limit between post submissions.', 'fpsm-time-limit'); ?></p>
        </div>
    </div>
    <?php
}

// Hook the function into the 'fpsm_form_sections_end' action
add_action('fpsm_form_sections_end', 'fpsm_add_limit_post_time_settings');
