<?php
use PHPUnit\Framework\TestCase;

if ( ! defined('AUTH_KEY') ) {
    define('AUTH_KEY', 'testkey');
}

// Minimal WordPress stubs
if ( ! function_exists('add_action') ) { function add_action(...$args) {} }
if ( ! function_exists('add_filter') ) { function add_filter(...$args) {} }
if ( ! function_exists('register_rest_route') ) { function register_rest_route(...$args) {} }
if ( ! function_exists('is_admin') ) { function is_admin() { return false; } }
if ( ! function_exists('__') ) { function __($s,$d=null){ return $s; } }
if ( ! function_exists('esc_html__') ) { function esc_html__($s,$d=null){ return $s; } }
if ( ! function_exists('get_site_url') ) { function get_site_url(){ return ''; } }
if ( ! function_exists('wp_die') ) { function wp_die($msg){ throw new Exception($msg); } }
if ( ! function_exists('get_transient') ) { function get_transient(){ return false; } }

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/class-petia-app-bridge.php';

class TokenTest extends TestCase {
    public function testGenerateAndDecodeToken() {
        $bridge = new PetIA_App_Bridge();
        $ref = new ReflectionClass($bridge);
        $gen = $ref->getMethod('generate_token');
        $gen->setAccessible(true);
        $dec = $ref->getMethod('decode_token');
        $dec->setAccessible(true);

        $token = $gen->invoke($bridge, 123);
        $payload = $dec->invoke($bridge, $token);
        $this->assertEquals(123, $payload['sub']);
    }
}
