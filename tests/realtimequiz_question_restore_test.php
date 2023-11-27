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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/realtimequiz_question_helper_test_trait.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

/**
 * Quiz backup and restore tests.
 *
 * @package    mod_realtimequiz
 * @category   test
 * @copyright  2021 Catalyst IT Australia Pty Ltd
 * @author     Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_realtimequiz\question\bank\qbank_helper
 * @coversDefaultClass \backup_realtimequiz_activity_structure_step
 * @coversDefaultClass \restore_realtimequiz_activity_structure_step
 */
class realtimequiz_question_restore_test extends \advanced_testcase {
    use \realtimequiz_question_helper_test_trait;

    /**
     * @var \stdClass test student user.
     */
    protected $student;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        global $USER;
        parent::setUp();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->user = $USER;
    }

    /**
     * Test a realtimequiz backup and restore in a different course without attempts for course question bank.
     *
     * @covers ::get_question_structure
     */
    public function test_realtimequiz_restore_in_a_different_course_using_course_question_bank() {
        $this->resetAfterTest();

        // Create the test realtimequiz.
        $realtimequiz = $this->create_test_realtimequiz($this->course);
        $oldrealtimequizcontext = \context_module::instance($realtimequiz->cmid);
        // Test for questions from a different context.
        $coursecontext = \context_course::instance($this->course->id);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $realtimequiz, ['contextid' => $coursecontext->id]);
        $this->add_one_random_question($questiongenerator, $realtimequiz, ['contextid' => $coursecontext->id]);

        // Make the backup.
        $backupid = $this->backup_realtimequiz($realtimequiz, $this->user);

        // Delete the current course to make sure there is no data.
        delete_course($this->course, false);

        // Check if the questions and associated data are deleted properly.
        $this->assertEquals(0, count(\mod_realtimequiz\question\bank\qbank_helper::get_question_structure(
                $realtimequiz->id, $oldrealtimequizcontext)));

        // Restore the course.
        $newcourse = $this->getDataGenerator()->create_course();
        $this->restore_realtimequiz($backupid, $newcourse, $this->user);

        // Verify.
        $modules = get_fast_modinfo($newcourse->id)->get_instances_of('realtimequiz');
        $module = reset($modules);
        $questions = \mod_realtimequiz\question\bank\qbank_helper::get_question_structure(
                $module->instance, $module->context);
        $this->assertCount(3, $questions);
    }

    /**
     * Test a realtimequiz backup and restore in a different course without attempts for realtimequiz question bank.
     *
     * @covers ::get_question_structure
     */
    public function test_realtimequiz_restore_in_a_different_course_using_realtimequiz_question_bank() {
        $this->resetAfterTest();

        // Create the test realtimequiz.
        $realtimequiz = $this->create_test_realtimequiz($this->course);
        // Test for questions from a different context.
        $realtimequizcontext = \context_module::instance($realtimequiz->cmid);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $realtimequiz, ['contextid' => $realtimequizcontext->id]);
        $this->add_one_random_question($questiongenerator, $realtimequiz, ['contextid' => $realtimequizcontext->id]);

        // Make the backup.
        $backupid = $this->backup_realtimequiz($realtimequiz, $this->user);

        // Delete the current course to make sure there is no data.
        delete_course($this->course, false);

        // Check if the questions and associated datas are deleted properly.
        $this->assertEquals(0, count(\mod_realtimequiz\question\bank\qbank_helper::get_question_structure(
                $realtimequiz->id, $realtimequizcontext)));

        // Restore the course.
        $newcourse = $this->getDataGenerator()->create_course();
        $this->restore_realtimequiz($backupid, $newcourse, $this->user);

        // Verify.
        $modules = get_fast_modinfo($newcourse->id)->get_instances_of('realtimequiz');
        $module = reset($modules);
        $this->assertEquals(3, count(\mod_realtimequiz\question\bank\qbank_helper::get_question_structure(
                $module->instance, $module->context)));
    }

    /**
     * Count the questions for the context.
     *
     * @param int $contextid
     * @param string $extracondition
     * @return int the number of questions.
     */
    protected function question_count(int $contextid, string $extracondition = ''): int {
        global $DB;
        return $DB->count_records_sql(
            "SELECT COUNT(q.id)
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc on qc.id = qbe.questioncategoryid
              WHERE qc.contextid = ?
              $extracondition", [$contextid]);
    }

    /**
     * Test if a duplicate does not duplicate questions in course question bank.
     *
     * @covers ::duplicate_module
     */
    public function test_realtimequiz_duplicate_does_not_duplicate_course_question_bank_questions() {
        $this->resetAfterTest();
        $realtimequiz = $this->create_test_realtimequiz($this->course);
        // Test for questions from a different context.
        $context = \context_course::instance($this->course->id);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $realtimequiz, ['contextid' => $context->id]);
        $this->add_one_random_question($questiongenerator, $realtimequiz, ['contextid' => $context->id]);
        // Count the questions in course context.
        $this->assertEquals(7, $this->question_count($context->id));
        $newrealtimequiz = $this->duplicate_realtimequiz($this->course, $realtimequiz);
        $this->assertEquals(7, $this->question_count($context->id));
        $context = \context_module::instance($newrealtimequiz->id);
        // Count the questions in the realtimequiz context.
        $this->assertEquals(0, $this->question_count($context->id));
    }

    /**
     * Test realtimequiz duplicate for realtimequiz question bank.
     *
     * @covers ::duplicate_module
     */
    public function test_realtimequiz_duplicate_for_realtimequiz_question_bank_questions() {
        $this->resetAfterTest();
        $realtimequiz = $this->create_test_realtimequiz($this->course);
        // Test for questions from a different context.
        $context = \context_module::instance($realtimequiz->cmid);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $realtimequiz, ['contextid' => $context->id]);
        $this->add_one_random_question($questiongenerator, $realtimequiz, ['contextid' => $context->id]);
        // Count the questions in course context.
        $this->assertEquals(7, $this->question_count($context->id));
        $newrealtimequiz = $this->duplicate_realtimequiz($this->course, $realtimequiz);
        $this->assertEquals(7, $this->question_count($context->id));
        $context = \context_module::instance($newrealtimequiz->id);
        // Count the questions in the realtimequiz context.
        $this->assertEquals(7, $this->question_count($context->id));
    }

    /**
     * Test realtimequiz restore with attempts.
     *
     * @covers ::get_question_structure
     */
    public function test_realtimequiz_restore_with_attempts() {
        $this->resetAfterTest();

        // Create a realtimequiz.
        $realtimequiz = $this->create_test_realtimequiz($this->course);
        $realtimequizcontext = \context_module::instance($realtimequiz->cmid);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $realtimequiz, ['contextid' => $realtimequizcontext->id]);
        $this->add_one_random_question($questiongenerator, $realtimequiz, ['contextid' => $realtimequizcontext->id]);

        // Attempt it as a student, and check.
        /** @var \question_usage_by_activity $quba */
        [, $quba] = $this->attempt_realtimequiz($realtimequiz, $this->student);
        $this->assertEquals(3, $quba->question_count());
        $this->assertCount(1, realtimequiz_get_user_attempts($realtimequiz->id, $this->student->id));

        // Make the backup.
        $backupid = $this->backup_realtimequiz($realtimequiz, $this->user);

        // Delete the current course to make sure there is no data.
        delete_course($this->course, false);

        // Restore the backup.
        $newcourse = $this->getDataGenerator()->create_course();
        $this->restore_realtimequiz($backupid, $newcourse, $this->user);

        // Verify.
        $modules = get_fast_modinfo($newcourse->id)->get_instances_of('realtimequiz');
        $module = reset($modules);
        $this->assertCount(1, realtimequiz_get_user_attempts($module->instance, $this->student->id));
        $this->assertCount(3, \mod_realtimequiz\question\bank\qbank_helper::get_question_structure(
                $module->instance, $module->context));
    }

    /**
     * Test pre 4.0 realtimequiz restore for regular questions.
     *
     * @covers ::process_realtimequiz_question_legacy_instance
     */
    public function test_pre_4_realtimequiz_restore_for_regular_questions() {
        global $USER, $DB;
        $this->resetAfterTest();
        $backupid = 'abc';
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
            __DIR__ . "/fixtures/moodle_28_realtimequiz.mbz", $backuppath);

        // Do the restore to new course with default settings.
        $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
        $newcourseid = \restore_dbops::create_new_course('Test fullname', 'Test shortname', $categoryid);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
            \backup::TARGET_NEW_COURSE);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Get the information about the resulting course and check that it is set up correctly.
        $modinfo = get_fast_modinfo($newcourseid);
        $realtimequiz = array_values($modinfo->get_instances_of('realtimequiz'))[0];
        $realtimequizobj = \mod_realtimequiz\realtimequiz_settings::create($realtimequiz->instance);
        $structure = structure::create_for_realtimequiz($realtimequizobj);

        // Are the correct slots returned?
        $slots = $structure->get_slots();
        $this->assertCount(2, $slots);

        $realtimequizobj->preload_questions();
        $realtimequizobj->load_questions();
        $questions = $realtimequizobj->get_questions();
        $this->assertCount(2, $questions);

        // Count the questions in realtimequiz qbank.
        $this->assertEquals(2, $this->question_count($realtimequizobj->get_context()->id));
    }

    /**
     * Test pre 4.0 realtimequiz restore for random questions.
     *
     * @covers ::process_realtimequiz_question_legacy_instance
     */
    public function test_pre_4_realtimequiz_restore_for_random_questions() {
        global $USER, $DB;
        $this->resetAfterTest();

        $backupid = 'abc';
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
            __DIR__ . "/fixtures/random_by_tag_realtimequiz.mbz", $backuppath);

        // Do the restore to new course with default settings.
        $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
        $newcourseid = \restore_dbops::create_new_course('Test fullname', 'Test shortname', $categoryid);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
            \backup::TARGET_NEW_COURSE);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Get the information about the resulting course and check that it is set up correctly.
        $modinfo = get_fast_modinfo($newcourseid);
        $realtimequiz = array_values($modinfo->get_instances_of('realtimequiz'))[0];
        $realtimequizobj = \mod_realtimequiz\realtimequiz_settings::create($realtimequiz->instance);
        $structure = structure::create_for_realtimequiz($realtimequizobj);

        // Are the correct slots returned?
        $slots = $structure->get_slots();
        $this->assertCount(1, $slots);

        $realtimequizobj->preload_questions();
        $realtimequizobj->load_questions();
        $questions = $realtimequizobj->get_questions();
        $this->assertCount(1, $questions);

        // Count the questions for course question bank.
        $this->assertEquals(6, $this->question_count(\context_course::instance($newcourseid)->id));
        $this->assertEquals(6, $this->question_count(\context_course::instance($newcourseid)->id,
            "AND q.qtype <> 'random'"));

        // Count the questions in realtimequiz qbank.
        $this->assertEquals(0, $this->question_count($realtimequizobj->get_context()->id));
    }

    /**
     * Test pre 4.0 realtimequiz restore for random question tags.
     *
     * @covers ::process_realtimequiz_question_legacy_instance
     */
    public function test_pre_4_realtimequiz_restore_for_random_question_tags() {
        global $USER, $DB;
        $this->resetAfterTest();
        $randomtags = [
            '1' => ['first question', 'one', 'number one'],
            '2' => ['first question', 'one', 'number one'],
            '3' => ['one', 'number one', 'second question'],
        ];
        $backupid = 'abc';
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
            __DIR__ . "/fixtures/moodle_311_realtimequiz.mbz", $backuppath);

        // Do the restore to new course with default settings.
        $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
        $newcourseid = \restore_dbops::create_new_course('Test fullname', 'Test shortname', $categoryid);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
            \backup::TARGET_NEW_COURSE);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Get the information about the resulting course and check that it is set up correctly.
        $modinfo = get_fast_modinfo($newcourseid);
        $realtimequiz = array_values($modinfo->get_instances_of('realtimequiz'))[0];
        $realtimequizobj = \mod_realtimequiz\realtimequiz_settings::create($realtimequiz->instance);
        $structure = \mod_realtimequiz\structure::create_for_realtimequiz($realtimequizobj);

        // Count the questions in realtimequiz qbank.
        $context = \context_module::instance(get_coursemodule_from_instance("realtimequiz", $realtimequizobj->get_realtimequizid(), $newcourseid)->id);
        $this->assertEquals(2, $this->question_count($context->id));

        // Are the correct slots returned?
        $slots = $structure->get_slots();
        $this->assertCount(3, $slots);

        // Check if the tags match with the actual restored data.
        foreach ($slots as $slot) {
            $setreference = $DB->get_record('question_set_references',
                ['itemid' => $slot->id, 'component' => 'mod_realtimequiz', 'questionarea' => 'slot']);
            $filterconditions = json_decode($setreference->filtercondition);
            $tags = [];
            foreach ($filterconditions->tags as $tagstring) {
                $tag = explode(',', $tagstring);
                $tags[] = $tag[1];
            }
            $this->assertEquals([], array_diff($randomtags[$slot->slot], $tags));
        }

    }

    /**
     * Test pre 4.0 realtimequiz restore for random question used on multiple realtimequizzes.
     *
     * @covers ::process_realtimequiz_question_legacy_instance
     */
    public function test_pre_4_realtimequiz_restore_shared_random_question() {
        global $USER, $DB;
        $this->resetAfterTest();

        $backupid = 'abc';
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
                __DIR__ . "/fixtures/pre-40-shared-random-question.mbz", $backuppath);

        // Do the restore to new course with default settings.
        $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
        $newcourseid = \restore_dbops::create_new_course('Test fullname', 'Test shortname', $categoryid);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
                \backup::TARGET_NEW_COURSE);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Get the information about the resulting course and check that it is set up correctly.
        // Each realtimequiz should contain an instance of the random question.
        $modinfo = get_fast_modinfo($newcourseid);
        $realtimequizzes = $modinfo->get_instances_of('realtimequiz');
        $this->assertCount(2, $realtimequizzes);
        foreach ($realtimequizzes as $realtimequiz) {
            $realtimequizobj = \mod_realtimequiz\realtimequiz_settings::create($realtimequiz->instance);
            $structure = structure::create_for_realtimequiz($realtimequizobj);

            // Are the correct slots returned?
            $slots = $structure->get_slots();
            $this->assertCount(1, $slots);

            $realtimequizobj->preload_questions();
            $realtimequizobj->load_questions();
            $questions = $realtimequizobj->get_questions();
            $this->assertCount(1, $questions);
        }

        // Count the questions for course question bank.
        // We should have a single question, the random question should have been deleted after the restore.
        $this->assertEquals(1, $this->question_count(\context_course::instance($newcourseid)->id));
        $this->assertEquals(1, $this->question_count(\context_course::instance($newcourseid)->id,
                "AND q.qtype <> 'random'"));

        // Count the questions in realtimequiz qbank.
        $this->assertEquals(0, $this->question_count($realtimequizobj->get_context()->id));
    }

    /**
     * Ensure that question slots are correctly backed up and restored with all properties.
     *
     * @covers \backup_realtimequiz_activity_structure_step::define_structure()
     * @return void
     */
    public function test_backup_restore_question_slots(): void {
        $this->resetAfterTest(true);

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        $user1 = $this->getDataGenerator()->create_and_enrol($course1, 'editingteacher');
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id, 'editingteacher');

        // Make a realtimequiz.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $course1->id, 'questionsperpage' => 0, 'grade' => 100.0,
                'sumgrades' => 3]);

        // Create some fixed and random questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        $matchq = $questiongenerator->create_question('match', null, ['category' => $cat->id]);
        $randomcat = $questiongenerator->create_question_category();
        $questiongenerator->create_question('shortanswer', null, ['category' => $randomcat->id]);
        $questiongenerator->create_question('numerical', null, ['category' => $randomcat->id]);
        $questiongenerator->create_question('match', null, ['category' => $randomcat->id]);

        // Add them to the realtimequiz.
        realtimequiz_add_realtimequiz_question($saq->id, $realtimequiz, 1, 3);
        realtimequiz_add_realtimequiz_question($numq->id, $realtimequiz, 2, 2);
        realtimequiz_add_realtimequiz_question($matchq->id, $realtimequiz, 3, 1);
        realtimequiz_add_random_questions($realtimequiz, 3, $randomcat->id, 2, false);

        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $user1->id);
        $originalstructure = \mod_realtimequiz\structure::create_for_realtimequiz($realtimequizobj);

        // Set one slot to a non-default display number.
        $originalslots = $originalstructure->get_slots();
        $firstslot = reset($originalslots);
        $originalstructure->update_slot_display_number($firstslot->id, rand(5, 10));

        // Set one slot to requireprevious.
        $lastslot = end($originalslots);
        $originalstructure->update_question_dependency($lastslot->id, true);

        // Backup and restore the realtimequiz.
        $backupid = $this->backup_realtimequiz($realtimequiz, $user1);
        $this->restore_realtimequiz($backupid, $course2, $user1);

        // Ensure the restored slots match the original slots.
        $modinfo = get_fast_modinfo($course2);
        $realtimequizzes = $modinfo->get_instances_of('realtimequiz');
        $restoredrealtimequiz = reset($realtimequizzes);
        $restoredrealtimequizobj = realtimequiz_settings::create($restoredrealtimequiz->instance, $user1->id);
        $restoredstructure = \mod_realtimequiz\structure::create_for_realtimequiz($restoredrealtimequizobj);
        $restoredslots = array_values($restoredstructure->get_slots());
        $originalstructure = \mod_realtimequiz\structure::create_for_realtimequiz($realtimequizobj);
        $originalslots = array_values($originalstructure->get_slots());
        foreach ($restoredslots as $key => $restoredslot) {
            $originalslot = $originalslots[$key];
            $this->assertEquals($originalslot->realtimequizid, $realtimequiz->id);
            $this->assertEquals($restoredslot->realtimequizid, $restoredrealtimequiz->instance);
            $this->assertEquals($originalslot->slot, $restoredslot->slot);
            $this->assertEquals($originalslot->page, $restoredslot->page);
            $this->assertEquals($originalslot->displaynumber, $restoredslot->displaynumber);
            $this->assertEquals($originalslot->requireprevious, $restoredslot->requireprevious);
            $this->assertEquals($originalslot->maxmark, $restoredslot->maxmark);
        }
    }
}
