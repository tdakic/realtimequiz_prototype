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
 * This dynamically sends quiz data to clients
 *
 * @copyright Davo Smith <moodle@davosmith.co.uk>
 * @package mod_realtimequiz
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

define('AJAX_SCRIPT', true);

use mod_realtimequiz\realtimequiz_settings;

require_once('../../config.php');
global $CFG, $DB, $USER, $PAGE;
require_once($CFG->dirroot.'/mod/realtimequiz/locallib_rt.php');
require_once($CFG->dirroot.'/mod/realtimequiz/locallib.php');
require_once($CFG->libdir.'/filelib.php');

require_login();
require_sesskey();
$requesttype = required_param('requesttype', PARAM_ALPHA);
$quizid = required_param('quizid', PARAM_INT);
$attempt = optional_param('attempt', -1, PARAM_INT );

/**
 * Go to the requested question.
 * @param context $context
 * @param int $quizid
 * @param int $questionnum
 * @throws coding_exception
 * @throws dml_exception
 */

 // ****************************************************************




/***********************************************************
 * End of functions - start of main code
 ***********************************************************/




realtimequiz_start_response();

if (!$realtimequiz = $DB->get_record("realtimequiz", array('id' => $quizid))) {
    realtimequiz_send_error("Quiz ID incorrect");
    realtimequiz_end_response();
    die();
}
if (!$course = $DB->get_record("course", array('id' => $realtimequiz->course))) {
    realtimequiz_send_error("Course is misconfigured");
    realtimequiz_end_response();
    die();
}
if (!$cm = get_coursemodule_from_instance("realtimequiz", $realtimequiz->id, $course->id)) {
    realtimequiz_send_error("Course Module ID was incorrect");
    realtimequiz_end_response();
    die();
}
if ($CFG->version < 2011120100) {
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
} else {
    $context = context_module::instance($cm->id);
}
$PAGE->set_context($context);

if (!has_capability('mod/realtimequiz:attempt', $context)) {
    realtimequiz_send_error(get_string('notallowedattempt', 'realtimequiz'));
    realtimequiz_end_response();
    die();
}

$status = $realtimequiz->status;
if ($status === false) {
    realtimequiz_send_error(get_string('badquizid', 'realtimequiz').$quizid);
} else {
    $status = realtimequiz_update_status($quizid, $status); // Check if the current status should change due to a timeout.

    if ($requesttype == 'quizrunning') {
        if (($status == REALTIMEQUIZ_STATUS_NOTRUNNING) || ($status == REALTIMEQUIZ_STATUS_FINALRESULTS)) {
            // Not running / finished.
            realtimequiz_send_not_running();
        } else {
            //include("./startattempt.php");
            realtimequiz_send_running();
        }
    } else if ($requesttype == 'startquiz') {
        if (has_capability('mod/realtimequiz:control', $context)) {
            $session = new stdClass();
            $session->timestamp = time();
            $session->name = optional_param('sessionname', '', PARAM_TEXT);
            $session->quizid = $quizid;
            $session->id = $DB->insert_record('realtimequiz_session', $session);

            $quiz = $DB->get_record('realtimequiz', array('id' => $quizid));
            $quiz->currentsessionid = $session->id;
            $quiz->status = REALTIMEQUIZ_STATUS_READYTOSTART;
            $quiz->currentquestion = 0;
            $quiz->classresult = 0;
            $quiz->questionresult = 0;
            //$quiz->questiontime = 10;
            $DB->update_record('realtimequiz', $quiz);

            //TTT change this later when you add to the edit quiz page



            //include("./startattempt.php");
            realtimequiz_send_running();

        } else {
            realtimequiz_send_error(get_string('notauthorised', 'realtimequiz'));
        }

    } else {

        switch ($status) {

            case REALTIMEQUIZ_STATUS_NOTRUNNING:   // Quiz is not running.
                realtimequiz_send_not_running(); // We don't care what they asked for.
                break;

            case REALTIMEQUIZ_STATUS_READYTOSTART: // Quiz is ready to start.
                if ($requesttype == 'nextquestion') {
                    //realtimequiz_goto_question($context, $quizid, 1);
                    // ***********
                    $questionnum = 1;

                    if (has_capability('mod/realtimequiz:control', $context)) {
                      $questioncount = $DB->count_records('realtimequiz_slots', ['realtimequizid' => $quizid]);
                      $quiz = $DB->get_record('realtimequiz', array('id' => $quizid));
                      // Update the question statistics.
                      $quiz->classresult += $quiz->questionresult;
                      $quiz->questionresult = 0;

                      $question_slot = $quiz->currentquestion; //is 0 at this point

                      if ($question_slot < $questioncount) {
                          $quiz->currentquestion = $question_slot + 1;
                          $quiz->status = REALTIMEQUIZ_STATUS_PREVIEWQUESTION;
                          $quiz->nextendtime = time() + 2;    // Give everyone a chance to get the question before starting.
                          $DB->update_record('realtimequiz', $quiz); // FIXME - not update all fields?

                              if (!$question = $DB->get_record('realtimequiz_slots', array('slot' => $question_slot+1,'realtimequizid' => $quizid))) {
                                  realtimequiz_send_error(get_string('badcurrentquestion', 'realtimequiz').$question_slot);
                              } else {

                                  realtimequiz_send_start_of_question($question, $quizid);
                                  $_GET['page'] = $quiz->currentquestion - 1;
                                  include("./attempt.php");
                                  //required ?cmid=20&quizid=3&atemptid=210
                                  realtimequiz_send_end_of_question($quiz,$question,true);

                              }

                          }
                          // **********

                          //realtimequiz_send_question($quizid, $context, true);
                       else { // Assume we have run out of questions.
                          $quiz->status = REALTIMEQUIZ_STATUS_FINALRESULTS;
                          $DB->update_record('realtimequiz', $quiz); // FIXME - not update all fields?
                          //realtimequiz_send_final_results($quizid);
                          echo '<status>finalresults</status>';
                          echo '<classresult>';
                          include("./report_final_results.php");
                          echo 'Well done!</classresult>';
                      }
                  } else {
                      realtimequiz_send_error(get_string('notauthorised', 'realtimequiz'));
                  }

                    // *********
                } else {
                    realtimequiz_send_await_question(); // Don't care what they asked for.
                    realtimequiz_number_students($quizid);
                }
                break;

            case REALTIMEQUIZ_STATUS_PREVIEWQUESTION: // Previewing question (send it out, but ask them to wait before showing).
                //TTT
                // function call didn't work, so the code is copied and pasted
                //realtimequiz_send_question($quizid, $context, true); // We don't care what they asked for.
                $preview = true;
                if (!$quiz = $DB->get_record('realtimequiz', array('id' => $quizid))) {
                    realtimequiz_send_error(get_string('badquizid', 'realtimequiz').$quizid);
                } else {
                    $question_slot = $quiz->currentquestion;
                      echo "<question_slot>{$question_slot}</question_slot>";

                    if (!$question = $DB->get_record('realtimequiz_slots', array('slot' => $question_slot,'realtimequizid' => $quizid))) {
                        realtimequiz_send_error(get_string('badcurrentquestion', 'realtimequiz'));
                    } else {

                        realtimequiz_send_start_of_question($question, $quizid);
                        $_GET['page'] = $quiz->currentquestion - 1;
                        include("./attempt.php");
                        //required ?cmid=20&quizid=3&atemptid=210
                        realtimequiz_send_end_of_question($quiz,$question, $preview );

                    }

                }

                break;

            case REALTIMEQUIZ_STATUS_SHOWQUESTION: // Question being displayed.
                if ($requesttype == 'getquestion' || $requesttype == 'nextquestion' || $requesttype == 'teacherrejoin') {
                    // Student asked for a question - so send it.
                    //TTT
                    //realtimequiz_send_question($quizid, $context);
                    if (!$quiz = $DB->get_record('realtimequiz', array('id' => $quizid))) {
                        realtimequiz_send_error(get_string('badquizid', 'realtimequiz').$quizid);
                    } else {
                        $question_slot = $quiz->currentquestion;
                          echo "<question_slot>{$question_slot}</question_slot>";

                        if (!$question = $DB->get_record('realtimequiz_slots', array('slot' => $question_slot,'realtimequizid' => $quizid))) {
                            realtimequiz_send_error(get_string('badcurrentquestion', 'realtimequiz').$question_slot."line 195");
                        } else {

                            realtimequiz_send_start_of_question($question, $quizid);
                            $_GET['page']= $quiz->currentquestion - 1;
                            include("./attempt.php");
                            realtimequiz_send_end_of_question($quiz,$question);

                        }

                    }

                } else if ($requesttype == 'postanswer') {
                    $questionnum = required_param('question', PARAM_INT);
                    $userid = $USER->id;
                    $answerid = required_param('answer', PARAM_INT);
                    realtimequiz_record_answer($quizid, $questionnum, $userid, $answerid, $context);

                } else if ($requesttype == 'getresults') {
                    $questionnum = required_param('question', PARAM_INT);
                    if (realtimequiz_current_question($quizid, $questionnum)) {
                        $timeleft = $DB->get_field('realtimequiz', 'nextendtime', array('id' => $quizid)) - time();
                        if ($timeleft < 0) {
                            $timeleft = 0;
                        }
                        realtimequiz_send_await_results($timeleft); // Results not yet ready.
                    } else {
                        // TTT
                        //realtimequiz_send_question($quizid, $context); // Asked for results for wrong question.
                        if (!$quiz = $DB->get_record('realtimequiz', array('id' => $quizid))) {
                            realtimequiz_send_error(get_string('badquizid', 'realtimequiz').$quizid);
                        } else {
                            $question_slot = $quiz->currentquestion;
                              echo "<question_slot>{$question_slot}</question_slot>";


                            if (!$question = $DB->get_record('realtimequiz_slots', array('slot' => $question_slot,'realtimequizid' => $quizid))) {
                                realtimequiz_send_error(get_string('badcurrentquestion', 'realtimequiz').$question_slot."line 235");
                            } else {

                                realtimequiz_send_start_of_question($question, $quizid);
                                $_GET['page'] = $quiz->currentquestion - 1;
                                include("./attempt.php");
                                //required ?cmid=20&quizid=3&atemptid=210
                                realtimequiz_send_end_of_question($quiz,$question );

                            }

                        }
                    }

                } else {
                    realtimequiz_send_error(get_string('unknownrequest', 'realtimequiz').$requesttype.'\'');
                }
                break;

            case REALTIMEQUIZ_STATUS_SHOWRESULTS: // Results being displayed.
                if ($requesttype == 'getquestion') { // Asking for the next question.
                    realtimequiz_send_await_question();


                } else if ($requesttype == 'postanswer' || $requesttype == 'getresults' || $requesttype == 'teacherrejoin') {
                    //realtimequiz_send_results($quizid);
                    sleep(1); // alows everyone to submit
                    $quiz = $DB->get_record('realtimequiz', array('id' => $quizid));
                    $questioncount = $DB->count_records('realtimequiz_slots', ['realtimequizid' => $quizid]);
                    echo '<status>showresults</status>';
                    echo '<questionnum>';
                    echo $quiz->currentquestion;
                    echo '</questionnum>';
                    echo '<questioncount>';
                    echo $questioncount;
                    echo '</questioncount>';


                    echo '<statistics>';
                    echo '<questionresult>Results are good!</questionresult>';
                    echo '<classresult>Class result are best!</classresult>';
                    echo '</statistics>';

                    echo "<result>";
                    //$_GET['page'] = $quiz->currentquestion;
                    include("./review_RT.php");
                    echo "</result>";

                } else if ($requesttype == 'nextquestion') {
                    $clientquestionnum = required_param('currentquestion', PARAM_INT);
                    $question_slot = $DB->get_field('realtimequiz', 'currentquestion', array('id' => $quizid));
                    //$questionnum = $DB->get_field('realtimequiz_question', 'questionnum', array('id' => $question_slot));
                    //if ($clientquestionnum != $questionnum){
                    if (false){
                        realtimequiz_send_results($quizid);
                    } else {
                        $questionnum++;
                        //realtimequiz_goto_question($context, $quizid, $questionnum);

                        echo "<question_slot>{$question_slot}</question_slot>";

                        if (has_capability('mod/realtimequiz:control', $context)) {
                          $questioncount = $DB->count_records('realtimequiz_slots', ['realtimequizid' => $quizid]);
                          $quiz = $DB->get_record('realtimequiz', array('id' => $quizid));
                          // Update the question statistics.
                          $quiz->classresult += $quiz->questionresult;
                          $quiz->questionresult = 0;

                          // the DB should be ultimate authority - fix when decided if more than one question per page is allowed
                          $question_slot = $quiz->currentquestion;

                          if ($question_slot < $questioncount) {
                              $quiz->currentquestion = $question_slot + 1;
                              $question_slot = $question_slot + 1;

                              $quiz->status = REALTIMEQUIZ_STATUS_PREVIEWQUESTION;
                              $quiz->nextendtime = time() + 2;    // Give everyone a chance to get the question before starting.
                              $DB->update_record('realtimequiz', $quiz); // FIXME - not update all fields?

                                  if (!$question = $DB->get_record('realtimequiz_slots', array('slot' => $question_slot,'realtimequizid' => $quizid))) {
                                      realtimequiz_send_error(get_string('badcurrentquestion', 'realtimequiz').$question_slot."line 294");
                                  } else {

                                      realtimequiz_send_start_of_question($question, $quizid);
                                      $_GET['page'] = $quiz->currentquestion - 1;
                                      include("./attempt.php");
                                      realtimequiz_send_end_of_question($quiz,$question,True);

                                  }

                              }
                              // **********

                              //realtimequiz_send_question($quizid, $context, true);
                           else { // Assume we have run out of questions.
                              $quiz->status = REALTIMEQUIZ_STATUS_FINALRESULTS;
                              $DB->update_record('realtimequiz', $quiz); // FIXME - not update all fields?
                              include("./submitattempt.php");
                              //realtimequiz_send_final_results($quizid);
                              (2);
                              echo '<status>finalresults</status>';
                              echo '<classresult>';
                              //$_GET["id"]=23;
                              //$_GET["mode"]="overview";
                              //include("./report_final_results.php");
                              echo 'Well done!</classresult>';
                          }
                      } else {
                          realtimequiz_send_error(get_string('notauthorised', 'realtimequiz'));
                      }

                        //***********************************************
                    }

                } else {
                    realtimequiz_send_error(get_string('unknownrequest', 'realtimequiz').$requesttype.'\'');
                }
                break;

            case REALTIMEQUIZ_STATUS_FINALRESULTS: // Showing the final totals, etc.
                include("./submitattempt.php");
                sleep(2);
                //realtimequiz_send_final_results($quizid);
                echo '<status>finalresults</status>';
                echo '<classresult>';
                //include("./report_final_results.php");
                echo 'Well done!</classresult>';
                break;

            default:
                realtimequiz_send_error(get_string('incorrectstatus', 'realtimequiz').$status.'\'');
                break;
        }
    }
}

realtimequiz_end_response();
