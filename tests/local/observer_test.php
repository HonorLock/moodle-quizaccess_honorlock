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

use mod_quiz\quiz_settings;
use mod_quiz\quiz_attempt;
use quizaccess_honorlock\local\util;

/**
 * Honorlock observer test.
 *
 * @package   quizaccess_honorlock
 * @copyright 2024 Honorlock (https://honorlock.com/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \quizaccess_honorlock\local\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Set up tests.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();

        require_once($CFG->dirroot . '/mod/quiz/accessrule/honorlock/rule.php');
    }

    /**
     * Test method.
     * @covers ::attempt_submitted
     */
    public function test_attempt_submitted(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $context = \context_module::instance($cm->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2, 'layout' => '1,0']);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz, 1);
        $quizobj = quiz_settings::create($quiz->id);

        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);

        $DB->insert_record('quizaccess_honorlock', [
            'quizid' => $quiz->id,
            'honorlockenable' => 1,
        ]);

        $this->setUser($user);

        util::set_cache_data(util::ACTIVE_EXAM_CACHE_KEY, ['quizid' => (int)$quiz->id, 'attempt' => 1]);

        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 1, null);

        $this->assertNotNull(util::get_cache_data(util::ACTIVE_EXAM_CACHE_KEY));

        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish(time(), false);

        $this->assertNull(util::get_cache_data(util::ACTIVE_EXAM_CACHE_KEY));
    }

    /**
     * Test method.
     * @covers ::attempt_abandoned
     */
    public function test_attempt_abandoned(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $context = \context_module::instance($cm->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2, 'layout' => '1,0']);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz, 1);
        $quizobj = quiz_settings::create($quiz->id);

        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);

        $DB->insert_record('quizaccess_honorlock', [
            'quizid' => $quiz->id,
            'honorlockenable' => 1,
        ]);

        $this->setUser($user);

        util::set_cache_data(util::ACTIVE_EXAM_CACHE_KEY, ['quizid' => (int)$quiz->id, 'attempt' => 1]);
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 1, null);

        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_abandon(time(), false);

        $this->assertNull(util::get_cache_data(util::ACTIVE_EXAM_CACHE_KEY));
    }
}
