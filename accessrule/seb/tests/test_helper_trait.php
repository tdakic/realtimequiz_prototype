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
 * A test helper trait.
 *
 * @package    realtimequizaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\local\access_rule_base;
use mod_realtimequiz\realtimequiz_attempt;
use realtimequizaccess_seb\seb_access_manager;
use realtimequizaccess_seb\settings_provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . "/mod/realtimequiz/accessrule/seb/rule.php"); // Include plugin rule class.
require_once($CFG->dirroot . "/mod/realtimequiz/mod_form.php"); // Include plugin rule class.

/**
 * A test helper trait. It has some common helper methods.
 *
 * @copyright  2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait realtimequizaccess_seb_test_helper_trait {

    /** @var \stdClass $course Test course to contain realtimequiz. */
    protected $course;

    /** @var \stdClass $realtimequiz A test realtimequiz. */
    protected $realtimequiz;

    /** @var \stdClass $user A test logged-in user. */
    protected $user;

    /**
     * Assign a capability to $USER
     * The function creates a student $USER if $USER->id is empty
     *
     * @param string $capability Capability name.
     * @param int $contextid Context ID.
     * @param int $roleid Role ID.
     * @return int The role id - mainly returned for creation, so calling function can reuse it.
     */
    protected function assign_user_capability($capability, $contextid, $roleid = null) {
        global $USER;

        // Create a new student $USER if $USER doesn't exist.
        if (empty($USER->id)) {
            $user = $this->getDataGenerator()->create_user();
            $this->setUser($user);
        }

        if (empty($roleid)) {
            $roleid = \create_role('Dummy role', 'dummyrole', 'dummy role description');
        }

        \assign_capability($capability, CAP_ALLOW, $roleid, $contextid);

        \role_assign($roleid, $USER->id, $contextid);

        \accesslib_clear_all_caches_for_unit_testing();

        return $roleid;
    }

    /**
     * Strip the seb_ prefix from each setting key.
     *
     * @param \stdClass $settings Object containing settings.
     * @return \stdClass The modified settings object.
     */
    protected function strip_all_prefixes(\stdClass $settings) : \stdClass {
        $newsettings = new \stdClass();
        foreach ($settings as $name => $setting) {
            $newname = preg_replace("/^seb_/", "", $name);
            $newsettings->$newname = $setting; // Add new key.
        }
        return $newsettings;
    }

    /**
     * Creates a file in the user draft area.
     *
     * @param string $xml
     * @return int The user draftarea id
     */
    protected function create_test_draftarea_file(string $xml) : int {
        global $USER;

        $itemid = 0;
        $usercontext = \context_user::instance($USER->id);
        $filerecord = [
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => 'test.xml'
        ];

        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord, $xml);

        $draftitemid = 0;
        file_prepare_draft_area($draftitemid, $usercontext->id, 'user', 'draft', 0);

        return $draftitemid;
    }

    /**
     * Create a file in a modules filearea.
     *
     * @param string $xml XML content of the file.
     * @param string $cmid Course module id.
     * @return int Item ID of file.
     */
    protected function create_module_test_file(string $xml, string $cmid) : int {
        $itemid = 0;
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => \context_module::instance($cmid)->id,
            'component' => 'realtimequizaccess_seb',
            'filearea' => 'filemanager_sebconfigfile',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => 'test.xml'
        ];
        $fs->create_file_from_string($filerecord, $xml);
        return $itemid;
    }

    /**
     * Create a test realtimequiz for the specified course.
     *
     * @param \stdClass $course
     * @param int $requiresafeexambrowser How to use SEB for this realtimequiz?
     * @return  array
     */
    protected function create_test_realtimequiz($course, $requiresafeexambrowser = settings_provider::USE_SEB_NO) {
        $realtimequizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_realtimequiz');

        $realtimequiz = $realtimequizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 100.0,
            'sumgrades' => 2,
            'seb_requiresafeexambrowser' => $requiresafeexambrowser,
        ]);
        $realtimequiz->seb_showsebdownloadlink = 1;
        $realtimequiz->coursemodule = $realtimequiz->cmid;

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
     * @param \stdClass $realtimequiz Quiz to attempt.
     * @param \stdClass $user A user to attempt the realtimequiz.
     * @return  array
     */
    protected function attempt_realtimequiz($realtimequiz, $user) {
        $this->setUser($user);

        $starttime = time();
        $realtimequizobj = mod_realtimequiz\realtimequiz_settings::create($realtimequiz->id, $user->id);

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
     * Create test template.
     *
     * @param string|null $xml Template content.
     * @return \realtimequizaccess_seb\template Just created template.
     */
    public function create_template(string $xml = null) {
        $data = [];

        if (!is_null($xml)) {
            $data['content'] = $xml;
        }

        return $this->getDataGenerator()->get_plugin_generator('realtimequizaccess_seb')->create_template($data);
    }

    /**
     * Get access manager for testing.
     *
     * @return \realtimequizaccess_seb\seb_access_manager
     */
    protected function get_access_manager() {
        return new seb_access_manager(new mod_realtimequiz\realtimequiz_settings($this->realtimequiz,
            get_coursemodule_from_id('realtimequiz', $this->realtimequiz->cmid), $this->course));
    }

    /**
     * A helper method to make the rule form the currently created realtimequiz and  course.
     *
     * @return access_rule_base|null
     */
    protected function make_rule() {
        return \realtimequizaccess_seb::make(
            new mod_realtimequiz\realtimequiz_settings($this->realtimequiz, get_coursemodule_from_id('realtimequiz', $this->realtimequiz->cmid), $this->course),
            0,
            true
        );
    }

    /**
     * A helper method to set up realtimequiz view page.
     */
    protected function set_up_realtimequiz_view_page() {
        global $PAGE;

        $page = new \moodle_page();
        $page->set_context(\context_module::instance($this->realtimequiz->cmid));
        $page->set_course($this->course);
        $page->set_pagelayout('standard');
        $page->set_pagetype("mod-realtimequiz-view");
        $page->set_url('/mod/realtimequiz/view.php?id=' . $this->realtimequiz->cmid);

        $PAGE = $page;
    }

    /**
     * Get a test object containing mock test settings.
     *
     * @return \stdClass Settings.
     */
    protected function get_test_settings(array $settings = []) : \stdClass {
        return (object) array_merge([
            'realtimequizid' => 1,
            'cmid' => 1,
            'requiresafeexambrowser' => '1',
            'showsebtaskbar' => '1',
            'showwificontrol' => '0',
            'showreloadbutton' => '1',
            'showtime' => '0',
            'showkeyboardlayout' => '1',
            'allowuserquitseb' => '1',
            'quitpassword' => 'test',
            'linkquitseb' => '',
            'userconfirmquit' => '1',
            'enableaudiocontrol' => '1',
            'muteonstartup' => '0',
            'allowspellchecking' => '0',
            'allowreloadinexam' => '1',
            'activateurlfiltering' => '1',
            'filterembeddedcontent' => '0',
            'expressionsallowed' => 'test.com',
            'regexallowed' => '',
            'expressionsblocked' => '',
            'regexblocked' => '',
            'showsebdownloadlink' => '1',
        ], $settings);
    }

}
