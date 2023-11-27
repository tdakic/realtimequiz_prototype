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
 * Quiz events tests.
 *
 * @package    mod_realtimequiz
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_realtimequiz\event;

use mod_realtimequiz\realtimequiz_attempt;
use mod_realtimequiz\realtimequiz_settings;
use context_module;

/**
 * Unit tests for realtimequiz events.
 *
 * @package    mod_realtimequiz
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends \advanced_testcase {

    /**
     * Setup a realtimequiz.
     *
     * @return realtimequiz_settings the generated realtimequiz.
     */
    protected function prepare_realtimequiz() {

        $this->resetAfterTest(true);

        // Create a course
        $course = $this->getDataGenerator()->create_course();

        // Make a realtimequiz.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $course->id, 'questionsperpage' => 0,
                'grade' => 100.0, 'sumgrades' => 2]);

        $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id, $course->id);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);

        // Add them to the realtimequiz.
        realtimequiz_add_realtimequiz_question($saq->id, $realtimequiz);
        realtimequiz_add_realtimequiz_question($numq->id, $realtimequiz);

        // Make a user to do the realtimequiz.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        return realtimequiz_settings::create($realtimequiz->id, $user1->id);
    }

    /**
     * Setup a realtimequiz attempt at the realtimequiz created by {@link prepare_realtimequiz()}.
     *
     * @param \mod_realtimequiz\realtimequiz_settings $realtimequizobj the generated realtimequiz.
     * @param bool $ispreview Make the attempt a preview attempt when true.
     * @return array with three elements, array($realtimequizobj, $quba, $attempt)
     */
    protected function prepare_realtimequiz_attempt($realtimequizobj, $ispreview = false) {
        // Start the attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow, $ispreview);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        return [$realtimequizobj, $quba, $attempt];
    }

    /**
     * Setup some convenience test data with a single attempt.
     *
     * @param bool $ispreview Make the attempt a preview attempt when true.
     * @return array with three elements, array($realtimequizobj, $quba, $attempt)
     */
    protected function prepare_realtimequiz_data($ispreview = false) {
        $realtimequizobj = $this->prepare_realtimequiz();
        return $this->prepare_realtimequiz_attempt($realtimequizobj, $ispreview);
    }

    public function test_attempt_submitted() {

        list($realtimequizobj, $quba, $attempt) = $this->prepare_realtimequiz_data();
        $attemptobj = realtimequiz_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();

        $timefinish = time();
        $attemptobj->process_finish($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(3, $events);
        $event = $events[2];
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_submitted', $event);
        $this->assertEquals('realtimequiz_attempts', $event->objecttable);
        $this->assertEquals($realtimequizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals(null, $event->other['submitterid']); // Should be the user, but PHP Unit complains...
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_becameoverdue() {

        list($realtimequizobj, $quba, $attempt) = $this->prepare_realtimequiz_data();
        $attemptobj = realtimequiz_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_going_overdue($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_becameoverdue', $event);
        $this->assertEquals('realtimequiz_attempts', $event->objecttable);
        $this->assertEquals($realtimequizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertNotEmpty($event->get_description());
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_abandoned() {

        list($realtimequizobj, $quba, $attempt) = $this->prepare_realtimequiz_data();
        $attemptobj = realtimequiz_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_abandon($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_abandoned', $event);
        $this->assertEquals('realtimequiz_attempts', $event->objecttable);
        $this->assertEquals($realtimequizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_started() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_started', $event);
        $this->assertEquals('realtimequiz_attempts', $event->objecttable);
        $this->assertEquals($attempt->id, $event->objectid);
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals($realtimequizobj->get_context(), $event->get_context());
        $this->assertEquals(\context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
    }

    /**
     * Test the attempt question restarted event.
     *
     * There is no external API for replacing a question, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_question_restarted() {
        list($realtimequizobj, $quba, $attempt) = $this->prepare_realtimequiz_data();

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $realtimequizobj->get_courseid(),
            'context' => \context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'page' => 2,
                'slot' => 3,
                'newquestionid' => 2
            ]
        ];
        $event = \mod_realtimequiz\event\attempt_question_restarted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_question_restarted', $event);
        $this->assertEquals(\context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt updated event.
     *
     * There is no external API for updating an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_updated() {
        list($realtimequizobj, $quba, $attempt) = $this->prepare_realtimequiz_data();

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $realtimequizobj->get_courseid(),
            'context' => \context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'page' => 0
            ]
        ];
        $event = \mod_realtimequiz\event\attempt_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_updated', $event);
        $this->assertEquals(\context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt auto-saved event.
     *
     * There is no external API for auto-saving an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_autosaved() {
        list($realtimequizobj, $quba, $attempt) = $this->prepare_realtimequiz_data();

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $realtimequizobj->get_courseid(),
            'context' => \context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'page' => 0
            ]
        ];

        $event = \mod_realtimequiz\event\attempt_autosaved::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_autosaved', $event);
        $this->assertEquals(\context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the edit page viewed event.
     *
     * There is no external API for updating a realtimequiz, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_edit_page_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        $params = [
            'courseid' => $course->id,
            'context' => \context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id
            ]
        ];
        $event = \mod_realtimequiz\event\edit_page_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\edit_page_viewed', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt deleted event.
     */
    public function test_attempt_deleted() {
        list($realtimequizobj, $quba, $attempt) = $this->prepare_realtimequiz_data();

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        realtimequiz_delete_attempt($attempt, $realtimequizobj->get_realtimequiz());
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_deleted', $event);
        $this->assertEquals(\context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test that preview attempt deletions are not logged.
     */
    public function test_preview_attempt_deleted() {
        // Create realtimequiz with preview attempt.
        list($realtimequizobj, $quba, $previewattempt) = $this->prepare_realtimequiz_data(true);

        // Delete a preview attempt, capturing events.
        $sink = $this->redirectEvents();
        realtimequiz_delete_attempt($previewattempt, $realtimequizobj->get_realtimequiz());

        // Verify that no events were generated.
        $this->assertEmpty($sink->get_events());
    }

    /**
     * Test the report viewed event.
     *
     * There is no external API for viewing reports, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_report_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        $params = [
            'context' => $context = \context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id,
                'reportname' => 'overview'
            ]
        ];
        $event = \mod_realtimequiz\event\report_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\report_viewed', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt reviewed event.
     *
     * There is no external API for reviewing attempts, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_reviewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id
            ]
        ];
        $event = \mod_realtimequiz\event\attempt_reviewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_reviewed', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt summary viewed event.
     *
     * There is no external API for viewing the attempt summary, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_summary_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id
            ]
        ];
        $event = \mod_realtimequiz\event\attempt_summary_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_summary_viewed', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override created event.
     *
     * There is no external API for creating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => \context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id
            ]
        ];
        $event = \mod_realtimequiz\event\user_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\user_override_created', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override created event.
     *
     * There is no external API for creating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        $params = [
            'objectid' => 1,
            'context' => \context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id,
                'groupid' => 2
            ]
        ];
        $event = \mod_realtimequiz\event\group_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\group_override_created', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override updated event.
     *
     * There is no external API for updating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => \context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id
            ]
        ];
        $event = \mod_realtimequiz\event\user_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\user_override_updated', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override updated event.
     *
     * There is no external API for updating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        $params = [
            'objectid' => 1,
            'context' => \context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id,
                'groupid' => 2
            ]
        ];
        $event = \mod_realtimequiz\event\group_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\group_override_updated', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override deleted event.
     */
    public function test_user_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        // Create an override.
        $override = new \stdClass();
        $override->realtimequiz = $realtimequiz->id;
        $override->userid = 2;
        $override->id = $DB->insert_record('realtimequiz_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        realtimequiz_delete_override($realtimequiz, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\user_override_deleted', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override deleted event.
     */
    public function test_group_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        // Create an override.
        $override = new \stdClass();
        $override->realtimequiz = $realtimequiz->id;
        $override->groupid = 2;
        $override->id = $DB->insert_record('realtimequiz_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        realtimequiz_delete_override($realtimequiz, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\group_override_deleted', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt viewed event.
     *
     * There is no external API for continuing an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id,
                'page' => 0
            ]
        ];
        $event = \mod_realtimequiz\event\attempt_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_viewed', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt previewed event.
     */
    public function test_attempt_preview_started() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow, true);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_preview_started', $event);
        $this->assertEquals(\context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the question manually graded event.
     *
     * There is no external API for manually grading a question, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_question_manually_graded() {
        list($realtimequizobj, $quba, $attempt) = $this->prepare_realtimequiz_data();

        $params = [
            'objectid' => 1,
            'courseid' => $realtimequizobj->get_courseid(),
            'context' => \context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'attemptid' => 2,
                'slot' => 3
            ]
        ];
        $event = \mod_realtimequiz\event\question_manually_graded::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\question_manually_graded', $event);
        $this->assertEquals(\context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt regraded event.
     *
     * There is no external API for regrading attempts, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_regraded() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id]);

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($realtimequiz->cmid),
            'other' => [
                'realtimequizid' => $realtimequiz->id
            ]
        ];
        $event = \mod_realtimequiz\event\attempt_regraded::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_regraded', $event);
        $this->assertEquals(\context_module::instance($realtimequiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt notify manual graded event.
     * There is no external API for notification email when manual grading of user's attempt is completed,
     * so the unit test will simply create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_manual_grading_completed() {
        $this->resetAfterTest();
        list($realtimequizobj, $quba, $attempt) = $this->prepare_realtimequiz_data();
        $attemptobj = realtimequiz_attempt::create($attempt->id);

        $params = [
            'objectid' => $attemptobj->get_attemptid(),
            'relateduserid' => $attemptobj->get_userid(),
            'courseid' => $attemptobj->get_course()->id,
            'context' => \context_module::instance($attemptobj->get_cmid()),
            'other' => [
                'realtimequizid' => $attemptobj->get_realtimequizid()
            ]
        ];
        $event = \mod_realtimequiz\event\attempt_manual_grading_completed::create($params);

        // Catch the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_manual_grading_completed', $event);
        $this->assertEquals('realtimequiz_attempts', $event->objecttable);
        $this->assertEquals($realtimequizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertNotEmpty($event->get_description());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the page break created event.
     *
     * There is no external API for creating page break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_page_break_created() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'slotnumber' => 3,
            ]
        ];
        $event = \mod_realtimequiz\event\page_break_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\page_break_created', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the page break deleted event.
     *
     * There is no external API for deleting page break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_page_deleted_created() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'slotnumber' => 3,
            ]
        ];
        $event = \mod_realtimequiz\event\page_break_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\page_break_deleted', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the realtimequiz grade updated event.
     *
     * There is no external API for updating realtimequiz grade, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_realtimequiz_grade_updated() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => $realtimequizobj->get_realtimequizid(),
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'oldgrade' => 1,
                'newgrade' => 3,
            ]
        ];
        $event = \mod_realtimequiz\event\realtimequiz_grade_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\realtimequiz_grade_updated', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the realtimequiz re-paginated event.
     *
     * There is no external API for re-paginating realtimequiz, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_realtimequiz_repaginated() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => $realtimequizobj->get_realtimequizid(),
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'slotsperpage' => 3,
            ]
        ];
        $event = \mod_realtimequiz\event\realtimequiz_repaginated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\realtimequiz_repaginated', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section break created event.
     *
     * There is no external API for creating section break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_break_created() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'firstslotid' => 1,
                'firstslotnumber' => 2,
                'title' => 'New title'
            ]
        ];
        $event = \mod_realtimequiz\event\section_break_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\section_break_created', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertStringContainsString($params['other']['title'], $event->get_description());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section break deleted event.
     *
     * There is no external API for deleting section break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_break_deleted() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'firstslotid' => 1,
                'firstslotnumber' => 2
            ]
        ];
        $event = \mod_realtimequiz\event\section_break_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\section_break_deleted', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section shuffle updated event.
     *
     * There is no external API for updating section shuffle, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_shuffle_updated() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'firstslotnumber' => 2,
                'shuffle' => true
            ]
        ];
        $event = \mod_realtimequiz\event\section_shuffle_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\section_shuffle_updated', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section title updated event.
     *
     * There is no external API for updating section title, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_title_updated() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'firstslotid' => 1,
                'firstslotnumber' => 2,
                'newtitle' => 'New title'
            ]
        ];
        $event = \mod_realtimequiz\event\section_title_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\section_title_updated', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertStringContainsString($params['other']['newtitle'], $event->get_description());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot created event.
     *
     * There is no external API for creating slot, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_created() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'slotnumber' => 1,
                'page' => 1
            ]
        ];
        $event = \mod_realtimequiz\event\slot_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\slot_created', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot deleted event.
     *
     * There is no external API for deleting slot, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_deleted() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'slotnumber' => 1,
            ]
        ];
        $event = \mod_realtimequiz\event\slot_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\slot_deleted', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot mark updated event.
     *
     * There is no external API for updating slot mark, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_mark_updated() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'previousmaxmark' => 1,
                'newmaxmark' => 2,
            ]
        ];
        $event = \mod_realtimequiz\event\slot_mark_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\slot_mark_updated', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot moved event.
     *
     * There is no external API for moving slot, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_moved() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'previousslotnumber' => 1,
                'afterslotnumber' => 2,
                'page' => 1
            ]
        ];
        $event = \mod_realtimequiz\event\slot_moved::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\slot_moved', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot require previous updated event.
     *
     * There is no external API for updating slot require previous option, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_requireprevious_updated() {
        $realtimequizobj = $this->prepare_realtimequiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($realtimequizobj->get_cmid()),
            'other' => [
                'realtimequizid' => $realtimequizobj->get_realtimequizid(),
                'requireprevious' => true
            ]
        ];
        $event = \mod_realtimequiz\event\slot_requireprevious_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_realtimequiz\event\slot_requireprevious_updated', $event);
        $this->assertEquals(context_module::instance($realtimequizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }
}
