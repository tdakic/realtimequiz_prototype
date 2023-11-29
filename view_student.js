/**
 * Code for a student taking the quiz
 *
 * @author: Davosmith
 * @package realtimequiz
 **/

// Set up the variables used throughout the javascript
var realtimequiz = {};
realtimequiz.givenanswer=false;
realtimequiz.timeleft=-1;
realtimequiz.timer=null;
realtimequiz.questionnumber=-1;
realtimequiz.answernumber=-1;
realtimequiz.questionxml=null;
realtimequiz.controlquiz = false;
realtimequiz.lastrequest = '';
realtimequiz.sesskey=-1;
realtimequiz.coursepage='';
realtimequiz.siteroot='';
realtimequiz.myscore=0;
realtimequiz.myanswer=-1;
realtimequiz.resendtimer = null;
realtimequiz.resenddelay = 2000; // How long to wait before resending request
realtimequiz.alreadyrunning = false;
realtimequiz.questionviewinitialised = false;

realtimequiz.markedquestions = 0;
// TTT
realtimequiz.attemptid = -1;
realtimequiz.cmid = -1;
realtimequiz.response_xml_text ='';


realtimequiz.image = [];
realtimequiz.text = [];

/**************************************************
 * Debugging
 **************************************************/
var realtimequiz_maxdebugmessages = 0;
var realtimequiz_debug_stop = false;

function realtimequiz_debugmessage(message) {
    if (realtimequiz_maxdebugmessages > 0) {
        realtimequiz_maxdebugmessages -= 1;

        var currentTime = new Date();
        var outTime = currentTime.getHours() + ':' + currentTime.getMinutes() + ':' + currentTime.getSeconds() + '.' + currentTime.getMilliseconds() + ' - ';

        var dbarea = document.getElementById('debugarea');
        dbarea.innerHTML += outTime + message + '<br />';
    }
}

function realtimequiz_debug_stopall() {
    realtimequiz_debug_stop = true;
}


/**************************************************
 * Some values that need to be passed in to the javascript
 **************************************************/

function realtimequiz_set_maxanswers(number) {
    realtimequiz.maxanswers = number;
}

function realtimequiz_set_quizid(id) {
    realtimequiz.quizid = id;
}

function realtimequiz_set_userid(id) {
    realtimequiz.userid = id;
}

function realtimequiz_set_sesskey(key) {
    realtimequiz.sesskey = key;
}

function realtimequiz_set_image(name, value) {
    realtimequiz.image[name] = value;
}

function realtimequiz_set_text(name, value) {
    realtimequiz.text[name] = value;

}

function realtimequiz_set_coursepage(url) {
    realtimequiz.coursepage = url;
}

function realtimequiz_set_siteroot(url) {
    realtimequiz.siteroot = url;
}

function realtimequiz_set_running(running) {
    realtimequiz.alreadyrunning = running;
}
// TTT
function realtimequiz_set_cmid(value) {
    realtimequiz.cmid = value;
}
/**************************************************
 * Set up the basic layout of the student view
 **************************************************/
function realtimequiz_init_student_view() {
    var msg="<center><input type='button' onclick='realtimequiz_start_attempt() ;' value='"+realtimequiz.text['joinrealtimequiz']+"' />";
    msg += "<p id='status'>"+realtimequiz.text['joininstruct']+"</p></center>";
    document.getElementById('questionarea').innerHTML = msg;
}

function realtimequiz_init_question_view() {

  //
  if (realtimequiz.questionviewinitialised) {
        return;
    }
    if (realtimequiz.controlquiz) {

        document.getElementById("questionarea").innerHTML = "<h1><span id='questionnumber'>"+realtimequiz.text['waitstudent']+"</span></h1><div id='numberstudents'></div><div id='questionimage'></div><div id='questiontext'>"+realtimequiz.text['clicknext']+"</div><ul id='answers'></ul><p><span id='status'></span> <span id='timeleft'></span></p>";
        document.getElementById("questionarea").innerHTML += "<div id='questioncontrols'></div><br style='clear: both;' />";
        realtimequiz_update_next_button(true);
        // To trigger the periodic sending to get the number of students

        realtimequiz_get_question();
    } else {
        document.getElementById("questionarea").innerHTML = "<h1><span id='questionnumber'>"+ realtimequiz.text['waitfirst']+"</span></h1><div id='questionimage'></div><div id='questiontext'></div><ul id='answers'></ul><p><span id='status'></span> <span id='timeleft'></span></p><br style='clear: both;' />";

        realtimequiz_get_question();
        realtimequiz.myscore = 0;
    }
    realtimequiz.questionviewinitialised = true;
}

/**************************************************
 * Functions to display information on the screen
 **************************************************/
function realtimequiz_set_status(status) {
    document.getElementById('status').innerHTML = status;
}

function realtimequiz_set_question_number(num, total) {
    document.getElementById('questionnumber').innerHTML = realtimequiz.text['question'] + ' ' + num + ' / ' + total;
    realtimequiz.questionnumber = num;

}

function realtimequiz_set_question_text(text) {
    document.getElementById('questiontext').innerHTML = text.replace(/\n/g, '<br />');
}


function realtimequiz_set_question(response_xml_text) {

    if (response_xml_text == null) {
        alert('realtimequiz.questionxml is null');
        return;
    }

    const parser = new DOMParser();
    // use text/html instead of text/xml to perserve css
    response_xml = parser.parseFromString(response_xml_text, 'text/html');

    var qnum = node_text(response_xml.getElementsByTagName('questionnumber').item(0));

    var total = node_text(response_xml.getElementsByTagName('questioncount').item(0));


    if (realtimequiz.questionnumber == qnum) {  // If the question is already displaying, assume this message is the result of a resend request
        return;
    }
    realtimequiz_set_question_number(qnum, total);

    question_text = response_xml.getElementsByTagName('questionpage').item(0);

    document.getElementById('questiontext').innerHTML = question_text.innerHTML;


    nav_button = document.getElementById("mod_realtimequiz-next-nav");

    // hide the navigation butons, but we may want tobring a button back so we can count how many students
    // submitted their answer
    nav_buttons = document.getElementsByClassName("submitbtns");
    for (var i = 0; i < nav_buttons.length; i++) {
      nav_buttons[i].style.display = 'none';
    }

    //hide the info class - the one to the right of the question with the flag
    // we might want that to show?
    // loop is there because we might want to have more than one question per page
    info_section = document.getElementsByClassName("info");
    for (var i = 0; i < info_section.length; i++) {
        info_section[i].style.display = 'none';
    }

    //hopefully this prevents redirection
    $(function () {
            $('#responseform').submit(function (event) {
                event.preventDefault();
                var form = document.getElementById('responseform');
                var formData = new FormData(form);

                $.ajax({
                    url: 'processattempt.php',
                    method: 'POST',
                    async: false,
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {

                    },
                    error: function (xhr, status, error) {
                        alert('Your question was not submitted successfully.');

                    }
                });
            });
        });

    realtimequiz_start_timer(parseInt(node_text(response_xml.getElementsByTagName('questiontime').item(0))), false);
}

async function submit_attempt_and_show_final_results(quizresponse){

  $.ajax({
            type: "GET",
            url: realtimequiz.siteroot + "/mod/realtimequiz/submitattempt.php" ,
            async: false,
            data: { cmid: realtimequiz.cmid,
                    quizid: realtimequiz.quizid,
                    sesskey:  realtimequiz.sesskey,
                    attempt: realtimequiz.attemptid
                  },
            success : function(data) {

                alert("submitted attempt:" + realtimequiz.attemptid);

                //var dbarea = document.getElementById('bedbug');
                //dbarea.innerHTML += '<br /> 1:  ID set to:' + realtimequiz.attemptid + '<br />';

                realtimequiz_show_final_results(quizresponse);

                      //location.reload();

                    }
        });

}

// for now get the graph of the final results ... later the stats can be added to both teacher and students' views
async function realtimequiz_show_final_results(quizresponse) {

  document.getElementById('questionnumber').innerHTML = '<h1>'+realtimequiz.text['finalresults']+'</h1>';
  if (!realtimequiz.controlquiz) {
      document.getElementById('questiontext').innerHTML="Coming soon!";
  }
  else {
      search_params = new URLSearchParams();
      search_params.append("id",realtimequiz.cmid);
      search_params.append("mode","overview");

      let myObject = await fetch(realtimequiz.siteroot+"/mod/realtimequiz/report_final_results.php" + "?" + search_params,{method: 'POST', mode:'no-cors'});
      let myText = await myObject.text();


      //myText = node_text(quizresponse.getElementsByTagName('classresult').item(0));

      var myDiv = document.createElement('div');
      myDiv.id = 'ttt_div';

      myDiv.innerHTML = myText;

      var scripts = myDiv.getElementsByTagName('script');
      script_lines = scripts[0].text;

      document.getElementById('questiontext').innerHTML="";
      document.getElementById('questiontext').appendChild(myDiv);
      eval(script_lines);


  }


}

function realtime_quiz_process_results_xml(myText)
{

  const result_parser = new DOMParser();
  const result_xml = result_parser.parseFromString(myText, 'text/xml');
  var graph = result_xml.getElementsByClassName('graph').item(0);

  document.getElementById('questionarea').innerHTML = myText;
}



/**************************************************
 * Functions to manage the on-screen timer
 **************************************************/
function realtimequiz_start_timer(counttime, preview) {
    realtimequiz_stop_timer();

    //alert(counttime + " Starting timer: "+ preview);
    if (preview) {
        realtimequiz_set_status(realtimequiz.text['displaynext']);
    } else {
        realtimequiz_set_status(realtimequiz.text['timeleft']);
    }
    realtimequiz.timeleft=counttime+1;
    realtimequiz.timer=setInterval("realtimequiz_timer_tick("+preview+")", 1000);
    realtimequiz_timer_tick();
}

function realtimequiz_stop_timer() {
    if (realtimequiz.timer != null) {
        clearInterval(realtimequiz.timer);
        realtimequiz.timer = null;
    }
}

function realtimequiz_timer_tick(preview) {
    realtimequiz.timeleft--;
    if (realtimequiz.timeleft <= 0) {
        realtimequiz_stop_timer();
        realtimequiz.timeleft=0;
        if (preview) {
            //alert("set_question in in realtimequiz_timer_tick");
            realtimequiz_set_question(realtimequiz.response_xml_text);
        } else {
            realtimequiz_set_status(realtimequiz.text['questionfinished']);

            $('#responseform').submit();

            document.getElementById('timeleft').innerHTML = "";

            realtimequiz_get_results();


        }
    } else {
        document.getElementById('timeleft').innerHTML = realtimequiz.timeleft;
    }
}


/**************************************************
 * Functions to communicate with server
 **************************************************/
function realtimequiz_delayed_request(code, time) {
    if (realtimequiz.resendtimer != null) {
        clearTimeout(realtimequiz.resendtimer);
        realtimequiz.resendtimer = null;
    }
    realtimequiz.resendtimer = setTimeout(code, time);
}

async function get_question_stats() {

  results_php_script = "/mod/realtimequiz/report_question_result_stats.php";
  search_params = new URLSearchParams(parameters);
  search_params.append("mode","statistics");
  search_params.append("id",realtimequiz.cmid);
  search_params.append("slot",realtimequiz.questionnumber);



  let myObject = await fetch(realtimequiz.siteroot+results_php_script+"?" + search_params,{method: 'POST'});
  let myText = await myObject.text();

  document.getElementById('questiontext').innerHTML += "<br />" + myText;

  return myText;
}


// TTT changed to fetch instead deprecated httprequest
async function realtimequiz_create_request(partial_url) {
  split_url = partial_url.split("?");
  requested_file = split_url[0];
  parameters = split_url[1];

  //parameters = parameters.replace(/=/g,":");
  //parameters = parameters.replace(/&/g,",");
  search_params = new URLSearchParams(parameters);

  if (!search_params.has("sesskey")){

    search_params.append("sesskey",realtimequiz.sesskey);
  }
  //TTT temp fix


  if (!search_params.has("page")){

    search_params.append("page",realtimequiz.questionnumber);
  }


  // TTT workaround
  if (search_params.has("attempt")){
    search_params.delete("attempt");

    search_params.append("attempt",realtimequiz.attemptid);
  }

  let myObject = await fetch(realtimequiz.siteroot+requested_file + "?" + search_params,{method: 'POST'});
  let myText = await myObject.text();

  await realtime_quiz_process_response_xml(myText);
}


//TTT this is to mimick realtimequiz_process_contents
function realtime_quiz_process_response_xml(response_xml_text)
{

        realtimequiz.response_xml_text= response_xml_text;
        // We've heard back from the server, so do not need to resend the request
        if (realtimequiz.resendtimer != null) {
            clearTimeout(realtimequiz.resendtimer);
            realtimequiz.resendtimer = null;
        }

        // Reduce the resend delay whenever there is a successful message
        // (assume network delays have started to recover again)
        realtimequiz.resenddelay -= 2000;
        if (realtimequiz.resenddelay < 2000) {
            realtimequiz.resenddelay = 2000;
        }

        const parser = new DOMParser();
        const response_xml = parser.parseFromString(response_xml_text, 'text/xml');

        var quizresponse = response_xml.getElementsByTagName('realtimequiz').item(0);


        //ERROR handling?
        //var quizresponse = httpRequest.responseXML.getElementsByTagName('questionpage').item(0);

        //TTT make sure this didn't happen too soon
        realtimequiz_init_question_view();

          if (quizresponse == null) {
              realtimequiz_delayed_request("realtimequiz_resend_request()", 700);

          } else {

            var quizstatus = node_text(quizresponse.getElementsByTagName('status').item(0));

              // Make sure the question view has been initialised, before displaying the question.
              realtimequiz_init_question_view();


              if (quizstatus == 'quizrunning'){
                //realtimequiz.attemptid = node_text(quizresponse.getElementsByTagName('attemptid').item(0));

                realtimequiz_init_question_view();

              } else if (quizstatus == 'showquestion') {
                  if (document.getElementById("numberstudents"))
                      document.getElementById("numberstudents").style.display = 'none' ;
                  //realtimequiz.questionxml = node_text(quizresponse.getElementsByTagName('question').item(0));
                  realtimequiz.questionxml = quizresponse.getElementsByTagName('question').item(0);

                  if (!realtimequiz.questionxml) {
                      alert(realtimequiz.text['noquestion']);

                      if (confirm(realtimequiz.text['tryagain'])) {
                          realtimequiz_resend_request();
                      } else {
                          realtimequiz_return_course();
                      }
                  } else {
                      var delay = quizresponse.getElementsByTagName('delay').item(0);

                      //TTT
                      //delay = 2;
                      if (delay) {

                          realtimequiz_start_timer(parseInt(node_text(delay)), true);

                      } else {

                        realtimequiz_set_question(response_xml_text);

                      }
                  }
                  if (realtimequiz.controlquiz){
                    realtimequiz_update_next_button(false); // Just in case.
                  }

              } else if (quizstatus == 'showresults') {

                  realtimequiz_init_question_view();

                  var questionnum = parseInt(node_text(quizresponse.getElementsByTagName('questionnum').item(0)));
                  var total = node_text(response_xml.getElementsByTagName('questioncount').item(0));

                  realtimequiz_set_question_number(questionnum,total);

                  loc3 = response_xml_text.search('<form');

                  html_to_display = response_xml_text.substring(loc3,response_xml_text.length);

                  // make the info part of the form not visible???
                  loc4 = html_to_display.search('<div class="info"');
                  html_to_display = html_to_display.substring(0,loc4+17) +'style="display:none"' + html_to_display.substring(loc4+17,response_xml_text.length);

                  // get rid of everything after the form
                  loc5 = html_to_display.search('</form');

                  html_to_display = html_to_display.substring(0,loc5);

                  //get rid of the submit button
                  loc6 = html_to_display.search('<div class="submitbtns"');
                  html_to_display = html_to_display.substring(0,loc6+23) +' style="display:none"' + html_to_display.substring(loc6+23,response_xml_text.length);

                  document.getElementById('questiontext').innerHTML = html_to_display;
                  //document.getElementById('questiontext').innerHTML = response_xml_text;

                  //if you are the teacher, display the statistics for the question
                  // else add displaying averages for students?
                  if (realtimequiz.controlquiz)
                  {
                      get_question_stats();
                  }


                  if (questionnum != realtimequiz.questionnumber) {
                      // If you have just joined and missed the question
                      // or if the teacher's PC missed the question altogether (but managed to start it)
                      realtimequiz.questionnumber = questionnum;

                  }
                  else {

                    //this will need to be dealt with....
                    realtimequiz.markedquestions++;

                  }
                  // TTT
                  //realtimequiz.questionnumber = realtimequiz.questionnumber +1;
                  if (realtimequiz.controlquiz) {
                      realtimequiz_update_next_button(true);  // Teacher controls when to display the next question
                  } else {
                      realtimequiz_delayed_request("realtimequiz_get_question()",900); // Wait for next question to be displayed
                  }

              } else if (quizstatus == 'answerreceived') {
                  if (realtimequiz.timeleft > 0) {
                      realtimequiz_set_status(realtimequiz.text['answersent']);
                  } else {
                      realtimequiz_get_results();
                  }

              } else if (quizstatus == 'waitforquestion') {
                //realtimequiz.attemptid = node_text(quizresponse.getElementsByTagName('attemptid').item(0));

                  var waittime = quizresponse.getElementsByTagName('waittime').item(0);
                  if (waittime) {
                      waittime = parseFloat(node_text(waittime)) * 1000;
                  } else {
                      waittime = 600;
                  }
                  var number_of_students = quizresponse.getElementsByTagName('numberstudents').item(0) ;
                  if (number_of_students && document.getElementById("numberstudents")) {
                      if (node_text(number_of_students) == '1') {
                          document.getElementById("numberstudents").innerHTML = node_text(number_of_students)+' '+realtimequiz.text['studentconnected'] ;
                      } else {
                          document.getElementById("numberstudents").innerHTML = node_text(number_of_students)+' '+realtimequiz.text['studentsconnected'] ;
                      }
                  }
                  realtimequiz_delayed_request("realtimequiz_get_question()", waittime);

              } else if (quizstatus == 'waitforresults') {
                  var waittime = quizresponse.getElementsByTagName('waittime').item(0);
                  if (waittime) {
                      waittime = parseFloat(node_text(waittime)) * 1000;
                  } else {
                      waittime = 1000;
                  }


                  realtimequiz_delayed_request("realtimequiz_get_results()", waittime);


              }  else if (quizstatus == 'quiznotrunning') {
                  realtimequiz_set_status(realtimequiz.text['quiznotrunning']);

              } else if (quizstatus == 'finalresults') {
                  //submit_attempt_and_show_final_results(quizresponse);
                  realtimequiz_show_final_results(quizresponse);

              } else if (quizstatus == 'error') {
                  var errmsg = node_text(quizresponse.getElementsByTagName('message').item(0));
                  alert(realtimequiz.text['servererror']+errmsg);

              } else {
                  alert(realtimequiz.text['badresponse']+httpRequest.responseText);
                  if (confirm(realtimequiz.text['tryagain'])) {
                      realtimequiz_resend_request();
                  } else {
                      realtimequiz_return_course();
                  }
              }
          }
          return;

}



function realtimequiz_resend_request() { // Only needed if something went wrong
    // Increase the resend delay, to reduce network saturation
    realtimequiz.resenddelay += 1000;
    if (realtimequiz.resenddelay > 15000) {
        realtimequiz.resenddelay = 15000;
    }

    realtimequiz_create_request(realtimequiz.lastrequest);
}

function realtimequiz_return_course() { // Go back to the course screen if something went wrong
    if (realtimequiz.coursepage == '') {
        alert('realtimequiz.coursepage not set');
    } else {
        //window.location = realtimequiz.coursepage;
    }
}

function node_text(node) { // Cross-browser - extract text from XML node
    var text = node.textContent;
    if (text != undefined) {
        return text;
    } else {
        return node.text;
    }
}

// Various requests that can be sent to the server
function realtimequiz_get_question() {

    //realtimequiz_create_request('requesttype=getquestion&quizid='+realtimequiz.quizid);
    realtimequiz_create_request('/mod/realtimequiz/quizdata.php?requesttype=getquestion&quizid='+realtimequiz.quizid+'&cmid='+ realtimequiz.cmid+'&userid='+realtimequiz.userid +'&attempt='+realtimequiz.attemptid+'&currentquestion='+realtimequiz.questionnumber);

}

function realtimequiz_get_results() {

    realtimequiz_create_request('/mod/realtimequiz/quizdata.php?requesttype=getresults&quizid='+realtimequiz.quizid+'&question='+realtimequiz.questionnumber+'&attempt='+realtimequiz.attemptid+"&showall=false"+"&page=" + (parseInt(realtimequiz.questionnumber) -1));
    //alert(realtimequiz.quizid+'&question='+realtimequiz.questionnumber+'&attempt='+realtimequiz.attemptid+"&showall=false"+"&page=" + (parseInt(realtimequiz.questionnumber) -1));
}

function realtimequiz_post_answer(ans) {
    //realtimequiz_create_request('requesttype=postanswer&quizid='+realtimequiz.quizid+'&question='+realtimequiz.questionnumber+'&userid='+realtimequiz.userid+'&answer='+ans);
    realtimequiz_create_request('/mod/realtimequiz/quizdata.php?requesttype=postanswer&quizid='+realtimequiz.quizid+'&question='+realtimequiz.questionnumber+'&userid='+realtimequiz.userid+'&answer='+ans);
}

async function realtimequiz_start_attempt() {

  $.ajax({
            type: "GET",
            url: realtimequiz.siteroot + "/mod/realtimequiz/startattempt.php" ,
            async: false,
            data: { cmid: realtimequiz.cmid,
                    quizid: realtimequiz.quizid,
                    sesskey:  realtimequiz.sesskey,
                    //forcenew: true
                  },
            success : function(data) {

                realtimequiz.attemptid = data;

                //var dbarea = document.getElementById('bedbug');
                //dbarea.innerHTML += '<br /> 1:  ID set to:' + realtimequiz.attemptid + '<br />';

                realtimequiz_join_quiz();

                      //location.reload();

                    }
        });
        //var dbarea = document.getElementById('bedbug');
        //dbarea.innerHTML += '<br /> 2: Alert ID set to:' + realtimequiz.attemptid + '<br />';

}

function realtimequiz_join_quiz() {
    //realtimequiz_create_request('requesttype=quizrunning&quizid='+realtimequiz.quizid+'');
    realtimequiz_create_request('/mod/realtimequiz/quizdata.php?requesttype=quizrunning&quizid='+realtimequiz.quizid);
}
