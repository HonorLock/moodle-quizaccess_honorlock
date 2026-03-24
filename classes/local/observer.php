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
 * Honorlock Proctoring observer.
 *
 * @package    quizaccess_honorlock
 * @copyright  2023 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Hook into quiz/view.php page.
     *
     * @param \mod_quiz\event\course_module_viewed $event
     * @return void
     */
    public static function course_module_viewed(\mod_quiz\event\course_module_viewed $event): void {
        global $PAGE;

        if (!util::is_honorlock_active()) {
            return;
        }

        // NOTE: unfortunately quiz pages url are a mess, so use deprecated workaround for now.
        // phpcs:disable
        global $FULLSCRIPT;
        if (!$FULLSCRIPT || strpos($FULLSCRIPT, '/mod/quiz/view.php') === false) {
            if (!PHPUNIT_TEST) {
                return;
            }
        }
        // phpcs:enable

        // Force Honorlock re-authentication if students visits the main quiz page.

        $cachedata = util::get_cache_data(util::ACTIVE_EXAM_CACHE_KEY);
        if (!CLI_SCRIPT && $cachedata !== null) {
            // Attempt to reset the Honorlock session in browser and extension - this is critical.
            $PAGE->requires->js_call_amd('quizaccess_honorlock/honorlockproctoring', 'quizViewReset');
        }

        util::clear_cache_data(util::ACTIVE_EXAM_CACHE_KEY);
    }

    /**
     * Submits a session completed API call after normal quiz submission.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $USER;

        if (!util::is_honorlock_active()) {
            return;
        }

        $record = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        if (!$record) {
            debugging('Missing quiz attempt', DEBUG_DEVELOPER);
            return;
        }
        if ($record->userid != $USER->id) {
            debugging('Invalid quiz attempt user', DEBUG_DEVELOPER);
            return;
        }

        if (!self::is_honorlock_enabled_quiz($record->quiz)) {
            return;
        }

        self::end_session($record->quiz, $record->attempt);
    }

    /**
     * Submits a session completed API call after quiz is abandoned.
     *
     * @param \mod_quiz\event\attempt_abandoned $event
     */
    public static function attempt_abandoned(\mod_quiz\event\attempt_abandoned $event): void {
        global $USER;

        if (!util::is_honorlock_active()) {
            return;
        }

        $record = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        if (!$record) {
            debugging('Missing quiz attempt', DEBUG_DEVELOPER);
            return;
        }
        if ($record->userid != $USER->id) {
            debugging('Invalid quiz attempt user', DEBUG_DEVELOPER);
            return;
        }

        if (!self::is_honorlock_enabled_quiz($record->quiz)) {
            return;
        }

        self::end_session($record->quiz, $record->attempt);
    }

    /**
     * Checks if the provided quiz $id is Honorlock Enabled.
     *
     * @param int $quizid
     * @return bool
     */
    private static function is_honorlock_enabled_quiz(int $quizid): bool {
        global $DB;

        return $DB->record_exists(
            'quizaccess_honorlock',
            ['quizid' => $quizid, 'honorlockenable' => 1]
        );
    }

    /**
     * End Honorlock exam session for current user.
     *
     * @param int $quizid
     * @param int $attempt
     */
    public static function end_session(int $quizid, int $attempt): void {
        global $USER;

        $cachedata = util::get_cache_data(util::ACTIVE_EXAM_CACHE_KEY);
        if ($cachedata === null) {
            return;
        }

        if ($cachedata['quizid'] != $quizid || $cachedata['attempt'] != $attempt) {
            return;
        }

        util::clear_cache_data(util::ACTIVE_EXAM_CACHE_KEY);

        if (util::is_behat() || util::is_phpunit()) {
            return;
        }

        // Session might have been already ended, so ignore any problems.
        $honorlock = new honorlock();
        $honorlock->end_session($USER->id, $quizid, $attempt);
    }
}
