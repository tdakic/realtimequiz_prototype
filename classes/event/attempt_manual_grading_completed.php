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

namespace mod_realtimequiz\event;

/**
 * The mod_realtimequiz attempt manual grading complete event.
 *
 * @package    mod_realtimequiz
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_manual_grading_completed extends \core\event\base {

    protected function init() {
        $this->data['objecttable'] = 'realtimequiz_attempts';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    public function get_description() {
        return "The attempt with id '$this->objectid' for the user with id '$this->relateduserid' " .
            "for the realtimequiz with course module id '$this->contextinstanceid' is now fully graded. Sending notification.";
    }

    public static function get_name() {
        return get_string('eventattemptmanualgradingcomplete', 'mod_realtimequiz');
    }

    public function get_url() {
        return new \moodle_url('/mod/realtimequiz/review.php', ['attempt' => $this->objectid]);
    }

    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['realtimequizid'])) {
            throw new \coding_exception('The \'realtimequizid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return ['db' => 'realtimequiz_attempts', 'restore' => 'realtimequiz_attempt'];
    }

    public static function get_other_mapping() {
        $othermapped = [];
        $othermapped['realtimequizid'] = ['db' => 'realtimequiz', 'restore' => 'realtimequiz'];

        return $othermapped;
    }
}
