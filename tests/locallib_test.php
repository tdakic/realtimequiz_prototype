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
 * @copyright  2008 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_realtimequiz;

use mod_realtimequiz\output\renderer;
use mod_realtimequiz\question\display_options;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');


/**
 * Unit tests for (some of) mod/realtimequiz/locallib.php.
 *
 * @copyright  2008 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locallib_test extends \advanced_testcase {

    public function test_realtimequiz_rescale_grade() {
        $realtimequiz = new \stdClass();
        $realtimequiz->decimalpoints = 2;
        $realtimequiz->questiondecimalpoints = 3;
        $realtimequiz->grade = 10;
        $realtimequiz->sumgrades = 10;
        $this->assertEquals(realtimequiz_rescale_grade(0.12345678, $realtimequiz, false), 0.12345678);
        $this->assertEquals(realtimequiz_rescale_grade(0.12345678, $realtimequiz, true), format_float(0.12, 2));
        $this->assertEquals(realtimequiz_rescale_grade(0.12345678, $realtimequiz, 'question'),
            format_float(0.123, 3));
        $realtimequiz->sumgrades = 5;
        $this->assertEquals(realtimequiz_rescale_grade(0.12345678, $realtimequiz, false), 0.24691356);
        $this->assertEquals(realtimequiz_rescale_grade(0.12345678, $realtimequiz, true), format_float(0.25, 2));
        $this->assertEquals(realtimequiz_rescale_grade(0.12345678, $realtimequiz, 'question'),
            format_float(0.247, 3));
    }

    public function realtimequiz_attempt_state_data_provider() {
        return [
            [realtimequiz_attempt::IN_PROGRESS, null, null, display_options::DURING],
            [realtimequiz_attempt::FINISHED, -90, null, display_options::IMMEDIATELY_AFTER],
            [realtimequiz_attempt::FINISHED, -7200, null, display_options::LATER_WHILE_OPEN],
            [realtimequiz_attempt::FINISHED, -7200, 3600, display_options::LATER_WHILE_OPEN],
            [realtimequiz_attempt::FINISHED, -30, 30, display_options::IMMEDIATELY_AFTER],
            [realtimequiz_attempt::FINISHED, -90, -30, display_options::AFTER_CLOSE],
            [realtimequiz_attempt::FINISHED, -7200, -3600, display_options::AFTER_CLOSE],
            [realtimequiz_attempt::FINISHED, -90, -3600, display_options::AFTER_CLOSE],
            [realtimequiz_attempt::ABANDONED, -10000000, null, display_options::LATER_WHILE_OPEN],
            [realtimequiz_attempt::ABANDONED, -7200, 3600, display_options::LATER_WHILE_OPEN],
            [realtimequiz_attempt::ABANDONED, -7200, -3600, display_options::AFTER_CLOSE],
        ];
    }

    /**
     * @dataProvider realtimequiz_attempt_state_data_provider
     *
     * @param string $attemptstate as in the realtimequiz_attempts.state DB column.
     * @param int|null $relativetimefinish time relative to now when the attempt finished, or null for 0.
     * @param int|null $relativetimeclose time relative to now when the realtimequiz closes, or null for 0.
     * @param int $expectedstate expected result. One of the display_options constants.
     * @covers ::realtimequiz_attempt_state
     */
    public function test_realtimequiz_attempt_state(string $attemptstate,
            ?int $relativetimefinish, ?int $relativetimeclose, int $expectedstate) {

        $attempt = new \stdClass();
        $attempt->state = $attemptstate;
        if ($relativetimefinish === null) {
            $attempt->timefinish = 0;
        } else {
            $attempt->timefinish = time() + $relativetimefinish;
        }

        $realtimequiz = new \stdClass();
        if ($relativetimeclose === null) {
            $realtimequiz->timeclose = 0;
        } else {
            $realtimequiz->timeclose = time() + $relativetimeclose;
        }

        $this->assertEquals($expectedstate, realtimequiz_attempt_state($realtimequiz, $attempt));
    }

    /**
     * @covers ::realtimequiz_question_tostring
     */
    public function test_realtimequiz_question_tostring() {
        $question = new \stdClass();
        $question->qtype = 'multichoice';
        $question->name = 'The question name';
        $question->questiontext = '<p>What sort of <b>inequality</b> is x &lt; y<img alt="?" src="..."></p>';
        $question->questiontextformat = FORMAT_HTML;

        $summary = realtimequiz_question_tostring($question);
        $this->assertEquals('<span class="questionname">The question name</span> ' .
                '<span class="questiontext">What sort of INEQUALITY is x &lt; y[?]' . "\n" . '</span>', $summary);
    }

    /**
     * @covers ::realtimequiz_question_tostring
     */
    public function test_realtimequiz_question_tostring_does_not_filter() {
        $question = new \stdClass();
        $question->qtype = 'multichoice';
        $question->name = 'The question name';
        $question->questiontext = '<p>No emoticons here :-)</p>';
        $question->questiontextformat = FORMAT_HTML;

        $summary = realtimequiz_question_tostring($question);
        $this->assertEquals('<span class="questionname">The question name</span> ' .
                '<span class="questiontext">No emoticons here :-)' . "\n</span>", $summary);
    }

    /**
     * Test realtimequiz_view
     * @return void
     */
    public function test_realtimequiz_view() {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $course->id],
                                                            ['completion' => 2, 'completionview' => 1]);
        $context = \context_module::instance($realtimequiz->cmid);
        $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        realtimequiz_view($realtimequiz, $course, $cm, $context);

        $events = $sink->get_events();
        // 2 additional events thanks to completion.
        $this->assertCount(3, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_realtimequiz\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/realtimequiz/view.php', ['id' => $cm->id]);
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
        // Check completion status.
        $completion = new \completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);
    }

    /**
     * Return false when there are not overrides for this realtimequiz instance.
     */
    public function test_realtimequiz_is_overriden_calendar_event_no_override() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $realtimequizgenerator = $generator->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $course->id]);

        $event = new \calendar_event((object)[
            'modulename' => 'realtimequiz',
            'instance' => $realtimequiz->id,
            'userid' => $user->id
        ]);

        $this->assertFalse(realtimequiz_is_overriden_calendar_event($event));
    }

    /**
     * Return false if the given event isn't an realtimequiz module event.
     */
    public function test_realtimequiz_is_overriden_calendar_event_no_module_event() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $realtimequizgenerator = $generator->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $course->id]);

        $event = new \calendar_event((object)[
            'userid' => $user->id
        ]);

        $this->assertFalse(realtimequiz_is_overriden_calendar_event($event));
    }

    /**
     * Return false if there is overrides for this use but they belong to another realtimequiz
     * instance.
     */
    public function test_realtimequiz_is_overriden_calendar_event_different_realtimequiz_instance() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $realtimequizgenerator = $generator->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $course->id]);
        $realtimequiz2 = $realtimequizgenerator->create_instance(['course' => $course->id]);

        $event = new \calendar_event((object) [
            'modulename' => 'realtimequiz',
            'instance' => $realtimequiz->id,
            'userid' => $user->id
        ]);

        $record = (object) [
            'realtimequiz' => $realtimequiz2->id,
            'userid' => $user->id
        ];

        $DB->insert_record('realtimequiz_overrides', $record);

        $this->assertFalse(realtimequiz_is_overriden_calendar_event($event));
    }

    /**
     * Return true if there is a user override for this event and realtimequiz instance.
     */
    public function test_realtimequiz_is_overriden_calendar_event_user_override() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $realtimequizgenerator = $generator->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $course->id]);

        $event = new \calendar_event((object) [
            'modulename' => 'realtimequiz',
            'instance' => $realtimequiz->id,
            'userid' => $user->id
        ]);

        $record = (object) [
            'realtimequiz' => $realtimequiz->id,
            'userid' => $user->id
        ];

        $DB->insert_record('realtimequiz_overrides', $record);

        $this->assertTrue(realtimequiz_is_overriden_calendar_event($event));
    }

    /**
     * Return true if there is a group override for the event and realtimequiz instance.
     */
    public function test_realtimequiz_is_overriden_calendar_event_group_override() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $realtimequizgenerator = $generator->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $course->id]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $realtimequiz->course]);
        $groupid = $group->id;
        $userid = $user->id;

        $event = new \calendar_event((object) [
            'modulename' => 'realtimequiz',
            'instance' => $realtimequiz->id,
            'groupid' => $groupid
        ]);

        $record = (object) [
            'realtimequiz' => $realtimequiz->id,
            'groupid' => $groupid
        ];

        $DB->insert_record('realtimequiz_overrides', $record);

        $this->assertTrue(realtimequiz_is_overriden_calendar_event($event));
    }

    /**
     * Test test_realtimequiz_get_user_timeclose().
     */
    public function test_realtimequiz_get_user_timeclose() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $basetimestamp = time(); // The timestamp we will base the enddates on.

        // Create generator, course and realtimequizzes.
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $student3 = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        // Both realtimequizzes close in two hours.
        $realtimequiz1 = $realtimequizgenerator->create_instance(['course' => $course->id, 'timeclose' => $basetimestamp + 7200]);
        $realtimequiz2 = $realtimequizgenerator->create_instance(['course' => $course->id, 'timeclose' => $basetimestamp + 7200]);
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $student1id = $student1->id;
        $student2id = $student2->id;
        $student3id = $student3->id;
        $teacherid = $teacher->id;

        // Users enrolments.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($student1id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student2id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student3id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacherid, $course->id, $teacherrole->id, 'manual');

        // Create groups.
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group1id = $group1->id;
        $group2id = $group2->id;
        $this->getDataGenerator()->create_group_member(['userid' => $student1id, 'groupid' => $group1id]);
        $this->getDataGenerator()->create_group_member(['userid' => $student2id, 'groupid' => $group2id]);

        // Group 1 gets an group override for realtimequiz 1 to close in three hours.
        $record1 = (object) [
            'realtimequiz' => $realtimequiz1->id,
            'groupid' => $group1id,
            'timeclose' => $basetimestamp + 10800 // In three hours.
        ];
        $DB->insert_record('realtimequiz_overrides', $record1);

        // Let's test realtimequiz 1 closes in three hours for user student 1 since member of group 1.
        // Quiz 2 closes in two hours.
        $this->setUser($student1id);
        $params = new \stdClass();

        $comparearray = [];
        $object = new \stdClass();
        $object->id = $realtimequiz1->id;
        $object->usertimeclose = $basetimestamp + 10800; // The overriden timeclose for realtimequiz 1.

        $comparearray[$realtimequiz1->id] = $object;

        $object = new \stdClass();
        $object->id = $realtimequiz2->id;
        $object->usertimeclose = $basetimestamp + 7200; // The unchanged timeclose for realtimequiz 2.

        $comparearray[$realtimequiz2->id] = $object;

        $this->assertEquals($comparearray, realtimequiz_get_user_timeclose($course->id));

        // Let's test realtimequiz 1 closes in two hours (the original value) for user student 3 since member of no group.
        $this->setUser($student3id);
        $params = new \stdClass();

        $comparearray = [];
        $object = new \stdClass();
        $object->id = $realtimequiz1->id;
        $object->usertimeclose = $basetimestamp + 7200; // The original timeclose for realtimequiz 1.

        $comparearray[$realtimequiz1->id] = $object;

        $object = new \stdClass();
        $object->id = $realtimequiz2->id;
        $object->usertimeclose = $basetimestamp + 7200; // The original timeclose for realtimequiz 2.

        $comparearray[$realtimequiz2->id] = $object;

        $this->assertEquals($comparearray, realtimequiz_get_user_timeclose($course->id));

        // User 2 gets an user override for realtimequiz 1 to close in four hours.
        $record2 = (object) [
            'realtimequiz' => $realtimequiz1->id,
            'userid' => $student2id,
            'timeclose' => $basetimestamp + 14400 // In four hours.
        ];
        $DB->insert_record('realtimequiz_overrides', $record2);

        // Let's test realtimequiz 1 closes in four hours for user student 2 since personally overriden.
        // Quiz 2 closes in two hours.
        $this->setUser($student2id);

        $comparearray = [];
        $object = new \stdClass();
        $object->id = $realtimequiz1->id;
        $object->usertimeclose = $basetimestamp + 14400; // The overriden timeclose for realtimequiz 1.

        $comparearray[$realtimequiz1->id] = $object;

        $object = new \stdClass();
        $object->id = $realtimequiz2->id;
        $object->usertimeclose = $basetimestamp + 7200; // The unchanged timeclose for realtimequiz 2.

        $comparearray[$realtimequiz2->id] = $object;

        $this->assertEquals($comparearray, realtimequiz_get_user_timeclose($course->id));

        // Let's test a teacher sees the original times.
        // Quiz 1 and realtimequiz 2 close in two hours.
        $this->setUser($teacherid);

        $comparearray = [];
        $object = new \stdClass();
        $object->id = $realtimequiz1->id;
        $object->usertimeclose = $basetimestamp + 7200; // The unchanged timeclose for realtimequiz 1.

        $comparearray[$realtimequiz1->id] = $object;

        $object = new \stdClass();
        $object->id = $realtimequiz2->id;
        $object->usertimeclose = $basetimestamp + 7200; // The unchanged timeclose for realtimequiz 2.

        $comparearray[$realtimequiz2->id] = $object;

        $this->assertEquals($comparearray, realtimequiz_get_user_timeclose($course->id));
    }

    /**
     * This function creates a realtimequiz with some standard (non-random) and some random questions.
     * The standard questions are created first and then random questions follow them.
     * So in a realtimequiz with 3 standard question and 2 random question, the first random question is at slot 4.
     *
     * @param int $qnum Number of standard questions that should be created in the realtimequiz.
     * @param int $randomqnum Number of random questions that should be created in the realtimequiz.
     * @param array $questiontags Tags to be used for random questions.
     *      This is an array in the following format:
     *      [
     *          0 => ['foo', 'bar'],
     *          1 => ['baz', 'qux']
     *      ]
     * @param string[] $unusedtags Some additional tags to be created.
     * @return array An array of 2 elements: $realtimequiz and $tagobjects.
     *      $tagobjects is an associative array of all created tag objects with its key being tag names.
     */
    private function setup_realtimequiz_and_tags($qnum, $randomqnum, $questiontags = [], $unusedtags = []) {
        global $SITE;

        $tagobjects = [];

        // Get all the tags that need to be created.
        $alltags = [];
        foreach ($questiontags as $questiontag) {
            $alltags = array_merge($alltags, $questiontag);
        }
        $alltags = array_merge($alltags, $unusedtags);
        $alltags = array_unique($alltags);

        // Create tags.
        foreach ($alltags as $tagname) {
            $tagrecord = [
                'isstandard' => 1,
                'flag' => 0,
                'rawname' => $tagname,
                'description' => $tagname . ' desc'
            ];
            $tagobjects[$tagname] = $this->getDataGenerator()->create_tag($tagrecord);
        }

        // Create a realtimequiz.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $SITE->id, 'questionsperpage' => 3, 'grade' => 100.0]);

        // Create a question category in the system context.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        // Setup standard questions.
        for ($i = 0; $i < $qnum; $i++) {
            $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
            realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);
        }
        // Setup random questions.
        for ($i = 0; $i < $randomqnum; $i++) {
            // Just create a standard question first, so there would be enough questions to pick a random question from.
            $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
            $tagids = [];
            if (!empty($questiontags[$i])) {
                foreach ($questiontags[$i] as $tagname) {
                    $tagids[] = $tagobjects[$tagname]->id;
                }
            }
            realtimequiz_add_random_questions($realtimequiz, 0, $cat->id, 1, false, $tagids);
        }

        return [$realtimequiz, $tagobjects];
    }

    public function test_realtimequiz_override_summary() {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        /** @var mod_realtimequiz_generator $realtimequizgenerator */
        $realtimequizgenerator = $generator->get_plugin_generator('mod_realtimequiz');
        /** @var renderer $renderer */
        $renderer = $PAGE->get_renderer('mod_realtimequiz');

        // Course with realtimequiz and a group - plus some others, to verify they don't get counted.
        $course = $generator->create_course();
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $course->id, 'groupmode' => SEPARATEGROUPS]);
        $cm = get_coursemodule_from_id('realtimequiz', $realtimequiz->cmid, $course->id);
        $group = $generator->create_group(['courseid' => $course->id]);
        $othergroup = $generator->create_group(['courseid' => $course->id]);
        $otherrealtimequiz = $realtimequizgenerator->create_instance(['course' => $course->id]);

        // Initial test (as admin) with no data.
        $this->setAdminUser();
        $this->assertEquals(['group' => 0, 'user' => 0, 'mode' => 'allgroups'],
                realtimequiz_override_summary($realtimequiz, $cm));
        $this->assertEquals(['group' => 0, 'user' => 0, 'mode' => 'onegroup'],
                realtimequiz_override_summary($realtimequiz, $cm, $group->id));

        // Editing teacher.
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Non-editing teacher.
        $tutor = $generator->create_user();
        $generator->enrol_user($tutor->id, $course->id, 'teacher');
        $generator->create_group_member(['userid' => $tutor->id, 'groupid' => $group->id]);

        // Three students.
        $student1 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->create_group_member(['userid' => $student1->id, 'groupid' => $group->id]);

        $student2 = $generator->create_user();
        $generator->enrol_user($student2->id, $course->id, 'student');
        $generator->create_group_member(['userid' => $student2->id, 'groupid' => $othergroup->id]);

        $student3 = $generator->create_user();
        $generator->enrol_user($student3->id, $course->id, 'student');

        // Initial test now users exist, but before overrides.
        // Test as teacher.
        $this->setUser($teacher);
        $this->assertEquals(['group' => 0, 'user' => 0, 'mode' => 'allgroups'],
                realtimequiz_override_summary($realtimequiz, $cm));
        $this->assertEquals(['group' => 0, 'user' => 0, 'mode' => 'onegroup'],
                realtimequiz_override_summary($realtimequiz, $cm, $group->id));

        // Test as tutor.
        $this->setUser($tutor);
        $this->assertEquals(['group' => 0, 'user' => 0, 'mode' => 'somegroups'],
                realtimequiz_override_summary($realtimequiz, $cm));
        $this->assertEquals(['group' => 0, 'user' => 0, 'mode' => 'onegroup'],
                realtimequiz_override_summary($realtimequiz, $cm, $group->id));
        $this->assertEquals('', $renderer->realtimequiz_override_summary_links($realtimequiz, $cm));

        // Quiz setting overrides for students 1 and 3.
        $realtimequizgenerator->create_override(['realtimequiz' => $realtimequiz->id, 'userid' => $student1->id, 'attempts' => 2]);
        $realtimequizgenerator->create_override(['realtimequiz' => $realtimequiz->id, 'userid' => $student3->id, 'attempts' => 2]);
        $realtimequizgenerator->create_override(['realtimequiz' => $realtimequiz->id, 'groupid' => $group->id, 'attempts' => 3]);
        $realtimequizgenerator->create_override(['realtimequiz' => $realtimequiz->id, 'groupid' => $othergroup->id, 'attempts' => 3]);
        $realtimequizgenerator->create_override(['realtimequiz' => $otherrealtimequiz->id, 'userid' => $student2->id, 'attempts' => 2]);

        // Test as teacher.
        $this->setUser($teacher);
        $this->assertEquals(['group' => 2, 'user' => 2, 'mode' => 'allgroups'],
                realtimequiz_override_summary($realtimequiz, $cm));
        $this->assertEquals('Settings overrides exist (Groups: 2, Users: 2)',
                // Links checked by Behat, so strip them for these tests.
                html_to_text($renderer->realtimequiz_override_summary_links($realtimequiz, $cm), 0, false));
        $this->assertEquals(['group' => 1, 'user' => 1, 'mode' => 'onegroup'],
                realtimequiz_override_summary($realtimequiz, $cm, $group->id));
        $this->assertEquals('Settings overrides exist (Groups: 1, Users: 1) for this group',
                html_to_text($renderer->realtimequiz_override_summary_links($realtimequiz, $cm, $group->id), 0, false));

        // Test as tutor.
        $this->setUser($tutor);
        $this->assertEquals(['group' => 1, 'user' => 1, 'mode' => 'somegroups'],
                realtimequiz_override_summary($realtimequiz, $cm));
        $this->assertEquals('Settings overrides exist (Groups: 1, Users: 1) for your groups',
                html_to_text($renderer->realtimequiz_override_summary_links($realtimequiz, $cm), 0, false));
        $this->assertEquals(['group' => 1, 'user' => 1, 'mode' => 'onegroup'],
                realtimequiz_override_summary($realtimequiz, $cm, $group->id));
        $this->assertEquals('Settings overrides exist (Groups: 1, Users: 1) for this group',
                html_to_text($renderer->realtimequiz_override_summary_links($realtimequiz, $cm, $group->id), 0, false));

        // Now set the realtimequiz to be group mode: no groups, and re-test as tutor.
        // In this case, the tutor should see all groups.
        $DB->set_field('course_modules', 'groupmode', NOGROUPS, ['id' => $cm->id]);
        $cm = get_coursemodule_from_id('realtimequiz', $realtimequiz->cmid, $course->id);

        $this->assertEquals(['group' => 2, 'user' => 2, 'mode' => 'allgroups'],
                realtimequiz_override_summary($realtimequiz, $cm));
        $this->assertEquals('Settings overrides exist (Groups: 2, Users: 2)',
                html_to_text($renderer->realtimequiz_override_summary_links($realtimequiz, $cm), 0, false));
    }

    /**
     *  Test realtimequiz_send_confirmation function.
     */
    public function test_realtimequiz_send_confirmation() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id);

        $recipient = $this->getDataGenerator()->create_user(['email' => 'student@example.com']);

        // Allow recipent to receive email confirm submission.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        assign_capability('mod/realtimequiz:emailconfirmsubmission', CAP_ALLOW, $studentrole->id,
            \context_course::instance($course->id), true);
        $this->getDataGenerator()->enrol_user($recipient->id, $course->id, $studentrole->id, 'manual');

        $timenow = time();
        $data = new \stdClass();
        // Course info.
        $data->courseid        = $course->id;
        $data->coursename      = $course->fullname;
        // Quiz info.
        $data->realtimequizname        = $realtimequiz->name;
        $data->realtimequizurl         = $CFG->wwwroot . '/mod/realtimequiz/view.php?id=' . $cm->id;
        $data->realtimequizid          = $realtimequiz->id;
        $data->realtimequizcmid        = $realtimequiz->cmid;
        $data->attemptid       = 1;
        $data->submissiontime = userdate($timenow);

        $sink = $this->redirectEmails();
        realtimequiz_send_confirmation($recipient, $data, true);
        $messages = $sink->get_messages();
        $message = reset($messages);
        $this->assertStringContainsString("Thank you for submitting your answers" ,
            quoted_printable_decode($message->body));
        $sink->close();

        $sink = $this->redirectEmails();
        realtimequiz_send_confirmation($recipient, $data, false);
        $messages = $sink->get_messages();
        $message = reset($messages);
        $this->assertStringContainsString("Your answers were submitted automatically" ,
            quoted_printable_decode($message->body));
        $sink->close();
    }
}
