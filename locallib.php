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
 * Library of functions used by the realtimequiz module.
 *
 * This contains functions that are called from within the realtimequiz module only
 * Functions that are also called by core Moodle are in {@link lib.php}
 * This script also loads the code in {@link questionlib.php} which holds
 * the module-indpendent code for handling questions and which in turn
 * initialises all the questiontype classes.
 *
 * @package    mod_realtimequiz
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/realtimequiz/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/questionlib.php');

use mod_realtimequiz\access_manager;
use mod_realtimequiz\event\attempt_submitted;
use mod_realtimequiz\grade_calculator;
use mod_realtimequiz\question\bank\qbank_helper;
use mod_realtimequiz\question\display_options;
use mod_realtimequiz\realtimequiz_attempt;
use mod_realtimequiz\realtimequiz_settings;
use qbank_previewquestion\question_preview_options;

/**
 * @var int We show the countdown timer if there is less than this amount of time left before the
 * the realtimequiz close date. (1 hour)
 */
define('QUIZ_SHOW_TIME_BEFORE_DEADLINE', '3600');

/**
 * @var int If there are fewer than this many seconds left when the student submits
 * a page of the realtimequiz, then do not take them to the next page of the realtimequiz. Instead
 * close the realtimequiz immediately.
 */
define('QUIZ_MIN_TIME_TO_CONTINUE', '2');

/**
 * @var int We show no image when user selects No image from dropdown menu in realtimequiz settings.
 */
define('QUIZ_SHOWIMAGE_NONE', 0);

/**
 * @var int We show small image when user selects small image from dropdown menu in realtimequiz settings.
 */
define('QUIZ_SHOWIMAGE_SMALL', 1);

/**
 * @var int We show Large image when user selects Large image from dropdown menu in realtimequiz settings.
 */
define('QUIZ_SHOWIMAGE_LARGE', 2);


// Functions related to attempts ///////////////////////////////////////////////

/**
 * Creates an object to represent a new attempt at a realtimequiz
 *
 * Creates an attempt object to represent an attempt at the realtimequiz by the current
 * user starting at the current time. The ->id field is not set. The object is
 * NOT written to the database.
 *
 * @param realtimequiz_settings $realtimequizobj the realtimequiz object to create an attempt for.
 * @param int $attemptnumber the sequence number for the attempt.
 * @param stdClass|false $lastattempt the previous attempt by this user, if any. Only needed
 *         if $attemptnumber > 1 and $realtimequiz->attemptonlast is true.
 * @param int $timenow the time the attempt was started at.
 * @param bool $ispreview whether this new attempt is a preview.
 * @param int|null $userid  the id of the user attempting this realtimequiz.
 *
 * @return stdClass the newly created attempt object.
 */
function realtimequiz_create_attempt(realtimequiz_settings $realtimequizobj, $attemptnumber, $lastattempt, $timenow, $ispreview = false, $userid = null) {
    global $USER;

    if ($userid === null) {
        $userid = $USER->id;
    }

    $realtimequiz = $realtimequizobj->get_realtimequiz();
    if ($realtimequiz->sumgrades < grade_calculator::ALMOST_ZERO && $realtimequiz->grade > grade_calculator::ALMOST_ZERO) {
        throw new moodle_exception('cannotstartgradesmismatch', 'realtimequiz',
                new moodle_url('/mod/realtimequiz/view.php', ['q' => $realtimequiz->id]),
                    ['grade' => realtimequiz_format_grade($realtimequiz, $realtimequiz->grade)]);
    }

    if ($attemptnumber == 1 || !$realtimequiz->attemptonlast) {
        // We are not building on last attempt so create a new attempt.
        $attempt = new stdClass();
        $attempt->realtimequiz = $realtimequiz->id;
        $attempt->userid = $userid;
        $attempt->preview = 0;
        $attempt->layout = '';
    } else {
        // Build on last attempt.
        if (empty($lastattempt)) {
            throw new \moodle_exception('cannotfindprevattempt', 'realtimequiz');
        }
        $attempt = $lastattempt;
    }

    $attempt->attempt = $attemptnumber;
    $attempt->timestart = $timenow;
    $attempt->timefinish = 0;
    $attempt->timemodified = $timenow;
    $attempt->timemodifiedoffline = 0;
    $attempt->state = realtimequiz_attempt::IN_PROGRESS;
    $attempt->currentpage = 0;
    $attempt->sumgrades = null;
    $attempt->gradednotificationsenttime = null;

    // If this is a preview, mark it as such.
    if ($ispreview) {
        $attempt->preview = 1;
    }

    $timeclose = $realtimequizobj->get_access_manager($timenow)->get_end_time($attempt);
    if ($timeclose === false || $ispreview) {
        $attempt->timecheckstate = null;
    } else {
        $attempt->timecheckstate = $timeclose;
    }

    return $attempt;
}
/**
 * Start a normal, new, realtimequiz attempt.
 *
 * @param realtimequiz_settings $realtimequizobj        the realtimequiz object to start an attempt for.
 * @param question_usage_by_activity $quba
 * @param stdClass    $attempt
 * @param integer   $attemptnumber      starting from 1
 * @param integer   $timenow            the attempt start time
 * @param array     $questionids        slot number => question id. Used for random questions, to force the choice
 *                                      of a particular actual question. Intended for testing purposes only.
 * @param array     $forcedvariantsbyslot slot number => variant. Used for questions with variants,
 *                                        to force the choice of a particular variant. Intended for testing
 *                                        purposes only.
 * @return stdClass   modified attempt object
 */
function realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, $attemptnumber, $timenow,
                                $questionids = [], $forcedvariantsbyslot = []) {

    // Usages for this user's previous realtimequiz attempts.
    $qubaids = new \mod_realtimequiz\question\qubaids_for_users_attempts(
            $realtimequizobj->get_realtimequizid(), $attempt->userid);

    // Fully load all the questions in this realtimequiz.
    $realtimequizobj->preload_questions();
    $realtimequizobj->load_questions();

    // First load all the non-random questions.
    $randomfound = false;
    $slot = 0;
    $questions = [];
    $maxmark = [];
    $page = [];
    foreach ($realtimequizobj->get_questions() as $questiondata) {
        $slot += 1;
        $maxmark[$slot] = $questiondata->maxmark;
        $page[$slot] = $questiondata->page;
        if ($questiondata->status == \core_question\local\bank\question_version_status::QUESTION_STATUS_DRAFT) {
            throw new moodle_exception('questiondraftonly', 'mod_realtimequiz', '', $questiondata->name);
        }
        if ($questiondata->qtype == 'random') {
            $randomfound = true;
            continue;
        }
        if (!$realtimequizobj->get_realtimequiz()->shuffleanswers) {
            $questiondata->options->shuffleanswers = false;
        }
        $questions[$slot] = question_bank::make_question($questiondata);
    }

    // Then find a question to go in place of each random question.
    if ($randomfound) {
        $slot = 0;
        $usedquestionids = [];
        foreach ($questions as $question) {
            if ($question->id && isset($usedquestions[$question->id])) {
                $usedquestionids[$question->id] += 1;
            } else {
                $usedquestionids[$question->id] = 1;
            }
        }
        $randomloader = new \core_question\local\bank\random_question_loader($qubaids, $usedquestionids);

        foreach ($realtimequizobj->get_questions() as $questiondata) {
            $slot += 1;
            if ($questiondata->qtype != 'random') {
                continue;
            }

            $tagids = qbank_helper::get_tag_ids_for_slot($questiondata);

            // Deal with fixed random choices for testing.
            if (isset($questionids[$quba->next_slot_number()])) {
                if ($randomloader->is_question_available($questiondata->category,
                        (bool) $questiondata->questiontext, $questionids[$quba->next_slot_number()], $tagids)) {
                    $questions[$slot] = question_bank::load_question(
                            $questionids[$quba->next_slot_number()], $realtimequizobj->get_realtimequiz()->shuffleanswers);
                    continue;
                } else {
                    throw new coding_exception('Forced question id not available.');
                }
            }

            // Normal case, pick one at random.
            $questionid = $randomloader->get_next_question_id($questiondata->category,
                    $questiondata->randomrecurse, $tagids);
            if ($questionid === null) {
                throw new moodle_exception('notenoughrandomquestions', 'realtimequiz',
                                           $realtimequizobj->view_url(), $questiondata);
            }

            $questions[$slot] = question_bank::load_question($questionid,
                    $realtimequizobj->get_realtimequiz()->shuffleanswers);
        }
    }

    // Finally add them all to the usage.
    ksort($questions);
    foreach ($questions as $slot => $question) {
        $newslot = $quba->add_question($question, $maxmark[$slot]);
        if ($newslot != $slot) {
            throw new coding_exception('Slot numbers have got confused.');
        }
    }

    // Start all the questions.
    $variantstrategy = new core_question\engine\variants\least_used_strategy($quba, $qubaids);

    if (!empty($forcedvariantsbyslot)) {
        $forcedvariantsbyseed = question_variant_forced_choices_selection_strategy::prepare_forced_choices_array(
            $forcedvariantsbyslot, $quba);
        $variantstrategy = new question_variant_forced_choices_selection_strategy(
            $forcedvariantsbyseed, $variantstrategy);
    }

    $quba->start_all_questions($variantstrategy, $timenow, $attempt->userid);

    // Work out the attempt layout.
    $sections = $realtimequizobj->get_sections();
    foreach ($sections as $i => $section) {
        if (isset($sections[$i + 1])) {
            $sections[$i]->lastslot = $sections[$i + 1]->firstslot - 1;
        } else {
            $sections[$i]->lastslot = count($questions);
        }
    }

    $layout = [];
    foreach ($sections as $section) {
        if ($section->shufflequestions) {
            $questionsinthissection = [];
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                $questionsinthissection[] = $slot;
            }
            shuffle($questionsinthissection);
            $questionsonthispage = 0;
            foreach ($questionsinthissection as $slot) {
                if ($questionsonthispage && $questionsonthispage == $realtimequizobj->get_realtimequiz()->questionsperpage) {
                    $layout[] = 0;
                    $questionsonthispage = 0;
                }
                $layout[] = $slot;
                $questionsonthispage += 1;
            }

        } else {
            $currentpage = $page[$section->firstslot];
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                if ($currentpage !== null && $page[$slot] != $currentpage) {
                    $layout[] = 0;
                }
                $layout[] = $slot;
                $currentpage = $page[$slot];
            }
        }

        // Each section ends with a page break.
        $layout[] = 0;
    }
    $attempt->layout = implode(',', $layout);

    return $attempt;
}

/**
 * Start a subsequent new attempt, in each attempt builds on last mode.
 *
 * @param question_usage_by_activity    $quba         this question usage
 * @param stdClass                        $attempt      this attempt
 * @param stdClass                        $lastattempt  last attempt
 * @return stdClass                       modified attempt object
 *
 */
function realtimequiz_start_attempt_built_on_last($quba, $attempt, $lastattempt) {
    $oldquba = question_engine::load_questions_usage_by_activity($lastattempt->uniqueid);

    $oldnumberstonew = [];
    foreach ($oldquba->get_attempt_iterator() as $oldslot => $oldqa) {
        $question = $oldqa->get_question(false);
        if ($question->status == \core_question\local\bank\question_version_status::QUESTION_STATUS_DRAFT) {
            throw new moodle_exception('questiondraftonly', 'mod_realtimequiz', '', $question->name);
        }
        $newslot = $quba->add_question($question, $oldqa->get_max_mark());

        $quba->start_question_based_on($newslot, $oldqa);

        $oldnumberstonew[$oldslot] = $newslot;
    }

    // Update attempt layout.
    $newlayout = [];
    foreach (explode(',', $lastattempt->layout) as $oldslot) {
        if ($oldslot != 0) {
            $newlayout[] = $oldnumberstonew[$oldslot];
        } else {
            $newlayout[] = 0;
        }
    }
    $attempt->layout = implode(',', $newlayout);
    return $attempt;
}

/**
 * The save started question usage and realtimequiz attempt in db and log the started attempt.
 *
 * @param realtimequiz_settings $realtimequizobj
 * @param question_usage_by_activity $quba
 * @param stdClass                     $attempt
 * @return stdClass                    attempt object with uniqueid and id set.
 */
function realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt) {
    global $DB;
    // Save the attempt in the database.
    question_engine::save_questions_usage_by_activity($quba);
    $attempt->uniqueid = $quba->get_id();
    $attempt->id = $DB->insert_record('realtimequiz_attempts', $attempt);

    // Params used by the events below.
    $params = [
        'objectid' => $attempt->id,
        'relateduserid' => $attempt->userid,
        'courseid' => $realtimequizobj->get_courseid(),
        'context' => $realtimequizobj->get_context()
    ];
    // Decide which event we are using.
    if ($attempt->preview) {
        $params['other'] = [
            'realtimequizid' => $realtimequizobj->get_realtimequizid()
        ];
        $event = \mod_realtimequiz\event\attempt_preview_started::create($params);
    } else {
        $event = \mod_realtimequiz\event\attempt_started::create($params);

    }

    // Trigger the event.
    $event->add_record_snapshot('realtimequiz', $realtimequizobj->get_realtimequiz());
    $event->add_record_snapshot('realtimequiz_attempts', $attempt);
    $event->trigger();

    return $attempt;
}

/**
 * Returns an unfinished attempt (if there is one) for the given
 * user on the given realtimequiz. This function does not return preview attempts.
 *
 * @param int $realtimequizid the id of the realtimequiz.
 * @param int $userid the id of the user.
 *
 * @return mixed the unfinished attempt if there is one, false if not.
 */
function realtimequiz_get_user_attempt_unfinished($realtimequizid, $userid) {
    $attempts = realtimequiz_get_user_attempts($realtimequizid, $userid, 'unfinished', true);
    if ($attempts) {
        return array_shift($attempts);
    } else {
        return false;
    }
}

/**
 * Delete a realtimequiz attempt.
 * @param mixed $attempt an integer attempt id or an attempt object
 *      (row of the realtimequiz_attempts table).
 * @param stdClass $realtimequiz the realtimequiz object.
 */
function realtimequiz_delete_attempt($attempt, $realtimequiz) {
    global $DB;
    if (is_numeric($attempt)) {
        if (!$attempt = $DB->get_record('realtimequiz_attempts', ['id' => $attempt])) {
            return;
        }
    }

    if ($attempt->realtimequiz != $realtimequiz->id) {
        debugging("Trying to delete attempt $attempt->id which belongs to realtimequiz $attempt->realtimequiz " .
                "but was passed realtimequiz $realtimequiz->id.");
        return;
    }

    if (!isset($realtimequiz->cmid)) {
        $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id, $realtimequiz->course);
        $realtimequiz->cmid = $cm->id;
    }

    question_engine::delete_questions_usage_by_activity($attempt->uniqueid);
    $DB->delete_records('realtimequiz_attempts', ['id' => $attempt->id]);

    // Log the deletion of the attempt if not a preview.
    if (!$attempt->preview) {
        $params = [
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'context' => context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id
            ]
        ];
        $event = \mod_realtimequiz\event\attempt_deleted::create($params);
        $event->add_record_snapshot('realtimequiz_attempts', $attempt);
        $event->trigger();
    }

    // Search realtimequiz_attempts for other instances by this user.
    // If none, then delete record for this realtimequiz, this user from realtimequiz_grades
    // else recalculate best grade.
    $userid = $attempt->userid;
    $gradecalculator = realtimequiz_settings::create($realtimequiz->id)->get_grade_calculator();
    if (!$DB->record_exists('realtimequiz_attempts', ['userid' => $userid, 'realtimequiz' => $realtimequiz->id])) {
        $DB->delete_records('realtimequiz_grades', ['userid' => $userid, 'realtimequiz' => $realtimequiz->id]);
    } else {
        $gradecalculator->recompute_final_grade($userid);
    }

    realtimequiz_update_grades($realtimequiz, $userid);
}

/**
 * Delete all the preview attempts at a realtimequiz, or possibly all the attempts belonging
 * to one user.
 * @param stdClass $realtimequiz the realtimequiz object.
 * @param int $userid (optional) if given, only delete the previews belonging to this user.
 */
function realtimequiz_delete_previews($realtimequiz, $userid = null) {
    global $DB;
    $conditions = ['realtimequiz' => $realtimequiz->id, 'preview' => 1];
    if (!empty($userid)) {
        $conditions['userid'] = $userid;
    }
    $previewattempts = $DB->get_records('realtimequiz_attempts', $conditions);
    foreach ($previewattempts as $attempt) {
        realtimequiz_delete_attempt($attempt, $realtimequiz);
    }
}

/**
 * @param int $realtimequizid The realtimequiz id.
 * @return bool whether this realtimequiz has any (non-preview) attempts.
 */
function realtimequiz_has_attempts($realtimequizid) {
    global $DB;
    return $DB->record_exists('realtimequiz_attempts', ['realtimequiz' => $realtimequizid, 'preview' => 0]);
}

// Functions to do with realtimequiz layout and pages //////////////////////////////////

/**
 * Repaginate the questions in a realtimequiz
 * @param int $realtimequizid the id of the realtimequiz to repaginate.
 * @param int $slotsperpage number of items to put on each page. 0 means unlimited.
 */
function realtimequiz_repaginate_questions($realtimequizid, $slotsperpage) {
    global $DB;
    $trans = $DB->start_delegated_transaction();

    $sections = $DB->get_records('realtimequiz_sections', ['realtimequizid' => $realtimequizid], 'firstslot ASC');
    $firstslots = [];
    foreach ($sections as $section) {
        if ((int)$section->firstslot === 1) {
            continue;
        }
        $firstslots[] = $section->firstslot;
    }

    $slots = $DB->get_records('realtimequiz_slots', ['realtimequizid' => $realtimequizid],
            'slot');
    $currentpage = 1;
    $slotsonthispage = 0;
    foreach ($slots as $slot) {
        if (($firstslots && in_array($slot->slot, $firstslots)) ||
            ($slotsonthispage && $slotsonthispage == $slotsperpage)) {
            $currentpage += 1;
            $slotsonthispage = 0;
        }
        if ($slot->page != $currentpage) {
            $DB->set_field('realtimequiz_slots', 'page', $currentpage, ['id' => $slot->id]);
        }
        $slotsonthispage += 1;
    }

    $trans->allow_commit();

    // Log realtimequiz re-paginated event.
    $cm = get_coursemodule_from_instance('realtimequiz', $realtimequizid);
    $event = \mod_realtimequiz\event\realtimequiz_repaginated::create([
        'context' => \context_module::instance($cm->id),
        'objectid' => $realtimequizid,
        'other' => [
            'slotsperpage' => $slotsperpage
        ]
    ]);
    $event->trigger();

}

// Functions to do with realtimequiz grades ////////////////////////////////////////////
// Note a lot of logic related to this is now in the grade_calculator class.

/**
 * Convert the raw grade stored in $attempt into a grade out of the maximum
 * grade for this realtimequiz.
 *
 * @param float $rawgrade the unadjusted grade, fof example $attempt->sumgrades
 * @param stdClass $realtimequiz the realtimequiz object. Only the fields grade, sumgrades and decimalpoints are used.
 * @param bool|string $format whether to format the results for display
 *      or 'question' to format a question grade (different number of decimal places.
 * @return float|string the rescaled grade, or null/the lang string 'notyetgraded'
 *      if the $grade is null.
 */
function realtimequiz_rescale_grade($rawgrade, $realtimequiz, $format = true) {
    if (is_null($rawgrade)) {
        $grade = null;
    } else if ($realtimequiz->sumgrades >= grade_calculator::ALMOST_ZERO) {
        $grade = $rawgrade * $realtimequiz->grade / $realtimequiz->sumgrades;
    } else {
        $grade = 0;
    }
    if ($format === 'question') {
        $grade = realtimequiz_format_question_grade($realtimequiz, $grade);
    } else if ($format) {
        $grade = realtimequiz_format_grade($realtimequiz, $grade);
    }
    return $grade;
}

/**
 * Get the feedback object for this grade on this realtimequiz.
 *
 * @param float $grade a grade on this realtimequiz.
 * @param stdClass $realtimequiz the realtimequiz settings.
 * @return false|stdClass the record object or false if there is not feedback for the given grade
 * @since  Moodle 3.1
 */
function realtimequiz_feedback_record_for_grade($grade, $realtimequiz) {
    global $DB;

    // With CBM etc, it is possible to get -ve grades, which would then not match
    // any feedback. Therefore, we replace -ve grades with 0.
    $grade = max($grade, 0);

    $feedback = $DB->get_record_select('realtimequiz_feedback',
            'realtimequizid = ? AND mingrade <= ? AND ? < maxgrade', [$realtimequiz->id, $grade, $grade]);

    return $feedback;
}

/**
 * Get the feedback text that should be show to a student who
 * got this grade on this realtimequiz. The feedback is processed ready for diplay.
 *
 * @param float $grade a grade on this realtimequiz.
 * @param stdClass $realtimequiz the realtimequiz settings.
 * @param context_module $context the realtimequiz context.
 * @return string the comment that corresponds to this grade (empty string if there is not one.
 */
function realtimequiz_feedback_for_grade($grade, $realtimequiz, $context) {

    if (is_null($grade)) {
        return '';
    }

    $feedback = realtimequiz_feedback_record_for_grade($grade, $realtimequiz);

    if (empty($feedback->feedbacktext)) {
        return '';
    }

    // Clean the text, ready for display.
    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $feedbacktext = file_rewrite_pluginfile_urls($feedback->feedbacktext, 'pluginfile.php',
            $context->id, 'mod_realtimequiz', 'feedback', $feedback->id);
    $feedbacktext = format_text($feedbacktext, $feedback->feedbacktextformat, $formatoptions);

    return $feedbacktext;
}

/**
 * @param stdClass $realtimequiz the realtimequiz database row.
 * @return bool Whether this realtimequiz has any non-blank feedback text.
 */
function realtimequiz_has_feedback($realtimequiz) {
    global $DB;
    static $cache = [];
    if (!array_key_exists($realtimequiz->id, $cache)) {
        $cache[$realtimequiz->id] = realtimequiz_has_grades($realtimequiz) &&
                $DB->record_exists_select('realtimequiz_feedback', "realtimequizid = ? AND " .
                    $DB->sql_isnotempty('realtimequiz_feedback', 'feedbacktext', false, true),
                [$realtimequiz->id]);
    }
    return $cache[$realtimequiz->id];
}

/**
 * Return summary of the number of settings override that exist.
 *
 * To get a nice display of this, see the realtimequiz_override_summary_links()
 * realtimequiz renderer method.
 *
 * @param stdClass $realtimequiz the realtimequiz settings. Only $realtimequiz->id is used at the moment.
 * @param cm_info|stdClass $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *      (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return array like 'group' => 3, 'user' => 12] where 3 is the number of group overrides,
 *      and 12 is the number of user ones.
 */
function realtimequiz_override_summary(stdClass $realtimequiz, cm_info|stdClass $cm, int $currentgroup = 0): array {
    global $DB;

    if ($currentgroup) {
        // Currently only interested in one group.
        $groupcount = $DB->count_records('realtimequiz_overrides', ['realtimequiz' => $realtimequiz->id, 'groupid' => $currentgroup]);
        $usercount = $DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {realtimequiz_overrides} o
                  JOIN {groups_members} gm ON o.userid = gm.userid
                 WHERE o.realtimequiz = ?
                   AND gm.groupid = ?
                    ", [$realtimequiz->id, $currentgroup]);
        return ['group' => $groupcount, 'user' => $usercount, 'mode' => 'onegroup'];
    }

    $realtimequizgroupmode = groups_get_activity_groupmode($cm);
    $accessallgroups = ($realtimequizgroupmode == NOGROUPS) ||
            has_capability('moodle/site:accessallgroups', context_module::instance($cm->id));

    if ($accessallgroups) {
        // User can see all groups.
        $groupcount = $DB->count_records_select('realtimequiz_overrides',
                'realtimequiz = ? AND groupid IS NOT NULL', [$realtimequiz->id]);
        $usercount = $DB->count_records_select('realtimequiz_overrides',
                'realtimequiz = ? AND userid IS NOT NULL', [$realtimequiz->id]);
        return ['group' => $groupcount, 'user' => $usercount, 'mode' => 'allgroups'];

    } else {
        // User can only see groups they are in.
        $groups = groups_get_activity_allowed_groups($cm);
        if (!$groups) {
            return ['group' => 0, 'user' => 0, 'mode' => 'somegroups'];
        }

        list($groupidtest, $params) = $DB->get_in_or_equal(array_keys($groups));
        $params[] = $realtimequiz->id;

        $groupcount = $DB->count_records_select('realtimequiz_overrides',
                "groupid $groupidtest AND realtimequiz = ?", $params);
        $usercount = $DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {realtimequiz_overrides} o
                  JOIN {groups_members} gm ON o.userid = gm.userid
                 WHERE gm.groupid $groupidtest
                   AND o.realtimequiz = ?
               ", $params);

        return ['group' => $groupcount, 'user' => $usercount, 'mode' => 'somegroups'];
    }
}

/**
 * Efficiently update check state time on all open attempts
 *
 * @param array $conditions optional restrictions on which attempts to update
 *                    Allowed conditions:
 *                      courseid => (array|int) attempts in given course(s)
 *                      userid   => (array|int) attempts for given user(s)
 *                      realtimequizid   => (array|int) attempts in given realtimequiz(s)
 *                      groupid  => (array|int) realtimequizzes with some override for given group(s)
 *
 */
function realtimequiz_update_open_attempts(array $conditions) {
    global $DB;

    foreach ($conditions as &$value) {
        if (!is_array($value)) {
            $value = [$value];
        }
    }

    $params = [];
    $wheres = ["realtimequiza.state IN ('inprogress', 'overdue')"];
    $iwheres = ["irealtimequiza.state IN ('inprogress', 'overdue')"];

    if (isset($conditions['courseid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'cid');
        $params = array_merge($params, $inparams);
        $wheres[] = "realtimequiza.realtimequiz IN (SELECT q.id FROM {realtimequiz} q WHERE q.course $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'icid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "irealtimequiza.realtimequiz IN (SELECT q.id FROM {realtimequiz} q WHERE q.course $incond)";
    }

    if (isset($conditions['userid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'uid');
        $params = array_merge($params, $inparams);
        $wheres[] = "realtimequiza.userid $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'iuid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "irealtimequiza.userid $incond";
    }

    if (isset($conditions['realtimequizid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['realtimequizid'], SQL_PARAMS_NAMED, 'qid');
        $params = array_merge($params, $inparams);
        $wheres[] = "realtimequiza.realtimequiz $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['realtimequizid'], SQL_PARAMS_NAMED, 'iqid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "irealtimequiza.realtimequiz $incond";
    }

    if (isset($conditions['groupid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'gid');
        $params = array_merge($params, $inparams);
        $wheres[] = "realtimequiza.realtimequiz IN (SELECT qo.realtimequiz FROM {realtimequiz_overrides} qo WHERE qo.groupid $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'igid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "irealtimequiza.realtimequiz IN (SELECT qo.realtimequiz FROM {realtimequiz_overrides} qo WHERE qo.groupid $incond)";
    }

    // SQL to compute timeclose and timelimit for each attempt:
    $realtimequizausersql = realtimequiz_get_attempt_usertime_sql(
            implode("\n                AND ", $iwheres));

    // SQL to compute the new timecheckstate
    $timecheckstatesql = "
          CASE WHEN realtimequizauser.usertimelimit = 0 AND realtimequizauser.usertimeclose = 0 THEN NULL
               WHEN realtimequizauser.usertimelimit = 0 THEN realtimequizauser.usertimeclose
               WHEN realtimequizauser.usertimeclose = 0 THEN realtimequiza.timestart + realtimequizauser.usertimelimit
               WHEN realtimequiza.timestart + realtimequizauser.usertimelimit < realtimequizauser.usertimeclose THEN realtimequiza.timestart + realtimequizauser.usertimelimit
               ELSE realtimequizauser.usertimeclose END +
          CASE WHEN realtimequiza.state = 'overdue' THEN realtimequiz.graceperiod ELSE 0 END";

    // SQL to select which attempts to process
    $attemptselect = implode("\n                         AND ", $wheres);

   /*
    * Each database handles updates with inner joins differently:
    *  - mysql does not allow a FROM clause
    *  - postgres and mssql allow FROM but handle table aliases differently
    *  - oracle requires a subquery
    *
    * Different code for each database.
    */

    $dbfamily = $DB->get_dbfamily();
    if ($dbfamily == 'mysql') {
        $updatesql = "UPDATE {realtimequiz_attempts} realtimequiza
                        JOIN {realtimequiz} realtimequiz ON realtimequiz.id = realtimequiza.realtimequiz
                        JOIN ( $realtimequizausersql ) realtimequizauser ON realtimequizauser.id = realtimequiza.id
                         SET realtimequiza.timecheckstate = $timecheckstatesql
                       WHERE $attemptselect";
    } else if ($dbfamily == 'postgres') {
        $updatesql = "UPDATE {realtimequiz_attempts} realtimequiza
                         SET timecheckstate = $timecheckstatesql
                        FROM {realtimequiz} realtimequiz, ( $realtimequizausersql ) realtimequizauser
                       WHERE realtimequiz.id = realtimequiza.realtimequiz
                         AND realtimequizauser.id = realtimequiza.id
                         AND $attemptselect";
    } else if ($dbfamily == 'mssql') {
        $updatesql = "UPDATE realtimequiza
                         SET timecheckstate = $timecheckstatesql
                        FROM {realtimequiz_attempts} realtimequiza
                        JOIN {realtimequiz} realtimequiz ON realtimequiz.id = realtimequiza.realtimequiz
                        JOIN ( $realtimequizausersql ) realtimequizauser ON realtimequizauser.id = realtimequiza.id
                       WHERE $attemptselect";
    } else {
        // oracle, sqlite and others
        $updatesql = "UPDATE {realtimequiz_attempts} realtimequiza
                         SET timecheckstate = (
                           SELECT $timecheckstatesql
                             FROM {realtimequiz} realtimequiz, ( $realtimequizausersql ) realtimequizauser
                            WHERE realtimequiz.id = realtimequiza.realtimequiz
                              AND realtimequizauser.id = realtimequiza.id
                         )
                         WHERE $attemptselect";
    }

    $DB->execute($updatesql, $params);
}

/**
 * Returns SQL to compute timeclose and timelimit for every attempt, taking into account user and group overrides.
 * The query used herein is very similar to the one in function realtimequiz_get_user_timeclose, so, in case you
 * would change either one of them, make sure to apply your changes to both.
 *
 * @param string $redundantwhereclauses extra where clauses to add to the subquery
 *      for performance. These can use the table alias irealtimequiza for the realtimequiz attempts table.
 * @return string SQL select with columns attempt.id, usertimeclose, usertimelimit.
 */
function realtimequiz_get_attempt_usertime_sql($redundantwhereclauses = '') {
    if ($redundantwhereclauses) {
        $redundantwhereclauses = 'WHERE ' . $redundantwhereclauses;
    }
    // The multiple qgo JOINS are necessary because we want timeclose/timelimit = 0 (unlimited) to supercede
    // any other group override
    $realtimequizausersql = "
          SELECT irealtimequiza.id,
           COALESCE(MAX(quo.timeclose), MAX(qgo1.timeclose), MAX(qgo2.timeclose), irealtimequiz.timeclose) AS usertimeclose,
           COALESCE(MAX(quo.timelimit), MAX(qgo3.timelimit), MAX(qgo4.timelimit), irealtimequiz.timelimit) AS usertimelimit

           FROM {realtimequiz_attempts} irealtimequiza
           JOIN {realtimequiz} irealtimequiz ON irealtimequiz.id = irealtimequiza.realtimequiz
      LEFT JOIN {realtimequiz_overrides} quo ON quo.realtimequiz = irealtimequiza.realtimequiz AND quo.userid = irealtimequiza.userid
      LEFT JOIN {groups_members} gm ON gm.userid = irealtimequiza.userid
      LEFT JOIN {realtimequiz_overrides} qgo1 ON qgo1.realtimequiz = irealtimequiza.realtimequiz AND qgo1.groupid = gm.groupid AND qgo1.timeclose = 0
      LEFT JOIN {realtimequiz_overrides} qgo2 ON qgo2.realtimequiz = irealtimequiza.realtimequiz AND qgo2.groupid = gm.groupid AND qgo2.timeclose > 0
      LEFT JOIN {realtimequiz_overrides} qgo3 ON qgo3.realtimequiz = irealtimequiza.realtimequiz AND qgo3.groupid = gm.groupid AND qgo3.timelimit = 0
      LEFT JOIN {realtimequiz_overrides} qgo4 ON qgo4.realtimequiz = irealtimequiza.realtimequiz AND qgo4.groupid = gm.groupid AND qgo4.timelimit > 0
          $redundantwhereclauses
       GROUP BY irealtimequiza.id, irealtimequiz.id, irealtimequiz.timeclose, irealtimequiz.timelimit";
    return $realtimequizausersql;
}

/**
 * @return array int => lang string the options for calculating the realtimequiz grade
 *      from the individual attempt grades.
 */
function realtimequiz_get_grading_options() {
    return [
        QUIZ_GRADEHIGHEST => get_string('gradehighest', 'realtimequiz'),
        QUIZ_GRADEAVERAGE => get_string('gradeaverage', 'realtimequiz'),
        QUIZ_ATTEMPTFIRST => get_string('attemptfirst', 'realtimequiz'),
        QUIZ_ATTEMPTLAST  => get_string('attemptlast', 'realtimequiz')
    ];
}

/**
 * @param int $option one of the values QUIZ_GRADEHIGHEST, QUIZ_GRADEAVERAGE,
 *      QUIZ_ATTEMPTFIRST or QUIZ_ATTEMPTLAST.
 * @return the lang string for that option.
 */
function realtimequiz_get_grading_option_name($option) {
    $strings = realtimequiz_get_grading_options();
    return $strings[$option];
}

/**
 * @return array string => lang string the options for handling overdue realtimequiz
 *      attempts.
 */
function realtimequiz_get_overdue_handling_options() {
    return [
        'autosubmit'  => get_string('overduehandlingautosubmit', 'realtimequiz'),
        'graceperiod' => get_string('overduehandlinggraceperiod', 'realtimequiz'),
        'autoabandon' => get_string('overduehandlingautoabandon', 'realtimequiz'),
    ];
}

/**
 * Get the choices for what size user picture to show.
 * @return array string => lang string the options for whether to display the user's picture.
 */
function realtimequiz_get_user_image_options() {
    return [
        QUIZ_SHOWIMAGE_NONE  => get_string('shownoimage', 'realtimequiz'),
        QUIZ_SHOWIMAGE_SMALL => get_string('showsmallimage', 'realtimequiz'),
        QUIZ_SHOWIMAGE_LARGE => get_string('showlargeimage', 'realtimequiz'),
    ];
}

/**
 * Return an user's timeclose for all realtimequizzes in a course, hereby taking into account group and user overrides.
 *
 * @param int $courseid the course id.
 * @return stdClass An object with of all realtimequizids and close unixdates in this course, taking into account the most lenient
 * overrides, if existing and 0 if no close date is set.
 */
function realtimequiz_get_user_timeclose($courseid) {
    global $DB, $USER;

    // For teacher and manager/admins return timeclose.
    if (has_capability('moodle/course:update', context_course::instance($courseid))) {
        $sql = "SELECT realtimequiz.id, realtimequiz.timeclose AS usertimeclose
                  FROM {realtimequiz} realtimequiz
                 WHERE realtimequiz.course = :courseid";

        $results = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        return $results;
    }

    $sql = "SELECT q.id,
  COALESCE(v.userclose, v.groupclose, q.timeclose, 0) AS usertimeclose
  FROM (
      SELECT realtimequiz.id as realtimequizid,
             MAX(quo.timeclose) AS userclose, MAX(qgo.timeclose) AS groupclose
       FROM {realtimequiz} realtimequiz
  LEFT JOIN {realtimequiz_overrides} quo on realtimequiz.id = quo.realtimequiz AND quo.userid = :userid
  LEFT JOIN {groups_members} gm ON gm.userid = :useringroupid
  LEFT JOIN {realtimequiz_overrides} qgo on realtimequiz.id = qgo.realtimequiz AND qgo.groupid = gm.groupid
      WHERE realtimequiz.course = :courseid
   GROUP BY realtimequiz.id) v
       JOIN {realtimequiz} q ON q.id = v.realtimequizid";

    $results = $DB->get_records_sql($sql, ['userid' => $USER->id, 'useringroupid' => $USER->id, 'courseid' => $courseid]);
    return $results;

}

/**
 * Get the choices to offer for the 'Questions per page' option.
 * @return array int => string.
 */
function realtimequiz_questions_per_page_options() {
    $pageoptions = [];
    $pageoptions[0] = get_string('neverallononepage', 'realtimequiz');
    $pageoptions[1] = get_string('everyquestion', 'realtimequiz');
    for ($i = 2; $i <= QUIZ_MAX_QPP_OPTION; ++$i) {
        $pageoptions[$i] = get_string('everynquestions', 'realtimequiz', $i);
    }
    return $pageoptions;
}

/**
 * Get the human-readable name for a realtimequiz attempt state.
 * @param string $state one of the state constants like {@see realtimequiz_attempt::IN_PROGRESS}.
 * @return string The lang string to describe that state.
 */
function realtimequiz_attempt_state_name($state) {
    switch ($state) {
        case realtimequiz_attempt::IN_PROGRESS:
            return get_string('stateinprogress', 'realtimequiz');
        case realtimequiz_attempt::OVERDUE:
            return get_string('stateoverdue', 'realtimequiz');
        case realtimequiz_attempt::FINISHED:
            return get_string('statefinished', 'realtimequiz');
        case realtimequiz_attempt::ABANDONED:
            return get_string('stateabandoned', 'realtimequiz');
        default:
            throw new coding_exception('Unknown realtimequiz attempt state.');
    }
}

// Other realtimequiz functions ////////////////////////////////////////////////////////

/**
 * @param stdClass $realtimequiz the realtimequiz.
 * @param int $cmid the course_module object for this realtimequiz.
 * @param stdClass $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param int $variant which question variant to preview (optional).
 * @return string html for a number of icons linked to action pages for a
 * question - preview and edit / view icons depending on user capabilities.
 */
function realtimequiz_question_action_icons($realtimequiz, $cmid, $question, $returnurl, $variant = null) {
    $html = '';
    if ($question->qtype !== 'random') {
        $html = realtimequiz_question_preview_button($realtimequiz, $question, false, $variant);
    }
    $html .= realtimequiz_question_edit_button($cmid, $question, $returnurl);
    return $html;
}

/**
 * @param int $cmid the course_module.id for this realtimequiz.
 * @param stdClass $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param string $contentbeforeicon some HTML content to be added inside the link, before the icon.
 * @return the HTML for an edit icon, view icon, or nothing for a question
 *      (depending on permissions).
 */
function realtimequiz_question_edit_button($cmid, $question, $returnurl, $contentaftericon = '') {
    global $CFG, $OUTPUT;

    // Minor efficiency saving. Only get strings once, even if there are a lot of icons on one page.
    static $stredit = null;
    static $strview = null;
    if ($stredit === null) {
        $stredit = get_string('edit');
        $strview = get_string('view');
    }

    // What sort of icon should we show?
    $action = '';
    if (!empty($question->id) &&
            (question_has_capability_on($question, 'edit') ||
                    question_has_capability_on($question, 'move'))) {
        $action = $stredit;
        $icon = 't/edit';
    } else if (!empty($question->id) &&
            question_has_capability_on($question, 'view')) {
        $action = $strview;
        $icon = 'i/info';
    }

    // Build the icon.
    if ($action) {
        if ($returnurl instanceof moodle_url) {
            $returnurl = $returnurl->out_as_local_url(false);
        }
        $questionparams = ['returnurl' => $returnurl, 'cmid' => $cmid, 'id' => $question->id];
        $questionurl = new moodle_url("$CFG->wwwroot/question/bank/editquestion/question.php", $questionparams);
        return '<a title="' . $action . '" href="' . $questionurl->out() . '" class="questioneditbutton">' .
                $OUTPUT->pix_icon($icon, $action) . $contentaftericon .
                '</a>';
    } else if ($contentaftericon) {
        return '<span class="questioneditbutton">' . $contentaftericon . '</span>';
    } else {
        return '';
    }
}

/**
 * @param stdClass $realtimequiz the realtimequiz settings
 * @param stdClass $question the question
 * @param int $variant which question variant to preview (optional).
 * @param int $restartversion version of the question to use when restarting the preview.
 * @return moodle_url to preview this question with the options from this realtimequiz.
 */
function realtimequiz_question_preview_url($realtimequiz, $question, $variant = null, $restartversion = null) {
    // Get the appropriate display options.
    $displayoptions = display_options::make_from_realtimequiz($realtimequiz,
            display_options::DURING);

    $maxmark = null;
    if (isset($question->maxmark)) {
        $maxmark = $question->maxmark;
    }

    // Work out the correcte preview URL.
    return \qbank_previewquestion\helper::question_preview_url($question->id, $realtimequiz->preferredbehaviour,
            $maxmark, $displayoptions, $variant, null, null, $restartversion);
}

/**
 * @param stdClass $realtimequiz the realtimequiz settings
 * @param stdClass $question the question
 * @param bool $label if true, show the preview question label after the icon
 * @param int $variant which question variant to preview (optional).
 * @param bool $random if question is random, true.
 * @return string the HTML for a preview question icon.
 */
function realtimequiz_question_preview_button($realtimequiz, $question, $label = false, $variant = null, $random = null) {
    global $PAGE;
    if (!question_has_capability_on($question, 'use')) {
        return '';
    }
    $structure = realtimequiz_settings::create($realtimequiz->id)->get_structure();
    if (!empty($question->slot)) {
        $requestedversion = $structure->get_slot_by_number($question->slot)->requestedversion
                ?? question_preview_options::ALWAYS_LATEST;
    } else {
        $requestedversion = question_preview_options::ALWAYS_LATEST;
    }
    return $PAGE->get_renderer('mod_realtimequiz', 'edit')->question_preview_icon(
            $realtimequiz, $question, $label, $variant, $requestedversion);
}

/**
 * @param stdClass $attempt the attempt.
 * @param stdClass $context the realtimequiz context.
 * @return int whether flags should be shown/editable to the current user for this attempt.
 */
function realtimequiz_get_flag_option($attempt, $context) {
    global $USER;
    if (!has_capability('moodle/question:flag', $context)) {
        return question_display_options::HIDDEN;
    } else if ($attempt->userid == $USER->id) {
        return question_display_options::EDITABLE;
    } else {
        return question_display_options::VISIBLE;
    }
}

/**
 * Work out what state this realtimequiz attempt is in - in the sense used by
 * realtimequiz_get_review_options, not in the sense of $attempt->state.
 * @param stdClass $realtimequiz the realtimequiz settings
 * @param stdClass $attempt the realtimequiz_attempt database row.
 * @return int one of the display_options::DURING,
 *      IMMEDIATELY_AFTER, LATER_WHILE_OPEN or AFTER_CLOSE constants.
 */
function realtimequiz_attempt_state($realtimequiz, $attempt) {
    if ($attempt->state == realtimequiz_attempt::IN_PROGRESS) {
        return display_options::DURING;
    } else if ($realtimequiz->timeclose && time() >= $realtimequiz->timeclose) {
        return display_options::AFTER_CLOSE;
    } else if (time() < $attempt->timefinish + 120) {
        return display_options::IMMEDIATELY_AFTER;
    } else {
        return display_options::LATER_WHILE_OPEN;
    }
}

/**
 * The appropriate display_options object for this attempt at this realtimequiz right now.
 *
 * @param stdClass $realtimequiz the realtimequiz instance.
 * @param stdClass $attempt the attempt in question.
 * @param context $context the realtimequiz context.
 *
 * @return display_options
 */
function realtimequiz_get_review_options($realtimequiz, $attempt, $context) {
    $options = display_options::make_from_realtimequiz($realtimequiz, realtimequiz_attempt_state($realtimequiz, $attempt));

    $options->readonly = true;
    $options->flags = realtimequiz_get_flag_option($attempt, $context);
    if (!empty($attempt->id)) {
        $options->questionreviewlink = new moodle_url('/mod/realtimequiz/reviewquestion.php',
                ['attempt' => $attempt->id]);
    }

    // Show a link to the comment box only for closed attempts.
    if (!empty($attempt->id) && $attempt->state == realtimequiz_attempt::FINISHED && !$attempt->preview &&
            !is_null($context) && has_capability('mod/realtimequiz:grade', $context)) {
        $options->manualcomment = question_display_options::VISIBLE;
        $options->manualcommentlink = new moodle_url('/mod/realtimequiz/comment.php',
                ['attempt' => $attempt->id]);
    }

    if (!is_null($context) && !$attempt->preview &&
            has_capability('mod/realtimequiz:viewreports', $context) &&
            has_capability('moodle/grade:viewhidden', $context)) {
        // People who can see reports and hidden grades should be shown everything,
        // except during preview when teachers want to see what students see.
        $options->attempt = question_display_options::VISIBLE;
        $options->correctness = question_display_options::VISIBLE;
        $options->marks = question_display_options::MARK_AND_MAX;
        $options->feedback = question_display_options::VISIBLE;
        $options->numpartscorrect = question_display_options::VISIBLE;
        $options->manualcomment = question_display_options::VISIBLE;
        $options->generalfeedback = question_display_options::VISIBLE;
        $options->rightanswer = question_display_options::VISIBLE;
        $options->overallfeedback = question_display_options::VISIBLE;
        $options->history = question_display_options::VISIBLE;
        $options->userinfoinhistory = $attempt->userid;

    }

    return $options;
}

/**
 * Combines the review options from a number of different realtimequiz attempts.
 * Returns an array of two ojects, so the suggested way of calling this
 * funciton is:
 * list($someoptions, $alloptions) = realtimequiz_get_combined_reviewoptions(...)
 *
 * @param stdClass $realtimequiz the realtimequiz instance.
 * @param array $attempts an array of attempt objects.
 *
 * @return array of two options objects, one showing which options are true for
 *          at least one of the attempts, the other showing which options are true
 *          for all attempts.
 */
function realtimequiz_get_combined_reviewoptions($realtimequiz, $attempts) {
    $fields = ['feedback', 'generalfeedback', 'rightanswer', 'overallfeedback'];
    $someoptions = new stdClass();
    $alloptions = new stdClass();
    foreach ($fields as $field) {
        $someoptions->$field = false;
        $alloptions->$field = true;
    }
    $someoptions->marks = question_display_options::HIDDEN;
    $alloptions->marks = question_display_options::MARK_AND_MAX;

    // This shouldn't happen, but we need to prevent reveal information.
    if (empty($attempts)) {
        return [$someoptions, $someoptions];
    }

    foreach ($attempts as $attempt) {
        $attemptoptions = display_options::make_from_realtimequiz($realtimequiz,
                realtimequiz_attempt_state($realtimequiz, $attempt));
        foreach ($fields as $field) {
            $someoptions->$field = $someoptions->$field || $attemptoptions->$field;
            $alloptions->$field = $alloptions->$field && $attemptoptions->$field;
        }
        $someoptions->marks = max($someoptions->marks, $attemptoptions->marks);
        $alloptions->marks = min($alloptions->marks, $attemptoptions->marks);
    }
    return [$someoptions, $alloptions];
}

// Functions for sending notification messages /////////////////////////////////

/**
 * Sends a confirmation message to the student confirming that the attempt was processed.
 *
 * @param stdClass $recipient user object for the recipient.
 * @param stdClass $a lots of useful information that can be used in the message
 *      subject and body.
 * @param bool $studentisonline is the student currently interacting with Moodle?
 *
 * @return int|false as for {@link message_send()}.
 */
function realtimequiz_send_confirmation($recipient, $a, $studentisonline) {

    // Add information about the recipient to $a.
    // Don't do idnumber. we want idnumber to be the submitter's idnumber.
    $a->username     = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $eventdata = new \core\message\message();
    $eventdata->courseid          = $a->courseid;
    $eventdata->component         = 'mod_realtimequiz';
    $eventdata->name              = 'confirmation';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = core_user::get_noreply_user();
    $eventdata->userto            = $recipient;
    $eventdata->subject           = get_string('emailconfirmsubject', 'realtimequiz', $a);

    if ($studentisonline) {
        $eventdata->fullmessage = get_string('emailconfirmbody', 'realtimequiz', $a);
    } else {
        $eventdata->fullmessage = get_string('emailconfirmbodyautosubmit', 'realtimequiz', $a);
    }

    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailconfirmsmall', 'realtimequiz', $a);
    $eventdata->contexturl        = $a->realtimequizurl;
    $eventdata->contexturlname    = $a->realtimequizname;
    $eventdata->customdata        = [
        'cmid' => $a->realtimequizcmid,
        'instance' => $a->realtimequizid,
        'attemptid' => $a->attemptid,
    ];

    // ... and send it.
    return message_send($eventdata);
}

/**
 * Sends notification messages to the interested parties that assign the role capability
 *
 * @param stdClass $recipient user object of the intended recipient
 * @param stdClass $submitter user object for the user who submitted the attempt.
 * @param stdClass $a associative array of replaceable fields for the templates
 *
 * @return int|false as for {@link message_send()}.
 */
function realtimequiz_send_notification($recipient, $submitter, $a) {
    global $PAGE;

    // Recipient info for template.
    $a->useridnumber = $recipient->idnumber;
    $a->username     = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $eventdata = new \core\message\message();
    $eventdata->courseid          = $a->courseid;
    $eventdata->component         = 'mod_realtimequiz';
    $eventdata->name              = 'submission';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = $submitter;
    $eventdata->userto            = $recipient;
    $eventdata->subject           = get_string('emailnotifysubject', 'realtimequiz', $a);
    $eventdata->fullmessage       = get_string('emailnotifybody', 'realtimequiz', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailnotifysmall', 'realtimequiz', $a);
    $eventdata->contexturl        = $a->realtimequizreviewurl;
    $eventdata->contexturlname    = $a->realtimequizname;
    $userpicture = new user_picture($submitter);
    $userpicture->size = 1; // Use f1 size.
    $userpicture->includetoken = $recipient->id; // Generate an out-of-session token for the user receiving the message.
    $eventdata->customdata        = [
        'cmid' => $a->realtimequizcmid,
        'instance' => $a->realtimequizid,
        'attemptid' => $a->attemptid,
        'notificationiconurl' => $userpicture->get_url($PAGE)->out(false),
    ];

    // ... and send it.
    return message_send($eventdata);
}

/**
 * Send all the requried messages when a realtimequiz attempt is submitted.
 *
 * @param stdClass $course the course
 * @param stdClass $realtimequiz the realtimequiz
 * @param stdClass $attempt this attempt just finished
 * @param stdClass $context the realtimequiz context
 * @param stdClass $cm the coursemodule for this realtimequiz
 * @param bool $studentisonline is the student currently interacting with Moodle?
 *
 * @return bool true if all necessary messages were sent successfully, else false.
 */
function realtimequiz_send_notification_messages($course, $realtimequiz, $attempt, $context, $cm, $studentisonline) {
    global $CFG, $DB;

    // Do nothing if required objects not present.
    if (empty($course) or empty($realtimequiz) or empty($attempt) or empty($context)) {
        throw new coding_exception('$course, $realtimequiz, $attempt, $context and $cm must all be set.');
    }

    $submitter = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);

    // Check for confirmation required.
    $sendconfirm = false;
    $notifyexcludeusers = '';
    if (has_capability('mod/realtimequiz:emailconfirmsubmission', $context, $submitter, false)) {
        $notifyexcludeusers = $submitter->id;
        $sendconfirm = true;
    }

    // Check for notifications required.
    $notifyfields = 'u.id, u.username, u.idnumber, u.email, u.emailstop, u.lang,
            u.timezone, u.mailformat, u.maildisplay, u.auth, u.suspended, u.deleted, ';
    $userfieldsapi = \core_user\fields::for_name();
    $notifyfields .= $userfieldsapi->get_sql('u', false, '', '', false)->selects;
    $groups = groups_get_all_groups($course->id, $submitter->id, $cm->groupingid);
    if (is_array($groups) && count($groups) > 0) {
        $groups = array_keys($groups);
    } else if (groups_get_activity_groupmode($cm, $course) != NOGROUPS) {
        // If the user is not in a group, and the realtimequiz is set to group mode,
        // then set $groups to a non-existant id so that only users with
        // 'moodle/site:accessallgroups' get notified.
        $groups = -1;
    } else {
        $groups = '';
    }
    $userstonotify = get_users_by_capability($context, 'mod/realtimequiz:emailnotifysubmission',
            $notifyfields, '', '', '', $groups, $notifyexcludeusers, false, false, true);

    if (empty($userstonotify) && !$sendconfirm) {
        return true; // Nothing to do.
    }

    $a = new stdClass();
    // Course info.
    $a->courseid        = $course->id;
    $a->coursename      = $course->fullname;
    $a->courseshortname = $course->shortname;
    // Quiz info.
    $a->realtimequizname        = $realtimequiz->name;
    $a->realtimequizreporturl   = $CFG->wwwroot . '/mod/realtimequiz/report.php?id=' . $cm->id;
    $a->realtimequizreportlink  = '<a href="' . $a->realtimequizreporturl . '">' .
            format_string($realtimequiz->name) . ' report</a>';
    $a->realtimequizurl         = $CFG->wwwroot . '/mod/realtimequiz/view.php?id=' . $cm->id;
    $a->realtimequizlink        = '<a href="' . $a->realtimequizurl . '">' . format_string($realtimequiz->name) . '</a>';
    $a->realtimequizid          = $realtimequiz->id;
    $a->realtimequizcmid        = $cm->id;
    // Attempt info.
    $a->submissiontime  = userdate($attempt->timefinish);
    $a->timetaken       = format_time($attempt->timefinish - $attempt->timestart);
    $a->realtimequizreviewurl   = $CFG->wwwroot . '/mod/realtimequiz/review.php?attempt=' . $attempt->id;
    $a->realtimequizreviewlink  = '<a href="' . $a->realtimequizreviewurl . '">' .
            format_string($realtimequiz->name) . ' review</a>';
    $a->attemptid       = $attempt->id;
    // Student who sat the realtimequiz info.
    $a->studentidnumber = $submitter->idnumber;
    $a->studentname     = fullname($submitter);
    $a->studentusername = $submitter->username;

    $allok = true;

    // Send notifications if required.
    if (!empty($userstonotify)) {
        foreach ($userstonotify as $recipient) {
            $allok = $allok && realtimequiz_send_notification($recipient, $submitter, $a);
        }
    }

    // Send confirmation if required. We send the student confirmation last, so
    // that if message sending is being intermittently buggy, which means we send
    // some but not all messages, and then try again later, then teachers may get
    // duplicate messages, but the student will always get exactly one.
    if ($sendconfirm) {
        $allok = $allok && realtimequiz_send_confirmation($submitter, $a, $studentisonline);
    }

    return $allok;
}

/**
 * Send the notification message when a realtimequiz attempt becomes overdue.
 *
 * @param realtimequiz_attempt $attemptobj all the data about the realtimequiz attempt.
 */
function realtimequiz_send_overdue_message($attemptobj) {
    global $CFG, $DB;

    $submitter = $DB->get_record('user', ['id' => $attemptobj->get_userid()], '*', MUST_EXIST);

    if (!$attemptobj->has_capability('mod/realtimequiz:emailwarnoverdue', $submitter->id, false)) {
        return; // Message not required.
    }

    if (!$attemptobj->has_response_to_at_least_one_graded_question()) {
        return; // Message not required.
    }

    // Prepare lots of useful information that admins might want to include in
    // the email message.
    $realtimequizname = format_string($attemptobj->get_realtimequiz_name());

    $deadlines = [];
    if ($attemptobj->get_realtimequiz()->timelimit) {
        $deadlines[] = $attemptobj->get_attempt()->timestart + $attemptobj->get_realtimequiz()->timelimit;
    }
    if ($attemptobj->get_realtimequiz()->timeclose) {
        $deadlines[] = $attemptobj->get_realtimequiz()->timeclose;
    }
    $duedate = min($deadlines);
    $graceend = $duedate + $attemptobj->get_realtimequiz()->graceperiod;

    $a = new stdClass();
    // Course info.
    $a->courseid           = $attemptobj->get_course()->id;
    $a->coursename         = format_string($attemptobj->get_course()->fullname);
    $a->courseshortname    = format_string($attemptobj->get_course()->shortname);
    // Quiz info.
    $a->realtimequizname           = $realtimequizname;
    $a->realtimequizurl            = $attemptobj->view_url()->out(false);
    $a->realtimequizlink           = '<a href="' . $a->realtimequizurl . '">' . $realtimequizname . '</a>';
    // Attempt info.
    $a->attemptduedate     = userdate($duedate);
    $a->attemptgraceend    = userdate($graceend);
    $a->attemptsummaryurl  = $attemptobj->summary_url()->out(false);
    $a->attemptsummarylink = '<a href="' . $a->attemptsummaryurl . '">' . $realtimequizname . ' review</a>';
    // Student's info.
    $a->studentidnumber    = $submitter->idnumber;
    $a->studentname        = fullname($submitter);
    $a->studentusername    = $submitter->username;

    // Prepare the message.
    $eventdata = new \core\message\message();
    $eventdata->courseid          = $a->courseid;
    $eventdata->component         = 'mod_realtimequiz';
    $eventdata->name              = 'attempt_overdue';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = core_user::get_noreply_user();
    $eventdata->userto            = $submitter;
    $eventdata->subject           = get_string('emailoverduesubject', 'realtimequiz', $a);
    $eventdata->fullmessage       = get_string('emailoverduebody', 'realtimequiz', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailoverduesmall', 'realtimequiz', $a);
    $eventdata->contexturl        = $a->realtimequizurl;
    $eventdata->contexturlname    = $a->realtimequizname;
    $eventdata->customdata        = [
        'cmid' => $attemptobj->get_cmid(),
        'instance' => $attemptobj->get_realtimequizid(),
        'attemptid' => $attemptobj->get_attemptid(),
    ];

    // Send the message.
    return message_send($eventdata);
}

/**
 * Handle the realtimequiz_attempt_submitted event.
 *
 * This sends the confirmation and notification messages, if required.
 *
 * @param attempt_submitted $event the event object.
 */
function realtimequiz_attempt_submitted_handler($event) {
    $course = get_course($event->courseid);
    $attempt = $event->get_record_snapshot('realtimequiz_attempts', $event->objectid);
    $realtimequiz = $event->get_record_snapshot('realtimequiz', $attempt->realtimequiz);
    $cm = get_coursemodule_from_id('realtimequiz', $event->get_context()->instanceid, $event->courseid);
    $eventdata = $event->get_data();

    if (!($course && $realtimequiz && $cm && $attempt)) {
        // Something has been deleted since the event was raised. Therefore, the
        // event is no longer relevant.
        return true;
    }

    // Update completion state.
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) &&
        ($realtimequiz->completionattemptsexhausted || $realtimequiz->completionminattempts)) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $event->userid);
    }
    return realtimequiz_send_notification_messages($course, $realtimequiz, $attempt,
            context_module::instance($cm->id), $cm, $eventdata['other']['studentisonline']);
}

/**
 * Send the notification message when a realtimequiz attempt has been manual graded.
 *
 * @param realtimequiz_attempt $attemptobj Some data about the realtimequiz attempt.
 * @param stdClass $userto
 * @return int|false As for message_send.
 */
function realtimequiz_send_notify_manual_graded_message(realtimequiz_attempt $attemptobj, object $userto): ?int {
    global $CFG;

    $realtimequizname = format_string($attemptobj->get_realtimequiz_name());

    $a = new stdClass();
    // Course info.
    $a->courseid           = $attemptobj->get_courseid();
    $a->coursename         = format_string($attemptobj->get_course()->fullname);
    // Quiz info.
    $a->realtimequizname           = $realtimequizname;
    $a->realtimequizurl            = $CFG->wwwroot . '/mod/realtimequiz/view.php?id=' . $attemptobj->get_cmid();

    // Attempt info.
    $a->attempttimefinish  = userdate($attemptobj->get_attempt()->timefinish);
    // Student's info.
    $a->studentidnumber    = $userto->idnumber;
    $a->studentname        = fullname($userto);

    $eventdata = new \core\message\message();
    $eventdata->component = 'mod_realtimequiz';
    $eventdata->name = 'attempt_grading_complete';
    $eventdata->userfrom = core_user::get_noreply_user();
    $eventdata->userto = $userto;

    $eventdata->subject = get_string('emailmanualgradedsubject', 'realtimequiz', $a);
    $eventdata->fullmessage = get_string('emailmanualgradedbody', 'realtimequiz', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = '';

    $eventdata->notification = 1;
    $eventdata->contexturl = $a->realtimequizurl;
    $eventdata->contexturlname = $a->realtimequizname;

    // Send the message.
    return message_send($eventdata);
}


/**
 * Logic to happen when a/some group(s) has/have been deleted in a course.
 *
 * @param int $courseid The course ID.
 * @return void
 */
function realtimequiz_process_group_deleted_in_course($courseid) {
    global $DB;

    // It would be nice if we got the groupid that was deleted.
    // Instead, we just update all realtimequizzes with orphaned group overrides.
    $sql = "SELECT o.id, o.realtimequiz, o.groupid
              FROM {realtimequiz_overrides} o
              JOIN {realtimequiz} realtimequiz ON realtimequiz.id = o.realtimequiz
         LEFT JOIN {groups} grp ON grp.id = o.groupid
             WHERE realtimequiz.course = :courseid
               AND o.groupid IS NOT NULL
               AND grp.id IS NULL";
    $params = ['courseid' => $courseid];
    $records = $DB->get_records_sql($sql, $params);
    if (!$records) {
        return; // Nothing to do.
    }
    $DB->delete_records_list('realtimequiz_overrides', 'id', array_keys($records));
    $cache = cache::make('mod_realtimequiz', 'overrides');
    foreach ($records as $record) {
        $cache->delete("{$record->realtimequiz}_g_{$record->groupid}");
    }
    realtimequiz_update_open_attempts(['realtimequizid' => array_unique(array_column($records, 'realtimequiz'))]);
}

/**
 * Get the information about the standard realtimequiz JavaScript module.
 * @return array a standard jsmodule structure.
 */
function realtimequiz_get_js_module() {
    global $PAGE;

    return [
        'name' => 'mod_realtimequiz',
        'fullpath' => '/mod/realtimequiz/module.js',
        'requires' => ['base', 'dom', 'event-delegate', 'event-key',
                'core_question_engine'],
        'strings' => [
            ['cancel', 'moodle'],
            ['flagged', 'question'],
            ['functiondisabledbysecuremode', 'realtimequiz'],
            ['startattempt', 'realtimequiz'],
            ['timesup', 'realtimequiz'],
        ],
    ];
}


/**
 * Creates a textual representation of a question for display.
 *
 * @param stdClass $question A question object from the database questions table
 * @param bool $showicon If true, show the question's icon with the question. False by default.
 * @param bool $showquestiontext If true (default), show question text after question name.
 *       If false, show only question name.
 * @param bool $showidnumber If true, show the question's idnumber, if any. False by default.
 * @param core_tag_tag[]|bool $showtags if array passed, show those tags. Else, if true, get and show tags,
 *       else, don't show tags (which is the default).
 * @return string HTML fragment.
 */
function realtimequiz_question_tostring($question, $showicon = false, $showquestiontext = true,
        $showidnumber = false, $showtags = false) {
    global $OUTPUT;
    $result = '';

    // Question name.
    $name = shorten_text(format_string($question->name), 200);
    if ($showicon) {
        $name .= print_question_icon($question) . ' ' . $name;
    }
    $result .= html_writer::span($name, 'questionname');

    // Question idnumber.
    if ($showidnumber && $question->idnumber !== null && $question->idnumber !== '') {
        $result .= ' ' . html_writer::span(
                html_writer::span(get_string('idnumber', 'question'), 'accesshide') .
                ' ' . s($question->idnumber), 'badge badge-primary');
    }

    // Question tags.
    if (is_array($showtags)) {
        $tags = $showtags;
    } else if ($showtags) {
        $tags = core_tag_tag::get_item_tags('core_question', 'question', $question->id);
    } else {
        $tags = [];
    }
    if ($tags) {
        $result .= $OUTPUT->tag_list($tags, null, 'd-inline', 0, null, true);
    }

    // Question text.
    if ($showquestiontext) {
        $questiontext = question_utils::to_plain_text($question->questiontext,
                $question->questiontextformat, ['noclean' => true, 'para' => false, 'filter' => false]);
        $questiontext = shorten_text($questiontext, 50);
        if ($questiontext) {
            $result .= ' ' . html_writer::span(s($questiontext), 'questiontext');
        }
    }

    return $result;
}

/**
 * Verify that the question exists, and the user has permission to use it.
 * Does not return. Throws an exception if the question cannot be used.
 * @param int $questionid The id of the question.
 */
function realtimequiz_require_question_use($questionid) {
    global $DB;
    $question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
    question_require_capability_on($question, 'use');
}

/**
 * Add a question to a realtimequiz
 *
 * Adds a question to a realtimequiz by updating $realtimequiz as well as the
 * realtimequiz and realtimequiz_slots tables. It also adds a page break if required.
 * @param int $questionid The id of the question to be added
 * @param stdClass $realtimequiz The extended realtimequiz object as used by edit.php
 *      This is updated by this function
 * @param int $page Which page in realtimequiz to add the question on. If 0 (default),
 *      add at the end
 * @param float $maxmark The maximum mark to set for this question. (Optional,
 *      defaults to question.defaultmark.
 * @return bool false if the question was already in the realtimequiz
 */
function realtimequiz_add_realtimequiz_question($questionid, $realtimequiz, $page = 0, $maxmark = null) {
    global $DB;

    if (!isset($realtimequiz->cmid)) {
        $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id, $realtimequiz->course);
        $realtimequiz->cmid = $cm->id;
    }

    // Make sue the question is not of the "random" type.
    $questiontype = $DB->get_field('question', 'qtype', ['id' => $questionid]);
    if ($questiontype == 'random') {
        throw new coding_exception(
                'Adding "random" questions via realtimequiz_add_realtimequiz_question() is deprecated. Please use realtimequiz_add_random_questions().'
        );
    }

    $trans = $DB->start_delegated_transaction();

    $sql = "SELECT qbe.id
              FROM {realtimequiz_slots} slot
              JOIN {question_references} qr ON qr.itemid = slot.id
              JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
             WHERE slot.realtimequizid = ?
               AND qr.component = ?
               AND qr.questionarea = ?
               AND qr.usingcontextid = ?";

    $questionslots = $DB->get_records_sql($sql, [$realtimequiz->id, 'mod_realtimequiz', 'slot',
            context_module::instance($realtimequiz->cmid)->id]);

    $currententry = get_question_bank_entry($questionid);

    if (array_key_exists($currententry->id, $questionslots)) {
        $trans->allow_commit();
        return false;
    }

    $sql = "SELECT slot.slot, slot.page, slot.id
              FROM {realtimequiz_slots} slot
             WHERE slot.realtimequizid = ?
          ORDER BY slot.slot";

    $slots = $DB->get_records_sql($sql, [$realtimequiz->id]);

    $maxpage = 1;
    $numonlastpage = 0;
    foreach ($slots as $slot) {
        if ($slot->page > $maxpage) {
            $maxpage = $slot->page;
            $numonlastpage = 1;
        } else {
            $numonlastpage += 1;
        }
    }

    // Add the new instance.
    $slot = new stdClass();
    $slot->realtimequizid = $realtimequiz->id;

    if ($maxmark !== null) {
        $slot->maxmark = $maxmark;
    } else {
        $slot->maxmark = $DB->get_field('question', 'defaultmark', ['id' => $questionid]);
    }

    if (is_int($page) && $page >= 1) {
        // Adding on a given page.
        $lastslotbefore = 0;
        foreach (array_reverse($slots) as $otherslot) {
            if ($otherslot->page > $page) {
                $DB->set_field('realtimequiz_slots', 'slot', $otherslot->slot + 1, ['id' => $otherslot->id]);
            } else {
                $lastslotbefore = $otherslot->slot;
                break;
            }
        }
        $slot->slot = $lastslotbefore + 1;
        $slot->page = min($page, $maxpage + 1);

        realtimequiz_update_section_firstslots($realtimequiz->id, 1, max($lastslotbefore, 1));

    } else {
        $lastslot = end($slots);
        if ($lastslot) {
            $slot->slot = $lastslot->slot + 1;
        } else {
            $slot->slot = 1;
        }
        if ($realtimequiz->questionsperpage && $numonlastpage >= $realtimequiz->questionsperpage) {
            $slot->page = $maxpage + 1;
        } else {
            $slot->page = $maxpage;
        }
    }

    $slotid = $DB->insert_record('realtimequiz_slots', $slot);

    // Update or insert record in question_reference table.
    $sql = "SELECT DISTINCT qr.id, qr.itemid
              FROM {question} q
              JOIN {question_versions} qv ON q.id = qv.questionid
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_references} qr ON qbe.id = qr.questionbankentryid AND qr.version = qv.version
              JOIN {realtimequiz_slots} qs ON qs.id = qr.itemid
             WHERE q.id = ?
               AND qs.id = ?
               AND qr.component = ?
               AND qr.questionarea = ?";
    $qreferenceitem = $DB->get_record_sql($sql, [$questionid, $slotid, 'mod_realtimequiz', 'slot']);

    if (!$qreferenceitem) {
        // Create a new reference record for questions created already.
        $questionreferences = new stdClass();
        $questionreferences->usingcontextid = context_module::instance($realtimequiz->cmid)->id;
        $questionreferences->component = 'mod_realtimequiz';
        $questionreferences->questionarea = 'slot';
        $questionreferences->itemid = $slotid;
        $questionreferences->questionbankentryid = get_question_bank_entry($questionid)->id;
        $questionreferences->version = null; // Always latest.
        $DB->insert_record('question_references', $questionreferences);

    } else if ($qreferenceitem->itemid === 0 || $qreferenceitem->itemid === null) {
        $questionreferences = new stdClass();
        $questionreferences->id = $qreferenceitem->id;
        $questionreferences->itemid = $slotid;
        $DB->update_record('question_references', $questionreferences);
    } else {
        // If the reference record exits for another realtimequiz.
        $questionreferences = new stdClass();
        $questionreferences->usingcontextid = context_module::instance($realtimequiz->cmid)->id;
        $questionreferences->component = 'mod_realtimequiz';
        $questionreferences->questionarea = 'slot';
        $questionreferences->itemid = $slotid;
        $questionreferences->questionbankentryid = get_question_bank_entry($questionid)->id;
        $questionreferences->version = null; // Always latest.
        $DB->insert_record('question_references', $questionreferences);
    }

    $trans->allow_commit();

    // Log slot created event.
    $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id);
    $event = \mod_realtimequiz\event\slot_created::create([
        'context' => context_module::instance($cm->id),
        'objectid' => $slotid,
        'other' => [
            'realtimequizid' => $realtimequiz->id,
            'slotnumber' => $slot->slot,
            'page' => $slot->page
        ]
    ]);
    $event->trigger();
}

/**
 * Move all the section headings in a certain slot range by a certain offset.
 *
 * @param int $realtimequizid the id of a realtimequiz
 * @param int $direction amount to adjust section heading positions. Normally +1 or -1.
 * @param int $afterslot adjust headings that start after this slot.
 * @param int|null $beforeslot optionally, only adjust headings before this slot.
 */
function realtimequiz_update_section_firstslots($realtimequizid, $direction, $afterslot, $beforeslot = null) {
    global $DB;
    $where = 'realtimequizid = ? AND firstslot > ?';
    $params = [$direction, $realtimequizid, $afterslot];
    if ($beforeslot) {
        $where .= ' AND firstslot < ?';
        $params[] = $beforeslot;
    }
    $firstslotschanges = $DB->get_records_select_menu('realtimequiz_sections',
            $where, $params, '', 'firstslot, firstslot + ?');
    update_field_with_unique_index('realtimequiz_sections', 'firstslot', $firstslotschanges, ['realtimequizid' => $realtimequizid]);
}

/**
 * Add a random question to the realtimequiz at a given point.
 * @param stdClass $realtimequiz the realtimequiz settings.
 * @param int $addonpage the page on which to add the question.
 * @param int $categoryid the question category to add the question from.
 * @param int $number the number of random questions to add.
 * @param bool $includesubcategories whether to include questoins from subcategories.
 * @param int[] $tagids Array of tagids. The question that will be picked randomly should be tagged with all these tags.
 */
function realtimequiz_add_random_questions($realtimequiz, $addonpage, $categoryid, $number,
        $includesubcategories, $tagids = []) {
    global $DB;

    $category = $DB->get_record('question_categories', ['id' => $categoryid]);
    if (!$category) {
        new moodle_exception('invalidcategoryid');
    }

    $catcontext = context::instance_by_id($category->contextid);
    require_capability('moodle/question:useall', $catcontext);

    // Tags for filter condition.
    $tags = \core_tag_tag::get_bulk($tagids, 'id, name');
    $tagstrings = [];
    foreach ($tags as $tag) {
        $tagstrings[] = "{$tag->id},{$tag->name}";
    }
    // Create the selected number of random questions.
    for ($i = 0; $i < $number; $i++) {
        // Set the filter conditions.
        $filtercondition = new stdClass();
        $filtercondition->questioncategoryid = $categoryid;
        $filtercondition->includingsubcategories = $includesubcategories ? 1 : 0;
        if (!empty($tagstrings)) {
            $filtercondition->tags = $tagstrings;
        }

        if (!isset($realtimequiz->cmid)) {
            $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id, $realtimequiz->course);
            $realtimequiz->cmid = $cm->id;
        }

        // Slot data.
        $randomslotdata = new stdClass();
        $randomslotdata->realtimequizid = $realtimequiz->id;
        $randomslotdata->usingcontextid = context_module::instance($realtimequiz->cmid)->id;
        $randomslotdata->questionscontextid = $category->contextid;
        $randomslotdata->maxmark = 1;

        $randomslot = new \mod_realtimequiz\local\structure\slot_random($randomslotdata);
        $randomslot->set_realtimequiz($realtimequiz);
        $randomslot->set_filter_condition($filtercondition);
        $randomslot->insert($addonpage);
    }
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $realtimequiz       realtimequiz object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.1
 */
function realtimequiz_view($realtimequiz, $course, $cm, $context) {

    $params = [
        'objectid' => $realtimequiz->id,
        'context' => $context
    ];

    $event = \mod_realtimequiz\event\course_module_viewed::create($params);
    $event->add_record_snapshot('realtimequiz', $realtimequiz);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Validate permissions for creating a new attempt and start a new preview attempt if required.
 *
 * @param  realtimequiz_settings $realtimequizobj realtimequiz object
 * @param  access_manager $accessmanager realtimequiz access manager
 * @param  bool $forcenew whether was required to start a new preview attempt
 * @param  int $page page to jump to in the attempt
 * @param  bool $redirect whether to redirect or throw exceptions (for web or ws usage)
 * @return array an array containing the attempt information, access error messages and the page to jump to in the attempt
 * @since Moodle 3.1
 */
function realtimequiz_validate_new_attempt(realtimequiz_settings $realtimequizobj, access_manager $accessmanager, $forcenew, $page, $redirect) {
    global $DB, $USER;
    $timenow = time();

    if ($realtimequizobj->is_preview_user() && $forcenew) {
        $accessmanager->current_attempt_finished();
    }

    // Check capabilities.
    if (!$realtimequizobj->is_preview_user()) {
        $realtimequizobj->require_capability('mod/realtimequiz:attempt');
    }

    // Check to see if a new preview was requested.
    if ($realtimequizobj->is_preview_user() && $forcenew) {
        // To force the creation of a new preview, we mark the current attempt (if any)
        // as abandoned. It will then automatically be deleted below.
        $DB->set_field('realtimequiz_attempts', 'state', realtimequiz_attempt::ABANDONED,
                ['realtimequiz' => $realtimequizobj->get_realtimequizid(), 'userid' => $USER->id]);
    }

    // Look for an existing attempt.
    $attempts = realtimequiz_get_user_attempts($realtimequizobj->get_realtimequizid(), $USER->id, 'all', true);
    $lastattempt = end($attempts);

    $attemptnumber = null;
    // If an in-progress attempt exists, check password then redirect to it.
    if ($lastattempt && ($lastattempt->state == realtimequiz_attempt::IN_PROGRESS ||
            $lastattempt->state == realtimequiz_attempt::OVERDUE)) {
        $currentattemptid = $lastattempt->id;
        $messages = $accessmanager->prevent_access();

        // If the attempt is now overdue, deal with that.
        $realtimequizobj->create_attempt_object($lastattempt)->handle_if_time_expired($timenow, true);

        // And, if the attempt is now no longer in progress, redirect to the appropriate place.
        if ($lastattempt->state == realtimequiz_attempt::ABANDONED || $lastattempt->state == realtimequiz_attempt::FINISHED) {
            if ($redirect) {
                redirect($realtimequizobj->review_url($lastattempt->id));
            } else {
                throw new moodle_exception('attemptalreadyclosed', 'realtimequiz', $realtimequizobj->view_url());
            }
        }

        // If the page number was not explicitly in the URL, go to the current page.
        if ($page == -1) {
            $page = $lastattempt->currentpage;
        }

    } else {
        while ($lastattempt && $lastattempt->preview) {
            $lastattempt = array_pop($attempts);
        }

        // Get number for the next or unfinished attempt.
        if ($lastattempt) {
            $attemptnumber = $lastattempt->attempt + 1;
        } else {
            $lastattempt = false;
            $attemptnumber = 1;
        }
        $currentattemptid = null;

        $messages = $accessmanager->prevent_access() +
            $accessmanager->prevent_new_attempt(count($attempts), $lastattempt);

        if ($page == -1) {
            $page = 0;
        }
    }
    return [$currentattemptid, $attemptnumber, $lastattempt, $messages, $page];
}

/**
 * Prepare and start a new attempt deleting the previous preview attempts.
 *
 * @param realtimequiz_settings $realtimequizobj realtimequiz object
 * @param int $attemptnumber the attempt number
 * @param stdClass $lastattempt last attempt object
 * @param bool $offlineattempt whether is an offline attempt or not
 * @param array $forcedrandomquestions slot number => question id. Used for random questions,
 *      to force the choice of a particular actual question. Intended for testing purposes only.
 * @param array $forcedvariants slot number => variant. Used for questions with variants,
 *      to force the choice of a particular variant. Intended for testing purposes only.
 * @param int $userid Specific user id to create an attempt for that user, null for current logged in user
 * @return stdClass the new attempt
 * @since  Moodle 3.1
 */
function realtimequiz_prepare_and_start_new_attempt(realtimequiz_settings $realtimequizobj, $attemptnumber, $lastattempt,
        $offlineattempt = false, $forcedrandomquestions = [], $forcedvariants = [], $userid = null) {
    global $DB, $USER;

    if ($userid === null) {
        $userid = $USER->id;
        $ispreviewuser = $realtimequizobj->is_preview_user();
    } else {
        $ispreviewuser = has_capability('mod/realtimequiz:preview', $realtimequizobj->get_context(), $userid);
    }
    // Delete any previous preview attempts belonging to this user.
    realtimequiz_delete_previews($realtimequizobj->get_realtimequiz(), $userid);

    $quba = question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
    $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

    // Create the new attempt and initialize the question sessions
    $timenow = time(); // Update time now, in case the server is running really slowly.
    $attempt = realtimequiz_create_attempt($realtimequizobj, $attemptnumber, $lastattempt, $timenow, $ispreviewuser, $userid);

    if (!($realtimequizobj->get_realtimequiz()->attemptonlast && $lastattempt)) {
        $attempt = realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, $attemptnumber, $timenow,
                $forcedrandomquestions, $forcedvariants);
    } else {
        $attempt = realtimequiz_start_attempt_built_on_last($quba, $attempt, $lastattempt);
    }

    $transaction = $DB->start_delegated_transaction();

    // Init the timemodifiedoffline for offline attempts.
    if ($offlineattempt) {
        $attempt->timemodifiedoffline = $attempt->timemodified;
    }
    $attempt = realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

    $transaction->allow_commit();

    return $attempt;
}

/**
 * Check if the given calendar_event is either a user or group override
 * event for realtimequiz.
 *
 * @param calendar_event $event The calendar event to check
 * @return bool
 */
function realtimequiz_is_overriden_calendar_event(\calendar_event $event) {
    global $DB;

    if (!isset($event->modulename)) {
        return false;
    }

    if ($event->modulename != 'realtimequiz') {
        return false;
    }

    if (!isset($event->instance)) {
        return false;
    }

    if (!isset($event->userid) && !isset($event->groupid)) {
        return false;
    }

    $overrideparams = [
        'realtimequiz' => $event->instance
    ];

    if (isset($event->groupid)) {
        $overrideparams['groupid'] = $event->groupid;
    } else if (isset($event->userid)) {
        $overrideparams['userid'] = $event->userid;
    }

    return $DB->record_exists('realtimequiz_overrides', $overrideparams);
}

/**
 * Get realtimequiz attempt and handling error.
 *
 * @param int $attemptid the id of the current attempt.
 * @param int|null $cmid the course_module id for this realtimequiz.
 * @return realtimequiz_attempt all the data about the realtimequiz attempt.
 */
function realtimequiz_create_attempt_handling_errors($attemptid, $cmid = null) {
    try {
        $attempobj = realtimequiz_attempt::create($attemptid);
    } catch (moodle_exception $e) {
        if (!empty($cmid)) {
            list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'realtimequiz');
            $continuelink = new moodle_url('/mod/realtimequiz/view.php', ['id' => $cmid]);
            $context = context_module::instance($cm->id);
            if (has_capability('mod/realtimequiz:preview', $context)) {
                throw new moodle_exception('attempterrorcontentchange', 'realtimequiz', $continuelink);
            } else {
                throw new moodle_exception('attempterrorcontentchangeforuser', 'realtimequiz', $continuelink);
            }
        } else {
            throw new moodle_exception('attempterrorinvalid', 'realtimequiz');
        }
    }
    if (!empty($cmid) && $attempobj->get_cmid() != $cmid) {
        throw new moodle_exception('invalidcoursemodule');
    } else {
        return $attempobj;
    }
}
