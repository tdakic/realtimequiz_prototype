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

/**
 * The mod_realtimequiz slots moved event.
 *
 * @package    mod_realtimequiz
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_realtimequiz\event;

/**
 * The mod_realtimequiz slot moved event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int realtimequizid: the id of the realtimequiz.
 *      - int previousslotnumber: the previous slot number in realtimequiz.
 *      - int afterslotnumber: the new slot number in realtimequiz.
 *      - int page: the page of new slot position in realtimequiz.
 * }
 *
 * @package    mod_realtimequiz
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slot_moved extends \core\event\base {
    protected function init() {
        $this->data['objecttable'] = 'realtimequiz_slots';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    public static function get_name() {
        return get_string('eventslotmoved', 'mod_realtimequiz');
    }

    public function get_description() {
        if ($this->other['afterslotnumber'] == 0) {
            $newposition = 'before the first slot';
        } else {
            $newposition = "after slot number '{$this->other['afterslotnumber']}'";
        }
        return "The user with id '$this->userid' has moved the slot with id '{$this->objectid}' " .
            "and slot number '{$this->other['previousslotnumber']}' to the new position $newposition " .
            "on page '{$this->other['page']}' belonging to the realtimequiz with course module id '$this->contextinstanceid'.";
    }

    public function get_url() {
        return new \moodle_url('/mod/realtimequiz/edit.php', [
            'cmid' => $this->contextinstanceid
        ]);
    }

    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' value must be set.');
        }

        if (!isset($this->contextinstanceid)) {
            throw new \coding_exception('The \'contextinstanceid\' value must be set.');
        }

        if (!isset($this->other['realtimequizid'])) {
            throw new \coding_exception('The \'realtimequizid\' value must be set in other.');
        }

        if (!isset($this->other['previousslotnumber'])) {
            throw new \coding_exception('The \'previousslotnumber\' value must be set in other.');
        }

        if (!isset($this->other['afterslotnumber'])) {
            throw new \coding_exception('The \'afterslotnumber\' value must be set in other.');
        }

        if (!isset($this->other['page'])) {
            throw new \coding_exception('The \'page\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return ['db' => 'realtimequiz_slots', 'restore' => 'realtimequiz_question_instance'];
    }

    public static function get_other_mapping() {
        $othermapped = [];
        $othermapped['realtimequizid'] = ['db' => 'realtimequiz', 'restore' => 'realtimequiz'];

        return $othermapped;
    }
}
