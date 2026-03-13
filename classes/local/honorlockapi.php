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

use stdClass;

/**
 * Honorlock Proctoring API helper.
 *
 * @package    quizaccess_honorlock
 * @copyright  2023 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class honorlockapi {
    /**
     * const string The component name.
     */
    public const COMPONENT_NAME = 'quizaccess_honorlock';

    /**
     * const string The API token cache key name.
     */
    public const HONORLOCK_API_TOKEN_CACHE_KEY = 'honorlock_api_token';

    /** @var string */
    private $clientid;
    /** @var string*/
    private $clientsecret;
    /** @var string*/
    private $honorlockurl;

    /**
     * Test that Honorlock client id and secret are valid
     * before the integration is activated.
     *
     * @param string $clientid
     * @param string $clientsecret
     * @param string $honorlockurl
     * @return array errors with 'clientid' and 'clientsecret' keys, empty array means all ok
     */
    public static function validate_credentials(string $clientid, string $clientsecret, string $honorlockurl): array {
        global $CFG;
        require_once("$CFG->libdir/filelib.php");

        $errors = [];
        if (trim($clientid) === '') {
            $errors['clientid'] = get_string('required');
        } else if (!preg_match('/^[a-z0-9-]+$/', $clientid)) {
            $errors['clientid'] = get_string('error');
        }
        if (trim($clientsecret) === '') {
            $errors['clientsecret'] = get_string('required');
        } else if (!preg_match('/^[a-zA-Z0-9]+$/', $clientsecret)) {
            $errors['clientsecret'] = get_string('error');
        }
        if (trim($honorlockurl) === '') {
            $errors['honorlockurl'] = get_string('required');
        } else if ($honorlockurl !== clean_param($honorlockurl, PARAM_URL)) {
            $errors['honorlockurl'] = get_string('error');
        }

        if ($errors) {
            return $errors;
        }

        if (util::is_behat()) {
            if ($clientid !== 'aaa-bbb-ccc') {
                return ['clientsecret' => get_string('error')];
            }
            return [];
        }

        $curl = new \curl();
        $jsonresult = $curl->post(
            "$honorlockurl/api/en/v1/token",
            ['client_id' => $clientid, 'client_secret' => $clientsecret]
        );
        $errno = $curl->get_errno();
        if ($errno) {
            return ['clientsecret' => get_string('error')];
        }
        $result = json_decode($jsonresult);
        if (empty($result->data->access_token)) {
            return ['clientsecret' => get_string('error')];
        }

        return [];
    }

    /**
     * Constructor.
     *
     * @param string $clientid
     * @param string $clientsecret
     * @param string $honorlockurl
     */
    public function __construct(string $clientid, string $clientsecret, string $honorlockurl) {
        global $CFG;
        require_once("$CFG->libdir/filelib.php");
        $this->clientid = $clientid;
        $this->clientsecret = $clientsecret;
        $this->honorlockurl = $honorlockurl;
    }

    /**
     * Reset Honorlock API caches.
     *
     * @return void
     */
    public static function reset_caches(): void {
        $honorlockapicache = \cache::make(self::COMPONENT_NAME, self::HONORLOCK_API_TOKEN_CACHE_KEY);
        $honorlockapicache->delete(self::HONORLOCK_API_TOKEN_CACHE_KEY);
    }

    /**
     * Generates a new Honorlock API token.
     *
     * @return object|null
     */
    private function generate_token(): ?object {
        $curl = new \curl();

        $jsonresult = $curl->post(
            "{$this->honorlockurl}/api/en/v1/token",
            [
                'client_id' => $this->clientid,
                'client_secret' => $this->clientsecret,
            ]
        );

        $result = json_decode($jsonresult);

        if (!isset($result->data)) {
            debugging('Cannot generate Honorlock API token', DEBUG_DEVELOPER);
            return null;
        }

        return $result->data;
    }

    /**
     * Get the Honorlock API Token in order to authenticate the API requests.
     *
     * @return string
     */
    private function get_token(): string {
        $honorlockapicache = \cache::make(self::COMPONENT_NAME, self::HONORLOCK_API_TOKEN_CACHE_KEY);
        $cachedtoken = $honorlockapicache->get(self::HONORLOCK_API_TOKEN_CACHE_KEY);

        // If a cache with the given key already exists.
        if ($cachedtoken && $cachedtoken['expiration_time'] > time()) {
            return $cachedtoken['token'];
        }

        $token = $this->generate_token();

        if (!$token) {
            return '';
        }

        $expirationtime = time() + $token->expires_in;
        $cachedata = [
            'token' => $token->access_token,
            'expiration_time' => $expirationtime,
        ];

        $honorlockapicache->set(self::HONORLOCK_API_TOKEN_CACHE_KEY, $cachedata);

        return $token->access_token;
    }

    /**
     * Send request.
     *
     * @param string $type
     * @param string $endpoint
     * @param array $payload
     * @param int|null $responsecode
     * @return object|null
     */
    public function send_request(string $type, string $endpoint, array $payload = [], ?int &$responsecode = null): ?object {
        $token = $this->get_token();
        $curl = new \curl();
        $curl->setHeader(
            [
                'Accept: application/json',
                "Authorization: Bearer $token",
            ]
        );
        $url = $this->honorlockurl;

        if ($type === 'get') {
            $jsonresult = $curl->get($url . $endpoint);
        } else if ($type === 'post') {
            $jsonresult = $curl->post($url . $endpoint, $payload);
        } else {
            throw new \coding_exception('Unknown request type');
        }

        $responsecode = null;
        if (isset($curl->info['http_code'])) {
            if (is_number($curl->info['http_code'])) {
                $responsecode = (int)$curl->info['http_code'];
            }
        }

        if (!$jsonresult) {
            return null;
        }

        $result = json_decode($jsonresult);
        $result = fix_utf8($result);
        return $result;
    }
}
