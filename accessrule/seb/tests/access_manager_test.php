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
 * PHPUnit tests for the access manager.
 *
 * @package   realtimequizaccess_seb
 * @author    Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright 2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \realtimequizaccess_seb\seb_access_manager
 */
class access_manager_test extends \advanced_testcase {
    use \realtimequizaccess_seb_test_helper_trait;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Test access_manager private property realtimequizsettings is null.
     */
    public function test_access_manager_realtimequizsettings_null() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course);

        $accessmanager = $this->get_access_manager();

        $this->assertFalse($accessmanager->seb_required());

        $reflection = new \ReflectionClass('\realtimequizaccess_seb\seb_access_manager');
        $property = $reflection->getProperty('realtimequizsettings');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($accessmanager));
    }

    /**
     * Test that SEB is not required.
     */
    public function test_seb_required_false() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course);

        $accessmanager = $this->get_access_manager();
        $this->assertFalse($accessmanager->seb_required());
    }

    /**
     * Test that SEB is required.
     */
    public function test_seb_required_true() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $accessmanager = $this->get_access_manager();
        $this->assertTrue($accessmanager->seb_required());
    }

    /**
     * Test that user has capability to bypass SEB check.
     */
    public function test_user_can_bypass_seb_check() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set the bypass SEB check capability to $USER.
        $this->assign_user_capability('realtimequizaccess/seb:bypassseb', \context_module::instance($this->realtimequiz->cmid)->id);

        $accessmanager = $this->get_access_manager();
        $this->assertTrue($accessmanager->can_bypass_seb());
    }

    /**
     * Test that user has capability to bypass SEB check.
     */
    public function test_admin_user_can_bypass_seb_check() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Test normal user cannot bypass check.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $accessmanager = $this->get_access_manager();
        $this->assertFalse($accessmanager->can_bypass_seb());

        // Test with admin user.
        $this->setAdminUser();
        $accessmanager = $this->get_access_manager();
        $this->assertTrue($accessmanager->can_bypass_seb());
    }

    /**
     * Test user does not have capability to bypass SEB check.
     */
    public function test_user_cannot_bypass_seb_check() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $accessmanager = $this->get_access_manager();
        $this->assertFalse($accessmanager->can_bypass_seb());
    }

    /**
     * Test we can detect SEB usage.
     */
    public function test_is_using_seb() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $accessmanager = $this->get_access_manager();

        $this->assertFalse($accessmanager->is_using_seb());

        $_SERVER['HTTP_USER_AGENT'] = 'Test';
        $this->assertFalse($accessmanager->is_using_seb());

        $_SERVER['HTTP_USER_AGENT'] = 'SEB';
        $this->assertTrue($accessmanager->is_using_seb());
    }

    /**
     * Test that the realtimequiz Config Key matches the incoming request header.
     */
    public function test_access_keys_validate_with_config_key() {
        global $FULLME;
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $accessmanager = $this->get_access_manager();

        $configkey = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id])->get_config_key();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/realtimequiz/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $configkey);
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedhash;

        $this->assertTrue($accessmanager->validate_config_key());
    }

    /**
     * Test that the realtimequiz Config Key matches a provided config key with no incoming request header.
     */
    public function test_access_keys_validate_with_provided_config_key() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $url = 'https://www.example.com/moodle';
        $accessmanager = $this->get_access_manager();

        $configkey = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id])->get_config_key();
        $fullconfigkey = hash('sha256', $url . $configkey);

        $this->assertTrue($accessmanager->validate_config_key($fullconfigkey, $url));
    }

    /**
     * Test that the realtimequiz Config Key does not match the incoming request header.
     */
    public function test_access_keys_fail_to_validate_with_config_key() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $accessmanager = $this->get_access_manager();

        $this->assertFalse($accessmanager->validate_config_key());
    }

    /**
     * Test that config key is not checked when using client configuration with SEB.
     */
    public function test_config_key_not_checked_if_client_requirement_is_selected() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $accessmanager = $this->get_access_manager();
        $this->assertFalse($accessmanager->should_validate_config_key());
    }

    /**
     * Test that if there are no browser exam keys for realtimequiz, check is skipped.
     */
    public function test_no_browser_exam_keys_cause_check_to_be_successful() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);

        $settings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $settings->set('allowedbrowserexamkeys', '');
        $settings->save();
        $accessmanager = $this->get_access_manager();
        $this->assertTrue($accessmanager->should_validate_browser_exam_key());
        $this->assertTrue($accessmanager->validate_browser_exam_key());
    }

    /**
     * Test that access fails if there is no hash in header.
     */
    public function test_access_keys_fail_if_browser_exam_key_header_does_not_exist() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);

        $settings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $settings->set('allowedbrowserexamkeys', hash('sha256', 'one') . "\n" . hash('sha256', 'two'));
        $settings->save();
        $accessmanager = $this->get_access_manager();
        $this->assertFalse($accessmanager->validate_browser_exam_key());
    }

    /**
     * Test that access fails if browser exam key doesn't match hash in header.
     */
    public function test_access_keys_fail_if_browser_exam_key_header_does_not_match_provided_hash() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);

        $settings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $settings->set('allowedbrowserexamkeys', hash('sha256', 'one') . "\n" . hash('sha256', 'two'));
        $settings->save();
        $accessmanager = $this->get_access_manager();
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] = hash('sha256', 'notwhatyouwereexpectinghuh');
        $this->assertFalse($accessmanager->validate_browser_exam_key());
    }

    /**
     * Test that browser exam key matches hash in header.
     */
    public function test_browser_exam_keys_match_header_hash() {
        global $FULLME;

        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $settings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $browserexamkey = hash('sha256', 'browserexamkey');
        $settings->set('allowedbrowserexamkeys', $browserexamkey); // Add a hashed BEK.
        $settings->save();
        $accessmanager = $this->get_access_manager();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/realtimequiz/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $browserexamkey);
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] = $expectedhash;
        $this->assertTrue($accessmanager->validate_browser_exam_key());
    }

    /**
     * Test that browser exam key matches a provided browser exam key.
     */
    public function test_browser_exam_keys_match_provided_browser_exam_key() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $url = 'https://www.example.com/moodle';
        $settings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $browserexamkey = hash('sha256', 'browserexamkey');
        $fullbrowserexamkey = hash('sha256', $url . $browserexamkey);
        $settings->set('allowedbrowserexamkeys', $browserexamkey); // Add a hashed BEK.
        $settings->save();
        $accessmanager = $this->get_access_manager();

        $this->assertTrue($accessmanager->validate_browser_exam_key($fullbrowserexamkey, $url));
    }

    /**
     * Test can get received config key.
     */
    public function test_get_received_config_key() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $accessmanager = $this->get_access_manager();

        $this->assertNull($accessmanager->get_received_config_key());

        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = 'Test key';
        $this->assertEquals('Test key', $accessmanager->get_received_config_key());
    }

    /**
     * Test can get received browser key.
     */
    public function get_received_browser_exam_key() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $accessmanager = $this->get_access_manager();

        $this->assertNull($accessmanager->get_received_browser_exam_key());

        $_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] = 'Test browser key';
        $this->assertEquals('Test browser key', $accessmanager->get_received_browser_exam_key());
    }

    /**
     * Test can correctly get type of SEB usage for the realtimequiz.
     */
    public function test_get_seb_use_type() {
        // No SEB.
        $this->realtimequiz = $this->create_test_realtimequiz($this->course);
        $accessmanager = $this->get_access_manager();
        $this->assertEquals(settings_provider::USE_SEB_NO, $accessmanager->get_seb_use_type());

        // Manually.
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $accessmanager = $this->get_access_manager();
        $this->assertEquals(settings_provider::USE_SEB_CONFIG_MANUALLY, $accessmanager->get_seb_use_type());

        // Use template.
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $realtimequizsettings->set('templateid', $this->create_template()->get('id'));
        $realtimequizsettings->save();
        $accessmanager = $this->get_access_manager();
        $this->assertEquals(settings_provider::USE_SEB_TEMPLATE, $accessmanager->get_seb_use_type());

        // Use uploaded config.
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG); // Doesn't check basic header.
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->realtimequiz->cmid);
        $realtimequizsettings->save();
        $accessmanager = $this->get_access_manager();
        $this->assertEquals(settings_provider::USE_SEB_UPLOAD_CONFIG, $accessmanager->get_seb_use_type());

        // Use client config.
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $accessmanager = $this->get_access_manager();
        $this->assertEquals(settings_provider::USE_SEB_CLIENT_CONFIG, $accessmanager->get_seb_use_type());
    }

    /**
     * Data provider for self::test_should_validate_basic_header.
     *
     * @return array
     */
    public function should_validate_basic_header_data_provider() {
        return [
            [settings_provider::USE_SEB_NO, false],
            [settings_provider::USE_SEB_CONFIG_MANUALLY, false],
            [settings_provider::USE_SEB_TEMPLATE, false],
            [settings_provider::USE_SEB_UPLOAD_CONFIG, false],
            [settings_provider::USE_SEB_CLIENT_CONFIG, true],
        ];
    }

    /**
     * Test we know when we should validate basic header.
     *
     * @param int $type Type of SEB usage.
     * @param bool $expected Expected result.
     *
     * @dataProvider should_validate_basic_header_data_provider
     */
    public function test_should_validate_basic_header($type, $expected) {
        $accessmanager = $this->getMockBuilder(seb_access_manager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_seb_use_type'])
            ->getMock();
        $accessmanager->method('get_seb_use_type')->willReturn($type);

        $this->assertEquals($expected, $accessmanager->should_validate_basic_header());

    }

    /**
     * Data provider for self::test_should_validate_config_key.
     *
     * @return array
     */
    public function should_validate_config_key_data_provider() {
        return [
            [settings_provider::USE_SEB_NO, false],
            [settings_provider::USE_SEB_CONFIG_MANUALLY, true],
            [settings_provider::USE_SEB_TEMPLATE, true],
            [settings_provider::USE_SEB_UPLOAD_CONFIG, true],
            [settings_provider::USE_SEB_CLIENT_CONFIG, false],
        ];
    }

    /**
     * Test we know when we should validate config key.
     *
     * @param int $type Type of SEB usage.
     * @param bool $expected Expected result.
     *
     * @dataProvider should_validate_config_key_data_provider
     */
    public function test_should_validate_config_key($type, $expected) {
        $accessmanager = $this->getMockBuilder(seb_access_manager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_seb_use_type'])
            ->getMock();
        $accessmanager->method('get_seb_use_type')->willReturn($type);

        $this->assertEquals($expected, $accessmanager->should_validate_config_key());
    }

    /**
     * Data provider for self::test_should_validate_browser_exam_key.
     *
     * @return array
     */
    public function should_validate_browser_exam_key_data_provider() {
        return [
            [settings_provider::USE_SEB_NO, false],
            [settings_provider::USE_SEB_CONFIG_MANUALLY, false],
            [settings_provider::USE_SEB_TEMPLATE, false],
            [settings_provider::USE_SEB_UPLOAD_CONFIG, true],
            [settings_provider::USE_SEB_CLIENT_CONFIG, true],
        ];
    }

    /**
     * Test we know when we should browser exam key.
     *
     * @param int $type Type of SEB usage.
     * @param bool $expected Expected result.
     *
     * @dataProvider should_validate_browser_exam_key_data_provider
     */
    public function test_should_validate_browser_exam_key($type, $expected) {
        $accessmanager = $this->getMockBuilder(seb_access_manager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_seb_use_type'])
            ->getMock();
        $accessmanager->method('get_seb_use_type')->willReturn($type);

        $this->assertEquals($expected, $accessmanager->should_validate_browser_exam_key());
    }

    /**
     * Test that access manager uses cached Config Key.
     */
    public function test_access_manager_uses_cached_config_key() {
        global $FULLME;
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $accessmanager = $this->get_access_manager();

        $configkey = $accessmanager->get_valid_config_key();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/realtimequiz/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $configkey);
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedhash;

        $this->assertTrue($accessmanager->validate_config_key());

        // Change settings (but don't save) and check that still can validate config key.
        $realtimequizsettings = seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]);
        $realtimequizsettings->set('showsebtaskbar', 0);
        $this->assertNotEquals($realtimequizsettings->get_config_key(), $configkey);
        $this->assertTrue($accessmanager->validate_config_key());

        // Now save settings which should purge caches but access manager still has config key.
        $realtimequizsettings->save();
        $this->assertNotEquals($realtimequizsettings->get_config_key(), $configkey);
        $this->assertTrue($accessmanager->validate_config_key());

        // Initialise a new access manager. Now validation should fail.
        $accessmanager = $this->get_access_manager();
        $this->assertFalse($accessmanager->validate_config_key());
    }

    /**
     * Check that valid SEB config key is null if realtimequiz doesn't have SEB settings.
     */
    public function test_valid_config_key_is_null_if_no_settings() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_NO);
        $accessmanager = $this->get_access_manager();

        $this->assertEmpty(seb_realtimequiz_settings::get_record(['realtimequizid' => $this->realtimequiz->id]));
        $this->assertNull($accessmanager->get_valid_config_key());

    }

    /**
     * Test if config key should not be validated.
     */
    public function test_if_config_key_should_not_be_validated() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_NO);
        $accessmanager = $this->get_access_manager();

        $this->assertTrue($accessmanager->validate_config_key());
    }

    /**
     * Test if browser exam key should not be validated.
     */
    public function test_if_browser_exam_key_should_not_be_validated() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $accessmanager = $this->get_access_manager();

        $this->assertTrue($accessmanager->validate_browser_exam_key());
    }

    /**
     * Test that access is set correctly in Moodle session.
     */
    public function test_set_session_access() {
        global $SESSION;

        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $accessmanager = $this->get_access_manager();

        $this->assertTrue(empty($SESSION->realtimequizaccess_seb_access[$this->realtimequiz->cmid]));

        $accessmanager->set_session_access(true);

        $this->assertTrue($SESSION->realtimequizaccess_seb_access[$this->realtimequiz->cmid]);
    }

    /**
     * Test that access is set in Moodle session for only course module associated with access manager.
     */
    public function test_session_access_set_for_specific_course_module() {
        global $SESSION;

        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $realtimequiz2 = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $accessmanager = $this->get_access_manager();

        $accessmanager->set_session_access(true);

        $this->assertCount(1, $SESSION->realtimequizaccess_seb_access);
        $this->assertTrue($SESSION->realtimequizaccess_seb_access[$this->realtimequiz->cmid]);
        $this->assertTrue(empty($SESSION->realtimequizaccess_seb_access[$realtimequiz2->cmid]));
    }

    /**
     * Test that access state can be retrieved from Moodle session.
     */
    public function test_validate_session_access() {
        global $SESSION;

        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $accessmanager = $this->get_access_manager();

        $this->assertEmpty($accessmanager->validate_session_access());

        $SESSION->realtimequizaccess_seb_access[$this->realtimequiz->cmid] = true;

        $this->assertTrue($accessmanager->validate_session_access());
    }

    /**
     * Test that access can be cleared from Moodle session.
     */
    public function test_clear_session_access() {
        global $SESSION;

        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $accessmanager = $this->get_access_manager();

        $SESSION->realtimequizaccess_seb_access[$this->realtimequiz->cmid] = true;

        $accessmanager->clear_session_access();

        $this->assertTrue(empty($SESSION->realtimequizaccess_seb_access[$this->realtimequiz->cmid]));
    }

    /**
     * Test we can decide if need to redirect to SEB config link.
     */
    public function test_should_redirect_to_seb_config_link() {
        $this->realtimequiz = $this->create_test_realtimequiz($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $accessmanager = $this->get_access_manager();

        set_config('autoreconfigureseb', '1', 'realtimequizaccess_seb');
        $_SERVER['HTTP_USER_AGENT'] = 'SEB';
        $this->assertFalse($accessmanager->should_redirect_to_seb_config_link());

        set_config('autoreconfigureseb', '1', 'realtimequizaccess_seb');
        $_SERVER['HTTP_USER_AGENT'] = 'SEB';
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = hash('sha256', 'configkey');
        $this->assertTrue($accessmanager->should_redirect_to_seb_config_link());
    }
}
