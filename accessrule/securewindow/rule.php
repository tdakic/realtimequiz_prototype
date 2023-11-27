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

use mod_realtimequiz\local\access_rule_base;
use mod_realtimequiz\realtimequiz_settings;

/**
 * A rule for ensuring that the realtimequiz is opened in a popup, with some JavaScript
 * to prevent copying and pasting, etc.
 *
 * @package   realtimequizaccess_securewindow
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtimequizaccess_securewindow extends access_rule_base {
    /** @var array options that should be used for opening the secure popup. */
    protected static $popupoptions = [
        'left' => 0,
        'top' => 0,
        'fullscreen' => true,
        'scrollbars' => true,
        'resizeable' => false,
        'directories' => false,
        'toolbar' => false,
        'titlebar' => false,
        'location' => false,
        'status' => false,
        'menubar' => false,
    ];

    public static function make(realtimequiz_settings $realtimequizobj, $timenow, $canignoretimelimits) {

        if ($realtimequizobj->get_realtimequiz()->browsersecurity !== 'securewindow') {
            return null;
        }

        return new self($realtimequizobj, $timenow);
    }

    public function attempt_must_be_in_popup() {
        return !$this->realtimequizobj->is_preview_user();
    }

    public function get_popup_options() {
        return self::$popupoptions;
    }

    public function setup_attempt_page($page) {
        $page->set_popup_notification_allowed(false); // Prevent message notifications.
        $page->set_title($this->realtimequizobj->get_course()->shortname . ': ' . $page->title);
        $page->set_pagelayout('secure');

        if ($this->realtimequizobj->is_preview_user()) {
            return;
        }

        $page->add_body_class('realtimequiz-secure-window');
        $page->requires->js_init_call('M.mod_realtimequiz.secure_window.init',
                null, false, realtimequiz_get_js_module());
    }

    /**
     * @return array key => lang string any choices to add to the realtimequiz Browser
     *      security settings menu.
     */
    public static function get_browser_security_choices() {
        return ['securewindow' =>
                get_string('popupwithjavascriptsupport', 'realtimequizaccess_securewindow')];
    }
}
