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
 * This file defines the realtimequiz responses report class.
 *
 * @package   realtimequiz_responses
 * @copyright 2006 Jean-Michel Vedrine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\local\reports\attempts_report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/realtimequiz/report/responses/responses_options.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/responses/responses_form.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/responses/last_responses_table.php');
require_once($CFG->dirroot . '/mod/realtimequiz/report/responses/first_or_all_responses_table.php');


/**
 * Quiz report subclass for the responses report.
 *
 * This report lists some combination of
 *  * what question each student saw (this makes sense if random questions were used).
 *  * the response they gave,
 *  * and what the right answer is.
 *
 * Like the overview report, there are options for showing students with/without
 * attempts, and for deleting selected attempts.
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtimequiz_responses_report extends attempts_report {

    public function display($realtimequiz, $cm, $course) {
        global $OUTPUT, $DB;

        list($currentgroup, $studentsjoins, $groupstudentsjoins, $allowedjoins) = $this->init(
                'responses', 'realtimequiz_responses_settings_form', $realtimequiz, $cm, $course);

        $options = new realtimequiz_responses_options('responses', $realtimequiz, $cm, $course);

        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);

        } else {
            $options->process_settings_from_params();
        }

        $this->form->set_data($options->get_initial_form_data());

        // Load the required questions.
        $questions = realtimequiz_report_get_significant_questions($realtimequiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                ['context' => context_course::instance($course->id)]);
        if ($options->whichtries === question_attempt::LAST_TRY) {
            $tableclassname = 'realtimequiz_last_responses_table';
        } else {
            $tableclassname = 'realtimequiz_first_or_all_responses_table';
        }
        $table = new $tableclassname($realtimequiz, $this->context, $this->qmsubselect,
                $options, $groupstudentsjoins, $studentsjoins, $questions, $options->get_url());
        $filename = realtimequiz_report_download_filename(get_string('responsesfilename', 'realtimequiz_responses'),
                $courseshortname, $realtimequiz->name);
        $table->is_downloading($options->download, $filename,
                $courseshortname . ' ' . format_string($realtimequiz->name, true));
        if ($table->is_downloading()) {
            raise_memory_limit(MEMORY_EXTRA);
        }

        $this->hasgroupstudents = false;
        if (!empty($groupstudentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                      FROM {user} u
                    $groupstudentsjoins->joins
                     WHERE $groupstudentsjoins->wheres";
            $this->hasgroupstudents = $DB->record_exists_sql($sql, $groupstudentsjoins->params);
        }
        $hasstudents = false;
        if (!empty($studentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                    FROM {user} u
                    $studentsjoins->joins
                    WHERE $studentsjoins->wheres";
            $hasstudents = $DB->record_exists_sql($sql, $studentsjoins->params);
        }
        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all realtimequiz attempts
            // are accessible, is not a security problem.
            $allowedjoins = new \core\dml\sql_join();
        }

        $this->process_actions($realtimequiz, $cm, $currentgroup, $groupstudentsjoins, $allowedjoins, $options->get_url());

        $hasquestions = realtimequiz_has_questions($realtimequiz->id);

        // Start output.
        if (!$table->is_downloading()) {
            // Only print headers if not asked to download data.
            $this->print_standard_header_and_messages($cm, $course, $realtimequiz,
                    $options, $currentgroup, $hasquestions, $hasstudents);

            // Print the display options.
            $this->form->display();
        }

        $hasstudents = $hasstudents && (!$currentgroup || $this->hasgroupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {

            $table->setup_sql_queries($allowedjoins);

            if (!$table->is_downloading()) {
                // Print information on the grading method.
                if ($strattempthighlight = realtimequiz_report_highlighting_grading_method(
                        $realtimequiz, $this->qmsubselect, $options->onlygraded)) {
                    echo '<div class="realtimequizattemptcounts">' . $strattempthighlight . '</div>';
                }
            }

            // Define table columns.
            $columns = [];
            $headers = [];

            if (!$table->is_downloading() && $options->checkboxcolumn) {
                $columnname = 'checkbox';
                $columns[] = $columnname;
                $headers[] = $table->checkbox_col_header($columnname);
            }

            $this->add_user_columns($table, $columns, $headers);
            $this->add_state_column($columns, $headers);

            if ($table->is_downloading()) {
                $this->add_time_columns($columns, $headers);
            }

            $this->add_grade_columns($realtimequiz, $options->usercanseegrades, $columns, $headers);

            foreach ($questions as $id => $question) {
                if ($options->showqtext) {
                    $columns[] = 'question' . $id;
                    $headers[] = get_string('questionx', 'question', $question->number);
                }
                if ($options->showresponses) {
                    $columns[] = 'response' . $id;
                    $headers[] = get_string('responsex', 'realtimequiz_responses', $question->number);
                }
                if ($options->showright) {
                    $columns[] = 'right' . $id;
                    $headers[] = get_string('rightanswerx', 'realtimequiz_responses', $question->number);
                }
            }

            $table->define_columns($columns);
            $table->define_headers($headers);
            $table->sortable(true, 'uniqueid');

            // Set up the table.
            $table->define_baseurl($options->get_url());

            $this->configure_user_columns($table);

            $table->no_sorting('feedbacktext');
            $table->column_class('sumgrades', 'bold');

            $table->set_attribute('id', 'responses');

            $table->collapsible(true);

            $table->out($options->pagesize, true);
        }
        return true;
    }

//TTT added the following which is just a copy of the above function
//it is added beacuse the function with the same name was added to overview report
//****************

public function display_final_graph($realtimequiz, $cm, $course) {
    global $OUTPUT, $DB;

    list($currentgroup, $studentsjoins, $groupstudentsjoins, $allowedjoins) = $this->init(
            'responses', 'realtimequiz_responses_settings_form', $realtimequiz, $cm, $course);

    $options = new realtimequiz_responses_options('responses', $realtimequiz, $cm, $course);

    if ($fromform = $this->form->get_data()) {
        $options->process_settings_from_form($fromform);

    } else {
        $options->process_settings_from_params();
    }

    $this->form->set_data($options->get_initial_form_data());

    // Load the required questions.
    $questions = realtimequiz_report_get_significant_questions($realtimequiz);

    // Prepare for downloading, if applicable.
    $courseshortname = format_string($course->shortname, true,
            ['context' => context_course::instance($course->id)]);
    if ($options->whichtries === question_attempt::LAST_TRY) {
        $tableclassname = 'realtimequiz_last_responses_table';
    } else {
        $tableclassname = 'realtimequiz_first_or_all_responses_table';
    }
    $table = new $tableclassname($realtimequiz, $this->context, $this->qmsubselect,
            $options, $groupstudentsjoins, $studentsjoins, $questions, $options->get_url());
    $filename = realtimequiz_report_download_filename(get_string('responsesfilename', 'realtimequiz_responses'),
            $courseshortname, $realtimequiz->name);
    $table->is_downloading($options->download, $filename,
            $courseshortname . ' ' . format_string($realtimequiz->name, true));
    if ($table->is_downloading()) {
        raise_memory_limit(MEMORY_EXTRA);
    }

    $this->hasgroupstudents = false;
    if (!empty($groupstudentsjoins->joins)) {
        $sql = "SELECT DISTINCT u.id
                  FROM {user} u
                $groupstudentsjoins->joins
                 WHERE $groupstudentsjoins->wheres";
        $this->hasgroupstudents = $DB->record_exists_sql($sql, $groupstudentsjoins->params);
    }
    $hasstudents = false;
    if (!empty($studentsjoins->joins)) {
        $sql = "SELECT DISTINCT u.id
                FROM {user} u
                $studentsjoins->joins
                WHERE $studentsjoins->wheres";
        $hasstudents = $DB->record_exists_sql($sql, $studentsjoins->params);
    }
    if ($options->attempts == self::ALL_WITH) {
        // This option is only available to users who can access all groups in
        // groups mode, so setting allowed to empty (which means all realtimequiz attempts
        // are accessible, is not a security problem.
        $allowedjoins = new \core\dml\sql_join();
    }

    $this->process_actions($realtimequiz, $cm, $currentgroup, $groupstudentsjoins, $allowedjoins, $options->get_url());

    $hasquestions = realtimequiz_has_questions($realtimequiz->id);

    // Start output.
    if (!$table->is_downloading()) {
        // Only print headers if not asked to download data.
        $this->print_standard_header_and_messages($cm, $course, $realtimequiz,
                $options, $currentgroup, $hasquestions, $hasstudents);

        // Print the display options.
        $this->form->display();
    }

    $hasstudents = $hasstudents && (!$currentgroup || $this->hasgroupstudents);
    if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {

        $table->setup_sql_queries($allowedjoins);

        if (!$table->is_downloading()) {
            // Print information on the grading method.
            if ($strattempthighlight = realtimequiz_report_highlighting_grading_method(
                    $realtimequiz, $this->qmsubselect, $options->onlygraded)) {
                echo '<div class="realtimequizattemptcounts">' . $strattempthighlight . '</div>';
            }
        }

        // Define table columns.
        $columns = [];
        $headers = [];

        if (!$table->is_downloading() && $options->checkboxcolumn) {
            $columnname = 'checkbox';
            $columns[] = $columnname;
            $headers[] = $table->checkbox_col_header($columnname);
        }

        $this->add_user_columns($table, $columns, $headers);
        $this->add_state_column($columns, $headers);

        if ($table->is_downloading()) {
            $this->add_time_columns($columns, $headers);
        }

        $this->add_grade_columns($realtimequiz, $options->usercanseegrades, $columns, $headers);

        foreach ($questions as $id => $question) {
            if ($options->showqtext) {
                $columns[] = 'question' . $id;
                $headers[] = get_string('questionx', 'question', $question->number);
            }
            if ($options->showresponses) {
                $columns[] = 'response' . $id;
                $headers[] = get_string('responsex', 'realtimequiz_responses', $question->number);
            }
            if ($options->showright) {
                $columns[] = 'right' . $id;
                $headers[] = get_string('rightanswerx', 'realtimequiz_responses', $question->number);
            }
        }

        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->sortable(true, 'uniqueid');

        // Set up the table.
        $table->define_baseurl($options->get_url());

        $this->configure_user_columns($table);

        $table->no_sorting('feedbacktext');
        $table->column_class('sumgrades', 'bold');

        $table->set_attribute('id', 'responses');

        $table->collapsible(true);

        $table->out($options->pagesize, true);
    }
    return true;
}
}
