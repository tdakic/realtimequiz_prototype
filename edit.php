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
 * Page to edit realtimequizzes
 *
 * This page generally has two columns:
 * The right column lists all available questions in a chosen category and
 * allows them to be edited or more to be added. This column is only there if
 * the realtimequiz does not already have student attempts
 * The left column lists all questions that have been added to the current realtimequiz.
 * The lecturer can add questions from the right hand list to the realtimequiz or remove them
 *
 * The script also processes a number of actions:
 * Actions affecting a realtimequiz:
 * up and down  Changes the order of questions and page breaks
 * addquestion  Adds a single question to the realtimequiz
 * add          Adds several selected questions to the realtimequiz
 * addrandom    Adds a certain number of random questions to the realtimequiz
 * repaginate   Re-paginates the realtimequiz
 * delete       Removes a question from the realtimequiz
 * savechanges  Saves the order and grades for questions in the realtimequiz
 *
 * @package    mod_realtimequiz
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\realtimequiz_settings;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');

$mdlscrollto = optional_param('mdlscrollto', '', PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $realtimequiz, $pagevars) =
        question_edit_setup('editq', '/mod/realtimequiz/edit.php', true);

$PAGE->set_url($thispageurl);
$PAGE->set_secondary_active_tab("mod_realtimequiz_edit");

// You need mod/realtimequiz:manage in addition to question capabilities to access this page.
require_capability('mod/realtimequiz:manage', $contexts->lowest());

// Get the course object and related bits.
$course = get_course($realtimequiz->course);
$realtimequizobj = new realtimequiz_settings($realtimequiz, $cm, $course);
$structure = $realtimequizobj->get_structure();
$gradecalculator = $realtimequizobj->get_grade_calculator();

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$realtimequizhasattempts = realtimequiz_has_attempts($realtimequiz->id);

// Process commands ============================================================.

// Get the list of question ids had their check-boxes ticked.
$selectedslots = [];
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedslots[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    // Re-paginate the realtimequiz.
    $structure->check_can_be_edited();
    $questionsperpage = optional_param('questionsperpage', $realtimequiz->questionsperpage, PARAM_INT);
    realtimequiz_repaginate_questions($realtimequiz->id, $questionsperpage );
    realtimequiz_delete_previews($realtimequiz);
    redirect($afteractionurl);
}

if ($mdlscrollto) {
    $afteractionurl->param('mdlscrollto', $mdlscrollto);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current realtimequiz.
    $structure->check_can_be_edited();
    realtimequiz_require_question_use($addquestion);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    realtimequiz_add_realtimequiz_question($addquestion, $realtimequiz, $addonpage);
    realtimequiz_delete_previews($realtimequiz);
    $gradecalculator->recompute_realtimequiz_sumgrades();
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $structure->check_can_be_edited();
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    // Add selected questions to the current realtimequiz.
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            realtimequiz_require_question_use($key);
            realtimequiz_add_realtimequiz_question($key, $realtimequiz, $addonpage);
        }
    }
    realtimequiz_delete_previews($realtimequiz);
    $gradecalculator->recompute_realtimequiz_sumgrades();
    redirect($afteractionurl);
}

if ($addsectionatpage = optional_param('addsectionatpage', false, PARAM_INT)) {
    // Add a section to the realtimequiz.
    $structure->check_can_be_edited();
    $structure->add_section_heading($addsectionatpage);
    realtimequiz_delete_previews($realtimequiz);
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    // Add random questions to the realtimequiz.
    $structure->check_can_be_edited();
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    realtimequiz_add_random_questions($realtimequiz, $addonpage, $categoryid, $randomcount, $recurse);

    realtimequiz_delete_previews($realtimequiz);
    $gradecalculator->recompute_realtimequiz_sumgrades();
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {

    // If rescaling is required save the new maximum.
    $maxgrade = unformat_float(optional_param('maxgrade', '', PARAM_RAW_TRIMMED), true);
    if (is_float($maxgrade) && $maxgrade >= 0) {
        $gradecalculator->update_realtimequiz_maximum_grade($maxgrade);
    }

    redirect($afteractionurl);
}

// Log this visit.
$event = \mod_realtimequiz\event\edit_page_viewed::create([
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => [
        'realtimequizid' => $realtimequiz->id
    ]
]);
$event->trigger();

// Get the question bank view.
$questionbank = new mod_realtimequiz\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $realtimequiz);
$questionbank->set_realtimequiz_has_attempts($realtimequizhasattempts);

// End of process commands =====================================================.

$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('mod-realtimequiz-edit');

$output = $PAGE->get_renderer('mod_realtimequiz', 'edit');

$PAGE->set_title(get_string('editingrealtimequizx', 'realtimequiz', format_string($realtimequiz->name)));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();
$node = $PAGE->settingsnav->find('mod_realtimequiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}
echo $OUTPUT->header();

// Initialise the JavaScript.
$realtimequizeditconfig = new stdClass();
$realtimequizeditconfig->url = $thispageurl->out(true, ['qbanktool' => '0']);
$realtimequizeditconfig->dialoglisteners = [];
$numberoflisteners = $DB->get_field_sql("
    SELECT COALESCE(MAX(page), 1)
      FROM {realtimequiz_slots}
     WHERE realtimequizid = ?", [$realtimequiz->id]);

for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $realtimequizeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('realtimequiz_edit_config', $realtimequizeditconfig);
$PAGE->requires->js_call_amd('core_question/question_engine');

// Questions wrapper start.
echo html_writer::start_tag('div', ['class' => 'mod-realtimequiz-edit-content']);

echo $output->edit_page($realtimequizobj, $structure, $contexts, $thispageurl, $pagevars);

// Questions wrapper end.
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
