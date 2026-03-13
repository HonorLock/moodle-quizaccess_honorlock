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
 * Honorlock Proctoring activation page.
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

$pageurl = new moodle_url('/mod/quiz/accessrule/honorlock/status_activate.php');
admin_externalpage_setup('quizaccess_honorlock_status', '', null, $pageurl, ['nosearch' => true]);

$returnurl = new moodle_url('/mod/quiz/accessrule/honorlock/status.php');

if (
    get_config('quizaccess_honorlock', 'active')
    || isset($CFG->forced_plugin_settings['quizaccess_honorlock']['active'])
) {
    redirect($returnurl);
}

$pagetitlestr = get_string('pluginname', 'quizaccess_honorlock');

$PAGE->set_secondary_navigation(false);
$PAGE->set_title($pagetitlestr);
$PAGE->set_heading($pagetitlestr);

$form = new \quizaccess_honorlock\local\form\activate();

if ($form->is_cancelled()) {
    redirect($returnurl);
}

echo $OUTPUT->header();

if ($data = $form->get_data()) {
    $activation = util::activate($data->clientid, $data->clientsecret, $data->honorlockurl);
    $info = [];
    foreach ($activation as $k => $v) {
        $info[] = $k . ': ' . $v;
    }
    $info = implode("\n", $info);
    echo $OUTPUT->notification(
        get_string('activateinstructions', 'quizaccess_honorlock'),
        \core\output\notification::NOTIFY_ERROR,
        false
    );
    echo $OUTPUT->box('<pre>' . $info . '</pre>', 'generalbox alert alert-info');

    echo $OUTPUT->single_button($returnurl, get_string('continue'), 'get');
} else {
    $form->display();
}

echo $OUTPUT->footer();
