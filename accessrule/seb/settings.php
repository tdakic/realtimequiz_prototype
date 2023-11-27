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
 * Global configuration settings for the realtimequizaccess_seb plugin.
 *
 * @package    realtimequizaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $ADMIN;

if ($hassiteconfig) {

    $settings->add(new admin_setting_heading(
        'realtimequizaccess_seb/supportedversions',
        '',
        $OUTPUT->notification(get_string('setting:supportedversions', 'realtimequizaccess_seb'), 'warning')));

    $settings->add(new admin_setting_configcheckbox('realtimequizaccess_seb/autoreconfigureseb',
        get_string('setting:autoreconfigureseb', 'realtimequizaccess_seb'),
        get_string('setting:autoreconfigureseb_desc', 'realtimequizaccess_seb'),
        '1'));

    $links = [
        'seb' => get_string('setting:showseblink', 'realtimequizaccess_seb'),
        'http' => get_string('setting:showhttplink', 'realtimequizaccess_seb')
    ];
    $settings->add(new admin_setting_configmulticheckbox('realtimequizaccess_seb/showseblinks',
        get_string('setting:showseblinks', 'realtimequizaccess_seb'),
        get_string('setting:showseblinks_desc', 'realtimequizaccess_seb'),
        $links, $links));

    $settings->add(new admin_setting_configtext('realtimequizaccess_seb/downloadlink',
        get_string('setting:downloadlink', 'realtimequizaccess_seb'),
        get_string('setting:downloadlink_desc', 'realtimequizaccess_seb'),
        'https://safeexambrowser.org/download_en.html',
        PARAM_URL));

    $settings->add(new admin_setting_configcheckbox('realtimequizaccess_seb/realtimequizpasswordrequired',
        get_string('setting:realtimequizpasswordrequired', 'realtimequizaccess_seb'),
        get_string('setting:realtimequizpasswordrequired_desc', 'realtimequizaccess_seb'),
        '0'));

    $settings->add(new admin_setting_configcheckbox('realtimequizaccess_seb/displayblocksbeforestart',
        get_string('setting:displayblocksbeforestart', 'realtimequizaccess_seb'),
        get_string('setting:displayblocksbeforestart_desc', 'realtimequizaccess_seb'),
        '0'));

    $settings->add(new admin_setting_configcheckbox('realtimequizaccess_seb/displayblockswhenfinished',
        get_string('setting:displayblockswhenfinished', 'realtimequizaccess_seb'),
        get_string('setting:displayblockswhenfinished_desc', 'realtimequizaccess_seb'),
        '1'));
}

if (has_capability('realtimequizaccess/seb:managetemplates', context_system::instance())) {
    $ADMIN->add('modsettingsrealtimequizcat',
        new admin_externalpage(
            'realtimequizaccess_seb/template',
            get_string('manage_templates', 'realtimequizaccess_seb'),
            new moodle_url('/mod/realtimequiz/accessrule/seb/template.php'),
            'realtimequizaccess/seb:managetemplates'
        )
    );
}
