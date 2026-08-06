<?php
/*
Plugin Name: Fleet PWA
Version: 24.0
Author: Inna Kovtunenko
*/

if (!defined('ABSPATH')) exit;

/* ================= CONSTANTS ================= */
define('FLEET_PWA_PATH', plugin_dir_path(__FILE__));
define('FLEET_PWA_URL', plugin_dir_url(__FILE__));

/* ================= INCLUDES ================= */
require_once FLEET_PWA_PATH . 'includes/auth.php';
require_once FLEET_PWA_PATH . 'includes/rest.php';

/* ================= SCRIPTS ================= */
add_action('wp_enqueue_scripts', function() {

    if (!is_page('kfz-pwa')) return;

    wp_enqueue_script(
        'fleet-db',
        FLEET_PWA_URL . 'public/db.js',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'fleet-app',
        FLEET_PWA_URL . 'public/app.js',
        ['fleet-db'],
        null,
        true
    );

    wp_enqueue_script(
        'fleet-sync',
        FLEET_PWA_URL . 'public/sync.js',
        ['fleet-app'],
        null,
        true
    );

      wp_enqueue_script(
        'fleet-jsqr',
        FLEET_PWA_URL . 'public/jsQR.js',
        ['fleet-app'],
        null,
        true
    );

    // ✅ CONFIG für JS
    wp_localize_script('fleet-app', 'FleetConfig', [
        'restUrl' => rest_url('/kfz-pwa/v1/'),
        'swUrl'   => home_url('/sw.js'), 
        'nonce'   => wp_create_nonce('wp_rest')
    ]);
});

/* ================= PWA META ================= */
add_action('wp_head', function () {
    echo '<link rel="manifest" href="' . esc_url(FLEET_PWA_URL . 'manifest.json') . '">';
    echo '<meta name="theme-color" content="#1e88e5">';
});

/* ================= SERVICE WORKER ROUTE ================= */




// Query var registrieren
add_filter('query_vars', function ($vars) {
    $vars[] = 'fleet_sw';
    return $vars;
});

// Service Worker ausliefern
add_action('template_redirect', function () {
    if (!get_query_var('fleet_sw')) return;

    // Wichtige Header
    header('Content-Type: application/javascript');
    header('Service-Worker-Allowed: /');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    // Datei ausliefern – ohne doppelten Slash
    readfile(FLEET_PWA_PATH . 'sw.js');
    exit;
});

wp_localize_script('fleet-app', 'FleetConfig', [
    'restUrl' => rest_url('/kfz-pwa/v1/'),
    'swUrl'   => home_url('/sw.js'), 
    'nonce'   => wp_create_nonce('wp_rest')
]);

// Register Shortcode
add_shortcode('fleet_pwa', function() {
    ob_start();
    $template = FLEET_PWA_PATH . 'templates/app-shell.php';
    if (file_exists($template)) {
        include $template;
    } else {
        echo '<p>Template not found</p>';
    }
    return ob_get_clean();
});
