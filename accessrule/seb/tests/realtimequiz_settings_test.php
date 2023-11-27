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

use context_module;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/test_helper_trait.php');

/**
 * PHPUnit tests for seb_realtimequiz_settings class.
 *
 * @package   realtimequizaccess_seb
 * @author    Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright 2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtimequiz_settings_test extends \advanced_testcase {
    use \realtimequizaccess_seb_test_helper_trait;

    /** @var context_module $context Test context. */
    protected $context;

    /** @var moodle_url $url Test realtimequiz URL. */
    protected $url;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->realtimequiz = $this->getDataGenerator()->create_module('realtimequiz', [
            'course' => $this->course->id,
            'seb_requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
        ]);
        $this->context = \context_module::instance($this->realtimequiz->cmid);
        $this->url = new \moodle_url("/mod/realtimequiz/view.php", ['id' => $this->realtimequiz->cmid]);
    }

    /**
     * Test that config is generated immediately prior to saving realtimequiz settings.
     */
    public function test_config_is_created_from_realtimequiz_settings() {
        // Test settings to populate the in the object.
        $settings = $this->get_test_settings([
            'realtimequizid' => $this->realtimequiz->id,
            'cmid' => $this->realtimequiz->cmid,
        ]);

        // Obtain the existing record that is created when using a generator.
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);

        // Update the settings with values from the test function.
        $realtimequizsettings->from_record($settings);
        $realtimequizsettings->save();

        $config = $realtimequizsettings->get_config();
        $this->assertEquals(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\"><dict><key>showTaskBar</key><true/><key>allowWlan</key><false/><key>showReloadButton</key><true/>"
                . "<key>showTime</key><false/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><true/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><true/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>hashedQuitPassword</key>"
                . "<string>9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08</string><key>URLFilterRules</key>"
                . "<array><dict><key>action</key><integer>1</integer><key>active</key><true/><key>expression</key>"
                . "<string>test.com</string><key>regex</key><false/></dict></array><key>startURL</key><string>$this->url</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/><key>allowPreferencesWindow</key><false/></dict></plist>\n",
            $config);
    }

    /**
     * Test that config string gets updated from realtimequiz settings.
     */
    public function test_config_is_updated_from_realtimequiz_settings() {
        // Test settings to populate the in the object.
        $settings = $this->get_test_settings([
            'realtimequizid' => $this->realtimequiz->id,
            'cmid' => $this->realtimequiz->cmid,
        ]);

        // Obtain the existing record that is created when using a generator.
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);

        // Update the settings with values from the test function.
        $realtimequizsettings->from_record($settings);
        $realtimequizsettings->save();

        $config = $realtimequizsettings->get_config();
        $this->assertEquals("<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\"><dict><key>showTaskBar</key><true/><key>allowWlan</key><false/><key>showReloadButton</key><true/>"
            . "<key>showTime</key><false/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
            . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><true/><key>audioMute</key><false/>"
            . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><true/>"
            . "<key>URLFilterEnableContentFilter</key><false/><key>hashedQuitPassword</key>"
            . "<string>9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08</string><key>URLFilterRules</key>"
            . "<array><dict><key>action</key><integer>1</integer><key>active</key><true/><key>expression</key>"
            . "<string>test.com</string><key>regex</key><false/></dict></array><key>startURL</key><string>$this->url</string>"
            . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
            . "<key>examSessionClearCookiesOnStart</key><false/>"
            . "<key>allowPreferencesWindow</key><false/></dict></plist>\n", $config);

        $realtimequizsettings->set('filterembeddedcontent', 1); // Alter the settings.
        $realtimequizsettings->save();
        $config = $realtimequizsettings->get_config();
        $this->assertEquals("<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\"><dict><key>showTaskBar</key><true/><key>allowWlan</key><false/><key>showReloadButton</key><true/>"
            . "<key>showTime</key><false/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
            . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><true/><key>audioMute</key><false/>"
            . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><true/>"
            . "<key>URLFilterEnableContentFilter</key><true/><key>hashedQuitPassword</key>"
            . "<string>9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08</string><key>URLFilterRules</key>"
            . "<array><dict><key>action</key><integer>1</integer><key>active</key><true/><key>expression</key>"
            . "<string>test.com</string><key>regex</key><false/></dict></array><key>startURL</key><string>$this->url</string>"
            . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
            . "<key>examSessionClearCookiesOnStart</key><false/>"
            . "<key>allowPreferencesWindow</key><false/></dict></plist>\n", $config);
    }

    /**
     * Test that config key is generated immediately prior to saving realtimequiz settings.
     */
    public function test_config_key_is_created_from_realtimequiz_settings() {
        $settings = $this->get_test_settings();

        $realtimequizsettings = new seb_realtimequiz_settings(0, $settings);
        $configkey = $realtimequizsettings->get_config_key();
        $this->assertEquals("65ff7a3b8aec80e58fbe2e7968826c33cbf0ac444a748055ebe665829cbf4201",
            $configkey
        );
    }

    /**
     * Test that config key is generated immediately prior to saving realtimequiz settings.
     */
    public function test_config_key_is_updated_from_realtimequiz_settings() {
        $settings = $this->get_test_settings();

        $realtimequizsettings = new seb_realtimequiz_settings(0, $settings);
        $configkey = $realtimequizsettings->get_config_key();
        $this->assertEquals("65ff7a3b8aec80e58fbe2e7968826c33cbf0ac444a748055ebe665829cbf4201",
                $configkey);

        $realtimequizsettings->set('filterembeddedcontent', 1); // Alter the settings.
        $configkey = $realtimequizsettings->get_config_key();
        $this->assertEquals("d975b8a2ec4472495a8be7c64d7c8cc960dbb62472d5e88a8847ac0e5d77e533",
            $configkey);
    }

    /**
     * Test that different URL filter expressions are turned into config XML.
     *
     * @param \stdClass $settings Quiz settings
     * @param string $expectedxml SEB Config XML.
     *
     * @dataProvider filter_rules_provider
     */
    public function test_filter_rules_added_to_config(\stdClass $settings, string $expectedxml) {
        $realtimequizsettings = new seb_realtimequiz_settings(0, $settings);
        $config = $realtimequizsettings->get_config();
        $this->assertEquals($expectedxml, $config);
    }

    /**
     * Test that browser keys are validated and retrieved as an array instead of string.
     */
    public function test_browser_exam_keys_are_retrieved_as_array() {
        $realtimequizsettings = new seb_realtimequiz_settings();
        $realtimequizsettings->set('allowedbrowserexamkeys', "one two,three\nfour");
        $retrievedkeys = $realtimequizsettings->get('allowedbrowserexamkeys');
        $this->assertEquals(['one', 'two', 'three', 'four'], $retrievedkeys);
    }

    /**
     * Test validation of Browser Exam Keys.
     *
     * @param string $bek Browser Exam Key.
     * @param string $expectederrorstring Expected error.
     *
     * @dataProvider bad_browser_exam_key_provider
     */
    public function test_browser_exam_keys_validation_errors($bek, $expectederrorstring) {
        $realtimequizsettings = new seb_realtimequiz_settings();
        $realtimequizsettings->set('allowedbrowserexamkeys', $bek);
        $realtimequizsettings->validate();
        $errors = $realtimequizsettings->get_errors();
        $this->assertContainsEquals($expectederrorstring, $errors);
    }

    /**
     * Test that uploaded seb file gets converted to config string.
     */
    public function test_config_file_uploaded_converted_to_config() {
        $url = new \moodle_url("/mod/realtimequiz/view.php", ['id' => $this->realtimequiz->cmid]);
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>hashedQuitPassword</key><string>hashedpassword</string>"
                . "<key>allowWlan</key><false/><key>startURL</key><string>$url</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer></dict></plist>\n";
        $itemid = $this->create_module_test_file($xml, $this->realtimequiz->cmid);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $realtimequizsettings->save();
        $config = $realtimequizsettings->get_config();
        $this->assertEquals($xml, $config);
    }

    /**
     * Test test_no_config_file_uploaded
     */
    public function test_no_config_file_uploaded() {
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $cmid = $realtimequizsettings->get('cmid');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage("No uploaded SEB config file could be found for realtimequiz with cmid: {$cmid}");
        $realtimequizsettings->get_config();
    }

    /**
     * A helper function to build a config file.
     *
     * @param mixed $allowuserquitseb Required allowQuit setting.
     * @param mixed $quitpassword Required hashedQuitPassword setting.
     *
     * @return string
     */
    protected function get_config_xml($allowuserquitseb = null, $quitpassword = null) {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
            . "<plist version=\"1.0\"><dict><key>allowWlan</key><false/><key>startURL</key>"
            . "<string>https://safeexambrowser.org/start</string>"
            . "<key>sendBrowserExamKey</key><true/>";

        if (!is_null($allowuserquitseb)) {
            $allowuserquitseb = empty($allowuserquitseb) ? 'false' : 'true';
            $xml .= "<key>allowQuit</key><{$allowuserquitseb}/>";
        }

        if (!is_null($quitpassword)) {
            $xml .= "<key>hashedQuitPassword</key><string>{$quitpassword}</string>";
        }

        $xml .= "</dict></plist>\n";

        return $xml;
    }

    /**
     * Test using USE_SEB_TEMPLATE and have it override settings from the template when they are set.
     */
    public function test_using_seb_template_override_settings_when_they_set_in_template() {
        $xml = $this->get_config_xml(true, 'password');
        $template = $this->create_template($xml);

        $this->assertStringContainsString("<key>startURL</key><string>https://safeexambrowser.org/start</string>", $template->get('content'));
        $this->assertStringContainsString("<key>allowQuit</key><true/>", $template->get('content'));
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>password</string>", $template->get('content'));

        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $realtimequizsettings->set('templateid', $template->get('id'));
        $realtimequizsettings->set('allowuserquitseb', 1);
        $realtimequizsettings->save();

        $this->assertStringContainsString(
            "<key>startURL</key><string>https://www.example.com/moodle/mod/realtimequiz/view.php?id={$this->realtimequiz->cmid}</string>",
            $realtimequizsettings->get_config()
        );

        $this->assertStringContainsString("<key>allowQuit</key><true/>", $realtimequizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $realtimequizsettings->get_config());

        $realtimequizsettings->set('quitpassword', 'new password');
        $realtimequizsettings->save();
        $hashedpassword = hash('SHA256', 'new password');
        $this->assertStringContainsString("<key>allowQuit</key><true/>", $realtimequizsettings->get_config());
        $this->assertStringNotContainsString("<key>hashedQuitPassword</key><string>password</string>", $realtimequizsettings->get_config());
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>{$hashedpassword}</string>", $realtimequizsettings->get_config());

        $realtimequizsettings->set('allowuserquitseb', 0);
        $realtimequizsettings->set('quitpassword', '');
        $realtimequizsettings->save();
        $this->assertStringContainsString("<key>allowQuit</key><false/>", $realtimequizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $realtimequizsettings->get_config());
    }

    /**
     * Test using USE_SEB_TEMPLATE and have it override settings from the template when they are not set.
     */
    public function test_using_seb_template_override_settings_when_not_set_in_template() {
        $xml = $this->get_config_xml();
        $template = $this->create_template($xml);

        $this->assertStringContainsString("<key>startURL</key><string>https://safeexambrowser.org/start</string>", $template->get('content'));
        $this->assertStringNotContainsString("<key>allowQuit</key><true/>", $template->get('content'));
        $this->assertStringNotContainsString("<key>hashedQuitPassword</key><string>password</string>", $template->get('content'));

        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $realtimequizsettings->set('templateid', $template->get('id'));
        $realtimequizsettings->set('allowuserquitseb', 1);
        $realtimequizsettings->save();

        $this->assertStringContainsString("<key>allowQuit</key><true/>", $realtimequizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $realtimequizsettings->get_config());

        $realtimequizsettings->set('quitpassword', 'new password');
        $realtimequizsettings->save();
        $hashedpassword = hash('SHA256', 'new password');
        $this->assertStringContainsString("<key>allowQuit</key><true/>", $realtimequizsettings->get_config());
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>{$hashedpassword}</string>", $realtimequizsettings->get_config());

        $realtimequizsettings->set('allowuserquitseb', 0);
        $realtimequizsettings->set('quitpassword', '');
        $realtimequizsettings->save();
        $this->assertStringContainsString("<key>allowQuit</key><false/>", $realtimequizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $realtimequizsettings->get_config());
    }

    /**
     * Test using USE_SEB_UPLOAD_CONFIG and use settings from the file if they are set.
     */
    public function test_using_own_config_settings_are_not_overridden_if_set() {
        $xml = $this->get_config_xml(true, 'password');
        $this->create_module_test_file($xml, $this->realtimequiz->cmid);

        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $realtimequizsettings->set('allowuserquitseb', 0);
        $realtimequizsettings->set('quitpassword', '');
        $realtimequizsettings->save();

        $this->assertStringContainsString(
            "<key>startURL</key><string>https://www.example.com/moodle/mod/realtimequiz/view.php?id={$this->realtimequiz->cmid}</string>",
            $realtimequizsettings->get_config()
        );

        $this->assertStringContainsString("<key>allowQuit</key><true/>", $realtimequizsettings->get_config());
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>password</string>", $realtimequizsettings->get_config());

        $realtimequizsettings->set('quitpassword', 'new password');
        $realtimequizsettings->save();
        $hashedpassword = hash('SHA256', 'new password');

        $this->assertStringNotContainsString("<key>hashedQuitPassword</key><string>{$hashedpassword}</string>", $realtimequizsettings->get_config());
        $this->assertStringContainsString("<key>allowQuit</key><true/>", $realtimequizsettings->get_config());
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>password</string>", $realtimequizsettings->get_config());

        $realtimequizsettings->set('allowuserquitseb', 0);
        $realtimequizsettings->set('quitpassword', '');
        $realtimequizsettings->save();

        $this->assertStringContainsString("<key>allowQuit</key><true/>", $realtimequizsettings->get_config());
        $this->assertStringContainsString("<key>hashedQuitPassword</key><string>password</string>", $realtimequizsettings->get_config());
    }

    /**
     * Test using USE_SEB_UPLOAD_CONFIG and use settings from the file if they are not set.
     */
    public function test_using_own_config_settings_are_not_overridden_if_not_set() {
        $xml = $this->get_config_xml();
        $this->create_module_test_file($xml, $this->realtimequiz->cmid);

        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $realtimequizsettings->set('allowuserquitseb', 1);
        $realtimequizsettings->set('quitpassword', '');
        $realtimequizsettings->save();

        $this->assertStringContainsString(
            "<key>startURL</key><string>https://www.example.com/moodle/mod/realtimequiz/view.php?id={$this->realtimequiz->cmid}</string>",
            $realtimequizsettings->get_config()
        );

        $this->assertStringNotContainsString("allowQuit", $realtimequizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $realtimequizsettings->get_config());

        $realtimequizsettings->set('quitpassword', 'new password');
        $realtimequizsettings->save();

        $this->assertStringNotContainsString("allowQuit", $realtimequizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $realtimequizsettings->get_config());

        $realtimequizsettings->set('allowuserquitseb', 0);
        $realtimequizsettings->set('quitpassword', '');
        $realtimequizsettings->save();

        $this->assertStringNotContainsString("allowQuit", $realtimequizsettings->get_config());
        $this->assertStringNotContainsString("hashedQuitPassword", $realtimequizsettings->get_config());
    }

    /**
     * Test using USE_SEB_TEMPLATE populates the linkquitseb setting if a quitURL is found.
     */
    public function test_template_has_quit_url_set() {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
            . "<plist version=\"1.0\"><dict><key>hashedQuitPassword</key><string>hashedpassword</string>"
            . "<key>allowWlan</key><false/><key>quitURL</key><string>http://seb.quit.url</string>"
            . "<key>sendBrowserExamKey</key><true/></dict></plist>\n";

        $template = $this->create_template($xml);

        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $realtimequizsettings->set('templateid', $template->get('id'));

        $this->assertEmpty($realtimequizsettings->get('linkquitseb'));
        $realtimequizsettings->save();

        $this->assertNotEmpty($realtimequizsettings->get('linkquitseb'));
        $this->assertEquals('http://seb.quit.url', $realtimequizsettings->get('linkquitseb'));
    }

    /**
     * Test using USE_SEB_UPLOAD_CONFIG populates the linkquitseb setting if a quitURL is found.
     */
    public function test_config_file_uploaded_has_quit_url_set() {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
            . "<plist version=\"1.0\"><dict><key>hashedQuitPassword</key><string>hashedpassword</string>"
            . "<key>allowWlan</key><false/><key>quitURL</key><string>http://seb.quit.url</string>"
            . "<key>sendBrowserExamKey</key><true/></dict></plist>\n";

        $itemid = $this->create_module_test_file($xml, $this->realtimequiz->cmid);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);

        $this->assertEmpty($realtimequizsettings->get('linkquitseb'));
        $realtimequizsettings->save();

        $this->assertNotEmpty($realtimequizsettings->get('linkquitseb'));
        $this->assertEquals('http://seb.quit.url', $realtimequizsettings->get('linkquitseb'));
    }

    /**
     * Test template id set correctly.
     */
    public function test_templateid_set_correctly_when_save_settings() {
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertEquals(0, $realtimequizsettings->get('templateid'));

        $template = $this->create_template();
        $templateid = $template->get('id');

        // Initially set to USE_SEB_TEMPLATE with a template id.
        $this->save_settings_with_optional_template($realtimequizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertEquals($templateid, $realtimequizsettings->get('templateid'));

        // Case for USE_SEB_NO, ensure template id reverts to 0.
        $this->save_settings_with_optional_template($realtimequizsettings, settings_provider::USE_SEB_NO);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertEquals(0, $realtimequizsettings->get('templateid'));

        // Reverting back to USE_SEB_TEMPLATE.
        $this->save_settings_with_optional_template($realtimequizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);

        // Case for USE_SEB_CONFIG_MANUALLY, ensure template id reverts to 0.
        $this->save_settings_with_optional_template($realtimequizsettings, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertEquals(0, $realtimequizsettings->get('templateid'));

        // Reverting back to USE_SEB_TEMPLATE.
        $this->save_settings_with_optional_template($realtimequizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);

        // Case for USE_SEB_CLIENT_CONFIG, ensure template id reverts to 0.
        $this->save_settings_with_optional_template($realtimequizsettings, settings_provider::USE_SEB_CLIENT_CONFIG);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertEquals(0, $realtimequizsettings->get('templateid'));

        // Reverting back to USE_SEB_TEMPLATE.
        $this->save_settings_with_optional_template($realtimequizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);

        // Case for USE_SEB_UPLOAD_CONFIG, ensure template id reverts to 0.
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->realtimequiz->cmid);
        $this->save_settings_with_optional_template($realtimequizsettings, settings_provider::USE_SEB_UPLOAD_CONFIG);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertEquals(0, $realtimequizsettings->get('templateid'));

        // Case for USE_SEB_TEMPLATE, ensure template id is correct.
        $this->save_settings_with_optional_template($realtimequizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertEquals($templateid, $realtimequizsettings->get('templateid'));
    }

    /**
     * Helper function in tests to set USE_SEB_TEMPLATE and a template id on the realtimequiz settings.
     *
     * @param seb_realtimequiz_settings $realtimequizsettings Given realtimequiz settings instance.
     * @param int $savetype Type of SEB usage.
     * @param int $templateid Template ID.
     */
    public function save_settings_with_optional_template($realtimequizsettings, $savetype, $templateid = 0) {
        $realtimequizsettings->set('requiresafeexambrowser', $savetype);
        if (!empty($templateid)) {
            $realtimequizsettings->set('templateid', $templateid);
        }
        $realtimequizsettings->save();
    }

    /**
     * Bad browser exam key data provider.
     *
     * @return array
     */
    public function bad_browser_exam_key_provider() : array {
        return [
            'Short string' => ['fdsf434r',
                    'A key should be a 64-character hex string.'],
            'Non hex string' => ['aadf6799aadf6789aadf6789aadf6789aadf6789aadf6789aadf6789aadf678!',
                    'A key should be a 64-character hex string.'],
            'Non unique' => ["aadf6799aadf6789aadf6789aadf6789aadf6789aadf6789aadf6789aadf6789"
                    . "\naadf6799aadf6789aadf6789aadf6789aadf6789aadf6789aadf6789aadf6789", 'The keys must all be different.'],
        ];
    }

    /**
     * Provide settings for different filter rules.
     *
     * @return array Test data.
     */
    public function filter_rules_provider() : array {
        return [
            'enabled simple expessions' => [
                (object) [
                    'requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
                    'realtimequizid' => 1,
                    'cmid' => 1,
                    'expressionsallowed' => "test.com\r\nsecond.hello",
                    'regexallowed' => '',
                    'expressionsblocked' => '',
                    'regexblocked' => '',
                ],
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>showTaskBar</key><true/>"
                . "<key>allowWlan</key><false/><key>showReloadButton</key>"
                . "<true/><key>showTime</key><true/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><false/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><false/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>URLFilterRules</key><array>"
                . "<dict><key>action</key><integer>1</integer><key>active</key><true/>"
                . "<key>expression</key><string>test.com</string>"
                . "<key>regex</key><false/></dict><dict><key>action</key><integer>1</integer>"
                . "<key>active</key><true/><key>expression</key>"
                . "<string>second.hello</string><key>regex</key><false/></dict></array>"
                . "<key>startURL</key><string>https://www.example.com/moodle/mod/realtimequiz/view.php?id=1</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/>"
                . "<key>allowPreferencesWindow</key><false/></dict></plist>\n",
            ],
            'blocked simple expessions' => [
                (object) [
                    'requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
                    'realtimequizid' => 1,
                    'cmid' => 1,
                    'expressionsallowed' => '',
                    'regexallowed' => '',
                    'expressionsblocked' => "test.com\r\nsecond.hello",
                    'regexblocked' => '',
                ],
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>showTaskBar</key><true/>"
                . "<key>allowWlan</key><false/><key>showReloadButton</key>"
                . "<true/><key>showTime</key><true/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><false/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><false/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>URLFilterRules</key><array>"
                . "<dict><key>action</key><integer>0</integer><key>active</key><true/>"
                . "<key>expression</key><string>test.com</string>"
                . "<key>regex</key><false/></dict><dict><key>action</key><integer>0</integer>"
                . "<key>active</key><true/><key>expression</key>"
                . "<string>second.hello</string><key>regex</key><false/></dict></array>"
                . "<key>startURL</key><string>https://www.example.com/moodle/mod/realtimequiz/view.php?id=1</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/>"
                . "<key>allowPreferencesWindow</key><false/></dict></plist>\n",
            ],
            'enabled regex expessions' => [
                (object) [
                    'requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
                    'realtimequizid' => 1,
                    'cmid' => 1,
                    'expressionsallowed' => '',
                    'regexallowed' => "test.com\r\nsecond.hello",
                    'expressionsblocked' => '',
                    'regexblocked' => '',
                ],
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>showTaskBar</key><true/>"
                . "<key>allowWlan</key><false/><key>showReloadButton</key>"
                . "<true/><key>showTime</key><true/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><false/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><false/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>URLFilterRules</key><array>"
                . "<dict><key>action</key><integer>1</integer><key>active</key><true/>"
                . "<key>expression</key><string>test.com</string>"
                . "<key>regex</key><true/></dict><dict><key>action</key><integer>1</integer>"
                . "<key>active</key><true/><key>expression</key>"
                . "<string>second.hello</string><key>regex</key><true/></dict></array>"
                . "<key>startURL</key><string>https://www.example.com/moodle/mod/realtimequiz/view.php?id=1</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/>"
                . "<key>allowPreferencesWindow</key><false/></dict></plist>\n",
            ],
            'blocked regex expessions' => [
                (object) [
                    'requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
                    'realtimequizid' => 1,
                    'cmid' => 1,
                    'expressionsallowed' => '',
                    'regexallowed' => '',
                    'expressionsblocked' => '',
                    'regexblocked' => "test.com\r\nsecond.hello",
                ],
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>showTaskBar</key><true/>"
                . "<key>allowWlan</key><false/><key>showReloadButton</key>"
                . "<true/><key>showTime</key><true/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><false/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><false/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>URLFilterRules</key><array>"
                . "<dict><key>action</key><integer>0</integer><key>active</key><true/>"
                . "<key>expression</key><string>test.com</string>"
                . "<key>regex</key><true/></dict><dict><key>action</key><integer>0</integer>"
                . "<key>active</key><true/><key>expression</key>"
                . "<string>second.hello</string><key>regex</key><true/></dict></array>"
                . "<key>startURL</key><string>https://www.example.com/moodle/mod/realtimequiz/view.php?id=1</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/>"
                . "<key>allowPreferencesWindow</key><false/></dict></plist>\n",
            ],
            'multiple simple expessions' => [
                (object) [
                    'requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
                    'realtimequizid' => 1,
                    'cmid' => 1,
                    'expressionsallowed' => "*",
                    'regexallowed' => '',
                    'expressionsblocked' => '',
                    'regexblocked' => "test.com\r\nsecond.hello",
                ],
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
                . "<plist version=\"1.0\"><dict><key>showTaskBar</key><true/>"
                . "<key>allowWlan</key><false/><key>showReloadButton</key>"
                . "<true/><key>showTime</key><true/><key>showInputLanguage</key><true/><key>allowQuit</key><true/>"
                . "<key>quitURLConfirm</key><true/><key>audioControlEnabled</key><false/><key>audioMute</key><false/>"
                . "<key>allowSpellCheck</key><false/><key>browserWindowAllowReload</key><true/><key>URLFilterEnable</key><false/>"
                . "<key>URLFilterEnableContentFilter</key><false/><key>URLFilterRules</key><array><dict><key>action</key>"
                . "<integer>1</integer><key>active</key><true/><key>expression</key><string>*</string>"
                . "<key>regex</key><false/></dict>"
                . "<dict><key>action</key><integer>0</integer><key>active</key><true/>"
                . "<key>expression</key><string>test.com</string>"
                . "<key>regex</key><true/></dict><dict><key>action</key><integer>0</integer>"
                . "<key>active</key><true/><key>expression</key>"
                . "<string>second.hello</string><key>regex</key><true/></dict></array>"
                . "<key>startURL</key><string>https://www.example.com/moodle/mod/realtimequiz/view.php?id=1</string>"
                . "<key>sendBrowserExamKey</key><true/><key>browserWindowWebView</key><integer>3</integer>"
                . "<key>examSessionClearCookiesOnStart</key><false/>"
                . "<key>allowPreferencesWindow</key><false/></dict></plist>\n",
            ],
        ];
    }

    /**
     * Test that config and config key are null when expected.
     */
    public function test_generates_config_values_as_null_when_expected() {
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertNotNull($realtimequizsettings->get_config());
        $this->assertNotNull($realtimequizsettings->get_config_key());

        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_NO);
        $realtimequizsettings->save();
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertNull($realtimequizsettings->get_config());
        $this->assertNull($realtimequizsettings->get_config());

        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->realtimequiz->cmid);
        $realtimequizsettings->save();
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertNotNull($realtimequizsettings->get_config());
        $this->assertNotNull($realtimequizsettings->get_config_key());

        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG);
        $realtimequizsettings->save();
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertNull($realtimequizsettings->get_config());
        $this->assertNull($realtimequizsettings->get_config_key());

        $template = $this->create_template();
        $templateid = $template->get('id');
        $this->save_settings_with_optional_template($realtimequizsettings, settings_provider::USE_SEB_TEMPLATE, $templateid);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertNotNull($realtimequizsettings->get_config());
        $this->assertNotNull($realtimequizsettings->get_config_key());
    }

    /**
     * Test that realtimequizsettings cache exists after creation.
     */
    public function test_realtimequizsettings_cache_exists_after_creation() {
        $expected = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $this->assertEquals($expected->to_record(), \cache::make('realtimequizaccess_seb', 'realtimequizsettings')->get($this->realtimequiz->id));
    }

    /**
     * Test that realtimequizsettings cache gets deleted after deletion.
     */
    public function test_realtimequizsettings_cache_purged_after_deletion() {
        $this->assertNotEmpty(\cache::make('realtimequizaccess_seb', 'realtimequizsettings')->get($this->realtimequiz->id));

        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->delete();

        $this->assertFalse(\cache::make('realtimequizaccess_seb', 'realtimequizsettings')->get($this->realtimequiz->id));
    }

    /**
     * Test that we can get seb_realtimequiz_settings by realtimequiz id.
     */
    public function test_get_realtimequiz_settings_by_realtimequiz_id() {
        $expected = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);

        $this->assertEquals($expected->to_record(), seb_realtimequiz_settings::get_by_realtimequiz_id($this->realtimequiz->id)->to_record());

        // Check that data is getting from cache.
        $expected->set('showsebtaskbar', 0);
        $this->assertNotEquals($expected->to_record(), seb_realtimequiz_settings::get_by_realtimequiz_id($this->realtimequiz->id)->to_record());

        // Now save and check that cached as been updated.
        $expected->save();
        $this->assertEquals($expected->to_record(), seb_realtimequiz_settings::get_by_realtimequiz_id($this->realtimequiz->id)->to_record());

        // Returns false for non existing realtimequiz.
        $this->assertFalse(seb_realtimequiz_settings::get_by_realtimequiz_id(7777777));
    }

    /**
     * Test that SEB config cache exists after creation of the realtimequiz.
     */
    public function test_config_cache_exists_after_creation() {
        $this->assertNotEmpty(\cache::make('realtimequizaccess_seb', 'config')->get($this->realtimequiz->id));
    }

    /**
     * Test that SEB config cache gets deleted after deletion.
     */
    public function test_config_cache_purged_after_deletion() {
        $this->assertNotEmpty(\cache::make('realtimequizaccess_seb', 'config')->get($this->realtimequiz->id));

        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->delete();

        $this->assertFalse(\cache::make('realtimequizaccess_seb', 'config')->get($this->realtimequiz->id));
    }

    /**
     * Test that we can get SEB config by realtimequiz id.
     */
    public function test_get_config_by_realtimequiz_id() {
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $expected = $realtimequizsettings->get_config();

        $this->assertEquals($expected, seb_realtimequiz_settings::get_config_by_realtimequiz_id($this->realtimequiz->id));

        // Check that data is getting from cache.
        $realtimequizsettings->set('showsebtaskbar', 0);
        $this->assertNotEquals($realtimequizsettings->get_config(), seb_realtimequiz_settings::get_config_by_realtimequiz_id($this->realtimequiz->id));

        // Now save and check that cached as been updated.
        $realtimequizsettings->save();
        $this->assertEquals($realtimequizsettings->get_config(), seb_realtimequiz_settings::get_config_by_realtimequiz_id($this->realtimequiz->id));

        // Returns null for non existing realtimequiz.
        $this->assertNull(seb_realtimequiz_settings::get_config_by_realtimequiz_id(7777777));
    }

    /**
     * Test that SEB config key cache exists after creation of the realtimequiz.
     */
    public function test_config_key_cache_exists_after_creation() {
        $this->assertNotEmpty(\cache::make('realtimequizaccess_seb', 'configkey')->get($this->realtimequiz->id));
    }

    /**
     * Test that SEB config key cache gets deleted after deletion.
     */
    public function test_config_key_cache_purged_after_deletion() {
        $this->assertNotEmpty(\cache::make('realtimequizaccess_seb', 'configkey')->get($this->realtimequiz->id));

        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->delete();

        $this->assertFalse(\cache::make('realtimequizaccess_seb', 'configkey')->get($this->realtimequiz->id));
    }

    /**
     * Test that we can get SEB config key by realtimequiz id.
     */
    public function test_get_config_key_by_realtimequiz_id() {
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $expected = $realtimequizsettings->get_config_key();

        $this->assertEquals($expected, seb_realtimequiz_settings::get_config_key_by_realtimequiz_id($this->realtimequiz->id));

        // Check that data is getting from cache.
        $realtimequizsettings->set('showsebtaskbar', 0);
        $this->assertNotEquals($realtimequizsettings->get_config_key(), seb_realtimequiz_settings::get_config_key_by_realtimequiz_id($this->realtimequiz->id));

        // Now save and check that cached as been updated.
        $realtimequizsettings->save();
        $this->assertEquals($realtimequizsettings->get_config_key(), seb_realtimequiz_settings::get_config_key_by_realtimequiz_id($this->realtimequiz->id));

        // Returns null for non existing realtimequiz.
        $this->assertNull(seb_realtimequiz_settings::get_config_key_by_realtimequiz_id(7777777));
    }

}
