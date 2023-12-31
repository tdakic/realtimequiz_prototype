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
 * Defines message providers (types of message sent) for the realtimequiz module.
 *
 * @package   mod_realtimequiz
 * @copyright 2010 Andrew Davis http://moodle.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // Notify teacher that a student has submitted a realtimequiz attempt.
    'submission' => [
        'capability' => 'mod/realtimequiz:emailnotifysubmission'
    ],

    // Confirm a student's realtimequiz attempt.
    'confirmation' => [
        'capability' => 'mod/realtimequiz:emailconfirmsubmission',
        'defaults' => [
            'airnotifier' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],

    // Warning to the student that their realtimequiz attempt is now overdue, if the realtimequiz
    // has a grace period.
    'attempt_overdue' => [
        'capability' => 'mod/realtimequiz:emailwarnoverdue',
        'defaults' => [
            'airnotifier' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],

    'attempt_grading_complete' => [
        'capability' => 'mod/realtimequiz:emailnotifyattemptgraded',
        'defaults' => [
            'airnotifier' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
