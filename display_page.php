<?php
require_once(__DIR__ . '/../../config.php');


use mod_realtimequiz\realtimequiz_settings;
$quizid = required_param('quizid', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);


$quizobj = realtimequiz_settings::create($quizid);
//$attempobj = realtimequiz_create_attempt_handling_errors($attemptid, 20);

echo $quizobj->attempt_url($attemptid, 0);

//echo $data = file_get_contents($quizobj->attempt_url($attemptid, 0));
