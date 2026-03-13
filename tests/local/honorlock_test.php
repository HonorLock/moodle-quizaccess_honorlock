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
 * Honorlock Proctoring test for API communication.
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
 * @coversDefaultClass \quizaccess_honorlock\local\honorlock
 */
final class honorlock_test extends \advanced_testcase {
    /**
     * Set up tests.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();

        require_once("$CFG->libdir/filelib.php"); // Needed for curl class.
        $this->resetAfterTest();
    }

    /**
     * Clean up after test.
     */
    protected function tearDown(): void {
        // Do not allow the mocking ot affect other tests!
        $reflection = new \ReflectionClass(\curl::class);
        $mockresponses = $reflection->getProperty('mockresponses');
        $mockresponses->setAccessible(true);
        $mockresponses->setValue([]);

        parent::tearDown();
    }

    /**
     * Activate test site with mocked Honorclock.
     */
    protected static function setup_mocked_honorlock(): honorlock {
        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);

        $honorlockapicache = \cache::make(honorlockapi::COMPONENT_NAME, honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $honorlockapicache->set(
            honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY,
            ['token' => 'xyz...', 'expiration_time' => time() + 3600]
        );

        return new honorlock();
    }

    /**
     * Activate test site with real access to Honorclock.
     */
    protected static function setup_test_honorlock(): honorlock {
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

        util::activate(
            TEST_QUIZACCESS_HONORLOCK_CLIENT_ID,
            TEST_QUIZACCESS_HONORLOCK_CLIENT_SECRET,
            $honorlockurl
        );

        return new honorlock();
    }

    /**
     * Test Creation of Honorlock Class returns Class Instance
     *
     * @coversNothing
     */
    public function test_constructor(): void {
        try {
            new honorlock();
            $this->fail('Exception expected if not active');
        } catch (\moodle_exception $ex) {
            $this->assertInstanceOf(\coding_exception::class, $ex);
            $this->assertSame(
                'Coding error detected, it must be fixed by a programmer: Honorlock is not active!',
                $ex->getMessage()
            );
        }

        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);
        $honorlock = new honorlock();
        $this->assertInstanceOf(honorlock::class, $honorlock);
    }

    /**
     * Test method.
     * @covers ::diagnose_connection
     */
    public function test_diagnose_connection(): void {
        $honorlock = self::setup_mocked_honorlock();

        $testresponse = (object)[
            'data' => [
                'uuid' => 'b5a82011-b42f-4670-9bfc-f2335c1207fa',
                'name' => 'Some name',
                'code' => 'SOMENAME',
            ]];
        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->diagnose_connection();
        $this->assertSame([], $result);
    }

    /**
     * Test method.
     * @covers ::diagnose_connection
     */
    public function test_diagnose_connection_real(): void {
        $honorlock = self::setup_test_honorlock();

        $result = $honorlock->diagnose_connection();
        $this->assertSame([], $result);
    }

    /**
     * Test method.
     * @covers ::extension_check
     */
    public function test_extension_check(): void {
        $honorlock = self::setup_mocked_honorlock();

        $testresponse = (object)[
            "data" => [
                "iframe_src" => "https://app.honorlock.com/install/extension?locale=en",
                "extension_id" => "easrpoxsvfplyfubtodkzvtjezcsfqrz",
            ]];

        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->extension_check();
        $this->assertIsObject($result);
        $this->assertEquals($result->iframe_src, $testresponse->data['iframe_src']);

        $testresponse = (object)[
            "data" => [
                "message" => "something",
            ]];

        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->extension_check();
        $this->assertNull($result);
    }

    /**
     * Test method.
     * @covers ::extension_check
     */
    public function test_extension_check_real(): void {
        $honorlock = self::setup_test_honorlock();

        $result = $honorlock->extension_check();
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('iframe_src', $result);
        $this->assertObjectHasProperty('extension_id', $result);
    }

    /**
     * Test method.
     * @covers ::create_session
     */
    public function test_create_session(): void {
        $honorlock = self::setup_mocked_honorlock();

        $testresponse = (object)[
            "data" => [
                "session" => [],
                "camera_url" => "string",
                "configurations" => [],
            ],
        ];
        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->create_session(['test_data']);
        $this->assertIsObject($result);
        $this->assertSame((array)$result, $testresponse->data);

        $testresponse = (object)[
            "data" => [
                "message" => 'fdslkjhfjkdskhjfds',
            ],
        ];
        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->create_session(['test_data']);
        $this->assertNull($result);
    }

    /**
     * Test method.
     * @covers ::get_exam_instructions
     */
    public function test_get_exam_instructions(): void {
        $honorlock = self::setup_mocked_honorlock();

        $testresponse = (object)[
            "data" => [
                "launch_screen_url" => "https://app.honorlock.com/install/extension?locale=en",
            ]];
        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->get_exam_instructions(1);
        $this->assertIsObject($result);
        $this->assertEquals((array)$result, $testresponse->data);
    }

    /**
     * Test method.
     * @covers ::verify_session
     */
    public function test_verify_session(): void {
        $honorlock = self::setup_mocked_honorlock();

        $testresponse = (object)[
            "data" => [
                "authenticated" => true,
            ]];
        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->verify_session(3, 2, 1);
        $this->assertTrue($result);

        $testresponse = (object)[
            "data" => [
                "authenticated" => false,
            ]];
        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->verify_session(3, 2, 1);
        $this->assertFalse($result);

        $testresponse = (object)[
            "data" => [
                "message" => 'fdslkjfsdjlkdf',
            ]];
        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->verify_session(3, 2, 1);
        $this->assertFalse($result);
    }

    /**
     * Test method.
     * @covers ::begin_session
     */
    public function test_begin_session(): void {
        $honorlock = self::setup_mocked_honorlock();

        $testresponse = (object)[
            "data" => [
                "event_type" => "string",
                "exam_taker_name" => "TestTaker",
                "created_at" => "2023-08-24T14:15:22Z",
            ],
        ];
        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->begin_session(11, 22, 33);
        $this->assertTrue($result);
    }

    /**
     * Test method.
     * @covers ::continue_session
     */
    public function test_continue_session(): void {
        $honorlock = self::setup_mocked_honorlock();

        $testresponse = (object)[
            "data" => [
                "event_type" => "string",
                "exam_taker_name" => "TestTaker",
                "created_at" => "2023-08-24T14:15:22Z",
            ],
        ];
        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->continue_session(11, 22, 33);
        $this->assertTrue($result);
    }

    /**
     * Test method.
     * @covers ::end_session
     */
    public function test_end_session(): void {
        $honorlock = self::setup_mocked_honorlock();

        $testresponse = (object)[
            "data" => [
                "event_type" => "string",
                "exam_taker_name" => "TestTaker",
                "created_at" => "2023-08-24T14:15:22Z",
            ],
        ];
        \curl::mock_response(json_encode($testresponse));
        $result = $honorlock->end_session(11, 22, 33);
        $this->assertTrue($result);
    }
}
