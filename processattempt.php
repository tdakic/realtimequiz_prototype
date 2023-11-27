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
 * This page deals with processing responses during an attempt at a realtimequiz.
 *
 * People will normally arrive here from a form submission on attempt.php or
 * summary.php, and once the responses are processed, they will be redirected to
 * attempt.php or summary.php.
 *
 * This code used to be near the top of attempt.php, if you are looking for CVS history.
 *
 * @package   mod_realtimequiz
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\realtimequiz_attempt;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

// Remember the current time as the time any responses were submitted
// (so as to make sure students don't get penalized for slow processing on this page).
$timenow = time();

// Get submitted parameters.
$attemptid     = required_param('attempt',  PARAM_INT);
$thispage      = optional_param('thispage', 0, PARAM_INT);
$nextpage      = optional_param('nextpage', 0, PARAM_INT);
$previous      = optional_param('previous',      false, PARAM_BOOL);
$next          = optional_param('next',          false, PARAM_BOOL);
$finishattempt = optional_param('finishattempt', false, PARAM_BOOL);
$timeup        = optional_param('timeup',        0,      PARAM_BOOL); // True if form was submitted by timer.
$mdlscrollto   = optional_param('mdlscrollto', '', PARAM_RAW);
$cmid          = optional_param('cmid', null, PARAM_INT);

$attemptobj = realtimequiz_create_attempt_handling_errors($attemptid, $cmid);

// Set $nexturl now.
if ($next) {
    $page = $nextpage;
} else if ($previous && $thispage > 0) {
    $page = $thispage - 1;
} else {
    $page = $thispage;
}
if ($page == -1) {
    $nexturl = $attemptobj->summary_url();
} else {
    $nexturl = $attemptobj->attempt_url(null, $page);
    if ($mdlscrollto !== '') {
        $nexturl->param('mdlscrollto', $mdlscrollto);
    }
}

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
require_sesskey();

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    throw new moodle_exception('notyourattempt', 'realtimequiz', $attemptobj->view_url());
}

// Check capabilities.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/realtimequiz:attempt');
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    throw new moodle_exception('attemptalreadyclosed', 'realtimequiz', $attemptobj->view_url());
}

// If this page cannot be accessed, notify user and send them to the correct page.
if (!$finishattempt && !$attemptobj->check_page_access($thispage)) {
    throw new moodle_exception('submissionoutofsequencefriendlymessage', 'question',
            $attemptobj->attempt_url(null, $attemptobj->get_currentpage()));
}
// TTT
// Set up auto-save if required.
$autosaveperiod = get_config('realtimequiz', 'autosaveperiod');
if ($autosaveperiod) {
    $PAGE->requires->yui_module('moodle-mod_realtimequiz-autosave',
            'M.mod_realtimequiz.autosave.init', [$autosaveperiod]);
}

//end TTT


// Process the attempt, getting the new status for the attempt.
//$attemptobj->process_auto_save($timenow);
$status = $attemptobj->process_attempt_T($timenow, $finishattempt, $timeup, $thispage);

// TTT comment out redirection
/*if ($status == realtimequiz_attempt::OVERDUE) {
    redirect($attemptobj->summary_url());
} else if ($status == realtimequiz_attempt::IN_PROGRESS) {
    redirect($nexturl);
} else {
    // Attempt abandoned or finished.
    redirect($attemptobj->review_url());
}*/
/*echo $thispage;
echo "<br />  process_attempt finishattempt";
if ($finishattempt)
{
  echo "True <br />";
}
else {
  echo "False <br />";
}
echo "<br /> Done!!!";
*/
