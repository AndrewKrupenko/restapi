<?php

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$wp_api = new WpRestAPI();

class WpCustomTypes extends WpRestAPI
{

    function register_post_type_routes()
    {
        register_rest_route($this->namespace, $this->get_route(), $this->get_route_params([
            'callback' => [$this, 'get_required_posts'],
        ]));
    }

    function get_required_posts(WP_REST_Request $request)
    {
        if ($this->if_in_black_list()) {
            return new WP_Error('black_list', 'Sorry, But You\'re In a Black List', ['status' => 403]);
        }

        $activated_custom_posts_arr = [];
        $post_types_table_name = $this->get_table_name('post_types');
        $wp_api_post_types = $this->wpdb->get_results(
            "
            SELECT post_type 
            FROM $post_types_table_name
          "
        );
        foreach($wp_api_post_types as $wp_api_post_type){
            $activated_custom_posts_arr[] = $wp_api_post_type->post_type;
        }

        $current_post_type = !empty($request->get_param('post_type')) ? $request->get_param('post_type') : [];

        if( !in_array( $current_post_type, $activated_custom_posts_arr ) ) {
            return new WP_Error('no_posts', 'Posts not found', ['status' => 404]);
        }

        $current_lang = $request->get_param('lang');
        $offset = $request->get_param('offset');
        $required_posts_arr = [];

        if ('custom_posts' == $current_post_type) {
            $custom_posts_arr = $this->get_custom_post_types();
            $current_post_type = array_values($custom_posts_arr);
        }

        $all_lang_posts_count = get_posts([
            'post_type'      => $current_post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'lang'           => $current_lang,
        ]);

        $posts = get_posts([
            'post_type'      => $current_post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $this->api_requests_options["posts_per_api_page"],
            'lang'           => $current_lang,
            'offset'         => $offset,
            'paged'          => isset($_REQUEST['page']) ? $_REQUEST['page'] : 1,
        ]);

        if (empty($posts)) {
            return new WP_Error('no_posts', 'Posts not found', ['status' => 404]);
        }

        foreach ($posts as $post) {
            $post_meta = get_post_meta($post->ID);

            // returns an array with 'meta_' prefix for keys
            $post_meta = array_combine(array_map(function ($k) {
                return 'meta_' . $k;
            }, array_keys($post_meta)), $post_meta);

            // TODO: Change it to one request
            if (has_post_thumbnail($post->ID)) {
                $image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
                $image_array = [
                    'image_url' => $image[0],
                ];
            } else {
                $image_array = [];
            }

            $post_obj = (object)array_merge((array)$post, (array)$post_meta, (array)$image_array);
            $required_posts_arr[] = $post_obj;
        }

        if (!empty($this->user_ip)) {
            $user_ip = $this->user_ip;
            $request_date_minute = date("Y-m-d H:i");
            $requests_per_minute_table = $this->get_table_name('requests_per_minute');

            $requests_minute_number_database = $this->wpdb->get_var(
                "
                    SELECT requests_number
                    FROM $requests_per_minute_table
                    WHERE user_ip = '$user_ip' AND request_datetime = '$request_date_minute'
                "
            );

            if (!empty($requests_minute_number_database)) { // this minute this ip has already had requests
                $requests_minute_number_database += 1;

                if ($this->api_requests_options['requests_minute_limit'] >= $requests_minute_number_database) {
                    $this->wpdb->update(
                        $requests_per_minute_table,
                        ['requests_number' => $requests_minute_number_database],
                        [
                            'user_ip'          => $user_ip,
                            'request_datetime' => $request_date_minute,
                        ],
                        ['%d'],
                        [
                            '%s',
                            '%s',
                        ]
                    );
                } else {
                    return new WP_Error('requests_limit', 'Sorry, the limit of requests is ' . $this->api_requests_options["requests_minute_limit"] . ' per minute', ['status' => 429]);
                }
            } else { // first request this minute from this ip
                $requests_number_database = 1;

                $this->wpdb->insert(
                    $requests_per_minute_table,
                    [
                        'user_ip'          => $user_ip,
                        'requests_number'  => $requests_number_database,
                        'request_datetime' => $request_date_minute,
                    ],
                    [
                        '%s',
                        '%d',
                        '%s',
                    ]
                );
            }

            $request_date_day = date("Y-m-d");
            $requests_per_day_table = $this->get_table_name('requests_per_day');

            $requests_number_database = $this->wpdb->get_var(
                "
                    SELECT requests_number
                    FROM $requests_per_day_table
                    WHERE user_ip = '$user_ip' AND request_date = '$request_date_day'
                "
            );

            if (!empty($requests_number_database)) { // today this ip has already had requests
                $requests_number_database += 1;

                if ($this->api_requests_options['requests_day_limit'] >= $requests_number_database) {
                    $this->wpdb->update(
                        $requests_per_day_table,
                        ['requests_number' => $requests_number_database],
                        [
                            'user_ip'      => $user_ip,
                            'request_date' => $request_date_day,
                        ],
                        ['%d'],
                        [
                            '%s',
                            '%s',
                        ]
                    );
                } else {
                    return new WP_Error('requests_limit', 'Sorry, the limit of requests is ' . $this->api_requests_options["requests_day_limit"] . ' per day', ['status' => 429]);
                }

            } else { // first request today from this ip
                $requests_number_database = 1;

                $this->wpdb->insert(
                    $requests_per_day_table,
                    [
                        'user_ip'         => $user_ip,
                        'requests_number' => $requests_number_database,
                        'request_date'    => $request_date_day,
                    ],
                    [
                        '%s',
                        '%d',
                        '%s',
                    ]
                );
            }
        }
        $required_posts_arr[] = [
            'posts_count'    => count($all_lang_posts_count),
            'posts_per_page' => $this->api_requests_options["posts_per_api_page"],
        ];
        return $required_posts_arr;
    }
}