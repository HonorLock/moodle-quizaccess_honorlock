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

/**
 * Honorlock web service tests.
 *
 * @package    quizaccess_honorlock
 * @copyright  2024 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \quizaccess_honorlock\external\get_quizzes
 */
final class get_quizzes_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test method.
     * @covers ::execute
     */
    public function test_execute(): void {
        global $DB;

        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);
        $wsuserid = get_config('quizaccess_honorlock', 'wsuserid');
        $wsuser = $DB->get_record('user', ['id' => $wsuserid], '*', MUST_EXIST);
        $this->setUser($wsuser);

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $course1 = $this->getDataGenerator()->create_course();
        $quiz11 = $quizgenerator->create_instance(['course' => $course1->id]);
        $quiz12 = $quizgenerator->create_instance(['course' => $course1->id]);
        $course2 = $this->getDataGenerator()->create_course();
        $quiz21 = $quizgenerator->create_instance(['course' => $course2->id]);
        $course3 = $this->getDataGenerator()->create_course();

        $DB->insert_record('quizaccess_honorlock', ['quizid' => $quiz12->id, 'honorlockenable' => 1]);
        $DB->insert_record('quizaccess_honorlock', ['quizid' => $quiz21->id, 'honorlockenable' => 0]);

        $result = get_quizzes::execute($course1->id);
        $result = get_quizzes::clean_returnvalue(get_quizzes::execute_returns(), $result);
        $expected = [
            'success' => true,
            'data' => [
                [
                    'id' => (int)$quiz11->id,
                    'courseid' => (int)$course1->id,
                    'name' => $quiz11->name,
                    'intro' => $quiz11->intro,
                    'introformat' => (int)$quiz11->introformat,
                    'timeopen' => (int)$quiz11->timeopen,
                    'timeclose' => (int)$quiz11->timeclose,
                    'honorlockenable' => null,
                ],
                [
                    'id' => (int)$quiz12->id,
                    'courseid' => (int)$course1->id,
                    'name' => $quiz12->name,
                    'intro' => $quiz12->intro,
                    'introformat' => (int)$quiz12->introformat,
                    'timeopen' => (int)$quiz12->timeopen,
                    'timeclose' => (int)$quiz12->timeclose,
                    'honorlockenable' => 1,
                ],
            ],
            'errors' => [],
        ];
        $this->assertSame($expected, $result);

        $result = get_quizzes::execute($course2->id);
        $result = get_quizzes::clean_returnvalue(get_quizzes::execute_returns(), $result);
        $expected = [
            'success' => true,
            'data' => [
                [
                    'id' => (int)$quiz21->id,
                    'courseid' => (int)$course2->id,
                    'name' => $quiz21->name,
                    'intro' => $quiz21->intro,
                    'introformat' => (int)$quiz21->introformat,
                    'timeopen' => (int)$quiz21->timeopen,
                    'timeclose' => (int)$quiz21->timeclose,
                    'honorlockenable' => 0,
                ],
            ],
            'errors' => [],
        ];
        $this->assertSame($expected, $result);

        $result = get_quizzes::execute($course3->id);
        $result = get_quizzes::clean_returnvalue(get_quizzes::execute_returns(), $result);
        $expected = [
            'success' => true,
            'data' => [],
            'errors' => [],
        ];
        $this->assertSame($expected, $result);
    }
}
