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
 * The mod_realtimequiz attempt regraded event.
 *
 * @package    mod_realtimequiz
 * @copyright  2020 Russell Boyatt <russell.boyatt@warwick.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_realtimequiz\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_realtimequiz attempt regraded event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int realtimequizid: the id of the realtimequiz.
 * }
 *
 * @package    mod_realtimequiz
 * @copyright  2020 Russell Boyatt <russell.boyatt@warwick.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_regraded extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'realtimequiz_attempts';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventrealtimequizattemptregraded', 'mod_realtimequiz');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' regraded realtimequiz attempt with id '$this->objectid' by user " .
          "with id '$this->relateduserid' for the realtimequiz with course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/realtimequiz/review.php', ['attempt' => $this->objectid]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->userid)) {
            throw new \coding_exception('The \'userid\' must be set.');
        }

        if (!isset($this->other['realtimequizid'])) {
            throw new \coding_exception('The \'realtimequizid\' value must be set in other.');
        }
    }

    /**
     * Get mapping to objects
     *
     * @return array Array of mappings
     */
    public static function get_objectid_mapping() {
        return ['db' => 'realtimequiz_attempts', 'restore' => 'realtimequiz_attempt'];
    }

    /**
     * Retrieve other mapping detail for the event.
     *
     * @return array Array of array mappings
     */
    public static function get_other_mapping() {
        $othermapped = [];
        $othermapped['realtimequizid'] = ['db' => 'realtimequiz', 'restore' => 'realtimequiz'];

        return $othermapped;
    }
}
