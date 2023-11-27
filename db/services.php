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
 * Quiz external functions and service definitions.
 *
 * @package    mod_realtimequiz
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

defined('MOODLE_INTERNAL') || die;

$functions = [

    'mod_realtimequiz_get_realtimequizzes_by_courses' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_realtimequizzes_by_courses',
        'description'   => 'Returns a list of realtimequizzes in a provided list of courses,
                            if no list is provided all realtimequizzes that the user can view will be returned.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_view_realtimequiz' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'view_realtimequiz',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/realtimequiz:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_get_user_attempts' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_user_attempts',
        'description'   => 'Return a list of attempts for the given realtimequiz and user.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_get_user_best_grade' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_user_best_grade',
        'description'   => 'Get the best current grade for the given user on a realtimequiz.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_get_combined_review_options' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_combined_review_options',
        'description'   => 'Combines the review options from a number of different realtimequiz attempts.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_start_attempt' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'start_attempt',
        'description'   => 'Starts a new attempt at a realtimequiz.',
        'type'          => 'write',
        'capabilities'  => 'mod/realtimequiz:attempt',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_get_attempt_data' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_attempt_data',
        'description'   => 'Returns information for the given attempt page for a realtimequiz attempt in progress.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:attempt',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_get_attempt_summary' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_attempt_summary',
        'description'   => 'Returns a summary of a realtimequiz attempt before it is submitted.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:attempt',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_save_attempt' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'save_attempt',
        'description'   => 'Processes save requests during the realtimequiz.
                            This function is intended for the realtimequiz auto-save feature.',
        'type'          => 'write',
        'capabilities'  => 'mod/realtimequiz:attempt',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_process_attempt' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'process_attempt',
        'description'   => 'Process responses during an attempt at a realtimequiz and also deals with attempts finishing.',
        'type'          => 'write',
        'capabilities'  => 'mod/realtimequiz:attempt',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_get_attempt_review' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_attempt_review',
        'description'   => 'Returns review information for the given finished attempt, can be used by users or teachers.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:reviewmyattempts',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_view_attempt' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'view_attempt',
        'description'   => 'Trigger the attempt viewed event.',
        'type'          => 'write',
        'capabilities'  => 'mod/realtimequiz:attempt',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_view_attempt_summary' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'view_attempt_summary',
        'description'   => 'Trigger the attempt summary viewed event.',
        'type'          => 'write',
        'capabilities'  => 'mod/realtimequiz:attempt',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_view_attempt_review' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'view_attempt_review',
        'description'   => 'Trigger the attempt reviewed event.',
        'type'          => 'write',
        'capabilities'  => 'mod/realtimequiz:reviewmyattempts',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_get_realtimequiz_feedback_for_grade' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_realtimequiz_feedback_for_grade',
        'description'   => 'Get the feedback text that should be show to a student who got the given grade in the given realtimequiz.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_get_realtimequiz_access_information' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_realtimequiz_access_information',
        'description'   => 'Return access information for a given realtimequiz.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_get_attempt_access_information' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_attempt_access_information',
        'description'   => 'Return access information for a given attempt in a realtimequiz.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_get_realtimequiz_required_qtypes' => [
        'classname'     => 'mod_realtimequiz_external',
        'methodname'    => 'get_realtimequiz_required_qtypes',
        'description'   => 'Return the potential question types that would be required for a given realtimequiz.',
        'type'          => 'read',
        'capabilities'  => 'mod/realtimequiz:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],

    'mod_realtimequiz_set_question_version' => [
        'classname'     => 'mod_realtimequiz\external\submit_question_version',
        'description'   => 'Set the version of question that would be required for a given realtimequiz.',
        'type'          => 'write',
        'capabilities'  => 'mod/realtimequiz:view',
        'ajax'          => true,
    ],

    'mod_realtimequiz_reopen_attempt' => [
        'classname' => 'mod_realtimequiz\external\reopen_attempt',
        'description' => 'Re-open an attempt that is currently in the never submitted state.',
        'type' => 'write',
        'capabilities' => 'mod/realtimequiz:reopenattempts',
        'ajax' => true,
    ],

    'mod_realtimequiz_get_reopen_attempt_confirmation' => [
        'classname' => 'mod_realtimequiz\external\get_reopen_attempt_confirmation',
        'description' => 'Verify it is OK to re-open a given realtimequiz attempt, and if so, return a suitable confirmation message.',
        'type' => 'read',
        'capabilities' => 'mod/realtimequiz:reopenattempts',
        'ajax' => true,
    ],
];
