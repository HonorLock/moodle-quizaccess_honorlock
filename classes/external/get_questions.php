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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

/**
 * Honorlock Proctoring external API for getting of quiz questions.
 *
 * @package    quizaccess_honorlock
 * @copyright  2023 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_questions extends external_api {
    /**
     * Get quiz questions parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'quizid' => new external_value(PARAM_INT, 'Quiz Id', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
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
                'data' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Question ID'),
                        'quizid' => new external_value(PARAM_INT, 'Quiz id'),
                        'title' => new external_value(PARAM_TEXT, 'Question Title'),
                        'intro' => new external_value(PARAM_RAW, 'Question Text'),
                    ])
                ),
                'errors' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'Error message'),
                    'List of errors'
                ),
            ],
        );
    }

    /**
     * Obtain the questions for a specific quiz
     *
     * @param int $quizid
     * @return array
     */
    public static function execute(int $quizid): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['quizid' => $quizid]);
        $quizid = $params['quizid'];

        require_capability('quizaccess/honorlock:ws', \context_system::instance());

        if (!util::is_honorlock_active()) {
            return ['success' => false, 'data' => [], 'errors' => ['Honorlock is not active']];
        }

        require_capability('quizaccess/honorlock:ws', \context_system::instance());

        try {
            $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
            // Check if the user has permission to view quiz questions.
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course);
            if (!$cm || $cm->deletioninprogress) {
                throw new \Exception('Activity is scheduled for deletion');
            }

            $result = [];
            // Fetch the questions based on the IDs.
            $quizobj = new \mod_quiz\quiz_settings($quiz, $cm, $quiz->course);
            $quizobj->preload_questions();
            $quizobj->load_questions();

            // Iterate over the questions.
            foreach ($quizobj->get_questions() as $question) {
                $result[] = [
                    'id' => $question->id,
                    'quizid' => $quizid,
                    'title' => $question->name,
                    'intro' => $question->questiontext,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => [],
                'errors' => [$e->getMessage()],
            ];
        }
        return ['success' => true, 'data' => $result, 'errors' => []];
    }
}
