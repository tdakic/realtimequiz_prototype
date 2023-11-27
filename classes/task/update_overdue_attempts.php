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
 * Update Overdue Attempts Task
 *
 * @package    mod_realtimequiz
 * @copyright  2017 Michael Hughes
 * @author Michael Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_realtimequiz\task;

use mod_realtimequiz\realtimequiz_attempt;
use moodle_exception;
use moodle_recordset;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

/**
 * Update Overdue Attempts Task
 *
 * @package    mod_realtimequiz
 * @copyright  2017 Michael Hughes
 * @author Michael Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class update_overdue_attempts extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('updateoverdueattemptstask', 'mod_realtimequiz');
    }

    /**
     * Close off any overdue attempts.
     */
    public function execute() {
        $timenow = time();
        $processto = $timenow - get_config('realtimequiz', 'graceperiodmin');

        mtrace('  Looking for realtimequiz overdue realtimequiz attempts...');

        list($count, $realtimequizcount) = $this->update_all_overdue_attempts($timenow, $processto);

        mtrace('  Considered ' . $count . ' attempts in ' . $realtimequizcount . ' realtimequizzes.');
    }

    /**
     * Do the processing required.
     *
     * @param int $timenow the time to consider as 'now' during the processing.
     * @param int $processto only process attempt with timecheckstate longer ago than this.
     * @return array with two elements, the number of attempt considered, and how many different realtimequizzes that was.
     */
    public function update_all_overdue_attempts(int $timenow, int $processto): array {
        global $DB;

        $attemptstoprocess = $this->get_list_of_overdue_attempts($processto);

        $course = null;
        $realtimequiz = null;
        $cm = null;

        $count = 0;
        $realtimequizcount = 0;
        foreach ($attemptstoprocess as $attempt) {
            try {

                // If we have moved on to a different realtimequiz, fetch the new data.
                if (!$realtimequiz || $attempt->realtimequiz != $realtimequiz->id) {
                    $realtimequiz = $DB->get_record('realtimequiz', ['id' => $attempt->realtimequiz], '*', MUST_EXIST);
                    $cm = get_coursemodule_from_instance('realtimequiz', $attempt->realtimequiz);
                    $realtimequizcount += 1;
                }

                // If we have moved on to a different course, fetch the new data.
                if (!$course || $course->id != $realtimequiz->course) {
                    $course = get_course($realtimequiz->course);
                }

                // Make a specialised version of the realtimequiz settings, with the relevant overrides.
                $realtimequizforuser = clone($realtimequiz);
                $realtimequizforuser->timeclose = $attempt->usertimeclose;
                $realtimequizforuser->timelimit = $attempt->usertimelimit;

                // Trigger any transitions that are required.
                $attemptobj = new realtimequiz_attempt($attempt, $realtimequizforuser, $cm, $course);
                $attemptobj->handle_if_time_expired($timenow, false);
                $count += 1;

            } catch (moodle_exception $e) {
                // If an error occurs while processing one attempt, don't let that kill cron.
                mtrace("Error while processing attempt $attempt->id at $attempt->realtimequiz realtimequiz:");
                mtrace($e->getMessage());
                mtrace($e->getTraceAsString());
                // Close down any currently open transactions, otherwise one error
                // will stop following DB changes from being committed.
                $DB->force_transaction_rollback();
            }
        }

        $attemptstoprocess->close();
        return [$count, $realtimequizcount];
    }

    /**
     * Get a recordset of all the attempts that need to be processed now.
     *
     * (Only public to allow unit testing. Do not use!)
     *
     * @param int $processto timestamp to process up to.
     * @return moodle_recordset of realtimequiz_attempts that need to be processed because time has
     *     passed, sorted by courseid then realtimequizid.
     */
    public function get_list_of_overdue_attempts(int $processto): moodle_recordset {
        global $DB;

        // SQL to compute timeclose and timelimit for each attempt.
        $realtimequizausersql = realtimequiz_get_attempt_usertime_sql(
                "irealtimequiza.state IN ('inprogress', 'overdue') AND irealtimequiza.timecheckstate <= :iprocessto");

        // This query should have all the realtimequiz_attempts columns.
        return $DB->get_recordset_sql("
         SELECT realtimequiza.*,
                realtimequizauser.usertimeclose,
                realtimequizauser.usertimelimit

           FROM {realtimequiz_attempts} realtimequiza
           JOIN {realtimequiz} realtimequiz ON realtimequiz.id = realtimequiza.realtimequiz
           JOIN ( $realtimequizausersql ) realtimequizauser ON realtimequizauser.id = realtimequiza.id

          WHERE realtimequiza.state IN ('inprogress', 'overdue')
            AND realtimequiza.timecheckstate <= :processto
       ORDER BY realtimequiz.course, realtimequiza.realtimequiz",

                ['processto' => $processto, 'iprocessto' => $processto]);
    }
}
