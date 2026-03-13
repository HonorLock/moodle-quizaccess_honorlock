<?php
// This file is part of the honorlockproctoring plugin for Moodle - https://moodle.org/
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
 * Honorlock Proctoring language strings.
 *
 * @package    quizaccess_honorlock
 * @copyright  2023 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activate'] = 'Activate';
$string['activateinfo'] = 'To activate Honorlock Proctoring following changes will be done to site configuration:

* web services will be enabled
* REST protocol will be enabled
* new web service role with _quizaccess/honorlock:ws_, _webservice/rest:use_ capabilities will be created
* new web service account will be created
* new web service token will be created and displayed once
* new External Tool (LTI) type will be created
* you will need to copy the token and pass it to Honorlock';
$string['activateinstructions'] = '<strong>IMPORTANT:</strong>Copy the following text and pass it to Honorlock as it will only ever be shown here once.';
$string['apierror'] = 'Unknown error communicating with Honorlock server';
$string['cachedef_honorlock_api_token'] = 'Honorlock API Token Cache';
$string['connectiondiags'] = 'Connection tests';
$string['disable'] = 'Disable';
$string['disableinfo'] = '__Are you sure you want to disable Honorlock Proctoring?__';
$string['honorlock:ws'] = 'Use Honorlock web services';
$string['honorlock_client_id'] = 'Honorlock Client ID';
$string['honorlock_client_id_help'] = 'The Organization Client ID generated for your organization in app.honorlock.com';
$string['honorlock_client_secret'] = 'Honorlock Client Secret';
$string['honorlock_client_secret_help'] = 'The Organization Client Secret generated for your organization in app.honorlock.com';
$string['honorlock_url'] = 'Honorlock URL';
$string['honorlock_url_help'] = 'Alternative Honorlock API endpoint, intended for testing purposes only.';
$string['otherrequirements'] = 'Other quiz access requirements must be resolved before Honorlock Proctoring authentication.';
$string['pluginname'] = 'Honorlock Proctoring Service';
$string['privacy:metadata:quizaccess_honorlock'] = 'In order to integrate with the Honorlock Proctoring service, user data needs to be exchanged with that service.';
$string['privacy:metadata:quizaccess_honorlock:attempt_id'] = 'The \'attempt ID\' is sent to the remote system to aggregate the session data.';
$string['privacy:metadata:quizaccess_honorlock:email'] = 'The exam taker\'s \'email\' is sent to the remote system to allow for better user experience.';
$string['privacy:metadata:quizaccess_honorlock:first_name'] = 'The exam taker\'s \'first name\' is sent to the remote system to allow for a better user experience.';
$string['privacy:metadata:quizaccess_honorlock:last_name'] = 'The exam taker\'s \'last name\' is sent to the remote system to allow for a better user experience.';
$string['privacy:metadata:quizaccess_honorlock:quiz_id'] = 'The \'quiz ID\' is sent to the remote system to aggregate the session data.';
$string['privacy:metadata:quizaccess_honorlock:user_id'] = 'The exam taker\'s \'user ID\' is sent from Moodle for identification on the remote system.';
$string['ruledescription'] = 'Honorlock Proctoring is mandatory for this quiz.';
$string['ruledisabled'] = 'Honorlock Proctoring is not enabled';
$string['ruleenabled'] = 'Honorlock Proctoring is enabled';
$string['settings_header'] = 'Honorlock Proctoring configuration';
$string['status'] = 'Status';
$string['statusactive'] = 'Active';
$string['statusdisabled'] = 'Disabled';
$string['wstoken'] = 'Web service token';
$string['wsuser'] = 'Web service user';
