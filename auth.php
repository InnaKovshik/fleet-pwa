<?php
if (!defined('ABSPATH')) exit;

class Fleet_Auth {
    private $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'fleet_devices';
    }

    public function register_device($user_id,$device_id){
        global $wpdb;
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE device_id=%s",$device_id));
        if($existing){
            $secret = $this->get_device_secret($existing);
            return ['device_id'=>$existing->device_id,'device_secret'=>$secret,'user_id'=>$existing->user_id];
        }

        $device_secret = bin2hex(random_bytes(32));
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($device_secret,'AES-256-CBC',AUTH_KEY,OPENSSL_RAW_DATA,$iv);
        $stored = base64_encode($iv.$encrypted);

        $insert = $wpdb->insert($this->table,[
            'user_id'=>$user_id,
            'device_id'=>$device_id,
            'device_secret'=>$stored,
            'created_at'=>current_time('mysql'),
            'revoked'=>0
        ]);

        if(!$insert) return new WP_Error('db_error',$wpdb->last_error,['status'=>500]);

        return ['device_id'=>$device_id,'device_secret'=>$device_secret,'user_id'=>$user_id];
    }

    public function get_device($user_id, $device_id){
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$this->table} WHERE user_id=%d AND device_id=%s",
        $user_id,
        $device_id
    ));
}

    public function get_device_secret($device){
        $decoded = base64_decode($device->device_secret);
        $iv = substr($decoded,0,16);
        $enc = substr($decoded,16);
        return openssl_decrypt($enc,'AES-256-CBC',AUTH_KEY,OPENSSL_RAW_DATA,$iv);
    }

    public function validate_device(WP_REST_Request $request){
        global $wpdb;
        $device_id = $request->get_header('x-device-id');
        $signature = $request->get_header('x-device-signature');

        if(!$device_id || !$signature) return new WP_Error('unauthorized','Missing headers',['status'=>401]);

        $device = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE device_id=%s AND revoked=0",$device_id));
        if(!$device) return new WP_Error('unauthorized','Device not found',['status'=>401]);

        $secret = $this->get_device_secret($device);
        $payload = $request->get_method()==='GET' ? '' : $request->get_body();
        $expected = hash_hmac('sha256',$payload,$secret);

        if(!hash_equals($expected,$signature)) return new WP_Error('unauthorized','Invalid signature',['status'=>401]);

        $request->set_param('fleet_user_id',$device->user_id);
        $request->set_param('fleet_device_id',$device_id);

        return true;
    }
}

$GLOBALS['fleet_auth'] = new Fleet_Auth();
?>
