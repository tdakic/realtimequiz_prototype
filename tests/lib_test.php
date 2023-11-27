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
 * Unit tests for (some of) mod/realtimequiz/locallib.php.
 *
 * @package    mod_realtimequiz
 * @category   test
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
namespace mod_realtimequiz;

use core_external\external_api;
use mod_realtimequiz\realtimequiz_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/realtimequiz/lib.php');

/**
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class lib_test extends \advanced_testcase {
    public function test_realtimequiz_has_grades() {
        $realtimequiz = new \stdClass();
        $realtimequiz->grade = '100.0000';
        $realtimequiz->sumgrades = '100.0000';
        $this->assertTrue(realtimequiz_has_grades($realtimequiz));
        $realtimequiz->sumgrades = '0.0000';
        $this->assertFalse(realtimequiz_has_grades($realtimequiz));
        $realtimequiz->grade = '0.0000';
        $this->assertFalse(realtimequiz_has_grades($realtimequiz));
        $realtimequiz->sumgrades = '100.0000';
        $this->assertFalse(realtimequiz_has_grades($realtimequiz));
    }

    public function test_realtimequiz_format_grade() {
        $realtimequiz = new \stdClass();
        $realtimequiz->decimalpoints = 2;
        $this->assertEquals(realtimequiz_format_grade($realtimequiz, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(realtimequiz_format_grade($realtimequiz, 0), format_float(0, 2));
        $this->assertEquals(realtimequiz_format_grade($realtimequiz, 1.000000000000), format_float(1, 2));
        $realtimequiz->decimalpoints = 0;
        $this->assertEquals(realtimequiz_format_grade($realtimequiz, 0.12345678), '0');
    }

    public function test_realtimequiz_get_grade_format() {
        $realtimequiz = new \stdClass();
        $realtimequiz->decimalpoints = 2;
        $this->assertEquals(realtimequiz_get_grade_format($realtimequiz), 2);
        $this->assertEquals($realtimequiz->questiondecimalpoints, -1);
        $realtimequiz->questiondecimalpoints = 2;
        $this->assertEquals(realtimequiz_get_grade_format($realtimequiz), 2);
        $realtimequiz->decimalpoints = 3;
        $realtimequiz->questiondecimalpoints = -1;
        $this->assertEquals(realtimequiz_get_grade_format($realtimequiz), 3);
        $realtimequiz->questiondecimalpoints = 4;
        $this->assertEquals(realtimequiz_get_grade_format($realtimequiz), 4);
    }

    public function test_realtimequiz_format_question_grade() {
        $realtimequiz = new \stdClass();
        $realtimequiz->decimalpoints = 2;
        $realtimequiz->questiondecimalpoints = 2;
        $this->assertEquals(realtimequiz_format_question_grade($realtimequiz, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(realtimequiz_format_question_grade($realtimequiz, 0), format_float(0, 2));
        $this->assertEquals(realtimequiz_format_question_grade($realtimequiz, 1.000000000000), format_float(1, 2));
        $realtimequiz->decimalpoints = 3;
        $realtimequiz->questiondecimalpoints = -1;
        $this->assertEquals(realtimequiz_format_question_grade($realtimequiz, 0.12345678), format_float(0.123, 3));
        $this->assertEquals(realtimequiz_format_question_grade($realtimequiz, 0), format_float(0, 3));
        $this->assertEquals(realtimequiz_format_question_grade($realtimequiz, 1.000000000000), format_float(1, 3));
        $realtimequiz->questiondecimalpoints = 4;
        $this->assertEquals(realtimequiz_format_question_grade($realtimequiz, 0.12345678), format_float(0.1235, 4));
        $this->assertEquals(realtimequiz_format_question_grade($realtimequiz, 0), format_float(0, 4));
        $this->assertEquals(realtimequiz_format_question_grade($realtimequiz, 1.000000000000), format_float(1, 4));
    }

    /**
     * Test deleting a realtimequiz instance.
     */
    public function test_realtimequiz_delete_instance() {
        global $SITE, $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Setup a realtimequiz with 1 standard and 1 random question.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $SITE->id, 'questionsperpage' => 3, 'grade' => 100.0]);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $standardq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);

        realtimequiz_add_realtimequiz_question($standardq->id, $realtimequiz);
        realtimequiz_add_random_questions($realtimequiz, 0, $cat->id, 1, false);

        // Get the random question.
        $randomq = $DB->get_record('question', ['qtype' => 'random']);

        realtimequiz_delete_instance($realtimequiz->id);

        // Check that the random question was deleted.
        if ($randomq) {
            $count = $DB->count_records('question', ['id' => $randomq->id]);
            $this->assertEquals(0, $count);
        }
        // Check that the standard question was not deleted.
        $count = $DB->count_records('question', ['id' => $standardq->id]);
        $this->assertEquals(1, $count);

        // Check that all the slots were removed.
        $count = $DB->count_records('realtimequiz_slots', ['realtimequizid' => $realtimequiz->id]);
        $this->assertEquals(0, $count);

        // Check that the realtimequiz was removed.
        $count = $DB->count_records('realtimequiz', ['id' => $realtimequiz->id]);
        $this->assertEquals(0, $count);
    }

    /**
     * Setup function for all test_realtimequiz_get_completion_state_* tests.
     *
     * @param array $completionoptions ['nbstudents'] => int, ['qtype'] => string, ['realtimequizoptions'] => array
     * @throws dml_exception
     * @return array [$course, $students, $realtimequiz, $cm]
     */
    private function setup_realtimequiz_for_testing_completion(array $completionoptions) {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        // Enable completion before creating modules, otherwise the completion data is not written in DB.
        $CFG->enablecompletion = true;

        // Create a course and students.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        $students = [];
        for ($i = 0; $i < $completionoptions['nbstudents']; $i++) {
            $students[$i] = $this->getDataGenerator()->create_user();
            $this->assertTrue($this->getDataGenerator()->enrol_user($students[$i]->id, $course->id, $studentrole->id));
        }

        // Make a realtimequiz.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $data = array_merge([
            'course' => $course->id,
            'grade' => 100.0,
            'questionsperpage' => 0,
            'sumgrades' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC
        ], $completionoptions['realtimequizoptions']);
        $realtimequiz = $realtimequizgenerator->create_instance($data);
        $cm = get_coursemodule_from_id('realtimequiz', $realtimequiz->cmid);

        // Create a question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question($completionoptions['qtype'], null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        // Set grade to pass.
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'realtimequiz',
            'iteminstance' => $realtimequiz->id, 'outcomeid' => null]);
        $item->gradepass = 80;
        $item->update();

        return [
            $course,
            $students,
            $realtimequiz,
            $cm
        ];
    }

    /**
     * Helper function for all test_realtimequiz_get_completion_state_* tests.
     * Starts an attempt, processes responses and finishes the attempt.
     *
     * @param $attemptoptions ['realtimequiz'] => object, ['student'] => object, ['tosubmit'] => array, ['attemptnumber'] => int
     */
    private function do_attempt_realtimequiz($attemptoptions) {
        $realtimequizobj = realtimequiz_settings::create($attemptoptions['realtimequiz']->id);

        // Start the passing attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, $attemptoptions['attemptnumber'], false, $timenow, false,
            $attemptoptions['student']->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, $attemptoptions['attemptnumber'], $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Process responses from the student.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, $attemptoptions['tosubmit']);

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);
    }

    /**
     * Test checking the completion state of a realtimequiz.
     * The realtimequiz requires a passing grade to be completed.
     */
    public function test_realtimequiz_get_completion_state_completionpass() {

        list($course, $students, $realtimequiz, $cm) = $this->setup_realtimequiz_for_testing_completion([
            'nbstudents' => 2,
            'qtype' => 'numerical',
            'realtimequizoptions' => [
                'completionusegrade' => 1,
                'completionpassgrade' => 1
            ]
        ]);

        list($passstudent, $failstudent) = $students;

        // Do a passing attempt.
        $this->do_attempt_realtimequiz([
           'realtimequiz' => $realtimequiz,
           'student' => $passstudent,
           'attemptnumber' => 1,
           'tosubmit' => [1 => ['answer' => '3.14']]
        ]);

        // Check the results.
        $this->assertTrue(realtimequiz_get_completion_state($course, $cm, $passstudent->id, 'return'));

        // Do a failing attempt.
        $this->do_attempt_realtimequiz([
            'realtimequiz' => $realtimequiz,
            'student' => $failstudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results.
        $this->assertFalse(realtimequiz_get_completion_state($course, $cm, $failstudent->id, 'return'));

        $this->assertDebuggingCalledCount(3, [
            'realtimequiz_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'realtimequiz_completion_check_min_attempts has been deprecated.',
            'realtimequiz_completion_check_passing_grade_or_all_attempts has been deprecated.',
        ]);
    }

    /**
     * Test checking the completion state of a realtimequiz.
     * To be completed, this realtimequiz requires either a passing grade or for all attempts to be used up.
     */
    public function test_realtimequiz_get_completion_state_completionexhausted() {

        list($course, $students, $realtimequiz, $cm) = $this->setup_realtimequiz_for_testing_completion([
            'nbstudents' => 2,
            'qtype' => 'numerical',
            'realtimequizoptions' => [
                'attempts' => 2,
                'completionusegrade' => 1,
                'completionpassgrade' => 1,
                'completionattemptsexhausted' => 1
            ]
        ]);

        list($passstudent, $exhauststudent) = $students;

        // Start a passing attempt.
        $this->do_attempt_realtimequiz([
            'realtimequiz' => $realtimequiz,
            'student' => $passstudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '3.14']]
        ]);

        // Check the results. Quiz is completed by $passstudent because of passing grade.
        $this->assertTrue(realtimequiz_get_completion_state($course, $cm, $passstudent->id, 'return'));

        // Do a failing attempt.
        $this->do_attempt_realtimequiz([
            'realtimequiz' => $realtimequiz,
            'student' => $exhauststudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results. Quiz is not completed by $exhauststudent yet because of failing grade and of remaining attempts.
        $this->assertFalse(realtimequiz_get_completion_state($course, $cm, $exhauststudent->id, 'return'));

        // Do a second failing attempt.
        $this->do_attempt_realtimequiz([
            'realtimequiz' => $realtimequiz,
            'student' => $exhauststudent,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results. Quiz is completed by $exhauststudent because there are no remaining attempts.
        $this->assertTrue(realtimequiz_get_completion_state($course, $cm, $exhauststudent->id, 'return'));

        $this->assertDebuggingCalledCount(5, [
            'realtimequiz_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'realtimequiz_completion_check_min_attempts has been deprecated.',
            'realtimequiz_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'realtimequiz_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'realtimequiz_completion_check_min_attempts has been deprecated.',
        ]);
    }

    /**
     * Test checking the completion state of a realtimequiz.
     * To be completed, this realtimequiz requires a minimum number of attempts.
     */
    public function test_realtimequiz_get_completion_state_completionminattempts() {

        list($course, $students, $realtimequiz, $cm) = $this->setup_realtimequiz_for_testing_completion([
            'nbstudents' => 1,
            'qtype' => 'essay',
            'realtimequizoptions' => [
                'completionminattemptsenabled' => 1,
                'completionminattempts' => 2
            ]
        ]);

        list($student) = $students;

        // Do a first attempt.
        $this->do_attempt_realtimequiz([
            'realtimequiz' => $realtimequiz,
            'student' => $student,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => 'Lorem ipsum.', 'answerformat' => '1']]
        ]);

        // Check the results. Quiz is not completed yet because only one attempt was done.
        $this->assertFalse(realtimequiz_get_completion_state($course, $cm, $student->id, 'return'));

        // Do a second attempt.
        $this->do_attempt_realtimequiz([
            'realtimequiz' => $realtimequiz,
            'student' => $student,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => 'Lorem ipsum.', 'answerformat' => '1']]
        ]);

        // Check the results. Quiz is completed by $student because two attempts were done.
        $this->assertTrue(realtimequiz_get_completion_state($course, $cm, $student->id, 'return'));

        $this->assertDebuggingCalledCount(4, [
            'realtimequiz_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'realtimequiz_completion_check_min_attempts has been deprecated.',
            'realtimequiz_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'realtimequiz_completion_check_min_attempts has been deprecated.',
        ]);
    }

    /**
     * Test checking the completion state of a realtimequiz.
     * To be completed, this realtimequiz requires a minimum number of attempts AND a passing grade.
     * This is somewhat of an edge case as it is hard to imagine a scenario in which these precise settings are useful.
     * Nevertheless, this test makes sure these settings interact as intended.
     */
    public function  test_realtimequiz_get_completion_state_completionminattempts_pass() {

        list($course, $students, $realtimequiz, $cm) = $this->setup_realtimequiz_for_testing_completion([
            'nbstudents' => 1,
            'qtype' => 'numerical',
            'realtimequizoptions' => [
                'attempts' => 2,
                'completionusegrade' => 1,
                'completionpassgrade' => 1,
                'completionminattemptsenabled' => 1,
                'completionminattempts' => 2
            ]
        ]);

        list($student) = $students;

        // Start a first attempt.
        $this->do_attempt_realtimequiz([
            'realtimequiz' => $realtimequiz,
            'student' => $student,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '3.14']]
        ]);

        // Check the results. Even though one requirement is met (passing grade) realtimequiz is not completed yet because only
        // one attempt was done.
        $this->assertFalse(realtimequiz_get_completion_state($course, $cm, $student->id, 'return'));

        // Start a second attempt.
        $this->do_attempt_realtimequiz([
            'realtimequiz' => $realtimequiz,
            'student' => $student,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => '42']]
        ]);

        // Check the results. Quiz is completed by $student because two attempts were done AND a passing grade was obtained.
        $this->assertTrue(realtimequiz_get_completion_state($course, $cm, $student->id, 'return'));

        $this->assertDebuggingCalledCount(4, [
            'realtimequiz_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'realtimequiz_completion_check_min_attempts has been deprecated.',
            'realtimequiz_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'realtimequiz_completion_check_min_attempts has been deprecated.',
        ]);
    }

    public function test_realtimequiz_get_user_attempts() {
        global $DB;
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $realtimequizgen = $dg->get_plugin_generator('mod_realtimequiz');
        $course = $dg->create_course();
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();
        $role = $DB->get_record('role', ['shortname' => 'student']);

        $dg->enrol_user($u1->id, $course->id, $role->id);
        $dg->enrol_user($u2->id, $course->id, $role->id);
        $dg->enrol_user($u3->id, $course->id, $role->id);
        $dg->enrol_user($u4->id, $course->id, $role->id);

        $realtimequiz1 = $realtimequizgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);
        $realtimequiz2 = $realtimequizgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);

        // Questions.
        $questgen = $dg->get_plugin_generator('core_question');
        $realtimequizcat = $questgen->create_question_category();
        $question = $questgen->create_question('numerical', null, ['category' => $realtimequizcat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz1);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz2);

        $realtimequizobj1a = realtimequiz_settings::create($realtimequiz1->id, $u1->id);
        $realtimequizobj1b = realtimequiz_settings::create($realtimequiz1->id, $u2->id);
        $realtimequizobj1c = realtimequiz_settings::create($realtimequiz1->id, $u3->id);
        $realtimequizobj1d = realtimequiz_settings::create($realtimequiz1->id, $u4->id);
        $realtimequizobj2a = realtimequiz_settings::create($realtimequiz2->id, $u1->id);

        // Set attempts.
        $quba1a = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj1a->get_context());
        $quba1a->set_preferred_behaviour($realtimequizobj1a->get_realtimequiz()->preferredbehaviour);
        $quba1b = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj1b->get_context());
        $quba1b->set_preferred_behaviour($realtimequizobj1b->get_realtimequiz()->preferredbehaviour);
        $quba1c = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj1c->get_context());
        $quba1c->set_preferred_behaviour($realtimequizobj1c->get_realtimequiz()->preferredbehaviour);
        $quba1d = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj1d->get_context());
        $quba1d->set_preferred_behaviour($realtimequizobj1d->get_realtimequiz()->preferredbehaviour);
        $quba2a = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj2a->get_context());
        $quba2a->set_preferred_behaviour($realtimequizobj2a->get_realtimequiz()->preferredbehaviour);

        $timenow = time();

        // User 1 passes realtimequiz 1.
        $attempt = realtimequiz_create_attempt($realtimequizobj1a, 1, false, $timenow, false, $u1->id);
        realtimequiz_start_new_attempt($realtimequizobj1a, $quba1a, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj1a, $quba1a, $attempt);
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj->process_finish($timenow, false);

        // User 2 goes overdue in realtimequiz 1.
        $attempt = realtimequiz_create_attempt($realtimequizobj1b, 1, false, $timenow, false, $u2->id);
        realtimequiz_start_new_attempt($realtimequizobj1b, $quba1b, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj1b, $quba1b, $attempt);
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_going_overdue($timenow, true);

        // User 3 does not finish realtimequiz 1.
        $attempt = realtimequiz_create_attempt($realtimequizobj1c, 1, false, $timenow, false, $u3->id);
        realtimequiz_start_new_attempt($realtimequizobj1c, $quba1c, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj1c, $quba1c, $attempt);

        // User 4 abandons the realtimequiz 1.
        $attempt = realtimequiz_create_attempt($realtimequizobj1d, 1, false, $timenow, false, $u4->id);
        realtimequiz_start_new_attempt($realtimequizobj1d, $quba1d, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj1d, $quba1d, $attempt);
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_abandon($timenow, true);

        // User 1 attempts the realtimequiz three times (abandon, finish, in progress).
        $quba2a = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj2a->get_context());
        $quba2a->set_preferred_behaviour($realtimequizobj2a->get_realtimequiz()->preferredbehaviour);

        $attempt = realtimequiz_create_attempt($realtimequizobj2a, 1, false, $timenow, false, $u1->id);
        realtimequiz_start_new_attempt($realtimequizobj2a, $quba2a, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj2a, $quba2a, $attempt);
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_abandon($timenow, true);

        $quba2a = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj2a->get_context());
        $quba2a->set_preferred_behaviour($realtimequizobj2a->get_realtimequiz()->preferredbehaviour);

        $attempt = realtimequiz_create_attempt($realtimequizobj2a, 2, false, $timenow, false, $u1->id);
        realtimequiz_start_new_attempt($realtimequizobj2a, $quba2a, $attempt, 2, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj2a, $quba2a, $attempt);
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        $quba2a = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj2a->get_context());
        $quba2a->set_preferred_behaviour($realtimequizobj2a->get_realtimequiz()->preferredbehaviour);

        $attempt = realtimequiz_create_attempt($realtimequizobj2a, 3, false, $timenow, false, $u1->id);
        realtimequiz_start_new_attempt($realtimequizobj2a, $quba2a, $attempt, 3, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj2a, $quba2a, $attempt);

        // Check for user 1.
        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u1->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($realtimequiz1->id, $attempt->realtimequiz);

        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u1->id, 'finished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($realtimequiz1->id, $attempt->realtimequiz);

        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u1->id, 'unfinished');
        $this->assertCount(0, $attempts);

        // Check for user 2.
        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u2->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::OVERDUE, $attempt->state);
        $this->assertEquals($u2->id, $attempt->userid);
        $this->assertEquals($realtimequiz1->id, $attempt->realtimequiz);

        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u2->id, 'finished');
        $this->assertCount(0, $attempts);

        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u2->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::OVERDUE, $attempt->state);
        $this->assertEquals($u2->id, $attempt->userid);
        $this->assertEquals($realtimequiz1->id, $attempt->realtimequiz);

        // Check for user 3.
        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u3->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u3->id, $attempt->userid);
        $this->assertEquals($realtimequiz1->id, $attempt->realtimequiz);

        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u3->id, 'finished');
        $this->assertCount(0, $attempts);

        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u3->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u3->id, $attempt->userid);
        $this->assertEquals($realtimequiz1->id, $attempt->realtimequiz);

        // Check for user 4.
        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u4->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u4->id, $attempt->userid);
        $this->assertEquals($realtimequiz1->id, $attempt->realtimequiz);

        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u4->id, 'finished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u4->id, $attempt->userid);
        $this->assertEquals($realtimequiz1->id, $attempt->realtimequiz);

        $attempts = realtimequiz_get_user_attempts($realtimequiz1->id, $u4->id, 'unfinished');
        $this->assertCount(0, $attempts);

        // Multiple attempts for user 1 in realtimequiz 2.
        $attempts = realtimequiz_get_user_attempts($realtimequiz2->id, $u1->id, 'all');
        $this->assertCount(3, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($realtimequiz2->id, $attempt->realtimequiz);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($realtimequiz2->id, $attempt->realtimequiz);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($realtimequiz2->id, $attempt->realtimequiz);

        $attempts = realtimequiz_get_user_attempts($realtimequiz2->id, $u1->id, 'finished');
        $this->assertCount(2, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::ABANDONED, $attempt->state);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::FINISHED, $attempt->state);

        $attempts = realtimequiz_get_user_attempts($realtimequiz2->id, $u1->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);

        // Multiple realtimequiz attempts fetched at once.
        $attempts = realtimequiz_get_user_attempts([$realtimequiz1->id, $realtimequiz2->id], $u1->id, 'all');
        $this->assertCount(4, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($realtimequiz1->id, $attempt->realtimequiz);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($realtimequiz2->id, $attempt->realtimequiz);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($realtimequiz2->id, $attempt->realtimequiz);
        $attempt = array_shift($attempts);
        $this->assertEquals(realtimequiz_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($realtimequiz2->id, $attempt->realtimequiz);
    }

    /**
     * Test for realtimequiz_get_group_override_priorities().
     */
    public function test_realtimequiz_get_group_override_priorities() {
        global $DB;
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $realtimequizgen = $dg->get_plugin_generator('mod_realtimequiz');
        $course = $dg->create_course();

        $realtimequiz = $realtimequizgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);

        $this->assertNull(realtimequiz_get_group_override_priorities($realtimequiz->id));

        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $now = 100;
        $override1 = (object)[
            'realtimequiz' => $realtimequiz->id,
            'groupid' => $group1->id,
            'timeopen' => $now,
            'timeclose' => $now + 20
        ];
        $DB->insert_record('realtimequiz_overrides', $override1);

        $override2 = (object)[
            'realtimequiz' => $realtimequiz->id,
            'groupid' => $group2->id,
            'timeopen' => $now - 10,
            'timeclose' => $now + 10
        ];
        $DB->insert_record('realtimequiz_overrides', $override2);

        $priorities = realtimequiz_get_group_override_priorities($realtimequiz->id);
        $this->assertNotEmpty($priorities);

        $openpriorities = $priorities['open'];
        // Override 2's time open has higher priority since it is sooner than override 1's.
        $this->assertEquals(2, $openpriorities[$override1->timeopen]);
        $this->assertEquals(1, $openpriorities[$override2->timeopen]);

        $closepriorities = $priorities['close'];
        // Override 1's time close has higher priority since it is later than override 2's.
        $this->assertEquals(1, $closepriorities[$override1->timeclose]);
        $this->assertEquals(2, $closepriorities[$override2->timeclose]);
    }

    public function test_realtimequiz_core_calendar_provide_event_action_open() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a realtimequiz.
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS]);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id, QUIZ_EVENT_TYPE_OPEN);
        // Now, log in as student.
        $this->setUser($student);
        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_realtimequiz_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attemptrealtimequiznow', 'realtimequiz'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_realtimequiz_core_calendar_provide_event_action_open_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a realtimequiz.
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS]);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id, QUIZ_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_realtimequiz_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attemptrealtimequiznow', 'realtimequiz'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_realtimequiz_core_calendar_provide_event_action_closed() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a realtimequiz.
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id,
            'timeclose' => time() - DAYSECS]);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id, QUIZ_EVENT_TYPE_CLOSE);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm the result was null.
        $this->assertNull(mod_realtimequiz_core_calendar_provide_event_action($event, $factory));
    }

    public function test_realtimequiz_core_calendar_provide_event_action_closed_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a realtimequiz.
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id,
            'timeclose' => time() - DAYSECS]);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id, QUIZ_EVENT_TYPE_CLOSE);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm the result was null.
        $this->assertNull(mod_realtimequiz_core_calendar_provide_event_action($event, $factory, $student->id));
    }

    public function test_realtimequiz_core_calendar_provide_event_action_open_in_future() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a realtimequiz.
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id,
            'timeopen' => time() + DAYSECS]);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id, QUIZ_EVENT_TYPE_CLOSE);
        // Now, log in as student.
        $this->setUser($student);
        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_realtimequiz_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attemptrealtimequiznow', 'realtimequiz'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_realtimequiz_core_calendar_provide_event_action_open_in_future_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a realtimequiz.
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id,
            'timeopen' => time() + DAYSECS]);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id, QUIZ_EVENT_TYPE_CLOSE);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_realtimequiz_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attemptrealtimequiznow', 'realtimequiz'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_realtimequiz_core_calendar_provide_event_action_no_capability() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a realtimequiz.
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        // Remove the permission to attempt or review the realtimequiz for the student role.
        $coursecontext = \context_course::instance($course->id);
        assign_capability('mod/realtimequiz:reviewmyattempts', CAP_PROHIBIT, $studentrole->id, $coursecontext);
        assign_capability('mod/realtimequiz:attempt', CAP_PROHIBIT, $studentrole->id, $coursecontext);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id, QUIZ_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Set current user to the student.
        $this->setUser($student);

        // Confirm null is returned.
        $this->assertNull(mod_realtimequiz_core_calendar_provide_event_action($event, $factory));
    }

    public function test_realtimequiz_core_calendar_provide_event_action_no_capability_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a realtimequiz.
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        // Remove the permission to attempt or review the realtimequiz for the student role.
        $coursecontext = \context_course::instance($course->id);
        assign_capability('mod/realtimequiz:reviewmyattempts', CAP_PROHIBIT, $studentrole->id, $coursecontext);
        assign_capability('mod/realtimequiz:attempt', CAP_PROHIBIT, $studentrole->id, $coursecontext);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id, QUIZ_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm null is returned.
        $this->assertNull(mod_realtimequiz_core_calendar_provide_event_action($event, $factory, $student->id));
    }

    public function test_realtimequiz_core_calendar_provide_event_action_already_finished() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a realtimequiz.
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id,
            'sumgrades' => 1]);

        // Add a question to the realtimequiz.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        // Get the realtimequiz object.
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $student->id);

        // Create an attempt for the student in the realtimequiz.
        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow, false, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id, QUIZ_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Set current user to the student.
        $this->setUser($student);

        // Confirm null is returned.
        $this->assertNull(mod_realtimequiz_core_calendar_provide_event_action($event, $factory));
    }

    public function test_realtimequiz_core_calendar_provide_event_action_already_finished_for_user() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a realtimequiz.
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id,
            'sumgrades' => 1]);

        // Add a question to the realtimequiz.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        // Get the realtimequiz object.
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $student->id);

        // Create an attempt for the student in the realtimequiz.
        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow, false, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id, QUIZ_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm null is returned.
        $this->assertNull(mod_realtimequiz_core_calendar_provide_event_action($event, $factory, $student->id));
    }

    public function test_realtimequiz_core_calendar_provide_event_action_already_completed() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id],
            ['completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS]);

        // Get some additional data.
        $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_realtimequiz_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    public function test_realtimequiz_core_calendar_provide_event_action_already_completed_for_user() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id],
            ['completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS]);

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Get some additional data.
        $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $realtimequiz->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed for the student.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm, $student->id);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_realtimequiz_core_calendar_provide_event_action($event, $factory, $student->id);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid
     * @param int $instanceid The realtimequiz id.
     * @param string $eventtype The event type. eg. QUIZ_EVENT_TYPE_OPEN.
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype) {
        $event = new \stdClass();
        $event->name = 'Calendar event';
        $event->modulename  = 'realtimequiz';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = time();

        return \calendar_event::create($event);
    }

    /**
     * Test the callback responsible for returning the completion rule descriptions.
     * This function should work given either an instance of the module (cm_info), such as when checking the active rules,
     * or if passed a stdClass of similar structure, such as when checking the the default completion settings for a mod type.
     */
    public function test_mod_realtimequiz_completion_get_active_rule_descriptions() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Two activities, both with automatic completion. One has the 'completionsubmit' rule, one doesn't.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 2]);
        $realtimequiz1 = $this->getDataGenerator()->create_module('realtimequiz', [
            'course' => $course->id,
            'completion' => 2,
            'completionusegrade' => 1,
            'completionpassgrade' => 1,
            'completionattemptsexhausted' => 1,
        ]);
        $realtimequiz2 = $this->getDataGenerator()->create_module('realtimequiz', [
            'course' => $course->id,
            'completion' => 2,
            'completionusegrade' => 0
        ]);
        $cm1 = \cm_info::create(get_coursemodule_from_instance('realtimequiz', $realtimequiz1->id));
        $cm2 = \cm_info::create(get_coursemodule_from_instance('realtimequiz', $realtimequiz2->id));

        // Data for the stdClass input type.
        // This type of input would occur when checking the default completion rules for an activity type, where we don't have
        // any access to cm_info, rather the input is a stdClass containing completion and customdata attributes, just like cm_info.
        $moddefaults = new \stdClass();
        $moddefaults->customdata = ['customcompletionrules' => [
            'completionattemptsexhausted' => 1,
        ]];
        $moddefaults->completion = 2;

        $activeruledescriptions = [
            get_string('completionpassorattemptsexhausteddesc', 'realtimequiz'),
        ];
        $this->assertEquals(mod_realtimequiz_get_completion_active_rule_descriptions($cm1), $activeruledescriptions);
        $this->assertEquals(mod_realtimequiz_get_completion_active_rule_descriptions($cm2), []);
        $this->assertEquals(mod_realtimequiz_get_completion_active_rule_descriptions($moddefaults), $activeruledescriptions);
        $this->assertEquals(mod_realtimequiz_get_completion_active_rule_descriptions(new \stdClass()), []);
    }

    /**
     * A user who does not have capabilities to add events to the calendar should be able to create a realtimequiz.
     */
    public function test_creation_with_no_calendar_capabilities() {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = self::getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = self::getDataGenerator()->create_role();
        self::getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        assign_capability('moodle/calendar:manageentries', CAP_PROHIBIT, $roleid, $context, true);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        // Create an instance as a user without the calendar capabilities.
        $this->setUser($user);
        $time = time();
        $params = [
            'course' => $course->id,
            'timeopen' => $time + 200,
            'timeclose' => $time + 2000,
        ];
        $generator->create_instance($params);
    }

    /**
     * Data provider for summarise_response() test cases.
     *
     * @return array List of data sets (test cases)
     */
    public function mod_realtimequiz_inplace_editable_provider(): array {
        return [
            'set to A1' => [1, 'A1'],
            'set with HTML characters' => [2, 'A & &amp; <-:'],
            'set to integer' => [3, '3'],
            'set to blank' => [4, ''],
            'set with Unicode characters' => [1, 'L\'Aina Lluís^'],
            'set with Unicode at the truncation point' => [1, '123456789012345碁'],
            'set with HTML Char at the truncation point' => [1, '123456789012345>'],
        ];
    }

    /**
     * Test customised and automated question numbering for a given slot number and customised value.
     *
     * @dataProvider mod_realtimequiz_inplace_editable_provider
     * @param int $slotnumber
     * @param string $newvalue
     * @covers ::mod_realtimequiz_inplace_editable
     */
    public function test_mod_realtimequiz_inplace_editable(int $slotnumber, string $newvalue): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/external/externallib.php');
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id, 'sumgrades' => 1]);
        $cm = get_coursemodule_from_id('realtimequiz', $realtimequiz->cmid);

        // Add few questions to the realtimequiz.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $question = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        $question = $questiongenerator->create_question('multichoice', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        // Create the realtimequiz object.
        $realtimequizobj = new realtimequiz_settings($realtimequiz, $cm, $course);
        $structure = $realtimequizobj->get_structure();

        $slots = $structure->get_slots();
        $this->assertEquals(4, count($slots));

        $slotid = $structure->get_slot_id_for_slot($slotnumber);
        $inplaceeditable = mod_realtimequiz_inplace_editable('slotdisplaynumber', $slotid, $newvalue);
        $result = \core_external::update_inplace_editable('mod_realtimequiz', 'slotdisplaynumber', $slotid, $newvalue);
        $result = external_api::clean_returnvalue(\core_external::update_inplace_editable_returns(), $result);

        $this->assertEquals(count((array) $inplaceeditable), count($result));
        $this->assertEquals($slotid, $result['itemid']);
        if ($newvalue === '' || is_null($newvalue)) {
            // Check against default.
            $this->assertEquals($slotnumber, $result['displayvalue']);
            $this->assertEquals($slotnumber, $result['value']);
        } else {
            // Check against the custom number.
            $this->assertEquals(s($newvalue), $result['displayvalue']);
            $this->assertEquals($newvalue, $result['value']);
        }
    }
}
