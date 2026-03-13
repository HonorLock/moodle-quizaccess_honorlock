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
 * Honorlock Proctoring status page.
 *
 * @package    quizaccess_honorlock
 * @copyright  2024 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $CFG */

use quizaccess_honorlock\local\util;

require_once(__DIR__ . '/../../../../config.php');
require_once("$CFG->libdir/adminlib.php");

admin_externalpage_setup('quizaccess_honorlock_status', '', null, '', ['nosearch' => true]);

$pagetitlestr = get_string('pluginname', 'quizaccess_honorlock');

$PAGE->set_secondary_navigation(false);
$PAGE->set_title($pagetitlestr);
$PAGE->set_heading($pagetitlestr);

echo $OUTPUT->header();

echo '<dl class="row">';
foreach (util::get_status_data() as $status) {
    if ($status['value'] === '' || $status['value'] === null) {
        $value = '&nbsp;';
    } else {
        $value = $status['value'];
    }
    echo '<dt class="col-3">' . $status['name'] . ':</dt><dd class="col-9">' . $value . '</dd>';
}
echo '</dl>';

if (!isset($CFG->forced_plugin_settings['quizaccess_honorlock']['active'])) {
    if (util::is_honorlock_active()) {
        $url = new moodle_url('/mod/quiz/accessrule/honorlock/status_disable.php');
        echo $OUTPUT->single_button($url, get_string('disable', 'quizaccess_honorlock'));
    } else {
        $url = new moodle_url('/mod/quiz/accessrule/honorlock/status_activate.php');
        echo $OUTPUT->single_button($url, get_string('activate', 'quizaccess_honorlock'));
    }
}

echo $OUTPUT->footer();
