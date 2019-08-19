// Specific Based Grading
//(c) Ondrej Zjevik 2019

// adds a button to select a random question from each displayed library

$(document).ready(function() {
    var addRandQuestionBTN = $('<input/>').attr({ type: 'button', name:'addRQ', value:'Select a question from each displayed library below' });
    $("#advanced #myTable").before('<div style="background-color: bisque;" class="SBGcontainer"></div>');
    $("#simple #myTable").before('<div style="background-color: bisque;"><p>Please switch to Advanced View</p></div>');

    $(".SBGcontainer").prepend("Make sure the first question is IP restriction and add one question for each Learning Goal. \
    Clear search field, display needed libraries and hit the button below.<br /> \
    Don't forget to have enough offline assignments in the default gradebook category. The order of the questions above \
    has to match the order of the goals (offline assessments).<br /> The students will see up to three questions based on\
    their pass/fail gradebook record. The questions will have the same version if opened within hour, \
    i.e., 2:00:00 - 2:59:59 is one version, 3:00:00 - 3:59:59 is another version.\
    ");
    $(".SBGcontainer").append(addRandQuestionBTN);

    addRandQuestionBTN.click(function() {
        var libraryRowIndex = [];

        var container = $("#view").is(":checked")?"advanced":"simple";
        var rows = $("#"+container+" #myTable tr.even td:nth-child(2),#"+container+" #myTable tr.odd td:nth-child(2)");
        rows.each(function( index,el ) {
            if ($(el).html().includes("<b>")) {
                //console.log( index + ": " + $(el).text() );
                libraryRowIndex.push(index);
            }
        });

        //select one question from libraries
        for (let i = 0; i < libraryRowIndex.length-1; i++) {
            var rand = randomIntFromInterval(libraryRowIndex[i]+1, libraryRowIndex[i+1]-1);
            $(rows[rand].parentElement).find("input")[0].click();
        }

        //select one question from the last library
        var rand = randomIntFromInterval(libraryRowIndex[libraryRowIndex.length-1]+1, rows.length-1);
        $(rows[rand].parentElement).find("input")[0].click();
      });
});

function randomIntFromInterval(min, max) { // min and max included 
    return Math.floor(Math.random() * (max - min + 1) + min);
  }