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
 * @coversDefaultClass \quizaccess_honorlock\external\get_courses
 */
final class get_courses_test extends \advanced_testcase {
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

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();

        $result = get_courses::execute([$course2->id, $course1->id]);
        $result = get_courses::clean_returnvalue(get_courses::execute_returns(), $result);

        $expected = [
            'success' => true,
            'data' => [
                ['id' => (int)$course1->id, 'fullname' => $course1->fullname, 'shortname' => $course1->shortname],
                ['id' => (int)$course2->id, 'fullname' => $course2->fullname, 'shortname' => $course2->shortname],
            ],
            'errors' => [],
        ];
        $this->assertSame($expected, $result);
    }
}
