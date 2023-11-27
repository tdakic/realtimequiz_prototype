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
 * Privacy provider tests.
 *
 * @package    mod_realtimequiz
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_realtimequiz\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\writer;
use mod_realtimequiz\privacy\provider;
use mod_realtimequiz\privacy\helper;
use mod_realtimequiz\realtimequiz_attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/tests/privacy_helper.php');

/**
 * Privacy provider tests class.
 *
 * @package    mod_realtimequiz
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_test extends \core_privacy\tests\provider_testcase {

    use \core_question_privacy_helper;

    /**
     * Test that a user who has no data gets no contexts
     */
    public function test_get_contexts_for_userid_no_data() {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $contextlist = provider::get_contexts_for_userid($USER->id);
        $this->assertEmpty($contextlist);
    }

    /**
     * Test for provider::get_contexts_for_userid() when there is no realtimequiz attempt at all.
     */
    public function test_get_contexts_for_userid_no_attempt_with_override() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Make a realtimequiz with an override.
        $this->setUser();
        $realtimequiz = $this->create_test_realtimequiz($course);
        $DB->insert_record('realtimequiz_overrides', [
            'realtimequiz' => $realtimequiz->id,
            'userid' => $user->id,
            'timeclose' => 1300,
            'timelimit' => null,
        ]);

        $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id);
        $context = \context_module::instance($cm->id);

        // Fetch the contexts - only one context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
    }

    /**
     * The export function should handle an empty contextlist properly.
     */
    public function test_export_user_data_no_data() {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($USER->id),
            'mod_realtimequiz',
            []
        );

        provider::export_user_data($approvedcontextlist);
        $this->assertDebuggingNotCalled();

        // No data should have been exported.
        $writer = \core_privacy\local\request\writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data_in_any_context());
    }

    /**
     * The delete function should handle an empty contextlist properly.
     */
    public function test_delete_data_for_user_no_data() {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($USER->id),
            'mod_realtimequiz',
            []
        );

        provider::delete_data_for_user($approvedcontextlist);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Export + Delete realtimequiz data for a user who has made a single attempt.
     */
    public function test_user_with_data() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        // Make a realtimequiz with an override.
        $this->setUser();
        $realtimequiz = $this->create_test_realtimequiz($course);
        $DB->insert_record('realtimequiz_overrides', [
                'realtimequiz' => $realtimequiz->id,
                'userid' => $user->id,
                'timeclose' => 1300,
                'timelimit' => null,
            ]);

        // Run as the user and make an attempt on the realtimequiz.
        list($realtimequizobj, $quba, $attemptobj) = $this->attempt_realtimequiz($realtimequiz, $user);
        $this->attempt_realtimequiz($realtimequiz, $otheruser);
        $context = $realtimequizobj->get_context();

        // Fetch the contexts - only one context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());

        // Perform the export and check the data.
        $this->setUser($user);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_realtimequiz',
            $contextlist->get_contextids()
        );
        provider::export_user_data($approvedcontextlist);

        // Ensure that the realtimequiz data was exported correctly.
        /** @var \core_privacy\tests\request\content_writer $writer */
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $realtimequizdata = $writer->get_data([]);
        $this->assertEquals($realtimequizobj->get_realtimequiz_name(), $realtimequizdata->name);

        // Every module has an intro.
        $this->assertTrue(isset($realtimequizdata->intro));

        // Fetch the attempt data.
        $attempt = $attemptobj->get_attempt();
        $attemptsubcontext = [
            get_string('attempts', 'mod_realtimequiz'),
            $attempt->attempt,
        ];
        $attemptdata = writer::with_context($context)->get_data($attemptsubcontext);

        $attempt = $attemptobj->get_attempt();
        $this->assertTrue(isset($attemptdata->state));
        $this->assertEquals(realtimequiz_attempt::state_name($attemptobj->get_state()), $attemptdata->state);
        $this->assertTrue(isset($attemptdata->timestart));
        $this->assertTrue(isset($attemptdata->timefinish));
        $this->assertTrue(isset($attemptdata->timemodified));
        $this->assertFalse(isset($attemptdata->timemodifiedoffline));
        $this->assertFalse(isset($attemptdata->timecheckstate));

        $this->assertTrue(isset($attemptdata->grade));
        $this->assertEquals(100.00, $attemptdata->grade->grade);

        // Check that the exported question attempts are correct.
        $attemptsubcontext = helper::get_realtimequiz_attempt_subcontext($attemptobj->get_attempt(), $user);
        $this->assert_question_attempt_exported(
            $context,
            $attemptsubcontext,
            \question_engine::load_questions_usage_by_activity($attemptobj->get_uniqueid()),
            realtimequiz_get_review_options($realtimequiz, $attemptobj->get_attempt(), $context),
            $user
        );

        // Delete the data and check it is removed.
        $this->setUser();
        provider::delete_data_for_user($approvedcontextlist);
        $this->expectException(\dml_missing_record_exception::class);
        realtimequiz_attempt::create($attemptobj->get_realtimequizid());
    }

    /**
     * Export + Delete realtimequiz data for a user who has made a single attempt.
     */
    public function test_user_with_preview() {
        global $DB;
        $this->resetAfterTest(true);

        // Make a realtimequiz.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        $realtimequiz = $realtimequizgenerator->create_instance([
                'course' => $course->id,
                'questionsperpage' => 0,
                'grade' => 100.0,
                'sumgrades' => 2,
            ]);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($saq->id, $realtimequiz);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($numq->id, $realtimequiz);

        // Run as the user and make an attempt on the realtimequiz.
        $this->setUser($user);
        $starttime = time();
        $realtimequizobj = \mod_realtimequiz\realtimequiz_settings::create($realtimequiz->id, $user->id);
        $context = $realtimequizobj->get_context();

        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        // Start the attempt.
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $starttime, true, $user->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $starttime);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Answer the questions.
        $attemptobj = realtimequiz_attempt::create($attempt->id);

        $tosubmit = [
            1 => ['answer' => 'frog'],
            2 => ['answer' => '3.14'],
        ];

        $attemptobj->process_submitted_actions($starttime, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($starttime, false);

        // Fetch the contexts - no context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);
    }

    /**
     * Export + Delete realtimequiz data for a user who has made a single attempt.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        // Make a realtimequiz with an override.
        $this->setUser();
        $realtimequiz = $this->create_test_realtimequiz($course);
        $DB->insert_record('realtimequiz_overrides', [
                'realtimequiz' => $realtimequiz->id,
                'userid' => $user->id,
                'timeclose' => 1300,
                'timelimit' => null,
            ]);

        // Run as the user and make an attempt on the realtimequiz.
        list($realtimequizobj, $quba, $attemptobj) = $this->attempt_realtimequiz($realtimequiz, $user);
        list($realtimequizobj, $quba, $attemptobj) = $this->attempt_realtimequiz($realtimequiz, $otheruser);

        // Create another realtimequiz and questions, and repeat the data insertion.
        $this->setUser();
        $otherrealtimequiz = $this->create_test_realtimequiz($course);
        $DB->insert_record('realtimequiz_overrides', [
                'realtimequiz' => $otherrealtimequiz->id,
                'userid' => $user->id,
                'timeclose' => 1300,
                'timelimit' => null,
            ]);

        // Run as the user and make an attempt on the realtimequiz.
        list($otherrealtimequizobj, $otherquba, $otherattemptobj) = $this->attempt_realtimequiz($otherrealtimequiz, $user);
        list($otherrealtimequizobj, $otherquba, $otherattemptobj) = $this->attempt_realtimequiz($otherrealtimequiz, $otheruser);

        // Delete all data for all users in the context under test.
        $this->setUser();
        $context = $realtimequizobj->get_context();
        provider::delete_data_for_all_users_in_context($context);

        // The realtimequiz attempt should have been deleted from this realtimequiz.
        $this->assertCount(0, $DB->get_records('realtimequiz_attempts', ['realtimequiz' => $realtimequizobj->get_realtimequizid()]));
        $this->assertCount(0, $DB->get_records('realtimequiz_overrides', ['realtimequiz' => $realtimequizobj->get_realtimequizid()]));
        $this->assertCount(0, $DB->get_records('question_attempts', ['questionusageid' => $quba->get_id()]));

        // But not for the other realtimequiz.
        $this->assertNotCount(0, $DB->get_records('realtimequiz_attempts', ['realtimequiz' => $otherrealtimequizobj->get_realtimequizid()]));
        $this->assertNotCount(0, $DB->get_records('realtimequiz_overrides', ['realtimequiz' => $otherrealtimequizobj->get_realtimequizid()]));
        $this->assertNotCount(0, $DB->get_records('question_attempts', ['questionusageid' => $otherquba->get_id()]));
    }

    /**
     * Export + Delete realtimequiz data for a user who has made a single attempt.
     */
    public function test_wrong_context() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Make a choice.
        $this->setUser();
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('mod_choice');
        $choice = $plugingenerator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('choice', $choice->id);
        $context = \context_module::instance($cm->id);

        // Fetch the contexts - no context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);

        // Perform the export and check the data.
        $this->setUser($user);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_realtimequiz',
            [$context->id]
        );
        provider::export_user_data($approvedcontextlist);

        // Ensure that nothing was exported.
        /** @var \core_privacy\tests\request\content_writer $writer */
        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data_in_any_context());

        $this->setUser();

        $dbwrites = $DB->perf_get_writes();

        // Perform a deletion with the approved contextlist containing an incorrect context.
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_realtimequiz',
            [$context->id]
        );
        provider::delete_data_for_user($approvedcontextlist);
        $this->assertEquals($dbwrites, $DB->perf_get_writes());
        $this->assertDebuggingNotCalled();

        // Perform a deletion of all data in the context.
        provider::delete_data_for_all_users_in_context($context);
        $this->assertEquals($dbwrites, $DB->perf_get_writes());
        $this->assertDebuggingNotCalled();
    }

    /**
     * Create a test realtimequiz for the specified course.
     *
     * @param   \stdClass $course
     * @return  \stdClass
     */
    protected function create_test_realtimequiz($course) {
        global $DB;

        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        $realtimequiz = $realtimequizgenerator->create_instance([
                'course' => $course->id,
                'questionsperpage' => 0,
                'grade' => 100.0,
                'sumgrades' => 2,
            ]);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($saq->id, $realtimequiz);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($numq->id, $realtimequiz);

        return $realtimequiz;
    }

    /**
     * Answer questions for a realtimequiz + user.
     *
     * @param   \stdClass   $realtimequiz
     * @param   \stdClass   $user
     * @return  array
     */
    protected function attempt_realtimequiz($realtimequiz, $user) {
        $this->setUser($user);

        $starttime = time();
        $realtimequizobj = \mod_realtimequiz\realtimequiz_settings::create($realtimequiz->id, $user->id);
        $context = $realtimequizobj->get_context();

        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        // Start the attempt.
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $starttime, false, $user->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $starttime);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Answer the questions.
        $attemptobj = realtimequiz_attempt::create($attempt->id);

        $tosubmit = [
            1 => ['answer' => 'frog'],
            2 => ['answer' => '3.14'],
        ];

        $attemptobj->process_submitted_actions($starttime, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_finish($starttime, false);

        $this->setUser();

        return [$realtimequizobj, $quba, $attemptobj];
    }

    /**
     * Test for provider::get_users_in_context().
     */
    public function test_get_users_in_context() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $anotheruser = $this->getDataGenerator()->create_user();
        $extrauser = $this->getDataGenerator()->create_user();

        // Make a realtimequiz.
        $this->setUser();
        $realtimequiz = $this->create_test_realtimequiz($course);

        // Create an override for user1.
        $DB->insert_record('realtimequiz_overrides', [
            'realtimequiz' => $realtimequiz->id,
            'userid' => $user->id,
            'timeclose' => 1300,
            'timelimit' => null,
        ]);

        // Make an attempt on the realtimequiz as user2.
        list($realtimequizobj, $quba, $attemptobj) = $this->attempt_realtimequiz($realtimequiz, $anotheruser);
        $context = $realtimequizobj->get_context();

        // Fetch users - user1 and user2 should be returned.
        $userlist = new \core_privacy\local\request\userlist($context, 'mod_realtimequiz');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing(
                [$user->id, $anotheruser->id],
                $userlist->get_userids());
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users() {
        global $DB;
        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        // Make a realtimequiz in each course.
        $realtimequiz1 = $this->create_test_realtimequiz($course1);
        $realtimequiz2 = $this->create_test_realtimequiz($course2);

        // Attempt realtimequiz1 as user1 and user2.
        list($realtimequiz1obj) = $this->attempt_realtimequiz($realtimequiz1, $user1);
        $this->attempt_realtimequiz($realtimequiz1, $user2);

        // Create an override in realtimequiz1 for user3.
        $DB->insert_record('realtimequiz_overrides', [
            'realtimequiz' => $realtimequiz1->id,
            'userid' => $user3->id,
            'timeclose' => 1300,
            'timelimit' => null,
        ]);

        // Attempt realtimequiz2 as user1.
        $this->attempt_realtimequiz($realtimequiz2, $user1);

        // Delete the data for user1 and user3 in course1 and check it is removed.
        $realtimequiz1context = $realtimequiz1obj->get_context();
        $approveduserlist = new \core_privacy\local\request\approved_userlist($realtimequiz1context, 'mod_realtimequiz',
                [$user1->id, $user3->id]);
        provider::delete_data_for_users($approveduserlist);

        // Only the attempt of user2 should be remained in realtimequiz1.
        $this->assertEquals(
                [$user2->id],
                $DB->get_fieldset_select('realtimequiz_attempts', 'userid', 'realtimequiz = ?', [$realtimequiz1->id])
        );

        // The attempt that user1 made in realtimequiz2 should be remained.
        $this->assertEquals(
                [$user1->id],
                $DB->get_fieldset_select('realtimequiz_attempts', 'userid', 'realtimequiz = ?', [$realtimequiz2->id])
        );

        // The realtimequiz override in realtimequiz1 that we had for user3 should be deleted.
        $this->assertEquals(
                [],
                $DB->get_fieldset_select('realtimequiz_overrides', 'userid', 'realtimequiz = ?', [$realtimequiz1->id])
        );
    }
}
