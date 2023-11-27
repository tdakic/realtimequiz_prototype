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
 * This script deals with starting a new attempt at a realtimequiz.
 *
 * Normally, it will end up redirecting to attempt.php - unless a password form is displayed.
 *
 * This code used to be at the top of attempt.php, if you are looking for CVS history.
 *
 * @package   mod_realtimequiz
 * @copyright 2009 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\realtimequiz_attempt;
use mod_realtimequiz\realtimequiz_settings;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

// Get submitted parameters.
$id = required_param('cmid', PARAM_INT); // Course module id
$forcenew = optional_param('forcenew', false, PARAM_BOOL); // Used to force a new preview
$page = optional_param('page', -1, PARAM_INT); // Page to jump to in the attempt.

$realtimequizobj = realtimequiz_settings::create_for_cmid($id, $USER->id);

// This script should only ever be posted to, so set page URL to the view page.
//$PAGE->set_url($realtimequizobj->view_url());
// During realtimequiz attempts, the browser back/forwards buttons should force a reload.
//$PAGE->set_cacheable(false);

// Check login and sesskey.
require_login($realtimequizobj->get_course(), false, $realtimequizobj->get_cm());
require_sesskey();
//$PAGE->set_heading($realtimequizobj->get_course()->fullname);

// If no questions have been set up yet redirect to edit.php or display an error.
if (!$realtimequizobj->has_questions()) {
    if ($realtimequizobj->has_capability('mod/realtimequiz:manage')) {
        redirect($realtimequizobj->edit_url());
    } else {
        throw new \moodle_exception('cannotstartnoquestions', 'realtimequiz', $realtimequizobj->view_url());
    }
}

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();
$accessmanager = $realtimequizobj->get_access_manager($timenow);

$context = $realtimequizobj->get_context();
$realtimequiz = $realtimequizobj->get_realtimequiz();

//TTT
/*if ($forcenew){
realtimequiz_delete_previews($realtimequiz, $userid = USER->id) ;
}*/

// Validate permissions for creating a new attempt and start a new preview attempt if required.
list($currentattemptid, $attemptnumber, $lastattempt, $messages, $page) =
    realtimequiz_validate_new_attempt($realtimequizobj, $accessmanager, $forcenew, $page, true);

if ($realtimequizobj->is_preview_user() && $currentattemptid) {
  echo $currentattemptid;
  return;
}
// TTT

//TTT ******** added Nov 8
//try to get the ateempt if the student crashed
$context = $realtimequizobj->get_context();
$realtimequiz = $realtimequizobj->get_realtimequiz();

//if the user is not a teacher if there is an unfinished attempt return its id
// THE ATTEMPT SHOULD HAVE BEEN STARTED AFTER THE START OF THE SESSION. OTHERWISE SUBMIT THE ATTEMPT AND LET THE CODE START A NEW ONE
if (!has_capability('mod/realtimequiz:control', $context)) {
   if( $unfinishedattempt = realtimequiz_get_user_attempt_unfinished($realtimequiz->id, $USER->id)){
      echo $unfinishedattempt->id;
      return;
  }
}

// ********************



//global $SESSION;
//$SESSION->currentattemptid = $currentattemptid;

//echo "currentattemptid CURRENT ATTEMPT ID TTT";
//echo "<br />";
//echo "currentattemptid CURRENT ATTEMPT ID TTT done<br />";
//echo json_encode($currentattemptid);


// Check access.
/*if (!$realtimequizobj->is_preview_user() && $messages) {
    $output = $PAGE->get_renderer('mod_realtimequiz');
    throw new \moodle_exception('attempterror', 'realtimequiz', $realtimequizobj->view_url(),
            $output->access_messages($messages));
}
*/
/*if ($accessmanager->is_preflight_check_required($currentattemptid)) {
    // Need to do some checks before allowing the user to continue.
    $mform = $accessmanager->get_preflight_check_form(
            $realtimequizobj->start_attempt_url($page), $currentattemptid);

    if ($mform->is_cancelled()) {
        $accessmanager->back_to_view_page($PAGE->get_renderer('mod_realtimequiz'));

    } else if (!$mform->get_data()) {

        // Form not submitted successfully, re-display it and stop.
        $PAGE->set_url($realtimequizobj->start_attempt_url($page));
        $PAGE->set_title($realtimequizobj->get_realtimequiz_name());
        $accessmanager->setup_attempt_page($PAGE);
        $output = $PAGE->get_renderer('mod_realtimequiz');
        if (empty($realtimequizobj->get_realtimequiz()->showblocks)) {
            $PAGE->blocks->show_only_fake_blocks();
        }

        echo $output->start_attempt_page($realtimequizobj, $mform);
        die();
    }

    // Pre-flight check passed.
    $accessmanager->notify_preflight_check_passed($currentattemptid);
}*/
if ($currentattemptid) {
    if ($lastattempt->state == realtimequiz_attempt::OVERDUE) {
        redirect($realtimequizobj->summary_url($lastattempt->id));
    } else {
        //redirect($realtimequizobj->attempt_url($currentattemptid, $page));
        echo $currentattemptid;
    }
}




$attempt = realtimequiz_prepare_and_start_new_attempt($realtimequizobj, $attemptnumber, $lastattempt);

/*if (has_capability('mod/realtimequiz:control', $context))
//TTT added on Oct 11 the xml Bits
header('content-type: text/xml');
echo '<?xml version="1.0" ?><realtimequiz>';
if (has_capability('mod/realtimequiz:control', $context)){
  echo "<status>waitforquestion</status>";
}
else
{
  echo "<status>quizrunning</status>";
}*/
//echo "<attemptid>";
echo $attempt->id;
//echo "</attemptid>";
//echo '</realtimequiz>';
// Redirect to the attempt page.
//redirect($realtimequizobj->attempt_url($attempt->id, $page));
