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
 * Install script for plugin.
 *
 * @package    realtimequizaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot  . '/mod/realtimequiz/accessrule/seb/lib.php');

/**
 * Custom code to be run on installing the plugin.
 */
function xmldb_realtimequizaccess_seb_install() {
    global $DB;

    // Reconfigure all existing realtimequizzes to use a new realtimequizaccess_seb.
    $params = ['browsersecurity' => 'safebrowser'];

    $total = $DB->count_records('realtimequiz', $params);
    if ($total > 0) {
        $rs = $DB->get_recordset('realtimequiz', $params);

        $i = 0;
        $pbar = new progress_bar('updaterealtimequizrecords', 500, true);

        foreach ($rs as $realtimequiz) {
            if (!$DB->record_exists('rtqaccess_seb_rtqsettings', ['realtimequizid' => $realtimequiz->id])) {
                $cm = get_coursemodule_from_instance('realtimequiz', $realtimequiz->id, $realtimequiz->course);

                $sebsettings = new stdClass();

                $sebsettings->realtimequizid = $realtimequiz->id;
                $sebsettings->cmid = $cm->id;
                $sebsettings->templateid = 0;
                $sebsettings->requiresafeexambrowser = \realtimequizaccess_seb\settings_provider::USE_SEB_CLIENT_CONFIG;
                $sebsettings->showsebtaskbar = null;
                $sebsettings->showwificontrol = null;
                $sebsettings->showreloadbutton = null;
                $sebsettings->showtime = null;
                $sebsettings->showkeyboardlayout = null;
                $sebsettings->allowuserquitseb = null;
                $sebsettings->quitpassword = null;
                $sebsettings->linkquitseb = null;
                $sebsettings->userconfirmquit = null;
                $sebsettings->enableaudiocontrol = null;
                $sebsettings->muteonstartup = null;
                $sebsettings->allowspellchecking = null;
                $sebsettings->allowreloadinexam = null;
                $sebsettings->activateurlfiltering = null;
                $sebsettings->filterembeddedcontent = null;
                $sebsettings->expressionsallowed = null;
                $sebsettings->regexallowed = null;
                $sebsettings->expressionsblocked = null;
                $sebsettings->regexblocked = null;
                $sebsettings->allowedbrowserexamkeys = null;
                $sebsettings->showsebdownloadlink = 1;
                $sebsettings->usermodified = get_admin()->id;
                $sebsettings->timecreated = time();
                $sebsettings->timemodified = time();

                $DB->insert_record('rtqaccess_seb_rtqsettings', $sebsettings);

                $realtimequiz->browsersecurity = '-';
                $DB->update_record('realtimequiz', $realtimequiz);
            }

            $i++;
            $pbar->update($i, $total, "Reconfiguring existing realtimequizzes to use a new SEB plugin - $i/$total.");
        }

        $rs->close();
    }

    return true;
}
