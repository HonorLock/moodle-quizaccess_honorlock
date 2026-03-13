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
 * Honorlock Proctoring API.
 *
 * @package    quizaccess_honorlock
 * @copyright  2023 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class honorlock {
    /**
     * The Honorlock API class instance.
     *
     * @var honorlockapi
     */
    private $honorlockapi;

    /**
     * The class constructor.
     */
    public function __construct() {
        if (!util::is_honorlock_active()) {
            throw new \coding_exception('Honorlock is not active!');
        }

        $clientid = get_config('quizaccess_honorlock', 'honorlock_client_id');
        $clientsecret = get_config('quizaccess_honorlock', 'honorlock_client_secret');
        $honorlockurl = get_config('quizaccess_honorlock', 'honorlock_url');

        $this->honorlockapi = new honorlockapi($clientid, $clientsecret, $honorlockurl);
    }

    /**
     * Perform Honorlock communication diagnostics.
     *
     * @return array of errors, empty means all ok
     */
    public function diagnose_connection(): array {
        $api = "/api/en/v1/me";

        $result = $this->honorlockapi->send_request('get', $api, [], $responsecode);
        if ($responsecode != 200 || empty($result->data->uuid)) {
            return [get_string('error')];
        }

        // NOTE: add some round-trip test that confirms Honorlock can use Moodle WS.

        return [];
    }

    /**
     * Extension check.
     *
     * @return object|null
     */
    public function extension_check(): ?object {
        $locale = self::get_locale();
        $api = "/api/{$locale}/v1/extension/check";
        $responsecode = null;

        $result = $this->honorlockapi->send_request('get', $api, [], $responsecode);

        if (!isset($result->data->iframe_src)) {
            self::error_log('extension_check', $result, $responsecode);
            return null;
        }
        return $result->data;
    }

    /**
     * Create a new session or reopen existing session.
     *
     * @param array $sessiondetails
     * @return object|null
     */
    public function create_session(array $sessiondetails): ?object {
        $locale = self::get_locale();
        $api = "/api/{$locale}/v1/exams/sessions/create";
        $responsecode = null;

        $result = $this->honorlockapi->send_request('post', $api, $sessiondetails, $responsecode);

        if (($responsecode != 200 && $responsecode != 201) || !isset($result->data->session)) {
            self::error_log('create_session', $result, $responsecode);
            return null;
        }
        return $result->data;
    }

    /**
     * Get exam instructions.
     *
     * @param int $examid
     * @return object|null
     */
    public function get_exam_instructions(int $examid): ?object {
        $locale = self::get_locale();
        $api = "/api/{$locale}/v1/exams/{$examid}/instructions";
        $responsecode = null;

        $result = $this->honorlockapi->send_request('get', $api, [], $responsecode);

        if (!isset($result->data->launch_screen_url)) {
            self::error_log('get_exam_instructions', $result, $responsecode);
            return null;
        }

        return $result->data;
    }

    /**
     * Verify session.
     *
     * @param int $userid
     * @param int $examid
     * @param int $attempt
     * @return bool
     */
    public function verify_session(int $userid, int $examid, int $attempt): bool {
        $api = "/api/en/v1/exams/{$examid}/sessions/{$userid}/{$attempt}/verify";
        $responsecode = null;

        $result = $this->honorlockapi->send_request('get', $api, [], $responsecode);
        if ($responsecode != 200 || !isset($result->data->authenticated)) {
            self::error_log('verify_session', $result, $responsecode);
            return false;
        }

        return $result->data->authenticated;
    }

    /**
     * Begin session.
     *
     * @param int $userid
     * @param int $examid
     * @param int $attempt
     * @return bool
     */
    public function begin_session(int $userid, int $examid, int $attempt): bool {
        $api = "/api/en/v1/session/start";
        $payload = [
            'external_exam_id' => $examid,
            'exam_taker_id' => $userid,
            'exam_taker_attempt_id' => $attempt,
            'continue' => false,
        ];
        $responsecode = null;

        $result = $this->honorlockapi->send_request('post', $api, $payload, $responsecode);
        // Note this returns 201 only, but we need 200 because Moodle curl mocking is not great.
        if (($responsecode != 201 && $responsecode != 200) || !isset($result->data->event_type)) {
            if ($responsecode != 409) { // Do not log, we will continue right after this.
                self::error_log('begin_session', $result, $responsecode);
            }
            return false;
        }
        return true;
    }

    /**
     * Continue started session.
     *
     * @param int $userid
     * @param int $examid
     * @param int $attempt
     * @return bool
     */
    public function continue_session(int $userid, int $examid, int $attempt): bool {
        $api = "/api/en/v1/session/start";
        $payload = [
            'external_exam_id' => $examid,
            'exam_taker_id' => $userid,
            'exam_taker_attempt_id' => $attempt,
            'continue' => true,
        ];
        $responsecode = null;

        $result = $this->honorlockapi->send_request('post', $api, $payload, $responsecode);
        // Note this returns 201 only, but we need 200 because Moodle curl mocking is not great.
        if (($responsecode != 201 && $responsecode != 200) || !isset($result->data->event_type)) {
            self::error_log('continue_session', $result, $responsecode);
            return false;
        }
        return true;
    }

    /**
     * End session.
     *
     * @param int $userid
     * @param int $examid
     * @param int $attempt
     * @return bool
     */
    public function end_session(int $userid, int $examid, int $attempt): bool {
        $api = "/api/en/v1/session/complete";
        $payload = [
            'external_exam_id' => $examid,
            'exam_taker_id' => $userid,
            'exam_taker_attempt_id' => $attempt,
        ];
        $responsecode = null;

        $result = $this->honorlockapi->send_request('post', $api, $payload, $responsecode);
        // Note this returns 201 only, but we need 200 because Moodle curl mocking is not great.
        if (($responsecode != 201 && $responsecode != 200) || !isset($result->data->event_type)) {
            if ($responsecode != 409) { // This might be called after the session was already ended by other means.
                self::error_log('end_session', $result, $responsecode);
            }
            return false;
        }
        return true;
    }

    /**
     * Return Honorlock API locale parameter value.
     *
     * @return string
     */
    public static function get_locale(): string {
        $map = [
            'de' => 'de',
            'en' => 'en',
            'es' => 'es',
            'fr' => 'fr',
            'it' => 'it',
            'ja' => 'ja',
            'pt_br' => 'pt-BR',
            'pt' => 'pt-PT',
            'zh_tw' => 'zh-TW',
        ];

        $lang = current_language();
        while ($lang !== '') {
            if (isset($map[$lang])) {
                return $map[$lang];
            }

            $lang = get_parent_language($lang);
        }

        return 'en';
    }

    /**
     * Log errors if debugging enabled.
     *
     * @param string $method
     * @param object|null $result
     * @param int|null $responsecode
     * @return void
     */
    public static function error_log(string $method, ?object $result, ?int $responsecode): void {
        global $CFG;

        if (PHPUNIT_TEST) {
            return;
        }

        if (!$CFG->debugdeveloper) {
            return;
        }

        // phpcs:disable
        error_log('Honorlock API error - method: ' . $method . ', response code: ' . $responsecode
            . ', request: ' . var_export($result, true));
        // phpcs:enable
    }
}
