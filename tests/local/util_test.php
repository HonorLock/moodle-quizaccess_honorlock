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

namespace quizaccess_honorlock\local;

use mod_quiz\quiz_settings;
use mod_quiz\quiz_attempt;

/**
 * Honorlock utility class tests.
 *
 * To run test with real connection to Honorlock servers
 * you need to provide test client id and secret via config.php,
 * see https://app.honorlock.com/auth/login
 *
 *  define('TEST_QUIZACCESS_HONORLOCK_CLIENT_ID', 'paste-client-id-here');
 *  define('TEST_QUIZACCESS_HONORLOCK_CLIENT_SECRET', 'paste-client-secret-here');
 *
 * and optionally:
 *
 *   define('TEST_QUIZACCESS_HONORLOCK_URL', 'https://testapp.honorlock.com');
 *
 * @package    quizaccess_honorlock
 * @copyright  2024 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \quizaccess_honorlock\local\util
 */
final class util_test extends \advanced_testcase {
    /**
     * Set up tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test method.
     * @covers ::is_honorlock_active
     */
    public function test_is_honorlock_active(): void {

        $this->assertFalse(util::is_honorlock_active());

        set_config('active', 1, 'quizaccess_honorlock');
        set_config('honorlock_client_id', 'a-b-c', 'quizaccess_honorlock');
        set_config('honorlock_client_secret', 'd-e-f', 'quizaccess_honorlock');
        set_config('honorlock_url', 'https://example.com', 'quizaccess_honorlock');
        $this->assertTrue(util::is_honorlock_active());

        set_config('active', 0, 'quizaccess_honorlock');
        set_config('honorlock_client_id', 'a-b-c', 'quizaccess_honorlock');
        set_config('honorlock_client_secret', 'd-e-f', 'quizaccess_honorlock');
        set_config('honorlock_url', 'https://example.com', 'quizaccess_honorlock');
        $this->assertFalse(util::is_honorlock_active());

        set_config('active', 1, 'quizaccess_honorlock');
        set_config('honorlock_client_id', '', 'quizaccess_honorlock');
        set_config('honorlock_client_secret', 'd-e-f', 'quizaccess_honorlock');
        set_config('honorlock_url', 'https://example.com', 'quizaccess_honorlock');
        $this->assertFalse(util::is_honorlock_active());

        set_config('active', 1, 'quizaccess_honorlock');
        set_config('honorlock_client_id', 'a-b-c', 'quizaccess_honorlock');
        set_config('honorlock_client_secret', '', 'quizaccess_honorlock');
        set_config('honorlock_url', 'https://example.com', 'quizaccess_honorlock');
        $this->assertFalse(util::is_honorlock_active());

        set_config('active', 1, 'quizaccess_honorlock');
        set_config('honorlock_client_id', 'a-b-c', 'quizaccess_honorlock');
        set_config('honorlock_client_secret', 'd-e-f', 'quizaccess_honorlock');
        set_config('honorlock_url', '', 'quizaccess_honorlock');
        $this->assertFalse(util::is_honorlock_active());
    }

    /**
     * Test method.
     * @covers ::is_behat
     */
    public function test_is_behat(): void {
        $this->assertFalse(util::is_behat());
    }

    /**
     * Test method.
     * @covers ::is_phpunit
     */
    public function test_is_phpunit(): void {
        $this->assertTrue(util::is_phpunit());
    }

    /**
     * Test method.
     * @covers ::init_ws
     */
    public function test_init_ws(): void {
        global $DB, $CFG;
        require_once("$CFG->dirroot/webservice/lib.php");

        $this->assertFalse(get_config('quizaccess_honorlock', 'wsuserid'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'wsroleid'));

        $webservice = $DB->get_record('external_services', ['shortname' => 'quizaccess_honorlock'], '*', MUST_EXIST);
        $this->assertSame('0', $webservice->enabled);
        $this->assertSame('Honorlock Proctoring Web Services', $webservice->name);
        $this->assertSame('quizaccess/honorlock:ws', $webservice->requiredcapability);
        $this->assertSame('quizaccess_honorlock', $webservice->component);

        set_config('enablewebservices', '0');

        $result = util::init_ws();
        $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $result['ws_token']);
        $this->assertCount(1, $result);
        $this->assertSame('1', get_config('core', 'enablewebservices'));

        $wsuserid = get_config('quizaccess_honorlock', 'wsuserid');
        $wsuser = $DB->get_record('user', ['id' => $wsuserid], '*', MUST_EXIST);
        $this->assertSame('honorlock_api', $wsuser->username);
        $this->assertSame('Honorlock', $wsuser->firstname);
        $this->assertSame('API', $wsuser->lastname);
        $this->assertSame('honorlockapi@example.com', $wsuser->email);
        $this->assertSame('webservice', $wsuser->auth);
        $this->assertSame('1', $wsuser->confirmed);
        $this->assertFalse(user_not_fully_set_up($wsuser));

        $wsroleid = get_config('quizaccess_honorlock', 'wsroleid');
        $wsrole = $DB->get_record('role', ['id' => $wsroleid], '*', MUST_EXIST);
        $this->assertSame('honorlock_api_access', $wsrole->shortname);
        $this->assertSame('Honorlock API Access', $wsrole->name);

        $this->assertTrue(has_capability('quizaccess/honorlock:ws', \context_system::instance(), $wsuser));

        $webservice = $DB->get_record('external_services', ['shortname' => 'quizaccess_honorlock'], '*', MUST_EXIST);
        $this->assertSame('1', $webservice->enabled);

        $webservicelib = new \webservice();
        $authenticationinfo = $webservicelib->authenticate_user($result['ws_token']);
        $this->assertSame($wsuser->id, $authenticationinfo['user']->id);
        $this->assertSame($result['ws_token'], $authenticationinfo['token']->token);
        $this->assertSame($webservice->id, $authenticationinfo['service']->id);

        $result2 = util::init_ws();
        $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $result2['ws_token']);
        $this->assertCount(1, $result2);
        $this->assertNotSame($result['ws_token'], $result2['ws_token']);
        $this->assertSame('1', get_config('core', 'enablewebservices'));

        $wsuserid2 = get_config('quizaccess_honorlock', 'wsuserid');
        $this->assertSame($wsuserid, $wsuserid2);
        $wsuser2 = $DB->get_record('user', ['id' => $wsuserid2], '*', MUST_EXIST);

        $wsroleid2 = get_config('quizaccess_honorlock', 'wsroleid');
        $this->assertNotSame($wsroleid, $wsroleid2);
        $wsrole2 = $DB->get_record('role', ['id' => $wsroleid2], '*', MUST_EXIST);
        $this->assertFalse($DB->get_record('role', ['id' => $wsroleid]));
    }

    /**
     * Test method.
     * @covers ::disable_ws
     */
    public function test_disable_ws(): void {
        global $DB;

        util::init_ws();
        $wsuserid = get_config('quizaccess_honorlock', 'wsuserid');
        $wsroleid = get_config('quizaccess_honorlock', 'wsroleid');
        $wsrole = $DB->get_record('role', ['id' => $wsroleid], '*', MUST_EXIST);

        util::disable_ws();

        $webservice = $DB->get_record('external_services', ['shortname' => 'quizaccess_honorlock'], '*', MUST_EXIST);
        $this->assertSame('0', $webservice->enabled);

        $this->assertFalse(get_config('quizaccess_honorlock', 'wsuserid'));
        $wsuser = $DB->get_record('user', ['id' => $wsuserid], '*', MUST_EXIST);
        $this->assertSame('1', $wsuser->deleted);

        $this->assertFalse(get_config('quizaccess_honorlock', 'wsroleid'));
        $this->assertFalse($DB->record_exists('role', ['id' => $wsroleid]));

        $this->assertSame('1', get_config('core', 'enablewebservices'));

        util::disable_ws();
    }

    /**
     * Test method.
     * @covers ::init_lti
     */
    public function test_init_lti(): void {
        set_config('honorlock_client_id', 'aa-bb-cc', 'quizaccess_honorlock');
        set_config('honorlock_url', util::HONORLOCK_URL, 'quizaccess_honorlock');

        $result = util::init_lti();
        $this->assertNotEmpty($result['lti_version']);
        $this->assertSame('https://www.example.com/moodle', $result['lti_platformid']);
        $this->assertNotEmpty($result['lti_clientid']);
        $this->assertNotEmpty($result['lti_deploymentid']);
        $this->assertSame('https://www.example.com/moodle/mod/lti/certs.php', $result['lti_publickeyseturl']);
        $this->assertSame('https://www.example.com/moodle/mod/lti/token.php', $result['lti_accesstokenurl']);
        $this->assertSame('https://www.example.com/moodle/mod/lti/auth.php', $result['lti_authrequesturl']);

        $result2 = util::init_lti();
        $this->assertSame($result, $result2);
    }

    /**
     * Test method.
     * @covers ::get_lti_type
     */
    public function test_get_lti_type(): void {
        set_config('honorlock_client_id', 'aa-bb-cc', 'quizaccess_honorlock');
        set_config('honorlock_url', util::HONORLOCK_URL, 'quizaccess_honorlock');
        $result = util::init_lti();

        $type = util::get_lti_type();
        $this->assertSame($result['lti_deploymentid'], $type->id);
        $this->assertSame('Honorlock LTI', $type->name);
        $this->assertSame('https://app.honorlock.com/lms', $type->baseurl);
        $this->assertSame('app.honorlock.com', $type->tooldomain);
        $this->assertSame('1', $type->state);
        $this->assertSame(SITEID, $type->course);
        $this->assertSame('1', $type->coursevisible);
        $this->assertSame('1.3.0', $type->ltiversion);
        $this->assertSame($result['lti_clientid'], $type->clientid);
        $this->assertSame('1.3.0', $type->ltiversion);
        $this->assertSame('Honorlock LTI Tool 1.3', $type->description);

        set_config('honorlock_url', 'https://appxxxx.honorlock.com', 'quizaccess_honorlock');
        $this->assertNull(util::get_lti_type());
    }

    /**
     * Test method.
     * @covers ::disable_lti
     */
    public function test_disable_lti(): void {
        set_config('honorlock_client_id', 'aa-bb-cc', 'quizaccess_honorlock');
        set_config('honorlock_url', util::HONORLOCK_URL, 'quizaccess_honorlock');
        $result = util::init_lti();
        $type = util::get_lti_type();

        set_config('honorlock_client_id', '', 'quizaccess_honorlock');
        set_config('honorlock_url', '', 'quizaccess_honorlock');
        util::disable_lti($type);

        set_config('honorlock_client_id', 'aa-bb-cc', 'quizaccess_honorlock');
        set_config('honorlock_url', util::HONORLOCK_URL, 'quizaccess_honorlock');
        $type = util::get_lti_type();
        $this->assertSame($result['lti_deploymentid'], $type->id);
        $this->assertSame('Honorlock LTI', $type->name);
        $this->assertSame('https://app.honorlock.com/lms', $type->baseurl);
        $this->assertSame('app.honorlock.com', $type->tooldomain);
        $this->assertSame('1', $type->state);
        $this->assertSame(SITEID, $type->course);
        $this->assertSame('1', $type->coursevisible);
        $this->assertSame('1.3.0', $type->ltiversion);
        $this->assertSame($result['lti_clientid'], $type->clientid);
        $this->assertSame('1.3.0', $type->ltiversion);
        $this->assertSame('Honorlock LTI Tool 1.3 (not active)', $type->description);

        util::init_lti();
        $type = util::get_lti_type();
        $this->assertSame($result['lti_deploymentid'], $type->id);
        $this->assertSame('Honorlock LTI', $type->name);
        $this->assertSame('https://app.honorlock.com/lms', $type->baseurl);
        $this->assertSame('app.honorlock.com', $type->tooldomain);
        $this->assertSame('1', $type->state);
        $this->assertSame(SITEID, $type->course);
        $this->assertSame('1', $type->coursevisible);
        $this->assertSame('1.3.0', $type->ltiversion);
        $this->assertSame($result['lti_clientid'], $type->clientid);
        $this->assertSame('1.3.0', $type->ltiversion);
        $this->assertSame('Honorlock LTI Tool 1.3', $type->description);
    }

    /**
     * Test method.
     * @covers ::activate
     */
    public function test_activate(): void {
        $this->assertFalse(get_config('quizaccess_honorlock', 'active'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'honorlock_client_id'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'honorlock_client_secret'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'honorlock_url'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'wsuserid'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'wsroleid'));

        $clientid = 'aa-bb-cc';
        $clientsecret = 'djhdfsghjdfghjdfghjs';
        $honorlockurl = util::HONORLOCK_URL;
        $result = util::activate($clientid, $clientsecret, $honorlockurl);

        $type = util::get_lti_type();
        $this->assertSame(get_config('quizaccess_honorlock', 'version'), $result['honorlock_version']);
        $this->assertSame($clientid, $result['honorlock_clientid']);
        $this->assertNotEmpty($result['ws_token']);
        $this->assertNotEmpty($result['lti_version']);
        $this->assertSame('https://www.example.com/moodle', $result['lti_platformid']);
        $this->assertSame($type->clientid, $result['lti_clientid']);
        $this->assertSame($type->id, $result['lti_deploymentid']);
        $this->assertSame('https://www.example.com/moodle/mod/lti/certs.php', $result['lti_publickeyseturl']);
        $this->assertSame('https://www.example.com/moodle/mod/lti/token.php', $result['lti_accesstokenurl']);
        $this->assertSame('https://www.example.com/moodle/mod/lti/auth.php', $result['lti_authrequesturl']);
        $this->assertSame('1', get_config('quizaccess_honorlock', 'active'));
        $this->assertSame($clientid, get_config('quizaccess_honorlock', 'honorlock_client_id'));
        $this->assertSame($clientsecret, get_config('quizaccess_honorlock', 'honorlock_client_secret'));
        $this->assertSame($honorlockurl, get_config('quizaccess_honorlock', 'honorlock_url'));
        $this->assertNotEmpty(get_config('quizaccess_honorlock', 'wsuserid'));
        $this->assertNotEmpty(get_config('quizaccess_honorlock', 'wsroleid'));
    }

    /**
     * Test method.
     * @covers ::disable
     */
    public function test_disable(): void {
        $clientid = 'aa-bb-cc';
        $clientsecret = 'djhdfsghjdfghjdfghjs';
        $honorlockurl = util::HONORLOCK_URL;
        $result = util::activate($clientid, $clientsecret, $honorlockurl);

        util::disable();
        $this->assertSame('0', get_config('quizaccess_honorlock', 'active'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'honorlock_client_id'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'honorlock_client_secret'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'honorlock_url'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'wsuserid'));
        $this->assertFalse(get_config('quizaccess_honorlock', 'wsroleid'));
    }

    /**
     * Test method.
     * @covers ::get_status_data
     */
    public function test_get_status_data(): void {
        $result = util::get_status_data();
        $this->assertSame([['name' => 'Status', 'value' => 'Disabled']], $result);

        $clientid = 'aa-bb-cc';
        $clientsecret = 'djhdfsghjdfghjdfghjs';
        $honorlockurl = util::HONORLOCK_URL;
        $result = util::activate($clientid, $clientsecret, $honorlockurl);

        $result = util::get_status_data();
        $row = array_shift($result);
        $this->assertSame(['name' => 'Status', 'value' => 'Active'], $row);
        $row = array_shift($result);
        $this->assertSame(['name' => 'Honorlock URL', 'value' => $honorlockurl], $row);
        $row = array_shift($result);
        $this->assertSame(['name' => 'Honorlock Client ID', 'value' => $clientid], $row);
        $row = array_shift($result);
        $this->assertSame(['name' => 'Honorlock Client Secret', 'value' => '*****'], $row);
        $row = array_shift($result);
        $this->assertSame('Web service user', $row['name']);
        $row = array_shift($result);
        $this->assertSame(['name' => 'External tool', 'value' => 'Honorlock LTI'], $row);
        $this->assertSame([], $result);

        util::disable();
        $result = util::get_status_data();
        $this->assertSame([['name' => 'Status', 'value' => 'Disabled']], $result);
    }

    /**
     * Test method.
     * @covers ::guess_attempt
     */
    public function test_guess_attempt(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2, 'layout' => '1,0']);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz, 1);
        $quizobj = quiz_settings::create($quiz->id);

        $this->setUser($user);

        $this->assertSame(1, util::guess_attempt($user->id, $quizobj->get_quizid(), null));

        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 1, null);
        $this->assertSame(1, util::guess_attempt($user->id, $quizobj->get_quizid(), $attempt->id));

        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish(time(), false);
        $this->assertSame(2, util::guess_attempt($user->id, $quizobj->get_quizid(), null));
        $this->assertSame(1, util::guess_attempt($user->id, $quizobj->get_quizid(), $attempt->id));
    }
}
