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
 * A rule implementing the password check.
 *
 * @package   realtimequizaccess_password
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtimequizaccess_password extends access_rule_base {

    public static function make(realtimequiz_settings $realtimequizobj, $timenow, $canignoretimelimits) {
        if (empty($realtimequizobj->get_realtimequiz()->password)) {
            return null;
        }

        return new self($realtimequizobj, $timenow);
    }

    public function description() {
        return get_string('requirepasswordmessage', 'realtimequizaccess_password');
    }

    public function is_preflight_check_required($attemptid) {
        global $SESSION;
        return empty($SESSION->passwordcheckedrealtimequizzes[$this->realtimequiz->id]);
    }

    public function add_preflight_check_form_fields(preflight_check_form $realtimequizform,
            MoodleQuickForm $mform, $attemptid) {

        $mform->addElement('header', 'passwordheader', get_string('password'));
        $mform->addElement('static', 'passwordmessage', '',
                get_string('requirepasswordmessage', 'realtimequizaccess_password'));

        // Don't use the 'proper' field name of 'password' since that get's
        // Firefox's password auto-complete over-excited.
        $mform->addElement('passwordunmask', 'realtimequizpassword',
                get_string('realtimequizpassword', 'realtimequizaccess_password'), ['autofocus' => 'true']);
    }

    public function validate_preflight_check($data, $files, $errors, $attemptid) {

        $enteredpassword = $data['realtimequizpassword'];
        if (strcmp($this->realtimequiz->password, $enteredpassword) === 0) {
            return $errors; // Password is OK.

        } else if (isset($this->realtimequiz->extrapasswords)) {
            // Group overrides may have additional passwords.
            foreach ($this->realtimequiz->extrapasswords as $password) {
                if (strcmp($password, $enteredpassword) === 0) {
                    return $errors; // Password is OK.
                }
            }
        }

        $errors['realtimequizpassword'] = get_string('passworderror', 'realtimequizaccess_password');
        return $errors;
    }

    public function notify_preflight_check_passed($attemptid) {
        global $SESSION;
        $SESSION->passwordcheckedrealtimequizzes[$this->realtimequiz->id] = true;
    }

    public function current_attempt_finished() {
        global $SESSION;
        // Clear the flag in the session that says that the user has already
        // entered the password for this realtimequiz.
        if (!empty($SESSION->passwordcheckedrealtimequizzes[$this->realtimequiz->id])) {
            unset($SESSION->passwordcheckedrealtimequizzes[$this->realtimequiz->id]);
        }
    }
}
