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
 * @package    mod_realtimequiz
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/realtimequiz/backup/moodle2/restore_realtimequiz_stepslib.php');


/**
 * realtimequiz restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_realtimequiz_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Quiz only has one structure step.
        $this->add_step(new restore_realtimequiz_activity_structure_step('realtimequiz_structure', 'realtimequiz.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('realtimequiz', ['intro'], 'realtimequiz');
        $contents[] = new restore_decode_content('realtimequiz_feedback',
                ['feedbacktext'], 'realtimequiz_feedback');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('QUIZVIEWBYID',
                '/mod/realtimequiz/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('QUIZVIEWBYQ',
                '/mod/realtimequiz/view.php?q=$1', 'realtimequiz');
        $rules[] = new restore_decode_rule('QUIZINDEX',
                '/mod/realtimequiz/index.php?id=$1', 'course');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * realtimequiz logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('realtimequiz', 'add',
                'view.php?id={course_module}', '{realtimequiz}');
        $rules[] = new restore_log_rule('realtimequiz', 'update',
                'view.php?id={course_module}', '{realtimequiz}');
        $rules[] = new restore_log_rule('realtimequiz', 'view',
                'view.php?id={course_module}', '{realtimequiz}');
        $rules[] = new restore_log_rule('realtimequiz', 'preview',
                'view.php?id={course_module}', '{realtimequiz}');
        $rules[] = new restore_log_rule('realtimequiz', 'report',
                'report.php?id={course_module}', '{realtimequiz}');
        $rules[] = new restore_log_rule('realtimequiz', 'editquestions',
                'view.php?id={course_module}', '{realtimequiz}');
        $rules[] = new restore_log_rule('realtimequiz', 'delete attempt',
                'report.php?id={course_module}', '[oldattempt]');
        $rules[] = new restore_log_rule('realtimequiz', 'edit override',
                'overrideedit.php?id={realtimequiz_override}', '{realtimequiz}');
        $rules[] = new restore_log_rule('realtimequiz', 'delete override',
                'overrides.php.php?cmid={course_module}', '{realtimequiz}');
        $rules[] = new restore_log_rule('realtimequiz', 'addcategory',
                'view.php?id={course_module}', '{question_category}');
        $rules[] = new restore_log_rule('realtimequiz', 'view summary',
                'summary.php?attempt={realtimequiz_attempt}', '{realtimequiz}');
        $rules[] = new restore_log_rule('realtimequiz', 'manualgrade',
                'comment.php?attempt={realtimequiz_attempt}&question={question}', '{realtimequiz}');
        $rules[] = new restore_log_rule('realtimequiz', 'manualgrading',
                'report.php?mode=grading&q={realtimequiz}', '{realtimequiz}');
        // All the ones calling to review.php have two rules to handle both old and new urls
        // in any case they are always converted to new urls on restore.
        // TODO: In Moodle 2.x (x >= 5) kill the old rules.
        // Note we are using the 'realtimequiz_attempt' mapping because that is the
        // one containing the realtimequiz_attempt->ids old an new for realtimequiz-attempt.
        $rules[] = new restore_log_rule('realtimequiz', 'attempt',
                'review.php?id={course_module}&attempt={realtimequiz_attempt}', '{realtimequiz}',
                null, null, 'review.php?attempt={realtimequiz_attempt}');
        $rules[] = new restore_log_rule('realtimequiz', 'attempt',
                'review.php?attempt={realtimequiz_attempt}', '{realtimequiz}',
                null, null, 'review.php?attempt={realtimequiz_attempt}');
        // Old an new for realtimequiz-submit.
        $rules[] = new restore_log_rule('realtimequiz', 'submit',
                'review.php?id={course_module}&attempt={realtimequiz_attempt}', '{realtimequiz}',
                null, null, 'review.php?attempt={realtimequiz_attempt}');
        $rules[] = new restore_log_rule('realtimequiz', 'submit',
                'review.php?attempt={realtimequiz_attempt}', '{realtimequiz}');
        // Old an new for realtimequiz-review.
        $rules[] = new restore_log_rule('realtimequiz', 'review',
                'review.php?id={course_module}&attempt={realtimequiz_attempt}', '{realtimequiz}',
                null, null, 'review.php?attempt={realtimequiz_attempt}');
        $rules[] = new restore_log_rule('realtimequiz', 'review',
                'review.php?attempt={realtimequiz_attempt}', '{realtimequiz}');
        // Old an new for realtimequiz-start attemp.
        $rules[] = new restore_log_rule('realtimequiz', 'start attempt',
                'review.php?id={course_module}&attempt={realtimequiz_attempt}', '{realtimequiz}',
                null, null, 'review.php?attempt={realtimequiz_attempt}');
        $rules[] = new restore_log_rule('realtimequiz', 'start attempt',
                'review.php?attempt={realtimequiz_attempt}', '{realtimequiz}');
        // Old an new for realtimequiz-close attemp.
        $rules[] = new restore_log_rule('realtimequiz', 'close attempt',
                'review.php?id={course_module}&attempt={realtimequiz_attempt}', '{realtimequiz}',
                null, null, 'review.php?attempt={realtimequiz_attempt}');
        $rules[] = new restore_log_rule('realtimequiz', 'close attempt',
                'review.php?attempt={realtimequiz_attempt}', '{realtimequiz}');
        // Old an new for realtimequiz-continue attempt.
        $rules[] = new restore_log_rule('realtimequiz', 'continue attempt',
                'review.php?id={course_module}&attempt={realtimequiz_attempt}', '{realtimequiz}',
                null, null, 'review.php?attempt={realtimequiz_attempt}');
        $rules[] = new restore_log_rule('realtimequiz', 'continue attempt',
                'review.php?attempt={realtimequiz_attempt}', '{realtimequiz}');
        // Old an new for realtimequiz-continue attemp.
        $rules[] = new restore_log_rule('realtimequiz', 'continue attemp',
                'review.php?id={course_module}&attempt={realtimequiz_attempt}', '{realtimequiz}',
                null, 'continue attempt', 'review.php?attempt={realtimequiz_attempt}');
        $rules[] = new restore_log_rule('realtimequiz', 'continue attemp',
                'review.php?attempt={realtimequiz_attempt}', '{realtimequiz}',
                null, 'continue attempt');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('realtimequiz', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
