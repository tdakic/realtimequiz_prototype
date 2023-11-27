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

use mod_realtimequiz\local\reports\attempts_report;

/**
 * Makes some protected methods of attempts_report public to facilitate testing.
 *
 * @package   realtimequiz_overview
 * @copyright 2020 Huong Nguyen <huongnv13@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Makes some protected methods of attempts_report public to facilitate testing.
 *
 * @copyright 2020 Huong Nguyen <huongnv13@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_realtimequiz_attempts_report extends attempts_report {

    /**
     * Override this function to displays the report.
     * @param stdClass $cm the course-module for this realtimequiz.
     * @param stdClass $course the course we are in.
     * @param stdClass $realtimequiz this realtimequiz.
     */
    public function display($cm, $course, $realtimequiz) {

    }

    /**
     * Testable delete_selected_attempts function.
     *
     * @param stdClass $realtimequiz
     * @param stdClass $cm
     * @param array $attemptids
     * @param \core\dml\sql_join $allowedjoins
     */
    public function delete_selected_attempts($realtimequiz, $cm, $attemptids, \core\dml\sql_join $allowedjoins) {
        parent::delete_selected_attempts($realtimequiz, $cm, $attemptids, $allowedjoins);
    }
}
