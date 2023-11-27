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

namespace mod_realtimequiz;

use coding_exception;
use mod_realtimequiz\event\realtimequiz_grade_updated;
use question_engine_data_mapper;
use stdClass;

/**
 * This class contains all the logic for computing the grade of a realtimequiz.
 *
 * There are two sorts of calculation which need to be done. For a single
 * attempt, we need to compute the total attempt score from score for each question.
 * And for a realtimequiz user, we need to compute the final grade from all the separate attempt grades.
 *
 * @package   mod_realtimequiz
 * @copyright 2023 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_calculator {

    /** @var float a number that is effectively zero. Used to avoid division-by-zero or underflow problems. */
    const ALMOST_ZERO = 0.000005;

    /** @var realtimequiz_settings the realtimequiz for which this instance computes grades. */
    protected $realtimequizobj;

    /**
     * Constructor. Recommended way to get an instance is $realtimequizobj->get_grade_calculator();
     *
     * @param realtimequiz_settings $realtimequizobj
     */
    protected function __construct(realtimequiz_settings $realtimequizobj) {
        $this->realtimequizobj = $realtimequizobj;
    }

    /**
     * Factory. The recommended way to get an instance is $realtimequizobj->get_grade_calculator();
     *
     * @param realtimequiz_settings $realtimequizobj settings of a realtimequiz.
     * @return grade_calculator instance of this class for the given realtimequiz.
     */
    public static function create(realtimequiz_settings $realtimequizobj): grade_calculator {
        return new self($realtimequizobj);
    }

    /**
     * Update the sumgrades field of the realtimequiz.
     *
     * This needs to be called whenever the grading structure of the realtimequiz is changed.
     * For example if a question is added or removed, or a question weight is changed.
     *
     * You should call {@see realtimequiz_delete_previews()} before you call this function.
     */
    public function recompute_realtimequiz_sumgrades(): void {
        global $DB;
        $realtimequiz = $this->realtimequizobj->get_realtimequiz();

        // Update sumgrades in the database.
        $DB->execute("
                UPDATE {realtimequiz}
                   SET sumgrades = COALESCE((
                        SELECT SUM(maxmark)
                          FROM {realtimequiz_slots}
                         WHERE realtimequizid = {realtimequiz}.id
                       ), 0)
                 WHERE id = ?
             ", [$realtimequiz->id]);

        // Update the value in memory.
        $realtimequiz->sumgrades = $DB->get_field('realtimequiz', 'sumgrades', ['id' => $realtimequiz->id]);

        if ($realtimequiz->sumgrades < self::ALMOST_ZERO && realtimequiz_has_attempts($realtimequiz->id)) {
            // If the realtimequiz has been attempted, and the sumgrades has been
            // set to 0, then we must also set the maximum possible grade to 0, or
            // we will get a divide by zero error.
            self::update_realtimequiz_maximum_grade(0);
        }
    }

    /**
     * Update the sumgrades field of attempts at this realtimequiz.
     */
    public function recompute_all_attempt_sumgrades(): void {
        global $DB;
        $dm = new question_engine_data_mapper();
        $timenow = time();

        $DB->execute("
                UPDATE {realtimequiz_attempts}
                   SET timemodified = :timenow,
                       sumgrades = (
                           {$dm->sum_usage_marks_subquery('uniqueid')}
                       )
                 WHERE realtimequiz = :realtimequizid AND state = :finishedstate
            ", [
                'timenow' => $timenow,
                'realtimequizid' => $this->realtimequizobj->get_realtimequizid(),
                'finishedstate' => realtimequiz_attempt::FINISHED
            ]);
    }

    /**
     * Update the final grade at this realtimequiz for a particular student.
     *
     * That is, given the realtimequiz settings, and all the attempts this user has made,
     * compute their final grade for the realtimequiz, as shown in the gradebook.
     *
     * The $attempts parameter is for efficiency. If you already have the data for
     * all this user's attempts loaded (for example from {@see realtimequiz_get_user_attempts()}
     * or because you are looping through a large recordset fetched in one efficient query,
     * then you can pass that data here to save DB queries.
     *
     * @param int|null $userid The userid to calculate the grade for. Defaults to the current user.
     * @param array $attempts if you already have this user's attempt records loaded, pass them here to save queries.
     */
    public function recompute_final_grade(?int $userid = null, array $attempts = []): void {
        global $DB, $USER;
        $realtimequiz = $this->realtimequizobj->get_realtimequiz();

        if (empty($userid)) {
            $userid = $USER->id;
        }

        if (!$attempts) {
            // Get all the attempts made by the user.
            $attempts = realtimequiz_get_user_attempts($realtimequiz->id, $userid);
        }

        // Calculate the best grade.
        $bestgrade = $this->compute_final_grade_from_attempts($attempts);
        $bestgrade = realtimequiz_rescale_grade($bestgrade, $realtimequiz, false);

        // Save the best grade in the database.
        if (is_null($bestgrade)) {
            $DB->delete_records('realtimequiz_grades', ['realtimequiz' => $realtimequiz->id, 'userid' => $userid]);

        } else if ($grade = $DB->get_record('realtimequiz_grades',
                ['realtimequiz' => $realtimequiz->id, 'userid' => $userid])) {
            $grade->grade = $bestgrade;
            $grade->timemodified = time();
            $DB->update_record('realtimequiz_grades', $grade);

        } else {
            $grade = new stdClass();
            $grade->realtimequiz = $realtimequiz->id;
            $grade->userid = $userid;
            $grade->grade = $bestgrade;
            $grade->timemodified = time();
            $DB->insert_record('realtimequiz_grades', $grade);
        }

        realtimequiz_update_grades($realtimequiz, $userid);
    }

    /**
     * Calculate the overall grade for a realtimequiz given a number of attempts by a particular user.
     *
     * @param array $attempts an array of all the user's attempts at this realtimequiz in order.
     * @return float|null the overall grade, or null if the user does not have a grade.
     */
    protected function compute_final_grade_from_attempts(array $attempts): ?float {

        $grademethod = $this->realtimequizobj->get_realtimequiz()->grademethod;
        switch ($grademethod) {

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
                $max = null;
                foreach ($attempts as $attempt) {
                    if ($attempt->sumgrades > $max) {
                        $max = $attempt->sumgrades;
                    }
                }
                return $max;

            default:
                throw new coding_exception('Unrecognised grading method ' . $grademethod);
        }
    }

    /**
     * Update the final grade at this realtimequiz for all students.
     *
     * This function is equivalent to calling {@see recompute_final_grade()} for all
     * users who have attempted the realtimequiz, but is much more efficient.
     */
    public function recompute_all_final_grades(): void {
        global $DB;
        $realtimequiz = $this->realtimequizobj->get_realtimequiz();

        // If the realtimequiz does not contain any graded questions, then there is nothing to do.
        if (!$realtimequiz->sumgrades) {
            return;
        }

        $param = ['irealtimequizid' => $realtimequiz->id, 'istatefinished' => realtimequiz_attempt::FINISHED];
        $firstlastattemptjoin = "JOIN (
                SELECT
                    irealtimequiza.userid,
                    MIN(attempt) AS firstattempt,
                    MAX(attempt) AS lastattempt

                FROM {realtimequiz_attempts} irealtimequiza

                WHERE
                    irealtimequiza.state = :istatefinished AND
                    irealtimequiza.preview = 0 AND
                    irealtimequiza.realtimequiz = :irealtimequizid

                GROUP BY irealtimequiza.userid
            ) first_last_attempts ON first_last_attempts.userid = realtimequiza.userid";

        switch ($realtimequiz->grademethod) {
            case QUIZ_ATTEMPTFIRST:
                // Because of the where clause, there will only be one row, but we
                // must still use an aggregate function.
                $select = 'MAX(realtimequiza.sumgrades)';
                $join = $firstlastattemptjoin;
                $where = 'realtimequiza.attempt = first_last_attempts.firstattempt AND';
                break;

            case QUIZ_ATTEMPTLAST:
                // Because of the where clause, there will only be one row, but we
                // must still use an aggregate function.
                $select = 'MAX(realtimequiza.sumgrades)';
                $join = $firstlastattemptjoin;
                $where = 'realtimequiza.attempt = first_last_attempts.lastattempt AND';
                break;

            case QUIZ_GRADEAVERAGE:
                $select = 'AVG(realtimequiza.sumgrades)';
                $join = '';
                $where = '';
                break;

            default:
            case QUIZ_GRADEHIGHEST:
                $select = 'MAX(realtimequiza.sumgrades)';
                $join = '';
                $where = '';
                break;
        }

        if ($realtimequiz->sumgrades >= self::ALMOST_ZERO) {
            $finalgrade = $select . ' * ' . ($realtimequiz->grade / $realtimequiz->sumgrades);
        } else {
            $finalgrade = '0';
        }
        $param['realtimequizid'] = $realtimequiz->id;
        $param['realtimequizid2'] = $realtimequiz->id;
        $param['realtimequizid3'] = $realtimequiz->id;
        $param['realtimequizid4'] = $realtimequiz->id;
        $param['statefinished'] = realtimequiz_attempt::FINISHED;
        $param['statefinished2'] = realtimequiz_attempt::FINISHED;
        $param['almostzero'] = self::ALMOST_ZERO;
        $finalgradesubquery = "
                SELECT realtimequiza.userid, $finalgrade AS newgrade
                FROM {realtimequiz_attempts} realtimequiza
                $join
                WHERE
                    $where
                    realtimequiza.state = :statefinished AND
                    realtimequiza.preview = 0 AND
                    realtimequiza.realtimequiz = :realtimequizid3
                GROUP BY realtimequiza.userid";

        $changedgrades = $DB->get_records_sql("
                SELECT users.userid, qg.id, qg.grade, newgrades.newgrade

                FROM (
                    SELECT userid
                    FROM {realtimequiz_grades} qg
                    WHERE realtimequiz = :realtimequizid
                UNION
                    SELECT DISTINCT userid
                    FROM {realtimequiz_attempts} realtimequiza2
                    WHERE
                        realtimequiza2.state = :statefinished2 AND
                        realtimequiza2.preview = 0 AND
                        realtimequiza2.realtimequiz = :realtimequizid2
                ) users

                LEFT JOIN {realtimequiz_grades} qg ON qg.userid = users.userid AND qg.realtimequiz = :realtimequizid4

                LEFT JOIN (
                    $finalgradesubquery
                ) newgrades ON newgrades.userid = users.userid

                WHERE
                    ABS(newgrades.newgrade - qg.grade) > :almostzero OR
                    ((newgrades.newgrade IS NULL OR qg.grade IS NULL) AND NOT
                              (newgrades.newgrade IS NULL AND qg.grade IS NULL))",
                    // The mess on the previous line is detecting where the value is
                    // NULL in one column, and NOT NULL in the other, but SQL does
                    // not have an XOR operator, and MS SQL server can't cope with
                    // (newgrades.newgrade IS NULL) <> (qg.grade IS NULL).
                $param);

        $timenow = time();
        $todelete = [];
        foreach ($changedgrades as $changedgrade) {

            if (is_null($changedgrade->newgrade)) {
                $todelete[] = $changedgrade->userid;

            } else if (is_null($changedgrade->grade)) {
                $toinsert = new stdClass();
                $toinsert->realtimequiz = $realtimequiz->id;
                $toinsert->userid = $changedgrade->userid;
                $toinsert->timemodified = $timenow;
                $toinsert->grade = $changedgrade->newgrade;
                $DB->insert_record('realtimequiz_grades', $toinsert);

            } else {
                $toupdate = new stdClass();
                $toupdate->id = $changedgrade->id;
                $toupdate->grade = $changedgrade->newgrade;
                $toupdate->timemodified = $timenow;
                $DB->update_record('realtimequiz_grades', $toupdate);
            }
        }

        if (!empty($todelete)) {
            list($test, $params) = $DB->get_in_or_equal($todelete);
            $DB->delete_records_select('realtimequiz_grades', 'realtimequiz = ? AND userid ' . $test,
                    array_merge([$realtimequiz->id], $params));
        }
    }

    /**
     * Update the realtimequiz setting for the grade the realtimequiz is out of.
     *
     * This function will update the data in realtimequiz_grades and realtimequiz_feedback, and
     * pass the new grades on to the gradebook.
     *
     * @param float $newgrade the new maximum grade for the realtimequiz.
     */
    public function update_realtimequiz_maximum_grade(float $newgrade): void {
        global $DB;
        $realtimequiz = $this->realtimequizobj->get_realtimequiz();

        // This is potentially expensive, so only do it if necessary.
        if (abs($realtimequiz->grade - $newgrade) < self::ALMOST_ZERO) {
            // Nothing to do.
            return;
        }

        // Use a transaction.
        $transaction = $DB->start_delegated_transaction();

        // Update the realtimequiz table.
        $oldgrade = $realtimequiz->grade;
        $realtimequiz->grade = $newgrade;
        $timemodified = time();
        $DB->update_record('realtimequiz', (object) [
            'id' => $realtimequiz->id,
            'grade' => $newgrade,
            'timemodified' => $timemodified,
        ]);

        // Rescale the grade of all realtimequiz attempts.
        if ($oldgrade < $newgrade) {
            // The new total is bigger, so we need to recompute fully to avoid underflow problems.
            $this->recompute_all_final_grades();

        } else {
            // New total smaller, so we can rescale the grades efficiently.
            $DB->execute("
                    UPDATE {realtimequiz_grades}
                       SET grade = ? * grade, timemodified = ?
                     WHERE realtimequiz = ?
            ", [$newgrade / $oldgrade, $timemodified, $realtimequiz->id]);
        }

        // Rescale the overall feedback boundaries.
        if ($oldgrade > self::ALMOST_ZERO) {
            // Update the realtimequiz_feedback table.
            $factor = $newgrade / $oldgrade;
            $DB->execute("
                    UPDATE {realtimequiz_feedback}
                    SET mingrade = ? * mingrade, maxgrade = ? * maxgrade
                    WHERE realtimequizid = ?
            ", [$factor, $factor, $realtimequiz->id]);
        }

        // Update grade item and send all grades to gradebook.
        realtimequiz_grade_item_update($realtimequiz);
        realtimequiz_update_grades($realtimequiz);

        // Log realtimequiz grade updated event.
        realtimequiz_grade_updated::create([
            'context' => $this->realtimequizobj->get_context(),
            'objectid' => $realtimequiz->id,
            'other' => [
                'oldgrade' => $oldgrade + 0, // Remove trailing 0s.
                'newgrade' => $newgrade,
            ]
        ])->trigger();

        $transaction->allow_commit();
    }
}
