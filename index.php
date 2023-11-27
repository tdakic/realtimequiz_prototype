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
 * This script lists all the instances of realtimequiz in a particular course
 *
 * @package    mod_realtimequiz
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("../../config.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT);

$PAGE->set_url('/mod/realtimequiz/index.php', ['id' => $id]);
$course = get_course($id);
$coursecontext = context_course::instance($id);
require_login($course);
$PAGE->set_pagelayout('incourse');

$params = [
    'context' => $coursecontext
];
$event = \mod_realtimequiz\event\course_module_instance_list_viewed::create($params);
$event->trigger();

// Print the header.
$strrealtimequizzes = get_string("modulenameplural", "realtimequiz");
$PAGE->navbar->add($strrealtimequizzes);
$PAGE->set_title($strrealtimequizzes);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($strrealtimequizzes, 2);

// Get all the appropriate data.
if (!$realtimequizzes = get_all_instances_in_course("realtimequiz", $course)) {
    notice(get_string('thereareno', 'moodle', $strrealtimequizzes), "../../course/view.php?id=$course->id");
    die;
}

// Check if we need the feedback header.
$showfeedback = false;
foreach ($realtimequizzes as $realtimequiz) {
    if (realtimequiz_has_feedback($realtimequiz)) {
        $showfeedback=true;
    }
    if ($showfeedback) {
        break;
    }
}

// Configure table for displaying the list of instances.
$headings = [get_string('name')];
$align = ['left'];

array_push($headings, get_string('realtimequizcloses', 'realtimequiz'));
array_push($align, 'left');

if (course_format_uses_sections($course->format)) {
    array_unshift($headings, get_string('sectionname', 'format_'.$course->format));
} else {
    array_unshift($headings, '');
}
array_unshift($align, 'center');

$showing = '';

if (has_capability('mod/realtimequiz:viewreports', $coursecontext)) {
    array_push($headings, get_string('attempts', 'realtimequiz'));
    array_push($align, 'left');
    $showing = 'stats';

} else if (has_any_capability(['mod/realtimequiz:reviewmyattempts', 'mod/realtimequiz:attempt'],
        $coursecontext)) {
    array_push($headings, get_string('grade', 'realtimequiz'));
    array_push($align, 'left');
    if ($showfeedback) {
        array_push($headings, get_string('feedback', 'realtimequiz'));
        array_push($align, 'left');
    }
    $showing = 'grades';

    $grades = $DB->get_records_sql_menu('
            SELECT qg.realtimequiz, qg.grade
            FROM {realtimequiz_grades} qg
            JOIN {realtimequiz} q ON q.id = qg.realtimequiz
            WHERE q.course = ? AND qg.userid = ?',
            [$course->id, $USER->id]);
}

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
$currentsection = '';
// Get all closing dates.
$timeclosedates = realtimequiz_get_user_timeclose($course->id);
foreach ($realtimequizzes as $realtimequiz) {
    $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id);
    $context = context_module::instance($cm->id);
    $data = [];

    // Section number if necessary.
    $strsection = '';
    if ($realtimequiz->section != $currentsection) {
        if ($realtimequiz->section) {
            $strsection = $realtimequiz->section;
            $strsection = get_section_name($course, $realtimequiz->section);
        }
        if ($currentsection !== "") {
            $table->data[] = 'hr';
        }
        $currentsection = $realtimequiz->section;
    }
    $data[] = $strsection;

    // Link to the instance.
    $class = '';
    if (!$realtimequiz->visible) {
        $class = ' class="dimmed"';
    }
    $data[] = "<a$class href=\"view.php?id=$realtimequiz->coursemodule\">" .
            format_string($realtimequiz->name, true) . '</a>';

    // Close date.
    if (($timeclosedates[$realtimequiz->id]->usertimeclose != 0)) {
        $data[] = userdate($timeclosedates[$realtimequiz->id]->usertimeclose);
    } else {
        $data[] = get_string('noclose', 'realtimequiz');
    }

    if ($showing == 'stats') {
        // The $realtimequiz objects returned by get_all_instances_in_course have the necessary $cm
        // fields set to make the following call work.
        $data[] = realtimequiz_attempt_summary_link_to_reports($realtimequiz, $cm, $context);

    } else if ($showing == 'grades') {
        // Grade and feedback.
        $attempts = realtimequiz_get_user_attempts($realtimequiz->id, $USER->id, 'all');
        list($someoptions, $alloptions) = realtimequiz_get_combined_reviewoptions(
                $realtimequiz, $attempts);

        $grade = '';
        $feedback = '';
        if ($realtimequiz->grade && array_key_exists($realtimequiz->id, $grades)) {
            if ($alloptions->marks >= question_display_options::MARK_AND_MAX) {
                $a = new stdClass();
                $a->grade = realtimequiz_format_grade($realtimequiz, $grades[$realtimequiz->id]);
                $a->maxgrade = realtimequiz_format_grade($realtimequiz, $realtimequiz->grade);
                $grade = get_string('outofshort', 'realtimequiz', $a);
            }
            if ($alloptions->overallfeedback) {
                $feedback = realtimequiz_feedback_for_grade($grades[$realtimequiz->id], $realtimequiz, $context);
            }
        }
        $data[] = $grade;
        if ($showfeedback) {
            $data[] = $feedback;
        }
    }

    $table->data[] = $data;
} // End of loop over realtimequiz instances.

// Display the table.
echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();
