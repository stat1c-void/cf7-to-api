<?php

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

function qs_cf7_notice_cf7_not_active() {
    ?>
    <div class="notice notice-warning">
        <p><?php _e('"Contact Form 7 To API" integrations requires "Contact Form 7" plugin to be installed and active', 'qs-cf7-api'); ?></p>
    </div>
    <?php
}


class QS_CF7_api_admin
{
    /**
     * Holds the plugin options
     * @var [type]
     */
    private $options;

    /**
     * Holds athe admin notices class
     * @var [QS_Admin_notices]
     */
    private $admin_notices;

    /**
     * PLugn is active or not
     */
    private $plugin_active;

    public function __construct()
    {
        $this->register_hooks();
    }

    /**
     * Check if contact form 7 is active
     * @return [type] [description]
     */
    public function verify_dependencies()
    {
        if (!is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
            add_action('admin_notices', 'qs_cf7_notice_cf7_not_active');
        }
    }

    /**
     * Registers the required admin hooks
     * @return [type] [description]
     */
    public function register_hooks()
    {
        // Check if required plugins are active
        add_action('admin_init', array($this, 'verify_dependencies'));
        // before sending email to user actions
        add_action('wpcf7_before_send_mail', array($this, 'qs_cf7_send_data_to_api'));
        // adds another tab to contact form 7 screen
        add_filter("wpcf7_editor_panels", array($this, "add_integrations_tab"), 1, 1);
        // actions to handle while saving the form
        add_action("wpcf7_save_contact_form", array($this, "qs_save_contact_form_details"), 10, 1);

        add_filter("wpcf7_contact_form_properties", array($this, "add_sf_properties"), 10, 2);
    }

    /**
     * Sets the form additional properties
     * @param [type] $properties   [description]
     * @param [type] $contact_form [description]
     */
    function add_sf_properties($properties, $contact_form)
    {
        //add mail tags to allowed properties
        $properties["wpcf7_api_data"] = isset($properties["wpcf7_api_data"]) ? $properties["wpcf7_api_data"] : array();
        return $properties;
    }

    /**
     * Adds a new tab on conract form 7 screen
     * @param [type] $panels [description]
     */
    function add_integrations_tab($panels)
    {
        $integration_panel = array(
            'title' => __('API Integration', 'qs-cf7-api'),
            'callback' => array($this, 'wpcf7_integrations')
        );

        $panels["qs-cf7-api-integration"] = $integration_panel;
        return $panels;
    }

    /**
     * The admin tab display, settings and instructions to the admin user
     * @param  [type] $post [description]
     * @return [type]       [description]
     */
    function wpcf7_integrations($post)
    {
        $wpcf7_api_data = $post->prop('wpcf7_api_data');

        $mail_tags = apply_filters('qs_cf7_collect_mail_tags', $post->collect_mail_tags(array("exclude" => array("all-fields"))));

        $wpcf7_api_data["base_url"] = isset($wpcf7_api_data["base_url"]) ? $wpcf7_api_data["base_url"] : '';
        $wpcf7_api_data["send_to_api"] = isset($wpcf7_api_data["send_to_api"]) ? !!$wpcf7_api_data["send_to_api"] : false;
        $wpcf7_api_data["stop_email"] = isset($wpcf7_api_data["stop_email"]) ? !!$wpcf7_api_data["stop_email"] : false;
        $wpcf7_api_data["method"] = isset($wpcf7_api_data["method"]) ? $wpcf7_api_data["method"] : 'GET';
        $wpcf7_api_data["query_headers"] = isset($wpcf7_api_data["query_headers"]) ? $wpcf7_api_data["query_headers"] : '';
        $wpcf7_api_data["query_body"] = isset($wpcf7_api_data["query_body"]) ? $wpcf7_api_data["query_body"] : '?foo=bar';
        $wpcf7_api_data["debug_log"] = isset($wpcf7_api_data["debug_log"]) ? !!$wpcf7_api_data["debug_log"] : false;

        $debug_url = get_option('qs_cf7_api_debug_url');
        $debug_result = get_option('qs_cf7_api_debug_result');
        $debug_params = get_option('qs_cf7_api_debug_params');
        ?>


        <h2><?php _e('API Integration', 'qs-cf7-api') ?></h2>

        <fieldset>
            <?php do_action('before_base_fields', $post); ?>

            <div class="cf7_row">
                <label for="wpcf7-sf-send_to_api">
                    <input type="checkbox" id="wpcf7-sf-send_to_api"
                           name="wpcf7-sf[send_to_api]" <?php checked($wpcf7_api_data["send_to_api"]) ?>/>
                    <?php _e('Send to API', 'qs-cf7-api') ?>
                </label>
            </div>

            <div class="cf7_row">
                <label for="wpcf7-sf-stop_email">
                    <input type="checkbox" id="wpcf7-sf-stop_email"
                           name="wpcf7-sf[stop_email]" <?php checked($wpcf7_api_data["stop_email"]) ?>/>
                    <?php _e('Do not send an e-mail', 'qs-cf7-api') ?>
                </label>
                <p class="description"><?php _e('If enabled, e-mails will not be sent upon form submission.') ?></p>
            </div>

            <div class="cf7_row">
                <label for="wpcf7-sf-base_url">
                    <?php _e('Base URL', 'qs-cf7-api'); ?>
                    <input type="text" id="wpcf7-sf-base_url" name="wpcf7-sf[base_url]" class="large-text"
                           value="<?php echo $wpcf7_api_data["base_url"]; ?>"/>
                </label>
            </div>

            <div class="cf7_row">
                <label for="wpcf7-sf-method">
                    <?php _e('Method', 'qs-cf7-api'); ?>
                    <select id="wpcf7-sf-method" name="wpcf7-sf[method]">
                        <option value="GET" <?php selected($wpcf7_api_data["method"], 'GET'); ?>>GET</option>
                        <option value="POST" <?php selected($wpcf7_api_data["method"], 'POST'); ?>>POST</option>
                    </select>
                </label>
            </div>

            <div class="cf7_row">
                <label for="wpcf7-sf-debug_log">
                    <input type="checkbox" id="wpcf7-sf-debug_log"
                           name="wpcf7-sf[debug_log]" <?php checked($wpcf7_api_data["debug_log"]) ?>/>
                    <?php _e('Debug log', 'qs-cf7-api'); ?>
                </label>
                <p class="description"><?php _e('If enabled, last API call result would be saved and displayed on this page below.') ?></p>
            </div>

            <?php do_action('after_base_fields', $post); ?>
        </fieldset>

        <h2><?php _e('Query Parameters', 'qs-cf7-api') ?></h2>

        <fieldset>
            <div class="cf7_row">
                <label for="wpcf7-sf-query_headers">
                    <?php _e('Query headers', 'qs-cf7-api') ?>
                    <textarea id="wpcf7-sf-query_headers" name="wpcf7-sf[query_headers]" cols="100" rows="4"
                        class="large-text code"><?php echo esc_html($wpcf7_api_data["query_headers"]) ?></textarea>
                </label>
            </div>

            <p class="description">
                <?php esc_html_e('Newline-delimited HTTP headers, usual HTTP format, e.g. Header: Header-Content.', 'qs-cf7-api') ?>
            </p>

            <div class="cf7_row">
                <label for="wpcf7-sf-query_body">
                    <?php _e('Query body', 'qs-cf7-api') ?>
                    <textarea id="wpcf7-sf-query_body" name="wpcf7-sf[query_body]" cols="100" rows="8"
                        class="large-text code"><?php echo esc_html($wpcf7_api_data["query_body"]) ?></textarea>
                </label>
            </div>

            <p class="description">
                <?php esc_html_e('If you use GET method, query body will be appended to the URL, use correct query delimeters like ? and &.', 'qs-cf7-api') ?>
                <br />
                <?php esc_html_e('You can use the following template tags (content will be url-encoded, dangerous characters stripped):') ?><br />
                [<?php echo join(']&nbsp;&nbsp;[', $mail_tags) ?>]
            </p>
        </fieldset>

        <?php if ($wpcf7_api_data['debug_log']): ?>
        <fieldset>
            <h3 class="debug_log_title"><?php _e('LAST API CALL', 'qs-cf7-api') ?></h3>

            <div class="debug_log">
                <h4><?php _e('Called URL', 'qs-cf7-api') ?>:</h4>
                <pre><?php echo trim(esc_attr($debug_url)) ?></pre>
                <h4><?php _e('Params', 'qs-cf7-api') ?>:</h4>
                <pre><?php print_r($debug_params) ?></pre>
                <h4><?php _e('Remote server result', 'qs-cf7-api') ?>:</h4>
                <pre><?php print_r($debug_result) ?></pre>
            </div>
        </fieldset>
        <?php endif;

    }

    /**
     * Saves the API settings
     * @param  [type] $contact_form [description]
     * @return [type]               [description]
     */
    public function qs_save_contact_form_details($contact_form)
    {
        $properties = $contact_form->get_properties();
        $properties['wpcf7_api_data'] = $_POST['wpcf7-sf'];
        $contact_form->set_properties($properties);
    }

    /**
     * The handler that will send the data to the api
     */
    public function qs_cf7_send_data_to_api($WPCF7_ContactForm)
    {
        $qs_cf7_data = $WPCF7_ContactForm->prop('wpcf7_api_data');

        /* check if the form is marked to be sent via API */
        if (isset($qs_cf7_data['send_to_api']) && $qs_cf7_data['send_to_api']) {
            $record = array();
            $record['url'] = $qs_cf7_data['base_url'];
            $record['query_body'] = $this->process_query_body(WPCF7_Submission::get_instance(), $qs_cf7_data['query_body']);
            $record['query_headers'] = $qs_cf7_data['query_headers'];

            if (isset($record["url"]) && $record["url"]) {
                do_action('qs_cf7_api_before_sent_to_api', $record);
                $response = $this->send_lead($record, $qs_cf7_data['debug_log'], $qs_cf7_data['method']);
                do_action('qs_cf7_api_after_sent_to_api', $record, $response);
            }
        }
    }

    /**
     * Processes the query body - replace form tags with content
     */
    function process_query_body($submission, $query_body)
    {
        $submitted_data = $submission->get_posted_data();

        // Iterating submitted form data
        foreach ($submitted_data as $key => $value) {
            // Safety stripping and url-encoding
            $value = preg_replace('/[[:punct:]]/', ' ', $value);
            $value = urlencode($value);
            // Replacing query_body tags with content
            $query_body = preg_replace("/\[$key\]/i", $value, $query_body);
        }

        return $query_body;
    }

    /**
     * Send the lead using wp_remote
     * @param  [type]  $record [description]
     * @param  boolean $debug [description]
     * @param  string $method [description]
     * @return [type]          [description]
     */
    private function send_lead($record, $debug = false, $method = 'GET')
    {
        global $wp_version;

        $url = $record['url'];
        $query_body = $record['query_body'];

        $args = array(
            'timeout' => 15,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(),
            'blocking' => true,
            'headers' => array(),
            'cookies' => array(),
            'body' => null,
            'compress' => false,
            'decompress' => true,
            'sslverify' => true,
            'stream' => false,
            'filename' => null
        );

        // Query headers processing
        $query_headers = $record['query_headers'];
        if ($query_headers) {
            preg_match_all('/([[:graph:]]+)\s*:\s*([[:graph:] ]+)\s*/', $query_headers, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $args['headers'][$match[1]] = $match[2];
            }
        }

        if ($method == 'GET') {
            $args = apply_filters('qs_cf7_api_get_args', $args, $record);
            $url .= $query_body;
            $url = apply_filters('qs_cf7_api_get_url', $url, $record);
            $result = wp_remote_get($url, $args);
        } else {
            $args['body'] = $query_body;
            $args = apply_filters('qs_cf7_api_post_args', $args);
            $url = apply_filters('qs_cf7_api_post_url', $url);
            $result = wp_remote_post($url, $args);
        }

        if (!is_wp_error($result))
            $result['body'] = strip_tags($result['body']);

        if ($debug) {
            update_option('qs_cf7_api_debug_url', $url);
            update_option('qs_cf7_api_debug_params', $args);
            update_option('qs_cf7_api_debug_result', $result);
        }

        return do_action('after_qs_cf7_api_send_lead', $result, $record);
    }
}