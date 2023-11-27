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
 * This page prints a summary of a realtimequiz attempt before it is submitted.
 *
 * @package   mod_realtimequiz
 * @copyright 2009 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\output\navigation_panel_attempt;
use mod_realtimequiz\output\renderer;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

$attemptid = required_param('attempt', PARAM_INT); // The attempt to summarise.
$cmid = optional_param('cmid', null, PARAM_INT);

$PAGE->set_url('/mod/realtimequiz/summary.php', ['attempt' => $attemptid]);
// During realtimequiz attempts, the browser back/forwards buttons should force a reload.
$PAGE->set_cacheable(false);
$PAGE->set_secondary_active_tab("modulepage");

$attemptobj = realtimequiz_create_attempt_handling_errors($attemptid, $cmid);

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    if ($attemptobj->has_capability('mod/realtimequiz:viewreports')) {
        redirect($attemptobj->review_url(null));
    } else {
        throw new moodle_exception('notyourattempt', 'realtimequiz', $attemptobj->view_url());
    }
}

// Check capabilites.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/realtimequiz:attempt');
}

if ($attemptobj->is_preview_user()) {
    navigation_node::override_active_url($attemptobj->start_attempt_url());
}

// Check access.
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
    redirect($attemptobj->start_attempt_url(null));
}

$displayoptions = $attemptobj->get_display_options(false);

// If the attempt is now overdue, or abandoned, deal with that.
$attemptobj->handle_if_time_expired(time(), true);

// If the attempt is already closed, redirect them to the review page.
if ($attemptobj->is_finished()) {
    redirect($attemptobj->review_url());
}

\core\session\manager::keepalive(); // Try to prevent sessions expiring during realtimequiz attempts.

// Arrange for the navigation to be displayed.
if (empty($attemptobj->get_realtimequiz()->showblocks)) {
    $PAGE->blocks->show_only_fake_blocks();
}

$navbc = $attemptobj->get_navigation_panel($output, navigation_panel_attempt::class, -1);
$regions = $PAGE->blocks->get_regions();
$PAGE->blocks->add_fake_block($navbc, reset($regions));

$PAGE->navbar->add(get_string('summaryofattempt', 'realtimequiz'));
$PAGE->set_title($attemptobj->summary_page_title());
$PAGE->set_heading($attemptobj->get_course()->fullname);
$PAGE->activityheader->disable();
// Display the page.
echo $output->summary_page($attemptobj, $displayoptions);

// Log this page view.
$attemptobj->fire_attempt_summary_viewed_event();
