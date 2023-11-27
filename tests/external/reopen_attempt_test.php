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

namespace mod_realtimequiz\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../webservice/tests/helpers.php');

use coding_exception;
use core_question_generator;
use externallib_advanced_testcase;
use mod_realtimequiz\realtimequiz_attempt;
use mod_realtimequiz\realtimequiz_settings;
use required_capability_exception;
use stdClass;

/**
 * Test for the reopen_attempt and get_reopen_attempt_confirmation services.
 *
 * @package   mod_realtimequiz
 * @category  external
 * @copyright 2023 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_realtimequiz\external\reopen_attempt
 * @covers \mod_realtimequiz\external\get_reopen_attempt_confirmation
 */
class reopen_attempt_test extends externallib_advanced_testcase {
    /** @var stdClass|null if we make a realtimequiz attempt, we store the student object here. */
    protected $student;

    public function test_reopen_attempt_service_works() {
        [$attemptid] = $this->create_attempt_at_realtimequiz_with_one_shortanswer_question();

        reopen_attempt::execute($attemptid);

        $attemptobj = realtimequiz_attempt::create($attemptid);
        $this->assertEquals(realtimequiz_attempt::IN_PROGRESS, $attemptobj->get_state());
    }

    public function test_reopen_attempt_service_checks_permissions() {
        [$attemptid] = $this->create_attempt_at_realtimequiz_with_one_shortanswer_question();

        $unprivilegeduser = $this->getDataGenerator()->create_user();
        $this->setUser($unprivilegeduser);

        $this->expectException(required_capability_exception::class);
        reopen_attempt::execute($attemptid);
    }

    public function test_reopen_attempt_service_checks_attempt_state() {
        [$attemptid] = $this->create_attempt_at_realtimequiz_with_one_shortanswer_question(realtimequiz_attempt::IN_PROGRESS);

        $this->expectExceptionMessage("Attempt $attemptid is in the wrong state (In progress) to be reopened.");
        reopen_attempt::execute($attemptid);
    }

    public function test_get_reopen_attempt_confirmation_staying_open() {
        global $DB;
        [$attemptid, $realtimequizid] = $this->create_attempt_at_realtimequiz_with_one_shortanswer_question();
        $DB->set_field('realtimequiz', 'timeclose', 0, ['id' => $realtimequizid]);

        $message = get_reopen_attempt_confirmation::execute($attemptid);

        $this->assertEquals('<p>This will reopen attempt 1 by ' . fullname($this->student) .
                '.</p><p>The attempt will remain open and can be continued.</p>',
                $message);
    }

    public function test_get_reopen_attempt_confirmation_staying_open_until() {
        global $DB;
        [$attemptid, $realtimequizid] = $this->create_attempt_at_realtimequiz_with_one_shortanswer_question();
        $timeclose = time() + HOURSECS;
        $DB->set_field('realtimequiz', 'timeclose', $timeclose, ['id' => $realtimequizid]);

        $message = get_reopen_attempt_confirmation::execute($attemptid);

        $this->assertEquals('<p>This will reopen attempt 1 by ' . fullname($this->student) .
                '.</p><p>The attempt will remain open and can be continued until the realtimequiz closes on ' .
                userdate($timeclose) . '.</p>',
                $message);
    }

    public function test_get_reopen_attempt_confirmation_submitting() {
        global $DB;
        [$attemptid, $realtimequizid] = $this->create_attempt_at_realtimequiz_with_one_shortanswer_question();
        $timeclose = time() - HOURSECS;
        $DB->set_field('realtimequiz', 'timeclose', $timeclose, ['id' => $realtimequizid]);

        $message = get_reopen_attempt_confirmation::execute($attemptid);

        $this->assertEquals('<p>This will reopen attempt 1 by ' . fullname($this->student) .
                '.</p><p>The attempt will be immediately submitted for grading.</p>',
                $message);
    }

    public function test_get_reopen_attempt_confirmation_service_checks_permissions() {
        [$attemptid] = $this->create_attempt_at_realtimequiz_with_one_shortanswer_question();

        $unprivilegeduser = $this->getDataGenerator()->create_user();
        $this->setUser($unprivilegeduser);

        $this->expectException(required_capability_exception::class);
        get_reopen_attempt_confirmation::execute($attemptid);
    }

    public function test_get_reopen_attempt_confirmation_service_checks_attempt_state() {
        [$attemptid] = $this->create_attempt_at_realtimequiz_with_one_shortanswer_question(realtimequiz_attempt::IN_PROGRESS);

        $this->expectExceptionMessage("Attempt $attemptid is in the wrong state (In progress) to be reopened.");
        get_reopen_attempt_confirmation::execute($attemptid);
    }

    /**
     * Create a realtimequiz of one shortanswer question and an attempt in a given state.
     *
     * @param string $attemptstate the desired attempt state. realtimequiz_attempt::ABANDONED or ::IN_PROGRESS.
     * @return array with two elements, the attempt id and the realtimequiz id.
     */
    protected function create_attempt_at_realtimequiz_with_one_shortanswer_question(
        string $attemptstate = realtimequiz_attempt::ABANDONED
    ): array {
        global $SITE;
        $this->resetAfterTest();

        // Make a realtimequiz.
        $timeclose = time() + HOURSECS;
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        $realtimequiz = $realtimequizgenerator->create_instance([
            'course' => $SITE->id,
            'timeclose' => $timeclose,
            'overduehandling' => 'autoabandon'
        ]);

        // Create a question.
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);

        // Add them to the realtimequiz.
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id);
        realtimequiz_add_realtimequiz_question($saq->id, $realtimequiz, 0, 1);
        $realtimequizobj->get_grade_calculator()->recompute_realtimequiz_sumgrades();

        // Make a user to do the realtimequiz.
        $this->student = $this->getDataGenerator()->create_user();
        $this->setUser($this->student);
        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $this->student->id);

        // Start the attempt.
        $attempt = realtimequiz_prepare_and_start_new_attempt($realtimequizobj, 1, null);
        $attemptobj = realtimequiz_attempt::create($attempt->id);

        if ($attemptstate === realtimequiz_attempt::ABANDONED) {
            // Attempt goes overdue (e.g. if cron ran).
            $attemptobj->process_abandon($timeclose + 2 * get_config('realtimequiz', 'graceperiodmin'), false);
        } else if ($attemptstate !== realtimequiz_attempt::IN_PROGRESS) {
            throw new coding_exception('State ' . $attemptstate . ' not currently supported.');
        }

        // Set current user to admin before we return.
        $this->setAdminUser();

        return [$attemptobj->get_attemptid(), $attemptobj->get_realtimequizid()];
    }
}
