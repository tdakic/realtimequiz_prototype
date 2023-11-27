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

namespace mod_realtimequiz\form;

use cm_info;
use context;
use context_module;
use mod_realtimequiz_mod_form;
use moodle_url;
use moodleform;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/realtimequiz/mod_form.php');

/**
 * Form for editing realtimequiz settings overrides.
 *
 * @package    mod_realtimequiz
 * @copyright  2010 Matt Petro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_override_form extends moodleform {

    /** @var cm_info course module object. */
    protected $cm;

    /** @var stdClass the realtimequiz settings object. */
    protected $realtimequiz;

    /** @var context_module the realtimequiz context. */
    protected $context;

    /** @var bool editing group override (true) or user override (false). */
    protected $groupmode;

    /** @var int groupid, if provided. */
    protected $groupid;

    /** @var int userid, if provided. */
    protected $userid;

    /**
     * Constructor.
     *
     * @param moodle_url $submiturl the form action URL.
     * @param cm_info|stdClass $cm course module object.
     * @param stdClass $realtimequiz the realtimequiz settings object.
     * @param context_module $context the realtimequiz context.
     * @param bool $groupmode editing group override (true) or user override (false).
     * @param stdClass|null $override the override being edited, if it already exists.
     */
    public function __construct(moodle_url $submiturl,
            cm_info|stdClass $cm, stdClass $realtimequiz, context_module $context,
            bool $groupmode, ?stdClass $override) {

        $this->cm = $cm;
        $this->realtimequiz = $realtimequiz;
        $this->context = $context;
        $this->groupmode = $groupmode;
        $this->groupid = empty($override->groupid) ? 0 : $override->groupid;
        $this->userid = empty($override->userid) ? 0 : $override->userid;

        parent::__construct($submiturl);
    }

    protected function definition() {
        global $DB;

        $cm = $this->cm;
        $mform = $this->_form;

        $mform->addElement('header', 'override', get_string('override', 'realtimequiz'));

        $realtimequizgroupmode = groups_get_activity_groupmode($cm);
        $accessallgroups = ($realtimequizgroupmode == NOGROUPS) || has_capability('moodle/site:accessallgroups', $this->context);

        if ($this->groupmode) {
            // Group override.
            if ($this->groupid) {
                // There is already a groupid, so freeze the selector.
                $groupchoices = [
                    $this->groupid => format_string(groups_get_group_name($this->groupid), true, $this->context),
                ];
                $mform->addElement('select', 'groupid',
                        get_string('overridegroup', 'realtimequiz'), $groupchoices);
                $mform->freeze('groupid');
            } else {
                // Prepare the list of groups.
                // Only include the groups the current can access.
                $groups = $accessallgroups ? groups_get_all_groups($cm->course) : groups_get_activity_allowed_groups($cm);
                if (empty($groups)) {
                    // Generate an error.
                    $link = new moodle_url('/mod/realtimequiz/overrides.php', ['cmid' => $cm->id]);
                    throw new \moodle_exception('groupsnone', 'realtimequiz', $link);
                }

                $groupchoices = [];
                foreach ($groups as $group) {
                    if ($group->visibility != GROUPS_VISIBILITY_NONE) {
                        $groupchoices[$group->id] = format_string($group->name, true, $this->context);
                    }
                }
                unset($groups);

                if (count($groupchoices) == 0) {
                    $groupchoices[0] = get_string('none');
                }

                $mform->addElement('select', 'groupid',
                        get_string('overridegroup', 'realtimequiz'), $groupchoices);
                $mform->addRule('groupid', get_string('required'), 'required', null, 'client');
            }
        } else {
            // User override.
            $userfieldsapi = \core_user\fields::for_identity($this->context)->with_userpic()->with_name();
            $extrauserfields = $userfieldsapi->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]);
            if ($this->userid) {
                // There is already a userid, so freeze the selector.
                $user = $DB->get_record('user', ['id' => $this->userid]);
                profile_load_custom_fields($user);
                $userchoices = [];
                $userchoices[$this->userid] = self::display_user_name($user, $extrauserfields);
                $mform->addElement('select', 'userid',
                        get_string('overrideuser', 'realtimequiz'), $userchoices);
                $mform->freeze('userid');
            } else {
                // Prepare the list of users.
                $groupids = 0;
                if (!$accessallgroups) {
                    $groups = groups_get_activity_allowed_groups($cm);
                    $groupids = array_keys($groups);
                }
                $enrolledjoin = get_enrolled_with_capabilities_join(
                        $this->context, '', 'mod/realtimequiz:attempt', $groupids, true);
                $userfieldsql = $userfieldsapi->get_sql('u', true, '', '', false);
                list($sort, $sortparams) = users_order_by_sql('u', null,
                        $this->context, $userfieldsql->mappings);

                $users = $DB->get_records_sql("
                        SELECT $userfieldsql->selects
                          FROM {user} u
                          $enrolledjoin->joins
                          $userfieldsql->joins
                          LEFT JOIN {realtimequiz_overrides} existingoverride ON
                                      existingoverride.userid = u.id AND existingoverride.realtimequiz = :realtimequizid
                         WHERE existingoverride.id IS NULL
                           AND $enrolledjoin->wheres
                      ORDER BY $sort
                        ", array_merge(['realtimequizid' => $this->realtimequiz->id], $userfieldsql->params, $enrolledjoin->params, $sortparams));

                // Filter users based on any fixed restrictions (groups, profile).
                $info = new \core_availability\info_module($cm);
                $users = $info->filter_user_list($users);

                if (empty($users)) {
                    // Generate an error.
                    $link = new moodle_url('/mod/realtimequiz/overrides.php', ['cmid' => $cm->id]);
                    throw new \moodle_exception('usersnone', 'realtimequiz', $link);
                }

                $userchoices = [];
                foreach ($users as $id => $user) {
                    $userchoices[$id] = self::display_user_name($user, $extrauserfields);
                }
                unset($users);

                $mform->addElement('searchableselector', 'userid',
                        get_string('overrideuser', 'realtimequiz'), $userchoices);
                $mform->addRule('userid', get_string('required'), 'required', null, 'client');
            }
        }

        // Password.
        // This field has to be above the date and timelimit fields,
        // otherwise browsers will clear it when those fields are changed.
        $mform->addElement('passwordunmask', 'password', get_string('requirepassword', 'realtimequiz'));
        $mform->setType('password', PARAM_TEXT);
        $mform->addHelpButton('password', 'requirepassword', 'realtimequiz');
        $mform->setDefault('password', $this->realtimequiz->password);

        // Open and close dates.
        $mform->addElement('date_time_selector', 'timeopen',
                get_string('realtimequizopen', 'realtimequiz'), mod_realtimequiz_mod_form::$datefieldoptions);
        $mform->setDefault('timeopen', $this->realtimequiz->timeopen);

        $mform->addElement('date_time_selector', 'timeclose',
                get_string('realtimequizclose', 'realtimequiz'), mod_realtimequiz_mod_form::$datefieldoptions);
        $mform->setDefault('timeclose', $this->realtimequiz->timeclose);

        // Time limit.
        $mform->addElement('duration', 'timelimit',
                get_string('timelimit', 'realtimequiz'), ['optional' => true]);
        $mform->addHelpButton('timelimit', 'timelimit', 'realtimequiz');
        $mform->setDefault('timelimit', $this->realtimequiz->timelimit);

        // Number of attempts.
        $attemptoptions = ['0' => get_string('unlimited')];
        for ($i = 1; $i <= QUIZ_MAX_ATTEMPT_OPTION; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'attempts',
                get_string('attemptsallowed', 'realtimequiz'), $attemptoptions);
        $mform->addHelpButton('attempts', 'attempts', 'realtimequiz');
        $mform->setDefault('attempts', $this->realtimequiz->attempts);

        // Submit buttons.
        $mform->addElement('submit', 'resetbutton',
                get_string('reverttodefaults', 'realtimequiz'));

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton',
                get_string('save', 'realtimequiz'));
        $buttonarray[] = $mform->createElement('submit', 'againbutton',
                get_string('saveoverrideandstay', 'realtimequiz'));
        $buttonarray[] = $mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'buttonbar', '', [' '], false);
        $mform->closeHeaderBefore('buttonbar');
    }

    /**
     * Get a user's name and identity ready to display.
     *
     * @param stdClass $user a user object.
     * @param array $extrauserfields (identity fields in user table only from the user_fields API)
     * @return string User's name, with extra info, for display.
     */
    public static function display_user_name(stdClass $user, array $extrauserfields): string {
        $username = fullname($user);
        $namefields = [];
        foreach ($extrauserfields as $field) {
            if (isset($user->$field) && $user->$field !== '') {
                $namefields[] = s($user->$field);
            } else if (strpos($field, 'profile_field_') === 0) {
                $field = substr($field, 14);
                if (isset($user->profile[$field]) && $user->profile[$field] !== '') {
                    $namefields[] = s($user->profile[$field]);
                }
            }
        }
        if ($namefields) {
            $username .= ' (' . implode(', ', $namefields) . ')';
        }
        return $username;
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $mform =& $this->_form;
        $realtimequiz = $this->realtimequiz;

        if ($mform->elementExists('userid')) {
            if (empty($data['userid'])) {
                $errors['userid'] = get_string('required');
            }
        }

        if ($mform->elementExists('groupid')) {
            if (empty($data['groupid'])) {
                $errors['groupid'] = get_string('required');
            }
        }

        // Ensure that the dates make sense.
        if (!empty($data['timeopen']) && !empty($data['timeclose'])) {
            if ($data['timeclose'] < $data['timeopen'] ) {
                $errors['timeclose'] = get_string('closebeforeopen', 'realtimequiz');
            }
        }

        // Ensure that at least one realtimequiz setting was changed.
        $changed = false;
        $keys = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'password'];
        foreach ($keys as $key) {
            if ($data[$key] != $realtimequiz->{$key}) {
                $changed = true;
                break;
            }
        }
        if (!$changed) {
            $errors['timeopen'] = get_string('nooverridedata', 'realtimequiz');
        }

        return $errors;
    }
}
