/**
 * Code for a teacher running a quiz
 *
 * @author: Davosmith
 * @package realtimequiz
 **/

realtimequiz.clickednext = 0; // The question number of the last time the teacher clicked 'next'

function realtimequiz_first_question() {

    realtimequiz.controlquiz = true;

    var sessionname = document.getElementById('sessionname').value;
    if (sessionname.length > 0) {
        sessionname = '&sessionname=' + encodeURIComponent(sessionname);
    }
    realtimequiz_create_request('/mod/realtimequiz/quizdata.php?requesttype=startquiz&quizid='+realtimequiz.quizid+"&cmid="+realtimequiz.cmid+'&userid='+realtimequiz.userid+'&attempt='+realtimequiz.attemptid+sessionname);

}

function realtimequiz_next_question() {
    realtimequiz_update_next_button(false);

    realtimequiz_create_request('/mod/realtimequiz/quizdata.php?requesttype=nextquestion&quizid='+realtimequiz.quizid+'&userid='+realtimequiz.userid+'&attempt='+realtimequiz.attemptid+'&currentquestion='+realtimequiz.questionnumber );

    realtimequiz.clickednext = realtimequiz.questionnumber;
    //Userid needed to authenticate request - I think that the DB should be the ultimate authority on which question is being displayed
}

function realtimequiz_update_next_button(enabled) {

    if (!realtimequiz.controlquiz) {
        return;
    }
    if (enabled) {
        if (realtimequiz.clickednext == realtimequiz.questionnumber) { // Teacher already clicked 'next' for this question, so resend that request
            realtimequiz_next_question();
        } else {
            document.getElementById('questioncontrols').innerHTML = '<input type="button" onclick="realtimequiz_next_question()" value="'+realtimequiz.text['next']+'" />';
        }

    } else {
        document.getElementById('questioncontrols').innerHTML = '<input type="button" onclick="realtimequiz_next_question()" value="'+realtimequiz.text['next']+'" disabled="disabled" />';
    }
}

// get the attemptid - ajax used because of the sync issues
async function realtimequiz_start_quiz() {
  $.ajax({
            type: "GET",
            url: realtimequiz.siteroot + "/mod/realtimequiz/startattempt.php" ,
            async: false,
            data: { cmid: realtimequiz.cmid,
                    quizid: realtimequiz.quizid,
                    sesskey:  realtimequiz.sesskey,
                    forcenew: true},
            success : function(data) {

                realtimequiz.attemptid = data;

                //var dbarea = document.getElementById('bedbug');
                //dbarea.innerHTML += '<br /> 1: Alert ID set to:' + realtimequiz.attemptid + '<br />';

                realtimequiz.controlquiz = true;

                realtimequiz_first_question();

              }
        });
        //var dbarea = document.getElementById('bedbug');
        //dbarea.innerHTML += '<br /> 2: Alert ID set to:' + realtimequiz.attemptid + '<br />';

}


function realtimequiz_start_new_quiz() {
    var confirm = window.confirm(realtimequiz.text['startnewquizconfirm']);
    if (confirm == true) {
        realtimequiz_start_quiz();
    }
}

async function realtimequiz_reconnect_quiz() {
    realtimequiz.controlquiz = true;
    
    $.ajax({
              type: "GET",
              url: realtimequiz.siteroot + "/mod/realtimequiz/startattempt.php" ,
              async: false,
              data: { cmid: realtimequiz.cmid,
                      quizid: realtimequiz.quizid,
                      sesskey:  realtimequiz.sesskey,
                      forcenew: false},
              success : function(data) {

                  realtimequiz.attemptid = data;

                  //var dbarea = document.getElementById('bedbug');
                  //dbarea.innerHTML += '<br /> 1: Alert ID set to:' + realtimequiz.attemptid + '<br />';

                  realtimequiz.controlquiz = true;

                  realtimequiz_create_request('/mod/realtimequiz/quizdata.php?requesttype=teacherrejoin&quizid='+realtimequiz.quizid+'&userid='+realtimequiz.userid+'&attempt='+realtimequiz.attemptid+'&currentquestion='+realtimequiz.questionnumber+'&showall=false' );

              }
          });

}

function realtimequiz_init_teacher_view() {
    realtimequiz.controlquiz = false;     // Set to true when controlling the quiz
    var msg = "<div style='text-align: center;'>";
    if (realtimequiz.alreadyrunning) {
        msg += "<input type='button' onclick='realtimequiz_reconnect_quiz();' value='" + realtimequiz.text['reconnectquiz'] + "' />";
        msg += "<p>"+realtimequiz.text['reconnectinstruct']+"</p>";
        msg += "<input type='button' onclick='realtimequiz_start_new_quiz();' value='" + realtimequiz.text['startnewquiz'] + "' /> <input type='text' name='sessionname' id='sessionname' maxlength='255' value='' />";
        msg += "<p>" + realtimequiz.text['teacherstartnewinstruct'] + "</p>";
    } else {
        msg += "<input type='button' onclick='realtimequiz_start_quiz();' value='" + realtimequiz.text['startquiz'] + "' /> <input type='text' name='sessionname' id='sessionname' maxlength='255' value='' />";
        msg += "<p>" + realtimequiz.text['teacherstartinstruct'] + "</p>";
    }
    msg += "<input type='button' onclick='realtimequiz_join_quiz();' value='"+realtimequiz.text['joinquizasstudent']+"' />";
    msg += "<p id='status'>"+realtimequiz.text['teacherjoinquizinstruct']+"</p></div>";
    document.getElementById('questionarea').innerHTML = msg;
}
