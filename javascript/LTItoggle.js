
function addLTItoggle(){
    $(".mainbody").prepend('<label class="switchLTI"><input type="checkbox" id="view"><div class="sliderLTI round"></div></label>');
    $("#view").change(function() {
        if($(this).is(":checked")) {
            showSimple(false);
        } else{
            showSimple(true);
        }
    });

    //check for cookie settings
    var showSimpleVar = getCookie("showSimple");
    if(showSimpleVar === 'true' || showSimpleVar === ''){
        $("#view").click();
    } else{
        showSimple(false);
    }

    
}

function showSimple(bool){
    setCookie("showSimple", bool, 100);
    if(bool){
        $("#simple").show();
        $("#advanced").hide();
    } else{
        $("#simple").hide();
        $("#advanced").show();
    }
    
}

function setCookie(cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    var expires = "expires="+d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}
  
function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for(var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') {
        c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length);
        }
    }
    return "true";
}