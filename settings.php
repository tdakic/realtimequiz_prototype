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
 * Administration settings definitions for the realtimequiz module.
 *
 * @package   mod_realtimequiz
 * @copyright 2010 Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\admin\review_setting;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/realtimequiz/lib.php');

// First get a list of realtimequiz reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$reports = core_component::get_plugin_list_with_file('realtimequiz', 'settings.php', false);
$reportsbyname = [];
foreach ($reports as $report => $reportdir) {
    $strreportname = get_string($report . 'report', 'realtimequiz_'.$report);
    $reportsbyname[$strreportname] = $report;
}
core_collator::ksort($reportsbyname);

// First get a list of realtimequiz reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$rules = core_component::get_plugin_list_with_file('realtimequizaccess', 'settings.php', false);
$rulesbyname = [];
foreach ($rules as $rule => $ruledir) {
    $strrulename = get_string('pluginname', 'realtimequizaccess_' . $rule);
    $rulesbyname[$strrulename] = $rule;
}
core_collator::ksort($rulesbyname);

// Create the realtimequiz settings page.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $pagetitle = get_string('modulename', 'realtimequiz');
} else {
    $pagetitle = get_string('generalsettings', 'admin');
}
$realtimequizsettings = new admin_settingpage('modsettingrealtimequiz', $pagetitle, 'moodle/site:config');

if ($ADMIN->fulltree) {
    // Introductory explanation that all the settings are defaults for the add realtimequiz form.
    $realtimequizsettings->add(new admin_setting_heading('realtimequizintro', '', get_string('configintro', 'realtimequiz')));

    // Time limit.
    $setting = new admin_setting_configduration('realtimequiz/timelimit',
            get_string('timelimit', 'realtimequiz'), get_string('configtimelimitsec', 'realtimequiz'),
            '0', 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Delay to notify graded attempts.
    $realtimequizsettings->add(new admin_setting_configduration('realtimequiz/notifyattemptgradeddelay',
        get_string('attemptgradeddelay', 'realtimequiz'), get_string('attemptgradeddelay_desc', 'realtimequiz'), 5 * HOURSECS, HOURSECS));

    // What to do with overdue attempts.
    $setting = new \mod_realtimequiz\admin\overdue_handling_setting('realtimequiz/overduehandling',
            get_string('overduehandling', 'realtimequiz'), get_string('overduehandling_desc', 'realtimequiz'),
            ['value' => 'autosubmit', 'adv' => false], null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Grace period time.
    $setting = new admin_setting_configduration('realtimequiz/graceperiod',
            get_string('graceperiod', 'realtimequiz'), get_string('graceperiod_desc', 'realtimequiz'),
            '86400');
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Minimum grace period used behind the scenes.
    $realtimequizsettings->add(new admin_setting_configduration('realtimequiz/graceperiodmin',
            get_string('graceperiodmin', 'realtimequiz'), get_string('graceperiodmin_desc', 'realtimequiz'),
            60, 1));

    // Number of attempts.
    $options = [get_string('unlimited')];
    for ($i = 1; $i <= QUIZ_MAX_ATTEMPT_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('realtimequiz/attempts',
            get_string('attemptsallowed', 'realtimequiz'), get_string('configattemptsallowed', 'realtimequiz'),
            0, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Grading method.
    $setting = new \mod_realtimequiz\admin\grade_method_setting('realtimequiz/grademethod',
            get_string('grademethod', 'realtimequiz'), get_string('configgrademethod', 'realtimequiz'),
            ['value' => QUIZ_GRADEHIGHEST, 'adv' => false], null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Maximum grade.
    $setting = new admin_setting_configtext('realtimequiz/maximumgrade',
            get_string('maximumgrade'), get_string('configmaximumgrade', 'realtimequiz'), 10, PARAM_INT);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Questions per page.
    $perpage = [];
    $perpage[0] = get_string('never');
    $perpage[1] = get_string('aftereachquestion', 'realtimequiz');
    for ($i = 2; $i <= QUIZ_MAX_QPP_OPTION; ++$i) {
        $perpage[$i] = get_string('afternquestions', 'realtimequiz', $i);
    }
    $setting = new admin_setting_configselect('realtimequiz/questionsperpage',
            get_string('newpageevery', 'realtimequiz'), get_string('confignewpageevery', 'realtimequiz'),
            1, $perpage);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Navigation method.
    $setting = new admin_setting_configselect('realtimequiz/navmethod',
            get_string('navmethod', 'realtimequiz'), get_string('confignavmethod', 'realtimequiz'),
            QUIZ_NAVMETHOD_FREE, realtimequiz_get_navigation_options());
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Shuffle within questions.
    $setting = new admin_setting_configcheckbox('realtimequiz/shuffleanswers',
            get_string('shufflewithin', 'realtimequiz'), get_string('configshufflewithin', 'realtimequiz'),
            1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Preferred behaviour.
    $setting = new admin_setting_question_behaviour('realtimequiz/preferredbehaviour',
            get_string('howquestionsbehave', 'question'), get_string('howquestionsbehave_desc', 'realtimequiz'),
            'deferredfeedback');
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Can redo completed questions.
    $setting = new admin_setting_configselect('realtimequiz/canredoquestions',
            get_string('canredoquestions', 'realtimequiz'), get_string('canredoquestions_desc', 'realtimequiz'),
            0,
            [0 => get_string('no'), 1 => get_string('canredoquestionsyes', 'realtimequiz')]);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Each attempt builds on last.
    $setting = new admin_setting_configcheckbox('realtimequiz/attemptonlast',
            get_string('eachattemptbuildsonthelast', 'realtimequiz'),
            get_string('configeachattemptbuildsonthelast', 'realtimequiz'),
            0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Review options.
    $realtimequizsettings->add(new admin_setting_heading('reviewheading',
            get_string('reviewoptionsheading', 'realtimequiz'), ''));
    foreach (review_setting::fields() as $field => $name) {
        $default = review_setting::all_on();
        $forceduring = null;
        if ($field == 'attempt') {
            $forceduring = true;
        } else if ($field == 'overallfeedback') {
            $default = $default ^ review_setting::DURING;
            $forceduring = false;
        }
        $realtimequizsettings->add(new review_setting('realtimequiz/review' . $field,
                $name, '', $default, $forceduring));
    }

    // Show the user's picture.
    $setting = new \mod_realtimequiz\admin\user_image_setting('realtimequiz/showuserpicture',
            get_string('showuserpicture', 'realtimequiz'), get_string('configshowuserpicture', 'realtimequiz'),
            ['value' => 0, 'adv' => false], null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Decimal places for overall grades.
    $options = [];
    for ($i = 0; $i <= QUIZ_MAX_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('realtimequiz/decimalpoints',
            get_string('decimalplaces', 'realtimequiz'), get_string('configdecimalplaces', 'realtimequiz'),
            2, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Decimal places for question grades.
    $options = [-1 => get_string('sameasoverall', 'realtimequiz')];
    for ($i = 0; $i <= QUIZ_MAX_Q_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('realtimequiz/questiondecimalpoints',
            get_string('decimalplacesquestion', 'realtimequiz'),
            get_string('configdecimalplacesquestion', 'realtimequiz'),
            -1, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Show blocks during realtimequiz attempts.
    $setting = new admin_setting_configcheckbox('realtimequiz/showblocks',
            get_string('showblocks', 'realtimequiz'), get_string('configshowblocks', 'realtimequiz'),
            0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Password.
    $setting = new admin_setting_configpasswordunmask('realtimequiz/realtimequizpassword',
            get_string('requirepassword', 'realtimequiz'), get_string('configrequirepassword', 'realtimequiz'),
            '');
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_required_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // IP restrictions.
    $setting = new admin_setting_configtext('realtimequiz/subnet',
            get_string('requiresubnet', 'realtimequiz'), get_string('configrequiresubnet', 'realtimequiz'),
            '', PARAM_TEXT);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Enforced delay between attempts.
    $setting = new admin_setting_configduration('realtimequiz/delay1',
            get_string('delay1st2nd', 'realtimequiz'), get_string('configdelay1st2nd', 'realtimequiz'),
            0, 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);
    $setting = new admin_setting_configduration('realtimequiz/delay2',
            get_string('delaylater', 'realtimequiz'), get_string('configdelaylater', 'realtimequiz'),
            0, 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    // Browser security.
    $setting = new \mod_realtimequiz\admin\browser_security_setting('realtimequiz/browsersecurity',
            get_string('showinsecurepopup', 'realtimequiz'), get_string('configpopup', 'realtimequiz'),
            ['value' => '-', 'adv' => true], null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $realtimequizsettings->add($setting);

    $realtimequizsettings->add(new admin_setting_configtext('realtimequiz/initialnumfeedbacks',
            get_string('initialnumfeedbacks', 'realtimequiz'), get_string('initialnumfeedbacks_desc', 'realtimequiz'),
            2, PARAM_INT, 5));

    // Allow user to specify if setting outcomes is an advanced setting.
    if (!empty($CFG->enableoutcomes)) {
        $realtimequizsettings->add(new admin_setting_configcheckbox('realtimequiz/outcomes_adv',
            get_string('outcomesadvanced', 'realtimequiz'), get_string('configoutcomesadvanced', 'realtimequiz'),
            '0'));
    }

    // Autosave frequency.
    $realtimequizsettings->add(new admin_setting_configduration('realtimequiz/autosaveperiod',
            get_string('autosaveperiod', 'realtimequiz'), get_string('autosaveperiod_desc', 'realtimequiz'), 60, 1));
}

// Now, depending on whether any reports have their own settings page, add
// the realtimequiz setting page to the appropriate place in the tree.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $ADMIN->add('modsettings', $realtimequizsettings);
} else {
    $ADMIN->add('modsettings', new admin_category('modsettingsrealtimequizcat',
            get_string('modulename', 'realtimequiz'), $module->is_enabled() === false));
    $ADMIN->add('modsettingsrealtimequizcat', $realtimequizsettings);

    // Add settings pages for the realtimequiz report subplugins.
    foreach ($reportsbyname as $strreportname => $report) {
        $reportname = $report;

        $settings = new admin_settingpage('modsettingsrealtimequizcat'.$reportname,
                $strreportname, 'moodle/site:config', $module->is_enabled() === false);
        include($CFG->dirroot . "/mod/realtimequiz/report/$reportname/settings.php");
        if (!empty($settings)) {
            $ADMIN->add('modsettingsrealtimequizcat', $settings);
        }
    }

    // Add settings pages for the realtimequiz access rule subplugins.
    foreach ($rulesbyname as $strrulename => $rule) {
        $settings = new admin_settingpage('modsettingsrealtimequizcat' . $rule,
                $strrulename, 'moodle/site:config', $module->is_enabled() === false);
        include($CFG->dirroot . "/mod/realtimequiz/accessrule/$rule/settings.php");
        if (!empty($settings)) {
            $ADMIN->add('modsettingsrealtimequizcat', $settings);
        }
    }
}

$settings = null; // We do not want standard settings link.
