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
 * Privacy Subsystem implementation for mod_realtimequiz.
 *
 * @package    mod_realtimequiz
 * @category   privacy
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_realtimequiz\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\transform;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\manager;
use mod_realtimequiz\realtimequiz_attempt;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/realtimequiz/lib.php');
require_once($CFG->dirroot . '/mod/realtimequiz/locallib.php');

/**
 * Privacy Subsystem implementation for mod_realtimequiz.
 *
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   collection  $items  The collection to add metadata to.
     * @return  collection  The array of metadata
     */
    public static function get_metadata(collection $items) : collection {
        // The table 'realtimequiz' stores a record for each realtimequiz.
        // It does not contain user personal data, but data is returned from it for contextual requirements.

        // The table 'realtimequiz_attempts' stores a record of each realtimequiz attempt.
        // It contains a userid which links to the user making the attempt and contains information about that attempt.
        $items->add_database_table('realtimequiz_attempts', [
                'attempt'                    => 'privacy:metadata:realtimequiz_attempts:attempt',
                'currentpage'                => 'privacy:metadata:realtimequiz_attempts:currentpage',
                'preview'                    => 'privacy:metadata:realtimequiz_attempts:preview',
                'state'                      => 'privacy:metadata:realtimequiz_attempts:state',
                'timestart'                  => 'privacy:metadata:realtimequiz_attempts:timestart',
                'timefinish'                 => 'privacy:metadata:realtimequiz_attempts:timefinish',
                'timemodified'               => 'privacy:metadata:realtimequiz_attempts:timemodified',
                'timemodifiedoffline'        => 'privacy:metadata:realtimequiz_attempts:timemodifiedoffline',
                'timecheckstate'             => 'privacy:metadata:realtimequiz_attempts:timecheckstate',
                'sumgrades'                  => 'privacy:metadata:realtimequiz_attempts:sumgrades',
                'gradednotificationsenttime' => 'privacy:metadata:realtimequiz_attempts:gradednotificationsenttime',
            ], 'privacy:metadata:realtimequiz_attempts');

        // The table 'realtimequiz_feedback' contains the feedback responses which will be shown to users depending upon the
        // grade they achieve in the realtimequiz.
        // It does not identify the user who wrote the feedback item so cannot be returned directly and is not
        // described, but relevant feedback items will be included with the realtimequiz export for a user who has a grade.

        // The table 'realtimequiz_grades' contains the current grade for each realtimequiz/user combination.
        $items->add_database_table('realtimequiz_grades', [
                'realtimequiz'                  => 'privacy:metadata:realtimequiz_grades:realtimequiz',
                'userid'                => 'privacy:metadata:realtimequiz_grades:userid',
                'grade'                 => 'privacy:metadata:realtimequiz_grades:grade',
                'timemodified'          => 'privacy:metadata:realtimequiz_grades:timemodified',
            ], 'privacy:metadata:realtimequiz_grades');

        // The table 'realtimequiz_overrides' contains any user or group overrides for users.
        // It should be included where data exists for a user.
        $items->add_database_table('realtimequiz_overrides', [
                'realtimequiz'                  => 'privacy:metadata:realtimequiz_overrides:realtimequiz',
                'userid'                => 'privacy:metadata:realtimequiz_overrides:userid',
                'timeopen'              => 'privacy:metadata:realtimequiz_overrides:timeopen',
                'timeclose'             => 'privacy:metadata:realtimequiz_overrides:timeclose',
                'timelimit'             => 'privacy:metadata:realtimequiz_overrides:timelimit',
            ], 'privacy:metadata:realtimequiz_overrides');

        // These define the structure of the realtimequiz.

        // The table 'realtimequiz_sections' contains data about the structure of a realtimequiz.
        // It does not contain any user identifying data and does not need a mapping.

        // The table 'realtimequiz_slots' contains data about the structure of a realtimequiz.
        // It does not contain any user identifying data and does not need a mapping.

        // The table 'realtimequiz_reports' does not contain any user identifying data and does not need a mapping.

        // The table 'realtimequiz_statistics' contains abstract statistics about question usage and cannot be mapped to any
        // specific user.
        // It does not contain any user identifying data and does not need a mapping.

        // The realtimequiz links to the 'core_question' subsystem for all question functionality.
        $items->add_subsystem_link('core_question', [], 'privacy:metadata:core_question');

        // The realtimequiz has two subplugins..
        $items->add_plugintype_link('realtimequiz', [], 'privacy:metadata:realtimequiz');
        $items->add_plugintype_link('realtimequizaccess', [], 'privacy:metadata:realtimequizaccess');

        // Although the realtimequiz supports the core_completion API and defines custom completion items, these will be
        // noted by the manager as all activity modules are capable of supporting this functionality.

        return $items;
    }

    /**
     * Get the list of contexts where the specified user has attempted a realtimequiz, or been involved with manual marking
     * and/or grading of a realtimequiz.
     *
     * @param   int             $userid The user to search.
     * @return  contextlist     $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $resultset = new contextlist();

        // Users who attempted the realtimequiz.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {realtimequiz} q ON q.id = cm.instance
                  JOIN {realtimequiz_attempts} qa ON qa.realtimequiz = q.id
                 WHERE qa.userid = :userid AND qa.preview = 0";
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'realtimequiz', 'userid' => $userid];
        $resultset->add_from_sql($sql, $params);

        // Users with realtimequiz overrides.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {realtimequiz} q ON q.id = cm.instance
                  JOIN {realtimequiz_overrides} qo ON qo.realtimequiz = q.id
                 WHERE qo.userid = :userid";
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'realtimequiz', 'userid' => $userid];
        $resultset->add_from_sql($sql, $params);

        // Get the SQL used to link indirect question usages for the user.
        // This includes where a user is the manual marker on a question attempt.
        $qubaid = \core_question\privacy\provider::get_related_question_usages_for_user('rel', 'mod_realtimequiz', 'qa.uniqueid', $userid);

        // Select the context of any realtimequiz attempt where a user has an attempt, plus the related usages.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {realtimequiz} q ON q.id = cm.instance
                  JOIN {realtimequiz_attempts} qa ON qa.realtimequiz = q.id
            " . $qubaid->from . "
            WHERE " . $qubaid->where() . " AND qa.preview = 0";
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'realtimequiz'] + $qubaid->from_where_params();
        $resultset->add_from_sql($sql, $params);

        return $resultset;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = [
            'cmid'    => $context->instanceid,
            'modname' => 'realtimequiz',
        ];

        // Users who attempted the realtimequiz.
        $sql = "SELECT qa.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {realtimequiz} q ON q.id = cm.instance
                  JOIN {realtimequiz_attempts} qa ON qa.realtimequiz = q.id
                 WHERE cm.id = :cmid AND qa.preview = 0";
        $userlist->add_from_sql('userid', $sql, $params);

        // Users with realtimequiz overrides.
        $sql = "SELECT qo.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {realtimequiz} q ON q.id = cm.instance
                  JOIN {realtimequiz_overrides} qo ON qo.realtimequiz = q.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Question usages in context.
        // This includes where a user is the manual marker on a question attempt.
        $sql = "SELECT qa.uniqueid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {realtimequiz} q ON q.id = cm.instance
                  JOIN {realtimequiz_attempts} qa ON qa.realtimequiz = q.id
                 WHERE cm.id = :cmid AND qa.preview = 0";
        \core_question\privacy\provider::get_users_in_context_from_sql($userlist, 'qn', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!count($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    q.*,
                    qg.id AS hasgrade,
                    qg.grade AS bestgrade,
                    qg.timemodified AS grademodified,
                    qo.id AS hasoverride,
                    qo.timeopen AS override_timeopen,
                    qo.timeclose AS override_timeclose,
                    qo.timelimit AS override_timelimit,
                    c.id AS contextid,
                    cm.id AS cmid
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {realtimequiz} q ON q.id = cm.instance
             LEFT JOIN {realtimequiz_overrides} qo ON qo.realtimequiz = q.id AND qo.userid = :qouserid
             LEFT JOIN {realtimequiz_grades} qg ON qg.realtimequiz = q.id AND qg.userid = :qguserid
                 WHERE c.id {$contextsql}";

        $params = [
            'contextlevel'      => CONTEXT_MODULE,
            'modname'           => 'realtimequiz',
            'qguserid'          => $userid,
            'qouserid'          => $userid,
        ];
        $params += $contextparams;

        // Fetch the individual realtimequizzes.
        $realtimequizzes = $DB->get_recordset_sql($sql, $params);
        foreach ($realtimequizzes as $realtimequiz) {
            list($course, $cm) = get_course_and_cm_from_cmid($realtimequiz->cmid, 'realtimequiz');
            $realtimequizobj = new \mod_realtimequiz\realtimequiz_settings($realtimequiz, $cm, $course);
            $context = $realtimequizobj->get_context();

            $realtimequizdata = \core_privacy\local\request\helper::get_context_data($context, $contextlist->get_user());
            \core_privacy\local\request\helper::export_context_files($context, $contextlist->get_user());

            if (!empty($realtimequizdata->timeopen)) {
                $realtimequizdata->timeopen = transform::datetime($realtimequiz->timeopen);
            }
            if (!empty($realtimequizdata->timeclose)) {
                $realtimequizdata->timeclose = transform::datetime($realtimequiz->timeclose);
            }
            if (!empty($realtimequizdata->timelimit)) {
                $realtimequizdata->timelimit = $realtimequiz->timelimit;
            }

            if (!empty($realtimequiz->hasoverride)) {
                $realtimequizdata->override = (object) [];

                if (!empty($realtimequizdata->override_override_timeopen)) {
                    $realtimequizdata->override->timeopen = transform::datetime($realtimequiz->override_timeopen);
                }
                if (!empty($realtimequizdata->override_timeclose)) {
                    $realtimequizdata->override->timeclose = transform::datetime($realtimequiz->override_timeclose);
                }
                if (!empty($realtimequizdata->override_timelimit)) {
                    $realtimequizdata->override->timelimit = $realtimequiz->override_timelimit;
                }
            }

            $realtimequizdata->accessdata = (object) [];

            $components = \core_component::get_plugin_list('realtimequizaccess');
            $exportparams = [
                    $realtimequizobj,
                    $user,
                ];
            foreach (array_keys($components) as $component) {
                $classname = manager::get_provider_classname_for_component("realtimequizaccess_$component");
                if (class_exists($classname) && is_subclass_of($classname, realtimequizaccess_provider::class)) {
                    $result = component_class_callback($classname, 'export_realtimequizaccess_user_data', $exportparams);
                    if (count((array) $result)) {
                        $realtimequizdata->accessdata->$component = $result;
                    }
                }
            }

            if (empty((array) $realtimequizdata->accessdata)) {
                unset($realtimequizdata->accessdata);
            }

            writer::with_context($context)
                ->export_data([], $realtimequizdata);
        }
        $realtimequizzes->close();

        // Store all realtimequiz attempt data.
        static::export_realtimequiz_attempts($contextlist);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context                 $context   The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel != CONTEXT_MODULE) {
            // Only realtimequiz module will be handled.
            return;
        }

        $cm = get_coursemodule_from_id('realtimequiz', $context->instanceid);
        if (!$cm) {
            // Only realtimequiz module will be handled.
            return;
        }

        $realtimequizobj = \mod_realtimequiz\realtimequiz_settings::create($cm->instance);
        $realtimequiz = $realtimequizobj->get_realtimequiz();

        // Handle the 'realtimequizaccess' subplugin.
        manager::plugintype_class_callback(
                'realtimequizaccess',
                realtimequizaccess_provider::class,
                'delete_subplugin_data_for_all_users_in_context',
                [$realtimequizobj]
            );

        // Delete all overrides - do not log.
        realtimequiz_delete_all_overrides($realtimequiz, false);

        // This will delete all question attempts, realtimequiz attempts, and realtimequiz grades for this realtimequiz.
        realtimequiz_delete_all_attempts($realtimequiz);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
            // Only realtimequiz module will be handled.
                continue;
            }

            $cm = get_coursemodule_from_id('realtimequiz', $context->instanceid);
            if (!$cm) {
                // Only realtimequiz module will be handled.
                continue;
            }

            // Fetch the details of the data to be removed.
            $realtimequizobj = \mod_realtimequiz\realtimequiz_settings::create($cm->instance);
            $realtimequiz = $realtimequizobj->get_realtimequiz();
            $user = $contextlist->get_user();

            // Handle the 'realtimequizaccess' realtimequizaccess.
            manager::plugintype_class_callback(
                    'realtimequizaccess',
                    realtimequizaccess_provider::class,
                    'delete_realtimequizaccess_data_for_user',
                    [$realtimequizobj, $user]
                );

            // Remove overrides for this user.
            $overrides = $DB->get_records('realtimequiz_overrides' , [
                'realtimequiz' => $realtimequizobj->get_realtimequizid(),
                'userid' => $user->id,
            ]);

            foreach ($overrides as $override) {
                realtimequiz_delete_override($realtimequiz, $override->id, false);
            }

            // This will delete all question attempts, realtimequiz attempts, and realtimequiz grades for this realtimequiz.
            realtimequiz_delete_user_attempts($realtimequizobj, $user);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            // Only realtimequiz module will be handled.
            return;
        }

        $cm = get_coursemodule_from_id('realtimequiz', $context->instanceid);
        if (!$cm) {
            // Only realtimequiz module will be handled.
            return;
        }

        $realtimequizobj = \mod_realtimequiz\realtimequiz_settings::create($cm->instance);
        $realtimequiz = $realtimequizobj->get_realtimequiz();

        $userids = $userlist->get_userids();

        // Handle the 'realtimequizaccess' realtimequizaccess.
        manager::plugintype_class_callback(
                'realtimequizaccess',
                realtimequizaccess_user_provider::class,
                'delete_realtimequizaccess_data_for_users',
                [$userlist]
        );

        foreach ($userids as $userid) {
            // Remove overrides for this user.
            $overrides = $DB->get_records('realtimequiz_overrides' , [
                'realtimequiz' => $realtimequizobj->get_realtimequizid(),
                'userid' => $userid,
            ]);

            foreach ($overrides as $override) {
                realtimequiz_delete_override($realtimequiz, $override->id, false);
            }

            // This will delete all question attempts, realtimequiz attempts, and realtimequiz grades for this user in the given realtimequiz.
            realtimequiz_delete_user_attempts($realtimequizobj, (object)['id' => $userid]);
        }
    }

    /**
     * Store all realtimequiz attempts for the contextlist.
     *
     * @param   approved_contextlist    $contextlist
     */
    protected static function export_realtimequiz_attempts(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $qubaid = \core_question\privacy\provider::get_related_question_usages_for_user('rel', 'mod_realtimequiz', 'qa.uniqueid', $userid);

        $sql = "SELECT
                    c.id AS contextid,
                    cm.id AS cmid,
                    qa.*
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'realtimequiz'
                  JOIN {realtimequiz} q ON q.id = cm.instance
                  JOIN {realtimequiz_attempts} qa ON qa.realtimequiz = q.id
            " . $qubaid->from. "
            WHERE (
                qa.userid = :qauserid OR
                " . $qubaid->where() . "
            ) AND qa.preview = 0
        ";

        $params = array_merge(
                [
                    'contextlevel'      => CONTEXT_MODULE,
                    'qauserid'          => $userid,
                ],
                $qubaid->from_where_params()
            );

        $attempts = $DB->get_recordset_sql($sql, $params);
        foreach ($attempts as $attempt) {
            $realtimequiz = $DB->get_record('realtimequiz', ['id' => $attempt->realtimequiz]);
            $context = \context_module::instance($attempt->cmid);
            $attemptsubcontext = helper::get_realtimequiz_attempt_subcontext($attempt, $contextlist->get_user());
            $options = realtimequiz_get_review_options($realtimequiz, $attempt, $context);

            if ($attempt->userid == $userid) {
                // This attempt was made by the user.
                // They 'own' all data on it.
                // Store the question usage data.
                \core_question\privacy\provider::export_question_usage($userid,
                        $context,
                        $attemptsubcontext,
                        $attempt->uniqueid,
                        $options,
                        true
                    );

                // Store the realtimequiz attempt data.
                $data = (object) [
                    'state' => realtimequiz_attempt::state_name($attempt->state),
                ];

                if (!empty($attempt->timestart)) {
                    $data->timestart = transform::datetime($attempt->timestart);
                }
                if (!empty($attempt->timefinish)) {
                    $data->timefinish = transform::datetime($attempt->timefinish);
                }
                if (!empty($attempt->timemodified)) {
                    $data->timemodified = transform::datetime($attempt->timemodified);
                }
                if (!empty($attempt->timemodifiedoffline)) {
                    $data->timemodifiedoffline = transform::datetime($attempt->timemodifiedoffline);
                }
                if (!empty($attempt->timecheckstate)) {
                    $data->timecheckstate = transform::datetime($attempt->timecheckstate);
                }
                if (!empty($attempt->gradednotificationsenttime)) {
                    $data->gradednotificationsenttime = transform::datetime($attempt->gradednotificationsenttime);
                }

                if ($options->marks == \question_display_options::MARK_AND_MAX) {
                    $grade = realtimequiz_rescale_grade($attempt->sumgrades, $realtimequiz, false);
                    $data->grade = (object) [
                            'grade' => realtimequiz_format_grade($realtimequiz, $grade),
                            'feedback' => realtimequiz_feedback_for_grade($grade, $realtimequiz, $context),
                        ];
                }

                writer::with_context($context)
                    ->export_data($attemptsubcontext, $data);
            } else {
                // This attempt was made by another user.
                // The current user may have marked part of the realtimequiz attempt.
                \core_question\privacy\provider::export_question_usage(
                        $userid,
                        $context,
                        $attemptsubcontext,
                        $attempt->uniqueid,
                        $options,
                        false
                    );
            }
        }
        $attempts->close();
    }
}
