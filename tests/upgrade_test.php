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

namespace quizaccess_honorlock;

/**
 * Honorlock upgrade steps tests.
 *
 * @package    quizaccess_honorlock
 * @copyright  2026 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Set up tests.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once("$CFG->dirroot/lib/upgradelib.php");
        require_once("$CFG->dirroot/mod/quiz/accessrule/honorlock/db/upgrade.php");
    }

    /**
     * Webservice auth is enabled on sites where Honorlock is active.
     * @covers ::xmldb_quizaccess_honorlock_upgrade
     */
    public function test_upgrade_enables_webservice_auth_when_active(): void {
        set_config('version', 2026032000, 'quizaccess_honorlock');
        set_config('active', 1, 'quizaccess_honorlock');
        \core\plugininfo\auth::enable_plugin('webservice', 0);
        $this->assertFalse(is_enabled_auth('webservice'));

        $this->assertTrue(xmldb_quizaccess_honorlock_upgrade(2026032000));
        $this->assertTrue(is_enabled_auth('webservice'));
    }

    /**
     * Webservice auth is left alone on sites where Honorlock is not active.
     * @covers ::xmldb_quizaccess_honorlock_upgrade
     */
    public function test_upgrade_skips_webservice_auth_when_inactive(): void {
        set_config('version', 2026032000, 'quizaccess_honorlock');
        \core\plugininfo\auth::enable_plugin('webservice', 0);

        $this->assertTrue(xmldb_quizaccess_honorlock_upgrade(2026032000));
        $this->assertFalse(is_enabled_auth('webservice'));
    }
}
