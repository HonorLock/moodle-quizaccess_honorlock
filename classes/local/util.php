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

/**
 * Honorlock Proctoring plugin configuration helper code.
 *
 * @package    quizaccess_honorlock
 * @copyright  2023 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class util {
    /** @var string default Honorlock URL for activation form */
    public const HONORLOCK_URL = 'https://app.honorlock.com';
    /** @var string WS role short name */
    public const WS_ROLE_SHORTNAME = 'honorlock_api_access';
    /** @var string WS account username */
    public const WS_USER_USERNAME = 'honorlock_api';
    /** @var string WS account email */
    public const WS_USER_EMAIL = 'honorlockapi@example.com';
    /** @var string Active exam cache key */
    public const ACTIVE_EXAM_CACHE_KEY = 'active_exam';

    /**
     * Is Honorlock Proctoring configured and active?
     *
     * @return bool
     */
    public static function is_honorlock_active(): bool {
        // Note: This has to be as fast as possible.
        if (!get_config('quizaccess_honorlock', 'active')) {
            return false;
        }
        if (!get_config('quizaccess_honorlock', 'honorlock_client_id')) {
            return false;
        }
        if (!get_config('quizaccess_honorlock', 'honorlock_client_secret')) {
            return false;
        }
        if (!get_config('quizaccess_honorlock', 'honorlock_url')) {
            return false;
        }
        // Note: do not test LTI configuration here, it is used for management only.
        return true;
    }

    /**
     * Is behat test in progress?
     *
     * @return bool
     */
    public static function is_behat(): bool {
        return (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING);
    }

    /**
     * Is PHPUnit test in progress.
     *
     * @return bool
     */
    public static function is_phpunit(): bool {
        return PHPUNIT_TEST;
    }

    /**
     * Initialise Moodle web services for Honorlock.
     *
     * @return array
     */
    public static function init_ws(): array {
        global $DB, $CFG;
        require_once("$CFG->dirroot/user/lib.php");
        require_once("$CFG->dirroot/webservice/lib.php");

        $syscontext = \context_system::instance();

        $wsuser = $DB->get_record('user', [
            'username' => self::WS_USER_USERNAME,
            'email' => self::WS_USER_EMAIL,
            'deleted' => 0,
            'auth' => 'webservice',
        ]);
        if ($wsuser) {
            role_unassign_all(['userid' => $wsuser->id]);
            $DB->delete_records('external_tokens', ['userid' => $wsuser->id]);
            $DB->delete_records('external_services_users', ['userid' => $wsuser->id]);
        } else {
            $wsuser = new \stdClass();
            $wsuser->username = self::WS_USER_USERNAME;
            $wsuser->firstname = 'Honorlock';
            $wsuser->lastname = 'API';
            $wsuser->email = self::WS_USER_EMAIL;
            $wsuser->auth = 'webservice';
            $wsuser->confirmed = 1;
            $wsuser->mnethostid = $CFG->mnet_localhost_id;
            $wsuserid = user_create_user($wsuser, false);
            $wsuser = $DB->get_record('user', ['id' => $wsuserid], '*', MUST_EXIST);
        }
        set_config('wsuserid', $wsuser->id, 'quizaccess_honorlock');

        $wsrole = $DB->get_record('role', ['shortname' => self::WS_ROLE_SHORTNAME]);
        if ($wsrole) {
            delete_role($wsrole->id);
        }
        $wsroleid = create_role(
            get_string('wsrolename', 'quizaccess_honorlock'),
            self::WS_ROLE_SHORTNAME,
            get_string('wsroledescription', 'quizaccess_honorlock')
        );
        set_config('wsroleid', $wsroleid, 'quizaccess_honorlock');
        $wsrole = $DB->get_record('role', ['id' => $wsroleid], '*', MUST_EXIST);

        assign_capability('quizaccess/honorlock:ws', CAP_ALLOW, $wsrole->id, $syscontext->id, true);
        assign_capability('webservice/rest:use', CAP_ALLOW, $wsrole->id, $syscontext->id, true);
        role_assign($wsrole->id, $wsuser->id, $syscontext->id, 'quizaccess_honorlock');

        $webservice = $DB->get_record('external_services', ['shortname' => 'quizaccess_honorlock'], '*', MUST_EXIST);
        if (!$webservice->enabled) {
            $webservice->enabled = '1';
            $DB->update_record('external_services', $webservice);
        }
        $webservicemanager = new \webservice();
        $serviceuser = new \stdClass();
        $serviceuser->externalserviceid = $webservice->id;
        $serviceuser->userid = $wsuser->id;
        $webservicemanager->add_ws_authorised_user($serviceuser);
        $params = [
            'objectid' => $serviceuser->externalserviceid,
            'relateduserid' => $serviceuser->userid,
        ];
        $event = \core\event\webservice_service_user_added::create($params);
        $event->trigger();

        \core\plugininfo\webservice::enable_plugin('rest', 1);

        set_config('enablewebservices', 1);

        $wstoken = \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $webservice, $wsuser->id, $syscontext);

        return ['ws_token' => $wstoken];
    }

    /**
     * Delete Honorlock WS user, role and services.
     * @return void
     */
    public static function disable_ws(): void {
        global $DB, $CFG;
        require_once("$CFG->dirroot/user/lib.php");
        require_once("$CFG->dirroot/webservice/lib.php");

        $wsuserid = get_config('quizaccess_honorlock', 'wsuserid');
        if ($wsuserid) {
            $wsuser = $DB->get_record('user', ['id' => $wsuserid, 'auth' => 'webservice', 'deleted' => 0]);
            if ($wsuser) {
                user_delete_user($wsuser);
            }
        }
        unset_config('wsuserid', 'quizaccess_honorlock');

        $wsroleid = get_config('quizaccess_honorlock', 'wsroleid');
        if ($wsroleid) {
            $wsrole = $DB->get_record('role', ['id' => $wsroleid]);
            if ($wsrole) {
                delete_role($wsrole->id);
            }
        }
        unset_config('wsroleid', 'quizaccess_honorlock');

        $webservice = $DB->get_record('external_services', ['shortname' => 'quizaccess_honorlock']);
        if ($webservice) {
            if ($webservice->enabled) {
                $webservice->enabled = '0';
                $DB->update_record('external_services', $webservice);
            }
        }
    }

    /**
     * Initialise Moodle LTI type for Honorlock.
     *
     * @return array
     */
    public static function init_lti(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/lti/lib.php');
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $clientid = get_config('quizaccess_honorlock', 'honorlock_client_id');
        if (!$clientid) {
            throw new \coding_exception('Missing honorlock_client_id');
        }
        $honorlockurl = get_config('quizaccess_honorlock', 'honorlock_url');
        if (!$honorlockurl) {
            throw new \coding_exception('Missing honorlock_url');
        }

        $type = self::get_lti_type();
        if ($type) {
            $ltidescription = get_string('ltitypedescription', 'quizaccess_honorlock');
            if ($type->description !== $ltidescription) {
                $type->description = $ltidescription;
                $DB->set_field('lti_types', 'description', $type->description, ['id' => $type->id]);
            }
        } else {
            $data = (object)[
                // Required parameters for Honorlock Proctoring.
                'lti_typename' => get_string('ltitypename', 'quizaccess_honorlock'),
                'lti_toolurl' => "$honorlockurl/lms",
                'lti_description' => get_string('ltitypedescription', 'quizaccess_honorlock'),
                'lti_ltiversion' => LTI_VERSION_1P3,
                'lti_keytype' => LTI_JWK_KEYSET,
                'lti_publickeyset' => '',
                'lti_initiatelogin' => "$honorlockurl/lti13/login",
                'lti_redirectionuris' => "$honorlockurl/lms",
                'lti_coursevisible' => LTI_COURSEVISIBLE_PRECONFIGURED,
                'lti_launchcontainer' => LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                'lti_sendname' => '1',
                'lti_sendemailaddr' => '1',

                // Creation of new type.
                'id' => 0,
                'typeid' => 0,

                // Other default settings from UI.
                'lti_customparameters' => '',
                'lti_contentitem' => '0',
                'ltiservice_gradesynchronization' => '0',
                'ltiservice_memberships' => '0',
                'ltiservice_toolsettings' => '0',
                'lti_acceptgrades' => '2',
                'lti_organizationid_default' => 'SITEID',
                'lti_organizationid' => '',
                'lti_organizationurl' => '',
            ];

            $type = new \stdClass();
            $type->state = LTI_TOOL_STATE_CONFIGURED;
            lti_load_type_if_cartridge($data);
            $typeid = lti_add_type($type, $data);
            $type = $DB->get_record('lti_types', ['id' => $typeid], '*', MUST_EXIST);
        }

        $return = [
            'lti_version' => get_config('mod_lti', 'version'),
        ];
        foreach (get_tool_type_config($type) as $k => $v) {
            $return['lti_' . $k] = $v;
        }

        return $return;
    }

    /**
     * Find Honorlock LTI type record.
     *
     * @return \stdClass|null
     */
    public static function get_lti_type(): ?\stdClass {
        global $DB;

        $clientid = get_config('quizaccess_honorlock', 'honorlock_client_id');
        if (!$clientid) {
            return null;
        }
        $url = get_config('quizaccess_honorlock', 'honorlock_url');
        if (!$url) {
            return null;
        }

        $baseurl = "$url/lms";

        $select = $DB->sql_compare_text('baseurl', strlen($baseurl) + 1) . '=?';
        $type = $DB->get_record_select('lti_types', $select, [$baseurl]);

        if ($type) {
            return $type;
        } else {
            return null;
        }
    }

    /**
     * Disable LTI type.
     *
     * @param \stdClass $type
     * @return void
     */
    public static function disable_lti(\stdClass $type): void {
        global $DB;

        // Do not delete LTI type because it may be referenced in course activities.

        if ($type) {
            $DB->set_field(
                'lti_types',
                'description',
                get_string('ltitypedescriptioninactive', 'quizaccess_honorlock'),
                ['id' => $type->id]
            );
        }
    }

    /**
     * Activate Honorlock Proctoring.
     *
     * @param string $clientid
     * @param string $clientsecret
     * @param string $honorlockurl
     * @return array information to be passed back to Honor
     */
    public static function activate(string $clientid, string $clientsecret, string $honorlockurl): array {
        set_config('active', 1, 'quizaccess_honorlock');
        set_config('honorlock_client_id', $clientid, 'quizaccess_honorlock');
        set_config('honorlock_client_secret', $clientsecret, 'quizaccess_honorlock');
        set_config('honorlock_url', $honorlockurl, 'quizaccess_honorlock');

        $result = [
            'honorlock_version' => get_config('quizaccess_honorlock', 'version'),
            'honorlock_clientid' => $clientid,
            'honorlock_url' => $honorlockurl,
        ];
        $result = array_merge($result, self::init_ws());
        $result = array_merge($result, self::init_lti());

        // Disable pre-existing plugin.
        unset_config('honorlock_client_id', 'local_honorlockproctoring');
        unset_config('honorlock_client_secret', 'local_honorlockproctoring');
        unset_config('honorlock_url', 'local_honorlockproctoring');

        honorlockapi::reset_caches();

        return $result;
    }

    /**
     * Deactivate Honorlock Proctoring.
     */
    public static function disable(): void {
        global $DB;

        $type = self::get_lti_type();

        set_config('active', 0, 'quizaccess_honorlock');
        unset_config('honorlock_client_id', 'quizaccess_honorlock');
        unset_config('honorlock_client_secret', 'quizaccess_honorlock');
        unset_config('honorlock_url', 'quizaccess_honorlock');

        self::disable_ws();
        if ($type) {
            self::disable_lti($type);
        }

        $DB->delete_records('quizaccess_honorlock', []);

        honorlockapi::reset_caches();
    }

    /**
     * Returns integration status information.
     *
     * @return array
     */
    public static function get_status_data(): array {
        global $DB;

        $result = [];

        $row = ['name' => get_string('status', 'quizaccess_honorlock')];
        $active = self::is_honorlock_active();
        if ($active) {
            $row['value'] = get_string('statusactive', 'quizaccess_honorlock');
        } else {
            $row['value'] = get_string('statusdisabled', 'quizaccess_honorlock');
        }
        $result[] = $row;

        if (!$active) {
            return $result;
        }

        $row = ['name' => get_string('honorlock_url', 'quizaccess_honorlock')];
        $honorlockurl = get_config('quizaccess_honorlock', 'honorlock_url');
        if ($honorlockurl) {
            $row['value'] = s($honorlockurl);
        } else {
            $row['value'] = '';
        }
        $result[] = $row;

        $row = ['name' => get_string('honorlock_client_id', 'quizaccess_honorlock')];
        $clientid = get_config('quizaccess_honorlock', 'honorlock_client_id');
        if ($clientid) {
            $row['value'] = s($clientid);
        } else {
            $row['value'] = '';
        }
        $result[] = $row;

        $row = ['name' => get_string('honorlock_client_secret', 'quizaccess_honorlock')];
        $clientsecret = get_config('quizaccess_honorlock', 'honorlock_client_secret');
        if ($clientsecret) {
            $row['value'] = '*****';
        } else {
            $row['value'] = '';
        }
        $result[] = $row;

        $row = ['name' => get_string('wsuser', 'quizaccess_honorlock')];
        $wsuserid = get_config('quizaccess_honorlock', 'wsuserid');
        if ($wsuserid) {
            $wsuser = $DB->get_record('user', ['id' => $wsuserid, 'deleted' => 0]);
            if ($wsuser) {
                $username = s($wsuser->username);
                if (has_capability('moodle/user:viewdetails', \context_user::instance($wsuser->id))) {
                    $url = new \moodle_url('/user/profile.php', ['id' => $wsuser->id]);
                    $username = \html_writer::link($url, $username);
                }
                $row['value'] = $username;
            } else {
                $row['value'] = '<span class="badge badge-danger">'
                    . get_string('error') . '</span>';
            }
        } else {
            $row['value'] = '';
        }
        $result[] = $row;

        $row = ['name' => get_string('pluginname', 'mod_lti')];
        $ltitype = self::get_lti_type();
        if ($ltitype) {
            $extname = s($ltitype->name);
            if (has_capability('moodle/site:config', \context_system::instance())) {
                $url = new \moodle_url('/mod/lti/toolconfigure.php');
                $extname = \html_writer::link($url, $extname);
            }
            $row['value'] = $extname;
        } else {
            $row['value'] = '<span class="badge badge-danger">'
                . get_string('error') . '</span>';
        }
        $result[] = $row;

        if ($active && !self::is_behat() && !self::is_phpunit()) {
            $row = ['name' => get_string('connectiondiags', 'quizaccess_honorlock')];
            $honorlock = new honorlock();
            $problems = $honorlock->diagnose_connection();
            if ($problems) {
                $row['value'] = '<span class="badge badge-danger">'
                    . implode('<br/>', $problems) . '</span>';
            } else {
                $row['value'] = '<span class="badge badge-success">'
                    . get_string('ok') . '</span>';
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Guess next attempt number.
     *
     * @param int $userid
     * @param int $quizid
     * @param int|null $attemptid the id of the current attempt, if there is one, otherwise null.
     * @return int
     */
    public static function guess_attempt(int $userid, int $quizid, ?int $attemptid): int {
        global $DB;

        if ($attemptid) {
            $attempt = $DB->get_record(
                'quiz_attempts',
                ['id' => $attemptid, 'userid' => $userid, 'quiz' => $quizid],
                'id, attempt'
            );
            if ($attempt) {
                return $attempt->attempt;
            } else {
                debugging("Unknown attempt: $userid,$quizid,$attemptid", DEBUG_DEVELOPER);
            }
        }

        return 1 + (int)$DB->get_field(
            'quiz_attempts',
            "MAX(attempt)",
            ['quiz' => $quizid, 'userid' => $userid]
        );
    }

    /**
     * Get a value from the Honorlock session cache.
     *
     * @param string $key The cache key to retrieve.
     * @return mixed The cached value, or null if not found.
     */
    public static function get_cache_data(string $key): mixed {
        $cache = \cache::make('quizaccess_honorlock', 'honorlock_session');
        $data = $cache->get($key);
        return ($data === false) ? null : $data;
    }

    /**
     * Store a value in the Honorlock session cache.
     *
     * @param string $key The cache key.
     * @param mixed $value The value to store.
     */
    public static function set_cache_data(string $key, mixed $value): void {
        $cache = \cache::make('quizaccess_honorlock', 'honorlock_session');
        $cache->set($key, $value);
    }

    /**
     * Clear a value from the Honorlock session cache.
     *
     * @param string $key The cache key to delete.
     */
    public static function clear_cache_data(string $key): void {
        $cache = \cache::make('quizaccess_honorlock', 'honorlock_session');
        $cache->delete($key);
    }
}
