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
 * List of deprecated mod_realtimequiz functions.
 *
 * @package   mod_realtimequiz
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\access_manager;
use mod_realtimequiz\realtimequiz_settings;
use mod_realtimequiz\task\update_overdue_attempts;

/**
 * Internal function used in realtimequiz_get_completion_state. Check passing grade (or no attempts left) requirement for completion.
 *
 * @deprecated since Moodle 3.11
 * @todo MDL-71196 Final deprecation in Moodle 4.3
 * @see \mod_realtimequiz\completion\custom_completion
 * @param stdClass $course
 * @param cm_info|stdClass $cm
 * @param int $userid
 * @param stdClass $realtimequiz
 * @return bool True if the passing grade (or no attempts left) requirement is disabled or met.
 * @throws coding_exception
 */
function realtimequiz_completion_check_passing_grade_or_all_attempts($course, $cm, $userid, $realtimequiz) {
    global $CFG;

    debugging('realtimequiz_completion_check_passing_grade_or_all_attempts has been deprecated.', DEBUG_DEVELOPER);

    if (!$cm->completionpassgrade) {
        return true;
    }

    // Check for passing grade.
    require_once($CFG->libdir . '/gradelib.php');
    $item = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod',
            'itemmodule' => 'realtimequiz', 'iteminstance' => $cm->instance, 'outcomeid' => null]);
    if ($item) {
        $grades = grade_grade::fetch_users_grades($item, [$userid], false);
        if (!empty($grades[$userid]) && $grades[$userid]->is_passed($item)) {
            return true;
        }
    }

    // If a passing grade is required and exhausting all available attempts is not accepted for completion,
    // then this realtimequiz is not complete.
    if (!$realtimequiz->completionattemptsexhausted) {
        return false;
    }

    // Check if all attempts are used up.
    $attempts = realtimequiz_get_user_attempts($realtimequiz->id, $userid, 'finished', true);
    if (!$attempts) {
        return false;
    }
    $lastfinishedattempt = end($attempts);
    $context = context_module::instance($cm->id);
    $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $userid);
    $accessmanager = new access_manager($realtimequizobj, time(),
            has_capability('mod/realtimequiz:ignoretimelimits', $context, $userid, false));

    return $accessmanager->is_finished(count($attempts), $lastfinishedattempt);
}

/**
 * Internal function used in realtimequiz_get_completion_state. Check minimum attempts requirement for completion.
 *
 * @deprecated since Moodle 3.11
 * @todo MDL-71196 Final deprecation in Moodle 4.3
 * @see \mod_realtimequiz\completion\custom_completion
 * @param int $userid
 * @param stdClass $realtimequiz
 * @return bool True if minimum attempts requirement is disabled or met.
 */
function realtimequiz_completion_check_min_attempts($userid, $realtimequiz) {

    debugging('realtimequiz_completion_check_min_attempts has been deprecated.', DEBUG_DEVELOPER);

    if (empty($realtimequiz->completionminattempts)) {
        return true;
    }

    // Check if the user has done enough attempts.
    $attempts = realtimequiz_get_user_attempts($realtimequiz->id, $userid, 'finished', true);
    return $realtimequiz->completionminattempts <= count($attempts);
}

/**
 * Obtains the automatic completion state for this realtimequiz on any conditions
 * in realtimequiz settings, such as if all attempts are used or a certain grade is achieved.
 *
 * @deprecated since Moodle 3.11
 * @todo MDL-71196 Final deprecation in Moodle 4.3
 * @see \mod_realtimequiz\completion\custom_completion
 * @param stdClass $course Course
 * @param cm_info|stdClass $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function realtimequiz_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // No need to call debugging here. Deprecation debugging notice already being called in \completion_info::internal_get_state().

    $realtimequiz = $DB->get_record('realtimequiz', ['id' => $cm->instance], '*', MUST_EXIST);
    if (!$realtimequiz->completionattemptsexhausted && !$cm->completionpassgrade && !$realtimequiz->completionminattempts) {
        return $type;
    }

    if (!realtimequiz_completion_check_passing_grade_or_all_attempts($course, $cm, $userid, $realtimequiz)) {
        return false;
    }

    if (!realtimequiz_completion_check_min_attempts($userid, $realtimequiz)) {
        return false;
    }

    return true;
}

/**
 * Retrieves tag information for the given list of realtimequiz slot ids.
 * Currently the only slots that have tags are random question slots.
 *
 * Example:
 * If we have 3 slots with id 1, 2, and 3. The first slot has two tags, the second
 * has one tag, and the third has zero tags. The return structure will look like:
 * [
 *      1 => [
 *          realtimequiz_slot_tags.id => { ...tag data... },
 *          realtimequiz_slot_tags.id => { ...tag data... },
 *      ],
 *      2 => [
 *          realtimequiz_slot_tags.id => { ...tag data... },
 *      ],
 *      3 => [],
 * ]
 *
 * @param int[] $slotids The list of id for the realtimequiz slots.
 * @return array[] List of realtimequiz_slot_tags records indexed by slot id.
 * @deprecated since Moodle 4.0
 * @todo Final deprecation on Moodle 4.4 MDL-72438
 */
function realtimequiz_retrieve_tags_for_slot_ids($slotids) {
    debugging('Method realtimequiz_retrieve_tags_for_slot_ids() is deprecated, ' .
        'see filtercondition->tags from the question_set_reference table.', DEBUG_DEVELOPER);
    global $DB;
    if (empty($slotids)) {
        return [];
    }

    $slottags = $DB->get_records_list('realtimequiz_slot_tags', 'slotid', $slotids);
    $tagsbyid = core_tag_tag::get_bulk(array_filter(array_column($slottags, 'tagid')), 'id, name');
    $tagsbyname = false; // It will be loaded later if required.
    $emptytagids = array_reduce($slotids, function($carry, $slotid) {
        $carry[$slotid] = [];
        return $carry;
    }, []);

    return array_reduce(
        $slottags,
        function($carry, $slottag) use ($slottags, $tagsbyid, $tagsbyname) {
            if (isset($tagsbyid[$slottag->tagid])) {
                // Make sure that we're returning the most updated tag name.
                $slottag->tagname = $tagsbyid[$slottag->tagid]->name;
            } else {
                if ($tagsbyname === false) {
                    // We were hoping that this query could be avoided, but life
                    // showed its other side to us!
                    $tagcollid = core_tag_area::get_collection('core', 'question');
                    $tagsbyname = core_tag_tag::get_by_name_bulk(
                        $tagcollid,
                        array_column($slottags, 'tagname'),
                        'id, name'
                    );
                }
                if (isset($tagsbyname[$slottag->tagname])) {
                    // Make sure that we're returning the current tag id that matches
                    // the given tag name.
                    $slottag->tagid = $tagsbyname[$slottag->tagname]->id;
                } else {
                    // The tag does not exist anymore (neither the tag id nor the tag name
                    // matches an existing tag).
                    // We still need to include this row in the result as some callers might
                    // be interested in these rows. An example is the editing forms that still
                    // need to display tag names even if they don't exist anymore.
                    $slottag->tagid = null;
                }
            }

            $carry[$slottag->slotid][$slottag->id] = $slottag;
            return $carry;
        },
        $emptytagids
    );
}

/**
 * Verify that the question exists, and the user has permission to use it.
 *
 * @deprecated in 4.1 use mod_realtimequiz\structure::has_use_capability(...) instead.
 *
 * @param stdClass $realtimequiz the realtimequiz settings.
 * @param int $slot which question in the realtimequiz to test.
 * @return bool whether the user can use this question.
 */
function realtimequiz_has_question_use($realtimequiz, $slot) {
    global $DB;

    debugging('Deprecated. Please use mod_realtimequiz\structure::has_use_capability instead.');

    $sql = 'SELECT q.*
              FROM {realtimequiz_slots} slot
              JOIN {question_references} qre ON qre.itemid = slot.id
              JOIN {question_bank_entries} qbe ON qbe.id = qre.questionbankentryid
              JOIN {question_versions} qve ON qve.questionbankentryid = qbe.id
              JOIN {question} q ON q.id = qve.questionid
             WHERE slot.realtimequizid = ?
               AND slot.slot = ?
               AND qre.component = ?
               AND qre.questionarea = ?';

    $question = $DB->get_record_sql($sql, [$realtimequiz->id, $slot, 'mod_realtimequiz', 'slot']);

    if (!$question) {
        return false;
    }
    return question_has_capability_on($question, 'use');
}

/**
 * @copyright 2012 the Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @deprecated since Moodle 4.2. Code moved to mod_realtimequiz\task\update_overdue_attempts.
 * @todo MDL-76612 Final deprecation in Moodle 4.6
 */
class mod_realtimequiz_overdue_attempt_updater {

    /**
     * @deprecated since Moodle 4.2. Code moved to mod_realtimequiz\task\update_overdue_attempts. that was.
     */
    public function update_overdue_attempts($timenow, $processto) {
        debugging('mod_realtimequiz_overdue_attempt_updater has been deprecated. The code wsa moved to ' .
                'mod_realtimequiz\task\update_overdue_attempts.');
        return (new update_overdue_attempts())->update_all_overdue_attempts((int) $timenow, (int) $processto);
    }

    /**
     * @deprecated since Moodle 4.2. Code moved to mod_realtimequiz\task\update_overdue_attempts.
     */
    public function get_list_of_overdue_attempts($processto) {
        debugging('mod_realtimequiz_overdue_attempt_updater has been deprecated. The code wsa moved to ' .
                'mod_realtimequiz\task\update_overdue_attempts.');
        return (new update_overdue_attempts())->get_list_of_overdue_attempts((int) $processto);
    }
}

/**
 * Class for realtimequiz exceptions. Just saves a couple of arguments on the
 * constructor for a moodle_exception.
 *
 * @copyright 2008 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 * @deprecated since Moodle 4.2. Please just use moodle_exception.
 * @todo MDL-76612 Final deprecation in Moodle 4.6
 */
class moodle_realtimequiz_exception extends moodle_exception {
    /**
     * Constructor.
     *
     * @param realtimequiz_settings $realtimequizobj the realtimequiz the error relates to.
     * @param string $errorcode The name of the string from error.php to print.
     * @param mixed $a Extra words and phrases that might be required in the error string.
     * @param string $link The url where the user will be prompted to continue.
     *      If no url is provided the user will be directed to the site index page.
     * @param string|null $debuginfo optional debugging information.
     * @deprecated since Moodle 4.2. Please just use moodle_exception.
     */
    public function __construct($realtimequizobj, $errorcode, $a = null, $link = '', $debuginfo = null) {
        debugging('Class moodle_realtimequiz_exception is deprecated. ' .
                'Please use a standard moodle_exception instead.', DEBUG_DEVELOPER);
        if (!$link) {
            $link = $realtimequizobj->view_url();
        }
        parent::__construct($errorcode, 'realtimequiz', $link, $a, $debuginfo);
    }
}

/**
 * Update the sumgrades field of the realtimequiz. This needs to be called whenever
 * the grading structure of the realtimequiz is changed. For example if a question is
 * added or removed, or a question weight is changed.
 *
 * You should call {@see realtimequiz_delete_previews()} before you call this function.
 *
 * @param stdClass $realtimequiz a realtimequiz.
 * @deprecated since Moodle 4.2. Please use grade_calculator::recompute_realtimequiz_sumgrades.
 * @todo MDL-76612 Final deprecation in Moodle 4.6
 */
function realtimequiz_update_sumgrades($realtimequiz) {
    debugging('realtimequiz_update_sumgrades is deprecated. ' .
        'Please use a standard grade_calculator::recompute_realtimequiz_sumgrades instead.', DEBUG_DEVELOPER);
    realtimequiz_settings::create($realtimequiz->id)->get_grade_calculator()->recompute_realtimequiz_sumgrades();
}

/**
 * Update the sumgrades field of the attempts at a realtimequiz.
 *
 * @param stdClass $realtimequiz a realtimequiz.
 * @deprecated since Moodle 4.2. Please use grade_calculator::recompute_all_attempt_sumgrades.
 * @todo MDL-76612 Final deprecation in Moodle 4.6
 */
function realtimequiz_update_all_attempt_sumgrades($realtimequiz) {
    debugging('realtimequiz_update_all_attempt_sumgrades is deprecated. ' .
        'Please use a standard grade_calculator::recompute_all_attempt_sumgrades instead.', DEBUG_DEVELOPER);
    realtimequiz_settings::create($realtimequiz->id)->get_grade_calculator()->recompute_all_attempt_sumgrades();
}

/**
 * Update the final grade at this realtimequiz for all students.
 *
 * This function is equivalent to calling realtimequiz_save_best_grade for all
 * users, but much more efficient.
 *
 * @param stdClass $realtimequiz the realtimequiz settings.
 * @deprecated since Moodle 4.2. Please use grade_calculator::recompute_all_final_grades.
 * @todo MDL-76612 Final deprecation in Moodle 4.6
 */
function realtimequiz_update_all_final_grades($realtimequiz) {
    debugging('realtimequiz_update_all_final_grades is deprecated. ' .
        'Please use a standard grade_calculator::recompute_all_final_grades instead.', DEBUG_DEVELOPER);
    realtimequiz_settings::create($realtimequiz->id)->get_grade_calculator()->recompute_all_final_grades();
}

/**
 * The realtimequiz grade is the maximum that student's results are marked out of. When it
 * changes, the corresponding data in realtimequiz_grades and realtimequiz_feedback needs to be
 * rescaled. After calling this function, you probably need to call
 * realtimequiz_update_all_attempt_sumgrades, grade_calculator::recompute_all_final_grades();
 * realtimequiz_update_grades. (At least, that is what this comment has said for years, but
 * it seems to call recompute_all_final_grades itself.)
 *
 * @param float $newgrade the new maximum grade for the realtimequiz.
 * @param stdClass $realtimequiz the realtimequiz we are updating. Passed by reference so its
 *      grade field can be updated too.
 * @return bool indicating success or failure.
 * @deprecated since Moodle 4.2. Please use grade_calculator::update_realtimequiz_maximum_grade.
 * @todo MDL-76612 Final deprecation in Moodle 4.6
 */
function realtimequiz_set_grade($newgrade, $realtimequiz) {
    debugging('realtimequiz_set_grade is deprecated. ' .
        'Please use a standard grade_calculator::update_realtimequiz_maximum_grade instead.', DEBUG_DEVELOPER);
    realtimequiz_settings::create($realtimequiz->id)->get_grade_calculator()->update_realtimequiz_maximum_grade($newgrade);
    return true;
}

/**
 * Save the overall grade for a user at a realtimequiz in the realtimequiz_grades table
 *
 * @param stdClass $realtimequiz The realtimequiz for which the best grade is to be calculated and then saved.
 * @param int $userid The userid to calculate the grade for. Defaults to the current user.
 * @param array $attempts The attempts of this user. Useful if you are
 * looping through many users. Attempts can be fetched in one master query to
 * avoid repeated querying.
 * @return bool Indicates success or failure.
 * @deprecated since Moodle 4.2. Please use grade_calculator::update_realtimequiz_maximum_grade.
 * @todo MDL-76612 Final deprecation in Moodle 4.6
 */
function realtimequiz_save_best_grade($realtimequiz, $userid = null, $attempts = []) {
    debugging('realtimequiz_save_best_grade is deprecated. ' .
        'Please use a standard grade_calculator::recompute_final_grade instead.', DEBUG_DEVELOPER);
    realtimequiz_settings::create($realtimequiz->id)->get_grade_calculator()->recompute_final_grade($userid, $attempts);
    return true;
}

/**
 * Calculate the overall grade for a realtimequiz given a number of attempts by a particular user.
 *
 * @param stdClass $realtimequiz    the realtimequiz settings object.
 * @param array $attempts an array of all the user's attempts at this realtimequiz in order.
 * @return float          the overall grade
 * @deprecated since Moodle 4.2. No direct replacement.
 * @todo MDL-76612 Final deprecation in Moodle 4.6
 */
function realtimequiz_calculate_best_grade($realtimequiz, $attempts) {
    debugging('realtimequiz_calculate_best_grade is deprecated with no direct replacement. It was only used ' .
        'in one place in the realtimequiz code so this logic is now private to grade_calculator.', DEBUG_DEVELOPER);

    switch ($realtimequiz->grademethod) {

        case QUIZ_ATTEMPTFIRST:
            $firstattempt = reset($attempts);
            return $firstattempt->sumgrades;

        case QUIZ_ATTEMPTLAST:
            $lastattempt = end($attempts);
            return $lastattempt->sumgrades;

        case QUIZ_GRADEAVERAGE:
            $sum = 0;
            $count = 0;
            foreach ($attempts as $attempt) {
                if (!is_null($attempt->sumgrades)) {
                    $sum += $attempt->sumgrades;
                    $count++;
                }
            }
            if ($count == 0) {
                return null;
            }
            return $sum / $count;

        case QUIZ_GRADEHIGHEST:
        default:
            $max = null;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                }
            }
            return $max;
    }
}

/**
 * Return the attempt with the best grade for a realtimequiz
 *
 * Which attempt is the best depends on $realtimequiz->grademethod. If the grade
 * method is GRADEAVERAGE then this function simply returns the last attempt.
 * @return stdClass         The attempt with the best grade
 * @param stdClass $realtimequiz    The realtimequiz for which the best grade is to be calculated
 * @param array $attempts An array of all the attempts of the user at the realtimequiz
 * @deprecated since Moodle 4.2. No direct replacement.
 * @todo MDL-76612 Final deprecation in Moodle 4.6
 */
function realtimequiz_calculate_best_attempt($realtimequiz, $attempts) {
    debugging('realtimequiz_calculate_best_attempt is deprecated with no direct replacement. ' .
        'It was not used anywhere!', DEBUG_DEVELOPER);

    switch ($realtimequiz->grademethod) {

        case QUIZ_ATTEMPTFIRST:
            foreach ($attempts as $attempt) {
                return $attempt;
            }
            break;

        case QUIZ_GRADEAVERAGE: // We need to do something with it.
        case QUIZ_ATTEMPTLAST:
            foreach ($attempts as $attempt) {
                $final = $attempt;
            }
            return $final;

        default:
        case QUIZ_GRADEHIGHEST:
            $max = -1;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                    $maxattempt = $attempt;
                }
            }
            return $maxattempt;
    }
}
