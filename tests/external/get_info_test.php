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
 * @coversDefaultClass \quizaccess_honorlock\external\get_info
 */
final class get_info_test extends \advanced_testcase {
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

        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);
        $wsuserid = get_config('quizaccess_honorlock', 'wsuserid');
        $wsuser = $DB->get_record('user', ['id' => $wsuserid], '*', MUST_EXIST);
        $this->setUser($wsuser);

        $result = get_info::execute();
        $result = get_info::clean_returnvalue(get_info::execute_returns(), $result);

        if (get_config('local_olms_work', 'version')) {
            $brand = 'Open LMS Work';
        } else {
            $brand = null;
        }

        $expected = [
            'success' => true,
            'data' => [
                'wwwroot' => 'https://www.example.com/moodle',
                'moodleversion' => $CFG->version,
                'lmsbrand' => $brand,
                'honorlock_version' => get_config('quizaccess_honorlock', 'version'),
                'honorlock_active' => true,
                'honorlock_client_id' => 'aa-bb-cc',
                'honorlock_url' => 'https://app.honorlock.com',
            ],
            'errors' => [],
        ];
        $this->assertSame($expected, $result);
    }
}
