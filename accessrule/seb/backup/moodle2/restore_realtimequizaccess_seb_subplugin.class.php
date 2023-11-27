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
 * Restore instructions for the seb (Safe Exam Browser) realtimequiz access subplugin.
 *
 * @package    realtimequizaccess_seb
 * @category   backup
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2020 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use realtimequizaccess_seb\seb_realtimequiz_settings;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/realtimequiz/backup/moodle2/restore_mod_realtimequiz_access_subplugin.class.php');

/**
 * Restore instructions for the seb (Safe Exam Browser) realtimequiz access subplugin.
 *
 * @copyright  2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_realtimequizaccess_seb_subplugin extends restore_mod_realtimequiz_access_subplugin {

    /**
     * Provides path structure required to restore data for seb realtimequiz access plugin.
     *
     * @return array
     */
    protected function define_realtimequiz_subplugin_structure() {
        $paths = [];

        // Quiz settings.
        $path = $this->get_pathfor('/rtqaccess_seb_rtqsettings'); // Subplugin root path.
        $paths[] = new restore_path_element('rtqaccess_seb_rtqsettings', $path);

        // Template settings.
        $path = $this->get_pathfor('/rtqaccess_seb_rtqsettings/rtqaccess_seb_template');
        $paths[] = new restore_path_element('rtqaccess_seb_template', $path);

        return $paths;
    }

    /**
     * Process the restored data for the rtqaccess_seb_rtqsettings table.
     *
     * @param stdClass $data Data for rtqaccess_seb_rtqsettings retrieved from backup xml.
     */
    public function process_rtqaccess_seb_rtqsettings($data) {
        global $DB, $USER;

        // Process realtimequizsettings.
        $data = (object) $data;
        $data->realtimequizid = $this->get_new_parentid('realtimequiz'); // Update realtimequizid with new reference.
        $data->cmid = $this->task->get_moduleid();

        unset($data->id);
        $data->timecreated = $data->timemodified = time();
        $data->usermodified = $USER->id;
        $DB->insert_record(realtimequizaccess_seb\seb_realtimequiz_settings::TABLE, $data);

        // Process attached files.
        $this->add_related_files('realtimequizaccess_seb', 'filemanager_sebconfigfile', null);
    }

    /**
     * Process the restored data for the rtqaccess_seb_template table.
     *
     * @param stdClass $data Data for rtqaccess_seb_template retrieved from backup xml.
     */
    public function process_rtqaccess_seb_template($data) {
        global $DB;

        $data = (object) $data;

        $realtimequizid = $this->get_new_parentid('realtimequiz');

        $template = null;
        if ($this->task->is_samesite()) {
            $template = \realtimequizaccess_seb\template::get_record(['id' => $data->id]);
        } else {
            // In a different site, try to find existing template with the same name and content.
            $candidates = \realtimequizaccess_seb\template::get_records(['name' => $data->name]);
            foreach ($candidates as $candidate) {
                if ($candidate->get('content') == $data->content) {
                    $template = $candidate;
                    break;
                }
            }
        }

        if (empty($template)) {
            unset($data->id);
            $template = new \realtimequizaccess_seb\template(0, $data);
            $template->save();
        }

        // Update the restored realtimequiz settings to use restored template.
        $DB->set_field(\realtimequizaccess_seb\seb_realtimequiz_settings::TABLE, 'templateid', $template->get('id'), ['realtimequizid' => $realtimequizid]);
    }

}
