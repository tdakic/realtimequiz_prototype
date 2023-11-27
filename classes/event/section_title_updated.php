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
 * The mod_realtimequiz section title updated event.
 *
 * @package    mod_realtimequiz
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_realtimequiz\event;

/**
 * The mod_realtimequiz section title updated event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int realtimequizid: the id of the realtimequiz.
 *      - string newtitle: new title.
 *      - int firstslotid: id of the slot which is right after the section break.
 *      - int firstslotnumber: slot number of the slot which is right after the section break.
 * }
 *
 * @package    mod_realtimequiz
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_title_updated extends \core\event\base {
    protected function init() {
        $this->data['objecttable'] = 'realtimequiz_sections';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    public static function get_name() {
        return get_string('eventsectiontitleupdated', 'mod_realtimequiz');
    }

    public function get_description() {
        $description = "The user with id '$this->userid' updated the section with id '{$this->objectid}' ";
        if ($this->other['firstslotid'] && $this->other['firstslotnumber']) {
            $description .= "before the slot with id '{$this->other['firstslotid']}' " .
                "and slot number '{$this->other['firstslotnumber']}' ";
        }
        $description .= "belonging to the realtimequiz with course module id '$this->contextinstanceid'. " .
            "Its title was changed to '{$this->other['newtitle']}'.";

        return $description;
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

        if (!isset($this->other['newtitle'])) {
            throw new \coding_exception('The \'newtitle\' value must be set in other.');
        }

    }

    public static function get_objectid_mapping() {
        return ['db' => 'realtimequiz_sections', 'restore' => 'realtimequiz_section'];
    }

    public static function get_other_mapping() {
        $othermapped = [];
        $othermapped['realtimequizid'] = ['db' => 'realtimequiz', 'restore' => 'realtimequiz'];
        $othermapped['firstslotid'] = ['db' => 'realtimequiz_slots', 'restore' => 'realtimequiz_question_instance'];

        return $othermapped;
    }
}
