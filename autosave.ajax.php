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
 * Thisscript processes ajax auto-save requests during the realtimequiz.
 *
 * @package    mod_realtimequiz
 * @copyright  2013 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

// Remember the current time as the time any responses were submitted
// (so as to make sure students don't get penalized for slow processing on this page).
$timenow = time();
require_sesskey();

// Get submitted parameters.
$attemptid = required_param('attempt',  PARAM_INT);
$thispage  = optional_param('thispage', 0, PARAM_INT);
$cmid      = optional_param('cmid', null, PARAM_INT);

$transaction = $DB->start_delegated_transaction();
$attemptobj = realtimequiz_create_attempt_handling_errors($attemptid, $cmid);

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

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
    throw new moodle_exception('attemptalreadyclosed', 'realtimequiz', $attemptobj->review_url());
}

$attemptobj->process_auto_save($timenow);
$transaction->allow_commit();

// Calculate time remaining.
$timeleft = $attemptobj->get_time_left_display($timenow);

// Build response, only returning timeleft if realtimequiz in-progress
// has a time limit.
$r = new stdClass();
$r->status = "OK";
if ($timeleft !== false) {
    $r->timeleft = $timeleft;
}
echo json_encode($r);
