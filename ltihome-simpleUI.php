<?php
//IMathAS: LTI instructor home page
//(c) 2011 David Lippman

//Edited in 2019 Ondrej Zjevik

require_once("init.php");
if (!isset($sessiondata['ltirole']) || $sessiondata['ltirole']!='instructor') {
	echo "Not authorized to view this page";
	exit;
}

#TODO: Simple UI
echo '<div id="simple">';
if (!$hascourse || isset($_GET['chgcourselink'])) {
	echo '<script type="text/javscript">
	function updateCourseSelector(el) {
		if ($(el).find(":selected").data("termsurl")) {
			$("#termsbox").show();
			$("#termsurl").attr("href",$(el).find(":selected").data("termsurl"));
		}
		else {
			$("#termsbox").hide();
		}
	}
	</script>';
	echo '<h2>Link courses</h2>';
	echo '<form method="post" action="ltihome.php">';
	echo "<p>This course on your LMS has not yet been linked to a course on $installname. ";
	echo 'Select a course to link with.  If it is a template course, a copy will be created for you:<br/> <select name="createcourse" onchange="updateCourseSelector(this)"> ';
	$stm = $DBH->prepare("SELECT ic.id,ic.name FROM imas_courses AS ic,imas_teachers WHERE imas_teachers.courseid=ic.id AND imas_teachers.userid=:userid AND ic.available<4 ORDER BY ic.name");
	$stm->execute(array(':userid'=>$userid));
	if ($stm->rowCount()>0) {
		echo '<optgroup label="Your Courses">';
		while ($row = $stm->fetch(PDO::FETCH_NUM)) {
			printf('<option value="%d">%s</option>' ,Sanitize::onlyInt($row[0]), Sanitize::encodeStringForDisplay($row[1]));
		}
		echo '</optgroup>';
	}
	$stm = $DBH->query("SELECT id,name,copyrights,termsurl FROM imas_courses WHERE (istemplate&1)=1 AND copyrights=2 AND available<4 ORDER BY name");
	if ($stm->rowCount()>0) {
		echo '<optgroup label="Template Courses">';
		while ($row = $stm->fetch(PDO::FETCH_NUM)) {
			echo '<option value="'.Sanitize::encodeStringForDisplay($row[0]).'"';
			if ($row[3]!='') {
				echo ' data-termsurl="'.Sanitize::encodeStringForDisplay($row[3]).'"';
			}
			echo '>'.Sanitize::encodeStringForDisplay($row[1]).'</option>';
		}
		echo '</optgroup>';
	}
	
	$query = "SELECT ic.id,ic.name,ic.copyrights,ic.termsurl FROM imas_courses AS ic JOIN imas_users AS iu ON ic.ownerid=iu.id WHERE ";
	$query .= "iu.groupid=:groupid AND (ic.istemplate&2)=2 AND ic.copyrights>0 AND ic.available<4 ORDER BY ic.name";
	$stm = $DBH->prepare($query);
	$stm->execute(array(':groupid'=>$groupid));
	if ($stm->rowCount()>0) {
		echo '<optgroup label="Group Template Courses">';
		while ($row = $stm->fetch(PDO::FETCH_NUM)) {
			echo '<option value="'.Sanitize::onlyInt($row[0]).'"';
			if ($row[3]!='') {
				echo ' data-termsurl="'.Sanitize::encodeStringForDisplay($row[3]).'"';
			}
			echo '>'.Sanitize::encodeStringForDisplay($row[1]).'</option>';
		}
		echo '</optgroup>';
	}
	
	echo '</select>';
	echo '<p id="termsbox" style="display:none;">This course has special <a id="termsurl">Terms of Use</a>.  By copying this course, you agree to these terms.</p>';
	echo '<input type="Submit" value="Link Course"/>';
	echo "<p>If you want to create a new course, log directly into $installname to create new courses</p>";
	echo '</form>';
} else if (!$hasplacement || isset($_GET['chgplacement'])) {
	if (isset($sessiondata['lti_selection_type']) && $sessiondata['lti_selection_type']=='assn') {
		echo '<h2>Link Assignment</h2>';
	} else {
		echo '<h2>Link Resource</h2>';
	}
	echo '<form method="post" id="LTIHomeForm"  action="ltihome.php">';
	echo "<p>This placement on your LMS has not yet been linked to content on $installname. ";
	if (isset($sessiondata['lti_selection_type']) && $sessiondata['lti_selection_type']=='assn') {
		echo 'Select the assessment you\'d like to use: ';
	} else if (isset($sessiondata['lti_selection_type']) && $sessiondata['lti_selection_type']=='link') {
		echo 'You can either do a full course placement, in which case all content of the course is available from this one placement, or ';
		echo 'you can place an individual assessment. In both cases, grades will not be returned if you set up the link in this way. ';
		echo 'For grade return, you need to create a new assignment link instead.</p>';
		echo '<p>Select the placement you\'d like to make: ';
	} else {
		echo 'You can either place an individual assessment (and grades will be returned to Canvas) in which students only see the selected assessment (recommended), or ';
		echo 'or do a full course placement, in which case all content of the course is available to students (but no grades are returned to Canvas). <br> Select the assessment you\'d like to use: ';
	}

	echo '<br/> <div class="container"><div class="small-12 medium-4 columns"><div class="fiu-button-blue">';
	echo '<select name="setplacement" style="width: 100%;height: 100%;background-color: #081E3f;border: #081E3f;color: #fff;"> ';
	
	if (isset($sessiondata['lti_selection_type']) && $sessiondata['lti_selection_type']=='link') {
		echo '<option value="course">Whole Course Placement</option>';
	}
	$stm = $DBH->prepare("SELECT id,name FROM imas_assessments WHERE courseid=:courseid ORDER BY name");
	$stm->execute(array(':courseid'=>$cid));
	if ($stm->rowCount()>0) {
		echo '<optgroup label="Assessments">';
		while ($row = $stm->fetch(PDO::FETCH_NUM)) {
			printf('<option value="%d">%s</option>', Sanitize::onlyInt($row[0]), Sanitize::encodeStringForDisplay($row[1]));
		}
		echo '</optgroup>';
	}
	if (!isset($sessiondata['lti_selection_type']) || $sessiondata['lti_selection_type']=='all') {
		echo '<optgroup label="Course">';
		echo '<option value="course">Whole Course Placement</option>';
		echo '</optgroup>';
	}
	echo '</select></div></div>';
	echo '<div class="small-12 medium-4 columns"><a onClick="javascript:document.getElementById(\'LTIHomeForm\').submit();" class="fiu-button-yellow">Make Placement</a></div>';
	echo "<div class='small-12 medium-4 columns'><a class='fiu-button-blue-outline' href='".$imasroot."/course/addassessment.php?cid=".$cid."'>New assessment</a></div>";
	echo '</div><br class="form">';
	echo "<p>If your LMS course is linked with the wrong course on $installname, ";
	echo '<div class="small-12 medium-4 columns"><a class="fiu-button-gray" href="ltihome.php?chgcourselink=true" onclick="return confirm(\'Are you SURE you want to do this? This may break existing placements.\');">Change course link</a></div></p>';
	echo '</form>';
} else if ($placementtype=='course') {
	echo '<h2>LTI Placement of whole course</h2>';
	echo "<p><a href=\"course/course.php?cid=" . Sanitize::courseId($cid) . "\">Enter course</a></p>";
	echo '<p><a href="ltihome.php?chgplacement=true">Change assessment</a></p>';
} else if ($placementtype=='assess') {
	$stm = $DBH->prepare("SELECT name,avail,startdate,enddate,date_by_lti,displaymethod,gbcategory FROM imas_assessments WHERE id=:id");
	$stm->execute(array(':id'=>$typeid));
	$line = $stm->fetch(PDO::FETCH_ASSOC);
	echo "<h2>LTI Placement of " . Sanitize::encodeStringForDisplay($line['name']) . "</h2>";
	echo "<div class='container'>";
	if($line['displaymethod']=="CanvasGradebook"){
		echo '<p>This assessment synchronizes grades between Live Poll assessments and Canvas. Please make sure you place it in your Canvas Assignments tab and that it is available to students. <font color="red">Each student must open this assessment only once in the semester</font> for their grade synchronization to be enabled. Beyond that, you will not need to access it.</p><br />';
		echo '<div class="small-12 medium-4 columns"><a class="fiu-button fiu-button-blue" href="ltihome.php?gradesync=true" >Initiate Grade Sync</a></div>';
		if(isset($_GET['gradesync'])){
			echo "<script>
			$.toast({
				heading: 'Grade Sync',
				text: 'Canvas grades will be updated shortly..',
				position: 'top-center',
				icon: 'info',
				bgColor: '#081E3f',
				textColor: '#ffffff',
				hideAfter: 10000
			})
			</script>";
			//Add this assignment to sync queue
			$stm = $DBH->prepare("INSERT INTO imas_lti_gbcatqueue (hash, userid, gbcategory, courseid,timestamp) VALUES (:hash, :userid, :gbcategory, :courseid, now())");
			$stm->execute(array(':hash'=>md5($userid.$line['gbcategory'].$cid), ':userid'=>$userid, ':gbcategory'=>$line['gbcategory'], ':courseid'=>$cid));
		}
	} else{
		echo "<div class='small-12 medium-4 columns'><a class='fiu-button fiu-button-blue' href=\"assessment/showtest.php?cid=" . Sanitize::courseId($cid) . "&id=" . Sanitize::encodeUrlParam($typeid) . "\">Preview assessment</a></div>";
		echo "<div class='small-12 medium-4 columns'><a class='fiu-button fiu-button-blue' href=\"course/isolateassessgrade.php?cid=" . Sanitize::courseId($cid) . "&aid=" . Sanitize::encodeUrlParam($typeid) . "\">Assessment grades</a></div>";
	}
	
	if ($role == 'teacher' && $line['displaymethod']!="CanvasGradebook") {
		echo "<div class='small-12 medium-4 columns'><a class='fiu-button fiu-button-blue' href=\"course/gb-itemanalysis.php?cid=" . Sanitize::courseId($cid) . "&asid=average&aid=" . Sanitize::encodeUrlParam($typeid) . "\">Item Analysis</a></div>";
	}
	echo "</div>";

	$now = time();
	if ($role == 'teacher') {
		echo "<div class='container'><div class='small-12 medium-4 columns'><a class='fiu-button fiu-button-blue' href=\"course/addassessment.php?cid=" . Sanitize::courseId($cid) . "&id=" . Sanitize::encodeUrlParam($typeid) . "&from=lti\">Settings</a></div>";
		if($line['displaymethod']!="CanvasGradebook"){
			echo "<div class='small-12 medium-4 columns'><a class='fiu-button fiu-button-blue' href=\"course/addquestions.php?cid=" . Sanitize::courseId($cid) . "&aid=" . Sanitize::encodeUrlParam($typeid) . "&from=lti\">Questions</a></div>";
		}
		if ($sessiondata['ltiitemtype']==-1) {
			echo '<div class="small-12 medium-4 columns"><a class="fiu-button fiu-button-yellow" href="ltihome.php?chgplacement=true">Change assessment</a></div>';
		}
		if($line['displaymethod']=="CanvasGradebook"){
			echo '<div class="small-12 medium-4 columns"><a class="fiu-button fiu-button-blue" target="_blank" href="assessment/showtest.php?cid=' . Sanitize::courseId($cid) . '&id=' . Sanitize::encodeUrlParam($typeid) . '" >See gradebook</a></div>';
		}
		echo '</div><br />';
	}

	echo '<br class="form"/><p>';
	if ($line['avail']==0) {
		echo 'Currently unavailable to students.';
	} else if ($line['date_by_lti']==1) {
		echo 'Waiting for the LMS to send a date';	
	} else if ($line['date_by_lti']>1) {
		echo 'Default due date set by LMS. Available until: '.formatdate($line['enddate']).'.';
		echo '</p><p>';
		if ($line['date_by_lti']==2) {
			echo 'This default due date was set by the date reported by the LMS in your instructor launch, and may change when the first student launches the assignment. ';
		} else {
			echo 'This default due date was set by the first student launch. ';
		}
		echo 'Be aware some LMSs will send unexpected dates on instructor launches, so don\'t worry if the date shown in the assessment preview is different than you expected or different than the default due date. ';
		echo '</p><p>';
		echo 'If the LMS reports a different due date for an individual student when they open this assignment, ';
		echo 'this system will handle that by setting a due date exception. ';
	} else if ($line['avail']==1 && $line['startdate']<$now && $line['enddate']>$now) { //regular show
		echo "Currently available to students.  ";
		echo "Available until " . formatdate($line['enddate']);
	} else {
		echo 'Currently unavailable to students. Available '.formatdate($line['startdate']).' until '.formatdate($line['enddate']);
	}
	echo '</p><p>&nbsp;</p><p class=small>This assessment is housed in course ID '.Sanitize::courseId($cid).'</p>';
}
echo '</div>';
