<?php
use PHPUnit\Framework\TestCase;

if ( ! function_exists('get_site_url') ) {
    function get_site_url(){ return 'https://default.com'; }
}

if ( ! defined('ABSPATH') ) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/../vendor/autoload.php';

class CorsTest extends TestCase {
    public function testAllowsAllOriginsByDefault() {
        require __DIR__ . '/../includes/class-petia-cors.php';
        $cors = new PetIA_CORS();
        $this->assertTrue($cors->is_origin_allowed('https://any.com'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testOriginAllowedWithConfiguredList() {
        define('PETIA_ALLOWED_ORIGINS', 'https://allowed.com,https://other.com');
        require __DIR__ . '/../includes/class-petia-cors.php';
        $cors = new PetIA_CORS();
        $this->assertTrue($cors->is_origin_allowed('https://allowed.com'));
        $this->assertFalse($cors->is_origin_allowed('https://evil.com'));
    }
}
