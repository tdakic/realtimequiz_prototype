<?php
require_once(__DIR__ . '/../../config.php');
//TTT from https://docs.moodle.org/dev/Using_the_question_engine_from_module
use core_question\local\bank\question_version_status;
use question_engine;
use mod_realtimequiz\realtimequiz_settings;
$quizid = required_param('quizid', PARAM_INT);

$quizobj = realtimequiz_settings::create($quizid);
// in startattempt there is this
//$realtimequizobj = realtimequiz_settings::create_for_cmid($id, $USER->id);
echo "QUIZOBJECTID" ;
echo $quizobj->get_realtimequizid();
echo "<br />";

//code from start attempt
$timenow = time();
$accessmanager = $quizobj->get_access_manager($timenow);
$forcenew = optional_param('forcenew', false, PARAM_BOOL); // Used to force a new preview
$page = optional_param('page', -1, PARAM_INT); // Page to jump to in the attempt.

// Validate permissions for creating a new attempt and start a new preview attempt if required.
list($currentattemptid, $attemptnumber, $lastattempt, $messages, $page) =
    realtimequiz_validate_new_attempt($quizobj, $accessmanager, $forcenew, $page, true);
echo "<br />";
echo "currentattemptid: " ;

echo $currentattemptid;
echo "<br />";
echo "attemptnumber: " ;
echo $attemptnumber;
echo "<br />";
//echo $lastattempt;
//echo $messages;
//echo $page;

$attemptnumber = 7;
$page = 0;
// code from startattempt
$attempt = realtimequiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);
redirect($quizobj->attempt_url($attempt->id, $page));



$quba = question_engine::make_questions_usage_by_activity('mod_realtimequiz', $quizobj->get_context());
$quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

$quba->start_all_questions();

question_engine::save_questions_usage_by_activity($quba);

$quba = question_engine::load_questions_usage_by_activity($quba->get_id());

if ($quizobj->has_questions()) {
        echo "YES!!!!";
        echo $quizobj->get_cmid();
    } else {
        echo "YES!!!!";
    }
echo "<br />";
echo "ID";
echo $quba->get_id();
echo "<br />";
echo "<br />";

//$page = $_SERVER['PHP_SELF'];

$slot = 0;
$questions = [];
$maxmark = [];
$page_1 = [];
//$headtags = question_engine::initialise_js() . $quba->render_question_head_html($slot);
foreach ($quizobj->get_questions() as $questiondata) {
    $slot += 1;
    $maxmark[$slot] = $questiondata->maxmark;
    $page_1[$slot] = $questiondata->page;
    $questions[$slot] = question_bank::make_question($questiondata);
    echo "<br /> <p> Question_id";
    echo $questions[$slot]->question_id;
    echo "</p>";
    echo "<br /> <p> Question summeary: ";
    echo $questions[$slot]-> get_question_summary();
    echo "<br />";
    echo "<br />";
    echo get_class($questions[$slot]);
    $renderer = $questions[$slot]->get_renderer($PAGE);
    echo "<br />";
    echo get_class($renderer);
    echo "<br />";
    //echo $renderer -> get_input_type();
    //$renderer->render($PAGE);
}
//echo "slot: ";
//echo $slot;
$rt_question_num = 1;

$options = new question_display_options();
$options->marks = question_display_options::MAX_ONLY;
$options->markdp = 2; // Display marks to 2 decimal places.
$options->feedback = question_display_options::VISIBLE;
$options->generalfeedback = question_display_options::HIDDEN;




$slot = $quba->get_first_question_number();
//$usedquestion = $quba->get_question($slot, false);


//echo $slot;
//echo $quba->render_question(0, $options, '1');
//echo $quba->render_question(0, $options, 1);

$processurl ="";
$slotsonpage = array(0,1);


/*echo '<form id="responseform" method="post" action="' . $processurl .
        '" enctype="multipart/form-data" accept-charset="utf-8">', "\n<div>\n";

$PAGE->requires->js_init_call('M.core_question_engine.init_form', array('#responseform'), false, array('core_question_engine'));

echo '<input type="hidden" name="slots" value="' . implode($slotsonpage) . "\" />\n";
echo '<input type="hidden" name="scrollpos" value="" />';

$slotsonpage = array(0,1);
foreach ($slotsonpage as $displaynumber => $slot) {
    echo $quba->render_question($slot, $options, $displaynumber);
}
foreach ($slotsonpage as $displaynumber => $slot) {
    echo $quba->render_question($slot, $options, $displaynumber);
}
*/
