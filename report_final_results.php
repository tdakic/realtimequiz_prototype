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
 * This script controls the display of the realtimequiz reports.
 *
 * @package   mod_realtimequiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\realtimequiz_settings;

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/reportlib.php');

$id = optional_param('id', 0, PARAM_INT);
$q = optional_param('q', 0, PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA);

if ($id) {
    $realtimequizobj = realtimequiz_settings::create_for_cmid($id);
} else {
    $realtimequizobj = realtimequiz_settings::create($q);
}
$realtimequiz = $realtimequizobj->get_realtimequiz();
$cm = $realtimequizobj->get_cm();
$course = $realtimequizobj->get_course();

$url = new moodle_url('/mod/realtimequiz/report_final_results.php', ['id' => $cm->id]);
if ($mode !== '') {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);

require_login($course, false, $cm);
$PAGE->set_pagelayout('report');
$PAGE->activityheader->disable();
$reportlist = realtimequiz_report_list($realtimequizobj->get_context());
if (empty($reportlist)) {
    throw new \moodle_exception('erroraccessingreport', 'realtimequiz');
}

// Validate the requested report name.
if ($mode == '') {
    // Default to first accessible report and redirect.
    $url->param('mode', reset($reportlist));
    redirect($url);
} else if (!in_array($mode, $reportlist)) {
    throw new \moodle_exception('erroraccessingreport', 'realtimequiz');
}
if (!is_readable("report/$mode/report.php")) {
    throw new \moodle_exception('reportnotfound', 'realtimequiz', '', $mode);
}

// Open the selected realtimequiz report and display it.
$file = $CFG->dirroot . '/mod/realtimequiz/report/' . $mode . '/report.php';
if (is_readable($file)) {
    include_once($file);
}
$reportclassname = 'realtimequiz_' . $mode . '_report';
if (!class_exists($reportclassname)) {
    throw new \moodle_exception('preprocesserror', 'realtimequiz');
}

$report = new $reportclassname();

//$report->display($realtimequiz, $cm, $course);
//TTT
$report->display_final_graph($realtimequiz, $cm, $course);

echo "<script> var M = {}; M.yui = {};</script>";
echo  '<script src="http://localhost/moodle/lib/javascript.php/1698861951/lib/javascript-static.js"></script>';

// Print footer.
//echo $OUTPUT->footer();
//echo $OUTPUT->footer_actions();

$footer = $PAGE->opencontainers->pop('header/footer');
$ttt = $PAGE->requires->get_end_code();
echo $ttt;
//echo $footer;

//$PAGE->set_state(moodle_page::STATE_DONE);

//$PAGE -> opencontainers -> pop('header/footer');

// Log that this report was viewed.
$params = [
    'context' => $realtimequizobj->get_context(),
    'other' => [
        'realtimequizid' => $realtimequiz->id,
        'reportname' => $mode
    ]
];
$event = \mod_realtimequiz\event\report_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('realtimequiz', $realtimequiz);
$event->trigger();





//TTT
//$report->display($realtimequiz, $cm, $course);
//$PAGE->set_state(moodle_page::STATE_BEFORE_HEADER);
//$PAGE->_state = (moodle_page::STATE_PRINTING_HEADER);
//$PAGE->set_state(moodle_page::STATE_PRINTING_HEADER);
//$PAGE->set_state(moodle_page::STATE_IN_BODY);
//$PAGE->header();
//$report->display_final_graph($realtimequiz, $cm, $course);
//$PAGE->set_state(moodle_page::STATE_DONE);
/*try {
      $PAGE->set_state(moodle_page::STATE_DONE);
}
catch(Exception $e) {
  echo 'Message: ' .$e->getMessage();
}*/


//echo $OUTPUT->footer_actions();

//const STATE_BEFORE_HEADER = 0;

/** The state the page is in temporarily while the header is being printed **/
//const STATE_PRINTING_HEADER = 1;

/** The state the page is in while content is presumably being printed **/
//const STATE_IN_BODY = 2;

/**
 * The state the page is when the footer has been printed and its function is
 * complete.
 */
//const STATE_DONE = 3;

// Print footer.
//$PAGE->set_state(0);
//$PAGE->set_state(1);
//$PAGE->set_state(2);
//$PAGE->set_state(3);
