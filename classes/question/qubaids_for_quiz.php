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

namespace mod_realtimequiz\question;

use mod_realtimequiz\realtimequiz_attempt;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/datalib.php');

/**
 * A {@see qubaid_condition} for finding all the question usages belonging to a particular realtimequiz.
 *
 * @package   mod_realtimequiz
 * @category  question
 * @copyright 2010 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qubaids_for_realtimequiz extends \qubaid_join {

    /**
     * Constructor.
     *
     * @param int $realtimequizid The realtimequiz to search.
     * @param bool $includepreviews Whether to include preview attempts
     * @param bool $onlyfinished Whether to only include finished attempts or not
     */
    public function __construct(int $realtimequizid, bool $includepreviews = true, bool $onlyfinished = false) {
        $where = 'realtimequiza.realtimequiz = :realtimequizarealtimequiz';
        $params = ['realtimequizarealtimequiz' => $realtimequizid];

        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }

        if ($onlyfinished) {
            $where .= ' AND state = :statefinished';
            $params['statefinished'] = realtimequiz_attempt::FINISHED;
        }

        parent::__construct('{realtimequiz_attempts} realtimequiza', 'realtimequiza.uniqueid', $where, $params);
    }
}
