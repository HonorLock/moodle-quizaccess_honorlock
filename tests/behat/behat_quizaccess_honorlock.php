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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\ElementNotFoundException;

require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');

/**
 * Honorlock Proctoring behat steps.
 *
 * @package    quizaccess_honorlock
 * @copyright  2024 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_quizaccess_honorlock extends behat_base {
    /**
     * Enable Honorlock in quiz magic.
     *
     * @Given I use behat magic to enable Honorlock Proctoring in quiz :quizname
     *
     * @param string $quizname
     */
    public function enable_in_quiz($quizname) {
        global $DB;

        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $record = $DB->get_record('quizaccess_honorlock', ['id' => $quiz->id]);
        if ($record) {
            $DB->set_field('quizaccess_honorlock', 'honorlockenable', 1, ['id' => $record->id]);
        } else {
            $DB->insert_record('quizaccess_honorlock', (object)[
                'quizid' => $quiz->id,
                'honorlockenable' => 1,
            ]);
        }
    }
}
