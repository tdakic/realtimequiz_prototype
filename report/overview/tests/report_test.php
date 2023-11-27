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

namespace realtimequiz_overview;

use core_question\local\bank\question_version_status;
use mod_realtimequiz\external\submit_question_version;
use mod_realtimequiz\realtimequiz_attempt;
use question_engine;
use mod_realtimequiz\realtimequiz_settings;
use mod_realtimequiz\local\reports\attempts_report;
use realtimequiz_overview_options;
use realtimequiz_overview_report;
use realtimequiz_overview_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/overview/report.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/overview/overview_form.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/overview/tests/helpers.php');
require_once($CFG->dirroot . '/mod/realtimequiz/tests/realtimequiz_question_helper_test_trait.php');


/**
 * Tests for the realtimequiz overview report.
 *
 * @package    realtimequiz_overview
 * @copyright  2014 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_test extends \advanced_testcase {
    use \realtimequiz_question_helper_test_trait;

    /**
     * Data provider for test_report_sql.
     *
     * @return array the data for the test sub-cases.
     */
    public function report_sql_cases(): array {
        return [[null], ['csv']]; // Only need to test on or off, not all download types.
    }

    /**
     * Test how the report queries the database.
     *
     * @param string|null $isdownloading a download type, or null.
     * @dataProvider report_sql_cases
     */
    public function test_report_sql(?string $isdownloading): void {
        global $DB;
        $this->resetAfterTest();

        // Create a course and a realtimequiz.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $realtimequizgenerator = $generator->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $course->id,
                'grademethod' => QUIZ_GRADEHIGHEST, 'grade' => 100.0, 'sumgrades' => 10.0,
                'attempts' => 10]);

        // Add one question.
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('essay', 'plain', ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($q->id, $realtimequiz, 0 , 10);

        // Create some students and enrol them in the course.
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $student3 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id);
        $generator->enrol_user($student2->id, $course->id);
        $generator->enrol_user($student3->id, $course->id);
        // This line is not really necessary for the test asserts below,
        // but what it does is add an extra user row returned by
        // get_enrolled_with_capabilities_join because of a second enrolment.
        // The extra row returned used to make $table->query_db complain
        // about duplicate records. So this is really a test that an extra
        // student enrolment does not cause duplicate records in this query.
        $generator->enrol_user($student2->id, $course->id, null, 'self');

        // Also create a user who should not appear in the reports,
        // because they have a role with neither 'mod/realtimequiz:attempt'
        // nor 'mod/realtimequiz:reviewmyattempts'.
        $tutor = $generator->create_user();
        $generator->enrol_user($tutor->id, $course->id, 'teacher');

        // The test data.
        $timestamp = 1234567890;
        $attempts = [
            [$realtimequiz, $student1, 1, 0.0,  realtimequiz_attempt::FINISHED],
            [$realtimequiz, $student1, 2, 5.0,  realtimequiz_attempt::FINISHED],
            [$realtimequiz, $student1, 3, 8.0,  realtimequiz_attempt::FINISHED],
            [$realtimequiz, $student1, 4, null, realtimequiz_attempt::ABANDONED],
            [$realtimequiz, $student1, 5, null, realtimequiz_attempt::IN_PROGRESS],
            [$realtimequiz, $student2, 1, null, realtimequiz_attempt::ABANDONED],
            [$realtimequiz, $student2, 2, null, realtimequiz_attempt::ABANDONED],
            [$realtimequiz, $student2, 3, 7.0,  realtimequiz_attempt::FINISHED],
            [$realtimequiz, $student2, 4, null, realtimequiz_attempt::ABANDONED],
            [$realtimequiz, $student2, 5, null, realtimequiz_attempt::ABANDONED],
        ];

        // Load it in to realtimequiz attempts table.
        foreach ($attempts as $attemptdata) {
            list($realtimequiz, $student, $attemptnumber, $sumgrades, $state) = $attemptdata;
            $timestart = $timestamp + $attemptnumber * 3600;

            $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $student->id);
            $quba = question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
            $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

            // Create the new attempt and initialize the question sessions.
            $attempt = realtimequiz_create_attempt($realtimequizobj, $attemptnumber, null, $timestart, false, $student->id);

            $attempt = realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, $attemptnumber, $timestamp);
            $attempt = realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

            // Process some responses from the student.
            $attemptobj = realtimequiz_attempt::create($attempt->id);
            switch ($state) {
                case realtimequiz_attempt::ABANDONED:
                    $attemptobj->process_abandon($timestart + 300, false);
                    break;

                case realtimequiz_attempt::IN_PROGRESS:
                    // Do nothing.
                    break;

                case realtimequiz_attempt::FINISHED:
                    // Save answer and finish attempt.
                    $attemptobj->process_submitted_actions($timestart + 300, false, [
                            1 => ['answer' => 'My essay by ' . $student->firstname, 'answerformat' => FORMAT_PLAIN]]);
                    $attemptobj->process_finish($timestart + 600, false);

                    // Manually grade it.
                    $quba = $attemptobj->get_question_usage();
                    $quba->get_question_attempt(1)->manual_grade(
                            'Comment', $sumgrades, FORMAT_HTML, $timestart + 1200);
                    question_engine::save_questions_usage_by_activity($quba);
                    $update = new \stdClass();
                    $update->id = $attemptobj->get_attemptid();
                    $update->timemodified = $timestart + 1200;
                    $update->sumgrades = $quba->get_total_mark();
                    $DB->update_record('realtimequiz_attempts', $update);
                    $attemptobj->get_realtimequizobj()->get_grade_calculator()->recompute_final_grade($student->id);
                    break;
            }
        }

        // Actually getting the SQL to run is quite hard. Do a minimal set up of
        // some objects.
        $context = \context_module::instance($realtimequiz->cmid);
        $cm = get_coursemodule_from_id('realtimequiz', $realtimequiz->cmid);
        $qmsubselect = realtimequiz_report_qm_filter_select($realtimequiz);
        $studentsjoins = get_enrolled_with_capabilities_join($context, '',
                ['mod/realtimequiz:attempt', 'mod/realtimequiz:reviewmyattempts']);
        $empty = new \core\dml\sql_join();

        // Set the options.
        $reportoptions = new realtimequiz_overview_options('overview', $realtimequiz, $cm, null);
        $reportoptions->attempts = attempts_report::ENROLLED_ALL;
        $reportoptions->onlygraded = true;
        $reportoptions->states = [realtimequiz_attempt::IN_PROGRESS, realtimequiz_attempt::OVERDUE, realtimequiz_attempt::FINISHED];

        // Now do a minimal set-up of the table class.
        $q->slot = 1;
        $q->maxmark = 10;
        $table = new realtimequiz_overview_table($realtimequiz, $context, $qmsubselect, $reportoptions,
                $empty, $studentsjoins, [1 => $q], null);
        $table->download = $isdownloading; // Cannot call the is_downloading API, because it gives errors.
        $table->define_columns(['fullname']);
        $table->sortable(true, 'uniqueid');
        $table->define_baseurl(new \moodle_url('/mod/realtimequiz/report.php'));
        $table->setup();

        // Run the query.
        $table->setup_sql_queries($studentsjoins);
        $table->query_db(30, false);

        // Should be 4 rows, matching count($table->rawdata) tested below.
        // The count is only done if not downloading.
        if (!$isdownloading) {
            $this->assertEquals(4, $table->totalrows);
        }

        // Verify what was returned: Student 1's best and in progress attempts.
        // Student 2's finshed attempt, and Student 3 with no attempt.
        // The array key is {student id}#{attempt number}.
        $this->assertEquals(4, count($table->rawdata));
        $this->assertArrayHasKey($student1->id . '#3', $table->rawdata);
        $this->assertEquals(1, $table->rawdata[$student1->id . '#3']->gradedattempt);
        $this->assertArrayHasKey($student1->id . '#3', $table->rawdata);
        $this->assertEquals(0, $table->rawdata[$student1->id . '#5']->gradedattempt);
        $this->assertArrayHasKey($student2->id . '#3', $table->rawdata);
        $this->assertEquals(1, $table->rawdata[$student2->id . '#3']->gradedattempt);
        $this->assertArrayHasKey($student3->id . '#0', $table->rawdata);
        $this->assertEquals(0, $table->rawdata[$student3->id . '#0']->gradedattempt);

        // Check the calculation of averages.
        $averagerow = $table->compute_average_row('overallaverage', $studentsjoins);
        $this->assertStringContainsString('75.00', $averagerow['sumgrades']);
        $this->assertStringContainsString('75.00', $averagerow['qsgrade1']);
        if (!$isdownloading) {
            $this->assertStringContainsString('(2)', $averagerow['sumgrades']);
            $this->assertStringContainsString('(2)', $averagerow['qsgrade1']);
        }

        // Ensure that filtering by initial does not break it.
        // This involves setting a private properly of the base class, which is
        // only really possible using reflection :-(.
        $reflectionobject = new \ReflectionObject($table);
        while ($parent = $reflectionobject->getParentClass()) {
            $reflectionobject = $parent;
        }
        $prefsproperty = $reflectionobject->getProperty('prefs');
        $prefsproperty->setAccessible(true);
        $prefs = $prefsproperty->getValue($table);
        $prefs['i_first'] = 'A';
        $prefsproperty->setValue($table, $prefs);

        list($fields, $from, $where, $params) = $table->base_sql($studentsjoins);
        $table->set_count_sql("SELECT COUNT(1) FROM (SELECT $fields FROM $from WHERE $where) temp WHERE 1 = 1", $params);
        $table->set_sql($fields, $from, $where, $params);
        $table->query_db(30, false);
        // Just verify that this does not cause a fatal error.
    }

    /**
     * Bands provider.
     * @return array
     */
    public function get_bands_count_and_width_provider(): array {
        return [
            [10, [20, .5]],
            [20, [20, 1]],
            [30, [15, 2]],
            // TODO MDL-55068 Handle bands better when grade is 50.
            // [50, [10, 5]],
            [100, [20, 5]],
            [200, [20, 10]],
        ];
    }

    /**
     * Test bands.
     *
     * @dataProvider get_bands_count_and_width_provider
     * @param int $grade grade
     * @param array $expected
     */
    public function test_get_bands_count_and_width(int $grade, array $expected): void {
        $this->resetAfterTest();
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => SITEID, 'grade' => $grade]);
        $this->assertEquals($expected, realtimequiz_overview_report::get_bands_count_and_width($realtimequiz));
    }

    /**
     * Test delete_selected_attempts function.
     */
    public function test_delete_selected_attempts(): void {
        $this->resetAfterTest();

        $timestamp = 1234567890;
        $timestart = $timestamp + 3600;

        // Create a course and a realtimequiz.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $realtimequizgenerator = $generator->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance([
                'course' => $course->id,
                'grademethod' => QUIZ_GRADEHIGHEST,
                'grade' => 100.0,
                'sumgrades' => 10.0,
                'attempts' => 10
        ]);

        // Add one question.
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('essay', 'plain', ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($q->id, $realtimequiz, 0 , 10);

        // Create student and enrol them in the course.
        // Note: we create two enrolments, to test the problem reported in MDL-67942.
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id);
        $generator->enrol_user($student->id, $course->id, null, 'self');

        $context = \context_module::instance($realtimequiz->cmid);
        $cm = get_coursemodule_from_id('realtimequiz', $realtimequiz->cmid);
        $allowedjoins = get_enrolled_with_capabilities_join($context, '', ['mod/realtimequiz:attempt', 'mod/realtimequiz:reviewmyattempts']);
        $realtimequizattemptsreport = new \testable_realtimequiz_attempts_report();

        // Create the new attempt and initialize the question sessions.
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $student->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, null, $timestart, false, $student->id);
        $attempt = realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timestamp);
        $attempt = realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Delete the student's attempt.
        $realtimequizattemptsreport->delete_selected_attempts($realtimequiz, $cm, [$attempt->id], $allowedjoins);
    }

    /**
     * Test question regrade for selected versions.
     *
     * @covers ::regrade_question
     */
    public function test_regrade_question() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->create_test_realtimequiz($course);
        $cm = get_fast_modinfo($course->id)->get_cm($realtimequiz->cmid);
        $context = \context_module::instance($realtimequiz->cmid);

        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        // Create a couple of questions.
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $q = $questiongenerator->create_question('shortanswer', null,
                ['category' => $cat->id, 'name' => 'Toad scores 0.8']);

        // Create a version, the last one draft.
        // Sadly, update_question is a bit dodgy, so it can't handle updating the answer score.
        $q2 = $questiongenerator->update_question($q, null,
                ['name' => 'Toad now scores 1.0']);
        $toadanswer = $DB->get_record_select('question_answers',
                'question = ? AND ' . $DB->sql_compare_text('answer') . ' = ?',
                [$q2->id, 'toad'], '*', MUST_EXIST);
        $DB->set_field('question_answers', 'fraction', 1, ['id' => $toadanswer->id]);

        // Add the question to the realtimequiz.
        realtimequiz_add_realtimequiz_question($q2->id, $realtimequiz, 0, 10);

        // Attempt the realtimequiz, submitting response 'toad'.
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id);
        $attempt = realtimequiz_prepare_and_start_new_attempt($realtimequizobj, 1, null);
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions(time(), false, [1 => ['answer' => 'toad']]);
        $attemptobj->process_finish(time(), false);

        // We should be using 'always latest' version, which is currently v2, so should be right.
        $this->assertEquals(10, $attemptobj->get_question_usage()->get_total_mark());

        // Now change the realtimequiz to use fixed version 1.
        $slot = $realtimequizobj->get_question($q2->id);
        submit_question_version::execute($slot->slotid, 1);

        // Regrade.
        $report = new realtimequiz_overview_report();
        $report->init('overview', 'realtimequiz_overview_settings_form', $realtimequiz, $cm, $course);
        $report->regrade_attempt($attempt);

        // The mark should now be 8.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertEquals(8, $attemptobj->get_question_usage()->get_total_mark());

        // Now add two more versions, the second of which is draft.
        $q3 = $questiongenerator->update_question($q, null,
                ['name' => 'Toad now scores 0.5']);
        $toadanswer = $DB->get_record_select('question_answers',
                'question = ? AND ' . $DB->sql_compare_text('answer') . ' = ?',
                [$q3->id, 'toad'], '*', MUST_EXIST);
        $DB->set_field('question_answers', 'fraction', 0.5, ['id' => $toadanswer->id]);

        $q4 = $questiongenerator->update_question($q, null,
                ['name' => 'Toad now scores 0.3',
                    'status' => question_version_status::QUESTION_STATUS_DRAFT]);
        $toadanswer = $DB->get_record_select('question_answers',
                'question = ? AND ' . $DB->sql_compare_text('answer') . ' = ?',
                [$q4->id, 'toad'], '*', MUST_EXIST);
        $DB->set_field('question_answers', 'fraction', 0.3, ['id' => $toadanswer->id]);

        // Now change the realtimequiz back to always latest and regrade again.
        submit_question_version::execute($slot->slotid, 0);
        $report->clear_regrade_date_cache();
        $report->regrade_attempt($attempt);

        // Score should now be 5, because v3 is the latest non-draft version.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertEquals(5, $attemptobj->get_question_usage()->get_total_mark());
    }
}
