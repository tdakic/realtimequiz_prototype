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
 * Rest endpoint for ajax editing for paging operations on the realtimequiz structure.
 *
 * @package   mod_realtimequiz
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\realtimequiz_settings;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

$realtimequizid = required_param('realtimequizid', PARAM_INT);
$slotnumber = required_param('slot', PARAM_INT);
$repagtype = required_param('repag', PARAM_INT);

require_sesskey();
$realtimequizobj = realtimequiz_settings::create($realtimequizid);
require_login($realtimequizobj->get_course(), false, $realtimequizobj->get_cm());
require_capability('mod/realtimequiz:manage', $realtimequizobj->get_context());
if (realtimequiz_has_attempts($realtimequizid)) {
    $reportlink = realtimequiz_attempt_summary_link_to_reports($realtimequizobj->get_realtimequiz(),
                    $realtimequizobj->get_cm(), $realtimequizobj->get_context());
    throw new \moodle_exception('cannoteditafterattempts', 'realtimequiz',
            new moodle_url('/mod/realtimequiz/edit.php', ['cmid' => $realtimequizobj->get_cmid()]), $reportlink);
}

$slotnumber++;
$repage = new \mod_realtimequiz\repaginate($realtimequizid);
$repage->repaginate_slots($slotnumber, $repagtype);

$structure = $realtimequizobj->get_structure();
$slots = $structure->refresh_page_numbers_and_update_db();

redirect(new moodle_url('edit.php', ['cmid' => $realtimequizobj->get_cmid()]));
