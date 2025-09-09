<?php
use PHPUnit\Framework\TestCase;

if ( ! defined('PETIA_ALLOWED_ORIGINS') ) {
    define('PETIA_ALLOWED_ORIGINS', 'https://allowed.com,https://other.com');
}
if ( ! function_exists('get_site_url') ) {
    function get_site_url(){ return 'https://default.com'; }
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/class-petia-cors.php';

class CorsTest extends TestCase {
    public function testOriginAllowed() {
        $cors = new PetIA_CORS();
        $this->assertTrue($cors->is_origin_allowed('https://allowed.com'));
        $this->assertFalse($cors->is_origin_allowed('https://evil.com'));
    }
}
