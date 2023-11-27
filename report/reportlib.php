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
 * Helper functions for the realtimequiz reports.
 *
 * @package   mod_realtimequiz
 * @copyright 2008 Jamie Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/realtimequiz/lib.php');
require_once($CFG->libdir . '/filelib.php');

use mod_realtimequiz\question\display_options;

/**
 * Takes an array of objects and constructs a multidimensional array keyed by
 * the keys it finds on the object.
 * @param array $datum an array of objects with properties on the object
 * including the keys passed as the next param.
 * @param array $keys Array of strings with the names of the properties on the
 * objects in datum that you want to index the multidimensional array by.
 * @param bool $keysunique If there is not only one object for each
 * combination of keys you are using you should set $keysunique to true.
 * Otherwise all the object will be added to a zero based array. So the array
 * returned will have count($keys) + 1 indexs.
 * @return array multidimensional array properly indexed.
 */
function realtimequiz_report_index_by_keys($datum, $keys, $keysunique = true) {
    if (!$datum) {
        return [];
    }
    $key = array_shift($keys);
    $datumkeyed = [];
    foreach ($datum as $data) {
        if ($keys || !$keysunique) {
            $datumkeyed[$data->{$key}][]= $data;
        } else {
            $datumkeyed[$data->{$key}]= $data;
        }
    }
    if ($keys) {
        foreach ($datumkeyed as $datakey => $datakeyed) {
            $datumkeyed[$datakey] = realtimequiz_report_index_by_keys($datakeyed, $keys, $keysunique);
        }
    }
    return $datumkeyed;
}

function realtimequiz_report_unindex($datum) {
    if (!$datum) {
        return $datum;
    }
    $datumunkeyed = [];
    foreach ($datum as $value) {
        if (is_array($value)) {
            $datumunkeyed = array_merge($datumunkeyed, realtimequiz_report_unindex($value));
        } else {
            $datumunkeyed[] = $value;
        }
    }
    return $datumunkeyed;
}

/**
 * Are there any questions in this realtimequiz?
 * @param int $realtimequizid the realtimequiz id.
 */
function realtimequiz_has_questions($realtimequizid) {
    global $DB;
    return $DB->record_exists('realtimequiz_slots', ['realtimequizid' => $realtimequizid]);
}

/**
 * Get the slots of real questions (not descriptions) in this realtimequiz, in order.
 * @param stdClass $realtimequiz the realtimequiz.
 * @return array of slot => objects with fields
 *      ->slot, ->id, ->qtype, ->length, ->number, ->maxmark, ->category (for random questions).
 */
function realtimequiz_report_get_significant_questions($realtimequiz) {
    $realtimequizobj = mod_realtimequiz\realtimequiz_settings::create($realtimequiz->id);
    $structure = \mod_realtimequiz\structure::create_for_realtimequiz($realtimequizobj);
    $slots = $structure->get_slots();

    $qsbyslot = [];
    $number = 1;
    foreach ($slots as $slot) {
        // Ignore 'questions' of zero length.
        if ($slot->length == 0) {
            continue;
        }

        $slotreport = new \stdClass();
        $slotreport->slot = $slot->slot;
        $slotreport->id = $slot->questionid;
        $slotreport->qtype = $slot->qtype;
        $slotreport->length = $slot->length;
        $slotreport->number = $number;
        $number += $slot->length;
        $slotreport->maxmark = $slot->maxmark;
        $slotreport->category = $slot->category;

        $qsbyslot[$slotreport->slot] = $slotreport;
    }

    return $qsbyslot;
}

/**
 * @param stdClass $realtimequiz the realtimequiz settings.
 * @return bool whether, for this realtimequiz, it is possible to filter attempts to show
 *      only those that gave the final grade.
 */
function realtimequiz_report_can_filter_only_graded($realtimequiz) {
    return $realtimequiz->attempts != 1 && $realtimequiz->grademethod != QUIZ_GRADEAVERAGE;
}

/**
 * This is a wrapper for {@link realtimequiz_report_grade_method_sql} that takes the whole realtimequiz object instead of just the grading method
 * as a param. See definition for {@link realtimequiz_report_grade_method_sql} below.
 *
 * @param stdClass $realtimequiz
 * @param string $realtimequizattemptsalias sql alias for 'realtimequiz_attempts' table
 * @return string sql to test if this is an attempt that will contribute towards the grade of the user
 */
function realtimequiz_report_qm_filter_select($realtimequiz, $realtimequizattemptsalias = 'realtimequiza') {
    if ($realtimequiz->attempts == 1) {
        // This realtimequiz only allows one attempt.
        return '';
    }
    return realtimequiz_report_grade_method_sql($realtimequiz->grademethod, $realtimequizattemptsalias);
}

/**
 * Given a realtimequiz grading method return sql to test if this is an
 * attempt that will be contribute towards the grade of the user. Or return an
 * empty string if the grading method is QUIZ_GRADEAVERAGE and thus all attempts
 * contribute to final grade.
 *
 * @param string $grademethod realtimequiz grading method.
 * @param string $realtimequizattemptsalias sql alias for 'realtimequiz_attempts' table
 * @return string sql to test if this is an attempt that will contribute towards the graded of the user
 */
function realtimequiz_report_grade_method_sql($grademethod, $realtimequizattemptsalias = 'realtimequiza') {
    switch ($grademethod) {
        case QUIZ_GRADEHIGHEST :
            return "($realtimequizattemptsalias.state = 'finished' AND NOT EXISTS (
                           SELECT 1 FROM {realtimequiz_attempts} qa2
                            WHERE qa2.realtimequiz = $realtimequizattemptsalias.realtimequiz AND
                                qa2.userid = $realtimequizattemptsalias.userid AND
                                 qa2.state = 'finished' AND (
                COALESCE(qa2.sumgrades, 0) > COALESCE($realtimequizattemptsalias.sumgrades, 0) OR
               (COALESCE(qa2.sumgrades, 0) = COALESCE($realtimequizattemptsalias.sumgrades, 0) AND qa2.attempt < $realtimequizattemptsalias.attempt)
                                )))";

        case QUIZ_GRADEAVERAGE :
            return '';

        case QUIZ_ATTEMPTFIRST :
            return "($realtimequizattemptsalias.state = 'finished' AND NOT EXISTS (
                           SELECT 1 FROM {realtimequiz_attempts} qa2
                            WHERE qa2.realtimequiz = $realtimequizattemptsalias.realtimequiz AND
                                qa2.userid = $realtimequizattemptsalias.userid AND
                                 qa2.state = 'finished' AND
                               qa2.attempt < $realtimequizattemptsalias.attempt))";

        case QUIZ_ATTEMPTLAST :
            return "($realtimequizattemptsalias.state = 'finished' AND NOT EXISTS (
                           SELECT 1 FROM {realtimequiz_attempts} qa2
                            WHERE qa2.realtimequiz = $realtimequizattemptsalias.realtimequiz AND
                                qa2.userid = $realtimequizattemptsalias.userid AND
                                 qa2.state = 'finished' AND
                               qa2.attempt > $realtimequizattemptsalias.attempt))";
    }
}

/**
 * Get the number of students whose score was in a particular band for this realtimequiz.
 * @param number $bandwidth the width of each band.
 * @param int $bands the number of bands
 * @param int $realtimequizid the realtimequiz id.
 * @param \core\dml\sql_join $usersjoins (joins, wheres, params) to get enrolled users
 * @return array band number => number of users with scores in that band.
 */
function realtimequiz_report_grade_bands($bandwidth, $bands, $realtimequizid, \core\dml\sql_join $usersjoins = null) {
    global $DB;
    if (!is_int($bands)) {
        debugging('$bands passed to realtimequiz_report_grade_bands must be an integer. (' .
                gettype($bands) . ' passed.)', DEBUG_DEVELOPER);
        $bands = (int) $bands;
    }

    if ($usersjoins && !empty($usersjoins->joins)) {
        $userjoin = "JOIN {user} u ON u.id = qg.userid
                {$usersjoins->joins}";
        $usertest = $usersjoins->wheres;
        $params = $usersjoins->params;
    } else {
        $userjoin = '';
        $usertest = '1=1';
        $params = [];
    }
    $sql = "
SELECT band, COUNT(1)

FROM (
    SELECT FLOOR(qg.grade / :bandwidth) AS band
      FROM {realtimequiz_grades} qg
    $userjoin
    WHERE $usertest AND qg.realtimequiz = :realtimequizid
) subquery

GROUP BY
    band

ORDER BY
    band";

    $params['realtimequizid'] = $realtimequizid;
    $params['bandwidth'] = $bandwidth;

    $data = $DB->get_records_sql_menu($sql, $params);

    // We need to create array elements with values 0 at indexes where there is no element.
    $data = $data + array_fill(0, $bands + 1, 0);
    ksort($data);

    // Place the maximum (perfect grade) into the last band i.e. make last
    // band for example 9 <= g <=10 (where 10 is the perfect grade) rather than
    // just 9 <= g <10.
    $data[$bands - 1] += $data[$bands];
    unset($data[$bands]);

    return $data;
}

function realtimequiz_report_highlighting_grading_method($realtimequiz, $qmsubselect, $qmfilter) {
    if ($realtimequiz->attempts == 1) {
        return '<p>' . get_string('onlyoneattemptallowed', 'realtimequiz_overview') . '</p>';

    } else if (!$qmsubselect) {
        return '<p>' . get_string('allattemptscontributetograde', 'realtimequiz_overview') . '</p>';

    } else if ($qmfilter) {
        return '<p>' . get_string('showinggraded', 'realtimequiz_overview') . '</p>';

    } else {
        return '<p>' . get_string('showinggradedandungraded', 'realtimequiz_overview',
                '<span class="gradedattempt">' . realtimequiz_get_grading_option_name($realtimequiz->grademethod) .
                '</span>') . '</p>';
    }
}

/**
 * Get the feedback text for a grade on this realtimequiz. The feedback is
 * processed ready for display.
 *
 * @param float $grade a grade on this realtimequiz.
 * @param int $realtimequizid the id of the realtimequiz object.
 * @return string the comment that corresponds to this grade (empty string if there is not one.
 */
function realtimequiz_report_feedback_for_grade($grade, $realtimequizid, $context) {
    global $DB;

    static $feedbackcache = [];

    if (!isset($feedbackcache[$realtimequizid])) {
        $feedbackcache[$realtimequizid] = $DB->get_records('realtimequiz_feedback', ['realtimequizid' => $realtimequizid]);
    }

    // With CBM etc, it is possible to get -ve grades, which would then not match
    // any feedback. Therefore, we replace -ve grades with 0.
    $grade = max($grade, 0);

    $feedbacks = $feedbackcache[$realtimequizid];
    $feedbackid = 0;
    $feedbacktext = '';
    $feedbacktextformat = FORMAT_MOODLE;
    foreach ($feedbacks as $feedback) {
        if ($feedback->mingrade <= $grade && $grade < $feedback->maxgrade) {
            $feedbackid = $feedback->id;
            $feedbacktext = $feedback->feedbacktext;
            $feedbacktextformat = $feedback->feedbacktextformat;
            break;
        }
    }

    // Clean the text, ready for display.
    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $feedbacktext = file_rewrite_pluginfile_urls($feedbacktext, 'pluginfile.php',
            $context->id, 'mod_realtimequiz', 'feedback', $feedbackid);
    $feedbacktext = format_text($feedbacktext, $feedbacktextformat, $formatoptions);

    return $feedbacktext;
}

/**
 * Format a number as a percentage out of $realtimequiz->sumgrades
 * @param number $rawgrade the mark to format.
 * @param stdClass $realtimequiz the realtimequiz settings
 * @param bool $round whether to round the results ot $realtimequiz->decimalpoints.
 */
function realtimequiz_report_scale_summarks_as_percentage($rawmark, $realtimequiz, $round = true) {
    if ($realtimequiz->sumgrades == 0) {
        return '';
    }
    if (!is_numeric($rawmark)) {
        return $rawmark;
    }

    $mark = $rawmark * 100 / $realtimequiz->sumgrades;
    if ($round) {
        $mark = realtimequiz_format_grade($realtimequiz, $mark);
    }

    return get_string('percents', 'moodle', $mark);
}

/**
 * Returns an array of reports to which the current user has access to.
 * @return array reports are ordered as they should be for display in tabs.
 */
function realtimequiz_report_list($context) {
    global $DB;
    static $reportlist = null;
    if (!is_null($reportlist)) {
        return $reportlist;
    }

    $reports = $DB->get_records('realtimequiz_reports', null, 'displayorder DESC', 'name, capability');
    $reportdirs = core_component::get_plugin_list('realtimequiz');

    // Order the reports tab in descending order of displayorder.
    $reportcaps = [];
    foreach ($reports as $key => $report) {
        if (array_key_exists($report->name, $reportdirs)) {
            $reportcaps[$report->name] = $report->capability;
        }
    }

    // Add any other reports, which are on disc but not in the DB, on the end.
    foreach ($reportdirs as $reportname => $notused) {
        if (!isset($reportcaps[$reportname])) {
            $reportcaps[$reportname] = null;
        }
    }
    $reportlist = [];
    foreach ($reportcaps as $name => $capability) {
        if (empty($capability)) {
            $capability = 'mod/realtimequiz:viewreports';
        }
        if (has_capability($capability, $context)) {
            $reportlist[] = $name;
        }
    }
    return $reportlist;
}

/**
 * Create a filename for use when downloading data from a realtimequiz report. It is
 * expected that this will be passed to flexible_table::is_downloading, which
 * cleans the filename of bad characters and adds the file extension.
 * @param string $report the type of report.
 * @param string $courseshortname the course shortname.
 * @param string $realtimequizname the realtimequiz name.
 * @return string the filename.
 */
function realtimequiz_report_download_filename($report, $courseshortname, $realtimequizname) {
    return $courseshortname . '-' . format_string($realtimequizname, true) . '-' . $report;
}

/**
 * Get the default report for the current user.
 * @param stdClass $context the realtimequiz context.
 */
function realtimequiz_report_default_report($context) {
    $reports = realtimequiz_report_list($context);
    return reset($reports);
}

/**
 * Generate a message saying that this realtimequiz has no questions, with a button to
 * go to the edit page, if the user has the right capability.
 * @param stdClass $realtimequiz the realtimequiz settings.
 * @param stdClass $cm the course_module object.
 * @param stdClass $context the realtimequiz context.
 * @return string HTML to output.
 */
function realtimequiz_no_questions_message($realtimequiz, $cm, $context) {
    global $OUTPUT;

    $output = '';
    $output .= $OUTPUT->notification(get_string('noquestions', 'realtimequiz'));
    if (has_capability('mod/realtimequiz:manage', $context)) {
        $output .= $OUTPUT->single_button(new moodle_url('/mod/realtimequiz/edit.php',
        ['cmid' => $cm->id]), get_string('editrealtimequiz', 'realtimequiz'), 'get');
    }

    return $output;
}

/**
 * Should the grades be displayed in this report. That depends on the realtimequiz
 * display options, and whether the realtimequiz is graded.
 * @param stdClass $realtimequiz the realtimequiz settings.
 * @param context $context the realtimequiz context.
 * @return bool
 */
function realtimequiz_report_should_show_grades($realtimequiz, context $context) {
    if ($realtimequiz->timeclose && time() > $realtimequiz->timeclose) {
        $when = display_options::AFTER_CLOSE;
    } else {
        $when = display_options::LATER_WHILE_OPEN;
    }
    $reviewoptions = display_options::make_from_realtimequiz($realtimequiz, $when);

    return realtimequiz_has_grades($realtimequiz) &&
            ($reviewoptions->marks >= question_display_options::MARK_AND_MAX ||
            has_capability('moodle/grade:viewhidden', $context));
}
