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

namespace quizaccess_honorlock;

use mod_quiz\quiz_settings;
use quizaccess_honorlock\local\util;

/**
 * Honorlock test.
 *
 * @coversDefaultClass \quizaccess_honorlock
 *
 * @package   quizaccess_honorlock
 * @copyright 2024 Honorlock (https://honorlock.com/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule_test extends \advanced_testcase {
    /**
     * Test setup.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();

        require_once($CFG->dirroot . '/mod/quiz/accessrule/honorlock/rule.php');
    }

    /**
     * Test method.
     * @covers ::make
     */
    public function test_make(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $context = \context_module::instance($cm->id);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($saq->id, $quiz);
        $quizobj = quiz_settings::create($quiz->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->assertNull(\quizaccess_honorlock::make($quizobj, time(), false));

        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);
        $this->assertNull(\quizaccess_honorlock::make($quizobj, time(), false));
        // Automatically disabled for now.
        $he = $DB->get_record('quizaccess_honorlock', ['quizid' => $quiz->id], '*', MUST_EXIST);
        $this->assertSame('0', $he->honorlockenable);

        $he->honorlockenable = 1;
        $DB->update_record('quizaccess_honorlock', $he);
        $this->assertInstanceOf('quizaccess_honorlock', \quizaccess_honorlock::make($quizobj, time(), false));
    }

    /**
     * Test method.
     * @covers ::sync_honorlockenable
     */
    public function test_sync_honorlockenable(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $context = \context_module::instance($cm->id);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($saq->id, $quiz);
        $quizobj = quiz_settings::create($quiz->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->assertFalse(\quizaccess_honorlock::sync_honorlockenable($quiz->id));
        $this->assertFalse($DB->record_exists('quizaccess_honorlock', ['quizid' => $quiz->id]));

        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);
        // Automatically disabled for now.
        $this->assertFalse(\quizaccess_honorlock::sync_honorlockenable($quiz->id));
        $he = $DB->get_record('quizaccess_honorlock', ['quizid' => $quiz->id], '*', MUST_EXIST);
        $this->assertSame('0', $he->honorlockenable);
    }

    /**
     * Test method.
     * @covers ::description
     */
    public function test_description(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $context = \context_module::instance($cm->id);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($saq->id, $quiz);
        $quizobj = quiz_settings::create($quiz->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);
        $DB->insert_record('quizaccess_honorlock', [
            'quizid' => $quiz->id,
            'honorlockenable' => 1,
        ]);

        $rule = \quizaccess_honorlock::make($quizobj, time(), false);
        $this->assertSame('Honorlock Proctoring is mandatory for this quiz.', $rule->description());
    }

    /**
     * Test method.
     * @covers ::delete_settings
     */
    public function test_delete_settings(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $context = \context_module::instance($cm->id);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($saq->id, $quiz);
        $quizobj = quiz_settings::create($quiz->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);
        $DB->insert_record('quizaccess_honorlock', [
            'quizid' => $quiz->id,
            'honorlockenable' => 1,
        ]);

        \quizaccess_honorlock::delete_settings($quiz);
        $this->assertFalse($DB->record_exists('quizaccess_honorlock', ['quizid' => $quiz->id]));
    }

    /**
     * Test method.
     * @covers ::is_preflight_check_required
     */
    public function test_is_preflight_check_required(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $context = \context_module::instance($cm->id);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($saq->id, $quiz);
        $quizobj = quiz_settings::create($quiz->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);
        $DB->insert_record('quizaccess_honorlock', [
            'quizid' => $quiz->id,
            'honorlockenable' => 1,
        ]);

        $this->setUser($user);

        $rule = \quizaccess_honorlock::make($quizobj, time(), false);
        $this->assertTrue($rule->is_preflight_check_required(null));

        util::set_session_data((int)$quiz->id, 1);
        $this->assertFalse($rule->is_preflight_check_required(null));

        util::set_session_data(-1, 1);
        $this->assertTrue($rule->is_preflight_check_required(null));
    }

    /**
     * Test method.
     * @covers ::notify_preflight_check_passed
     */
    public function test_notify_preflight_check_passed(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $context = \context_module::instance($cm->id);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($saq->id, $quiz);
        $quizobj = quiz_settings::create($quiz->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);
        $DB->insert_record('quizaccess_honorlock', [
            'quizid' => $quiz->id,
            'honorlockenable' => 1,
        ]);

        $this->setUser($user);

        $rule = \quizaccess_honorlock::make($quizobj, time(), false);
        $this->assertTrue($rule->is_preflight_check_required(null));

        $errors = $rule->validate_preflight_check([], [], [], null);
        $this->assertSame([], $errors);

        try {
            $rule->notify_preflight_check_passed(null);
            $this->fail('redirection expected');
        } catch (\moodle_exception $ex) {
            $this->assertSame('Unsupported redirect detected, script execution terminated', $ex->getMessage());
        }

        $this->assertTrue($rule->is_preflight_check_required(null));
    }

    /**
     * Test method.
     * @covers ::current_attempt_finished
     */
    public function test_current_attempt_finished(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $context = \context_module::instance($cm->id);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($saq->id, $quiz);
        $quizobj = quiz_settings::create($quiz->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        util::activate('aa-bb-cc', 'dskjdsakj', util::HONORLOCK_URL);
        $DB->insert_record('quizaccess_honorlock', [
            'quizid' => $quiz->id,
            'honorlockenable' => 1,
        ]);

        $this->setUser($user);

        $rule = \quizaccess_honorlock::make($quizobj, time(), false);

        util::set_session_data((int)$quizobj->get_quizid(), 1);
        $this->assertFalse($rule->is_preflight_check_required(null));

        $rule->current_attempt_finished();
        $this->assertTrue($rule->is_preflight_check_required(null));
    }
}
