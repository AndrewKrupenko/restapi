<?php
/**
 * Plugin Name: Rest api plugin
 * Description: Rest api plugin made by Andrew
 * Author:      Andrew
 * Version:     1.0
 * Text Domain: rest-api
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 */

class WpRestAPI
{
    protected $namespace = 'wp-rest-api/v1';
    private static $TABLE_PREFIX = 'api_';

    function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->api_requests_options = get_option('api_requests_options');
        $this->user_ip = $_SERVER['REMOTE_ADDR'];
        $this->init();
    }

    function init()
    {
        register_activation_hook(__FILE__, [$this, 'create_tables']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    function create_tables()
    {
        $file_path = plugin_dir_path(__FILE__) . 'database/tables.sql';
        $this->run_sql_file($file_path);
    }

    private function run_sql_file($file_path)
    {
        if (!is_file($file_path) || 'sql' !== pathinfo($file_path, PATHINFO_EXTENSION)) {
            return null;
        }

        $query = file_get_contents($file_path);
        $query = str_replace("{{" . '$table_prefix' . "}}", $this->get_table_prefix(), $query);
        dbDelta($query);
    }

    private function get_table_prefix()
    {
        return $this->wpdb->prefix . self::$TABLE_PREFIX;
    }

    function register_routes()
    {
        $custom_posts_arr = $this->get_custom_post_types();

        $wp_custom_types = new WpCustomTypes();

        foreach ($custom_posts_arr as $custom_post) {
            $wp_custom_types->register_post_type_routes($custom_post);
        }
    }

    protected function get_route()
    {
        return "/token=" . $this->api_requests_options["request_token"];
    }

    protected function get_route_params($args = [])
    {
        return array_merge([
            'methods' => 'GET',
        ], $args);
    }

    protected function get_table_name($slug, $no_prefix = false)
    {
        $prefix = $no_prefix ? $this->wpdb->prefix : $this->get_table_prefix();
        return $prefix . $slug;
    }

    protected function if_in_black_list()
    {
        $black_list_table_name = $this->get_table_name('black_list');

        $wp_api_black_list = $this->wpdb->get_col("
            SELECT user_ip 
            FROM $black_list_table_name
          "
        );

        $wp_api_black_list_ips = !empty($wp_api_black_list) ? array_values($wp_api_black_list) : [];

        return in_array($this->user_ip, $wp_api_black_list_ips);
    }

    private function get_api_table($table_name)
    {
        $wp_api_table = $this->wpdb->get_results(
            "
            SELECT * 
            FROM $table_name
          "
        );
        return $wp_api_table;
    }

    function apiDashboard()
    {

        $api_custom_post_type = isset($_POST['api-custom-post-type']) ? $_POST['api-custom-post-type'] : '';

        $custom_posts_arr = $this->get_custom_post_types();
        $all_custom_posts_option = [
          'custom_posts' => 'custom_posts'
        ];
        $custom_posts_arr = array_merge($all_custom_posts_option, $custom_posts_arr);
        $api_settings_image = plugins_url('wp-rest-api/assets/img/apiSettings.png');
        $site_api_url = site_url() . '/wp-json/' . $this->namespace . $this->get_route() . '?post_type=';
        $request_date = date("Y-m-d");

        $post_types_table_name = $this->get_table_name('post_types');

        if (isset($_POST['remove_post_type'])) {
            $id = isset($_POST['remove_post_id']) ? $_POST['remove_post_id'] : '';
            $sql = "DELETE FROM $post_types_table_name WHERE id ='$id'";
            $this->wpdb->query($sql);
        }

        $activated_post_types = [];

        if (!empty($api_custom_post_type)  && !in_array($api_custom_post_type, $activated_post_types) ) {
            $this->wpdb->insert(
                $post_types_table_name,
                [
                    'post_type'    => $api_custom_post_type,
                    'request_date' => $request_date,
                ],
                [
                    '%s',
                    '%s',
                ]
            );
        }
        $wp_api_post_types = $this->get_api_table($post_types_table_name);

        foreach ($wp_api_post_types as $user_option => $value) {
            $activated_post_types[] = $value->post_type;
        }

        $wp_api_post_types = $this->get_api_table($post_types_table_name);

        ?>
        <div class="wp-rest-api-dashboard">
            <div class="left-side">
                <h2><?php esc_html_e('I\'m happy you\'ve installed my plugin!', 'rest-api') ?></h2>
                <h3><?php esc_html_e('Here you can find instructions you may need', 'rest-api') ?></h3>
                <ol>
                    <li><?php esc_html_e('Input where you can change requests limit for a minute', 'rest-api') ?></li>
                    <li><?php esc_html_e('Input where you can change requests limit for a day', 'rest-api') ?></li>
                    <li><?php esc_html_e('Your current request token', 'rest-api') ?></li>
                    <li><?php esc_html_e('If you wanna set or change your current token, you can take it here', 'rest-api') ?></li>
                    <li><?php esc_html_e('Don\'t forget to save settings', 'rest-api') ?></li>
                </ol>
                <img src="<?php echo $api_settings_image ?>" alt="SettingsImage">
            </div>
            <div class="right-side">

                <form method="post" action="">
                    <h2>
                        <?php esc_html_e('Follow this links to check out your custom post types api info', 'rest-api') ?>
                    </h2>

                    <div class="<?php echo count($custom_posts_arr) === count($activated_post_types) ? 'd-none' : ''; ?> ">
                        <label for="api-custom-post-type">Choose a post type to add: </label>
                        <select name="api-custom-post-type" id="api-custom-post-type">
                            <?php

                            foreach ($custom_posts_arr as $custom_post_type) {
                                if (!in_array($custom_post_type, $activated_post_types)) {
                                    ?>
                                    <option value="<?php echo $custom_post_type; ?>">
                                        <?php echo ucfirst($custom_post_type); ?>
                                    </option>
                                <?php }
                            }
                            ?>
                        </select>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Add post type to api', 'rest-api') ?>
                        </button>
                    </div>
                </form>
                <table class="api-custom-post-types-table">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('№', 'rest-api') ?></th>
                        <th><?php esc_html_e('Post type', 'rest-api') ?></th>
                        <th><?php esc_html_e('Date', 'rest-api') ?></th>
                        <th><?php esc_html_e('Remove', 'rest-api') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;

                    if ($wp_api_post_types) {
                        foreach ($wp_api_post_types as $wp_api_post_type) {

                            $formatted_date = date('d.m.Y', strtotime($wp_api_post_type->request_date));

                            echo '<tr>';
                            echo '<td>' . $i++ . '</td>';
                            echo '<td>
                                    <a target="_blank" href="' . $site_api_url . $wp_api_post_type->post_type . '">' . ucfirst( $wp_api_post_type->post_type ) . '</a>
                                  </td>';
                            echo '<td>' . $formatted_date . '</td>';
                            echo '<td> 
                                  <form method="post" action="">
                                      <input type="hidden" name="remove_post_id" value="' . $wp_api_post_type->id . '">
                                      <button name="remove_post_type" class="remove-post-type-btn">Remove</button>                             
                                  </form>
                              </td>';
                            echo '</tr>';
                        }
                    } ?>
                    </tbody>
                </table>

                <p><?php _e('* Add <b>&lang=en</b> to your request to specify which language you wanna get', 'rest-api') ?></p>
                <p><?php _e('* Add <b>&page=2</b> to your request to specify which page you wanna get', 'rest-api') ?></p>
                <p><?php _e('* Add <b>&offset=0</b> to your request to set an offset', 'rest-api') ?></p>
            </div>
        </div>
        <?php
    }

    function get_custom_post_types()
    {
        $custom_posts_arr = get_post_types([
            '_builtin' => false,
            'public'   => true,
        ]);
        return $custom_posts_arr;
    }

    function displayRestApiAdminSettings()
    {
        if (!empty($_POST)) {
            if ( is_numeric($_POST['requests_minute_limit']) && is_numeric($_POST['requests_day_limit']) && $_POST['requests_minute_limit'] <= $_POST['requests_day_limit']) {
                $set_requests_options = [
                    'requests_minute_limit' => isset($_POST['requests_minute_limit']) && 1 <= $_POST['requests_minute_limit'] ? $_POST['requests_minute_limit'] : die(_e('<h2> Minute Limit of requests must be at least 1</h2>', 'rest-api')),
                    'requests_day_limit'    => isset($_POST['requests_day_limit']) && 1 <= $_POST['requests_day_limit'] ? $_POST['requests_day_limit'] : die(_e('<h2>Day Limit of requests must be at least 1</h2>', 'rest-api')),
                    'posts_per_api_page'    => isset($_POST['posts_per_api_page']) && 1 <= $_POST['posts_per_api_page'] ? $_POST['posts_per_api_page'] : die(_e('<h2>Limit for posts per page must be at least 1</h2>', 'rest-api')),
                    'request_token'         => isset($_POST['request_token']) ? $_POST['request_token'] : '',
                ];
            } else if( !is_numeric($_POST['requests_minute_limit']) || !is_numeric($_POST['requests_day_limit']) || !is_numeric($_POST['posts_per_api_page']) ){
                _e('<h2>Limits must be numeric</h2>', 'rest-api');
                die();
            } else {
                _e('<h2>It\'s forbidden to set a day limit less than a minute limit</h2>', 'rest-api');
                die();
            }

            $api_requests_options = get_option('api_requests_options');
            empty($api_requests_options) ? add_option('api_requests_options', $set_requests_options) : update_option('api_requests_options', $set_requests_options);
        }
        $api_requests_options = get_option('api_requests_options');

        ?>
        <div class="rest-api-settings-info">
            <h1>
                <?php esc_html_e('Rest api plugin settings', 'rest-api') ?>
            </h1>
            <form method="post" action="">
                <h3>
                    <?php esc_html_e('Requests Limit For a Minute', 'rest-api') ?>
                </h3>
                <input type="number" min="1" placeholder="Number of requests" name="requests_minute_limit" value="<?php echo $api_requests_options['requests_minute_limit']; ?>">
                <h3>
                    <?php esc_html_e('Requests Limit For a Day', 'rest-api') ?>
                </h3>
                <input type="number" min="1" placeholder="Number of requests" name="requests_day_limit" value="<?php echo $api_requests_options['requests_day_limit']; ?>">
                <h3>
                    <?php esc_html_e('Posts Per Page', 'rest-api') ?>
                </h3>
                <input type="number" min="1" max="100" placeholder="Posts per api page" name="posts_per_api_page" value="<?php echo $api_requests_options['posts_per_api_page']; ?>">
                <h3>
                    <?php esc_html_e('Current Request Token', 'rest-api') ?>
                </h3>
                <input type="text" placeholder="Example: 932c1ca8ec2b85178463e5ddc45ced" name="request_token" value="<?php echo $api_requests_options['request_token']; ?>">
                <br><br>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'rest-api') ?></button>
            </form>
        </div>
        <?php
    }

    function blackIpList()
    {
        $black_ip_option = isset($_POST['black_ip_list']) ? $_POST['black_ip_list'] : '';
        $request_date = date("Y-m-d");
        $black_list_table_name = $this->get_table_name('black_list');
        $existing_ips = [];

        if (isset($_POST['remove_ip'])) {
            $id = isset($_POST['id']) ? $_POST['id'] : '';
            $sql = "DELETE FROM $black_list_table_name WHERE id ='$id'";
            $this->wpdb->query($sql);
        }

        $wp_api_black_list = $this->get_api_table($black_list_table_name);

        foreach ($wp_api_black_list as $user_option => $value) {
            $existing_ips[] = $value->user_ip;
        }

        if (!empty($black_ip_option) && !in_array($black_ip_option, $existing_ips)) {
            $this->wpdb->insert(
                $black_list_table_name,
                [
                    'user_ip'      => $black_ip_option,
                    'request_date' => $request_date,
                ],
                [
                    '%s',
                    '%s',
                ]
            );
        }

        ?>
        <form method="post" action="">
            <h3>
                <?php esc_html_e('Add ip to the black list', 'rest-api') ?>
            </h3>
            <input type="text" placeholder="IP to block" name="black_ip_list">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Add', 'rest-api') ?>
            </button>
        </form>
        <table class="api-black-list-table">
            <thead>
            <tr>
                <th><?php esc_html_e('№', 'rest-api') ?></th>
                <th><?php esc_html_e('User IP', 'rest-api') ?></th>
                <th><?php esc_html_e('Date', 'rest-api') ?></th>
                <th><?php esc_html_e('Remove', 'rest-api') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            $wp_api_black_list = $this->get_api_table($black_list_table_name);
            $i = 1;

            if ($wp_api_black_list) {
                foreach ($wp_api_black_list as $wp_api) {
                    $formatted_date = date('d.m.Y', strtotime($wp_api->request_date));

                    echo '<tr>';
                    echo '<td>' . $i++ . '</td>';
                    echo '<td>' . $wp_api->user_ip . '</td>';
                    echo '<td>' . $formatted_date . '</td>';
                    echo '<td> 
                                  <form method="post" action="">
                                      <input type="hidden" name="id" value="' . $wp_api->id . '">
                                      <button name="remove_ip" class="remove-ip-btn">Remove</button>                             
                                  </form>
                              </td>';
                    echo '</tr>';
                }
            } ?>
            </tbody>
        </table>
        <?php
    }
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once 'WpCustomTypes.php';

add_action('admin_menu', 'register_api_custom_menu_page');
function register_api_custom_menu_page()
{
    $wp_api = new WpRestAPI();
    add_menu_page('Rest api plugin title', 'Rest api plugin', 'manage_options', 'wp-rest-api/wp-rest-api.php', [$wp_api, 'apiDashboard'], plugins_url('wp-rest-api/assets/img/api.png'),
        100);
    add_submenu_page('wp-rest-api/wp-rest-api.php', 'Plugin Name Settings', 'Settings', 'administrator', 'wp-rest-api-settings', [$wp_api, 'displayRestApiAdminSettings']);
    add_submenu_page('wp-rest-api/wp-rest-api.php', 'Black List Of IPs', 'Black IP List', 'administrator', 'wp-rest-api-black-list', [$wp_api, 'blackIpList']);
    wp_enqueue_style('restApiStylesheet', plugins_url('assets/css/style.css', __FILE__));
}

add_action('init', 'startSession');
function startSession()
{
    if (!session_id()) {
        session_start();
    }
}