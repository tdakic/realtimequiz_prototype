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

namespace mod_realtimequiz\task;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_user;
use mod_realtimequiz\realtimequiz_attempt;
use moodle_recordset;
use question_display_options;
use mod_realtimequiz\question\display_options;

require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

/**
 * Cron Quiz Notify Attempts Graded Task.
 *
 * @package    mod_realtimequiz
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class realtimequiz_notify_attempt_manual_grading_completed extends \core\task\scheduled_task {
    /**
     * @var int|null For using in unit testing only. Override the time we consider as now.
     */
    protected $forcedtime = null;

    /**
     * Get name of schedule task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('notifyattemptsgradedtask', 'mod_realtimequiz');
    }

    /**
     * To let this class be unit tested, we wrap all accesses to the current time in this method.
     *
     * @return int The current time.
     */
    protected function get_time(): int {
        if (PHPUNIT_TEST && $this->forcedtime !== null) {
            return $this->forcedtime;
        }

        return time();
    }

    /**
     * For testing only, pretend the current time is different.
     *
     * @param int $time The time to set as the current time.
     */
    public function set_time_for_testing(int $time): void {
        if (!PHPUNIT_TEST) {
            throw new \coding_exception('set_time_for_testing should only be used in unit tests.');
        }
        $this->forcedtime = $time;
    }

    /**
     * Execute sending notification for manual graded attempts.
     */
    public function execute() {
        global $DB;

        mtrace('Looking for realtimequiz attempts which may need a graded notification sent...');

        $attempts = $this->get_list_of_attempts();
        $course = null;
        $realtimequiz = null;
        $cm = null;

        foreach ($attempts as $attempt) {
            mtrace('Checking attempt ' . $attempt->id . ' at realtimequiz ' . $attempt->realtimequiz . '.');

            if (!$realtimequiz || $attempt->realtimequiz != $realtimequiz->id) {
                $realtimequiz = $DB->get_record('realtimequiz', ['id' => $attempt->realtimequiz], '*', MUST_EXIST);
                $cm = get_coursemodule_from_instance('realtimequiz', $attempt->realtimequiz);
            }

            if (!$course || $course->id != $realtimequiz->course) {
                $course = get_course($realtimequiz->course);
                $coursecontext = context_course::instance($realtimequiz->course);
            }

            $realtimequiz = realtimequiz_update_effective_access($realtimequiz, $attempt->userid);
            $attemptobj = new realtimequiz_attempt($attempt, $realtimequiz, $cm, $course, false);
            $options = display_options::make_from_realtimequiz($realtimequiz, realtimequiz_attempt_state($realtimequiz, $attempt));

            if ($options->manualcomment == question_display_options::HIDDEN) {
                // User cannot currently see the feedback, so don't message them.
                // However, this may change in future, so leave them on the list.
                continue;
            }

            if (!has_capability('mod/realtimequiz:emailnotifyattemptgraded', $coursecontext, $attempt->userid, false)) {
                // User not eligible to get a notification. Mark them done while doing nothing.
                $DB->set_field('realtimequiz_attempts', 'gradednotificationsenttime', $attempt->timefinish, ['id' => $attempt->id]);
                continue;
            }

            // OK, send notification.
            mtrace('Sending email to user ' . $attempt->userid . '...');
            $ok = realtimequiz_send_notify_manual_graded_message($attemptobj, core_user::get_user($attempt->userid));
            if ($ok) {
                mtrace('Send email successfully!');
                $attempt->gradednotificationsenttime = $this->get_time();
                $DB->set_field('realtimequiz_attempts', 'gradednotificationsenttime', $attempt->gradednotificationsenttime,
                        ['id' => $attempt->id]);
                $attemptobj->fire_attempt_manual_grading_completed_event();
            }
        }

        $attempts->close();
    }

    /**
     * Get a number of records as an array of realtimequiz_attempts using a SQL statement.
     *
     * @return moodle_recordset Of realtimequiz_attempts that need to be processed.
     */
    public function get_list_of_attempts(): moodle_recordset {
        global $DB;

        $delaytime = $this->get_time() - get_config('realtimequiz', 'notifyattemptgradeddelay');

        $sql = "SELECT qa.*
                  FROM {realtimequiz_attempts} qa
                  JOIN {realtimequiz} realtimequiz ON realtimequiz.id = qa.realtimequiz
                 WHERE qa.state = 'finished'
                       AND qa.gradednotificationsenttime IS NULL
                       AND qa.sumgrades IS NOT NULL
                       AND qa.timemodified < :delaytime
              ORDER BY realtimequiz.course, qa.realtimequiz";

        return $DB->get_recordset_sql($sql, ['delaytime' => $delaytime]);
    }
}
