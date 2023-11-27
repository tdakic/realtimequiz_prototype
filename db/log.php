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
 * Definition of log events for the realtimequiz module.
 *
 * @package    mod_realtimequiz
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = [
    ['module' => 'realtimequiz', 'action' => 'add', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'update', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'view', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'report', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'attempt', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'submit', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'review', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'editquestions', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'preview', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'start attempt', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'close attempt', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'continue attempt', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'edit override', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'delete override', 'mtable' => 'realtimequiz', 'field' => 'name'],
    ['module' => 'realtimequiz', 'action' => 'view summary', 'mtable' => 'realtimequiz', 'field' => 'name'],
];
