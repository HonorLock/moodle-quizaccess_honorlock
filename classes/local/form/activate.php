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

namespace quizaccess_honorlock\local\form;

use quizaccess_honorlock\local\util;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Honorlock activation form.
 *
 * @package    quizaccess_honorlock
 * @copyright  2023 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activate extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $info = markdown_to_html(get_string('activateinfo', 'quizaccess_honorlock'));
        $mform->addElement('static', 'info', '', $info);

        $mform->addElement(
            'text',
            'clientid',
            get_string('honorlock_client_id', 'quizaccess_honorlock'),
            ['size' => 40]
        );
        $mform->setType('clientid', PARAM_RAW);
        $mform->addRule('clientid', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('clientid', 'honorlock_client_id', 'quizaccess_honorlock');

        $mform->addElement(
            'text',
            'clientsecret',
            get_string('honorlock_client_secret', 'quizaccess_honorlock'),
            ['size' => 80]
        );
        $mform->setType('clientsecret', PARAM_RAW);
        $mform->addRule('clientsecret', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('clientsecret', 'honorlock_client_secret', 'quizaccess_honorlock');

        $mform->addElement(
            'text',
            'honorlockurl',
            get_string('honorlock_url', 'quizaccess_honorlock'),
            ['size' => 40]
        );
        $mform->setType('honorlockurl', PARAM_URL);
        $mform->addRule('honorlockurl', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('honorlockurl', 'honorlock_url', 'quizaccess_honorlock');

        // Set form defaults.
        $clientid = get_config('quizaccess_honorlock', 'honorlock_client_id');
        if (!$clientid) {
            $clientid = get_config('local_honorlockproctoring', 'honorlock_client_id');
        }
        if ($clientid) {
            $mform->setDefault('clientid', $clientid);
        }

        $clientsecret = get_config('quizaccess_honorlock', 'honorlock_client_secret');
        if (!$clientsecret) {
            $clientsecret = get_config('local_honorlockproctoring', 'honorlock_client_secret');
        }
        if ($clientsecret) {
            $mform->setDefault('clientsecret', $clientsecret);
        }

        $honorlockurl = get_config('quizaccess_honorlock', 'honorlock_url');
        if (!$honorlockurl) {
            $honorlockurl = get_config('local_honorlockproctoring', 'honorlock_url');
        }
        if (!$honorlockurl) {
            $honorlockurl = util::HONORLOCK_URL;
        }
        $mform->setDefault('honorlockurl', $honorlockurl);

        $this->add_action_buttons(true, get_string('activate', 'quizaccess_honorlock'));
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $errors = array_merge(
            $errors,
            \quizaccess_honorlock\local\honorlockapi::validate_credentials(
                $data['clientid'],
                $data['clientsecret'],
                $data['honorlockurl']
            )
        );

        return $errors;
    }
}
