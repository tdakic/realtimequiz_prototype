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
 * This file contains mappings for classes that have been renamed.
 *
 * @package mod_realtimequiz
 * @copyright 2022 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$renamedclasses = [
    // Since Moodle 4.1.
    'mod_realtimequiz\local\views\secondary' => 'mod_realtimequiz\navigation\views\secondary',
    // Since Moodle 4.2.
    'mod_realtimequiz_display_options' => 'mod_realtimequiz\question\display_options',
    'qubaids_for_realtimequiz' => 'mod_realtimequiz\question\qubaids_for_realtimequiz',
    'qubaids_for_realtimequiz_user' => 'mod_realtimequiz\question\qubaids_for_realtimequiz_user',
    'mod_realtimequiz_admin_setting_browsersecurity' => 'mod_realtimequiz\admin\browser_security_setting',
    'mod_realtimequiz_admin_setting_grademethod' => 'mod_realtimequiz\admin\grade_method_setting',
    'mod_realtimequiz_admin_setting_overduehandling' => 'mod_realtimequiz\admin\overdue_handling_setting',
    'mod_realtimequiz_admin_review_setting' => 'mod_realtimequiz\admin\review_setting',
    'mod_realtimequiz_admin_setting_user_image' => 'mod_realtimequiz\admin\user_image_setting',
    'mod_realtimequiz\adminpresets\adminpresets_mod_realtimequiz_admin_setting_browsersecurity' =>
            'mod_realtimequiz\adminpresets\adminpresets_browser_security_setting',
    'mod_realtimequiz\adminpresets/adminpresets_mod_realtimequiz_admin_setting_grademethod' =>
            'mod_realtimequiz\adminpresets\adminpresets_grade_method_setting',
    'mod_realtimequiz\adminpresets\adminpresets_mod_realtimequiz_admin_setting_overduehandling' =>
            'mod_realtimequiz\adminpresets\adminpresets_overdue_handling_setting',
    'mod_realtimequiz\adminpresets\adminpresets_mod_realtimequiz_admin_review_setting' =>
            'mod_realtimequiz\adminpresets\adminpresets_review_setting',
    'mod_realtimequiz\adminpresets\adminpresets_mod_realtimequiz_admin_setting_user_image' =>
            'mod_realtimequiz\adminpresets\adminpresets_user_image_setting',
    'realtimequiz_default_report' => 'mod_realtimequiz\local\reports\report_base',
    'realtimequiz_attempts_report' => 'mod_realtimequiz\local\reports\attempts_report',
    'mod_realtimequiz_attempts_report_form' => 'mod_realtimequiz\local\reports\attempts_report_options_form',
    'mod_realtimequiz_attempts_report_options' => 'mod_realtimequiz\local\reports\attempts_report_options',
    'realtimequiz_attempts_report_table' => 'mod_realtimequiz\local\reports\attempts_report_table',
    'realtimequiz_access_manager' => 'mod_realtimequiz\access_manager',
    'mod_realtimequiz_preflight_check_form' => 'mod_realtimequiz\form\preflight_check_form',
    'realtimequiz_override_form' => 'mod_realtimequiz\form\edit_override_form',
    'realtimequiz_access_rule_base' => 'mod_realtimequiz\local\access_rule_base',
    'realtimequiz_add_random_form' => 'mod_realtimequiz\form\add_random_form',
    'mod_realtimequiz_links_to_other_attempts' => 'mod_realtimequiz\output\links_to_other_attempts',
    'mod_realtimequiz_view_object' => 'mod_realtimequiz\output\view_page',
    'mod_realtimequiz_renderer' => 'mod_realtimequiz\output\renderer',
    'realtimequiz_nav_question_button' => 'mod_realtimequiz\output\navigation_question_button',
    'realtimequiz_nav_section_heading' => 'mod_realtimequiz\output\navigation_section_heading',
    'realtimequiz_nav_panel_base' => 'mod_realtimequiz\output\navigation_panel_base',
    'realtimequiz_attempt_nav_panel' => 'mod_realtimequiz\output\navigation_panel_attempt',
    'realtimequiz_review_nav_panel' => 'mod_realtimequiz\output\navigation_panel_review',
    'realtimequiz_attempt' => 'mod_realtimequiz\realtimequiz_attempt',
    'realtimequiz' => 'mod_realtimequiz\realtimequiz_settings',
];
