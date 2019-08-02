<?php
//IMathAS:  Grade all of one question for an assessment
//(c) 2007 David Lippman
	require("../init.php");

	$isteacher = isset($teacherid);
	$istutor = isset($tutorid);
	if (!$isteacher && !$istutor) {
		require("../header.php");
		echo "You need to log in as a teacher or tutor to access this page";
		require("../footer.php");
		exit;
	}


	echo '<div id="simple">';
	echo "<style type=\"text/css\">p.tips {	display: none;}\n .hideongradeall { display: none;} .pseudohidden {visibility:hidden;position:absolute;}</style>\n";
	if(isset($sessiondata['ltiitemtype'])){
		echo "<div class=breadcrumb>$breadcrumbbase <span href=\"course.php?cid=".Sanitize::courseId($_GET['cid'])."\">".Sanitize::encodeStringForDisplay($coursename)."</span> ";
	} else{
		echo "<div class=breadcrumb>$breadcrumbbase <a href=\"course.php?cid=".Sanitize::courseId($_GET['cid'])."\">".Sanitize::encodeStringForDisplay($coursename)."</a> ";
	}
	echo "&gt; <a href=\"gradebook.php?stu=0&cid=$cid\">Gradebook</a> ";
	echo "&gt; <a href=\"gb-itemanalysis.php?stu=" . Sanitize::encodeUrlParam($stu) . "&cid=$cid&aid=" . Sanitize::onlyInt($aid) . "\">Item Analysis</a> ";
	echo "&gt; Grading a Question</div>";
	echo "<div id=\"headergradeallq\" class=\"pagetitle\"><h1>Grading a Question in ".Sanitize::encodeStringForDisplay($aname)."</h1></div>";

	echo "<p>Note: Feedback is for whole assessment, not the individual question.</p>";
	$query = "SELECT imas_rubrics.id,imas_rubrics.rubrictype,imas_rubrics.rubric FROM imas_rubrics JOIN imas_questions ";
	$query .= "ON imas_rubrics.id=imas_questions.rubric WHERE imas_questions.id=:id";
	$stm = $DBH->prepare($query);
	$stm->execute(array(':id'=>$qid));
	if ($stm->rowCount()>0) {
		echo printrubrics(array($stm->fetch(PDO::FETCH_NUM)));
	}
	if ($page==-1) {
		echo '<button type=button id="hctoggle" onclick="hidecorrect()">'._('Hide Questions with Perfect Scores').'</button>';
		echo '<button type=button id="nztoggle" onclick="hidenonzero()">'._('Hide Nonzero Score Questions').'</button>';
		echo ' <button type=button id="hnatoggle" onclick="hideNA()">'._('Hide Unanswered Questions').'</button>';
		echo ' <button type="button" id="showanstoggle" onclick="showallans()">'._('Show All Answers').'</button>';
		echo ' <button type="button" onclick="previewallfiles()">'._('Preview All Files').'</button>';
	}
	echo ' <input type="button" id="clrfeedback" value="Clear all feedback" onclick="clearfeedback()" />';
	if ($deffbtext != '') {
		echo ' <input type="button" id="clrfeedback" value="Clear default feedback" onclick="cleardeffeedback()" />';
	}
	if ($canedit) {
		echo '<p>All visible questions: <button type=button onclick="allvisfullcred();">'._('Full Credit').'</button> ';
		echo '<button type=button onclick="allvisnocred();">'._('No Credit').'</button></p>';

		echo '<p>All answers containing: <input id="ansContainText" type=text /> assign <input id="ansPts" type=text />pts. ';
		echo '<button type=button onclick="searchAllQ();">'._('Apply').'</button></p>';		
	}
	if ($page==-1 && $canedit) {
		echo '<div class="fixedbottomright">';
		echo '<button type="button" id="quicksavebtn" onclick="quicksave()">'._('Quick Save').'</button><br/>';
		echo '<span class="noticetext" id="quicksavenotice">&nbsp;</span>';
		echo '</div>';
	}
	echo '</div>';
	
	
?>
