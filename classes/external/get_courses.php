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
 * Honorlock Proctoring external API for getting courses.
 *
 * @package    quizaccess_honorlock
 * @copyright  2024 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_courses extends external_api {
    /**
     * Get quiz questions parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'ids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course Id', VALUE_REQUIRED, null, NULL_NOT_ALLOWED)
                ),
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
                        'id' => new external_value(PARAM_INT, 'Course Id'),
                        'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                        'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
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
     * Obtain the quizzes for a specific course
     *
     * @param int[] $ids
     * @return array
     */
    public static function execute(array $ids): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['ids' => $ids]);
        $ids = $params['ids'];

        $syscontext = \context_system::instance();
        self::validate_context($syscontext);

        require_capability('quizaccess/honorlock:ws', \context_system::instance());

        if (!util::is_honorlock_active()) {
            return ['success' => false, 'data' => [], 'errors' => ['Honorlock is not active']];
        }

        [$select, $params] = $DB->get_in_or_equal($ids);
        $sql = "SELECT c.id, c.fullname, c.shortname
                  FROM {course} c
                 WHERE c.id $select
              ORDER BY c.id ASC";
        $result = $DB->get_records_sql($sql, $params);
        $result = array_values($result);

        return ['success' => true, 'data' => $result, 'errors' => []];
    }
}
