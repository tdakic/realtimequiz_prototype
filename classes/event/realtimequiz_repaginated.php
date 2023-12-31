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
 * The mod_realtimequiz realtimequiz re-paginated event.
 *
 * @package    mod_realtimequiz
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_realtimequiz\event;

/**
 * The mod_realtimequiz realtimequiz re-paginated event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int slotsperpage: the slot number per page option.
 * }
 *
 * @package    mod_realtimequiz
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtimequiz_repaginated extends \core\event\base {
    protected function init() {
        $this->data['objecttable'] = 'realtimequiz';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    public static function get_name() {
        return get_string('eventrealtimequizrepaginated', 'mod_realtimequiz');
    }

    public function get_description() {
        return "The user with id '$this->userid' re-paginated the realtimequiz with course module id '$this->contextinstanceid' " .
            " with the new option '{$this->other['slotsperpage']}' slot(s) per page.";
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

        if (!isset($this->other['slotsperpage'])) {
            throw new \coding_exception('The \'slotsperpage\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return ['db' => 'realtimequiz', 'restore' => 'realtimequiz'];
    }

    public static function get_other_mapping() {
        return [];
    }
}
