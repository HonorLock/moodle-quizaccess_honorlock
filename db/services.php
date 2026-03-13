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

/**
 * Honorlock Proctoring webservice functions.
 *
 * @package    quizaccess_honorlock
 * @copyright  2023 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'quizaccess_honorlock_get_courses' => [
        'classname' => quizaccess_honorlock\external\get_courses::class,
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Get courses',
        'type' => 'read',
        'ajax' => false,
    ],
    'quizaccess_honorlock_get_info' => [
        'classname' => quizaccess_honorlock\external\get_info::class,
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Get site info for diagnostic purposes',
        'type' => 'read',
        'ajax' => false,
    ],
    'quizaccess_honorlock_get_questions' => [
        'classname' => quizaccess_honorlock\external\get_questions::class,
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Get quiz questions',
        'type' => 'read',
        'ajax' => false,
    ],
    'quizaccess_honorlock_get_quizzes' => [
        'classname' => quizaccess_honorlock\external\get_quizzes::class,
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Get quizzes',
        'type' => 'read',
        'ajax' => false,
    ],
    'quizaccess_honorlock_update_quiz' => [
        'classname' => quizaccess_honorlock\external\update_quiz::class,
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Get quizzes',
        'type' => 'write',
        'ajax' => false,
    ],
    'quizaccess_honorlock_exam_started' => [
        'classname' => quizaccess_honorlock\external\exam_started::class,
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Get quizzes',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];

$services = [
    'Honorlock Proctoring Web Services' => [
        'functions' => [
            'quizaccess_honorlock_get_courses',
            'quizaccess_honorlock_get_info',
            'quizaccess_honorlock_get_questions',
            'quizaccess_honorlock_get_quizzes',
            'quizaccess_honorlock_update_quiz',
        ],
        'restrictedusers' => 1,
        'enabled' => 0,
        'shortname' => 'quizaccess_honorlock',
        'requiredcapability' => 'quizaccess/honorlock:ws',
    ],
];
