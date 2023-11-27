<?php
require_once(__DIR__ . '/../../config.php');
//TTT from https://docs.moodle.org/dev/Using_the_question_engine_from_module

use mod_realtimequiz\realtimequiz_settings;
$quizid = required_param('quizid', PARAM_INT);

$quizobj = realtimequiz_settings::create($quizid);
// in startattempt there is this
//$realtimequizobj = realtimequiz_settings::create_for_cmid($id, $USER->id);
//echo "QUIZOBJECTID" ;
//echo $quizobj->get_realtimequizid();
//echo "<br />";

//code from start attempt
$timenow = time();
$accessmanager = $quizobj->get_access_manager($timenow);
$forcenew = optional_param('forcenew', false, PARAM_BOOL); // Used to force a new preview
$page = optional_param('page', -1, PARAM_INT); // Page to jump to in the attempt.

// Validate permissions for creating a new attempt and start a new preview attempt if required.
list($currentattemptid, $attemptnumber, $lastattempt, $messages, $page) =
    realtimequiz_validate_new_attempt($quizobj, $accessmanager, $forcenew, $page, true);
//echo "<br />";
//echo "currentattemptid: " ;

//echo $currentattemptid;
//echo "<br />";
//echo "attemptnumber: " ;
//echo $attemptnumber;
//echo "<br />";
//echo $lastattempt;
//echo $messages;
//echo $page;

$attemptnumber = 7;
$page = 0;
// code from startattempt
$attempt = realtimequiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);
echo $attempt->id;
//redirect($quizobj->attempt_url($attempt->id, $page));
