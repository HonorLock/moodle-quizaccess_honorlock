<?php
// This file is part of the Honorlock Proctoring plugin for Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace quizaccess_honorlock\local;

/**
 * Honorlock Proctoring test for low level communication helper.
 *
 * To run test with real connection to Honorlock servers
 * you need to provide test client id and secret via config.php,
 * see https://app.honorlock.com/auth/login
 *
 * define('TEST_QUIZACCESS_HONORLOCK_CLIENT_ID', 'paste-client-id-here');
 * define('TEST_QUIZACCESS_HONORLOCK_CLIENT_SECRET', 'paste-client-secret-here');
 *
 * and optionally:
 *
 * define('TEST_QUIZACCESS_HONORLOCK_URL', 'https://testapp.honorlock.com');
 *
 * @package   quizaccess_honorlock
 * @copyright 2023 Honorlock (https://honorlock.com/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \quizaccess_honorlock\local\honorlockapi
 */
final class honorlockapi_test extends \advanced_testcase {
    /**
     * Set up tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Configure system to use honorlockapi.
     *
     * NOTE: this requires real access info!
     */
    protected static function create_honorlockapi(): honorlockapi {
        if (
            !defined('TEST_QUIZACCESS_HONORLOCK_CLIENT_ID')
            || !defined('TEST_QUIZACCESS_HONORLOCK_CLIENT_SECRET')
        ) {
            self::markTestSkipped('Honorlock test client credentials not provided');
        }

        if (defined('TEST_QUIZACCESS_HONORLOCK_URL')) {
            $honorlockurl = TEST_QUIZACCESS_HONORLOCK_URL;
        } else {
            $honorlockurl = util::HONORLOCK_URL;
        }

        return new honorlockapi(
            TEST_QUIZACCESS_HONORLOCK_CLIENT_ID,
            TEST_QUIZACCESS_HONORLOCK_CLIENT_SECRET,
            $honorlockurl
        );
    }

    /**
     * Test method.
     * @covers ::validate_credentials
     */
    public function test_validate_credentials(): void {
        $this->assertSame(
            ['clientid' => 'Required', 'clientsecret' => 'Required', 'honorlockurl' => 'Required'],
            honorlockapi::validate_credentials('', '', '')
        );
        $this->assertSame(
            ['clientsecret' => 'Required'],
            honorlockapi::validate_credentials('a-b-c', '', util::HONORLOCK_URL)
        );
        $this->assertSame(
            ['clientid' => 'Required'],
            honorlockapi::validate_credentials('', 'abc', util::HONORLOCK_URL)
        );

        if (
            !defined('TEST_QUIZACCESS_HONORLOCK_CLIENT_ID')
            || !defined('TEST_QUIZACCESS_HONORLOCK_CLIENT_SECRET')
        ) {
            $this->markTestSkipped('Honorlock test client credentials not provided');
        }

        if (defined('TEST_QUIZACCESS_HONORLOCK_URL')) {
            $honorlockappurl = TEST_QUIZACCESS_HONORLOCK_URL;
        } else {
            $honorlockappurl = util::HONORLOCK_URL;
        }

        $this->assertSame(
            [],
            honorlockapi::validate_credentials(
                TEST_QUIZACCESS_HONORLOCK_CLIENT_ID,
                TEST_QUIZACCESS_HONORLOCK_CLIENT_SECRET,
                $honorlockappurl
            )
        );

        $this->assertSame(
            ['clientsecret' => 'Error'],
            honorlockapi::validate_credentials(
                TEST_QUIZACCESS_HONORLOCK_CLIENT_ID,
                'Moodle PHPUNIT test failure with wrong secret',
                $honorlockappurl
            )
        );
    }

    /**
     * Test constructor.
     * @coversNothing
     */
    public function test_constructor(): void {
        $honorlockapi = $this->create_honorlockapi();
        $this->assertInstanceOf(honorlockapi::class, $honorlockapi);
    }

    /**
     * Test method.
     * @covers ::reset_caches
     */
    public function test_reset_caches(): void {
        honorlockapi::reset_caches();
    }

    /**
     * Test the private generate_token method.
     * @covers ::generate_token
     */
    public function test_generate_token(): void {
        $honorlockapi = $this->create_honorlockapi();

        $reflection = new \ReflectionClass(get_class($honorlockapi));
        $method = $reflection->getMethod('generate_token');
        $method->setAccessible(true);

        $result1 = $method->invoke($honorlockapi);
        $this->assertSame('Bearer', $result1->token_type);
        $this->assertIsString($result1->access_token);
        $this->assertGreaterThan(3600, $result1->expires_in);

        // Note that the same token might be returned here if the old one is not expired yet.
        $result2 = $method->invoke($honorlockapi);
        $this->assertSame('Bearer', $result2->token_type);
        $this->assertIsString($result2->access_token);
        $this->assertGreaterThan(3600, $result2->expires_in);
    }

    /**
     * Test caching in the private get_token method.
     * @covers ::get_token
     */
    public function test_get_token(): void {
        $honorlockapi = $this->create_honorlockapi();

        $reflection = new \ReflectionClass(get_class($honorlockapi));
        $method = $reflection->getMethod('get_token');
        $method->setAccessible(true);
        $token1 = $method->invoke($honorlockapi);
        $this->assertIsString($token1);
        $honorlockapicache = \cache::make(honorlockapi::COMPONENT_NAME, honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $cachedtoken = $honorlockapicache->get(honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $this->assertSame($token1, $cachedtoken['token']);

        $token2 = $method->invoke($honorlockapi);
        $this->assertSame($token1, $token2);
        $honorlockapicache = \cache::make(honorlockapi::COMPONENT_NAME, honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $cachedtoken = $honorlockapicache->get(honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $this->assertSame($token1, $cachedtoken['token']);

        honorlockapi::reset_caches();
        $honorlockapicache = \cache::make(honorlockapi::COMPONENT_NAME, honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $cachedtoken = $honorlockapicache->get(honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $this->assertSame(false, $cachedtoken);

        $token3 = $method->invoke($honorlockapi);
        $honorlockapicache = \cache::make(honorlockapi::COMPONENT_NAME, honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $cachedtoken = $honorlockapicache->get(honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $this->assertNotEmpty($cachedtoken['token']);

        $honorlockapicache = \cache::make(honorlockapi::COMPONENT_NAME, honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $honorlockapicache->set(honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY, ['token' => 'xyz', 'expiration_time' => time() + 60]);
        $token4 = $method->invoke($honorlockapi);
        $this->assertSame('xyz', $token4);

        $honorlockapicache = \cache::make(honorlockapi::COMPONENT_NAME, honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $honorlockapicache->set(honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY, ['token' => 'xyz', 'expiration_time' => time() - 60]);
        $token4 = $method->invoke($honorlockapi);
        $this->assertNotSame('xyz', $token4);
    }

    /**
     * Test method.
     * @covers ::send_request
     */
    public function test_send_request(): void {
        $honorlockapi = $this->create_honorlockapi();

        // Some GET method.
        $responsecode = null;
        $result = $honorlockapi->send_request('get', '/api/en/v1/me', [], $responsecode);
        $this->assertSame(200, $responsecode);
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('data', $result);
        $this->assertObjectHasProperty('uuid', $result->data);
        $this->assertObjectHasProperty('name', $result->data);
        $this->assertObjectHasProperty('code', $result->data);

        // No response code and payload.
        $result = $honorlockapi->send_request('get', '/api/en/v1/me');
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('data', $result);
        $this->assertObjectHasProperty('uuid', $result->data);
        $this->assertObjectHasProperty('name', $result->data);
        $this->assertObjectHasProperty('code', $result->data);

        // Some POST method.
        $payload = [
            'client_id' => TEST_QUIZACCESS_HONORLOCK_CLIENT_ID,
            'client_secret' => TEST_QUIZACCESS_HONORLOCK_CLIENT_SECRET,
        ];
        $responsecode = null;
        $result = $honorlockapi->send_request('post', '/api/en/v1/token', $payload, $responsecode);
        $this->assertSame(200, $responsecode);
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('data', $result);
        $this->assertObjectHasProperty('token_type', $result->data);
        $this->assertObjectHasProperty('access_token', $result->data);

        // Failure.
        $payload = [
            'client_id' => TEST_QUIZACCESS_HONORLOCK_CLIENT_ID,
            'client_secret' => 'Moodle PHPUNIT test failure with wrong secret',
        ];
        $responsecode = null;
        $result = $honorlockapi->send_request('post', '/api/en/v1/token', $payload, $responsecode);
        $this->assertSame(401, $responsecode);
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('message', $result);
    }
}
