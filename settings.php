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
 * Honorlock Proctoring settings.
 *
 * @package    quizaccess_honorlock
 * @copyright  2023 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var admin_root $ADMIN */

$ADMIN->add(
    'modsettingsquizcat',
    new admin_externalpage(
        'quizaccess_honorlock_status',
        get_string('pluginname', 'quizaccess_honorlock'),
        new moodle_url('/mod/quiz/accessrule/honorlock/status.php')
    )
);

// Prevent adding of normal settings page.
$settings = null;
