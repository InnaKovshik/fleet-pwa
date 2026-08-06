<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {

    
    $secure = function($request){
    return $GLOBALS['fleet_auth']->validate_device($request);
};


  register_rest_route('kfz-pwa/v1','/register-device', [
    'methods'=>'POST',
    'callback'=>'fleet_register_device',
    'permission_callback'=>function() {
        return is_user_logged_in();
    }
]);

  register_rest_route('kfz-pwa/v1', '/check-device', [
    'methods' => 'POST',
    'callback' => function($request) {
        $user_id = get_current_user_id();

        if (!$user_id) return new WP_Error('no_user', 'User not logged in', ['status'=>401]);

        $device_id = sanitize_text_field($request->get_param('device_id'));

        $device = $GLOBALS['fleet_auth']->get_device($user_id, $device_id);

        if (!$device) {

            $device = $GLOBALS['fleet_auth']->register_device($user_id, $device_id);
        }

        return rest_ensure_response($device);
    },
    'permission_callback' => function() { return is_user_logged_in(); }
]);

    register_rest_route('kfz-pwa/v1','/cars', [
    'methods'=>'GET',
    'callback'=>'fleet_get_cars',
    'permission_callback'=>$secure
]);

    register_rest_route('kfz-pwa/v1','/start', [
        'methods'=>'POST',
        'callback'=>'fleet_start_trip',
        'permission_callback'=>$secure
    ]);

    register_rest_route('kfz-pwa/v1','/end', [
        'methods'=>'POST',
        'callback'=>'fleet_end_trip',
        'permission_callback'=>$secure
    ]); 
});

/* ================= USER ================= */

function fleet_permissions_logged_in() {
    if (!is_user_logged_in()) {
        return new WP_Error('unauthorized', 'Login required', ['status'=>401]);
    }
    return true;
}


function fleet_get_current_user_id_from_request($request){
    $user_id = $request->get_param('fleet_user_id');
    if(!$user_id){
        return new WP_Error('no_user','User not resolved',['status'=>401]);
    }
    return (int)$user_id;
}


// Device registration
function fleet_register_device(WP_REST_Request $req){
    if(!is_user_logged_in()) {
        return new WP_Error('not_logged_in','Login required',['status'=>401]);
    }

    $device_id = sanitize_text_field($req->get_param('device_id'));

    if (!isset($GLOBALS['fleet_auth'])) {
    return new WP_Error('no_auth', 'fleet_auth not initialized', ['status'=>500]);
}

    return $GLOBALS['fleet_auth']->register_device(
        get_current_user_id(),
        $device_id
    );
}


/* ================= GET CARS ================= */
function fleet_get_cars(WP_REST_Request $request) {
    $updated_after = $request->get_param('updated_after');

    $args = [
        'post_type' => 'fahrzeug',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ];

    if ($updated_after) {
        $args['date_query'] = [[
            'column' => 'post_modified_gmt',
            'after'  => sanitize_text_field($updated_after)
        ]];
    }

    $query = new WP_Query($args);
    $cars = [];

    foreach ($query->posts as $post) {
        $cars[] = [
            'id' => $post->ID,
            'name' => $post->post_title,
            'kennzeichen' => get_post_meta($post->ID, 'kennzeichen', true),
            'updated_at' => $post->post_modified_gmt
        ];
    }

    return rest_ensure_response($cars);
}

/* ================= START TRIP ================= */
function fleet_start_trip(WP_REST_Request $request) {
    global $wpdb;

    $data = $request->get_json_params();
    $car_id = intval($data['car_id'] ?? 0);
    $km_start = intval($data['km_start'] ?? 0);

    if (!$car_id || !$km_start) {
        return new WP_Error('invalid_data', 'Missing car_id or km_start', ['status' => 400]);
    }

    $car = get_post($car_id);
    if (!$car || $car->post_type !== 'fahrzeug') {
        return new WP_Error('invalid_car', 'Car not found', ['status' => 404]);
    }

    $user_id = fleet_get_current_user_id_from_request($request);
    if (is_wp_error($user_id)) return $user_id;

    $fahrt_id = wp_generate_uuid4();

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'fleet_km',
        [
            'fahrt_id' => $fahrt_id,
            'car_id' => $car_id,
            'km_start' => $km_start,
            'km_end' => null,
            'time_start' => current_time('mysql'),
            'time_end' => null,
            'user_id' => $user_id,
            'status' => 'active',
            'create_date' => current_time('mysql'),
            'last_update' => current_time('mysql')
        ],
        ['%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s']
    );

    if (!$inserted) {
        return new WP_Error('db_error', $wpdb->last_error, ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'fahrt_id' => $fahrt_id
    ]);
}

/* ================= END TRIP ================= */
function fleet_end_trip(WP_REST_Request $request) {
    global $wpdb;

    $data = $request->get_json_params();
    $fahrt_id = sanitize_text_field($data['fahrt_id'] ?? '');
    $km_end = intval($data['km_end'] ?? 0);

    if (!$fahrt_id || !$km_end) {
        return new WP_Error('invalid_data', 'Missing fahrt_id or km_end', ['status' => 400]);
    }

    $updated = $wpdb->update(
        $wpdb->prefix . 'fleet_km',
        [
            'km_end' => $km_end,
            'time_end' => current_time('mysql'),
            'status' => 'finished',
            'last_update' => current_time('mysql')
        ],
        ['fahrt_id' => $fahrt_id],
        ['%d', '%s', '%s', '%s'],
        ['%s']
    );

    if ($updated === false) {
        return new WP_Error('db_error', 'Update failed', ['status' => 500]);
    }

    return rest_ensure_response(['success' => true]);
}
?>
