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
 * Internal functions
 *
 * @package   mod_realtimequiz
 * @copyright 2014 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/** Quiz not running */
define('REALTIMEQUIZ_STATUS_NOTRUNNING', 0);
/** Quiz ready to start */
define('REALTIMEQUIZ_STATUS_READYTOSTART', 10);
/** Quiz showing 'review question' page */
define('REALTIMEQUIZ_STATUS_PREVIEWQUESTION', 15);
/** Quiz showing a question */
define('REALTIMEQUIZ_STATUS_SHOWQUESTION', 20);
/** Quiz showing results */
define('REALTIMEQUIZ_STATUS_SHOWRESULTS', 30);
/** Quiz showing the final results */
define('REALTIMEQUIZ_STATUS_FINALRESULTS', 40);


/**
 * Output the response start
 */
function realtimequiz_start_response() {
    header('content-type: text/xml');
    echo '<?xml version="1.0" ?><realtimequiz>';
}

/**
 * Output the response end
 */
function realtimequiz_end_response() {
    echo '</realtimequiz>';
}

/**
 * Send the given error messsage
 * @param string $msg
 */
function realtimequiz_send_error($msg) {
    echo "<status>error</status><message><![CDATA[{$msg}]]></message>";
}
/*************************************************************************************************************

/**
 * Send the question details
 * @param int $quizid
 * @param context $context
 * @param bool $preview
 * @throws coding_exception
 * @throws dml_exception
 */
function realtimequiz_send_question($quizid, $context, $preview = false) {
    global $DB;
    global $CFG;

    if (!$quiz = $DB->get_record('realtimequiz', array('id' => $quizid))) {
        realtimequiz_send_error(get_string('badquizid', 'realtimequiz').$quizid);
    } else {
        $questionid = $quiz->currentquestion;
          echo "<questionid>{$questionid}</questionid>";
        //TTT quick fix
        $questionid = 1;
        // TTT slot or displaynumber???
        if (!$question = $DB->get_record('realtimequiz_slots', array('slot' => $questionid,'realtimequizid' => $quizid))) {
            realtimequiz_send_error(get_string('badcurrentquestion', 'realtimequiz').$questionid." in locallib");
        } else {

            realtimequiz_send_start_of_question($question, $quizid);
            //include("./attempt.php");
            //required ?cmid=20&quizid=3&atemptid=210
            realtimequiz_send_end_of_question($quiz,$question,$preview );

        }

    }
}

function realtimequiz_send_start_of_question($question, $quizid)
{
  global $DB;
  echo "<dberror>".$quizid."</dberror>";
  $questioncount = $DB->count_records('realtimequiz_slots', ['realtimequizid' => $quizid]);
  //$questioncount = $DB->count_records('realtimequiz_question', array('quizid' => $quizid));
  echo '<status>showquestion</status>';
  // This is super wonky....
  // it would be scary if {$question->slot} != $questionid) but $question->slot doesn't work
  //in any case that should switch to page Later
  echo "<question><questionnumber>";
  echo $question->slot;
  echo "</questionnumber>";
  echo "<questioncount>{$questioncount}</questioncount>";

  echo "<questiontext>";
  // more TTT after Thanksgiving
  //include("./attempt.php");
  //get_attempt_page();

  echo "<questionpage>";
  //echo "<attemptid> {$attempt} </attemptid>" ;


}

function realtimequiz_send_end_of_question($quiz,$question, $preview = false ) {

  echo "</questionpage>";
  echo "</questiontext>";

  if ($preview) {
      $previewtime = $quiz->nextendtime - time();
      if ($previewtime > 0) {
          echo "<delay>{$previewtime}</delay>";
      }
      //TTT

      $questiontime = $quiz->questiontime;
      //$questiontime = 10;
      //if ($questiontime == 0) {
      //    $questiontime = $quiz->questiontime;
      //}
      echo "<questiontime>{$questiontime}</questiontime>";
  } else {
      $questiontime = $quiz->nextendtime - time();
      if ($questiontime < 0) {
          $questiontime = 0;
      }
      echo "<questiontime>{$questiontime}</questiontime>";
  }

  echo '</question>';

}

function realtimequiz_goto_question($context, $quizid, $questionnum) {
    global $DB;

    if (has_capability('mod/realtimequiz:control', $context)) {
      $questioncount = $DB->count_records('realtimequiz_slots', ['realtimequizid' => $quizid]);
      $quiz = $DB->get_record('realtimequiz', array('id' => $quizid));
      // Update the question statistics.
      $quiz->classresult += $quiz->questionresult;
      $quiz->questionresult = 0;

      $questionid = $quiz->currentquestion;
      if ($questionid < $questioncount) {
          $quiz->currentquestion = $questionid + 1;
          $quiz->status = REALTIMEQUIZ_STATUS_PREVIEWQUESTION;
          $quiz->nextendtime = time() + 2;    // Give everyone a chance to get the question before starting.
          $DB->update_record('realtimequiz', $quiz); // FIXME - not update all fields?
          // *************
              // TTT slot or displaynumber???
              // TTT quick fix
              $questionid =1;
              if (!$question = $DB->get_record('realtimequiz_slots', array('slot' => $questionid,'realtimequizid' => $quizid))) {
                  realtimequiz_send_error(get_string('badcurrentquestion', 'realtimequiz').$questionid);
              } else {

                  realtimequiz_send_start_of_question($question, $quizid);
                  //include("./attempt.php");
                  //required ?cmid=20&quizid=3&atemptid=210
                  realtimequiz_send_end_of_question($quiz,$question,$preview );

              }

          }
          // **********

          //realtimequiz_send_question($quizid, $context, true);
       else { // Assume we have run out of questions.
          $quiz->status = REALTIMEQUIZ_STATUS_FINALRESULTS;
          $DB->update_record('realtimequiz', $quiz); // FIXME - not update all fields?
          realtimequiz_send_final_results($quizid);
      }
  } else {
      realtimequiz_send_error(get_string('notauthorised', 'realtimequiz'));
  }
}


function realtimequiz_goto_question_original($context, $quizid, $questionnum) {
    global $DB;

    if (has_capability('mod/realtimequiz:control', $context)) {
      $questioncount = $DB->count_records('realtimequiz_slots', ['realtimequizid' => $quizid]);
      $quiz = $DB->get_record('realtimequiz', array('id' => $quizid));
      // Update the question statistics.
      $quiz->classresult += $quiz->questionresult;
      $quiz->questionresult = 0;
      //$questionid = $DB->get_field('realtimequiz_question', 'id', array('quizid' => $quizid, 'questionnum' => $questionnum));
      // TTT Wonkyness!!!!

      //if (!$question = $DB->get_record('realtimequiz_slots', array('slot' => $questionid,'realtimequizid' => $quizid)))
      // TTT
      //$questionid = $DB->get_field('realtimequiz_question', 'id', array('quizid' => $quizid, 'questionnum' => $questionnum));

      //$questionid = $DB->get_field('realtimequiz_slots', 'displaynumber', array('realtimequizid' => $quizid, 'slot' => $questionnum));

      //$questionid = 1;
      //TTT this is a nasty fix, but why can't one just use $quiz->currentquestion to control which question is
      // being displayed... here only teacher should be able to update it
      $questionid = $quiz->currentquestion;
      if ($questionid < $questioncount) {
          $quiz->currentquestion = $questionid + 1;
          $quiz->status = REALTIMEQUIZ_STATUS_PREVIEWQUESTION;
          $quiz->nextendtime = time() + 2;    // Give everyone a chance to get the question before starting.
          $DB->update_record('realtimequiz', $quiz); // FIXME - not update all fields?
          realtimequiz_send_question($quizid, $context, true);
      } else { // Assume we have run out of questions.
          $quiz->status = REALTIMEQUIZ_STATUS_FINALRESULTS;
          $DB->update_record('realtimequiz', $quiz); // FIXME - not update all fields?
          realtimequiz_send_final_results($quizid);
      }
  } else {
      realtimequiz_send_error(get_string('notauthorised', 'realtimequiz'));
  }
}

//************************************************************************



/**
 * Send the result details
 * @param int $quizid
 * @throws coding_exception
 * @throws dml_exception
 */
function realtimequiz_send_results($quizid) {
  global $DB;
  $quiz = $DB->get_record('realtimequiz', array('id' => $quizid));
  echo '<status>showresults</status>';
  echo '<questionnum>';
  echo $quiz->currentquestion;
  echo '</questionnum>';
  //echo '<questionnum>1</questionnum>';
  echo '<results>';
  echo "<result id='1' correct='2'>3</result>";
  echo '</results>';

  echo '<statistics>';
  echo '<questionresult>Results are good!</questionresult>';
  echo '<classresult>Class result are best!</classresult>';
  echo '</statistics>';
  /*global $DB;

    if (!$quiz = $DB->get_record('realtimequiz', array('id' => $quizid))) {
        realtimequiz_send_error(get_string('badquizid', 'realtimequiz').$quizid);
    } else {
        $questionid = $quiz->currentquestion;
        if (!$question = $DB->get_record('realtimequiz_question', array('id' => $questionid))) {
            realtimequiz_send_error(get_string('badcurrentquestion', 'realtimequiz').$questionid);
        } else {
            // Do not worry about question number not matching request
            // client should sort out correct state, if they do not match
            // just get on with sending current results.
            $totalanswers = 0;
            $totalcorrect = 0;
            $answers = $DB->get_records('realtimequiz_answer', array('questionid' => $questionid), 'id');
            echo '<status>showresults</status>';
            echo '<questionnum>'.$question->questionnum.'</questionnum>';
            echo '<results>';
            $numberofcorrectanswers = 0; // To detect questions that have no 'correct' answers.
            foreach ($answers as $answer) {
                $result = $DB->count_records('realtimequiz_submitted', array(
                    'questionid' => $questionid, 'answerid' => $answer->id, 'sessionid' => $quiz->currentsessionid
                ));
                $totalanswers += $result;
                $correct = 'false';
                if ($answer->correct == 1) {
                    $correct = 'true';
                    $totalcorrect += $result;
                    $numberofcorrectanswers++;
                }
                echo "<result id='{$answer->id}' correct='{$correct}'>{$result}</result>";
            }
            if ($numberofcorrectanswers == 0) {
                $newresult = 100;
            } else if ($totalanswers > 0) {
                $newresult = intval((100 * $totalcorrect) / $totalanswers);
            } else {
                $newresult = 0;
            }
            if ($newresult != $quiz->questionresult) {
                $quiz->questionresult = $newresult;
                $upd = new stdClass;
                $upd->id = $quiz->id;
                $upd->questionresult = $quiz->questionresult;
                $DB->update_record('realtimequiz', $upd);
            }
            $classresult = intval(($quiz->classresult + $quiz->questionresult) / $question->questionnum);
            echo '</results>';
            if ($numberofcorrectanswers == 0) {
                echo '<nocorrect/>';
            }
            echo '<statistics>';
            echo '<questionresult>'.$quiz->questionresult.'</questionresult>';
            echo '<classresult>'.$classresult.'</classresult>';
            echo '</statistics>';
        }
    }*/
}

/**
 * Record the answer given
 * @param int $quizid
 * @param int $questionnum
 * @param int $userid
 * @param int $answerid
 * @param context $context
 * @throws coding_exception
 * @throws dml_exception
 */
function realtimequiz_record_answer($quizid, $questionnum, $userid, $answerid, $context) {
  echo '<status>answerreceived</status>';
  /*  global $DB;

    $quiz = $DB->get_record('realtimequiz', array('id' => $quizid));
    $question = $DB->get_record('realtimequiz_question', array('id' => $quiz->currentquestion));
    $answer = $DB->get_record('realtimequiz_answer', array('id' => $answerid));

    if (($answer->questionid == $quiz->currentquestion)
        && ($question->questionnum == $questionnum)
    ) {
        $conditions = array(
            'questionid' => $question->id, 'sessionid' => $quiz->currentsessionid, 'userid' => $userid
        );
        if (!$DB->record_exists('realtimequiz_submitted', $conditions)) {
            // If we already have an answer from them, do not send error, as this is likely to be the
            // result of lost network packets & resends, just ignore silently.
            $submitted = new stdClass;
            $submitted->questionid = $question->id;
            $submitted->sessionid = $quiz->currentsessionid;
            $submitted->userid = $userid;     // FIXME: make sure the userid is on the course.
            $submitted->answerid = $answerid;
            $DB->insert_record('realtimequiz_submitted', $submitted);
        }
        echo '<status>answerreceived</status>';

    } else {

        // Answer is not for the current question - so send the current question.
        realtimequiz_send_question($quizid, $context,false);
    }
    */
}

/**
 * Count the number of students connected
 * @param int $quizid
 * @throws coding_exception
 * @throws dml_exception
 */
function realtimequiz_number_students($quizid) {

    global $CFG, $DB, $USER;
  /*  if ($realtimequiz = $DB->get_record("realtimequiz", array('id' => $quizid))) {
        if ($course = $DB->get_record("course", array('id' => $realtimequiz->course))) {
            if ($cm = get_coursemodule_from_instance("realtimequiz", $realtimequiz->id, $course->id)) {
                if ($CFG->version < 2011120100) {
                    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
                } else {
                    $context = context_module::instance($cm->id);
                }
                // Is it a student and not a teacher?
                if (!has_capability('mod/realtimequiz:control', $context, $USER->id)) {
                    $cond = array(
                        'userid' => $USER->id, 'questionid' => 0, 'answerid' => 0,
                        'sessionid' => $realtimequiz->currentsessionid,
                    );
                    if (!$DB->record_exists("realtimequiz_submitted", $cond)) {
                        $data = new stdClass();
                        $data->questionid = 0;
                        $data->userid = $USER->id;
                        $data->answerid = 0;
                        $data->sessionid = $realtimequiz->currentsessionid;
                        $DB->insert_record('realtimequiz_submitted', $data);
                    }
                }
            }
        }*/

        //TTT
        $quizid = required_param('quizid', PARAM_INT);
        $attempts = $DB->get_records_sql("SELECT id FROM {realtimequiz_attempts} where realtimequiz={$quizid} AND state='inprogress' AND preview=0");
        $num_students = sizeof($attempts);
        echo "<numberstudents>";
        echo $num_students;

      //  echo ($DB->count_records('realtimequiz_submitted', array(
      //      'questionid' => 0, 'answerid' => 0, 'sessionid' => $realtimequiz->currentsessionid
      //  )));
        echo "</numberstudents>";

    }


/**
 * Send 'quiz running' status.
 */
function realtimequiz_send_running() {
    echo '<status>waitforquestion</status>';
}

/**
 * Send 'quiz not running' status.
 */
function realtimequiz_send_not_running() {
    echo '<status>quiznotrunning</status>';
}

/**
 * Send 'waiting for question to start' status.
 * @throws dml_exception
 */
function realtimequiz_send_await_question() {
    //TTT
    $waittime = get_config('realtimequiz', 'awaittime');
    //$waittime = 10;
    $waittime = 2;
    echo '<status>waitforquestion</status>';
    echo "<waittime>{$waittime}</waittime>";
    //echo add_requesttype($requesttype);
}

/**
 * Send 'waiting for results' status.
 * @param int $timeleft
 * @throws dml_exception
 */
function realtimequiz_send_await_results($timeleft) {
    $waittime = (int)get_config('realtimequiz', 'awaittime');
    // We need to randomise the waittime a little, otherwise all clients will
    // start sending 'waitforquestion' simulatiniously after the first question -
    // it can cause a problem is there is a large number of clients.
    // If waittime is 1 sec, there is no point to randomise it.
    $waittime = 2;
    $waittime = mt_rand(1, $waittime) + $timeleft;
    echo '<status>waitforresults</status>';
    echo "<waittime>{$waittime}</waittime>";
}

/**
 * Send the final results details.
 * @param int $quizid
 * @throws dml_exception
 */
function realtimequiz_send_final_results($quizid) {
    global $DB;

    /*$quiz = $DB->get_record('realtimequiz', array('id' => $quizid));
    $questionnum = $DB->get_field('realtimequiz_question', 'questionnum', array('id' => $quiz->currentquestion));
    echo '<status>finalresults</status>';
    echo '<classresult>'.intval($quiz->classresult / $questionnum).'</classresult>';
    */
    echo '<status>finalresults</status>';
    echo '<classresult>Well done!</classresult>';
}

/**
 * Check if the current status should change due to a timeout.
 * @param int $quizid
 * @param int $status
 * @return int|mixed
 * @throws dml_exception
 */
function realtimequiz_update_status($quizid, $status) {
    global $DB;



    if ($status == REALTIMEQUIZ_STATUS_PREVIEWQUESTION) {
        $quiz = $DB->get_record('realtimequiz', array('id' => $quizid));
        if ($quiz->nextendtime < time()) {
            //TTT
            //$questiontime = $DB->get_field('realtimequiz_question', 'questiontime', array('id' => $quiz->currentquestion));
            //$questiontime = 10;
            $questiontime = $quiz->questiontime;


            //if ($questiontime == 0) {
            //    $questiontime = $quiz->questiontime;
            //}
            $timeleft = $quiz->nextendtime - time() + $questiontime;
            if ($timeleft > 0) {
                $quiz->status = REALTIMEQUIZ_STATUS_SHOWQUESTION;
                $quiz->nextendtime = time() + $timeleft;
            } else {
                $quiz->status = REALTIMEQUIZ_STATUS_SHOWRESULTS;
                //echo "Issue with changing quiz status";
            }

            $upd = new stdClass;
            $upd->id = $quiz->id;
            $upd->status = $quiz->status;
            $upd->nextendtime = $quiz->nextendtime;
            $DB->update_record('realtimequiz', $upd);

            $status = $quiz->status;

        }
    } else if ($status == REALTIMEQUIZ_STATUS_SHOWQUESTION) {
        $nextendtime = $DB->get_field('realtimequiz', 'nextendtime', array('id' => $quizid));
        if ($nextendtime < time()) {
            $status = REALTIMEQUIZ_STATUS_SHOWRESULTS;
            $DB->set_field('realtimequiz', 'status', $status, array('id' => $quizid));
        }
    } else if (($status != REALTIMEQUIZ_STATUS_NOTRUNNING) && ($status != REALTIMEQUIZ_STATUS_READYTOSTART)
        && ($status != REALTIMEQUIZ_STATUS_SHOWRESULTS) && ($status != REALTIMEQUIZ_STATUS_FINALRESULTS)) {
        // Bad status = probably should set it back to 0.
        $status = REALTIMEQUIZ_STATUS_NOTRUNNING;
        $DB->set_field('realtimequiz', 'status', REALTIMEQUIZ_STATUS_NOTRUNNING, array('id' => $quizid));
    }


    return $status;
}

/**
 * Is the quiz currently running?
 * @param int $status
 * @return bool
 */
function realtimequiz_is_running($status) {
    return ($status > REALTIMEQUIZ_STATUS_NOTRUNNING && $status < REALTIMEQUIZ_STATUS_FINALRESULTS);
}

/**
 * Check the question requested matches the current question.
 * @param int $quizid
 * @param int $questionnumber
 * @return bool
 * @throws dml_exception
 */
function realtimequiz_current_question($quizid, $questionnumber) {
    global $DB;


    $questionid = $DB->get_field('realtimequiz', 'currentquestion', array('id' => $quizid));
    if (!$questionid) {
        return false;
    }
  if ($questionnumber != $questionid) {
        return false;
    }


    return true;
}



function add_requesttype($requesttype)
{
  echo "<requesttype>";
  echo $requesttype;
  echo "</requesttype>";
}

use mod_realtimequiz\output\navigation_panel_attempt;
use mod_realtimequiz\output\renderer;
use mod_realtimequiz\realtimequiz_attempt;

function get_attempt_page()
{
    // contents of attempt.php

     // This script displays a particular page of a realtimequiz attempt that is in progress.



    //require_once(__DIR__ . '/../../config.php');
    //require_once($CFG->dirroot.'/mod/realtimequiz/locallib.php');

    // Look for old-style URLs, such as may be in the logs, and redirect them to startattemtp.php.
    if ($id = optional_param('id', 0, PARAM_INT)) {
        redirect($CFG->wwwroot . '/mod/realtimequiz/startattempt.php?cmid=' . $id . '&sesskey=' . sesskey());
    } else if ($qid = optional_param('q', 0, PARAM_INT)) {
        if (!$cm = get_coursemodule_from_instance('realtimequiz', $qid)) {
            throw new \moodle_exception('invalidrealtimequizid', 'realtimequiz');
        }
        redirect(new moodle_url('/mod/realtimequiz/startattempt.php',
                ['cmid' => $cm->id, 'sesskey' => sesskey()]));
    }

    // Get submitted parameters.
    $attemptid = required_param('attempt', PARAM_INT);
    $page = optional_param('page', 0, PARAM_INT);
    $cmid = optional_param('cmid', null, PARAM_INT);

    $attemptobj = realtimequiz_create_attempt_handling_errors($attemptid, $cmid);
    //$attemptobj = realtimequiz_create_attempt_handling_errors($attemptid);

    $page = $attemptobj->force_page_number_into_range($page);
    //TTT commented out the line below for no good reason other than it wouldn't work with the line
    //$PAGE->set_url($attemptobj->attempt_url(null, $page));
    // During realtimequiz attempts, the browser back/forwards buttons should force a reload.
    // TTT comented the line below out for no good reason
    //$PAGE->set_cacheable(false);
    // TTT comented the line below out for no good reason

    //$PAGE->set_secondary_active_tab("modulepage");

    // Check login.
    require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

    // Check that this attempt belongs to this user.
    if ($attemptobj->get_userid() != $USER->id) {
        if ($attemptobj->has_capability('mod/realtimequiz:viewreports')) {
            redirect($attemptobj->review_url(null, $page));
        } else {
            throw new moodle_exception('notyourattempt', 'realtimequiz', $attemptobj->view_url());
        }
    }

    // Check capabilities and block settings.
    if (!$attemptobj->is_preview_user()) {
        $attemptobj->require_capability('mod/realtimequiz:attempt');
        if (empty($attemptobj->get_realtimequiz()->showblocks)) {
            $PAGE->blocks->show_only_fake_blocks();
        }

    } else {
        navigation_node::override_active_url($attemptobj->start_attempt_url());
    }

    // If the attempt is already closed, send them to the review page.
    if ($attemptobj->is_finished()) {
        redirect($attemptobj->review_url(null, $page));
    } else if ($attemptobj->get_state() == realtimequiz_attempt::OVERDUE) {
        redirect($attemptobj->summary_url());
    }

    // Check the access rules.
    $accessmanager = $attemptobj->get_access_manager(time());
    $accessmanager->setup_attempt_page($PAGE);
    /** @var renderer $output */
    $output = $PAGE->get_renderer('mod_realtimequiz');
    $messages = $accessmanager->prevent_access();
    if (!$attemptobj->is_preview_user() && $messages) {
        throw new \moodle_exception('attempterror', 'realtimequiz', $attemptobj->view_url(),
                $output->access_messages($messages));
    }
    if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
        redirect($attemptobj->start_attempt_url(null, $page));
    }

    // Set up auto-save if required.
    $autosaveperiod = get_config('realtimequiz', 'autosaveperiod');
    if ($autosaveperiod) {
        $PAGE->requires->yui_module('moodle-mod_realtimequiz-autosave',
                'M.mod_realtimequiz.autosave.init', [$autosaveperiod]);
    }

    // Log this page view.
    $attemptobj->fire_attempt_viewed_event();

    // Get the list of questions needed by this page.
    $slots = $attemptobj->get_slots($page);

    // Check.
    if (empty($slots)) {
        throw new moodle_exception('noquestionsfound', 'realtimequiz', $attemptobj->view_url());
    }

    // Update attempt page, redirecting the user if $page is not valid.
    if (!$attemptobj->set_currentpage($page)) {
        redirect($attemptobj->start_attempt_url(null, $attemptobj->get_currentpage()));
    }

    // Initialise the JavaScript.
    $headtags = $attemptobj->get_html_head_contributions($page);
    $PAGE->requires->js_init_call('M.mod_realtimequiz.init_attempt_form', null, false, realtimequiz_get_js_module());
    \core\session\manager::keepalive(); // Try to prevent sessions expiring during realtimequiz attempts.

    // Arrange for the navigation to be displayed in the first region on the page.
    $navbc = $attemptobj->get_navigation_panel($output, navigation_panel_attempt::class, $page);
    $regions = $PAGE->blocks->get_regions();
    $PAGE->blocks->add_fake_block($navbc, reset($regions));

    $headtags = $attemptobj->get_html_head_contributions($page);
    $PAGE->set_title($attemptobj->attempt_page_title($page));
    $PAGE->add_body_class('limitedwidth');
    $PAGE->set_heading($attemptobj->get_course()->fullname);
    $PAGE->activityheader->disable();
    if ($attemptobj->is_last_page($page)) {
        $nextpage = -1;
    } else {
        $nextpage = $page + 1;
    }

    echo $output->attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage);





}
