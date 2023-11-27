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

namespace realtimequizaccess_seb;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/test_helper_trait.php');

/**
 * PHPUnit tests for backup and restore functionality.
 *
 * @package   realtimequizaccess_seb
 * @author    Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright 2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_restore_test extends \advanced_testcase {
    use \realtimequizaccess_seb_test_helper_trait;


    /** @var template $template A test template. */
    protected $template;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        global $USER;

        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->template = $this->create_template();
        $this->user = $USER;
    }

    /**
     * A helper method to create a realtimequiz with template usage of SEB.
     *
     * @return seb_realtimequiz_settings
     */
    protected function create_realtimequiz_with_template() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $realtimequizsettings->set('templateid', $this->template->get('id'));
        $realtimequizsettings->save();

        return $realtimequizsettings;
    }

    /**
     * A helper method to emulate backup and restore of the realtimequiz.
     *
     * @return \cm_info|null
     */
    protected function backup_and_restore_realtimequiz() {
        return duplicate_module($this->course, get_fast_modinfo($this->course)->get_cm($this->realtimequiz->cmid));
    }

    /**
     * A helper method to backup test realtimequiz.
     *
     * @return mixed A backup ID ready to be restored.
     */
    protected function backup_realtimequiz() {
        global $CFG;

        // Get the necessary files to perform backup and restore.
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $backupid = 'test-seb-backup-restore';

        $bc = new \backup_controller(\backup::TYPE_1ACTIVITY, $this->realtimequiz->coursemodule, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $this->user->id);
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
     */
    protected function restore_realtimequiz($backupid) {
        $rc = new \restore_controller($backupid, $this->course->id,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $this->user->id, \backup::TARGET_CURRENT_ADDING);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * A helper method to emulate restoring to a different site.
     */
    protected function change_site() {
        set_config('siteidentifier', random_string(32) . 'not the same site');
    }

    /**
     * A helper method to validate backup and restore results.
     *
     * @param cm_info $newcm Restored course_module object.
     */
    protected function validate_backup_restore(\cm_info $newcm) {
        $this->assertEquals(2, seb_realtimequiz_settings::count_records());
        $actual = seb_realtimequiz_settings::get_record(['realtimequizid' => $newcm->instance]);

        $expected = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertEquals($expected->get('templateid'), $actual->get('templateid'));
        $this->assertEquals($expected->get('requiresafeexambrowser'), $actual->get('requiresafeexambrowser'));
        $this->assertEquals($expected->get('showsebdownloadlink'), $actual->get('showsebdownloadlink'));
        $this->assertEquals($expected->get('allowuserquitseb'), $actual->get('allowuserquitseb'));
        $this->assertEquals($expected->get('quitpassword'), $actual->get('quitpassword'));
        $this->assertEquals($expected->get('allowedbrowserexamkeys'), $actual->get('allowedbrowserexamkeys'));

        // Validate specific SEB config settings.
        foreach (settings_provider::get_seb_config_elements() as $name => $notused) {
            $name = preg_replace("/^seb_/", "", $name);
            $this->assertEquals($expected->get($name), $actual->get($name));
        }
    }

    /**
     * Test backup and restore when no seb.
     */
    public function test_backup_restore_no_seb() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_NO);
        $this->assertEquals(0, seb_realtimequiz_settings::count_records());

        $this->backup_and_restore_realtimequiz();
        $this->assertEquals(0, seb_realtimequiz_settings::count_records());
    }

    /**
     * Test backup and restore when manually configured.
     */
    public function test_backup_restore_manual_config() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $expected = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $expected->set('showsebdownloadlink', 0);
        $expected->set('quitpassword', '123');
        $expected->save();

        $this->assertEquals(1, seb_realtimequiz_settings::count_records());

        $newcm = $this->backup_and_restore_realtimequiz();
        $this->validate_backup_restore($newcm);
    }

    /**
     * Test backup and restore when using template.
     */
    public function test_backup_restore_template_config() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $expected = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $template = $this->create_template();
        $expected->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $expected->set('templateid', $template->get('id'));
        $expected->save();

        $this->assertEquals(1, seb_realtimequiz_settings::count_records());

        $newcm = $this->backup_and_restore_realtimequiz();
        $this->validate_backup_restore($newcm);
    }

    /**
     * Test backup and restore when using uploaded file.
     */
    public function test_backup_restore_uploaded_config() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $expected = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $expected->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->realtimequiz->cmid);
        $expected->save();

        $this->assertEquals(1, seb_realtimequiz_settings::count_records());

        $newcm = $this->backup_and_restore_realtimequiz();
        $this->validate_backup_restore($newcm);

        $expectedfile = settings_provider::get_module_context_sebconfig_file($this->realtimequiz->cmid);
        $actualfile = settings_provider::get_module_context_sebconfig_file($newcm->id);

        $this->assertEquals($expectedfile->get_content(), $actualfile->get_content());
    }

    /**
     * No new template should be restored if restoring to a different site,
     * but the template with  the same name and content exists..
     */
    public function test_restore_template_to_a_different_site_when_the_same_template_exists() {
        $this->create_realtimequiz_with_template();
        $backupid = $this->backup_realtimequiz();

        $this->assertEquals(1, seb_realtimequiz_settings::count_records());
        $this->assertEquals(1, template::count_records());

        $this->change_site();
        $this->restore_realtimequiz($backupid);

        // Should see additional setting record, but no new template record.
        $this->assertEquals(2, seb_realtimequiz_settings::count_records());
        $this->assertEquals(1, template::count_records());
    }

    /**
     * A new template should be restored if restoring to a different site, but existing template
     * has the same content, but different name.
     */
    public function test_restore_template_to_a_different_site_when_the_same_content_but_different_name() {
        $this->create_realtimequiz_with_template();
        $backupid = $this->backup_realtimequiz();

        $this->assertEquals(1, seb_realtimequiz_settings::count_records());
        $this->assertEquals(1, template::count_records());

        $this->template->set('name', 'New name for template');
        $this->template->save();

        $this->change_site();
        $this->restore_realtimequiz($backupid);

        // Should see additional setting record, and new template record.
        $this->assertEquals(2, seb_realtimequiz_settings::count_records());
        $this->assertEquals(2, template::count_records());
    }

    /**
     * A new template should be restored if restoring to a different site, but existing template
     * has the same name, but different content.
     */
    public function test_restore_template_to_a_different_site_when_the_same_name_but_different_content() {
        global $CFG;

        $this->create_realtimequiz_with_template();
        $backupid = $this->backup_realtimequiz();

        $this->assertEquals(1, seb_realtimequiz_settings::count_records());
        $this->assertEquals(1, template::count_records());

        $newxml = file_get_contents($CFG->dirroot . '/mod/realtimequiz/accessrule/seb/tests/fixtures/simpleunencrypted.seb');
        $this->template->set('content', $newxml);
        $this->template->save();

        $this->change_site();
        $this->restore_realtimequiz($backupid);

        // Should see additional setting record, and new template record.
        $this->assertEquals(2, seb_realtimequiz_settings::count_records());
        $this->assertEquals(2, template::count_records());
    }

}
