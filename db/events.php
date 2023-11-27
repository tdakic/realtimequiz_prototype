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
 * Add event handlers for the realtimequiz
 *
 * @package    mod_realtimequiz
 * @category   event
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$observers = [

    // Handle group events, so that open realtimequiz attempts with group overrides get updated check times.
    [
        'eventname' => '\core\event\course_reset_started',
        'callback' => '\mod_realtimequiz\group_observers::course_reset_started',
    ],
    [
        'eventname' => '\core\event\course_reset_ended',
        'callback' => '\mod_realtimequiz\group_observers::course_reset_ended',
    ],
    [
        'eventname' => '\core\event\group_deleted',
        'callback' => '\mod_realtimequiz\group_observers::group_deleted'
    ],
    [
        'eventname' => '\core\event\group_member_added',
        'callback' => '\mod_realtimequiz\group_observers::group_member_added',
    ],
    [
        'eventname' => '\core\event\group_member_removed',
        'callback' => '\mod_realtimequiz\group_observers::group_member_removed',
    ],

    // Handle our own \mod_realtimequiz\event\attempt_submitted event, as a way to
    // send confirmation messages asynchronously.
    [
        'eventname' => '\mod_realtimequiz\event\attempt_submitted',
        'includefile'     => '/mod/realtimequiz/locallib.php',
        'callback' => 'realtimequiz_attempt_submitted_handler',
        'internal' => false
    ],
];
