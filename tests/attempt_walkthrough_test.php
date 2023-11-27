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

use moodle_url;
use question_bank;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

/**
 * Quiz attempt walk through.
 *
 * @package   mod_realtimequiz
 * @category  test
 * @copyright 2013 The Open University
 * @author    Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_realtimequiz\realtimequiz_attempt
 */
class attempt_walkthrough_test extends \advanced_testcase {

    /**
     * Create a realtimequiz with questions and walk through a realtimequiz attempt.
     */
    public function test_realtimequiz_attempt_walkthrough() {
        global $SITE;

        $this->resetAfterTest(true);

        // Make a realtimequiz.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $SITE->id, 'questionsperpage' => 0, 'grade' => 100.0,
                                                      'sumgrades' => 3]);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        $matchq = $questiongenerator->create_question('match', null, ['category' => $cat->id]);

        // Add them to the realtimequiz.
        realtimequiz_add_realtimequiz_question($saq->id, $realtimequiz);
        realtimequiz_add_realtimequiz_question($numq->id, $realtimequiz);
        realtimequiz_add_realtimequiz_question($matchq->id, $realtimequiz);

        // Make a user to do the realtimequiz.
        $user1 = $this->getDataGenerator()->create_user();

        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $user1->id);

        // Start the attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow, false, $user1->id);

        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);
        $this->assertEquals('1,2,3,0', $attempt->layout);

        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertFalse($attemptobj->has_response_to_at_least_one_graded_question());
        // The student has not answered any questions.
        $this->assertEquals(3, $attemptobj->get_number_of_unanswered_questions());

        $tosubmit = [1 => ['answer' => 'frog'],
                          2 => ['answer' => '3.14']];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);
        // The student has answered two questions, and only one remaining.
        $this->assertEquals(1, $attemptobj->get_number_of_unanswered_questions());

        $tosubmit = [
            3 => [
                'frog' => 'amphibian',
                'cat' => 'mammal',
                'newt' => ''
            ]
        ];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);
        // The student has answered three questions but one is invalid, so there is still one remaining.
        $this->assertEquals(1, $attemptobj->get_number_of_unanswered_questions());

        $tosubmit = [
            3 => [
                'frog' => 'amphibian',
                'cat' => 'mammal',
                'newt' => 'amphibian'
            ]
        ];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);
        // The student has answered three questions, so there are no remaining.
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Re-load realtimequiz attempt data.
        $attemptobj = realtimequiz_attempt::create($attempt->id);

        // Check that results are stored as expected.
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertEquals(3, $attemptobj->get_sum_marks());
        $this->assertEquals(true, $attemptobj->is_finished());
        $this->assertEquals($timenow, $attemptobj->get_submitted_date());
        $this->assertEquals($user1->id, $attemptobj->get_userid());
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

        // Check realtimequiz grades.
        $grades = realtimequiz_get_user_grades($realtimequiz, $user1->id);
        $grade = array_shift($grades);
        $this->assertEquals(100.0, $grade->rawgrade);

        // Check grade book.
        $gradebookgrades = grade_get_grades($SITE->id, 'mod', 'realtimequiz', $realtimequiz->id, $user1->id);
        $gradebookitem = array_shift($gradebookgrades->items);
        $gradebookgrade = array_shift($gradebookitem->grades);
        $this->assertEquals(100, $gradebookgrade->grade);
    }

    /**
     * Create a realtimequiz containing one question and a close time.
     *
     * The question is the standard shortanswer test question.
     * The realtimequiz is set to close 1 hour from now.
     * The realtimequiz is set to use a grade period of 1 hour once time expires.
     *
     * @param string $overduehandling value for the overduehandling realtimequiz setting.
     * @return \stdClass the realtimequiz that was created.
     */
    protected function create_realtimequiz_with_one_question(string $overduehandling = 'graceperiod'): \stdClass {
        global $SITE;
        $this->resetAfterTest();

        // Make a realtimequiz.
        $timeclose = time() + HOURSECS;
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        $realtimequiz = $realtimequizgenerator->create_instance(
                ['course' => $SITE->id, 'timeclose' => $timeclose,
                        'overduehandling' => $overduehandling, 'graceperiod' => HOURSECS]);

        // Create a question.
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);

        // Add them to the realtimequiz.
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id);
        realtimequiz_add_realtimequiz_question($saq->id, $realtimequiz, 0, 1);
        $realtimequizobj->get_grade_calculator()->recompute_realtimequiz_sumgrades();

        return $realtimequiz;
    }

    public function test_realtimequiz_attempt_walkthrough_submit_time_recorded_correctly_when_overdue() {

        $realtimequiz = $this->create_realtimequiz_with_one_question();

        // Make a user to do the realtimequiz.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $user->id);

        // Start the attempt.
        $attempt = realtimequiz_prepare_and_start_new_attempt($realtimequizobj, 1, null);

        // Process some responses from the student.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertEquals(1, $attemptobj->get_number_of_unanswered_questions());
        $attemptobj->process_submitted_actions($realtimequiz->timeclose - 30 * MINSECS, false, [1 => ['answer' => 'frog']]);

        // Attempt goes overdue (e.g. if cron ran).
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_going_overdue($realtimequiz->timeclose + 2 * get_config('realtimequiz', 'graceperiodmin'), false);

        // Verify the attempt state.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertEquals(false, $attemptobj->is_finished());
        $this->assertEquals(0, $attemptobj->get_submitted_date());
        $this->assertEquals($user->id, $attemptobj->get_userid());
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

        // Student submits the attempt during the grace period.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_attempt($realtimequiz->timeclose + 30 * MINSECS, true, false, 1);

        // Verify the attempt state.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertEquals(true, $attemptobj->is_finished());
        $this->assertEquals($realtimequiz->timeclose + 30 * MINSECS, $attemptobj->get_submitted_date());
        $this->assertEquals($user->id, $attemptobj->get_userid());
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());
    }

    public function test_realtimequiz_attempt_walkthrough_close_time_extended_at_last_minute() {
        global $DB;

        $realtimequiz = $this->create_realtimequiz_with_one_question();
        $originaltimeclose = $realtimequiz->timeclose;

        // Make a user to do the realtimequiz.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $user->id);

        // Start the attempt.
        $attempt = realtimequiz_prepare_and_start_new_attempt($realtimequizobj, 1, null);

        // Process some responses from the student during the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($originaltimeclose - 30 * MINSECS, false, [1 => ['answer' => 'frog']]);

        // Teacher edits the realtimequiz to extend the time-limit by one minute.
        $DB->set_field('realtimequiz', 'timeclose', $originaltimeclose + MINSECS, ['id' => $realtimequiz->id]);
        \course_modinfo::clear_instance_cache($realtimequiz->course);

        // Timer expires in the student browser and thinks it is time to submit the realtimequiz.
        // This sets $finishattempt to false - since the student did not click the button, and $timeup to true.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_attempt($originaltimeclose, false, true, 1);

        // Verify the attempt state - the $timeup was ignored becuase things have changed server-side.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertFalse($attemptobj->is_finished());
        $this->assertEquals(realtimequiz_attempt::IN_PROGRESS, $attemptobj->get_state());
        $this->assertEquals(0, $attemptobj->get_submitted_date());
        $this->assertEquals($user->id, $attemptobj->get_userid());
    }

    /**
     * Create a realtimequiz with a random as well as other questions and walk through realtimequiz attempts.
     */
    public function test_realtimequiz_with_random_question_attempt_walkthrough() {
        global $SITE;

        $this->resetAfterTest(true);
        question_bank::get_qtype('random')->clear_caches_before_testing();

        $this->setAdminUser();

        // Make a realtimequiz.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $SITE->id, 'questionsperpage' => 2, 'grade' => 100.0,
                                                      'sumgrades' => 4]);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Add two questions to question category.
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);

        // Add random question to the realtimequiz.
        realtimequiz_add_random_questions($realtimequiz, 0, $cat->id, 1, false);

        // Make another category.
        $cat2 = $questiongenerator->create_question_category();
        $match = $questiongenerator->create_question('match', null, ['category' => $cat->id]);

        realtimequiz_add_realtimequiz_question($match->id, $realtimequiz, 0);

        $multichoicemulti = $questiongenerator->create_question('multichoice', 'two_of_four', ['category' => $cat->id]);

        realtimequiz_add_realtimequiz_question($multichoicemulti->id, $realtimequiz, 0);

        $multichoicesingle = $questiongenerator->create_question('multichoice', 'one_of_four', ['category' => $cat->id]);

        realtimequiz_add_realtimequiz_question($multichoicesingle->id, $realtimequiz, 0);

        foreach ([$saq->id => 'frog', $numq->id => '3.14'] as $randomqidtoselect => $randqanswer) {
            // Make a new user to do the realtimequiz each loop.
            $user1 = $this->getDataGenerator()->create_user();
            $this->setUser($user1);

            $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $user1->id);

            // Start the attempt.
            $quba = question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
            $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

            $timenow = time();
            $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow);

            realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow, [1 => $randomqidtoselect]);
            $this->assertEquals('1,2,0,3,4,0', $attempt->layout);

            realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

            // Process some responses from the student.
            $attemptobj = realtimequiz_attempt::create($attempt->id);
            $this->assertFalse($attemptobj->has_response_to_at_least_one_graded_question());
            $this->assertEquals(4, $attemptobj->get_number_of_unanswered_questions());

            $tosubmit = [];
            $selectedquestionid = $quba->get_question_attempt(1)->get_question_id();
            $tosubmit[1] = ['answer' => $randqanswer];
            $tosubmit[2] = [
                'frog' => 'amphibian',
                'cat'  => 'mammal',
                'newt' => 'amphibian'];
            $tosubmit[3] = ['One' => '1', 'Two' => '0', 'Three' => '1', 'Four' => '0']; // First and third choice.
            $tosubmit[4] = ['answer' => 'One']; // The first choice.

            $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

            // Finish the attempt.
            $attemptobj = realtimequiz_attempt::create($attempt->id);
            $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
            $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());
            $attemptobj->process_finish($timenow, false);

            // Re-load realtimequiz attempt data.
            $attemptobj = realtimequiz_attempt::create($attempt->id);

            // Check that results are stored as expected.
            $this->assertEquals(1, $attemptobj->get_attempt_number());
            $this->assertEquals(4, $attemptobj->get_sum_marks());
            $this->assertEquals(true, $attemptobj->is_finished());
            $this->assertEquals($timenow, $attemptobj->get_submitted_date());
            $this->assertEquals($user1->id, $attemptobj->get_userid());
            $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
            $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

            // Check realtimequiz grades.
            $grades = realtimequiz_get_user_grades($realtimequiz, $user1->id);
            $grade = array_shift($grades);
            $this->assertEquals(100.0, $grade->rawgrade);

            // Check grade book.
            $gradebookgrades = grade_get_grades($SITE->id, 'mod', 'realtimequiz', $realtimequiz->id, $user1->id);
            $gradebookitem = array_shift($gradebookgrades->items);
            $gradebookgrade = array_shift($gradebookitem->grades);
            $this->assertEquals(100, $gradebookgrade->grade);
        }
    }


    public function get_correct_response_for_variants() {
        return [[1, 9.9], [2, 8.5], [5, 14.2], [10, 6.8, true]];
    }

    protected $realtimequizwithvariants = null;

    /**
     * Create a realtimequiz with a single question with variants and walk through realtimequiz attempts.
     *
     * @dataProvider get_correct_response_for_variants
     */
    public function test_realtimequiz_with_question_with_variants_attempt_walkthrough($variantno, $correctresponse, $done = false) {
        global $SITE;

        $this->resetAfterTest($done);

        $this->setAdminUser();

        if ($this->realtimequizwithvariants === null) {
            // Make a realtimequiz.
            $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

            $this->realtimequizwithvariants = $realtimequizgenerator->create_instance(['course' => $SITE->id,
                                                                            'questionsperpage' => 0,
                                                                            'grade' => 100.0,
                                                                            'sumgrades' => 1]);

            $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

            $cat = $questiongenerator->create_question_category();
            $calc = $questiongenerator->create_question('calculatedsimple', 'sumwithvariants', ['category' => $cat->id]);
            realtimequiz_add_realtimequiz_question($calc->id, $this->realtimequizwithvariants, 0);
        }


        // Make a new user to do the realtimequiz.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $realtimequizobj = realtimequiz_settings::create($this->realtimequizwithvariants->id, $user1->id);

        // Start the attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow);

        // Select variant.
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow, [], [1 => $variantno]);
        $this->assertEquals('1,0', $attempt->layout);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertFalse($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(1, $attemptobj->get_number_of_unanswered_questions());

        $tosubmit = [1 => ['answer' => $correctresponse]];
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

        $attemptobj->process_finish($timenow, false);

        // Re-load realtimequiz attempt data.
        $attemptobj = realtimequiz_attempt::create($attempt->id);

        // Check that results are stored as expected.
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertEquals(1, $attemptobj->get_sum_marks());
        $this->assertEquals(true, $attemptobj->is_finished());
        $this->assertEquals($timenow, $attemptobj->get_submitted_date());
        $this->assertEquals($user1->id, $attemptobj->get_userid());
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

        // Check realtimequiz grades.
        $grades = realtimequiz_get_user_grades($this->realtimequizwithvariants, $user1->id);
        $grade = array_shift($grades);
        $this->assertEquals(100.0, $grade->rawgrade);

        // Check grade book.
        $gradebookgrades = grade_get_grades($SITE->id, 'mod', 'realtimequiz', $this->realtimequizwithvariants->id, $user1->id);
        $gradebookitem = array_shift($gradebookgrades->items);
        $gradebookgrade = array_shift($gradebookitem->grades);
        $this->assertEquals(100, $gradebookgrade->grade);
    }

    public function test_realtimequiz_attempt_walkthrough_abandoned_attempt_reopened_with_timelimit_override() {
        global $DB;

        $realtimequiz = $this->create_realtimequiz_with_one_question('autoabandon');
        $originaltimeclose = $realtimequiz->timeclose;

        // Make a user to do the realtimequiz.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $user->id);

        // Start the attempt.
        $attempt = realtimequiz_prepare_and_start_new_attempt($realtimequizobj, 1, null);

        // Process some responses from the student during the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($originaltimeclose - 30 * MINSECS, false, [1 => ['answer' => 'frog']]);

        // Student leaves, so cron closes the attempt when time expires.
        $attemptobj->process_abandon($originaltimeclose + 5 * MINSECS, false);

        // Verify the attempt state.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertEquals(realtimequiz_attempt::ABANDONED, $attemptobj->get_state());
        $this->assertEquals(0, $attemptobj->get_submitted_date());
        $this->assertEquals($user->id, $attemptobj->get_userid());

        // The teacher feels kind, so adds an override for the student, and re-opens the attempt.
        $sink = $this->redirectEvents();
        $overriddentimeclose = $originaltimeclose + HOURSECS;
        $DB->insert_record('realtimequiz_overrides', [
            'realtimequiz' => $realtimequiz->id,
            'userid' => $user->id,
            'timeclose' => $overriddentimeclose,
        ]);
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $reopentime = $originaltimeclose + 10 * MINSECS;
        $attemptobj->process_reopen_abandoned($reopentime);

        // Verify the attempt state.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertFalse($attemptobj->is_finished());
        $this->assertEquals(realtimequiz_attempt::IN_PROGRESS, $attemptobj->get_state());
        $this->assertEquals(0, $attemptobj->get_submitted_date());
        $this->assertEquals($user->id, $attemptobj->get_userid());
        $this->assertEquals($overriddentimeclose,
                $attemptobj->get_access_manager($reopentime)->get_end_time($attemptobj->get_attempt()));

        // Verify this was logged correctly.
        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $reopenedevent = array_shift($events);
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_reopened', $reopenedevent);
        $this->assertEquals($attemptobj->get_context(), $reopenedevent->get_context());
        $this->assertEquals(new moodle_url('/mod/realtimequiz/review.php', ['attempt' => $attemptobj->get_attemptid()]),
                $reopenedevent->get_url());
    }

    public function test_realtimequiz_attempt_walkthrough_abandoned_attempt_reopened_after_close_time() {
        $realtimequiz = $this->create_realtimequiz_with_one_question('autoabandon');
        $originaltimeclose = $realtimequiz->timeclose;

        // Make a user to do the realtimequiz.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $user->id);

        // Start the attempt.
        $attempt = realtimequiz_prepare_and_start_new_attempt($realtimequizobj, 1, null);

        // Process some responses from the student during the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($originaltimeclose - 30 * MINSECS, false, [1 => ['answer' => 'frog']]);

        // Student leaves, so cron closes the attempt when time expires.
        $attemptobj->process_abandon($originaltimeclose + 5 * MINSECS, false);

        // Verify the attempt state.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertEquals(realtimequiz_attempt::ABANDONED, $attemptobj->get_state());
        $this->assertEquals(0, $attemptobj->get_submitted_date());
        $this->assertEquals($user->id, $attemptobj->get_userid());

        // The teacher reopens the attempt without granting more time, so previously submitted responess are graded.
        $sink = $this->redirectEvents();
        $reopentime = $originaltimeclose + 10 * MINSECS;
        $attemptobj->process_reopen_abandoned($reopentime);

        // Verify the attempt state.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertTrue($attemptobj->is_finished());
        $this->assertEquals(realtimequiz_attempt::FINISHED, $attemptobj->get_state());
        $this->assertEquals($originaltimeclose, $attemptobj->get_submitted_date());
        $this->assertEquals($user->id, $attemptobj->get_userid());
        $this->assertEquals(1, $attemptobj->get_sum_marks());

        // Verify this was logged correctly - there are some gradebook events between the two we want to check.
        $events = $sink->get_events();
        $this->assertGreaterThanOrEqual(2, $events);

        $reopenedevent = array_shift($events);
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_reopened', $reopenedevent);
        $this->assertEquals($attemptobj->get_context(), $reopenedevent->get_context());
        $this->assertEquals(new moodle_url('/mod/realtimequiz/review.php', ['attempt' => $attemptobj->get_attemptid()]),
                $reopenedevent->get_url());

        $submittedevent = array_pop($events);
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_submitted', $submittedevent);
        $this->assertEquals($attemptobj->get_context(), $submittedevent->get_context());
        $this->assertEquals(new moodle_url('/mod/realtimequiz/review.php', ['attempt' => $attemptobj->get_attemptid()]),
                $submittedevent->get_url());
    }
}
