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
 * Honorlock Proctoring javascript.
 *
 * @module quizaccess_honorlock/honorlockproctoring
 * @copyright 2024 Honorlock (https://honorlock.com/)
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Log from 'core/log';
import Ajax from 'core/ajax';
import Config from 'core/config';
import Notification from 'core/notification';

/**
 * Load Honorlock SDK from CDN.
 *
 * @returns {Promise}
 */
const injectSdkScript = async() => {
    // Inject CDN SDK.
    let scriptLoaded = false;
    let sdkScript = document.createElement('script');
    sdkScript.type = "module";
    sdkScript.onload = () => {
        Log.debug('honorlockproctoring::injectSdkScript done');
        scriptLoaded = true;
    };
    // Compressed version does not send expected JS mimetype, so use the human-readable for now.
    sdkScript.src = "https://unpkg.com/@honorlock/integration-sdk-js@1.3.0/dist/honorlock.js";
    document.getElementsByTagName('head')[0].appendChild(sdkScript);

    const injectedAt = new Date();
    return new Promise(function(resolve, reject) {
        const poll = ()=> {
            // 10s timeout
            if (new Date().getTime() - injectedAt.getTime() > 10000) {
                return reject('honorlockproctoring:injectSdkScript timed out');
            }

            if (scriptLoaded) {
                Log.debug('honorlockproctoring::injectSdkScript done');
                return resolve();
            }
            return setTimeout(poll, 100);
        };

        poll();
    });
};

/**
 * Called on main quiz page to reset preexisting session.
 *
 * @returns {Promise<void>}
 */
export const quizViewReset = async() => {
    Log.debug('honorlockproctoring::quizViewReset plugin func');

    await injectSdkScript();
    await window.Honorlock.init();
    window.Honorlock.examClosed();
};

/**
 * Alter full preflight page (not used in the JS dialog).
 *
 * @returns {Promise<void>}
 */
export const preflightInit = async() => {
    Log.debug('honorlockproctoring::preflightInit plugin func');

    // Honorlock preflight is not submitted by standard Moodle form button,
    // note there is the Back button at the top.
    const form = document.getElementById('mod_quiz_preflight_form');
    const startButton = form.querySelectorAll('input[name="submitbutton"]')[0];
    startButton.style.display = 'none';
    const cancelButton = form.querySelectorAll('input[name="cancel"]')[0];
    cancelButton.style.display = 'none';

    await injectSdkScript();

    const json = document.getElementById('quizaccess-honorlock-verification-id').dataset.honorlock;
    const proctoringArgs = JSON.parse(json);

    const honorlockExamExtensionIframe = document.getElementById('id_hlextensioncheckiframe');
    honorlockExamExtensionIframe.setAttribute('src', proctoringArgs.extension_frame_src);

    await globalThis.Honorlock.init();

    const honorlockExamInstructionsIframe = document.getElementById('id_hlexaminstructioniframe');

    // On Extension Verified show the exam instructions with Start exam button at the end.
    window.Honorlock.onExtensionVerified(() => {
        honorlockExamInstructionsIframe.setAttribute('src', proctoringArgs.instructions_frame_src);

        honorlockExamExtensionIframe.style.display = 'none';
        honorlockExamInstructionsIframe.style.display = 'block';

        // Setup extension session.
        /* eslint-disable */
        window.Honorlock.setupSession({
            session: proctoringArgs.session_details,
            app_url: Config.wwwroot,
            external_exam_id: proctoringArgs.external_exam_id,
            exam_taker_id: proctoringArgs.exam_taker_id,
            exam_taker_name: proctoringArgs.exam_taker_name,
            exam_taker_attempt_id: proctoringArgs.exam_taker_attempt_id,
        });
        /* eslint-enable */
    });

    window.Honorlock.onLaunchProctoringIframeResize(data => {
        const updatedIframeHeight = data.launch_proctoring_data.iframe_height;
        const honorlockExamInstructionsIframe = document.getElementById('id_hlexaminstructioniframe');
        honorlockExamInstructionsIframe.style.height = updatedIframeHeight + 'px';
    });

    window.Honorlock.onBeginExam(() => {
        Ajax.call([{
            methodname: 'quizaccess_honorlock_exam_started',
            args: {
                quizid: proctoringArgs.external_exam_id,
                attempt: proctoringArgs.exam_taker_attempt_id
            },
            done: function(data) {
                if (data.success === true) {
                    Log.debug('honorlockproctoring::preflightInit quizaccess_honorlock_exam_started ajax success');
                    form.submit();
                } else {
                    // This should not happen.
                    Log.debug('honorlockproctoring::preflightInit quizaccess_honorlock_exam_started ajax error');
                    window.Honorlock.examClosed();
                    /* eslint-disable */
                    alert('Error: ' + data.errors.join(' '));
                    /* eslint-enable */
                    window.location = Config.wwwroot + '/mod/quiz/view.php?q=' + proctoringArgs.external_exam_id;
                }
            },
            fail: function(ex) {
                // This should not happen.
                Log.debug('honorlockproctoring::preflightInit quizaccess_honorlock_exam_started ajax exception');
                window.Honorlock.examClosed();
                Notification.exception(ex);
            }
        }]);
    });
};

/**
 * Called on every page where user attempts the quiz.
 *
 * @param {integer} quizid
 * @returns {Promise<void>}
 */
export const takeQuiz = async(quizid) => {
    Log.debug('honorlockproctoring::takeQuiz plugin func');

    const navigation = document.getElementsByClassName('tertiary-navigation')[0];
    if (navigation) {
        navigation.style.display = 'none';
    }

    if (Config.behatsiterunning) {
        return;
    }

    await injectSdkScript();
    await window.Honorlock.init();
    window.Honorlock.examLoaded();

    window.Honorlock.onExtensionRemoved(() => {
        window.location = Config.wwwroot + '/mod/quiz/view.php?q=' + quizid;
    });
    window.Honorlock.onExtensionOffline(() => {
        window.location = Config.wwwroot + '/mod/quiz/view.php?q=' + quizid;
    });

    const form = document.querySelector('form#responseform');
    if (!form) {
        return;
    }

    // Get question info.
    const questionNumber =
        form.querySelector('.que > .info > .no')?.textContent;
    const questionText = form.querySelector(
        '.que > .content .qtext'
    )?.textContent;
    const questionHtml = form.querySelector(
        '.que > .content .qtext'
    )?.innerHTML;

    // Log loaded question
    window.Honorlock.questionLoaded(
        String(questionNumber),
        String(questionText),
        String(questionHtml)
    );
};

/**
 * Called on page where user submits the quiz.
 *
 * @returns {Promise<void>}
 */
export const quizSummary = async() => {
    Log.debug('honorlockproctoring::quizSummary plugin func');

    const navigation = document.getElementsByClassName('tertiary-navigation')[0];
    if (navigation) {
        navigation.style.display = 'none';
    }

    if (Config.behatsiterunning) {
        return;
    }

    await injectSdkScript();
    await window.Honorlock.init();
    window.Honorlock.examLoaded();

    let submitAttemptBtn = document.querySelector(
        "form#frm-finishattempt button[type='submit']"
    );

    if (!submitAttemptBtn) {
        Log.debug('honorlockproctoring::quizSummary missing Submit attempt button');
        return;
    }

    // Exam submit buttons.
    let confirmSubmitAttemptBtn;
    submitAttemptBtn.addEventListener('click', () => {
        // Get the modals submit button
        const searchForConfirmBtnFunc = () => {
            confirmSubmitAttemptBtn = document.querySelector(
                "div.modal button[data-action='save']"
            );

            if (
                confirmSubmitAttemptBtn === undefined ||
                confirmSubmitAttemptBtn === null
            ) {
                // Re-trigger
                setTimeout(searchForConfirmBtnFunc, 250);
            } else {
                // Replace confirm exam submit button
                const newConfirmSubmitAttemptBtn =
                    confirmSubmitAttemptBtn.cloneNode(true);
                newConfirmSubmitAttemptBtn.addEventListener('click', () => {
                    window.Honorlock.examSubmit();
                });
                confirmSubmitAttemptBtn.replaceWith(newConfirmSubmitAttemptBtn);
            }
        };
        setTimeout(searchForConfirmBtnFunc, 250);
    });

    // Submit the exam form onExamSubmit event.
    window.Honorlock.onExamSubmit(() => {
        let examSubmitForm = document.querySelector(
            'form#frm-finishattempt'
        );

        examSubmitForm.submit();
    });
};
