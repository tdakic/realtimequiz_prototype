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
 * Common functions for the realtimequiz statistics report.
 *
 * @package    realtimequiz_statistics
 * @copyright  2013 The Open University
 * @author     James Pratt me@jamiep.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\realtimequiz_attempt;

defined('MOODLE_INTERNAL') || die;

/**
 * SQL to fetch relevant 'realtimequiz_attempts' records.
 *
 * @param int    $realtimequizid        realtimequiz id to get attempts for
 * @param \core\dml\sql_join $groupstudentsjoins Contains joins, wheres, params, empty if not using groups
 * @param string $whichattempts which attempts to use, represented internally as one of the constants as used in
 *                                   $realtimequiz->grademethod ie.
 *                                   QUIZ_GRADEAVERAGE, QUIZ_GRADEHIGHEST, QUIZ_ATTEMPTLAST or QUIZ_ATTEMPTFIRST
 *                                   we calculate stats based on which attempts would affect the grade for each student.
 * @param bool   $includeungraded whether to fetch ungraded attempts too
 * @return array FROM and WHERE sql fragments and sql params
 */
function realtimequiz_statistics_attempts_sql($realtimequizid, \core\dml\sql_join $groupstudentsjoins,
        $whichattempts = QUIZ_GRADEAVERAGE, $includeungraded = false) {

    if ($whichattempts == realtimequiz_attempt::IN_PROGRESS)
    {
      $fromqa = "{realtimequiz_attempts} realtimequiza ";
      //TTT $whereqa = 'realtimequiza.realtimequiz = :realtimequizid AND realtimequiza.preview = 0 AND realtimequiza.state = :realtimequizstatefinished';
      $whereqa = 'realtimequiza.realtimequiz = :realtimequizid AND realtimequiza.preview = 0 AND realtimequiza.state = :realtimequiz_state'; // didn't work
      //$whereqa = 'realtimequiza.realtimequiz = :realtimequizid AND realtimequiza.preview = 0';
      //TTT $qaparams = ['realtimequizid' => (int)$realtimequizid, 'realtimequizstatefinished' => realtimequiz_attempt::FINISHED];
      $qaparams = ['realtimequizid' => (int)$realtimequizid, 'realtimequiz_state' => realtimequiz_attempt::IN_PROGRESS];


      //TTT
      //$whichattempts = QUIZ_ATTEMPTLAST;
      //$whichattempts = IN_PROGRESS; not defined
      $whichattempts = realtimequiz_attempt::IN_PROGRESS;

      $includeungraded = true;
   }

   else {
     $fromqa = "{realtimequiz_attempts} realtimequiza ";
     $whereqa = 'realtimequiza.realtimequiz = :realtimequizid AND realtimequiza.preview = 0 AND realtimequiza.state = :realtimequizstatefinished';
     $qaparams = ['realtimequizid' => (int)$realtimequizid, 'realtimequizstatefinished' => realtimequiz_attempt::FINISHED];
    }

    if (!empty($groupstudentsjoins->joins)) {
        $fromqa .= "\nJOIN {user} u ON u.id = realtimequiza.userid
            {$groupstudentsjoins->joins} ";
        $whereqa .= " AND {$groupstudentsjoins->wheres}";
        $qaparams += $groupstudentsjoins->params;
    }


    $whichattemptsql = realtimequiz_report_grade_method_sql($whichattempts);
    if ($whichattemptsql) {
        $whereqa .= ' AND ' . $whichattemptsql;
    }

    if (!$includeungraded) {
        $whereqa .= ' AND realtimequiza.sumgrades IS NOT NULL';
    }

    return [$fromqa, $whereqa, $qaparams];
}

/**
 * Return a {@link qubaid_condition} from the values returned by {@link realtimequiz_statistics_attempts_sql}.
 *
 * @param int     $realtimequizid
 * @param \core\dml\sql_join $groupstudentsjoins Contains joins, wheres, params
 * @param string $whichattempts which attempts to use, represented internally as one of the constants as used in
 *                                   $realtimequiz->grademethod ie.
 *                                   QUIZ_GRADEAVERAGE, QUIZ_GRADEHIGHEST, QUIZ_ATTEMPTLAST or QUIZ_ATTEMPTFIRST
 *                                   we calculate stats based on which attempts would affect the grade for each student.
 * @param bool    $includeungraded
 * @return        \qubaid_join
 */
function realtimequiz_statistics_qubaids_condition($realtimequizid, \core\dml\sql_join $groupstudentsjoins,
        $whichattempts = QUIZ_GRADEAVERAGE, $includeungraded = false) {
    list($fromqa, $whereqa, $qaparams) = realtimequiz_statistics_attempts_sql(
            $realtimequizid, $groupstudentsjoins, $whichattempts, $includeungraded);
    return new qubaid_join($fromqa, 'realtimequiza.uniqueid', $whereqa, $qaparams);
}
