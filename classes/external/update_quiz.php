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
 * Honorlock Proctoring external API for updating of quiz settings.
 *
 * @package    quizaccess_honorlock
 * @copyright  2024 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_quiz extends external_api {
    /**
     * Get quiz questions parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'quizid' => new external_value(PARAM_INT, 'Quiz Id', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
                'honorlockenable' => new external_value(PARAM_INT, 'Enabled Honorlock', VALUE_REQUIRED, null, NULL_ALLOWED),
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
     * Update quiz settings.
     *
     * @param int $quizid
     * @param int|null $honorlockenable
     * @return array
     */
    public static function execute(int $quizid, ?int $honorlockenable): array {
        global $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['quizid' => $quizid, 'honorlockenable' => $honorlockenable]
        );
        $quizid = $params['quizid'];
        $honorlockenable = $params['honorlockenable'];
        $syscontext = \context_system::instance();

        if ($honorlockenable !== null) {
            $honorlockenable = (int)(bool)$honorlockenable;
        }

        self::validate_context($syscontext);
        require_capability('quizaccess/honorlock:ws', $syscontext);

        if (!util::is_honorlock_active()) {
            return ['success' => false, 'errors' => ['Honorlock is not active']];
        }

        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        if (!$quiz) {
            return ['success' => false, 'errors' => ['Quiz does not exist']];
        }

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course);
        if (!$cm || $cm->deletioninprogress) {
            return ['success' => false, 'errors' => ['Activity is scheduled for deletion']];
        }

        if ($honorlockenable === null) {
            $DB->delete_records('quizaccess_honorlock', ['quizid' => $quizid]);
        } else {
            $current = $DB->get_record('quizaccess_honorlock', ['quizid' => $quizid]);
            if ($current) {
                if ($current->honorlockenable != $honorlockenable) {
                    $current->honorlockenable = $honorlockenable;
                    $DB->update_record('quizaccess_honorlock', $current);
                }
            } else {
                $DB->insert_record(
                    'quizaccess_honorlock',
                    ['quizid' => $quizid, 'honorlockenable' => $honorlockenable]
                );
            }
        }

        return ['success' => true, 'errors' => []];
    }
}
