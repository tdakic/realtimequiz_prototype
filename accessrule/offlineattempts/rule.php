<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

use mod_realtimequiz\form\preflight_check_form;
use mod_realtimequiz\local\access_rule_base;
use mod_realtimequiz\realtimequiz_settings;

/**
 * A rule implementing the offlineattempts check.
 *
 * @package    realtimequizaccess_offlineattempts
 * @copyright  2016 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.2
 */
class realtimequizaccess_offlineattempts extends access_rule_base {

    public static function make(realtimequiz_settings $realtimequizobj, $timenow, $canignoretimelimits) {
        global $CFG;

        // If mobile services are off, the user won't be able to use any external app.
        if (empty($CFG->enablemobilewebservice) or empty($realtimequizobj->get_realtimequiz()->allowofflineattempts)) {
            return null;
        }

        return new self($realtimequizobj, $timenow);
    }

    public function is_preflight_check_required($attemptid) {
        global $SESSION, $DB;

        // First, check if the user did something offline.
        if (!empty($attemptid)) {
            $timemodifiedoffline = $DB->get_field('realtimequiz_attempts', 'timemodifiedoffline', ['id' => $attemptid]);
            if (empty($timemodifiedoffline)) {
                return false;
            }
            return empty($SESSION->offlineattemptscheckedrealtimequizzes[$this->realtimequiz->id]);
        } else {
            // Starting a new attempt, we don't have to check anything here.
            return false;
        }
    }

    public function add_preflight_check_form_fields(preflight_check_form $realtimequizform,
            MoodleQuickForm $mform, $attemptid) {
        global $DB;

        $timemodifiedoffline = $DB->get_field('realtimequiz_attempts', 'timemodifiedoffline', ['id' => $attemptid]);
        $lasttime = format_time(time() - $timemodifiedoffline);

        $mform->addElement('header', 'offlineattemptsheader', get_string('mobileapp', 'realtimequizaccess_offlineattempts'));
        $mform->addElement('static', 'offlinedatamessage', '',
                get_string('offlinedatamessage', 'realtimequizaccess_offlineattempts', $lasttime));
        $mform->addElement('advcheckbox', 'confirmdatasaved', null,
                get_string('confirmdatasaved', 'realtimequizaccess_offlineattempts'));
    }

    public function validate_preflight_check($data, $files, $errors, $attemptid) {

        // The user confirmed that he doesn't have unsaved work.
        if (!empty($data['confirmdatasaved'])) {
            return $errors;
        }

        $errors['confirmdatasaved'] = get_string('pleaseconfirm', 'realtimequizaccess_offlineattempts');
        return $errors;
    }

    public function notify_preflight_check_passed($attemptid) {
        global $SESSION;
        $SESSION->offlineattemptscheckedrealtimequizzes[$this->realtimequiz->id] = true;
    }

    public function current_attempt_finished() {
        global $SESSION;
        // Clear the flag in the session that says that the user has already agreed to the notice.
        if (!empty($SESSION->offlineattemptscheckedrealtimequizzes[$this->realtimequiz->id])) {
            unset($SESSION->offlineattemptscheckedrealtimequizzes[$this->realtimequiz->id]);
        }
    }

    public static function add_settings_form_fields(
            mod_realtimequiz_mod_form $realtimequizform, MoodleQuickForm $mform) {
        global $CFG;

        // Allow to enable the access rule only if the Mobile services are enabled.
        if ($CFG->enablemobilewebservice) {
            $mform->addElement('selectyesno', 'allowofflineattempts',
                                get_string('allowofflineattempts', 'realtimequizaccess_offlineattempts'));
            $mform->addHelpButton('allowofflineattempts', 'allowofflineattempts', 'realtimequizaccess_offlineattempts');
            $mform->setDefault('allowofflineattempts', 0);
            $mform->setAdvanced('allowofflineattempts');
            $mform->disabledIf('allowofflineattempts', 'timelimit[number]', 'neq', 0);
            $mform->disabledIf('allowofflineattempts', 'subnet', 'neq', '');
        }
    }

    public static function validate_settings_form_fields(array $errors,
            array $data, $files, mod_realtimequiz_mod_form $realtimequizform) {
        global $CFG;

        if ($CFG->enablemobilewebservice) {
            // Do not allow offline attempts if:
            // - The realtimequiz uses a timer.
            // - The realtimequiz is restricted by subnet.
            // - The question behaviour is not deferred feedback or deferred feedback with CBM.
            if (!empty($data['allowofflineattempts']) and
                    (!empty($data['timelimit']) or !empty($data['subnet']) or
                    ($data['preferredbehaviour'] != 'deferredfeedback' and $data['preferredbehaviour'] != 'deferredcbm'))) {

                $errors['allowofflineattempts'] = get_string('offlineattemptserror', 'realtimequizaccess_offlineattempts');
            }
        }

        return $errors;
    }
}
