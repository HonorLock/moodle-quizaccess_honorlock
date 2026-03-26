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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace quizaccess_honorlock\external;

use quizaccess_honorlock\local\util;
use quizaccess_honorlock\local\honorlockapi;

/**
 * Honorlock web service tests.
 *
 * @package    quizaccess_honorlock
 * @copyright  2024 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \quizaccess_honorlock\external\exam_started
 */
final class exam_started_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test method.
     * @covers ::execute
     */
    public function test_execute(): void {
        global $CFG, $DB;
        require_once("$CFG->libdir/filelib.php");

        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);

        $honorlockapicache = \cache::make(honorlockapi::COMPONENT_NAME, honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY);
        $honorlockapicache->set(
            honorlockapi::HONORLOCK_API_TOKEN_CACHE_KEY,
            ['token' => 'xyz...', 'expiration_time' => time() + 3600]
        );

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $course1 = $this->getDataGenerator()->create_course();
        $quiz1 = $quizgenerator->create_instance(['course' => $course1->id]);
        $DB->insert_record('quizaccess_honorlock', ['quizid' => $quiz1->id, 'honorlockenable' => 1]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course1->id, 'student');
        $this->setUser($user);

        $testresponse = (object)[
            "data" => [
                "event_type" => "string",
                "exam_taker_name" => "TestTaker",
                "created_at" => "2023-08-24T14:15:22Z",
            ],
        ];
        \curl::mock_response(json_encode($testresponse));
        $testresponse = (object)[
            "data" => [
                "authenticated" => true,
            ]];
        \curl::mock_response(json_encode($testresponse));
        $result = exam_started::execute($quiz1->id, 1);
        $result = exam_started::clean_returnvalue(exam_started::execute_returns(), $result);
        $this->assertSame(['success' => true, 'errors' => []], $result);
        $cache = \cache::make('quizaccess_honorlock', 'honorlock_session');
        $cachedata = $cache->get(util::ACTIVE_EXAM_CACHE_KEY);
        $this->assertNotFalse($cachedata);
        $this->assertSame((int)$quiz1->id, $cachedata['quizid']);
        $this->assertSame(1, $cachedata['attempt']);

        $testresponse = (object)[
            "data" => [
                "event_type" => "string",
                "exam_taker_name" => "TestTaker",
                "created_at" => "2023-08-24T14:15:22Z",
            ],
        ];
        \curl::mock_response(json_encode($testresponse));
        $testresponse = (object)[
            "data" => [
                "authenticated" => true,
            ]];
        \curl::mock_response(json_encode($testresponse));
        $result = exam_started::execute($quiz1->id, 2);
        $result = exam_started::clean_returnvalue(exam_started::execute_returns(), $result);
        $this->assertSame(['success' => true, 'errors' => []], $result);
        $cachedata = $cache->get(util::ACTIVE_EXAM_CACHE_KEY);
        $this->assertNotFalse($cachedata);
        $this->assertSame((int)$quiz1->id, $cachedata['quizid']);
        $this->assertSame(2, $cachedata['attempt']);

        $testresponse = (object)[
            "data" => [
                "authenticated" => false,
            ]];
        \curl::mock_response(json_encode($testresponse));
        $result = exam_started::execute($quiz1->id, 2);
        $result = exam_started::clean_returnvalue(exam_started::execute_returns(), $result);
        $this->assertSame(
            ['success' => false, 'errors' => [get_string('usernotauthenticated', 'quizaccess_honorlock')]],
            $result
        );
        $this->assertFalse($cache->get(util::ACTIVE_EXAM_CACHE_KEY));

        $testresponse = (object)[
            "data" => [],
        ];
        \curl::mock_response(json_encode($testresponse));
        $testresponse = (object)[
            "data" => [
                "authenticated" => true,
            ]];
        \curl::mock_response(json_encode($testresponse));
        $result = exam_started::execute($quiz1->id, 2);
        $result = exam_started::clean_returnvalue(exam_started::execute_returns(), $result);
        $this->assertSame(
            ['success' => false, 'errors' => [get_string('cannotbeginsession', 'quizaccess_honorlock')]],
            $result
        );
        $this->assertFalse($cache->get(util::ACTIVE_EXAM_CACHE_KEY));
    }
}
