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
 * Honorlock Proctoring upgrade steps.
 *
 * @package    quizaccess_honorlock
 * @copyright  2026 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade Honorlock Proctoring plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_quizaccess_honorlock_upgrade($oldversion) {
    if ($oldversion < 2026103000) {
        // Moodle 5.3 rejects WS tokens of users whose auth plugin is disabled,
        // already active sites will not re-run activation, so enable webservice auth here.
        if (get_config('quizaccess_honorlock', 'active')) {
            \core\plugininfo\auth::enable_plugin('webservice', 1);
        }

        upgrade_plugin_savepoint(true, 2026103000, 'quizaccess', 'honorlock');
    }

    return true;
}
