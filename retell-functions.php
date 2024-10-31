<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'retell-settings.php';

function retell_parse_jwt($token)
{
    $tokenParts = explode(".", $token);
    $header = base64_decode($tokenParts[0]);
    $payload = base64_decode($tokenParts[1]);
    $signatureProvided = $tokenParts[2];
    $data = json_decode($payload, true);
    return $data;
}

add_action('admin_enqueue_scripts', function($hook) {
    if (isset($_GET['page']) && $_GET['page'] === 'retell-settings') {
        wp_enqueue_style('retell_main_style', plugins_url('/style.css', __FILE__), array(), '1.0.7');
        wp_enqueue_style('retell_fonts', plugins_url('fonts/stylesheet.css', __FILE__), array(), '1.0.0');
    }
});

add_action('admin_menu', 'retell_add_admin_menu');

function retell_add_admin_menu()
{
    add_menu_page(
        'Retell.media',
        'Retell.media',
        'manage_options',
        'retell-settings',
        'retell_render_settings_page',
    );
}

add_action('admin_init', 'retell_settings_init');

function retell_settings_init()
{
    register_setting('retell_settings', 'retell_settings', 'retell_sanitize_input');

    add_settings_section(
        'retell_settings_section',
        __('Retell.media Settings', 'retell-media'),
        'retell_settings_section_callback',
        'retell_settings'
    );

    add_settings_field(
        'retell_api_key',
        __('Retell.media API Key', 'retell-media'),
        'retell_api_key_render',
        'retell_settings',
        'retell_settings_section'
    );
}

function retell_settings_section_callback()
{
    echo 'Enter your Retell.media API Key';
}

function retell_api_key_render()
{
    $options = get_option('retell_settings');

    if (!isset($options['retell_api_key']) || !is_array($options)) {
        $options = [];
        $options['retell_api_key'] = '';
    }
    ?>

                    <input type="text" placeholder="Your API Key" class="input" name='retell_settings[retell_api_key]' value='<?php echo esc_textarea($options['retell_api_key']); ?>'>
    <?php
}

function retell_validate_jwt($token)
{
    try {
        $parts = explode(".", $token['retell_api_key']);
        if (count($parts) !== 3) {
            return false;
        }

        $payload = base64_decode($parts[1]);

        $data = json_decode($payload, true);
        if ($data === null) {
            return false;
        }
    } catch (Exception $e) {
        return false;
    }

    return $data;
}

function retell_sanitize_input($input)
{
    $data = retell_validate_jwt($input);

    if ($data === false) {
        add_settings_error('retell_settings', 'retell_invalid_jwt', 'Invalid JWT', 'error');
        return '';
    }

    $input['retell_api_key'] = sanitize_text_field($input['retell_api_key']);

    return $input;
}


function retell_cron_period($schedules)
{
    $options = get_option('retell_settings');
    if (!isset($options['retell_api_key']) || !is_array($options)) {
        return $schedules;
    }

    $data = retell_validate_jwt($options);

    $period = $data['period'];

    $display = "Every $period seconds";

    $schedules['retell_period'] = array(
        'interval' => $period, 
        'display' => $display
    );
    return $schedules;
}

add_filter('cron_schedules', 'retell_cron_period');

if (!wp_next_scheduled('retell_cron_hook')) {
    $schedules = wp_get_schedules();

    if (!isset($schedules['retell_period'])) {
        return;
    }
    wp_schedule_event(time(), 'retell_period', 'retell_cron_hook');
}

add_action('retell_cron_hook', 'retell_cron_function');

function retell_cron_function()
{
    if (filter_var($_SERVER['HTTP_HOST'], FILTER_VALIDATE_IP)) {
        return;
    }

    $options = get_option('retell_settings');
    if (!isset($options['retell_api_key']) || !is_array($options)) {
        return;
    }

    $last_visited = get_option('retell_last_visited');

    $data = retell_validate_jwt($options);
    $url = $data['public_api_url'] . "/articles";
    if ($last_visited) {
        $url .= "?timestamp=" . $last_visited;
    }
    $headers = array(
        'Authorization' => 'Bearer ' . $options['retell_api_key'],
    );
    $response = wp_remote_get($url, array('headers' => $headers));
    if (is_wp_error($response)) {
        error_log($response->get_error_message());
        return;
    }
    $body = wp_remote_retrieve_body($response);
    if (is_wp_error($body)) {
        error_log($body->get_error_message());
        return;
    }
    $json = json_decode($body, true);
    $posts = $json['articles'];

    if (count($posts) === 0) {
        return;
    }

    foreach ($posts as $post) {
        $post_id = wp_insert_post(array(
            'post_title' => sanitize_text_field($post['title']),
            'post_content' => wp_kses_post($post['content']),
            'post_status' => 'draft',
            'post_author' => 1,
        ));
        if (is_wp_error($post_id)) {
            error_log($post_id->get_error_message());
            continue;
        }

        update_post_meta($post_id, 'retell_id', $post['id']);
        update_post_meta($post_id, 'retell_has_image', $post['with_image']);
        update_post_meta($post_id, 'retell_image_uploaded', '0');
    }

    update_option('retell_last_visited', $json['timestamp']);

    $searchArgs = array(
        'post_type' => 'post',
        'posts_per_page' => 10,
        'post_status' => ['draft', 'publish'],
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'retell_has_image',
                'value' => '1',
                'compare' => '=',
            ),
            array(
                'key' => 'retell_image_uploaded',
                'value' => '0',
                'compare' => '=',
            ),
        ),
    );

    $requestData = [];

    $searchQuery = new WP_Query($searchArgs);
    if ($searchQuery->have_posts()) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        while ($searchQuery->have_posts()) {
            $searchQuery->the_post();
            $post_id = get_the_ID();
            $retell_id = get_post_meta($post_id, 'retell_id', true);
            $requestData[] = array(
                'id' => $retell_id,
                'url' => esc_url(sanitize_url($protocol.$_SERVER['HTTP_HOST'] . '/retell/' . $post_id)),
            );
        }
    }

    wp_reset_postdata();

    if (count($requestData) > 0) {
        $headers['Content-Type'] = 'application/json';
        $url = $data['public_api_url'] . "/image-callback";
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => wp_json_encode($requestData),
            'method' => 'POST',
            'data_format' => 'body',
        ));
        if (is_wp_error($response)) {
            error_log($response->get_error_message());
            return;
        }
    }
}

function retell_uninstall()
{
    delete_option('retell_settings');
    delete_option('retell_last_visited');
}

function retell_deactivate()
{
    $timestamp = wp_next_scheduled('retell_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'retell_cron_hook');
    }
}

function retell_router()
{
    add_rewrite_rule('retell/([0-9]+)/?', 'index.php?retell_id=$matches[1]', 'top');
}

add_action('init', 'retell_router');

function retell_query_vars($vars)
{
    $vars[] = 'retell_id';
    return $vars;
}

add_filter('query_vars', 'retell_query_vars');

function retell_validate_header($token) {
    if(count(explode(' ', $token)) !== 2) {
        status_header(401);
        exit;
    }

    $parts = explode(' ', $token);
    if($parts[0] !== 'Bearer') {
        status_header(401);
        exit;
    }


    $options = get_option('retell_settings');
    if (!isset($options['retell_api_key']) || !is_array($options)) {
        status_header(401);
        exit;
    }

    $token = $parts[1]; 


    if ($token !== $options['retell_api_key']) {
        status_header(401);
        exit;
    }
}

function retell_handle_image_upload(&$wp)
{
    // just return if we're not uploading an image
    // auth header is validating next, dont need to check nonce, because it's not a form
    if (
        (!isset($wp->query_vars['retell_id']) && 
        (!isset($wp->query_vars['pagename']) || $wp->query_vars['pagename'] !== 'retell' || !isset($wp->query_vars['page']))) || 
        !isset($_FILES['image'])
    ) {
        return;
    }
    require_once(ABSPATH . 'wp-admin/includes/file.php');


    // validate the auth header
    retell_validate_header($_SERVER['HTTP_AUTHORIZATION']);

    $post_id = 1;
    if (isset($wp->query_vars['retell_id'])) {
        $post_id = $wp->query_vars['retell_id'];
    } else if (isset($wp->query_vars['page'])) {
        $post_id = $wp->query_vars['page'];
    }

    $image = $_FILES['image'];

    $moveFile = wp_handle_upload($image, array('test_form' => false));
    if (!$moveFile || isset($moveFile['error'])) {
        status_header(500);
        exit;
    }

    $fileType = wp_check_filetype(basename($moveFile['file']), null);

    $attachment = array(
        'post_mime_type' => $fileType['type'], 
        'post_title' => preg_replace('/\.[^.]+$/', '', basename($moveFile['file'])),
        'post_content' => '',
        'post_status' => 'inherit',
    );

    $attach_id = wp_insert_attachment($attachment, $moveFile['file'], $post_id);

    set_post_thumbnail($post_id, $attach_id);

    echo 'Image uploaded';

    update_post_meta($post_id, 'retell_image_uploaded', '1');
    exit;
}

add_action('parse_request', 'retell_handle_image_upload');

function retell_show_notification()
{
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        echo '<div class="notice notice-success is-dismissible">
        <p>Settings saved</p>
    </div>';
    }
}

add_action('admin_notices', 'retell_show_notification');
