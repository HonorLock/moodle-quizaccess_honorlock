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
use quizaccess_honorlock\local\honorlock;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

/**
 * Honorlock Proctoring exam start callback.
 *
 * @package    quizaccess_honorlock
 * @copyright  2024 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exam_started extends external_api {
    /**
     * Get quiz questions parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'quizid' => new external_value(PARAM_INT, 'Quiz Id', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
                'attempt' => new external_value(PARAM_INT, 'Attempt number', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
            ]
        );
    }

    /**
     * Get quiz questions returns
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was operation successful? '),
                'errors' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'Error message'),
                    'List of errors'
                ),
            ],
        );
    }

    /**
     * Store session flag that exam started.
     *
     * @param int $quizid
     * @param int $attempt
     * @return array
     */
    public static function execute(int $quizid, int $attempt): array {
        global $DB, $SESSION, $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['quizid' => $quizid, 'attempt' => $attempt]
        );
        $quizid = $params['quizid'];
        $attempt = $params['attempt'];

        $syscontext = \context_system::instance();

        self::validate_context($syscontext);

        if (!util::is_honorlock_active()) {
            return ['success' => false, 'errors' => ['Honorlock is not active']];
        }

        require_login();

        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        if (!$quiz) {
            return ['success' => false, 'errors' => ['Quiz does not exist']];
        }
        if (!$course = $DB->get_record('course', ['id' => $quiz->course])) {
            return ['success' => false, 'errors' => ['Course does not exist']];
        }
        if (!$cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
            return ['success' => false, 'errors' => ['Course module does not exist']];
        }
        if ($cm->deletioninprogress) {
            return ['success' => false, 'errors' => ['Activity is scheduled for deletion']];
        }
        require_login($course, false, $cm);

        unset($SESSION->quizaccess_honorlock_exam);
        unset($SESSION->quizaccess_honorlock_attempt);

        // Verify user is on the authentication page.
        $honorlock = new honorlock();
        if (!$honorlock->verify_session($USER->id, $quizid, $attempt)) {
            return ['success' => false, 'errors' => ['User not authenticated with Honorlock']];
        }

        // Start session.
        if (!$honorlock->begin_session($USER->id, $quizid, $attempt)) {
            // Try continuing the session.
            if (!$honorlock->continue_session($USER->id, $quizid, $attempt)) {
                return ['success' => false, 'errors' => ['Cannot begin Honorlock exam session']];
            }
        }

        $SESSION->quizaccess_honorlock_exam = $quizid;
        $SESSION->quizaccess_honorlock_attempt = $attempt;

        return ['success' => true, 'errors' => []];
    }
}
