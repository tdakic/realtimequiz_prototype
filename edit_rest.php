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
 * Rest endpoint for ajax editing of realtimequiz structure.
 *
 * @package   mod_realtimequiz
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\realtimequiz_settings;

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

// Initialise ALL the incoming parameters here, up front.
$realtimequizid     = required_param('realtimequizid', PARAM_INT);
$class      = required_param('class', PARAM_ALPHA);
$field      = optional_param('field', '', PARAM_ALPHA);
$instanceid = optional_param('instanceId', 0, PARAM_INT);
$sectionid  = optional_param('sectionId', 0, PARAM_INT);
$previousid = optional_param('previousid', 0, PARAM_INT);
$value      = optional_param('value', 0, PARAM_INT);
$column     = optional_param('column', 0, PARAM_ALPHA);
$id         = optional_param('id', 0, PARAM_INT);
$summary    = optional_param('summary', '', PARAM_RAW);
$sequence   = optional_param('sequence', '', PARAM_SEQUENCE);
$visible    = optional_param('visible', 0, PARAM_INT);
$pageaction = optional_param('action', '', PARAM_ALPHA); // Used to simulate a DELETE command.
$maxmark    = optional_param('maxmark', '', PARAM_FLOAT);
$newheading = optional_param('newheading', '', PARAM_TEXT);
$shuffle    = optional_param('newshuffle', 0, PARAM_INT);
$page       = optional_param('page', '', PARAM_INT);
$ids        = optional_param('ids', '', PARAM_SEQUENCE);
$PAGE->set_url('/mod/realtimequiz/edit-rest.php',
        ['realtimequizid' => $realtimequizid, 'class' => $class]);

require_sesskey();
$realtimequizobj = realtimequiz_settings::create($realtimequizid);
$realtimequiz = $realtimequizobj->get_realtimequiz();
$cm = $realtimequizobj->get_cm();
$course = $realtimequizobj->get_course();
require_login($course, false, $cm);

$structure = $realtimequizobj->get_structure();
$gradecalculator = $realtimequizobj->get_grade_calculator();
$modcontext = $realtimequizobj->get_context();

echo $OUTPUT->header(); // Send headers.

// All these AJAX actions should be logically atomic.
$transaction = $DB->start_delegated_transaction();

// OK, now let's process the parameters and do stuff
// MDL-10221 the DELETE method is not allowed on some web servers,
// so we simulate it with the action URL param.
$requestmethod = $_SERVER['REQUEST_METHOD'];
if ($pageaction == 'DELETE') {
    $requestmethod = 'DELETE';
}

$result = null;

switch($requestmethod) {
    case 'POST':
    case 'GET': // For debugging.
        switch ($class) {
            case 'section':
                $table = 'realtimequiz_sections';
                $section = $structure->get_section_by_id($id);
                switch ($field) {
                    case 'getsectiontitle':
                        require_capability('mod/realtimequiz:manage', $modcontext);
                        $result = ['instancesection' => $section->heading];
                        break;
                    case 'updatesectiontitle':
                        require_capability('mod/realtimequiz:manage', $modcontext);
                        $structure->set_section_heading($id, $newheading);
                        $result = ['instancesection' => format_string($newheading)];
                        break;
                    case 'updateshufflequestions':
                        require_capability('mod/realtimequiz:manage', $modcontext);
                        $structure->set_section_shuffle($id, $shuffle);
                        $result = ['instanceshuffle' => $section->shufflequestions];
                        break;
                }
                break;

            case 'resource':
                switch ($field) {
                    case 'move':
                        require_capability('mod/realtimequiz:manage', $modcontext);
                        if (!$previousid) {
                            $section = $structure->get_section_by_id($sectionid);
                            if ($section->firstslot > 1) {
                                $previousid = $structure->get_slot_id_for_slot($section->firstslot - 1);
                                $page = $structure->get_page_number_for_slot($section->firstslot);
                            }
                        }
                        $structure->move_slot($id, $previousid, $page);
                        realtimequiz_delete_previews($realtimequiz);
                        $result = ['visible' => true];
                        break;

                    case 'getmaxmark':
                        require_capability('mod/realtimequiz:manage', $modcontext);
                        $slot = $DB->get_record('realtimequiz_slots', ['id' => $id], '*', MUST_EXIST);
                        $result = ['instancemaxmark' => realtimequiz_format_question_grade($realtimequiz, $slot->maxmark)];
                        break;

                    case 'updatemaxmark':
                        require_capability('mod/realtimequiz:manage', $modcontext);
                        $slot = $structure->get_slot_by_id($id);
                        if ($structure->update_slot_maxmark($slot, $maxmark)) {
                            // Grade has really changed.
                            realtimequiz_delete_previews($realtimequiz);
                            $gradecalculator->recompute_realtimequiz_sumgrades();
                            $gradecalculator->recompute_all_attempt_sumgrades();
                            $gradecalculator->recompute_all_final_grades();
                            realtimequiz_update_grades($realtimequiz, 0, true);
                        }
                        $result = ['instancemaxmark' => realtimequiz_format_question_grade($realtimequiz, $maxmark),
                                'newsummarks' => realtimequiz_format_grade($realtimequiz, $realtimequiz->sumgrades)];
                        break;

                    case 'updatepagebreak':
                        require_capability('mod/realtimequiz:manage', $modcontext);
                        $slots = $structure->update_page_break($id, $value);
                        $json = [];
                        foreach ($slots as $slot) {
                            $json[$slot->slot] = ['id' => $slot->id, 'slot' => $slot->slot,
                                                            'page' => $slot->page];
                        }
                        $result = ['slots' => $json];
                        break;

                    case 'deletemultiple':
                        require_capability('mod/realtimequiz:manage', $modcontext);

                        $ids = explode(',', $ids);
                        foreach ($ids as $id) {
                            $slot = $DB->get_record('realtimequiz_slots', ['realtimequizid' => $realtimequiz->id, 'id' => $id],
                                    '*', MUST_EXIST);
                            if ($structure->has_use_capability($slot->slot)) {
                                $structure->remove_slot($slot->slot);
                            }
                        }
                        realtimequiz_delete_previews($realtimequiz);
                        $gradecalculator->recompute_realtimequiz_sumgrades();

                        $result = ['newsummarks' => realtimequiz_format_grade($realtimequiz, $realtimequiz->sumgrades),
                                'deleted' => true, 'newnumquestions' => $structure->get_question_count()];
                        break;

                    case 'updatedependency':
                        require_capability('mod/realtimequiz:manage', $modcontext);
                        $slot = $structure->get_slot_by_id($id);
                        $value = (bool) $value;
                        $structure->update_question_dependency($slot->id, $value);
                        $result = ['requireprevious' => $value];
                        break;
                }
                break;
        }
        break;

    case 'DELETE':
        switch ($class) {
            case 'section':
                require_capability('mod/realtimequiz:manage', $modcontext);
                $structure->remove_section_heading($id);
                $result = ['deleted' => true];
                break;

            case 'resource':
                require_capability('mod/realtimequiz:manage', $modcontext);
                if (!$slot = $DB->get_record('realtimequiz_slots', ['realtimequizid' => $realtimequiz->id, 'id' => $id])) {
                    throw new moodle_exception('AJAX commands.php: Bad slot ID '.$id);
                }

                if (!$structure->has_use_capability($slot->slot)) {
                    $slotdetail = $structure->get_slot_by_id($slot->id);
                    $context = context::instance_by_id($slotdetail->contextid);
                    throw new required_capability_exception($context,
                        'moodle/question:useall', 'nopermissions', '');
                }
                $structure->remove_slot($slot->slot);
                realtimequiz_delete_previews($realtimequiz);
                $gradecalculator->recompute_realtimequiz_sumgrades();
                $result = ['newsummarks' => realtimequiz_format_grade($realtimequiz, $realtimequiz->sumgrades),
                            'deleted' => true, 'newnumquestions' => $structure->get_question_count()];
                break;
        }
        break;
}

$transaction->allow_commit();
echo json_encode($result);
