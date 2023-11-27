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
 * Quiz module external functions tests.
 *
 * @package    mod_realtimequiz
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

namespace mod_realtimequiz\external;

use core_external\external_api;
use core_question\local\bank\question_version_status;
use externallib_advanced_testcase;
use mod_realtimequiz\question\display_options;
use mod_realtimequiz\realtimequiz_attempt;
use mod_realtimequiz\realtimequiz_settings;
use mod_realtimequiz\structure;
use mod_realtimequiz_external;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Silly class to access mod_realtimequiz_external internal methods.
 *
 * @package mod_realtimequiz
 * @copyright 2016 Juan Leyva <juan@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since  Moodle 3.1
 */
class testable_mod_realtimequiz_external extends mod_realtimequiz_external {

    /**
     * Public accessor.
     *
     * @param  array $params Array of parameters including the attemptid and preflight data
     * @param  bool $checkaccessrules whether to check the realtimequiz access rules or not
     * @param  bool $failifoverdue whether to return error if the attempt is overdue
     * @return  array containing the attempt object and access messages
     */
    public static function validate_attempt($params, $checkaccessrules = true, $failifoverdue = true) {
        return parent::validate_attempt($params, $checkaccessrules, $failifoverdue);
    }

    /**
     * Public accessor.
     *
     * @param  array $params Array of parameters including the attemptid
     * @return  array containing the attempt object and display options
     */
    public static function validate_attempt_review($params) {
        return parent::validate_attempt_review($params);
    }
}

/**
 * Quiz module external functions tests
 *
 * @package    mod_realtimequiz
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class external_test extends externallib_advanced_testcase {

    /** @var \stdClass course record. */
    protected $course;

    /** @var \stdClass activity record. */
    protected $realtimequiz;

    /** @var \context_module context instance. */
    protected $context;

    /** @var \stdClass */
    protected $cm;

    /** @var \stdClass user record. */
    protected $student;

    /** @var \stdClass user record. */
    protected $teacher;

    /** @var \stdClass user role record. */
    protected $studentrole;

    /** @var \stdClass  user role record. */
    protected $teacherrole;

    /**
     * Set up for every test
     */
    public function setUp(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();
        $this->realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', ['course' => $this->course->id]);
        $this->context = \context_module::instance($this->realtimequiz->cmid);
        $this->cm = get_coursemodule_from_instance('realtimequiz', $this->realtimequiz->id);

        // Create users.
        $this->student = self::getDataGenerator()->create_user();
        $this->teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $this->studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        // Allow student to receive messages.
        $coursecontext = \context_course::instance($this->course->id);
        assign_capability('mod/realtimequiz:emailnotifysubmission', CAP_ALLOW, $this->teacherrole->id, $coursecontext, true);

        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $this->teacherrole->id, 'manual');
    }

    /**
     * Create a realtimequiz with questions including a started or finished attempt optionally
     *
     * @param  boolean $startattempt whether to start a new attempt
     * @param  boolean $finishattempt whether to finish the new attempt
     * @param  string $behaviour the realtimequiz preferredbehaviour, defaults to 'deferredfeedback'.
     * @param  boolean $includeqattachments whether to include a question that supports attachments, defaults to false.
     * @param  array $extraoptions extra options for Quiz.
     * @return array array containing the realtimequiz, context and the attempt
     */
    private function create_realtimequiz_with_questions($startattempt = false, $finishattempt = false, $behaviour = 'deferredfeedback',
            $includeqattachments = false, $extraoptions = []) {

        // Create a new realtimequiz with attempts.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $data = ['course' => $this->course->id,
                      'sumgrades' => 2,
                      'preferredbehaviour' => $behaviour];
        $data = array_merge($data, $extraoptions);
        $realtimequiz = $realtimequizgenerator->create_instance($data);
        $context = \context_module::instance($realtimequiz->cmid);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);
        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        if ($includeqattachments) {
            $question = $questiongenerator->create_question('essay', null, ['category' => $cat->id, 'attachments' => 1,
                'attachmentsrequired' => 1]);
            realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);
        }

        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $this->student->id);

        // Set grade to pass.
        $item = \grade_item::fetch(['courseid' => $this->course->id, 'itemtype' => 'mod',
                                        'itemmodule' => 'realtimequiz', 'iteminstance' => $realtimequiz->id, 'outcomeid' => null]);
        $item->gradepass = 80;
        $item->update();

        if ($startattempt or $finishattempt) {
            // Now, do one attempt.
            $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
            $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

            $timenow = time();
            $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow, false, $this->student->id);
            realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);
            realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);
            $attemptobj = realtimequiz_attempt::create($attempt->id);

            if ($finishattempt) {
                // Process some responses from the student.
                $tosubmit = [1 => ['answer' => '3.14']];
                $attemptobj->process_submitted_actions(time(), false, $tosubmit);

                // Finish the attempt.
                $attemptobj->process_finish(time(), false);
            }
            return [$realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj, $quba];
        } else {
            return [$realtimequiz, $context, $realtimequizobj];
        }

    }

    /*
     * Test get realtimequizzes by courses
     */
    public function test_mod_realtimequiz_get_realtimequizzes_by_courses() {
        global $DB;

        // Create additional course.
        $course2 = self::getDataGenerator()->create_course();

        // Second realtimequiz.
        $record = new \stdClass();
        $record->course = $course2->id;
        $record->intro = '<button>Test with HTML allowed.</button>';
        $realtimequiz2 = self::getDataGenerator()->create_module('realtimequiz', $record);

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $this->student->id, $this->studentrole->id);

        self::setUser($this->student);

        $returndescription = mod_realtimequiz_external::get_realtimequizzes_by_courses_returns();

        // Create what we expect to be returned when querying the two courses.
        // First for the student user.
        $allusersfields = ['id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'introfiles', 'lang',
                                'timeopen', 'timeclose', 'grademethod', 'section', 'visible', 'groupmode', 'groupingid',
                                'attempts', 'timelimit', 'grademethod', 'decimalpoints', 'questiondecimalpoints', 'sumgrades',
                                'grade', 'preferredbehaviour', 'hasfeedback'];
        $userswithaccessfields = ['attemptonlast', 'reviewattempt', 'reviewcorrectness', 'reviewmarks',
                                        'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer',
                                        'reviewoverallfeedback', 'questionsperpage', 'navmethod',
                                        'browsersecurity', 'delay1', 'delay2', 'showuserpicture', 'showblocks',
                                        'completionattemptsexhausted', 'completionpass', 'autosaveperiod', 'hasquestions',
                                        'overduehandling', 'graceperiod', 'canredoquestions', 'allowofflineattempts'];
        $managerfields = ['shuffleanswers', 'timecreated', 'timemodified', 'password', 'subnet'];

        // Add expected coursemodule and other data.
        $realtimequiz1 = $this->realtimequiz;
        $realtimequiz1->coursemodule = $realtimequiz1->cmid;
        $realtimequiz1->introformat = 1;
        $realtimequiz1->section = 0;
        $realtimequiz1->visible = true;
        $realtimequiz1->groupmode = 0;
        $realtimequiz1->groupingid = 0;
        $realtimequiz1->hasquestions = 0;
        $realtimequiz1->hasfeedback = 0;
        $realtimequiz1->completionpass = 0;
        $realtimequiz1->autosaveperiod = get_config('realtimequiz', 'autosaveperiod');
        $realtimequiz1->introfiles = [];
        $realtimequiz1->lang = '';

        $realtimequiz2->coursemodule = $realtimequiz2->cmid;
        $realtimequiz2->introformat = 1;
        $realtimequiz2->section = 0;
        $realtimequiz2->visible = true;
        $realtimequiz2->groupmode = 0;
        $realtimequiz2->groupingid = 0;
        $realtimequiz2->hasquestions = 0;
        $realtimequiz2->hasfeedback = 0;
        $realtimequiz2->completionpass = 0;
        $realtimequiz2->autosaveperiod = get_config('realtimequiz', 'autosaveperiod');
        $realtimequiz2->introfiles = [];
        $realtimequiz2->lang = '';

        foreach (array_merge($allusersfields, $userswithaccessfields) as $field) {
            $expected1[$field] = $realtimequiz1->{$field};
            $expected2[$field] = $realtimequiz2->{$field};
        }

        $expectedrealtimequizzes = [$expected2, $expected1];

        // Call the external function passing course ids.
        $result = mod_realtimequiz_external::get_realtimequizzes_by_courses([$course2->id, $this->course->id]);
        $result = external_api::clean_returnvalue($returndescription, $result);

        $this->assertEquals($expectedrealtimequizzes, $result['realtimequizzes']);
        $this->assertCount(0, $result['warnings']);

        // Call the external function without passing course id.
        $result = mod_realtimequiz_external::get_realtimequizzes_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedrealtimequizzes, $result['realtimequizzes']);
        $this->assertCount(0, $result['warnings']);

        // Unenrol user from second course and alter expected realtimequizzes.
        $enrol->unenrol_user($instance2, $this->student->id);
        array_shift($expectedrealtimequizzes);

        // Call the external function without passing course id.
        $result = mod_realtimequiz_external::get_realtimequizzes_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedrealtimequizzes, $result['realtimequizzes']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_realtimequiz_external::get_realtimequizzes_by_courses([$course2->id]);
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);

        // Now, try as a teacher for getting all the additional fields.
        self::setUser($this->teacher);

        foreach ($managerfields as $field) {
            $expectedrealtimequizzes[0][$field] = $realtimequiz1->{$field};
        }

        $result = mod_realtimequiz_external::get_realtimequizzes_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedrealtimequizzes, $result['realtimequizzes']);

        // Admin also should get all the information.
        self::setAdminUser();

        $result = mod_realtimequiz_external::get_realtimequizzes_by_courses([$this->course->id]);
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedrealtimequizzes, $result['realtimequizzes']);

        // Now, prevent access.
        $enrol->enrol_user($instance2, $this->student->id);

        self::setUser($this->student);

        $realtimequiz2->timeclose = time() - DAYSECS;
        $DB->update_record('realtimequiz', $realtimequiz2);

        $result = mod_realtimequiz_external::get_realtimequizzes_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertCount(2, $result['realtimequizzes']);
        // We only see a limited set of fields.
        $this->assertCount(5, $result['realtimequizzes'][0]);
        $this->assertEquals($realtimequiz2->id, $result['realtimequizzes'][0]['id']);
        $this->assertEquals($realtimequiz2->cmid, $result['realtimequizzes'][0]['coursemodule']);
        $this->assertEquals($realtimequiz2->course, $result['realtimequizzes'][0]['course']);
        $this->assertEquals($realtimequiz2->name, $result['realtimequizzes'][0]['name']);
        $this->assertEquals($realtimequiz2->course, $result['realtimequizzes'][0]['course']);

        $this->assertFalse(isset($result['realtimequizzes'][0]['timelimit']));

    }

    /**
     * Test test_view_realtimequiz
     */
    public function test_view_realtimequiz() {
        global $DB;

        // Test invalid instance id.
        try {
            mod_realtimequiz_external::view_realtimequiz(0);
            $this->fail('Exception expected due to invalid mod_realtimequiz instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test not-enrolled user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);
        try {
            mod_realtimequiz_external::view_realtimequiz($this->realtimequiz->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_realtimequiz_external::view_realtimequiz($this->realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::view_realtimequiz_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_realtimequiz\event\course_module_viewed', $event);
        $this->assertEquals($this->context, $event->get_context());
        $moodlerealtimequiz = new \moodle_url('/mod/realtimequiz/view.php', ['id' => $this->cm->id]);
        $this->assertEquals($moodlerealtimequiz, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/realtimequiz:view', CAP_PROHIBIT, $this->studentrole->id, $this->context->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        \course_modinfo::clear_instance_cache();

        try {
            mod_realtimequiz_external::view_realtimequiz($this->realtimequiz->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

    }

    /**
     * Test get_user_attempts
     */
    public function test_get_user_attempts() {

        // Create a realtimequiz with one attempt finished.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj) = $this->create_realtimequiz_with_questions(true, true);

        $this->setUser($this->student);
        $result = mod_realtimequiz_external::get_user_attempts($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($attempt->id, $result['attempts'][0]['id']);
        $this->assertEquals($realtimequiz->id, $result['attempts'][0]['realtimequiz']);
        $this->assertEquals($this->student->id, $result['attempts'][0]['userid']);
        $this->assertEquals(1, $result['attempts'][0]['attempt']);
        $this->assertArrayHasKey('sumgrades', $result['attempts'][0]);
        $this->assertEquals(1.0, $result['attempts'][0]['sumgrades']);

        // Test filters. Only finished.
        $result = mod_realtimequiz_external::get_user_attempts($realtimequiz->id, 0, 'finished', false);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($attempt->id, $result['attempts'][0]['id']);

        // Test filters. All attempts.
        $result = mod_realtimequiz_external::get_user_attempts($realtimequiz->id, 0, 'all', false);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($attempt->id, $result['attempts'][0]['id']);

        // Test filters. Unfinished.
        $result = mod_realtimequiz_external::get_user_attempts($realtimequiz->id, 0, 'unfinished', false);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_attempts_returns(), $result);

        $this->assertCount(0, $result['attempts']);

        // Start a new attempt, but not finish it.
        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 2, false, $timenow, false, $this->student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Test filters. All attempts.
        $result = mod_realtimequiz_external::get_user_attempts($realtimequiz->id, 0, 'all', false);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_attempts_returns(), $result);

        $this->assertCount(2, $result['attempts']);

        // Test filters. Unfinished.
        $result = mod_realtimequiz_external::get_user_attempts($realtimequiz->id, 0, 'unfinished', false);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);

        // Test manager can see user attempts.
        $this->setUser($this->teacher);
        $result = mod_realtimequiz_external::get_user_attempts($realtimequiz->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($this->student->id, $result['attempts'][0]['userid']);

        $result = mod_realtimequiz_external::get_user_attempts($realtimequiz->id, $this->student->id, 'all');
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_attempts_returns(), $result);

        $this->assertCount(2, $result['attempts']);
        $this->assertEquals($this->student->id, $result['attempts'][0]['userid']);

        // Invalid parameters.
        try {
            mod_realtimequiz_external::get_user_attempts($realtimequiz->id, $this->student->id, 'INVALID_PARAMETER');
            $this->fail('Exception expected due to missing capability.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertEquals('invalidparameter', $e->errorcode);
        }
    }

    /**
     * Test get_user_attempts with marks hidden
     */
    public function test_get_user_attempts_with_marks_hidden() {
        // Create realtimequiz with one attempt finished and hide the mark.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj) = $this->create_realtimequiz_with_questions(
                true, true, 'deferredfeedback', false,
                ['marksduring' => 0, 'marksimmediately' => 0, 'marksopen' => 0, 'marksclosed' => 0]);

        // Student cannot see the grades.
        $this->setUser($this->student);
        $result = mod_realtimequiz_external::get_user_attempts($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($attempt->id, $result['attempts'][0]['id']);
        $this->assertEquals($realtimequiz->id, $result['attempts'][0]['realtimequiz']);
        $this->assertEquals($this->student->id, $result['attempts'][0]['userid']);
        $this->assertEquals(1, $result['attempts'][0]['attempt']);
        $this->assertArrayHasKey('sumgrades', $result['attempts'][0]);
        $this->assertEquals(null, $result['attempts'][0]['sumgrades']);

        // Test manager can see user grades.
        $this->setUser($this->teacher);
        $result = mod_realtimequiz_external::get_user_attempts($realtimequiz->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($attempt->id, $result['attempts'][0]['id']);
        $this->assertEquals($realtimequiz->id, $result['attempts'][0]['realtimequiz']);
        $this->assertEquals($this->student->id, $result['attempts'][0]['userid']);
        $this->assertEquals(1, $result['attempts'][0]['attempt']);
        $this->assertArrayHasKey('sumgrades', $result['attempts'][0]);
        $this->assertEquals(1.0, $result['attempts'][0]['sumgrades']);
    }

    /**
     * Test get_user_best_grade
     */
    public function test_get_user_best_grade() {
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $questioncat = $questiongenerator->create_question_category();

        // Create a new realtimequiz.
        $realtimequizapi1 = $realtimequizgenerator->create_instance([
                'name' => 'Test Quiz API 1',
                'course' => $this->course->id,
                'sumgrades' => 1
        ]);
        $realtimequizapi2 = $realtimequizgenerator->create_instance([
                'name' => 'Test Quiz API 2',
                'course' => $this->course->id,
                'sumgrades' => 1,
                'marksduring' => 0,
                'marksimmediately' => 0,
                'marksopen' => 0,
                'marksclosed' => 0
        ]);

        // Create a question.
        $question = $questiongenerator->create_question('numerical', null, ['category' => $questioncat->id]);

        // Add question to the realtimequizzes.
        realtimequiz_add_realtimequiz_question($question->id, $realtimequizapi1);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequizapi2);

        // Create realtimequiz object.
        $realtimequizapiobj1 = realtimequiz_settings::create($realtimequizapi1->id, $this->student->id);
        $realtimequizapiobj2 = realtimequiz_settings::create($realtimequizapi2->id, $this->student->id);

        // Set grade to pass.
        $item = \grade_item::fetch([
                'courseid' => $this->course->id,
                'itemtype' => 'mod',
                'itemmodule' => 'realtimequiz',
                'iteminstance' => $realtimequizapi1->id,
                'outcomeid' => null
        ]);
        $item->gradepass = 80;
        $item->update();

        $item = \grade_item::fetch([
                'courseid' => $this->course->id,
                'itemtype' => 'mod',
                'itemmodule' => 'realtimequiz',
                'iteminstance' => $realtimequizapi2->id,
                'outcomeid' => null
        ]);
        $item->gradepass = 80;
        $item->update();

        // Start the passing attempt.
        $quba1 = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizapiobj1->get_context());
        $quba1->set_preferred_behaviour($realtimequizapiobj1->get_realtimequiz()->preferredbehaviour);

        $quba2 = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizapiobj2->get_context());
        $quba2->set_preferred_behaviour($realtimequizapiobj2->get_realtimequiz()->preferredbehaviour);

        // Start the testing for realtimequizapi1 that allow the student to view the grade.

        $this->setUser($this->student);
        $result = mod_realtimequiz_external::get_user_best_grade($realtimequizapi1->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_best_grade_returns(), $result);

        // No grades yet.
        $this->assertFalse($result['hasgrade']);
        $this->assertTrue(!isset($result['grade']));

        // Start the attempt.
        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizapiobj1, 1, false, $timenow, false, $this->student->id);
        realtimequiz_start_new_attempt($realtimequizapiobj1, $quba1, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizapiobj1, $quba1, $attempt);

        // Process some responses from the student.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);

        // Finish the attempt.
        $attemptobj->process_finish($timenow, false);

        $result = mod_realtimequiz_external::get_user_best_grade($realtimequizapi1->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_best_grade_returns(), $result);

        // Now I have grades.
        $this->assertTrue($result['hasgrade']);
        $this->assertEquals(100.0, $result['grade']);
        $this->assertEquals(80, $result['gradetopass']);

        // We should not see other users grades.
        $anotherstudent = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($anotherstudent->id, $this->course->id, $this->studentrole->id, 'manual');

        try {
            mod_realtimequiz_external::get_user_best_grade($realtimequizapi1->id, $anotherstudent->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (\required_capability_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        // Teacher must be able to see student grades.
        $this->setUser($this->teacher);

        $result = mod_realtimequiz_external::get_user_best_grade($realtimequizapi1->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_best_grade_returns(), $result);

        $this->assertTrue($result['hasgrade']);
        $this->assertEquals(100.0, $result['grade']);
        $this->assertEquals(80, $result['gradetopass']);

        // Invalid user.
        try {
            mod_realtimequiz_external::get_user_best_grade($this->realtimequiz->id, -1);
            $this->fail('Exception expected due to missing capability.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals('invaliduser', $e->errorcode);
        }

        // End the testing for realtimequizapi1 that allow the student to view the grade.

        // Start the testing for realtimequizapi2 that do not allow the student to view the grade.

        $this->setUser($this->student);
        $result = mod_realtimequiz_external::get_user_best_grade($realtimequizapi2->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_best_grade_returns(), $result);

        // No grades yet.
        $this->assertFalse($result['hasgrade']);
        $this->assertTrue(!isset($result['grade']));

        // Start the attempt.
        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizapiobj2, 1, false, $timenow, false, $this->student->id);
        realtimequiz_start_new_attempt($realtimequizapiobj2, $quba2, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizapiobj2, $quba2, $attempt);

        // Process some responses from the student.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);

        // Finish the attempt.
        $attemptobj->process_finish($timenow, false);

        $result = mod_realtimequiz_external::get_user_best_grade($realtimequizapi2->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_best_grade_returns(), $result);

        // Now I have grades but I will not be allowed to see it.
        $this->assertFalse($result['hasgrade']);
        $this->assertTrue(!isset($result['grade']));

        // Teacher must be able to see student grades.
        $this->setUser($this->teacher);

        $result = mod_realtimequiz_external::get_user_best_grade($realtimequizapi2->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_user_best_grade_returns(), $result);

        $this->assertTrue($result['hasgrade']);
        $this->assertEquals(100.0, $result['grade']);

        // End the testing for realtimequizapi2 that do not allow the student to view the grade.

    }
    /**
     * Test get_combined_review_options.
     * This is a basic test, this is already tested in display_options_testcase.
     */
    public function test_get_combined_review_options() {
        global $DB;

        // Create a new realtimequiz with attempts.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $data = ['course' => $this->course->id,
                      'sumgrades' => 1];
        $realtimequiz = $realtimequizgenerator->create_instance($data);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $this->student->id);

        // Set grade to pass.
        $item = \grade_item::fetch(['courseid' => $this->course->id, 'itemtype' => 'mod',
                                        'itemmodule' => 'realtimequiz', 'iteminstance' => $realtimequiz->id, 'outcomeid' => null]);
        $item->gradepass = 80;
        $item->update();

        // Start the passing attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow, false, $this->student->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        $this->setUser($this->student);

        $result = mod_realtimequiz_external::get_combined_review_options($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_combined_review_options_returns(), $result);

        // Expected values.
        $expected = [
            "someoptions" => [
                ["name" => "feedback", "value" => 1],
                ["name" => "generalfeedback", "value" => 1],
                ["name" => "rightanswer", "value" => 1],
                ["name" => "overallfeedback", "value" => 0],
                ["name" => "marks", "value" => 2],
            ],
            "alloptions" => [
                ["name" => "feedback", "value" => 1],
                ["name" => "generalfeedback", "value" => 1],
                ["name" => "rightanswer", "value" => 1],
                ["name" => "overallfeedback", "value" => 0],
                ["name" => "marks", "value" => 2],
            ],
            "warnings" => [],
        ];

        $this->assertEquals($expected, $result);

        // Now, finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        $expected = [
            "someoptions" => [
                ["name" => "feedback", "value" => 1],
                ["name" => "generalfeedback", "value" => 1],
                ["name" => "rightanswer", "value" => 1],
                ["name" => "overallfeedback", "value" => 1],
                ["name" => "marks", "value" => 2],
            ],
            "alloptions" => [
                ["name" => "feedback", "value" => 1],
                ["name" => "generalfeedback", "value" => 1],
                ["name" => "rightanswer", "value" => 1],
                ["name" => "overallfeedback", "value" => 1],
                ["name" => "marks", "value" => 2],
            ],
            "warnings" => [],
        ];

        // We should see now the overall feedback.
        $result = mod_realtimequiz_external::get_combined_review_options($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_combined_review_options_returns(), $result);
        $this->assertEquals($expected, $result);

        // Start a new attempt, but not finish it.
        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 2, false, $timenow, false, $this->student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        $expected = [
            "someoptions" => [
                ["name" => "feedback", "value" => 1],
                ["name" => "generalfeedback", "value" => 1],
                ["name" => "rightanswer", "value" => 1],
                ["name" => "overallfeedback", "value" => 1],
                ["name" => "marks", "value" => 2],
            ],
            "alloptions" => [
                ["name" => "feedback", "value" => 1],
                ["name" => "generalfeedback", "value" => 1],
                ["name" => "rightanswer", "value" => 1],
                ["name" => "overallfeedback", "value" => 0],
                ["name" => "marks", "value" => 2],
            ],
            "warnings" => [],
        ];

        $result = mod_realtimequiz_external::get_combined_review_options($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_combined_review_options_returns(), $result);
        $this->assertEquals($expected, $result);

        // Teacher, for see student options.
        $this->setUser($this->teacher);

        $result = mod_realtimequiz_external::get_combined_review_options($realtimequiz->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_combined_review_options_returns(), $result);

        $this->assertEquals($expected, $result);

        // Invalid user.
        try {
            mod_realtimequiz_external::get_combined_review_options($realtimequiz->id, -1);
            $this->fail('Exception expected due to missing capability.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals('invaliduser', $e->errorcode);
        }
    }

    /**
     * Test start_attempt
     */
    public function test_start_attempt() {
        global $DB;

        // Create a new realtimequiz with questions.
        list($realtimequiz, $context, $realtimequizobj) = $this->create_realtimequiz_with_questions();

        $this->setUser($this->student);

        // Try to open attempt in closed realtimequiz.
        $realtimequiz->timeopen = time() - WEEKSECS;
        $realtimequiz->timeclose = time() - DAYSECS;
        $DB->update_record('realtimequiz', $realtimequiz);
        $result = mod_realtimequiz_external::start_attempt($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::start_attempt_returns(), $result);

        $this->assertEquals([], $result['attempt']);
        $this->assertCount(1, $result['warnings']);

        // Now with a password.
        $realtimequiz->timeopen = 0;
        $realtimequiz->timeclose = 0;
        $realtimequiz->password = 'abc';
        $DB->update_record('realtimequiz', $realtimequiz);

        try {
            mod_realtimequiz_external::start_attempt($realtimequiz->id, [["name" => "realtimequizpassword", "value" => 'bad']]);
            $this->fail('Exception expected due to invalid passwod.');
        } catch (moodle_exception $e) {
            $this->assertEquals(get_string('passworderror', 'realtimequizaccess_password'), $e->errorcode);
        }

        // Now, try everything correct.
        $result = mod_realtimequiz_external::start_attempt($realtimequiz->id, [["name" => "realtimequizpassword", "value" => 'abc']]);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::start_attempt_returns(), $result);

        $this->assertEquals(1, $result['attempt']['attempt']);
        $this->assertEquals($this->student->id, $result['attempt']['userid']);
        $this->assertEquals($realtimequiz->id, $result['attempt']['realtimequiz']);
        $this->assertCount(0, $result['warnings']);
        $attemptid = $result['attempt']['id'];

        // We are good, try to start a new attempt now.

        try {
            mod_realtimequiz_external::start_attempt($realtimequiz->id, [["name" => "realtimequizpassword", "value" => 'abc']]);
            $this->fail('Exception expected due to attempt not finished.');
        } catch (moodle_exception $e) {
            $this->assertEquals('attemptstillinprogress', $e->errorcode);
        }

        // Finish the started attempt.

        // Process some responses from the student.
        $timenow = time();
        $attemptobj = realtimequiz_attempt::create($attemptid);
        $tosubmit = [1 => ['answer' => '3.14']];
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attemptid);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // We should be able to start a new attempt.
        $result = mod_realtimequiz_external::start_attempt($realtimequiz->id, [["name" => "realtimequizpassword", "value" => 'abc']]);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::start_attempt_returns(), $result);

        $this->assertEquals(2, $result['attempt']['attempt']);
        $this->assertEquals($this->student->id, $result['attempt']['userid']);
        $this->assertEquals($realtimequiz->id, $result['attempt']['realtimequiz']);
        $this->assertCount(0, $result['warnings']);

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/realtimequiz:attempt', CAP_PROHIBIT, $this->studentrole->id, $context->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        \course_modinfo::clear_instance_cache();

        try {
            mod_realtimequiz_external::start_attempt($realtimequiz->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (\required_capability_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

    }

    /**
     * Test validate_attempt
     */
    public function test_validate_attempt() {
        global $DB;

        // Create a new realtimequiz with one attempt started.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj) = $this->create_realtimequiz_with_questions(true);

        $this->setUser($this->student);

        // Invalid attempt.
        try {
            $params = ['attemptid' => -1, 'page' => 0];
            testable_mod_realtimequiz_external::validate_attempt($params);
            $this->fail('Exception expected due to invalid attempt id.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test OK case.
        $params = ['attemptid' => $attempt->id, 'page' => 0];
        $result = testable_mod_realtimequiz_external::validate_attempt($params);
        $this->assertEquals($attempt->id, $result[0]->get_attempt()->id);
        $this->assertEquals([], $result[1]);

        // Test with preflight data.
        $realtimequiz->password = 'abc';
        $DB->update_record('realtimequiz', $realtimequiz);

        try {
            $params = ['attemptid' => $attempt->id, 'page' => 0,
                            'preflightdata' => [["name" => "realtimequizpassword", "value" => 'bad']]];
            testable_mod_realtimequiz_external::validate_attempt($params);
            $this->fail('Exception expected due to invalid passwod.');
        } catch (moodle_exception $e) {
            $this->assertEquals(get_string('passworderror', 'realtimequizaccess_password'), $e->errorcode);
        }

        // Now, try everything correct.
        $params['preflightdata'][0]['value'] = 'abc';
        $result = testable_mod_realtimequiz_external::validate_attempt($params);
        $this->assertEquals($attempt->id, $result[0]->get_attempt()->id);
        $this->assertEquals([], $result[1]);

        // Page out of range.
        $DB->update_record('realtimequiz', $realtimequiz);
        $params['page'] = 4;
        try {
            testable_mod_realtimequiz_external::validate_attempt($params);
            $this->fail('Exception expected due to page out of range.');
        } catch (moodle_exception $e) {
            $this->assertEquals('Invalid page number', $e->errorcode);
        }

        $params['page'] = 0;
        // Try to open attempt in closed realtimequiz.
        $realtimequiz->timeopen = time() - WEEKSECS;
        $realtimequiz->timeclose = time() - DAYSECS;
        $DB->update_record('realtimequiz', $realtimequiz);

        // This should work, ommit access rules.
        testable_mod_realtimequiz_external::validate_attempt($params, false);

        // Get a generic error because prior to checking the dates the attempt is closed.
        try {
            testable_mod_realtimequiz_external::validate_attempt($params);
            $this->fail('Exception expected due to passed dates.');
        } catch (moodle_exception $e) {
            $this->assertEquals('attempterror', $e->errorcode);
        }

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $attemptobj->process_finish(time(), false);

        try {
            testable_mod_realtimequiz_external::validate_attempt($params, false);
            $this->fail('Exception expected due to attempt finished.');
        } catch (moodle_exception $e) {
            $this->assertEquals('attemptalreadyclosed', $e->errorcode);
        }

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/realtimequiz:attempt', CAP_PROHIBIT, $this->studentrole->id, $context->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        \course_modinfo::clear_instance_cache();

        try {
            testable_mod_realtimequiz_external::validate_attempt($params);
            $this->fail('Exception expected due to missing permissions.');
        } catch (\required_capability_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        // Now try with a different user.
        $this->setUser($this->teacher);

        $params['page'] = 0;
        try {
            testable_mod_realtimequiz_external::validate_attempt($params);
            $this->fail('Exception expected due to not your attempt.');
        } catch (moodle_exception $e) {
            $this->assertEquals('notyourattempt', $e->errorcode);
        }
    }

    /**
     * Test get_attempt_data
     */
    public function test_get_attempt_data() {
        global $DB;

        $timenow = time();
        // Create a new realtimequiz with one attempt started.
        [$realtimequiz, , $realtimequizobj, $attempt] = $this->create_realtimequiz_with_questions(true);
        /** @var structure $structure */
        $structure = $realtimequizobj->get_structure();
        $structure->update_slot_display_number($structure->get_slot_id_for_slot(1), '1.a');

        // Set correctness mask so questions state can be fetched only after finishing the attempt.
        $DB->set_field('realtimequiz', 'reviewcorrectness', display_options::IMMEDIATELY_AFTER, ['id' => $realtimequiz->id]);

        // Having changed some settings, recreate the objects.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $realtimequizobj = $attemptobj->get_realtimequizobj();
        $realtimequizobj->preload_questions();
        $realtimequizobj->load_questions();
        $questions = $realtimequizobj->get_questions();

        $this->setUser($this->student);

        // We receive one question per page.
        $result = mod_realtimequiz_external::get_attempt_data($attempt->id, 0);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_data_returns(), $result);

        $this->assertEquals($attempt, (object) $result['attempt']);
        $this->assertEquals(1, $result['nextpage']);
        $this->assertCount(0, $result['messages']);
        $this->assertCount(1, $result['questions']);
        $this->assertEquals(1, $result['questions'][0]['slot']);
        $this->assertArrayNotHasKey('number', $result['questions'][0]);
        $this->assertEquals('1.a', $result['questions'][0]['questionnumber']);
        $this->assertEquals('numerical', $result['questions'][0]['type']);
        $this->assertArrayNotHasKey('state', $result['questions'][0]);  // We don't receive the state yet.
        $this->assertEquals(get_string('notyetanswered', 'question'), $result['questions'][0]['status']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertEquals(0, $result['questions'][0]['page']);
        $this->assertEmpty($result['questions'][0]['mark']);
        $this->assertEquals(1, $result['questions'][0]['maxmark']);
        $this->assertEquals(1, $result['questions'][0]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);

        // Now try the last page.
        $result = mod_realtimequiz_external::get_attempt_data($attempt->id, 1);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_data_returns(), $result);

        $this->assertEquals($attempt, (object) $result['attempt']);
        $this->assertEquals(-1, $result['nextpage']);
        $this->assertCount(0, $result['messages']);
        $this->assertCount(1, $result['questions']);
        $this->assertEquals(2, $result['questions'][0]['slot']);
        $this->assertEquals(2, $result['questions'][0]['questionnumber']);
        $this->assertEquals(2, $result['questions'][0]['number']);
        $this->assertEquals('numerical', $result['questions'][0]['type']);
        $this->assertArrayNotHasKey('state', $result['questions'][0]);  // We don't receive the state yet.
        $this->assertEquals(get_string('notyetanswered', 'question'), $result['questions'][0]['status']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertEquals(1, $result['questions'][0]['page']);
        $this->assertEquals(1, $result['questions'][0]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);

        // Finish previous attempt.
        $attemptobj->process_finish(time(), false);

        // Now we should receive the question state.
        $result = mod_realtimequiz_external::get_attempt_review($attempt->id, 1);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_review_returns(), $result);
        $this->assertEquals('gaveup', $result['questions'][0]['state']);

        // Change setting and expect two pages.
        $realtimequiz->questionsperpage = 4;
        $DB->update_record('realtimequiz', $realtimequiz);
        realtimequiz_repaginate_questions($realtimequiz->id, $realtimequiz->questionsperpage);

        // Start with new attempt with the new layout.
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 2, false, $timenow, false, $this->student->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // We receive two questions per page.
        $result = mod_realtimequiz_external::get_attempt_data($attempt->id, 0);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_data_returns(), $result);
        $this->assertCount(2, $result['questions']);
        $this->assertEquals(-1, $result['nextpage']);

        // Check questions looks good.
        $found = 0;
        foreach ($questions as $question) {
            foreach ($result['questions'] as $rquestion) {
                if ($rquestion['slot'] == $question->slot) {
                    $this->assertTrue(strpos($rquestion['html'], "qid=$question->id") !== false);
                    $found++;
                }
            }
        }
        $this->assertEquals(2, $found);

    }

    /**
     * Test get_attempt_data with blocked questions.
     * @since 3.2
     */
    public function test_get_attempt_data_with_blocked_questions() {
        global $DB;

        // Create a new realtimequiz with one attempt started and using immediatefeedback.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj) = $this->create_realtimequiz_with_questions(
                true, false, 'immediatefeedback');

        $realtimequizobj = $attemptobj->get_realtimequizobj();

        // Make second question blocked by the first one.
        $structure = $realtimequizobj->get_structure();
        $slots = $structure->get_slots();
        $structure->update_question_dependency(end($slots)->id, true);

        $realtimequizobj->preload_questions();
        $realtimequizobj->load_questions();
        $questions = $realtimequizobj->get_questions();

        $this->setUser($this->student);

        // We receive one question per page.
        $result = mod_realtimequiz_external::get_attempt_data($attempt->id, 0);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_data_returns(), $result);

        $this->assertEquals($attempt, (object) $result['attempt']);
        $this->assertCount(1, $result['questions']);
        $this->assertEquals(1, $result['questions'][0]['slot']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals(false, $result['questions'][0]['blockedbyprevious']);

        // Now try the last page.
        $result = mod_realtimequiz_external::get_attempt_data($attempt->id, 1);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_data_returns(), $result);

        $this->assertEquals($attempt, (object) $result['attempt']);
        $this->assertCount(1, $result['questions']);
        $this->assertEquals(2, $result['questions'][0]['slot']);
        $this->assertEquals(2, $result['questions'][0]['number']);
        $this->assertEquals(true, $result['questions'][0]['blockedbyprevious']);
    }

    /**
     * Test get_attempt_summary
     */
    public function test_get_attempt_summary() {

        $timenow = time();
        // Create a new realtimequiz with one attempt started.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj) = $this->create_realtimequiz_with_questions(true);

        $this->setUser($this->student);
        $result = mod_realtimequiz_external::get_attempt_summary($attempt->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_summary_returns(), $result);

        // Check the state, flagged and mark data is correct.
        $this->assertEquals('todo', $result['questions'][0]['state']);
        $this->assertEquals('todo', $result['questions'][1]['state']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals(2, $result['questions'][1]['number']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertFalse($result['questions'][1]['flagged']);
        $this->assertEmpty($result['questions'][0]['mark']);
        $this->assertEmpty($result['questions'][1]['mark']);
        $this->assertEquals(1, $result['questions'][0]['sequencecheck']);
        $this->assertEquals(1, $result['questions'][1]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][1]['lastactiontime']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);
        $this->assertEquals(false, $result['questions'][1]['hasautosavedstep']);

        // Check question options.
        $this->assertNotEmpty(5, $result['questions'][0]['settings']);
        // Check at least some settings returned.
        $this->assertCount(4, (array) json_decode($result['questions'][0]['settings']));

        // Submit a response for the first question.
        $tosubmit = [1 => ['answer' => '3.14']];
        $attemptobj->process_submitted_actions(time(), false, $tosubmit);
        $result = mod_realtimequiz_external::get_attempt_summary($attempt->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_summary_returns(), $result);

        // Check it's marked as completed only the first one.
        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals('todo', $result['questions'][1]['state']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals(2, $result['questions'][1]['number']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertFalse($result['questions'][1]['flagged']);
        $this->assertEmpty($result['questions'][0]['mark']);
        $this->assertEmpty($result['questions'][1]['mark']);
        $this->assertEquals(2, $result['questions'][0]['sequencecheck']);
        $this->assertEquals(1, $result['questions'][1]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][1]['lastactiontime']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);
        $this->assertEquals(false, $result['questions'][1]['hasautosavedstep']);

    }

    /**
     * Test save_attempt
     */
    public function test_save_attempt() {

        $timenow = time();
        // Create a new realtimequiz with one attempt started.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj, $quba) = $this->create_realtimequiz_with_questions(true);

        // Response for slot 1.
        $prefix = $quba->get_field_prefix(1);
        $data = [
            ['name' => 'slots', 'value' => 1],
            ['name' => $prefix . ':sequencecheck',
                    'value' => $attemptobj->get_question_attempt(1)->get_sequence_check_count()],
            ['name' => $prefix . 'answer', 'value' => 1],
        ];

        $this->setUser($this->student);

        $result = mod_realtimequiz_external::save_attempt($attempt->id, $data);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::save_attempt_returns(), $result);
        $this->assertTrue($result['status']);

        // Now, get the summary.
        $result = mod_realtimequiz_external::get_attempt_summary($attempt->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_summary_returns(), $result);

        // Check it's marked as completed only the first one.
        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals('todo', $result['questions'][1]['state']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals(2, $result['questions'][1]['number']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertFalse($result['questions'][1]['flagged']);
        $this->assertEmpty($result['questions'][0]['mark']);
        $this->assertEmpty($result['questions'][1]['mark']);
        $this->assertEquals(1, $result['questions'][0]['sequencecheck']);
        $this->assertEquals(1, $result['questions'][1]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][1]['lastactiontime']);
        $this->assertEquals(true, $result['questions'][0]['hasautosavedstep']);
        $this->assertEquals(false, $result['questions'][1]['hasautosavedstep']);

        // Now, second slot.
        $prefix = $quba->get_field_prefix(2);
        $data = [
            ['name' => 'slots', 'value' => 2],
            ['name' => $prefix . ':sequencecheck',
                    'value' => $attemptobj->get_question_attempt(1)->get_sequence_check_count()],
            ['name' => $prefix . 'answer', 'value' => 1],
        ];

        $result = mod_realtimequiz_external::save_attempt($attempt->id, $data);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::save_attempt_returns(), $result);
        $this->assertTrue($result['status']);

        // Now, get the summary.
        $result = mod_realtimequiz_external::get_attempt_summary($attempt->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_summary_returns(), $result);

        // Check it's marked as completed only the first one.
        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals(1, $result['questions'][0]['sequencecheck']);
        $this->assertEquals('complete', $result['questions'][1]['state']);
        $this->assertEquals(1, $result['questions'][1]['sequencecheck']);

    }

    /**
     * Test process_attempt
     */
    public function test_process_attempt() {
        global $DB;

        $timenow = time();
        // Create a new realtimequiz with three questions and one attempt started.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj, $quba) = $this->create_realtimequiz_with_questions(true, false,
            'deferredfeedback', true);

        // Response for slot 1.
        $prefix = $quba->get_field_prefix(1);
        $data = [
            ['name' => 'slots', 'value' => 1],
            ['name' => $prefix . ':sequencecheck',
                    'value' => $attemptobj->get_question_attempt(1)->get_sequence_check_count()],
            ['name' => $prefix . 'answer', 'value' => 1],
        ];

        $this->setUser($this->student);

        $result = mod_realtimequiz_external::process_attempt($attempt->id, $data);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::process_attempt_returns(), $result);
        $this->assertEquals(realtimequiz_attempt::IN_PROGRESS, $result['state']);

        $result = mod_realtimequiz_external::get_attempt_data($attempt->id, 2);

        // Now, get the summary.
        $result = mod_realtimequiz_external::get_attempt_summary($attempt->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_summary_returns(), $result);

        // Check it's marked as completed only the first one.
        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals('todo', $result['questions'][1]['state']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals(2, $result['questions'][1]['number']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertFalse($result['questions'][1]['flagged']);
        $this->assertEmpty($result['questions'][0]['mark']);
        $this->assertEmpty($result['questions'][1]['mark']);
        $this->assertEquals(2, $result['questions'][0]['sequencecheck']);
        $this->assertEquals(2, $result['questions'][0]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);

        // Now, second slot.
        $prefix = $quba->get_field_prefix(2);
        $data = [
            ['name' => 'slots', 'value' => 2],
            ['name' => $prefix . ':sequencecheck',
                    'value' => $attemptobj->get_question_attempt(1)->get_sequence_check_count()],
            ['name' => $prefix . 'answer', 'value' => 1],
            ['name' => $prefix . ':flagged', 'value' => 1],
        ];

        $result = mod_realtimequiz_external::process_attempt($attempt->id, $data);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::process_attempt_returns(), $result);
        $this->assertEquals(realtimequiz_attempt::IN_PROGRESS, $result['state']);

        // Now, get the summary.
        $result = mod_realtimequiz_external::get_attempt_summary($attempt->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_summary_returns(), $result);

        // Check it's marked as completed the two first questions.
        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals('complete', $result['questions'][1]['state']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertTrue($result['questions'][1]['flagged']);

        // Add files in the attachment response.
        $draftitemid = file_get_unused_draft_itemid();
        $filerecordinline = [
            'contextid' => \context_user::instance($this->student->id)->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftitemid,
            'filepath'  => '/',
            'filename'  => 'faketxt.txt',
        ];
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecordinline, 'fake txt contents 1.');

        // Last slot.
        $prefix = $quba->get_field_prefix(3);
        $data = [
            ['name' => 'slots', 'value' => 3],
            ['name' => $prefix . ':sequencecheck',
                    'value' => $attemptobj->get_question_attempt(1)->get_sequence_check_count()],
            ['name' => $prefix . 'answer', 'value' => 'Some test'],
            ['name' => $prefix . 'answerformat', 'value' => FORMAT_HTML],
            ['name' => $prefix . 'attachments', 'value' => $draftitemid],
        ];

        $result = mod_realtimequiz_external::process_attempt($attempt->id, $data);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::process_attempt_returns(), $result);
        $this->assertEquals(realtimequiz_attempt::IN_PROGRESS, $result['state']);

        // Now, get the summary.
        $result = mod_realtimequiz_external::get_attempt_summary($attempt->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_summary_returns(), $result);

        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals('complete', $result['questions'][1]['state']);
        $this->assertEquals('complete', $result['questions'][2]['state']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertTrue($result['questions'][1]['flagged']);
        $this->assertFalse($result['questions'][2]['flagged']);

        // Check submitted files are there.
        $this->assertCount(1, $result['questions'][2]['responsefileareas']);
        $this->assertEquals('attachments', $result['questions'][2]['responsefileareas'][0]['area']);
        $this->assertCount(1, $result['questions'][2]['responsefileareas'][0]['files']);
        $this->assertEquals($filerecordinline['filename'], $result['questions'][2]['responsefileareas'][0]['files'][0]['filename']);

        // Finish the attempt.
        $sink = $this->redirectMessages();
        $result = mod_realtimequiz_external::process_attempt($attempt->id, [], true);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::process_attempt_returns(), $result);
        $this->assertEquals(realtimequiz_attempt::FINISHED, $result['state']);
        $messages = $sink->get_messages();
        $message = reset($messages);
        $sink->close();
        // Test customdata.
        if (!empty($message->customdata)) {
            $customdata = json_decode($message->customdata);
            $this->assertEquals($realtimequizobj->get_realtimequizid(), $customdata->instance);
            $this->assertEquals($realtimequizobj->get_cmid(), $customdata->cmid);
            $this->assertEquals($attempt->id, $customdata->attemptid);
            $this->assertObjectHasAttribute('notificationiconurl', $customdata);
        }

        // Start new attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 2, false, $timenow, false, $this->student->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 2, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Force grace period, attempt going to overdue.
        $realtimequiz->timeclose = $timenow - 10;
        $realtimequiz->graceperiod = 60;
        $realtimequiz->overduehandling = 'graceperiod';
        $DB->update_record('realtimequiz', $realtimequiz);

        $result = mod_realtimequiz_external::process_attempt($attempt->id, []);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::process_attempt_returns(), $result);
        $this->assertEquals(realtimequiz_attempt::OVERDUE, $result['state']);

        // Force grace period for time limit.
        $realtimequiz->timeclose = 0;
        $realtimequiz->timelimit = 1;
        $realtimequiz->graceperiod = 60;
        $realtimequiz->overduehandling = 'graceperiod';
        $DB->update_record('realtimequiz', $realtimequiz);

        $timenow = time();
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);
        $attempt = realtimequiz_create_attempt($realtimequizobj, 3, 2, $timenow - 10, false, $this->student->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 2, $timenow - 10);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        $result = mod_realtimequiz_external::process_attempt($attempt->id, []);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::process_attempt_returns(), $result);
        $this->assertEquals(realtimequiz_attempt::OVERDUE, $result['state']);

        // New attempt.
        $timenow = time();
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);
        $attempt = realtimequiz_create_attempt($realtimequizobj, 4, 3, $timenow, false, $this->student->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 3, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Force abandon.
        $realtimequiz->timeclose = $timenow - HOURSECS;
        $DB->update_record('realtimequiz', $realtimequiz);

        $result = mod_realtimequiz_external::process_attempt($attempt->id, []);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::process_attempt_returns(), $result);
        $this->assertEquals(realtimequiz_attempt::ABANDONED, $result['state']);

    }

    /**
     * Test validate_attempt_review
     */
    public function test_validate_attempt_review() {
        global $DB;

        // Create a new realtimequiz with one attempt started.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj) = $this->create_realtimequiz_with_questions(true);

        $this->setUser($this->student);

        // Invalid attempt, invalid id.
        try {
            $params = ['attemptid' => -1];
            testable_mod_realtimequiz_external::validate_attempt_review($params);
            $this->fail('Exception expected due invalid id.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Invalid attempt, not closed.
        try {
            $params = ['attemptid' => $attempt->id];
            testable_mod_realtimequiz_external::validate_attempt_review($params);
            $this->fail('Exception expected due not closed attempt.');
        } catch (moodle_exception $e) {
            $this->assertEquals('attemptclosed', $e->errorcode);
        }

        // Test ok case (finished attempt).
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj) = $this->create_realtimequiz_with_questions(true, true);

        $params = ['attemptid' => $attempt->id];
        testable_mod_realtimequiz_external::validate_attempt_review($params);

        // Teacher should be able to view the review of one student's attempt.
        $this->setUser($this->teacher);
        testable_mod_realtimequiz_external::validate_attempt_review($params);

        // We should not see other students attempts.
        $anotherstudent = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($anotherstudent->id, $this->course->id, $this->studentrole->id, 'manual');

        $this->setUser($anotherstudent);
        try {
            $params = ['attemptid' => $attempt->id];
            testable_mod_realtimequiz_external::validate_attempt_review($params);
            $this->fail('Exception expected due missing permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('noreviewattempt', $e->errorcode);
        }
    }


    /**
     * Test get_attempt_review
     */
    public function test_get_attempt_review() {
        global $DB;

        // Create a new realtimequiz with two questions and one attempt finished.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj, $quba) = $this->create_realtimequiz_with_questions(true, true);

        // Add feedback to the realtimequiz.
        $feedback = new \stdClass();
        $feedback->realtimequizid = $realtimequiz->id;
        $feedback->feedbacktext = 'Feedback text 1';
        $feedback->feedbacktextformat = 1;
        $feedback->mingrade = 49;
        $feedback->maxgrade = 100;
        $feedback->id = $DB->insert_record('realtimequiz_feedback', $feedback);

        $feedback->feedbacktext = 'Feedback text 2';
        $feedback->feedbacktextformat = 1;
        $feedback->mingrade = 30;
        $feedback->maxgrade = 48;
        $feedback->id = $DB->insert_record('realtimequiz_feedback', $feedback);

        $result = mod_realtimequiz_external::get_attempt_review($attempt->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_review_returns(), $result);

        // Two questions, one completed and correct, the other gave up.
        $this->assertEquals(50, $result['grade']);
        $this->assertEquals(1, $result['attempt']['attempt']);
        $this->assertEquals('finished', $result['attempt']['state']);
        $this->assertEquals(1, $result['attempt']['sumgrades']);
        $this->assertCount(2, $result['questions']);
        $this->assertEquals('gradedright', $result['questions'][0]['state']);
        $this->assertEquals(1, $result['questions'][0]['slot']);
        $this->assertEquals('gaveup', $result['questions'][1]['state']);
        $this->assertEquals(2, $result['questions'][1]['slot']);

        $this->assertCount(1, $result['additionaldata']);
        $this->assertEquals('feedback', $result['additionaldata'][0]['id']);
        $this->assertEquals('Feedback', $result['additionaldata'][0]['title']);
        $this->assertEquals('Feedback text 1', $result['additionaldata'][0]['content']);

        // Only first page.
        $result = mod_realtimequiz_external::get_attempt_review($attempt->id, 0);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_review_returns(), $result);

        $this->assertEquals(50, $result['grade']);
        $this->assertEquals(1, $result['attempt']['attempt']);
        $this->assertEquals('finished', $result['attempt']['state']);
        $this->assertEquals(1, $result['attempt']['sumgrades']);
        $this->assertCount(1, $result['questions']);
        $this->assertEquals('gradedright', $result['questions'][0]['state']);
        $this->assertEquals(1, $result['questions'][0]['slot']);

         $this->assertCount(1, $result['additionaldata']);
        $this->assertEquals('feedback', $result['additionaldata'][0]['id']);
        $this->assertEquals('Feedback', $result['additionaldata'][0]['title']);
        $this->assertEquals('Feedback text 1', $result['additionaldata'][0]['content']);

    }

    /**
     * Test test_view_attempt
     */
    public function test_view_attempt() {
        global $DB;

        // Create a new realtimequiz with two questions and one attempt started.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj, $quba) = $this->create_realtimequiz_with_questions(true, false);

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_realtimequiz_external::view_attempt($attempt->id, 0);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::view_attempt_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Now, force the realtimequiz with QUIZ_NAVMETHOD_SEQ (sequential) navigation method.
        $DB->set_field('realtimequiz', 'navmethod', QUIZ_NAVMETHOD_SEQ, ['id' => $realtimequiz->id]);
        // Quiz requiring preflightdata.
        $DB->set_field('realtimequiz', 'password', 'abcdef', ['id' => $realtimequiz->id]);
        $preflightdata = [["name" => "realtimequizpassword", "value" => 'abcdef']];

        // See next page.
        $result = mod_realtimequiz_external::view_attempt($attempt->id, 1, $preflightdata);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::view_attempt_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(2, $events);

        // Try to go to previous page.
        try {
            mod_realtimequiz_external::view_attempt($attempt->id, 0);
            $this->fail('Exception expected due to try to see a previous page.');
        } catch (moodle_exception $e) {
            $this->assertEquals('Out of sequence access', $e->errorcode);
        }

    }

    /**
     * Test test_view_attempt_summary
     */
    public function test_view_attempt_summary() {
        global $DB;

        // Create a new realtimequiz with two questions and one attempt started.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj, $quba) = $this->create_realtimequiz_with_questions(true, false);

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_realtimequiz_external::view_attempt_summary($attempt->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::view_attempt_summary_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_summary_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodlerealtimequiz = new \moodle_url('/mod/realtimequiz/summary.php', ['attempt' => $attempt->id]);
        $this->assertEquals($moodlerealtimequiz, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Quiz requiring preflightdata.
        $DB->set_field('realtimequiz', 'password', 'abcdef', ['id' => $realtimequiz->id]);
        $preflightdata = [["name" => "realtimequizpassword", "value" => 'abcdef']];

        $result = mod_realtimequiz_external::view_attempt_summary($attempt->id, $preflightdata);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::view_attempt_summary_returns(), $result);
        $this->assertTrue($result['status']);

    }

    /**
     * Test test_view_attempt_summary
     */
    public function test_view_attempt_review() {
        global $DB;

        // Create a new realtimequiz with two questions and one attempt finished.
        list($realtimequiz, $context, $realtimequizobj, $attempt, $attemptobj, $quba) = $this->create_realtimequiz_with_questions(true, true);

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_realtimequiz_external::view_attempt_review($attempt->id, 0);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::view_attempt_review_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_realtimequiz\event\attempt_reviewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodlerealtimequiz = new \moodle_url('/mod/realtimequiz/review.php', ['attempt' => $attempt->id]);
        $this->assertEquals($moodlerealtimequiz, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

    }

    /**
     * Test get_realtimequiz_feedback_for_grade
     */
    public function test_get_realtimequiz_feedback_for_grade() {
        global $DB;

        // Add feedback to the realtimequiz.
        $feedback = new \stdClass();
        $feedback->realtimequizid = $this->realtimequiz->id;
        $feedback->feedbacktext = 'Feedback text 1';
        $feedback->feedbacktextformat = 1;
        $feedback->mingrade = 49;
        $feedback->maxgrade = 100;
        $feedback->id = $DB->insert_record('realtimequiz_feedback', $feedback);
        // Add a fake inline image to the feedback text.
        $filename = 'shouldbeanimage.jpg';
        $filerecordinline = [
            'contextid' => $this->context->id,
            'component' => 'mod_realtimequiz',
            'filearea'  => 'feedback',
            'itemid'    => $feedback->id,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        $feedback->feedbacktext = 'Feedback text 2';
        $feedback->feedbacktextformat = 1;
        $feedback->mingrade = 30;
        $feedback->maxgrade = 49;
        $feedback->id = $DB->insert_record('realtimequiz_feedback', $feedback);

        $result = mod_realtimequiz_external::get_realtimequiz_feedback_for_grade($this->realtimequiz->id, 50);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_realtimequiz_feedback_for_grade_returns(), $result);
        $this->assertEquals('Feedback text 1', $result['feedbacktext']);
        $this->assertEquals($filename, $result['feedbackinlinefiles'][0]['filename']);
        $this->assertEquals(FORMAT_HTML, $result['feedbacktextformat']);

        $result = mod_realtimequiz_external::get_realtimequiz_feedback_for_grade($this->realtimequiz->id, 30);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_realtimequiz_feedback_for_grade_returns(), $result);
        $this->assertEquals('Feedback text 2', $result['feedbacktext']);
        $this->assertEquals(FORMAT_HTML, $result['feedbacktextformat']);

        $result = mod_realtimequiz_external::get_realtimequiz_feedback_for_grade($this->realtimequiz->id, 10);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_realtimequiz_feedback_for_grade_returns(), $result);
        $this->assertEquals('', $result['feedbacktext']);
        $this->assertEquals(FORMAT_MOODLE, $result['feedbacktextformat']);
    }

    /**
     * Test get_realtimequiz_access_information
     */
    public function test_get_realtimequiz_access_information() {
        global $DB;

        // Create a new realtimequiz.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $data = ['course' => $this->course->id];
        $realtimequiz = $realtimequizgenerator->create_instance($data);

        $this->setUser($this->student);

        // Default restrictions (none).
        $result = mod_realtimequiz_external::get_realtimequiz_access_information($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_realtimequiz_access_information_returns(), $result);

        $expected = [
            'canattempt' => true,
            'canmanage' => false,
            'canpreview' => false,
            'canreviewmyattempts' => true,
            'canviewreports' => false,
            'accessrules' => [],
            // This rule is always used, even if the realtimequiz has no open or close date.
            'activerulenames' => ['realtimequizaccess_openclosedate'],
            'preventaccessreasons' => [],
            'warnings' => []
        ];

        $this->assertEquals($expected, $result);

        // Now teacher, different privileges.
        $this->setUser($this->teacher);
        $result = mod_realtimequiz_external::get_realtimequiz_access_information($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_realtimequiz_access_information_returns(), $result);

        $expected['canmanage'] = true;
        $expected['canpreview'] = true;
        $expected['canviewreports'] = true;
        $expected['canattempt'] = false;
        $expected['canreviewmyattempts'] = false;

        $this->assertEquals($expected, $result);

        $this->setUser($this->student);
        // Now add some restrictions.
        $realtimequiz->timeopen = time() + DAYSECS;
        $realtimequiz->timeclose = time() + WEEKSECS;
        $realtimequiz->password = '123456';
        $DB->update_record('realtimequiz', $realtimequiz);

        $result = mod_realtimequiz_external::get_realtimequiz_access_information($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_realtimequiz_access_information_returns(), $result);

        // Access is limited by time and password, but only the password limit has a description.
        $this->assertCount(1, $result['accessrules']);
        // Two rule names, password and open/close date.
        $this->assertCount(2, $result['activerulenames']);
        $this->assertCount(1, $result['preventaccessreasons']);

    }

    /**
     * Test get_attempt_access_information
     */
    public function test_get_attempt_access_information() {
        global $DB;

        $this->setAdminUser();

        // Create a new realtimequiz with attempts.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $data = ['course' => $this->course->id,
                      'sumgrades' => 2];
        $realtimequiz = $realtimequizgenerator->create_instance($data);

        // Create some questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        // Add new question types in the category (for the random one).
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        $question = $questiongenerator->create_question('essay', null, ['category' => $cat->id]);

        realtimequiz_add_random_questions($realtimequiz, 0, $cat->id, 1, false);

        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $this->student->id);

        // Set grade to pass.
        $item = \grade_item::fetch(['courseid' => $this->course->id, 'itemtype' => 'mod',
                                        'itemmodule' => 'realtimequiz', 'iteminstance' => $realtimequiz->id, 'outcomeid' => null]);
        $item->gradepass = 80;
        $item->update();

        $this->setUser($this->student);

        // Default restrictions (none).
        $result = mod_realtimequiz_external::get_attempt_access_information($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_access_information_returns(), $result);

        $expected = [
            'isfinished' => false,
            'preventnewattemptreasons' => [],
            'warnings' => []
        ];

        $this->assertEquals($expected, $result);

        // Limited attempts.
        $realtimequiz->attempts = 1;
        $DB->update_record('realtimequiz', $realtimequiz);

        // Now, do one attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);

        $timenow = time();
        $attempt = realtimequiz_create_attempt($realtimequizobj, 1, false, $timenow, false, $this->student->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, 1, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $tosubmit = [1 => ['answer' => '3.14']];
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Can we start a new attempt? We shall not!
        $result = mod_realtimequiz_external::get_attempt_access_information($realtimequiz->id, $attempt->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_attempt_access_information_returns(), $result);

        // Now new attemps allowed.
        $this->assertCount(1, $result['preventnewattemptreasons']);
        $this->assertFalse($result['ispreflightcheckrequired']);
        $this->assertEquals(get_string('nomoreattempts', 'realtimequiz'), $result['preventnewattemptreasons'][0]);

    }

    /**
     * Test get_realtimequiz_required_qtypes
     */
    public function test_get_realtimequiz_required_qtypes() {
        $this->setAdminUser();

        // Create a new realtimequiz.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $data = ['course' => $this->course->id];
        $realtimequiz = $realtimequizgenerator->create_instance($data);

        // Create some questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        $question = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        $question = $questiongenerator->create_question('essay', null, ['category' => $cat->id]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        $question = $questiongenerator->create_question('multichoice', null,
                ['category' => $cat->id, 'status' => question_version_status::QUESTION_STATUS_DRAFT]);
        realtimequiz_add_realtimequiz_question($question->id, $realtimequiz);

        $this->setUser($this->student);

        $result = mod_realtimequiz_external::get_realtimequiz_required_qtypes($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_realtimequiz_required_qtypes_returns(), $result);

        $expected = [
            'questiontypes' => ['essay', 'numerical', 'shortanswer', 'truefalse'],
            'warnings' => []
        ];

        $this->assertEquals($expected, $result);

    }

    /**
     * Test get_realtimequiz_required_qtypes for realtimequiz with random questions
     */
    public function test_get_realtimequiz_required_qtypes_random() {
        $this->setAdminUser();

        // Create a new realtimequiz.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $realtimequiz = $realtimequizgenerator->create_instance(['course' => $this->course->id]);

        // Create some questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $anothercat = $questiongenerator->create_question_category();

        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        // Question in a different category.
        $question = $questiongenerator->create_question('essay', null, ['category' => $anothercat->id]);

        // Add a couple of random questions from the same category.
        realtimequiz_add_random_questions($realtimequiz, 0, $cat->id, 1, false);
        realtimequiz_add_random_questions($realtimequiz, 0, $cat->id, 1, false);

        $this->setUser($this->student);

        $result = mod_realtimequiz_external::get_realtimequiz_required_qtypes($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_realtimequiz_required_qtypes_returns(), $result);

        $expected = ['numerical', 'shortanswer', 'truefalse'];
        ksort($result['questiontypes']);

        $this->assertEquals($expected, $result['questiontypes']);

        // Add more questions to the realtimequiz, this time from the other category.
        $this->setAdminUser();
        realtimequiz_add_random_questions($realtimequiz, 0, $anothercat->id, 1, false);

        $this->setUser($this->student);
        $result = mod_realtimequiz_external::get_realtimequiz_required_qtypes($realtimequiz->id);
        $result = external_api::clean_returnvalue(mod_realtimequiz_external::get_realtimequiz_required_qtypes_returns(), $result);

        // The new question from the new category is returned as a potential random question for the realtimequiz.
        $expected = ['essay', 'numerical', 'shortanswer', 'truefalse'];
        ksort($result['questiontypes']);

        $this->assertEquals($expected, $result['questiontypes']);
    }

    /**
     * Test that a sequential navigation realtimequiz is not allowing to see questions in advance except if reviewing
     */
    public function test_sequential_navigation_view_attempt() {
        // Test user with full capabilities.
        $realtimequiz = $this->prepare_sequential_realtimequiz();
        $attemptobj = $this->create_realtimequiz_attempt_object($realtimequiz);
        $this->setUser($this->student);
        // Check out of sequence access for view.
        $this->assertNotEmpty(mod_realtimequiz_external::view_attempt($attemptobj->get_attemptid(), 0, []));
        try {
            mod_realtimequiz_external::view_attempt($attemptobj->get_attemptid(), 3, []);
            $this->fail('Exception expected due to out of sequence access.');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('realtimequiz/Out of sequence access', $e->getMessage());
        }
    }

    /**
     * Test that a sequential navigation realtimequiz is not allowing to see questions in advance for a student
     */
    public function test_sequential_navigation_attempt_summary() {
        // Test user with full capabilities.
        $realtimequiz = $this->prepare_sequential_realtimequiz();
        $attemptobj = $this->create_realtimequiz_attempt_object($realtimequiz);
        $this->setUser($this->student);
        // Check that we do not return other questions than the one currently viewed.
        $result = mod_realtimequiz_external::get_attempt_summary($attemptobj->get_attemptid());
        $this->assertCount(1, $result['questions']);
        $this->assertStringContainsString('Question (1)', $result['questions'][0]['html']);
    }

    /**
     * Test that a sequential navigation realtimequiz is not allowing to see questions in advance for student
     */
    public function test_sequential_navigation_get_attempt_data() {
        // Test user with full capabilities.
        $realtimequiz = $this->prepare_sequential_realtimequiz();
        $attemptobj = $this->create_realtimequiz_attempt_object($realtimequiz);
        $this->setUser($this->student);
        // Test invalid instance id.
        try {
            mod_realtimequiz_external::get_attempt_data($attemptobj->get_attemptid(), 2);
            $this->fail('Exception expected due to out of sequence access.');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('realtimequiz/Out of sequence access', $e->getMessage());
        }
        // Now we moved to page 1, we should see page 2 and 1 but not 0 or 3.
        $attemptobj->set_currentpage(1);
        // Test invalid instance id.
        try {
            mod_realtimequiz_external::get_attempt_data($attemptobj->get_attemptid(), 0);
            $this->fail('Exception expected due to out of sequence access.');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('realtimequiz/Out of sequence access', $e->getMessage());
        }

        try {
            mod_realtimequiz_external::get_attempt_data($attemptobj->get_attemptid(), 3);
            $this->fail('Exception expected due to out of sequence access.');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('realtimequiz/Out of sequence access', $e->getMessage());
        }

        // Now we can see page 1.
        $result = mod_realtimequiz_external::get_attempt_data($attemptobj->get_attemptid(), 1);
        $this->assertCount(1, $result['questions']);
        $this->assertStringContainsString('Question (2)', $result['questions'][0]['html']);
    }

    /**
     * Prepare realtimequiz for sequential navigation tests
     *
     * @return realtimequiz_settings
     */
    private function prepare_sequential_realtimequiz(): realtimequiz_settings {
        // Create a new realtimequiz with 5 questions and one attempt started.
        // Create a new realtimequiz with attempts.
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');
        $data = [
            'course' => $this->course->id,
            'sumgrades' => 2,
            'preferredbehaviour' => 'deferredfeedback',
            'navmethod' => QUIZ_NAVMETHOD_SEQ
        ];
        $realtimequiz = $realtimequizgenerator->create_instance($data);

        // Now generate the questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        for ($pageindex = 1; $pageindex <= 5; $pageindex++) {
            $question = $questiongenerator->create_question('truefalse', null, [
                'category' => $cat->id,
                'questiontext' => ['text' => "Question ($pageindex)"]
            ]);
            realtimequiz_add_realtimequiz_question($question->id, $realtimequiz, $pageindex);
        }

        $realtimequizobj = realtimequiz_settings::create($realtimequiz->id, $this->student->id);
        // Set grade to pass.
        $item = \grade_item::fetch(['courseid' => $this->course->id, 'itemtype' => 'mod',
            'itemmodule' => 'realtimequiz', 'iteminstance' => $realtimequiz->id, 'outcomeid' => null]);
        $item->gradepass = 80;
        $item->update();
        return $realtimequizobj;
    }

    /**
     * Create question attempt
     *
     * @param realtimequiz_settings $realtimequizobj
     * @param int|null $userid
     * @param bool|null $ispreview
     * @return realtimequiz_attempt
     * @throws moodle_exception
     */
    private function create_realtimequiz_attempt_object(
        realtimequiz_settings $realtimequizobj,
        ?int $userid = null,
        ?bool $ispreview = false
    ): realtimequiz_attempt {
        global $USER;

        $timenow = time();
        // Now, do one attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_realtimequiz', $realtimequizobj->get_context());
        $quba->set_preferred_behaviour($realtimequizobj->get_realtimequiz()->preferredbehaviour);
        $attemptnumber = 1;
        if (!empty($USER->id)) {
            $attemptnumber = count(realtimequiz_get_user_attempts($realtimequizobj->get_realtimequizid(), $USER->id)) + 1;
        }
        $attempt = realtimequiz_create_attempt($realtimequizobj, $attemptnumber, false, $timenow, $ispreview, $userid ?? $this->student->id);
        realtimequiz_start_new_attempt($realtimequizobj, $quba, $attempt, $attemptnumber, $timenow);
        realtimequiz_attempt_save_started($realtimequizobj, $quba, $attempt);
        $attemptobj = realtimequiz_attempt::create($attempt->id);
        return $attemptobj;
    }
}
