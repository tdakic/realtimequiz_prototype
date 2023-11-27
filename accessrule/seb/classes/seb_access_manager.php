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
 * Manage the access to the realtimequiz.
 *
 * @package    realtimequizaccess_seb
 * @author     Tim Hunt
 * @author     Luca Bösch <luca.boesch@bfh.ch>
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace realtimequizaccess_seb;

use context_module;
use mod_realtimequiz\realtimequiz_settings;

defined('MOODLE_INTERNAL') || die();

/**
 * Manage the access to the realtimequiz.
 *
 * @copyright  2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class seb_access_manager {

    /** Header sent by Safe Exam Browser containing the Config Key hash. */
    private const CONFIG_KEY_HEADER = 'HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH';

    /** Header sent by Safe Exam Browser containing the Browser Exam Key hash. */
    private const BROWSER_EXAM_KEY_HEADER = 'HTTP_X_SAFEEXAMBROWSER_REQUESTHASH';

    /** @var realtimequiz_settings $realtimequiz A realtimequiz object containing all information pertaining to current realtimequiz. */
    private $realtimequiz;

    /** @var seb_realtimequiz_settings $realtimequizsettings A realtimequiz settings persistent object containing plugin settings */
    private $realtimequizsettings;

    /** @var context_module $context Context of this realtimequiz activity. */
    private $context;

    /** @var string|null $validconfigkey Expected valid SEB config key. */
    private $validconfigkey = null;

    /**
     * The access_manager constructor.
     *
     * @param realtimequiz_settings $realtimequiz The details of the realtimequiz.
     */
    public function __construct(realtimequiz_settings $realtimequiz) {
        $this->realtimequiz = $realtimequiz;
        $this->context = context_module::instance($realtimequiz->get_cmid());
        $this->realtimequizsettings = seb_realtimequiz_settings::get_by_realtimequiz_id($realtimequiz->get_realtimequizid());
        $this->validconfigkey = seb_realtimequiz_settings::get_config_key_by_realtimequiz_id($realtimequiz->get_realtimequizid());
    }

    /**
     * Validate browser exam key. It will validate a provided browser exam key if provided, then will fall back to checking
     * the header.
     *
     * @param string|null $browserexamkey Optional. Can validate a provided key, or will fall back to checking header.
     * @param string|null $url Optionally provide URL of page to validate.
     * @return bool
     */
    public function validate_browser_exam_key(?string $browserexamkey = null, ?string $url = null): bool {
        if (!$this->should_validate_browser_exam_key()) {
            // Browser exam key should not be checked, so do not prevent access.
            return true;
        }

        if (!$this->is_allowed_browser_examkeys_configured()) {
            return true; // If no browser exam keys, no check required.
        }

        if (empty($browserexamkey)) {
            $browserexamkey = $this->get_received_browser_exam_key();
        }

        $validbrowserexamkeys = $this->realtimequizsettings->get('allowedbrowserexamkeys');

        // If the Browser Exam Key header isn't present, prevent access.
        if (is_null($browserexamkey)) {
            return false;
        }

        return $this->check_browser_exam_keys($validbrowserexamkeys, $browserexamkey, $url);
    }

    /**
     * Validate a config key. It will check a provided config key if provided then will fall back to checking config
     * key in header.
     *
     * @param string|null $configkey Optional. Can validate a provided key, or will fall back to checking header.
     * @param string|null $url URL of page to validate.
     * @return bool
     */
    public function validate_config_key(?string $configkey = null, ?string $url = null): bool {
        if (!$this->should_validate_config_key()) {
            // Config key should not be checked, so do not prevent access.
            return true;
        }

        // If using client config, or with no requirement, then no check required.
        $requiredtype = $this->get_seb_use_type();
        if ($requiredtype == settings_provider::USE_SEB_NO
                || $requiredtype == settings_provider::USE_SEB_CLIENT_CONFIG) {
            return true;
        }

        if (empty($configkey)) {
            $configkey = $this->get_received_config_key();
        }

        if (empty($this->validconfigkey)) {
            return false; // No config key has been saved.
        }

        if (is_null($configkey)) {
            return false;
        }

        // Check if there is a valid config key supplied in the header.
        return $this->check_key($this->validconfigkey, $configkey, $url);
    }

    /**
     * Check if Safe Exam Browser is required to access realtimequiz.
     * If realtimequizsettings do not exist, then there is no requirement for using SEB.
     *
     * @return bool If required.
     */
    public function seb_required() : bool {
        if (!$this->realtimequizsettings) {
            return false;
        } else {
            return $this->get_seb_use_type() != settings_provider::USE_SEB_NO;
        }
    }

    /**
     * This is the basic check for the Safe Exam Browser previously used in the realtimequizaccess_safebrowser plugin that
     * managed basic Moodle interactions with SEB.
     *
     * @return bool
     */
    public function validate_basic_header(): bool {
        if (!$this->should_validate_basic_header()) {
            // Config key should not be checked, so do not prevent access.
            return true;
        }

        if ($this->get_seb_use_type() == settings_provider::USE_SEB_CLIENT_CONFIG) {
            return $this->is_using_seb();
        }
        return true;
    }

    /**
     * Check if using Safe Exam Browser.
     *
     * @return bool
     */
    public function is_using_seb(): bool {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            return strpos($_SERVER['HTTP_USER_AGENT'], 'SEB') !== false;
        }

        return false;
    }

    /**
     * Check if user has any capability to bypass the Safe Exam Browser requirement.
     *
     * @return bool True if user can bypass check.
     */
    public function can_bypass_seb(): bool {
        return has_capability('realtimequizaccess/seb:bypassseb', $this->context);
    }

    /**
     * Return the full URL that was used to request the current page, which is
     * what we need for verifying the X-SafeExamBrowser-RequestHash header.
     */
    private function get_this_page_url(): string {
        global $CFG, $FULLME;
        // If $FULLME not set fall back to wwwroot.
        if ($FULLME == null) {
            return $CFG->wwwroot;
        }
        return $FULLME;
    }

    /**
     * Return expected SEB config key.
     *
     * @return string|null
     */
    public function get_valid_config_key(): ?string {
        return $this->validconfigkey;
    }

    /**
     * Getter for the realtimequiz object.
     *
     * @return \mod_realtimequiz\realtimequiz_settings
     */
    public function get_realtimequiz() : realtimequiz_settings {
        return $this->realtimequiz;
    }

    /**
     * Check that at least one browser exam key exists in the realtimequiz settings.
     *
     * @return bool True if one or more keys are set in realtimequiz settings.
     */
    private function is_allowed_browser_examkeys_configured(): bool {
        return !empty($this->realtimequizsettings->get('allowedbrowserexamkeys'));
    }

    /**
     * Check the hash from the request header against the permitted browser exam keys.
     *
     * @param array $keys Allowed browser exam keys.
     * @param string $header The value of the X-SafeExamBrowser-RequestHash to check.
     * @param string|null $url URL of page to validate.
     * @return bool True if the hash matches.
     */
    private function check_browser_exam_keys(array $keys, string $header, ?string $url = null): bool {
        foreach ($keys as $key) {
            if ($this->check_key($key, $header, $url)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check the hash from the request header against a single permitted key.
     *
     * @param string $validkey An allowed key.
     * @param string $key The value of X-SafeExamBrowser-RequestHash, X-SafeExamBrowser-ConfigKeyHash or a provided key to check.
     * @param string|null $url URL of page to validate.
     * @return bool True if the hash matches.
     */
    private function check_key(string $validkey, string $key, ?string $url = null): bool {
        if (empty($url)) {
            $url = $this->get_this_page_url();
        }
        return hash('sha256', $url . $validkey) === $key;
    }

    /**
     * Returns Safe Exam Browser Config Key hash.
     *
     * @return string|null
     */
    public function get_received_config_key(): ?string {
        if (isset($_SERVER[self::CONFIG_KEY_HEADER])) {
            return trim($_SERVER[self::CONFIG_KEY_HEADER]);
        }

        return null;
    }

    /**
     * Returns the Browser Exam Key hash.
     *
     * @return string|null
     */
    public function get_received_browser_exam_key(): ?string {
        if (isset($_SERVER[self::BROWSER_EXAM_KEY_HEADER])) {
            return trim($_SERVER[self::BROWSER_EXAM_KEY_HEADER]);
        }

        return null;
    }

    /**
     * Get type of SEB usage for the realtimequiz.
     *
     * @return int
     */
    public function get_seb_use_type(): int {
        if (empty($this->realtimequizsettings)) {
            return settings_provider::USE_SEB_NO;
        } else {
            return $this->realtimequizsettings->get('requiresafeexambrowser');
        }
    }

    /**
     * Should validate basic header?
     *
     * @return bool
     */
    public function should_validate_basic_header(): bool {
        return in_array($this->get_seb_use_type(), [
            settings_provider::USE_SEB_CLIENT_CONFIG,
        ]);
    }

    /**
     * Should validate SEB config key?
     * @return bool
     */
    public function should_validate_config_key(): bool {
        return in_array($this->get_seb_use_type(), [
            settings_provider::USE_SEB_CONFIG_MANUALLY,
            settings_provider::USE_SEB_TEMPLATE,
            settings_provider::USE_SEB_UPLOAD_CONFIG,
        ]);
    }

    /**
     * Should validate browser exam key?
     *
     * @return bool
     */
    public function should_validate_browser_exam_key(): bool {
        return in_array($this->get_seb_use_type(), [
            settings_provider::USE_SEB_UPLOAD_CONFIG,
            settings_provider::USE_SEB_CLIENT_CONFIG,
        ]);
    }

    /**
     * Set session access for realtimequiz.
     *
     * @param bool $accessallowed
     */
    public function set_session_access(bool $accessallowed): void {
        global $SESSION;
        if (!isset($SESSION->realtimequizaccess_seb_access)) {
            $SESSION->realtimequizaccess_seb_access = [];
        }
        $SESSION->realtimequizaccess_seb_access[$this->realtimequiz->get_cmid()] = $accessallowed;
    }

    /**
     * Check session access for realtimequiz if already set.
     *
     * @return bool
     */
    public function validate_session_access(): bool {
        global $SESSION;
        return !empty($SESSION->realtimequizaccess_seb_access[$this->realtimequiz->get_cmid()]);
    }

    /**
     * Unset the global session access variable for this realtimequiz.
     */
    public function clear_session_access(): void {
        global $SESSION;
        unset($SESSION->realtimequizaccess_seb_access[$this->realtimequiz->get_cmid()]);
    }

    /**
     * Redirect to SEB config link. This will force Safe Exam Browser to be reconfigured.
     */
    public function redirect_to_seb_config_link(): void {
        global $PAGE;

        $seblink = \realtimequizaccess_seb\link_generator::get_link($this->realtimequiz->get_cmid(), true, is_https());
        $PAGE->requires->js_amd_inline("document.location.replace('" . $seblink . "')");
    }

    /**
     * Check if we need to redirect to SEB config link.
     *
     * @return bool
     */
    public function should_redirect_to_seb_config_link(): bool {
        // We check if there is an existing config key header. If there is none, we assume that
        // the SEB application is not using header verification so auto redirect should not proceed.
        $haskeyinheader = !is_null($this->get_received_config_key());

        return $this->is_using_seb()
                && get_config('realtimequizaccess_seb', 'autoreconfigureseb')
                && $haskeyinheader;
    }
}
