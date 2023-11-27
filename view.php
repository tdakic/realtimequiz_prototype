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
 * This page is the entry page into the realtimequiz UI. Displays information about the
 * realtimequiz to students and teachers, and lets students see their previous attempts.
 *
 * @package   mod_realtimequiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_realtimequiz\access_manager;
use mod_realtimequiz\output\renderer;
use mod_realtimequiz\output\view_page;
use mod_realtimequiz\realtimequiz_attempt;
use mod_realtimequiz\realtimequiz_settings;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/mod/realtimequiz/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/format/lib.php');

//TTT added
require_once($CFG->dirroot.'/mod/realtimequiz/locallib_rt.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or ...
$q = optional_param('q',  0, PARAM_INT);  // Quiz ID.

if ($id) {
    $realtimequizobj = realtimequiz_settings::create_for_cmid($id, $USER->id);
} else {
    $realtimequizobj = realtimequiz_settings::create($q, $USER->id);
}
$realtimequiz = $realtimequizobj->get_realtimequiz();
$cm = $realtimequizobj->get_cm();
$course = $realtimequizobj->get_course();

//This should set quiz->status to NOT_RUNNING ... just in case
$realtimequizstatus = realtimequiz_update_status($realtimequiz->id, $realtimequiz->status);


// Check login and get context.
require_login($course, false, $cm);
$context = $realtimequizobj->get_context();
require_capability('mod/realtimequiz:view', $context);

// Cache some other capabilities we use several times.
$canattempt = has_capability('mod/realtimequiz:attempt', $context);
$canreviewmine = has_capability('mod/realtimequiz:reviewmyattempts', $context);
$canpreview = has_capability('mod/realtimequiz:preview', $context);

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();
$accessmanager = new access_manager($realtimequizobj, $timenow,
        has_capability('mod/realtimequiz:ignoretimelimits', $context, null, false));

// Trigger course_module_viewed event and completion.
realtimequiz_view($realtimequiz, $course, $cm, $context);

// Initialize $PAGE, compute blocks.
$PAGE->set_url('/mod/realtimequiz/view.php', ['id' => $cm->id]);

// Create view object which collects all the information the renderer will need.
$viewobj = new view_page();
$viewobj->accessmanager = $accessmanager;
$viewobj->canreviewmine = $canreviewmine || $canpreview;

// Get this user's attempts.
$attempts = realtimequiz_get_user_attempts($realtimequiz->id, $USER->id, 'finished', true);
$lastfinishedattempt = end($attempts);
$unfinished = false;
$unfinishedattemptid = null;
if ($unfinishedattempt = realtimequiz_get_user_attempt_unfinished($realtimequiz->id, $USER->id)) {
    $attempts[] = $unfinishedattempt;

    // If the attempt is now overdue, deal with that - and pass isonline = false.
    // We want the student notified in this case.
    $realtimequizobj->create_attempt_object($unfinishedattempt)->handle_if_time_expired(time(), false);

    $unfinished = $unfinishedattempt->state == realtimequiz_attempt::IN_PROGRESS ||
            $unfinishedattempt->state == realtimequiz_attempt::OVERDUE;
    if (!$unfinished) {
        $lastfinishedattempt = $unfinishedattempt;
    }
    $unfinishedattemptid = $unfinishedattempt->id;
    $unfinishedattempt = null; // To make it clear we do not use this again.
}
$numattempts = count($attempts);

$viewobj->attempts = $attempts;
$viewobj->attemptobjs = [];
foreach ($attempts as $attempt) {
    $viewobj->attemptobjs[] = new realtimequiz_attempt($attempt, $realtimequiz, $cm, $course, false);
}

// Work out the final grade, checking whether it was overridden in the gradebook.
if (!$canpreview) {
    $mygrade = realtimequiz_get_best_grade($realtimequiz, $USER->id);
} else if ($lastfinishedattempt) {
    // Users who can preview the realtimequiz don't get a proper grade, so work out a
    // plausible value to display instead, so the page looks right.
    $mygrade = realtimequiz_rescale_grade($lastfinishedattempt->sumgrades, $realtimequiz, false);
} else {
    $mygrade = null;
}

$mygradeoverridden = false;
$gradebookfeedback = '';

$item = null;

$gradinginfo = grade_get_grades($course->id, 'mod', 'realtimequiz', $realtimequiz->id, $USER->id);
if (!empty($gradinginfo->items)) {
    $item = $gradinginfo->items[0];
    if (isset($item->grades[$USER->id])) {
        $grade = $item->grades[$USER->id];

        if ($grade->overridden) {
            $mygrade = $grade->grade + 0; // Convert to number.
            $mygradeoverridden = true;
        }
        if (!empty($grade->str_feedback)) {
            $gradebookfeedback = $grade->str_feedback;
        }
    }
}

$title = $course->shortname . ': ' . format_string($realtimequiz->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
if (html_is_blank($realtimequiz->intro)) {
    $PAGE->activityheader->set_description('');
}
$PAGE->add_body_class('limitedwidth');
/** @var renderer $output */
$output = $PAGE->get_renderer('mod_realtimequiz');

// Print table with existing attempts.
if ($attempts) {
    // Work out which columns we need, taking account what data is available in each attempt.
    list($someoptions, $alloptions) = realtimequiz_get_combined_reviewoptions($realtimequiz, $attempts);

    $viewobj->attemptcolumn  = $realtimequiz->attempts != 1;

    $viewobj->gradecolumn    = $someoptions->marks >= question_display_options::MARK_AND_MAX &&
            realtimequiz_has_grades($realtimequiz);
    $viewobj->markcolumn     = $viewobj->gradecolumn && ($realtimequiz->grade != $realtimequiz->sumgrades);
    $viewobj->overallstats   = $lastfinishedattempt && $alloptions->marks >= question_display_options::MARK_AND_MAX;

    $viewobj->feedbackcolumn = realtimequiz_has_feedback($realtimequiz) && $alloptions->overallfeedback;
}

$viewobj->timenow = $timenow;
$viewobj->numattempts = $numattempts;
$viewobj->mygrade = $mygrade;
$viewobj->moreattempts = $unfinished ||
        !$accessmanager->is_finished($numattempts, $lastfinishedattempt);
$viewobj->mygradeoverridden = $mygradeoverridden;
$viewobj->gradebookfeedback = $gradebookfeedback;
$viewobj->lastfinishedattempt = $lastfinishedattempt;
$viewobj->canedit = has_capability('mod/realtimequiz:manage', $context);
$viewobj->editurl = new moodle_url('/mod/realtimequiz/edit.php', ['cmid' => $cm->id]);
$viewobj->backtocourseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
$viewobj->startattempturl = $realtimequizobj->start_attempt_url();

if ($accessmanager->is_preflight_check_required($unfinishedattemptid)) {
    $viewobj->preflightcheckform = $accessmanager->get_preflight_check_form(
            $viewobj->startattempturl, $unfinishedattemptid);
}
$viewobj->popuprequired = $accessmanager->attempt_must_be_in_popup();
$viewobj->popupoptions = $accessmanager->get_popup_options();

// Display information about this realtimequiz.
$viewobj->infomessages = $viewobj->accessmanager->describe_rules();
if ($realtimequiz->attempts != 1) {
    $viewobj->infomessages[] = get_string('gradingmethod', 'realtimequiz',
            realtimequiz_get_grading_option_name($realtimequiz->grademethod));
}

// Inform user of the grade to pass if non-zero.
if ($item && grade_floats_different($item->gradepass, 0)) {
    $a = new stdClass();
    $a->grade = realtimequiz_format_grade($realtimequiz, $item->gradepass);
    $a->maxgrade = realtimequiz_format_grade($realtimequiz, $realtimequiz->grade);
    $viewobj->infomessages[] = get_string('gradetopassoutof', 'realtimequiz', $a);
}

// Determine whether a start attempt button should be displayed.
$viewobj->realtimequizhasquestions = $realtimequizobj->has_questions();
$viewobj->preventmessages = [];
/* if (!$viewobj->realtimequizhasquestions) {
    $viewobj->buttontext = '';

} else {
    if ($unfinished) {
        if ($canpreview) {
            $viewobj->buttontext = get_string('continuepreview', 'realtimequiz');
        } else if ($canattempt) {
            $viewobj->buttontext = get_string('continueattemptrealtimequiz', 'realtimequiz');
        }
    } else {
        if ($canpreview) {
            $viewobj->buttontext = get_string('previewrealtimequizstart', 'realtimequiz');
        } else if ($canattempt) {
            $viewobj->preventmessages = $viewobj->accessmanager->prevent_new_attempt(
                    $viewobj->numattempts, $viewobj->lastfinishedattempt);
            if ($viewobj->preventmessages) {
                $viewobj->buttontext = '';
            } else if ($viewobj->numattempts == 0) {
                $viewobj->buttontext = get_string('attemptrealtimequiz', 'realtimequiz');
            } else {
                $viewobj->buttontext = get_string('reattemptrealtimequiz', 'realtimequiz');
            }
        }
    }

    // Users who can preview the realtimequiz should be able to see all messages for not being able to access the realtimequiz.
    if ($canpreview) {
        $viewobj->preventmessages = $viewobj->accessmanager->prevent_access();
    } else if ($viewobj->buttontext) {
        // If, so far, we think a button should be printed, so check if they will be allowed to access it.
        if (!$viewobj->moreattempts) {
            $viewobj->buttontext = '';
        } else if ($canattempt) {
            $viewobj->preventmessages = $viewobj->accessmanager->prevent_access();
            if ($viewobj->preventmessages) {
                $viewobj->buttontext = '';
            }
        }
    }
} */
$viewobj->buttontext = '';
$viewobj->showbacktocourse = ($viewobj->buttontext === '' &&
        course_get_format($course)->has_view_page());

echo $OUTPUT->header();

//TTT start



//$realtimequizstatus = realtimequiz_update_status($q, $realtimequiz->status);
$realtimequizstatus = realtimequiz_update_status($realtimequiz->id, $realtimequiz->status);

echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter realtimequizbox');
?>
    <div id="questionarea"></div>
    <!--    <div id="debugarea" style="border: 1px dashed black; width: 600px; height: 100px; overflow: scroll; "></div>
        <button onclick="realtimequiz_debug_stopall();">Stop</button> -->
    <script type="text/javascript" src="<?php echo $CFG->wwwroot; ?>/mod/realtimequiz/view_student.js"></script>
    <script type="text/javascript">
        realtimequiz_set_maxanswers(10);
        realtimequiz_set_quizid(<?php echo $realtimequiz->id; ?>);
        realtimequiz_set_userid(<?php echo $USER->id; ?>);
        realtimequiz_set_sesskey('<?php echo sesskey(); ?>');
        realtimequiz_set_coursepage('<?php echo "$CFG->wwwroot/course/view.php?id=$course->id"; ?>');
        realtimequiz_set_siteroot('<?php echo "$CFG->wwwroot"; ?>');
        realtimequiz_set_running(<?php echo(realtimequiz_is_running($realtimequizstatus) ? 'true' : 'false'); ?>);
        //TTT
        realtimequiz_set_cmid(<?php echo $id; ?>)

        realtimequiz_set_image('tick', "<?php echo $tickimg ?>");
        realtimequiz_set_image('cross', "<?php echo $crossimg ?>");
        realtimequiz_set_image('blank', "<?php echo $spacer ?>");

        //Pass all the text strings into the javascript (to allow for translation)
        // Used by view_student.js
        realtimequiz_set_text('joinrealtimequiz', "<?php echo addslashes(get_string('joinquiz', 'realtimequiz')); ?>");
        realtimequiz_set_text('joininstruct', "<?php echo addslashes(get_string('joininstruct', 'realtimequiz')); ?>");
        realtimequiz_set_text('waitstudent', "<?php echo addslashes(get_string('waitstudent', 'realtimequiz')); ?>");
        realtimequiz_set_text('clicknext', "<?php echo addslashes(get_string('clicknext', 'realtimequiz')); ?>");
        realtimequiz_set_text('waitfirst', "<?php echo addslashes(get_string('waitfirst', 'realtimequiz')); ?>");
        realtimequiz_set_text('question', "<?php echo addslashes(get_string('question', 'realtimequiz')); ?>");
        realtimequiz_set_text('invalidanswer', "<?php echo addslashes(get_string('invalidanswer',
                                                                                 'realtimequiz')); ?>");
        realtimequiz_set_text('finalresults', "<?php echo addslashes(get_string('finalresults', 'realtimequiz')); ?>");
        realtimequiz_set_text('realtimequizfinished', "<?php echo addslashes(get_string('quizfinished', 'realtimequiz')); ?>");
        realtimequiz_set_text('classresult', "<?php echo addslashes(get_string('classresult', 'realtimequiz')); ?>");
        realtimequiz_set_text('classresultcorrect', "<?php echo addslashes(get_string('classresultcorrect',
                                                                                      'realtimequiz')); ?>");
        realtimequiz_set_text('questionfinished', "<?php echo addslashes(get_string('questionfinished',
                                                                                    'realtimequiz')); ?>");
        realtimequiz_set_text('httprequestfail', "<?php echo addslashes(get_string('httprequestfail',
                                                                                   'realtimequiz')); ?>");
        realtimequiz_set_text('noquestion', "<?php echo addslashes(get_string('noquestion', 'realtimequiz')); ?>");
        realtimequiz_set_text('tryagain', "<?php echo addslashes(get_string('tryagain', 'realtimequiz')); ?>");
        realtimequiz_set_text('resultthisquestion', "<?php echo addslashes(get_string('resultthisquestion',
                                                                                      'realtimequiz')); ?>");
        realtimequiz_set_text('resultoverall', "<?php echo addslashes(get_string('resultoverall',
                                                                                 'realtimequiz')); ?>");
        realtimequiz_set_text('resultcorrect', "<?php echo addslashes(get_string('resultcorrect',
                                                                                 'realtimequiz')); ?>");
        realtimequiz_set_text('answersent', "<?php echo addslashes(get_string('answersent', 'realtimequiz')); ?>");
        realtimequiz_set_text('quiznotrunning', "<?php echo addslashes(get_string('quiznotrunning',
                                                                                  'realtimequiz')); ?>");
        realtimequiz_set_text('servererror', "<?php echo addslashes(get_string('servererror', 'realtimequiz')); ?>");
        realtimequiz_set_text('badresponse', "<?php echo addslashes(get_string('badresponse', 'realtimequiz')); ?>");
        realtimequiz_set_text('httperror', "<?php echo addslashes(get_string('httperror', 'realtimequiz')); ?>");
        realtimequiz_set_text('yourresult', "<?php echo addslashes(get_string('yourresult', 'realtimequiz')); ?>");

        realtimequiz_set_text('timeleft', "<?php echo addslashes(get_string('timeleft', 'realtimequiz')); ?>");
        realtimequiz_set_text('displaynext', "<?php echo addslashes(get_string('displaynext', 'realtimequiz')); ?>");
        realtimequiz_set_text('sendinganswer', "<?php echo addslashes(get_string('sendinganswer',
                                                                                 'realtimequiz')); ?>");
        realtimequiz_set_text('tick', "<?php echo addslashes(get_string('tick', 'realtimequiz')); ?>");
        realtimequiz_set_text('cross', "<?php echo addslashes(get_string('cross', 'realtimequiz')); ?>");

        // Used by view_teacher.js
        realtimequiz_set_text('joinquizasstudent', "<?php echo addslashes(get_string('joinquizasstudent',
                                                                                     'realtimequiz')); ?>");
        realtimequiz_set_text('next', "<?php echo addslashes(get_string('next', 'realtimequiz')); ?>");
        realtimequiz_set_text('startquiz', "<?php echo addslashes(get_string('startquiz', 'realtimequiz')); ?>");
        realtimequiz_set_text('startnewquiz', "<?php echo addslashes(get_string('startnewquiz', 'realtimequiz')); ?>");
        realtimequiz_set_text('startnewquizconfirm', "<?php echo addslashes(get_string('startnewquizconfirm',
                                                                                       'realtimequiz')); ?>");
        realtimequiz_set_text('studentconnected', "<?php echo addslashes(get_string('studentconnected',
                                                                                    'realtimequiz')); ?>");
        realtimequiz_set_text('studentsconnected', "<?php echo addslashes(get_string('studentsconnected',
                                                                                     'realtimequiz')); ?>");
        realtimequiz_set_text('teacherstartinstruct', "<?php echo addslashes(get_string('teacherstartinstruct',
                                                                                        'realtimequiz')); ?>");
        realtimequiz_set_text('teacherstartnewinstruct', "<?php echo addslashes(get_string('teacherstartnewinstruct',
                                                                                           'realtimequiz')); ?>");
        realtimequiz_set_text('teacherjoinquizinstruct', "<?php echo addslashes(get_string('teacherjoinquizinstruct',
                                                                                           'realtimequiz')); ?>");
        realtimequiz_set_text('reconnectquiz', "<?php echo addslashes(get_string('reconnectquiz',
                                                                                 'realtimequiz')); ?>");
        realtimequiz_set_text('reconnectinstruct', "<?php echo addslashes(get_string('reconnectinstruct',
                                                                                     'realtimequiz')); ?>");
    </script>

<?php

if (has_capability('mod/realtimequiz:control', $context)) {
    ?>
    <script type="text/javascript" src="<?php echo $CFG->wwwroot; ?>/mod/realtimequiz/view_teacher.js"></script>
    <script type="text/javascript">
        realtimequiz_init_teacher_view();
    </script>
    <?php
} else {
    echo '<script type="text/javascript">realtimequiz_init_student_view();</script>';
}

echo $OUTPUT->box_end();


echo '<div id="bedbug">  </div>';
//TTT end




if (!empty($gradinginfo->errors)) {
    foreach ($gradinginfo->errors as $error) {
        $errortext = new \core\output\notification($error, \core\output\notification::NOTIFY_ERROR);
        echo $OUTPUT->render($errortext);
    }
}

if (isguestuser()) {
    // Guests can't do a realtimequiz, so offer them a choice of logging in or going back.
    echo $output->view_page_guest($course, $realtimequiz, $cm, $context, $viewobj->infomessages, $viewobj);
} else if (!isguestuser() && !($canattempt || $canpreview
          || $viewobj->canreviewmine)) {
    // If they are not enrolled in this course in a good enough role, tell them to enrol.
    echo $output->view_page_notenrolled($course, $realtimequiz, $cm, $context, $viewobj->infomessages, $viewobj);
} else {
    echo $output->view_page($course, $realtimequiz, $cm, $context, $viewobj);
}

echo $OUTPUT->footer();
