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

namespace mod_realtimequiz;

use mod_realtimequiz\question\display_options;
use mod_realtimequiz\realtimequiz_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

/**
 * Unit tests for the realtimequiz class
 *
 * @package    mod_realtimequiz
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtimequizobj_test extends \basic_testcase {
    public function test_cannot_review_message() {
        $realtimequiz = new \stdClass();
        $realtimequiz->reviewattempt = 0x10010;
        $realtimequiz->timeclose = 0;
        $realtimequiz->attempts = 0;

        $cm = new \stdClass();
        $cm->id = 123;

        $realtimequizobj = new realtimequiz_settings($realtimequiz, $cm, new \stdClass(), false);

        $this->assertEquals('',
            $realtimequizobj->cannot_review_message(display_options::DURING));
        $this->assertEquals('',
            $realtimequizobj->cannot_review_message(display_options::IMMEDIATELY_AFTER));
        $this->assertEquals(get_string('noreview', 'realtimequiz'),
            $realtimequizobj->cannot_review_message(display_options::LATER_WHILE_OPEN));
        $this->assertEquals(get_string('noreview', 'realtimequiz'),
            $realtimequizobj->cannot_review_message(display_options::AFTER_CLOSE));

        $closetime = time() + 10000;
        $realtimequiz->timeclose = $closetime;
        $realtimequizobj = new realtimequiz_settings($realtimequiz, $cm, new \stdClass(), false);

        $this->assertEquals(get_string('noreviewuntil', 'realtimequiz', userdate($closetime)),
            $realtimequizobj->cannot_review_message(display_options::LATER_WHILE_OPEN));
    }
}
