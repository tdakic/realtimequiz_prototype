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
 * The realtimequizaccess_user_provider interface provides the expected interface for all 'realtimequizaccess' realtimequizaccesss.
 *
 * Quiz sub plugins should implement this if they store personal information and can retrieve a userid.
 *
 * @package    mod_realtimequiz
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_realtimequiz\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_userlist;

interface realtimequizaccess_user_provider extends \core_privacy\local\request\plugin\subplugin_provider {

    /**
     * Delete multiple users data within a single context.
     *
     * @param   approved_userlist   $userlist The approved context and user information to delete information for.
     */
    public static function delete_realtimequizaccess_data_for_users(approved_userlist $userlist);
}
