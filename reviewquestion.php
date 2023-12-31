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
 * This page prints a review of a particular question attempt.
 * This page is expected to only be used in a popup window.
 *
 * @package   mod_realtimequiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once('locallib.php');

$attemptid = required_param('attempt', PARAM_INT);
$slot = required_param('slot', PARAM_INT);
$seq = optional_param('step', null, PARAM_INT);
$cmid = optional_param('cmid', null, PARAM_INT);

$baseurl = new moodle_url('/mod/realtimequiz/reviewquestion.php',
        ['attempt' => $attemptid, 'slot' => $slot]);
$currenturl = new moodle_url($baseurl);
if (!is_null($seq)) {
    $currenturl->param('step', $seq);
}
$PAGE->set_url($currenturl);

$attemptobj = realtimequiz_create_attempt_handling_errors($attemptid, $cmid);
$attemptobj->preload_all_attempt_step_users();

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
$attemptobj->check_review_capability();
$student = $DB->get_record('user', ['id' => $attemptobj->get_userid()]);

$accessmanager = $attemptobj->get_access_manager(time());
$options = $attemptobj->get_display_options(true);

$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('reviewofquestion', 'realtimequiz', [
        'question' => format_string($attemptobj->get_question_name($slot)),
        'realtimequiz' => format_string($attemptobj->get_realtimequiz_name()), 'user' => fullname($student)]));
$PAGE->set_heading($attemptobj->get_course()->fullname);
$output = $PAGE->get_renderer('mod_realtimequiz');

// Check permissions - warning there is similar code in review.php and
// realtimequiz_attempt::check_file_access. If you change on, change them all.
if ($attemptobj->is_own_attempt()) {
    if (!$attemptobj->is_finished()) {
        //echo $output->review_question_not_allowed($attemptobj, get_string('cannotreviewopen', 'realtimequiz'));
        //die();
    } else if (!$options->attempt) {
        echo $output->review_question_not_allowed($attemptobj,
                $attemptobj->cannot_review_message());
        die();
    }

} else if (!$attemptobj->is_review_allowed()) {
    throw new moodle_exception('noreviewattempt', 'realtimequiz', $attemptobj->view_url());
}

// Prepare summary informat about this question attempt.
$summarydata = [];

// Student name.
$userpicture = new user_picture($student);
$userpicture->courseid = $attemptobj->get_courseid();
$summarydata['user'] = [
    'title'   => $userpicture,
    'content' => new action_link(new moodle_url('/user/view.php', [
            'id' => $student->id, 'course' => $attemptobj->get_courseid()]),
            fullname($student, true)),
];

// Quiz name.
$summarydata['realtimequizname'] = [
    'title'   => get_string('modulename', 'realtimequiz'),
    'content' => format_string($attemptobj->get_realtimequiz_name()),
];

// Question name.
$summarydata['questionname'] = [
    'title'   => get_string('question', 'realtimequiz'),
    'content' => $attemptobj->get_question_name($slot),
];

// Other attempts at the realtimequiz.
if ($attemptobj->has_capability('mod/realtimequiz:viewreports')) {
    $otherattemptsurl = clone($baseurl);
    $otherattemptsurl->param('slot', $attemptobj->get_original_slot($slot));
    $attemptlist = $attemptobj->links_to_other_attempts($otherattemptsurl);
    if ($attemptlist) {
        $summarydata['attemptlist'] = [
            'title'   => get_string('attempts', 'realtimequiz'),
            'content' => $attemptlist,
        ];
    }
}

// Timestamp of this action.
$timestamp = $attemptobj->get_question_action_time($slot);
if ($timestamp) {
    $summarydata['timestamp'] = [
        'title'   => get_string('completedon', 'realtimequiz'),
        'content' => userdate($timestamp),
    ];
}

echo $output->review_question_page($attemptobj, $slot, $seq,
        $attemptobj->get_display_options(true), $summarydata);
