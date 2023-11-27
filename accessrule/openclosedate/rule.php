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
 * A rule enforcing open and close dates.
 *
 * @package   realtimequizaccess_openclosedate
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtimequizaccess_openclosedate extends access_rule_base {

    public static function make(realtimequiz_settings $realtimequizobj, $timenow, $canignoretimelimits) {
        // This rule is always used, even if the realtimequiz has no open or close date.
        return new self($realtimequizobj, $timenow);
    }

    public function prevent_access() {
        $message = get_string('notavailable', 'realtimequizaccess_openclosedate');

        if ($this->timenow < $this->realtimequiz->timeopen) {
            return $message;
        }

        if (!$this->realtimequiz->timeclose) {
            return false;
        }

        if ($this->timenow <= $this->realtimequiz->timeclose) {
            return false;
        }

        if ($this->realtimequiz->overduehandling != 'graceperiod') {
            return $message;
        }

        if ($this->timenow <= $this->realtimequiz->timeclose + $this->realtimequiz->graceperiod) {
            return false;
        }

        return $message;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        return $this->realtimequiz->timeclose && $this->timenow > $this->realtimequiz->timeclose;
    }

    public function end_time($attempt) {
        if ($this->realtimequiz->timeclose) {
            return $this->realtimequiz->timeclose;
        }
        return false;
    }

    public function time_left_display($attempt, $timenow) {
        // If this is a teacher preview after the close date, do not show
        // the time.
        if ($attempt->preview && $timenow > $this->realtimequiz->timeclose) {
            return false;
        }
        // Otherwise, return to the time left until the close date, providing that is
        // less than QUIZ_SHOW_TIME_BEFORE_DEADLINE.
        $endtime = $this->end_time($attempt);
        if ($endtime !== false && $timenow > $endtime - QUIZ_SHOW_TIME_BEFORE_DEADLINE) {
            return $endtime - $timenow;
        }
        return false;
    }
}
