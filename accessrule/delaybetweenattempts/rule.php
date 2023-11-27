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
 * A rule imposing the delay between attempts settings.
 *
 * @package   realtimequizaccess_delaybetweenattempts
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtimequizaccess_delaybetweenattempts extends access_rule_base {

    public static function make(realtimequiz_settings $realtimequizobj, $timenow, $canignoretimelimits) {
        if (empty($realtimequizobj->get_realtimequiz()->delay1) && empty($realtimequizobj->get_realtimequiz()->delay2)) {
            return null;
        }

        return new self($realtimequizobj, $timenow);
    }

    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        if ($this->realtimequiz->attempts > 0 && $numprevattempts >= $this->realtimequiz->attempts) {
            // No more attempts allowed anyway.
            return false;
        }
        if ($this->realtimequiz->timeclose != 0 && $this->timenow > $this->realtimequiz->timeclose) {
            // No more attempts allowed anyway.
            return false;
        }
        $nextstarttime = $this->compute_next_start_time($numprevattempts, $lastattempt);
        if ($this->timenow < $nextstarttime) {
            if ($this->realtimequiz->timeclose == 0 || $nextstarttime <= $this->realtimequiz->timeclose) {
                return get_string('youmustwait', 'realtimequizaccess_delaybetweenattempts',
                        userdate($nextstarttime));
            } else {
                return get_string('youcannotwait', 'realtimequizaccess_delaybetweenattempts');
            }
        }
        return false;
    }

    /**
     * Compute the next time a student would be allowed to start an attempt,
     * according to this rule.
     * @param int $numprevattempts number of previous attempts.
     * @param stdClass $lastattempt information about the previous attempt.
     * @return number the time.
     */
    protected function compute_next_start_time($numprevattempts, $lastattempt) {
        if ($numprevattempts == 0) {
            return 0;
        }

        $lastattemptfinish = $lastattempt->timefinish;
        if ($this->realtimequiz->timelimit > 0) {
            $lastattemptfinish = min($lastattemptfinish,
                    $lastattempt->timestart + $this->realtimequiz->timelimit);
        }

        if ($numprevattempts == 1 && $this->realtimequiz->delay1) {
            return $lastattemptfinish + $this->realtimequiz->delay1;
        } else if ($numprevattempts > 1 && $this->realtimequiz->delay2) {
            return $lastattemptfinish + $this->realtimequiz->delay2;
        }
        return 0;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        $nextstarttime = $this->compute_next_start_time($numprevattempts, $lastattempt);
        return $this->timenow <= $nextstarttime &&
        $this->realtimequiz->timeclose != 0 && $nextstarttime >= $this->realtimequiz->timeclose;
    }
}
