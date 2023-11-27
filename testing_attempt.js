$.ajax({
          type: "GET",
          url: realtimequiz.siteroot + "/mod/realtimequiz/startattempt.php" ,
          async: false,
          data: { cmid: realtimequiz.cmid,
                  quizid: realtimequiz.quizid,
                sesskey:  realtimequiz.sesskey},
          success : function(data) {

              realtimequiz.attemptid = data;

              var dbarea = document.getElementById('bedbug');
              dbarea.innerHTML += '<br /> 1: Alert ID set to:' + realtimequiz.attemptid + '<br />';

              alert("1: Alert ID set to: " + realtimequiz.attemptid);
              realtimequiz.controlquiz = true;
              realtimequiz_first_question();

                    //location.reload();

                  }
      });
      var dbarea = document.getElementById('bedbug');
      dbarea.innerHTML += '<br /> 2: Alert ID set to:' + realtimequiz.attemptid + '<br />';
      alert("2: Alert ID set to: " + realtimequiz.attemptid);
}
