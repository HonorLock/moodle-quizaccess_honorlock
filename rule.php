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

use quizaccess_honorlock\local\util;
use quizaccess_honorlock\local\honorlock;
use mod_quiz\quiz_settings;

/**
 * Honorlock Proctoring quiz access rule.
 *
 * @package    quizaccess_honorlock
 * @copyright  2024 Honorlock (https://honorlock.com/)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_honorlock extends \mod_quiz\local\access_rule_base {
    /** @var bool indicates if rule order was hacked in access manager */
    private $accessmanagerhacked = false;

    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     * to the given quiz, otherwise return null.
     * @param quiz_settings $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from
     *      time limits by the mod/quiz:ignoretimelimits capability.
     * @return quizaccess_honorlock|null the rule, if applicable, else null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        if (!util::is_honorlock_active()) {
            return null;
        }

        if ($quizobj->get_quiz()->honorlockenable === null) {
            // Fetch setting from Honorlock server if possible.
            $honorlockenable = self::sync_honorlockenable($quizobj->get_quizid());
            if (!$honorlockenable) {
                return null;
            }
        } else if (!$quizobj->get_quiz()->honorlockenable) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    /**
     * Synchronise quiz setting record in quizaccess_honorlock table
     * with honorlock server.
     *
     * @param int $quizid
     * @return bool
     */
    public static function sync_honorlockenable(int $quizid): bool {
        global $DB;

        if (!util::is_honorlock_active()) {
            return false;
        }

        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        if (!$quiz) {
            $DB->delete_records('quizaccess_honorlock', ['quizid' => $quizid]);
            return false;
        }
        $current = $DB->get_record('quizaccess_honorlock', ['quizid' => $quizid]);

        if (util::is_behat()) {
            if ($current) {
                return (bool)$current->honorlockenable;
            } else {
                return false;
            }
        }

        // NOTE: in the future ask Honorlock server if exam proctoring is enabled.

        if ($current) {
            return (bool)$current->honorlockenable;
        } else {
            $record = (object)[
                'quizid' => $quizid,
                'honorlockenable' => '0',
            ];
            $DB->insert_record('quizaccess_honorlock', $record);
            return false;
        }
    }

    /**
     * Information, such as might be shown on the quiz view page, relating to this restriction.
     * There is no obligation to return anything. If it is not appropriate to tell students
     * about this rule, then just return ''.
     * @return mixed a message, or array of messages, explaining the restriction
     *         (may be '' if no message is appropriate).
     */
    public function description() {
        return get_string('ruledescription', 'quizaccess_honorlock');
    }

    /**
     * Sets up the attempt (review or summary) page with any special extra
     * properties required by this rule. securewindow rule is an example of where
     * this is used.
     *
     * @param moodle_page $page the page object to initialise.
     */
    public function setup_attempt_page($page) {
        global $USER, $SESSION;

        $page->set_pagelayout('secure');
        $page->set_popup_notification_allowed(false);

        if ($this->quizobj->is_preview_user()) {
            // This must be a preview.
            return;
        }

        if (empty($SESSION->quizaccess_honorlock_exam) || empty($SESSION->quizaccess_honorlock_attempt)) {
            return;
        }
        if ($SESSION->quizaccess_honorlock_exam != $this->quizobj->get_quizid()) {
            return;
        }

        $quizid = $SESSION->quizaccess_honorlock_exam;
        $attempt = $SESSION->quizaccess_honorlock_attempt;

        if (!util::is_behat()) {
            // Get Honorlock instance.
            $honorlock = new honorlock();
            if (!$honorlock->continue_session($USER->id, $quizid, $attempt)) {
                // Session is not started, they will have to authenticate again.
                unset($SESSION->quizaccess_honorlock_exam);
                unset($SESSION->quizaccess_honorlock_attempt);
                return;
            }
        }

        $pageurl = $page->url->get_path();
        if (strpos($pageurl, "/mod/quiz/attempt.php") !== false) {
            $page->requires->js_call_amd('quizaccess_honorlock/honorlockproctoring', 'takeQuiz', [$quizid]);
        } else if (strpos($pageurl, "/mod/quiz/summary.php") !== false) {
            $page->requires->js_call_amd('quizaccess_honorlock/honorlockproctoring', 'quizSummary');
        }
    }

    /**
     * Add any fields that this rule requires to the quiz settings form. This
     * method is called from mod_quiz_mod_form::definition(), while the
     * security seciton is being built.
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        if (!util::is_honorlock_active()) {
            return;
        }

        $honorlockenable = false;
        $context = $quizform->get_context();
        if ($context instanceof context_module) {
            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if ($cm) {
                $honorlockenable = self::sync_honorlockenable($cm->instance);
            }
        }

        if ($honorlockenable) {
            $mform->addElement('static', 'honorlockenable', get_string('ruleenabled', 'quizaccess_honorlock'));
        } else {
            $mform->addElement('static', 'honorlockenable', get_string('ruledisabled', 'quizaccess_honorlock'));
        }
    }

    /**
     * Save any submitted settings when the quiz settings form is submitted. This
     * is called from quiz_after_add_or_update() in lib.php.
     * @param object $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     */
    public static function save_settings($quiz) {
        // Nothing to do here for now.
    }

    /**
     * Delete any rule-specific settings when the quiz is deleted. This is called
     * from quiz_delete_instance() in lib.php.
     * @param object $quiz the data from the database, including $quiz->id
     *      which is the id of the quiz being deleted.
     */
    public static function delete_settings($quiz) {
        global $DB;

        $DB->delete_records('quizaccess_honorlock', ['quizid' => $quiz->id]);
    }

    /**
     * Return the bits of SQL needed to load all the settings from all the access
     * plugins in one DB query. The easiest way to understand what you need to do
     * here is probalby to read the code of quiz_access_manager::load_settings().
     *
     * If you have some settings that cannot be loaded in this way, then you can
     * use the get_extra_settings() method instead, but that has
     * performance implications.
     *
     * @param int $quizid the id of the quiz we are loading settings for. This
     *     can also be accessed as quiz.id in the SQL. (quiz is a table alisas for {quiz}.)
     * @return array with three elements:
     *     1. fields: any fields to add to the select list. These should be alised
     *        if neccessary so that the field name starts the name of the plugin.
     *     2. joins: any joins (should probably be LEFT JOINS) with other tables that
     *        are needed.
     *     3. params: array of placeholder values that are needed by the SQL. You must
     *        used named placeholders, and the placeholder names should start with the
     *        plugin name, to avoid collisions.
     */
    public static function get_settings_sql($quizid) {
        return ['honorlock.honorlockenable', ' LEFT JOIN {quizaccess_honorlock} honorlock ON honorlock.quizid = quiz.id ', []];
    }

    /**
     * Is the quiz preflight form required?
     *
     * @param int|null $attemptid the id of the current attempt, if there is one,
     *      otherwise null.
     * @return bool whether a check is required before the user starts/continues
     *      their attempt.
     */
    public function is_preflight_check_required($attemptid): bool {
        global $SESSION, $USER;

        $quizid = $this->quizobj->get_quizid();

        if ($this->quizobj->is_preview_user()) {
            return false;
        }

        $attempt = util::guess_attempt($USER->id, $quizid, $attemptid);

        if (empty($SESSION->quizaccess_honorlock_exam) || $SESSION->quizaccess_honorlock_exam != $quizid) {
            return true;
        }

        if (empty($SESSION->quizaccess_honorlock_attempt) || $SESSION->quizaccess_honorlock_attempt != $attempt) {
            return true;
        }

        return false;
    }

    /**
     * Add any field you want to pre-flight check form. You should only do
     * something here if is_preflight_check_required() returned true.
     *
     * @param mod_quiz\form\preflight_check_form $quizform the form being built.
     * @param MoodleQuickForm $mform The wrapped MoodleQuickForm.
     * @param int|null $attemptid the id of the current attempt, if there is one,
     *      otherwise null.
     */
    public function add_preflight_check_form_fields(
        mod_quiz\form\preflight_check_form $quizform,
        MoodleQuickForm $mform,
        $attemptid
    ) {
        global $PAGE, $USER;

        $mform->addElement('header', 'honorlockheader', get_string('pluginname', 'quizaccess_honorlock'));

        $quizid = $this->quizobj->get_quizid();

        // Quiz does not check if there are any missing requirements after notify_preflight_check_passed,
        // unfortunately there are some rules that rely on this, ignore them here.
        $accessmanager = $this->quizobj->get_access_manager(time());
        $reflectionclass = new ReflectionClass(get_class($accessmanager));
        $reflectionproperty = $reflectionclass->getProperty('rules');
        $reflectionproperty->setAccessible(true);
        $rules = $reflectionproperty->getValue($accessmanager);
        $otherrequirements = false;
        foreach ($rules as $rule) {
            if ($rule instanceof \quizaccess_timelimit) {
                continue;
            }
            if ($rule instanceof \quizaccess_honorlock) {
                // This just shows info, it does not require any user action.
                continue;
            }
            if ($rule->is_preflight_check_required($attemptid)) {
                $otherrequirements = true;
                break;
            }
        }

        if ($otherrequirements) {
            $mform->addElement(
                'static',
                'quizaccess_honorlock_redirect',
                '',
                get_string('otherrequirements', 'quizaccess_honorlock')
            );
            return;
        }

        // Quiz mangles page URLs, we have to rely on deprecated $FULLSCRIPT here.
        // phpcs:disable
        global $FULLSCRIPT;
        if (strpos($FULLSCRIPT, '/mod/quiz/startattempt.php') === false) {
            // The fancy JS dialog forms with preflight are not suitable for Honorlock,
            // so redirect to mod/quiz/startattempt.php if not there.
            $mform->addElement('static', 'quizaccess_honorlock_redirect', '',
                get_string('ruledescription', 'quizaccess_honorlock'));
            return;
        }
        // phpcs:enable

        if (util::is_behat()) {
            $mform->addElement('advcheckbox', 'quizaccess_honorlock_verification', 'HONORLOCK BEHAT PREFLIGHT');
            return;
        }

        $attempt = util::guess_attempt($USER->id, $quizid, $attemptid);

        $honorlock = new honorlock();

        // Hit HL-API extension-check.
        $extensioncheckresult = $honorlock->extension_check();

        // Hit HL-API create session.
        $sessiondetails = [
            'external_exam_id' => $quizid,
            'exam_taker_id' => $USER->id,
            'exam_taker_email' => $USER->email,
            'exam_taker_first_name' => $USER->firstname,
            'exam_taker_last_name' => $USER->lastname,
            'exam_taker_attempt_id' => $attempt,
        ];
        $sessioncreateresult = $honorlock->create_session($sessiondetails);
        if ($sessioncreateresult === null) {
            $mform->addElement('static', 'apierror', ' <span class="alert alert-danger">'
                . get_string('apierror', 'quizaccess_honorlock') . '</span>');
            return;
        }

        // Hit HL-API get exam instructions.
        $examinstructions = $honorlock->get_exam_instructions($quizid);
        if (!isset($examinstructions->launch_screen_url)) {
            $mform->addElement('static', 'apierror', ' <span class="alert alert-danger">'
                . get_string('apierror', 'quizaccess_honorlock') . '</span>');
            return;
        }

        $data = [
            'session_details' => $sessioncreateresult,
            'external_exam_id' => $quizid,
            'exam_taker_id' => $USER->id,
            'exam_taker_name' => $USER->firstname . " " . $USER->lastname,
            'exam_taker_attempt_id' => $attempt,
            'extension_frame_src' => $extensioncheckresult->iframe_src,
            'instructions_frame_src' => $examinstructions->launch_screen_url,
        ];

        $mform->addElement(
            'hidden',
            'quizaccess_honorlock_verification',
            '',
            ['id' => 'quizaccess-honorlock-verification-id', 'data-honorlock' => json_encode($data)]
        );
        $mform->setType('quizaccess_honorlock_verification', PARAM_BOOL);

        $frames = html_writer::tag('iframe', '', [
            'id' => 'id_hlextensioncheckiframe',
            'style' => 'width: 80vw; height: 600px; border: none',
        ]);
        $frames .= html_writer::tag('iframe', '', [
            'id' => 'id_hlexaminstructioniframe',
            'style' => 'width: 80vw; height: 600px; border: none; display: none',
        ]);

        $mform->addElement('static', 'quizaccess_honorlock_frames', '', $frames);

        $PAGE->requires->js_call_amd('quizaccess_honorlock/honorlockproctoring', 'preflightInit');
    }

    /**
     * Validate the pre-flight check form submission. You should only do
     * something here if is_preflight_check_required() returned true.
     *
     * If the form validates, the user will be allowed to continue.
     *
     * @param array $data the submitted form data.
     * @param array $files any files in the submission.
     * @param array $errors the list of validation errors that is being built up.
     * @param int|null $attemptid the id of the current attempt, if there is one,
     *      otherwise null.
     * @return array the update $errors array;
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        global $USER, $SESSION;
        if (!$this->accessmanagerhacked) {
            // Move rule to the end.
            $this->accessmanagerhacked = true;
            $accessmanager = $this->quizobj->get_access_manager(time());

            $reflectionclass = new ReflectionClass(get_class($accessmanager));
            $reflectionproperty = $reflectionclass->getProperty('rules');
            $reflectionproperty->setAccessible(true);
            $rules = $reflectionproperty->getValue($accessmanager);
            if (isset($rules[static::class])) {
                $honorlockrule = $rules[static::class];
                unset($rules[static::class]);
                $rules[static::class] = $honorlockrule;
                $reflectionproperty->setValue($accessmanager, $rules);
            }
        }

        if (util::is_behat()) {
            if (!empty($data['quizaccess_honorlock_verification'])) {
                $quizid = (int)$this->quizobj->get_quizid();
                $attempt = util::guess_attempt($USER->id, $quizid, $attemptid);
                $SESSION->quizaccess_honorlock_exam = $quizid;
                $SESSION->quizaccess_honorlock_attempt = $attempt;
            }
        }

        return $errors;
    }

    /**
     * The pre-flight check has passed. This is a chance to record that fact in
     * some way.
     * @param int|null $attemptid the id of the current attempt, if there is one,
     *      otherwise null.
     */
    public function notify_preflight_check_passed($attemptid) {
        if ($this->accessmanagerhacked) {
            // This means this is the last rule processed in start attempt page.
            if (self::is_preflight_check_required($attemptid)) {
                // Do Honorlock stuff before starting attempt.
                $page = optional_param('page', -1, PARAM_INT);
                redirect($this->quizobj->start_attempt_url($page));
            }
        }
    }

    /**
     * This is called when the current attempt at the quiz is finished.
     * In case of Honorlock the session is usually ended from event observers.
     */
    public function current_attempt_finished() {
        global $SESSION;

        if (empty($SESSION->quizaccess_honorlock_exam)) {
            return;
        }

        if ($SESSION->quizaccess_honorlock_exam != $this->quizobj->get_quizid()) {
            return;
        }

        \quizaccess_honorlock\local\observer::end_session(
            $SESSION->quizaccess_honorlock_exam,
            $SESSION->quizaccess_honorlock_attempt
        );
    }
}
