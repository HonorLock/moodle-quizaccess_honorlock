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
 * @copyright  2024 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_info extends external_api {
    /**
     * Get quiz questions parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
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
                'data' => new external_single_structure(
                    [
                        'wwwroot' => new external_value(PARAM_URL, 'Site www root to detect incorrect aliases'),
                        'moodleversion' => new external_value(PARAM_RAW, 'Moodle version'),
                        'lmsbrand' => new external_value(PARAM_RAW, 'null, Open LMS Work, etc.'),
                        'honorlock_version' => new external_value(PARAM_RAW, 'Honorlock plugin version'),
                        'honorlock_active' => new external_value(PARAM_BOOL, 'Is Honroloc integration active'),
                        'honorlock_client_id' => new external_value(PARAM_RAW, 'Honorlock client id'),
                        'honorlock_url' => new external_value(PARAM_RAW, 'Honorlock URL'),
                    ]
                ),
                'errors' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'Error message'),
                    'List of errors'
                ),
            ],
        );
    }

    /**
     * Obtain the site info.
     *
     * @return array
     */
    public static function execute(): array {
        global $CFG;

        $syscontext = \context_system::instance();
        self::validate_context($syscontext);

        require_capability('quizaccess/honorlock:ws', \context_system::instance());

        $errors = [];
        $result = [
            'wwwroot' => $CFG->wwwroot,
            'moodleversion' => get_config('core', 'version'),
            'lmsbrand' => null,
            'honorlock_version' => get_config('quizaccess_honorlock', 'version'),
            'honorlock_active' => (int)util::is_honorlock_active(),
            'honorlock_client_id' => (string)get_config('quizaccess_honorlock', 'honorlock_client_id'),
            'honorlock_url' => (string)get_config('quizaccess_honorlock', 'honorlock_url'),
        ];

        if (file_exists(__DIR__ . '/../../../../../../local/olms_work/version.php')) {
            $result['lmsbrand'] = 'Open LMS Work';
        }

        return ['success' => true, 'data' => $result, 'errors' => $errors];
    }
}
