<?php
use PHPUnit\Framework\TestCase;

if ( ! defined('ABSPATH') ) {
    define('ABSPATH', __DIR__ . '/');
}

if ( ! function_exists('get_site_url') ) {
    function get_site_url(){ return 'https://default.com'; }
}

require_once __DIR__ . '/../vendor/autoload.php';

class CorsTest extends TestCase {
    /**
     * @runInSeparateProcess
     */
    public function testOriginAllowed() {
        define('PETIA_ALLOWED_ORIGINS', 'https://allowed.com,https://other.com');
        $cors = new PetIA_CORS();
        $this->assertTrue($cors->is_origin_allowed('https://allowed.com'));
        $this->assertFalse($cors->is_origin_allowed('https://evil.com'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testWildcardAllowsAllOrigins() {
        define('PETIA_ALLOWED_ORIGINS', '*');
        $headers = [];
        $cors    = new PetIA_CORS(function($h) use (&$headers){ $headers[] = $h; });
        $origin  = 'https://example.com';
      
        $this->assertTrue($cors->is_origin_allowed($origin));
      
        $prop = new ReflectionProperty(PetIA_CORS::class, 'allow_all');
        $prop->setAccessible(true);
        $this->assertTrue($prop->getValue($cors));
      
        $method = new ReflectionMethod(PetIA_CORS::class, 'send_cors_headers');
        $method->setAccessible(true);
        $method->invoke($cors, $origin);

        $this->assertContains("Access-Control-Allow-Origin: {$origin}", $headers);
        $this->assertContains('Vary: Origin', $headers);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSpecificOriginsDoNotVary() {
        define('PETIA_ALLOWED_ORIGINS', 'https://allowed.com');
        $headers = [];
        $cors    = new PetIA_CORS(function($h) use (&$headers){ $headers[] = $h; });
        $origin  = 'https://allowed.com';

        $this->assertTrue($cors->is_origin_allowed($origin));

        $method = new ReflectionMethod(PetIA_CORS::class, 'send_cors_headers');
        $method->setAccessible(true);
        $method->invoke($cors, $origin);

        $this->assertContains("Access-Control-Allow-Origin: {$origin}", $headers);
        $this->assertNotContains('Vary: Origin', $headers);
    }
}
