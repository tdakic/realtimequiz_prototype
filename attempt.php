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
 * This script displays a particular page of a realtimequiz attempt that is in progress.
 *
 * @package   mod_realtimequiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\output\navigation_panel_attempt;
use mod_realtimequiz\output\renderer;
use mod_realtimequiz\realtimequiz_attempt;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/realtimequiz/locallib.php');

// Look for old-style URLs, such as may be in the logs, and redirect them to startattemtp.php.
if ($id = optional_param('id', 0, PARAM_INT)) {
    redirect($CFG->wwwroot . '/mod/realtimequiz/startattempt.php?cmid=' . $id . '&sesskey=' . sesskey());
} else if ($qid = optional_param('q', 0, PARAM_INT)) {
    if (!$cm = get_coursemodule_from_instance('realtimequiz', $qid)) {
        throw new \moodle_exception('invalidrealtimequizid', 'realtimequiz');
    }
    redirect(new moodle_url('/mod/realtimequiz/startattempt.php',
            ['cmid' => $cm->id, 'sesskey' => sesskey()]));
}

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$cmid = optional_param('cmid', null, PARAM_INT);
$quizid = required_param('quizid', PARAM_INT);


$attemptobj = realtimequiz_create_attempt_handling_errors($attemptid, $cmid);
//$attemptobj = realtimequiz_create_attempt_handling_errors($attemptid);

// *****************************************************************
// use the DB as the ultimate reference to which question should be shown
//messes up the review???
global $DB;
//$quizid =  $attemptobj.get_realtimequizid();

/*$quiz_db = $DB->get_record('realtimequiz', array('id' => $quizid));
$question_slot = $quiz_db->currentquestion;

if ($question_slot != $page){
  $page = $question_slot;
}*/

//end of db interaction


$page = $attemptobj->force_page_number_into_range($page);
//TTT commented out the line below for no good reason other than it wouldn't work with the line
//$PAGE->set_url($attemptobj->attempt_url(null, $page));
// During realtimequiz attempts, the browser back/forwards buttons should force a reload.
//$PAGE->set_cacheable(false);
// TTT comented the line below
//$PAGE->set_secondary_active_tab("modulepage");

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    if ($attemptobj->has_capability('mod/realtimequiz:viewreports')) {
        redirect($attemptobj->review_url(null, $page));
    } else {
        throw new moodle_exception('notyourattempt', 'realtimequiz', $attemptobj->view_url());
    }
}

// Check capabilities and block settings.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/realtimequiz:attempt');
    if (empty($attemptobj->get_realtimequiz()->showblocks)) {
        $PAGE->blocks->show_only_fake_blocks();
    }

} else {
    navigation_node::override_active_url($attemptobj->start_attempt_url());
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    redirect($attemptobj->review_url(null, $page));
} else if ($attemptobj->get_state() == realtimequiz_attempt::OVERDUE) {
    redirect($attemptobj->summary_url());
}

// Check the access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$accessmanager->setup_attempt_page($PAGE);
/** @var renderer $output */
$output = $PAGE->get_renderer('mod_realtimequiz');
$messages = $accessmanager->prevent_access();
if (!$attemptobj->is_preview_user() && $messages) {
    throw new \moodle_exception('attempterror', 'realtimequiz', $attemptobj->view_url(),
            $output->access_messages($messages));
}
if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
    redirect($attemptobj->start_attempt_url(null, $page));
}

// Set up auto-save if required.
$autosaveperiod = get_config('realtimequiz', 'autosaveperiod');
if ($autosaveperiod) {
    $PAGE->requires->yui_module('moodle-mod_realtimequiz-autosave',
            'M.mod_realtimequiz.autosave.init', [$autosaveperiod]);
}

// Log this page view.
$attemptobj->fire_attempt_viewed_event();

// Get the list of questions needed by this page.
$slots = $attemptobj->get_slots($page);

// Check.
if (empty($slots)) {
    throw new moodle_exception('noquestionsfound', 'realtimequiz', $attemptobj->view_url());
}

// Update attempt page, redirecting the user if $page is not valid.
if (!$attemptobj->set_currentpage($page)) {
    redirect($attemptobj->start_attempt_url(null, $attemptobj->get_currentpage()));
}

// Initialise the JavaScript.
$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->requires->js_init_call('M.mod_realtimequiz.init_attempt_form', null, false, realtimequiz_get_js_module());
\core\session\manager::keepalive(); // Try to prevent sessions expiring during realtimequiz attempts.

// Arrange for the navigation to be displayed in the first region on the page.
$navbc = $attemptobj->get_navigation_panel($output, navigation_panel_attempt::class, $page);
$regions = $PAGE->blocks->get_regions();
$PAGE->blocks->add_fake_block($navbc, reset($regions));

$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->set_title($attemptobj->attempt_page_title($page));
$PAGE->add_body_class('limitedwidth');
$PAGE->set_heading($attemptobj->get_course()->fullname);
$PAGE->activityheader->disable();
if ($attemptobj->is_last_page($page)) {
    $nextpage = -1;
} else {
    $nextpage = $page + 1;
}

//echo $output->attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage);
echo $output->attempt_form($attemptobj, $page, $slots, $id, $nextpage);
