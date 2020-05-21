<?php
//IMathAS:  Add/modify blocks of items on course page
//(c) 2006 David Lippman

// Modified by Ondrej Zjevik 2018

/*** master php includes *******/

if (!(isset($teacherid))) { // loaded by a NON-teacher
	$overwriteBody=1;
	$body = "You need to log in as a teacher to access this page";
} elseif (!(isset($_GET['cid']))) {
	$overwriteBody=1;
	$body = "You need to access this page from the course page menu";
} else { // PERMISSIONS ARE OK, PROCEED WITH PROCESSING
	echo '<div id="simple">';

	if ($overwriteBody==1) {
		echo $body;
	} else {  //ONLY INITIAL LOAD HAS DISPLAY
	
	?>
		<style type="text/css">
		span.hidden {
			display: none;
		}
		span.show {
			display: inline;
		}
		</style>
	
		<script>
		function chgfb() {
			if (document.getElementById("deffeedback").value=="Practice" || document.getElementById("deffeedback").value=="Homework") {
				document.getElementById("showanspracspan").className = "show";
				document.getElementById("showansspan").className = "hidden";
				document.getElementById("showreattdiffver").className = "hidden";
			} else {
				document.getElementById("showanspracspan").className = "hidden";
				document.getElementById("showansspan").className = "show";
				document.getElementById("showreattdiffver").className = "show";
			}
			if (document.getElementById("deffeedback").value=="Practice") {
				document.getElementById("stdcntingb").className = "hidden";
				document.getElementById("praccntingb").className = "formright";
			} else {
				document.getElementById("stdcntingb").className = "formright";
				document.getElementById("praccntingb").className = "hidden";
			}
		}
		function chgcopyfrom() {
			if (document.getElementById('copyfrom').value==0) {
				document.getElementById('customoptions').className="show";
				document.getElementById('copyfromoptions').className="hidden";
			} else {
				document.getElementById('customoptions').className="hidden";
				document.getElementById('copyfromoptions').className="show";
			}
		}
		function apwshowhide(s) {
			var el = document.getElementById("assmpassword");
			if (el.type == "password") {
				el.type = "text";
				s.innerHTML = "Hide";
			} else {
				el.type = "password";
				s.innerHTML = "Show";
			}
		}
		var newextrefcnt = 0;
		function addextref(el) {
			var html = '<span class="aextref">';
			html += '<label for=newextreflabel'+newextrefcnt+'>Label:</label>';
			html += '<input id=newextreflabel'+newextrefcnt+' name=newextreflabel'+newextrefcnt+' size=10 /> ';
			html += '<label for=newextreflink'+newextrefcnt+'>Link:</label>';
			html += '<input id=newextreflink'+newextrefcnt+' name=newextreflink'+newextrefcnt+' size=30 />';		
			html += '<button type=button onclick="removeextref(this)">Remove</button><br/></span>';
			newextrefcnt++;
			$(el).before(html);
		}
		function removeextref(el) {
			$(el).closest(".aextref").remove();	
		}
		$(function() {
			$("input[name=dolpcutoff]").on("click", function() {
				var chk = $(this).is(":checked");
				$("#lpcutoffwrap").toggle(chk);
				$(this).attr("aria-expanded", chk);
			});
			$("#reqscoreshowtype").attr("aria-controls", "reqscorewrap")
				.attr("aria-expanded", $("#reqscoreshowtype").val()>-1)
				.on("change", function() {
					var rqshow = ($(this).val()>-1);
					$("#reqscorewrap").toggle(rqshow);
					$(this).attr("aria-expanded", rqshow);
			});
			$(".displaymethod").attr("aria-controls", "SBGgoalswrap")
				.attr("aria-expanded", $(".displaymethod").val()=="SBG")
				.on("change", function() {
					var rqshow = ($(this).val()=="SBG");
					$(".SBGgoalswrap").toggle(rqshow);
					$(this).attr("aria-expanded", rqshow);
			});
			$("#locationtype").attr("aria-controls", "locationwrap")
				.attr("aria-expanded", $("#locationtype").val()>0)
				.on("change", function() {
					var loctype = parseInt($(this).val());
					var locshow = (loctype>0);
					$("#locationwrap").toggle(locshow);
					$(this).attr("aria-expanded", locshow);
					if (locshow) {
						showmap(loctype);
						//console.log("here");
					}
	
					switch (loctype) {
						//Custom location
						case 3:
							$("#latlonwrap").removeClass("grey");
							$("#myRange").prop( "disabled", false );
							$("#latitude").prop( "disabled", false );
							$("#longitude").prop( "disabled", false );
							break;
						//Location is set to MMC or BBC
						default:
							$("#latlonwrap").addClass("grey");
							$("#myRange").prop( "disabled", true );
							$("#latitude").prop( "disabled", true );
							$("#longitude").prop( "disabled", true );
					}
			});
			$("#myRange").on("input",function() {
				circle.setRadius(this.value);
			});
			$(".myparticipation").on("input",function() {
				$(".partPart").html(this.value);
				$(".partCorr").html(100-this.value);
			});
		})
		var map, tileLayer, marker, circle, latitude, longitude;
		function showmap(campus, lat, lon, rad){
			var markerDraggable = false;
			var radius;
			switch (campus){
				//MMC
				case 1:
					latitude = 25.754;
					longitude = -80.376;
					radius = 1000;
					
					$("#latlonwrap").addClass("grey");
					$("#myRange").prop( "disabled", true );
					$("#latitude").prop( "disabled", true );
					$("#longitude").prop( "disabled", true );
					break;
				//BBC
				case 2:
					latitude = 25.9110057;
					longitude = -80.139516;
					radius = 500;
	
					$("#latlonwrap").addClass("grey");
					$("#myRange").prop( "disabled", true );
					$("#latitude").prop( "disabled", true );
					$("#longitude").prop( "disabled", true );
					break;
				//Custom
				case 3:
					latitude = (lat != undefined)?lat:25.754;
					longitude = (lon != undefined)?lon:-80.376;
					radius = (rad != undefined)?rad:1000;
					markerDraggable = true;
					$("#latlonwrap").removeClass("grey");
					$("#myRange").prop( "disabled", false );
					$("#latitude").prop( "disabled", false );
					$("#longitude").prop( "disabled", false );
			}
			//Need to create the map
			if(map == undefined){
				tileLayer = new L.TileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',{
				attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, &copy; <a href="http://cartodb.com/attributions">CartoDB</a>'
				});
	
				map = new L.Map('mapdiv', {
				'center': [latitude, longitude],
				'zoom': campus=="1"?14:15,
				'layers': [tileLayer]
				});
	
				marker = L.marker([latitude, longitude],{
				draggable: markerDraggable
				}).addTo(map);
	
				circle = L.circle([latitude, longitude], {
					color: '#081E3F',
					fillColor: '#F8C93E',
					fillOpacity: 0.5,
					radius: radius
				}).addTo(map);
	
				marker.on('dragend', function (e) {
					document.getElementById('latitude').value = marker.getLatLng().lat;
					document.getElementById('longitude').value = marker.getLatLng().lng;
					circle.setLatLng(marker.getLatLng());
				});
			} else{
				//Map is already created
				if(markerDraggable){
					marker.dragging.enable()
				} else{
					marker.dragging.disable()
				}
				if(campus == "1" || campus == "2"){
					zoom = campus=="1"?14:15;
					map.flyTo([latitude, longitude], zoom);
					circle.setLatLng([latitude, longitude]);
					circle.setRadius(radius);
					marker.setLatLng([latitude, longitude]);
					circle.setLatLng(marker.getLatLng());
				}
			}
			$("#myRange").val(radius);
			document.getElementById('latitude').value = marker.getLatLng().lat;
			document.getElementById('longitude').value = marker.getLatLng().lng;
		}
		</script>
	
		<div class=breadcrumb><?php echo $curBreadcrumb  ?></div>
		<?php echo $formTitle ?>
		<?php
		if (isset($_GET['id'])) {
			printf('<div class="cp"><a href="addquestions.php?aid=%d&amp;cid=%s" onclick="return confirm(\''
				. _('This will discard any changes you have made on this page').'\');">'
				. _('Add/Remove Questions').'</a></div>', Sanitize::onlyInt($_GET['id']), $cid);
		}
		?>
		<?php echo $page_isTakenMsg ?>
	
		<form method=post action="<?php echo $page_formActionTag ?>">
			<span class=form>Assessment Name:</span>
			<span class=formright><input type=text size=30 name=name value="<?php echo Sanitize::encodeStringForDisplay($line['name']); ?>" required></span><BR class=form>
	
	
	<?php
		if ($dates_by_lti==0) {
	?>
			<textarea style='display:none' cols=50 rows=15 id=summary name=summary style="width: 100%"><?php echo Sanitize::encodeStringForDisplay($line['summary'], true); ?></textarea>
			<textarea style='display:none' cols=50 rows=20 id=intro name=intro style="width: 100%"><?php echo Sanitize::encodeStringForDisplay($line['intro'], true); ?></textarea>
			
				
				<input type="hidden" name="avail" value="1" <?php writeHtmlChecked($line['avail'],1);?> onclick="$('#datediv').slideDown(100);"/>
			
	
			<div id="datediv" style="display:<?php echo ($line['avail']==1)?"block":"none"; ?>">
	
			<span class=form>Available After:</span>
			<span class=formright>
				<input type=radio name="sdatetype" value="0" <?php writeHtmlChecked($startdate,"0",0); ?>/>
				Always until end date<br/>
				<input type=radio name="sdatetype" value="sdate" <?php writeHtmlChecked($startdate,"0",1); ?>/>
				<input type=text size=10 name="sdate" value="<?php echo $sdate;?>">
				<a href="#" onClick="displayDatePicker('sdate', this); return false">
				<img src="../img/cal.gif" alt="Calendar"/></A>
				at <input type=text size=8 name=stime value="<?php echo $stime;?>">
			</span><BR class=form>
	
			<span class=form>Available Until:</span>
			<span class=formright>
				<input type=radio name="edatetype" value="2000000000" <?php writeHtmlChecked($enddate,"2000000000",0); ?>/>
				 Always after start date
				 <?php if ($courseenddate<2000000000) {
					  echo 'until the course end date, '.tzdate("n/j/Y", $courseenddate);
				 }?><br/>
				<input type=radio name="edatetype" value="edate"  <?php writeHtmlChecked($enddate,"2000000000",1); ?>/>
				<input type=text size=10 name="edate" value="<?php echo $edate;?>">
				<a href="#" onClick="displayDatePicker('edate', this, 'sdate', 'start date'); return false">
				<img src="../img/cal.gif" alt="Calendar"/></A>
				at <input type=text size=8 name=etime value="<?php echo $etime;?>">
			</span><BR class=form>
	<?php
		} else { //dates_by_lti is on
	?>
			<span class=form>Availability:</span>
			<span class=formright>
				<input type=radio name="avail" value="0" <?php writeHtmlChecked($line['avail'],0);?> onclick="$('#datediv').slideUp(100);"/>Prevent access<br/>
				<input type=radio name="avail" value="1" <?php writeHtmlChecked($line['avail'],1);?> onclick="$('#datediv').slideDown(100);"/>Allow access<br/>
			</span><br class="form"/>
			
			<div id="datediv" style="display:<?php echo ($line['avail']==1)?"block":"none"; ?>">
			
			<span class=form>Due date</span>
			<span class=formright>
				The course setting is enabled for dates to be set via LTI.<br/>
				<?php
				if ($line['date_by_lti']==1) {
					echo 'Waiting for the LMS to send a date';
				} else {
					if ($enddate==2000000000) {
						echo 'Default due date set by LMS: No due date (individual student due dates may vary)';
					} else {
						echo 'Default due date set by LMS: '.$edate.' '.$etime.' (individual student due dates may vary).';
					}
				}
				?>
			</span><br class=form />
	
	<?php
		}
	?>
			<BR class=form>
			</div>
			<input type=hidden name="doreview" value="0" <?php if ($line['reviewdate']>0) {echo 'checked';} ?>>
			<span class=form></span>
			<span class=formright>
				<input type=submit value="<?php echo Sanitize::encodeStringForDisplay($savetitle); ?>"> now or continue below for Assessment Options
			</span><br class=form>
	
			<fieldset><legend>Assessment Options</legend>
	<?php
		if (count($page_copyFromSelect['val'])>0) {
	?>
			<span class=form>Copy Options from:</span>
			<span class=formright>
	
	<?php
			writeHtmlSelect ("copyfrom",$page_copyFromSelect['val'],$page_copyFromSelect['label'],0,"None - use settings below",0," onChange=\"chgcopyfrom()\"");
	?>
			</span><br class=form>
	<?php
		}
	?>
	
			<div id="copyfromoptions" class="hidden">
			<span class=form>Also copy:</span>
			<span class=formright>
				<input type=checkbox name="copysummary" value=1 /> Summary<br/>
				<input type=checkbox name="copyinstr" value=1 /> Instructions<br/>
				<input type=checkbox name="copydates" value=1 /> Dates <br/>
				<input type=checkbox name="copyendmsg" value=1 /> End of Assessment Messages
			</span><br class=form />
			<span class=form>Remove any existing per-question settings?</span>
			<span class=formright>
				<input type=checkbox name="removeperq" />
			</span><br class=form />
	
			</div>
			<div id="customoptions" class="show">
				<hr/>
				<span class=form>Display method: </span>
				<span class=formright>
					<select class="displaymethod" name="displaymethod">
						<option value="AllAtOnce" <?php writeHtmlSelected($line['displaymethod'],"AllAtOnce",0) ?>>Full test at once</option>
						<option value="OneByOne" <?php writeHtmlSelected($line['displaymethod'],"OneByOne",0) ?>>One question at a time</option>
						<option value="Seq" <?php writeHtmlSelected($line['displaymethod'],"Seq",0) ?>>Full test, submit one at time</option>
						<option value="SkipAround" <?php writeHtmlSelected($line['displaymethod'],"SkipAround",0) ?>>Skip Around</option>
						<option value="Embed" <?php writeHtmlSelected($line['displaymethod'],"Embed",0) ?>>Embedded</option>
						<option value="VideoCue" <?php writeHtmlSelected($line['displaymethod'],"VideoCue",0) ?>>Video Cued</option>
						<?php if (isset($CFG['GEN']['livepollserver'])) {
							echo '<option value="LivePoll" ';
							writeHtmlSelected($line['displaymethod'],"LivePoll",0);
							echo '>Live Poll</option>';
						}?>
						<option value="JustInTime" <?php writeHtmlSelected($line['displaymethod'],"JustInTime",0) ?>>Just In time</option>
						<?php if (isset($CFG['LTI']['gradebookcategory'])) {
							echo '<option value="CanvasGradebook" ';
							writeHtmlSelected($line['displaymethod'],"CanvasGradebook",0);
							echo '>Canvas Gradebook Catagory</option>';
						}?>
						<option value="SBG" <?php writeHtmlSelected($line['displaymethod'],"SBG",0) ?>>Specific Based Grading</option>
					</select>
					<span class="SBGgoalswrap" <?php if ($line['displaymethod']!="SBG") {
						echo 'style="display:none;"';
					} ?>>max # of goals: 
					<input type="text" size="2" name="SBGgoals" value="<?php echo Sanitize::encodeStringForDisplay($SBGgoals); ?>">.
					<input type="text" size="2" name="SBGtime" value="<?php echo Sanitize::encodeStringForDisplay($SBGtime); ?>">min/goal
				</span>
				</span><BR class=form>
	
				<span class=form>Feedback method: </span>
				<span class=formright>
					<select id="deffeedback" name="deffeedback" onChange="chgfb()" >
						<option value="NoScores" <?php if ($testtype=="NoScores") {echo "SELECTED";} ?>>No scores shown (last attempt is scored)</option>
						<option value="EndScore" <?php if ($testtype=="EndScore") {echo "SELECTED";} ?>>Just show final score (total points &amp; average) - only whole test can be reattempted</option>
						<option value="EachAtEnd" <?php if ($testtype=="EachAtEnd") {echo "SELECTED";} ?>>Show score on each question at the end of the test </option>
						<option value="EndReview" <?php if ($testtype=="EndReview") {echo "SELECTED";} ?>>Reshow question with score at the end of the test </option>
						<option value="EndReviewWholeTest" <?php if ($testtype=="EndReviewWholeTest") {echo "SELECTED";} ?>>Reshow question with score at the end of the test  - only whole test can be reattempted </option>
	
						<option value="AsGo" <?php if ($testtype=="AsGo") {echo "SELECTED";} ?>>Show score on each question as it's submitted (does not apply to Full test at once display)</option>
						<option value="Practice" <?php if ($testtype=="Practice") {echo "SELECTED";} ?>>Practice test: Show score on each question as it's submitted &amp; can restart test; scores not saved</option>
						<option value="Homework" <?php if ($testtype=="Homework") {echo "SELECTED";} ?>>Homework: Show score on each question as it's submitted &amp; allow similar question to replace missed question</option>
					</select>
				</span><BR class=form>
				
				<span class=form>Default attempts per problem (0 for unlimited): </span>
				<span class=formright>
					<input type=text size=4 name=defattempts value="<?php echo Sanitize::encodeStringForDisplay($line['defattempts']); ?>" >
					<span id="showreattdiffver" class="<?php if ($testtype!="Practice" && $testtype!="Homework") {echo "show";} else {echo "hidden";} ?>">
					 <?php
					 if ($line['shuffle']&8 == 8){
						 echo ' <input type="hidden" name="reattemptsdiffver" />';
					 }
					 ?>
					 </span>
				 </span><BR class=form>
	
				<span class=form>Default penalty:</span>
				<span class=formright>
					<input type=text size=4 name=defpenalty value="<?php echo Sanitize::encodeStringForDisplay($line['defpenalty']); ?>" <?php if ($taken) {echo 'disabled=disabled';}?>>%
					   <select name="skippenalty" <?php if ($taken) {echo 'disabled=disabled';}?>>
						<option value="0" <?php if ($skippenalty==0) {echo "selected=1";} ?>>per missed attempt</option>
						<option value="1" <?php if ($skippenalty==1) {echo "selected=1";} ?>>per missed attempt, after 1</option>
						<option value="2" <?php if ($skippenalty==2) {echo "selected=1";} ?>>per missed attempt, after 2</option>
						<option value="3" <?php if ($skippenalty==3) {echo "selected=1";} ?>>per missed attempt, after 3</option>
						<option value="4" <?php if ($skippenalty==4) {echo "selected=1";} ?>>per missed attempt, after 4</option>
						<option value="5" <?php if ($skippenalty==5) {echo "selected=1";} ?>>per missed attempt, after 5</option>
						<option value="6" <?php if ($skippenalty==6) {echo "selected=1";} ?>>per missed attempt, after 6</option>
						<option value="10" <?php if ($skippenalty==10) {echo "selected=1";} ?>>on last possible attempt only</option>
					</select>
				</span><BR class=form>
	
	
				<span class=form>Show Answers: </span>
				<span class=formright>
					<span id="showanspracspan" class="<?php if ($testtype=="Practice" || $testtype=="Homework") {echo "show";} else {echo "hidden";} ?>">
					<select name="showansprac">
						<option value="V" <?php if ($showans=="V") {echo "SELECTED";} ?>>Never, but allow students to review their own answers</option>
						<option value="N" <?php if ($showans=="N") {echo "SELECTED";} ?>>Never, and don't allow students to review their own answers</option>
						<option value="F" <?php if ($showans=="F") {echo "SELECTED";} ?>>After last attempt (Skip Around only)</option>
						<option value="J" <?php if ($showans=="J") {echo "SELECTED";} ?>>After last attempt or Jump to Ans button (Skip Around only)</option>
						<option value="0" <?php if ($showans=="0") {echo "SELECTED";} ?>>Always</option>
						<option value="1" <?php if ($showans=="1") {echo "SELECTED";} ?>>After 1 attempt</option>
						<option value="2" <?php if ($showans=="2") {echo "SELECTED";} ?>>After 2 attempts</option>
						<option value="3" <?php if ($showans=="3") {echo "SELECTED";} ?>>After 3 attempts</option>
						<option value="4" <?php if ($showans=="4") {echo "SELECTED";} ?>>After 4 attempts</option>
						<option value="5" <?php if ($showans=="5") {echo "SELECTED";} ?>>After 5 attempts</option>
					</select>
					</span>
					<span id="showansspan" class="<?php if ($testtype!="Practice" && $testtype!="Homework") {echo "show";} else {echo "hidden";} ?>">
					<select name="showans">
						<option value="V" <?php if ($showans=="V") {echo "SELECTED";} ?>>Never, but allow students to review their own answers</option>
						<option value="N" <?php if ($showans=="N") {echo "SELECTED";} ?>>Never, and don't allow students to review their own answers</option>
						<option value="I" <?php if ($showans=="I") {echo "SELECTED";} ?>>Immediately (in gradebook) - don't use if allowing multiple attempts per problem</option>
						<option value="F" <?php if ($showans=="F") {echo "SELECTED";} ?>>After last attempt (Skip Around only)</option>
						<option value="R" <?php if ($showans=="R") {echo "SELECTED";} ?>>After last attempt on a version</option>
						<option value="A" <?php if ($showans=="A") {echo "SELECTED";} ?>>After due date (in gradebook)</option>
						<option value="1" <?php if ($showans=="1") {echo "SELECTED";} ?>>After 1 attempt</option>
						<option value="2" <?php if ($showans=="2") {echo "SELECTED";} ?>>After 2 attempts</option>
						<option value="3" <?php if ($showans=="3") {echo "SELECTED";} ?>>After 3 attempts</option>
						<option value="4" <?php if ($showans=="4") {echo "SELECTED";} ?>>After 4 attempts</option>
						<option value="5" <?php if ($showans=="5") {echo "SELECTED";} ?>>After 5 attempts</option>
					</select>
					</span>
				</span><br class=form>
				
				<span class=form>Gradebook Category:</span>
				<span class=formright>
	
	<?php
		writeHtmlSelect("gbcat",$page_gbcatSelect['val'],$page_gbcatSelect['label'],$gbcat,"Default",0);
	?>
				</span><br class=form>
		
			 <div><a href="#" onclick="groupToggleAll(1);return false;">Expand All</a>
			<a href="#" onclick="groupToggleAll(0);return false;">Collapse All</a></div>
			 
			<input name="caltagact" type="hidden" size=8 value="<?php echo Sanitize::encodeStringForDisplay($line['caltag']); ?>"/>
			<input name="showqcat" type="hidden" value="0" <?php writeHtmlChecked($showqcat,"0"); ?>>
			<input name="showqcat" type="hidden" value="1" <?php writeHtmlChecked($showqcat,"1"); ?>>
			<input name="showqcat" type="hidden" value="2" <?php writeHtmlChecked($showqcat,"2"); ?>>
			<input type="hidden" value="0" name="noprint" <?php writeHtmlChecked($line['noprint'],0); ?>/>
			<input type="hidden" name="sameseed" <?php writeHtmlChecked($line['shuffle']&2,2); ?>>
			<input type="hidden" name="samever" <?php writeHtmlChecked($line['shuffle']&4,4); ?>>
			<input type="hidden" name="istutorial" <?php writeHtmlChecked($line['istutorial'],1); ?>>
			<input type="hidden" name="latepassafterdue" <?php writeHtmlChecked($line['allowlate']>10,true); ?>>
			<input type="hidden" size=4 name=timelimit value="<?php echo Sanitize::onlyFloat(abs($timelimit));?>">
			<input type="hidden" name="timelimitkickout" <?php if ($timelimit<0) echo 'checked="checked"';?> />
			
			<input type="hidden" name="showhints" <?php writeHtmlChecked($line['showhints'],1); ?>>
			<input type="hidden" name="msgtoinstr" <?php writeHtmlChecked($line['msgtoinstr'],1); ?>/>
			<input type="hidden" name="doposttoforum" <?php writeHtmlChecked($line['posttoforum'],0,true); ?>/>
			

			 <div class="block grouptoggle" onclick="setTimeout(function() {map.invalidateSize();}, 500);">
			   <img class="mida" src="../img/expand.gif" />
			   Access Control
			 </div>
			 <div class="">
			
				<br class=form>
	
				<span class=form>Restrict Location? (Live Poll only): </span>
				<span class=formright>
	<?php
		writeHtmlSelect("locationtype", array(0,1,2,3), array(_('No'),_('To MMC'), _('To BBC'), _('Custom Location')), $locationtype);
				echo '<span id="locationwrap"';
				if ($locationtype==0) {
					echo 'style="display:none;"';
				}
				echo '>';
	?> <span id="locinstructions"
	<?php
				if ($locationtype!=3) {
					echo 'style="display:none;"';
				}
	?>
	><br>Drag the marker on the map to change the required location.<br></span>
				<span id="latlonwrap">Latitude: <input type=text size=12 name="loclatitude" id="latitude" value="<?php echo $line['loclat'];?>">, 
				Longitude: <input type=text size=12 name="loclongitude" id="longitude" value="<?php echo $line['loclng'];?>">
	
				Radius: <input style="width: 80%;" type="range" min="100" max="1000" value="<?php echo $line['locradius'];?>" step="25" id="myRange" name="locradius">
	
				<div id="mapdiv" style="height: 400px"></div>
	<script>
	<?php
				if ($locationtype > 0 && $locationtype < 3) {
					echo 'showmap('.$locationtype.');';
				} else if ($locationtype != 0){
					echo 'showmap('.$locationtype.','.$line["loclat"].','.$line["loclng"].','.$line["locradius"].');';
				}
	?>
	
	</script>
	
				</span></span></span><br class=form>
			 </div>
			 
			 
	
			 <div class="block grouptoggle">
			   <img class="mida" src="../img/expand.gif" />
			   Grading and Feedback
			 </div>
			 <div class="blockitems hidden">
	
				<span class=form>Count: </span>
				<span <?php if ($testtype=="Practice") {echo "class=hidden";} else {echo "class=formright";} ?> id="stdcntingb">
					<select name="cntingb">
					<option value="1" <?php writeHtmlSelected($cntingb,1,0); ?>> Count in Gradebook</option>
					<option value="0" <?php writeHtmlSelected($cntingb,0,0); ?>> Don't count in grade total and hide from students</option>
					<option value="3" <?php writeHtmlSelected($cntingb,3,0); ?>> Don't count in grade total</option>
					<option value="2" <?php writeHtmlSelected($cntingb,2,0); ?>> Count as Extra Credit</option>
					</select>
				</span>
				<span <?php if ($testtype!="Practice") {echo "class=hidden";} else {echo "class=formright";} ?> id="praccntingb">
					<input type=radio name="pcntingb" value="0" <?php writeHtmlChecked($pcntingb,0,0); ?> /> Don't count in grade total and hide from students<br/>
					<input type=radio name="pcntingb" value="3" <?php writeHtmlChecked($pcntingb,3,0); ?> /> Don't count in grade total<br/>
				</span><br class=form />
				
				<span class=form>Minimum score to receive credit: </span>
				<span class=formright>
					<input type=text size=4 name=minscore value="<?php echo Sanitize::encodeStringForDisplay($line['minscore']); ?>">
					<input type="radio" name="minscoretype" value="0" <?php writeHtmlChecked($minscoretype,0);?>> Points
					<input type="radio" name="minscoretype" value="1" <?php writeHtmlChecked($minscoretype,1);?>> Percent
				</span><BR class=form>
	
	
	
				<span class="form">Participation weight:(Canvas Gradebook Category only)</span>
				<span class="formright">
				Final score = <span class="partCorr"><?php echo 100-$line['gbcatweight'];?></span>% Correctness + <span class="partPart"><?php echo $line['gbcatweight']==0?"0":$line['gbcatweight'];?></span>% Participation
				<input style="width: 80%;" type="range" min="0" max="100" value="<?php echo $line['gbcatweight']==0?"0":$line['gbcatweight'];?>" step="5" class="myparticipation" name="gbcatweight">
				
				
				</span><br class="form" />
				
			 </div>
			 
			</div>
		</fieldset>
		<div class=submit><input type=submit value="<?php echo $savetitle;?>"></div>
		</form>
	<?php
	}
		echo '</div>';
}
?>
