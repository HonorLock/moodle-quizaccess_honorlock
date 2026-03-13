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
 * @coversDefaultClass \quizaccess_honorlock\external\update_quiz
 */
final class update_quiz_test extends \advanced_testcase {
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

        $this->assertCount(0, $DB->get_records('quizaccess_honorlock', []));

        $result = update_quiz::execute($quiz11->id, 1);
        $result = update_quiz::clean_returnvalue(update_quiz::execute_returns(), $result);
        $expected = [
            'success' => true,
            'errors' => [],
        ];
        $this->assertSame($expected, $result);
        $this->assertCount(1, $DB->get_records('quizaccess_honorlock', []));
        $this->assertTrue($DB->record_exists(
            'quizaccess_honorlock',
            ['quizid' => $quiz11->id, 'honorlockenable' => 1]
        ));

        $result = update_quiz::execute($quiz12->id, 0);
        $result = update_quiz::clean_returnvalue(update_quiz::execute_returns(), $result);
        $this->assertSame($expected, $result);
        $this->assertCount(2, $DB->get_records('quizaccess_honorlock', []));
        $this->assertTrue($DB->record_exists(
            'quizaccess_honorlock',
            ['quizid' => $quiz11->id, 'honorlockenable' => 1]
        ));
        $this->assertTrue($DB->record_exists(
            'quizaccess_honorlock',
            ['quizid' => $quiz12->id, 'honorlockenable' => 0]
        ));

        $result = update_quiz::execute($quiz12->id, null);
        $result = update_quiz::clean_returnvalue(update_quiz::execute_returns(), $result);
        $this->assertSame($expected, $result);
        $this->assertCount(1, $DB->get_records('quizaccess_honorlock', []));
        $this->assertTrue($DB->record_exists(
            'quizaccess_honorlock',
            ['quizid' => $quiz11->id, 'honorlockenable' => 1]
        ));
    }
}
