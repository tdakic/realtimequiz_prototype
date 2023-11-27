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
use mod_realtimequiz\realtimequiz_attempt;
use mod_realtimequiz\realtimequiz_settings;

/**
 * Helper trait for realtimequiz question unit tests.
 *
 * This trait helps to execute different tests for realtimequiz, for example if it needs to create a realtimequiz, add question
 * to the question, add random quetion to the realtimequiz, do a backup or restore.
 *
 * @package    mod_realtimequiz
 * @category   test
 * @copyright  2021 Catalyst IT Australia Pty Ltd
 * @author     Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait realtimequiz_question_helper_test_trait {

    /** @var \stdClass $course Test course to contain realtimequiz. */
    protected $course;

    /** @var \stdClass $realtimequiz A test realtimequiz. */
    protected $realtimequiz;

    /** @var \stdClass $user A test logged-in user. */
    protected $user;

    /**
     * Create a test realtimequiz for the specified course.
     *
     * @param \stdClass $course
     * @return  \stdClass
     */
    protected function create_test_realtimequiz(\stdClass $course): \stdClass {

        /** @var mod_realtimequiz_generator $realtimequizgenerator */
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        return $realtimequizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 100.0,
            'sumgrades' => 2,
        ]);
    }

    /**
     * Helper method to add regular questions in realtimequiz.
     *
     * @param component_generator_base $questiongenerator
     * @param \stdClass $realtimequiz
     * @param array $override
     */
    protected function add_two_regular_questions($questiongenerator, \stdClass $realtimequiz, $override = null): void {
        // Create a couple of questions.
        $cat = $questiongenerator->create_question_category($override);

        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        // Create another version.
        $questiongenerator->update_question($saq);
        realtimequiz_add_realtimequiz_question($saq->id, $realtimequiz);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        // Create two version.
        $questiongenerator->update_question($numq);
        $questiongenerator->update_question($numq);
        realtimequiz_add_realtimequiz_question($numq->id, $realtimequiz);
    }

    /**
     * Helper method to add random question to realtimequiz.
     *
     * @param component_generator_base $questiongenerator
     * @param \stdClass $realtimequiz
     * @param array $override
     */
    protected function add_one_random_question($questiongenerator, \stdClass $realtimequiz, $override = []): void {
        // Create a random question.
        $cat = $questiongenerator->create_question_category($override);
        $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        $questiongenerator->create_question('essay', null, ['category' => $cat->id]);
        realtimequiz_add_random_questions($realtimequiz, 0, $cat->id, 1, false);
    }

    /**
     * Attempt questions for a realtimequiz and user.
     *
     * @param \stdClass $realtimequiz Quiz to attempt.
     * @param \stdClass $user A user to attempt the realtimequiz.
     * @param int $attemptnumber
     * @return array
     */
    protected function attempt_realtimequiz(\stdClass $realtimequiz, \stdClass $user, $attemptnumber = 1): array {
        $this->setUser($user);

        $starttime = time();
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $user->id);

        $quba = question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        // Start the attempt.
        $attempt = realtimequiz_create_attempt($realtimequizobj, $attemptnumber, null, $starttime, false, $user->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, $attemptnumber, $starttime);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_finish($starttime, false);

        $this->setUser();
        return [$realtimequizobj, $quba, $attemptobj];
    }

    /**
     * A helper method to backup test realtimequiz.
     *
     * @param \stdClass $realtimequiz Quiz to attempt.
     * @param \stdClass $user A user to attempt the realtimequiz.
     * @return string A backup ID ready to be restored.
     */
    protected function backup_realtimequiz(\stdClass $realtimequiz, \stdClass $user): string {
        global $CFG;

        // Get the necessary files to perform backup and restore.
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $backupid = 'test-question-backup-restore';

        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $realtimequiz->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $user->id);
        $bc->execute_plan();

        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/' . $backupid;
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();

        return $backupid;
    }

    /**
     * A helper method to restore provided backup.
     *
     * @param string $backupid Backup ID to restore.
     * @param stdClass $course
     * @param stdClass $user
     */
    protected function restore_realtimequiz(string $backupid, stdClass $course, stdClass $user): void {
        $rc = new restore_controller($backupid, $course->id,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $user->id, backup::TARGET_CURRENT_ADDING);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * A helper method to emulate duplication of the realtimequiz.
     *
     * @param stdClass $course
     * @param stdClass $realtimequiz
     * @return \cm_info|null
     */
    protected function duplicate_realtimequiz($course, $realtimequiz): ?\cm_info {
        return duplicate_module($course, get_fast_modinfo($course)->get_cm($realtimequiz->cmid));
    }
}
