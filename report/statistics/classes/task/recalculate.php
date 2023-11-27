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

namespace realtimequiz_statistics\task;

use core\dml\sql_join;
use mod_realtimequiz\realtimequiz_attempt;
use mod_realtimequiz\realtimequiz_settings;
use realtimequiz_statistics_report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/statistics/statisticslib.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/statistics/report.php');

/**
 * Re-calculate question statistics.
 *
 * @package    realtimequiz_statistics
 * @copyright  2022 Catalyst IT Australia Pty Ltd
 * @author     Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalculate extends \core\task\scheduled_task {
    /** @var int the maximum length of time one instance of this task will run. */
    const TIME_LIMIT = 3600;

    public function get_name(): string {
        return get_string('recalculatetask', 'realtimequiz_statistics');
    }

    public function execute(): void {
        global $DB;
        $stoptime = time() + self::TIME_LIMIT;
        $dateformat = get_string('strftimedatetimeshortaccurate', 'core_langconfig');

        // TODO: MDL-75197, add realtimequizid in realtimequiz_statistics so that it is simpler to find realtimequizzes for stats calculation.
        // Only calculate stats for realtimequizzes which have recently finished attempt.
        $latestattempts = $DB->get_records_sql("
                SELECT q.id AS realtimequizid,
                       q.name AS realtimequizname,
                       q.grademethod AS realtimequizgrademethod,
                       c.id AS courseid,
                       c.shortname AS courseshortname,
                       MAX(realtimequiza.timefinish) AS mostrecentattempttime,
                       COUNT(1) AS numberofattempts

                  FROM {realtimequiz_attempts} realtimequiza
                  JOIN {realtimequiz} q ON q.id = realtimequiza.realtimequiz
                  JOIN {course} c ON c.id = q.course

                 WHERE realtimequiza.preview = 0
                   AND realtimequiza.state = :realtimequizstatefinished

              GROUP BY q.id, q.name, q.grademethod, c.id, c.shortname
              ORDER BY MAX(realtimequiza.timefinish) DESC
            ", ["realtimequizstatefinished" => realtimequiz_attempt::FINISHED]);
        //TTT changed inprogress from realtimequizstatefinished in line 68
        $anyexception = null;
        foreach ($latestattempts as $latestattempt) {
            if (time() >= $stoptime) {
                mtrace("This task has been running for more than " .
                        format_time(self::TIME_LIMIT) . " so stopping this execution.");
                break;
            }

            // Check if there is any existing question stats, and it has been calculated after latest realtimequiz attempt.
            $qubaids = realtimequiz_statistics_qubaids_condition($latestattempt->realtimequizid,
                    new sql_join(), $latestattempt->realtimequizgrademethod);
            $lateststatstime = $DB->get_field('realtimequiz_statistics', 'COALESCE(MAX(timemodified), 0)',
                    ['hashcode' => $qubaids->get_hash_code()]);

            $realtimequizinfo = "'$latestattempt->realtimequizname' ($latestattempt->realtimequizid) in course " .
                    "$latestattempt->courseshortname ($latestattempt->courseid) has most recent attempt finished at " .
                        userdate($latestattempt->mostrecentattempttime, $dateformat);
            if ($lateststatstime) {
                $realtimequizinfo .= " and statistics from " . userdate($lateststatstime, $dateformat);
            }

            if ($lateststatstime >= $latestattempt->mostrecentattempttime) {
                mtrace("  " . $realtimequizinfo . " so nothing to do.");
                continue;
            }

            // OK, so we need to calculate for this realtimequiz.
            mtrace("  " . $realtimequizinfo . " so re-calculating statistics for $latestattempt->numberofattempts attempts, start time " .
                    userdate(time(), $dateformat) . " ...");

            try {
                $realtimequizobj = realtimequiz_settings::create($latestattempt->realtimequizid);
                $report = new realtimequiz_statistics_report();
                $report->clear_cached_data($qubaids);
                $report->calculate_questions_stats_for_question_bank($realtimequizobj->get_realtimequizid());
                mtrace("    Calculations completed at " . userdate(time(), $dateformat) . ".");

            } catch (\Throwable $e) {
                // We don't want an exception from one realtimequiz to stop processing of other realtimequizzes.
                mtrace_exception($e);
                $anyexception = $e;
            }
        }

        if ($anyexception) {
            // If there was any error, ensure the task fails.
            throw $anyexception;
        }
    }
}
