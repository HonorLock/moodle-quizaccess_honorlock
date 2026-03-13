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
 * @coversDefaultClass \quizaccess_honorlock\external\get_questions
 */
final class get_questions_test extends \advanced_testcase {
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

        $result = get_questions::execute($quiz11->id);
        $result = get_questions::clean_returnvalue(get_questions::execute_returns(), $result);
        $expected = [
            'success' => true,
            'data' => [],
            'errors' => [],
        ];
        $this->assertSame($expected, $result);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question1 = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question1->id, $quiz11);
        $question2 = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question2->id, $quiz11);

        $result = get_questions::execute($quiz11->id);
        $result = get_questions::clean_returnvalue(get_questions::execute_returns(), $result);

        $expected = [
            'success' => true,
            'data' => [
                [
                    'id' => (int)$question1->id,
                    'quizid' => (int)$quiz11->id,
                    'title' => $question1->name,
                    'intro' => $question1->questiontext,
                ],
                [
                    'id' => (int)$question2->id,
                    'quizid' => (int)$quiz11->id,
                    'title' => $question2->name,
                    'intro' => $question2->questiontext,
                ],
            ],
            'errors' => [],
        ];
        $this->assertSame($expected, $result);
    }
}
