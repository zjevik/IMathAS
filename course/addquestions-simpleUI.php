<?php
//IMathAS:  Add/modify blocks of items on course page
//(c) 2006 David Lippman
//Modified by Ondrej Zjevik 2019



/*** pre-html data manipulation, including function code *******/

//set some page specific variables and counters
$overwriteBody = 0;
$body = "";
$pagetitle = "Add/Remove Questions";
$curBreadcrumb = "";
if(isset($sessiondata['ltiitemtype'])){
	$curBreadcrumb .= "$breadcrumbbase <span href=\"course.php?cid=" . Sanitize::courseId($_GET['cid']) . "\">".Sanitize::encodeStringForDisplay($coursename)."</span> ";
} else{
	$curBreadcrumb .= "$breadcrumbbase <a href=\"course.php?cid=" . Sanitize::courseId($_GET['cid']) . "\">".Sanitize::encodeStringForDisplay($coursename)."</a> ";
}

if (isset($_GET['clearattempts']) || isset($_GET['clearqattempts']) || isset($_GET['withdraw'])) {
	$curBreadcrumb .= "&gt; <a href=\"addquestions.php?cid=" . Sanitize::courseId($_GET['cid']) . "&aid=" . Sanitize::onlyInt($_GET['aid']) . "\">Add/Remove Questions</a> &gt; Confirm\n";
	//$pagetitle = "Modify Inline Text";
} else {
	$curBreadcrumb .= "&gt; Add/Remove Questions\n";
	//$pagetitle = "Add Inline Text";
}

if (!(isset($teacherid))) { // loaded by a NON-teacher
	$overwriteBody=1;
	$body = "You need to log in as a teacher to access this page";
} elseif (!(isset($_GET['cid'])) || !(isset($_GET['aid']))) {
	$overwriteBody=1;
	$body = "You need to access this page from the course page menu";
} else { // PERMISSIONS ARE OK, PROCEED WITH PROCESSING

	$cid = Sanitize::courseId($_GET['cid']);
	$aid = Sanitize::onlyInt($_GET['aid']);
	$stm = $DBH->prepare("SELECT courseid FROM imas_assessments WHERE id=?");
	$stm->execute(array($aid));
	if ($stm->rowCount()==0 || $stm->fetchColumn(0) != $cid) {
		echo "Invalid ID";
		exit;
	}

	if (isset($_GET['grp'])) { $sessiondata['groupopt'.$aid] = Sanitize::onlyInt($_GET['grp']); writesessiondata();}
	if (isset($_GET['selfrom'])) {
		$sessiondata['selfrom'.$aid] = Sanitize::stripHtmlTags($_GET['selfrom']);
		writesessiondata();
	} else {
		if (!isset($sessiondata['selfrom'.$aid])) {
			$sessiondata['selfrom'.$aid] = 'lib';
			writesessiondata();
		}
	}

	if (isset($teacherid) && isset($_GET['addset'])) {
		if (!isset($_POST['nchecked']) && !isset($_POST['qsetids'])) {
			$overwriteBody = 1;
			$body = "No questions selected.  <a href=\"addquestions.php?cid=$cid&aid=$aid\">Go back</a>\n";
		} else if (isset($_POST['add'])) {
			include("modquestiongrid.php");
			if (isset($_GET['process'])) {
				header('Location: ' . $GLOBALS['basesiteurl'] . "/course/addquestions.php?cid=$cid&aid=$aid&r=" .Sanitize::randomQueryStringParam());
				exit;
			}
		} else {
			$checked = $_POST['nchecked'];
			foreach ($checked as $qsetid) {
				$query = "INSERT INTO imas_questions (assessmentid,points,attempts,penalty,questionsetid) ";
				$query .= "VALUES (:assessmentid, :points, :attempts, :penalty, :questionsetid);";
				$stm = $DBH->prepare($query);
				$stm->execute(array(':assessmentid'=>$aid, ':points'=>9999, ':attempts'=>9999, ':penalty'=>9999, ':questionsetid'=>$qsetid));
				$qids[] = $DBH->lastInsertId();
			}
			//add to itemorder
			$stm = $DBH->prepare("SELECT itemorder,viddata,defpoints FROM imas_assessments WHERE id=:id");
			$stm->execute(array(':id'=>$aid));
			$row = $stm->fetch(PDO::FETCH_NUM);
			if ($row[0]=='') {
				$itemorder = implode(",",$qids);
			} else {
				$itemorder  = $row[0] . "," . implode(",",$qids);
			}
			$viddata = $row[1];
			if ($viddata != '') {
				if ($row[0]=='') {
					$nextnum = 0;
				} else {
					$nextnum = substr_count($row[0],',')+1;
				}
				$numnew= count($checked);
				$viddata = unserialize($viddata);
				if (!isset($viddata[count($viddata)-1][1])) {
					$finalseg = array_pop($viddata);
				} else {
					$finalseg = '';
				}
				for ($i=$nextnum;$i<$nextnum+$numnew;$i++) {
					$viddata[] = array('','',$i);
				}
				if ($finalseg != '') {
					$viddata[] = $finalseg;
				}
				$viddata = serialize($viddata);
			}
			$stm = $DBH->prepare("UPDATE imas_assessments SET itemorder=:itemorder,viddata=:viddata WHERE id=:id");
			$stm->execute(array(':itemorder'=>$itemorder, ':viddata'=>$viddata, ':id'=>$aid));

			require_once("../includes/updateptsposs.php");
			updatePointsPossible($aid, $itemorder, $row['defpoints']);

			header('Location: ' . $GLOBALS['basesiteurl'] . "/course/addquestions.php?cid=$cid&aid=$aid&r=" .Sanitize::randomQueryStringParam());
			exit;
		}
	}
	if (isset($_GET['modqs'])) {
		if (!isset($_POST['checked']) && !isset($_POST['qids'])) {
			$overwriteBody = 1;
			$body = "No questions selected.  <a href=\"addquestions.php?cid=$cid&aid=$aid\">Go back</a>\n";
		} else {
			include("modquestiongrid.php");
			if (isset($_GET['process'])) {
				header('Location: ' . $GLOBALS['basesiteurl'] . "/course/addquestions.php?cid=$cid&aid=$aid&r=" .Sanitize::randomQueryStringParam());
				exit;
			}
		}
	}
	if (isset($_REQUEST['clearattempts'])) {
		if (isset($_POST['clearattempts']) && $_POST['clearattempts']=="confirmed") {
			require_once('../includes/filehandler.php');
			deleteallaidfiles($aid);
			$stm = $DBH->prepare("DELETE FROM imas_assessment_sessions WHERE assessmentid=:assessmentid");
			$stm->execute(array(':assessmentid'=>$aid));
			$stm = $DBH->prepare("DELETE FROM imas_livepoll_status WHERE assessmentid=:assessmentid");
			$stm->execute(array(':assessmentid'=>$aid));
			$stm = $DBH->prepare("UPDATE imas_questions SET withdrawn=0 WHERE assessmentid=:assessmentid");
			$stm->execute(array(':assessmentid'=>$aid));
			header('Location: ' . $GLOBALS['basesiteurl'] . "/course/addquestions.php?cid=$cid&aid=$aid&r=" .Sanitize::randomQueryStringParam());
			exit;
		} else {
			$overwriteBody = 1;
			$stm = $DBH->prepare("SELECT name FROM imas_assessments WHERE id=:id");
			$stm->execute(array(':id'=>$aid));
			$assessmentname = $stm->fetchColumn(0);
			$body = "<div class=breadcrumb>$curBreadcrumb</div>\n";
			$body .= "<h2>".Sanitize::encodeStringForDisplay($assessmentname)."</h2>";
			$body .= "<p>Are you SURE you want to delete all attempts (grades) for this assessment?</p>";
			$body .= '<form method="POST" action="'.sprintf('addquestions.php?cid=%s&aid=%d',$cid, $aid).'">';
			$body .= '<p><button type=submit name=clearattempts value=confirmed>'._('Yes, Clear').'</button>';
			$body .= "<input type=button value=\"Nevermind\" class=\"secondarybtn\" onClick=\"window.location='addquestions.php?cid=$cid&aid=$aid';\"></p>\n";
			$body .= '</form>';
		}
	}
	
	/*
	9/25/14: Doesn't appear to get referenced anywhere
	if (isset($_GET['clearqattempts'])) {
		if (isset($_GET['confirmed'])) {
			$clearid = $_GET['clearqattempts'];
			if ($clearid!=='' && is_numeric($clearid)) {
				$query = "SELECT id,questions,scores,attempts,lastanswers,bestscores,bestattempts,bestlastanswers ";
				$query .= "FROM imas_assessment_sessions WHERE assessmentid='$aid'";
				$result = mysql_query($query) or die("Query failed : " . mysql_error());
				while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
					if (strpos($line['questions'],';')===false) {
						$questions = explode(",",$line['questions']);
						$bestquestions = $questions;
					} else {
						list($questions,$bestquestions) = explode(";",$line['questions']);
						$questions = explode(",",$questions);
						$bestquestions = explode(",",$bestquestions);
					}
					$qloc = array_search($clearid,$questions);
					if ($qloc!==false) {
						$attempts = explode(',',$line['attempts']);
						$lastanswers = explode('~',$line['lastanswers']);
						$bestattempts = explode(',',$line['bestattempts']);
						$bestlastanswers = explode('~',$line['bestlastanswers']);

						if (strpos($line['scores'],';')===false) {
							//old format
							$scores = explode(',',$line['scores']);
							$bestscores = explode(',',$line['bestscores']);
							$scores[$qloc] = -1;
							$bestscores[$qloc] = -1;
							$scorelist = implode(',',$scores);
							$bestscorelist = implode(',',$scores);
						} else {
							//has raw
							list($scorelist,$rawscorelist) = explode(';',$line['scores']);
							$scores = explode(',', $scorelist);
							$rawscores = explode(',', $rawscorelist);
							$scores[$qloc] = -1;
							$rawscores[$qloc] = -1;
							$scorelist = implode(',',$scores).';'.implode(',',$rawscores);
							list($bestscorelist,$bestrawscorelist,$firstscorelist) = explode(';',$line['bestscores']);
							$bestscores = explode(',', $bestscorelist);
							$bestrawscores = explode(',', $bestrawscorelist);
							$firstscores = explode(',', $firstscorelist);
							$bestscores[$qloc] = -1;
							$bestrawscores[$qloc] = -1;
							$firstscores[$qloc] = -1;
							$bestscorelist = implode(',',$bestscores).';'.implode(',',$bestrawscores).';'.implode(',',$firstscores);
						}



						$attempts[$qloc] = 0;
						$lastanswers[$qloc] = '';
						$bestattempts[$qloc] = 0;
						$bestlastanswers[$qloc] = '';
						$attemptslist = implode(',',$attempts);
						$lalist = addslashes(implode('~',$lastanswers));
						$bestattemptslist = implode(',',$attempts);
						$bestlalist = addslashes(implode('~',$lastanswers));

						$query = "UPDATE imas_assessment_sessions SET scores='$scorelist',attempts='$attemptslist',lastanswers='$lalist',";
						$query .= "bestscores='$bestscorelist',bestattempts='$bestattemptslist',bestlastanswers='$bestlalist' ";
						$query .= "WHERE id='{$line['id']}'";
						mysql_query($query) or die("Query failed : " . mysql_error());
					}
				}
				header('Location: ' . $urlmode  . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/addquestions.php?cid=$cid&aid=$aid");
				exit;
			} else {
				$overwriteBody = 1;
				$body = "<p>Error with question id.  Try again.</p>";
			}
		} else {
			$overwriteBody = 1;
			$body = "<div class=breadcrumb>$curBreadcrumb</div>\n";
			$body .= "<p>Are you SURE you want to delete all attempts (grades) for this question?</p>";
			$body .= "<p>This will allow you to safely change points and penalty for a question, or give students another attempt ";
			$body .= "on a question that needed fixing.  This will NOT allow you to remove the question from the assessment.</p>";
			$body .= "<p><input type=button value=\"Yes, Clear\" onClick=\"window.location='addquestions.php?cid=$cid&aid=$aid&clearqattempts={$_GET['clearqattempts']}&confirmed=1'\">\n";
			$body .= "<input type=button value=\"Nevermind\" class=\"secondarybtn\" onClick=\"window.location='addquestions.php?cid=$cid&aid=$aid'\"></p>\n";
		}
	}
	*/
	if (isset($_GET['withdraw'])) {
		if (isset($_POST['withdrawtype'])) {
			if (strpos($_GET['withdraw'],'-')!==false) {
				$isingroup = true;
				$loc = explode('-',$_GET['withdraw']);
				$toremove = $loc[0];
			} else {
				$isingroup = false;
				$toremove = $_GET['withdraw'];
			}
			$stm = $DBH->prepare("SELECT itemorder,defpoints FROM imas_assessments WHERE id=:id");
			$stm->execute(array(':id'=>$aid));
			list($itemorder, $defpoints) = $stm->fetch(PDO::FETCH_NUM);
			$itemorder = explode(',', $itemorder);

			$qids = array();
			if ($isingroup && $_POST['withdrawtype']!='full') { //is group remove
				$qids = explode('~',$itemorder[$toremove]);
				if (strpos($qids[0],'|')!==false) { //pop off nCr
					array_shift($qids);
				}
			} else if ($isingroup) { //is single remove from group
				$sub = explode('~',$itemorder[$toremove]);
				if (strpos($sub[0],'|')!==false) { //pop off nCr
					array_shift($sub);
				}
				$qids = array($sub[$loc[1]]);
			} else { //is regular item remove
				$qids = array($itemorder[$toremove]);
			}
			$qidlist = implode(',',array_map('intval',$qids));
			//withdraw question
			$query = "UPDATE imas_questions SET withdrawn=1";
			if ($_POST['withdrawtype']=='zero' || $_POST['withdrawtype']=='groupzero') {
				$query .= ',points=0';
			}
			$query .= " WHERE id IN ($qidlist)";
			$stm = $DBH->query($query);

			//get possible points if needed
			if ($_POST['withdrawtype']=='full' || $_POST['withdrawtype']=='groupfull') {
				$poss = array();
				$query = "SELECT id,points FROM imas_questions WHERE id IN ($qidlist)";
				$stm = $DBH->query($query);
				while ($row = $stm->fetch(PDO::FETCH_NUM)) {
					if ($row[1]==9999) {
						$poss[$row[0]] = $defpoints;
					} else {
						$poss[$row[0]] = $row[1];
					}
				}
			}
			
			if ($_POST['withdrawtype']=='zero' || $_POST['withdrawtype']=='groupzero') {
				//update points possible
				require_once("../includes/updateptsposs.php");
				updatePointsPossible($aid, $itemorder, $defpoints);
			}

			//update assessment sessions
			$stm = $DBH->prepare("SELECT id,questions,bestscores,lti_sourcedid FROM imas_assessment_sessions WHERE assessmentid=:assessmentid");
			$stm->execute(array(':assessmentid'=>$aid));
			while ($row = $stm->fetch(PDO::FETCH_ASSOC)) {
				if (strpos($row['questions'],';')===false) {
					$qarr = explode(",",$row['questions']);
				} else {
					list($questions,$bestquestions) = explode(";",$row['questions']);
					$qarr = explode(",",$bestquestions);
				}
				if (strpos($row['bestscores'],';')===false) {
					$bestscores = explode(',',$row['bestscores']);
					$doraw = false;
				} else {
					list($bestscorelist,$bestrawscorelist,$firstscorelist) = explode(';',$row['bestscores']);
					$bestscores = explode(',', $bestscorelist);
					$bestrawscores = explode(',', $bestrawscorelist);
					$firstscores = explode(',', $firstscorelist);
					$doraw = true;
				}
				for ($i=0; $i<count($qarr); $i++) {
					if (in_array($qarr[$i],$qids)) {
						if ($_POST['withdrawtype']=='zero' || $_POST['withdrawtype']=='groupzero') {
							$bestscores[$i] = 0;
						} else if ($_POST['withdrawtype']=='full' || $_POST['withdrawtype']=='groupfull') {
							$bestscores[$i] = $poss[$qarr[$i]];
						}
					}
				}
				if ($doraw) {
					$slist = implode(',',$bestscores).';'.implode(',',$bestrawscores).';'.implode(',',$firstscores);
				} else {
					$slist = implode(',',$bestscores );
				}
				$stm2 = $DBH->prepare("UPDATE imas_assessment_sessions SET bestscores=:bestscores WHERE id=:id");
				$stm2->execute(array(':bestscores'=>$slist, ':id'=>$row['id']));
				
				if (strlen($row['lti_sourcedid'])>1) {
					//update LTI score
					require_once("../includes/ltioutcomes.php");
					calcandupdateLTIgrade($row['lti_sourcedid'], $aid, $bestscores, true);
				}
			}

			header('Location: ' . $GLOBALS['basesiteurl'] . "/course/addquestions.php?cid=$cid&aid=$aid&r=" .Sanitize::randomQueryStringParam());
			exit;

		} else {
			if (strpos($_GET['withdraw'],'-')!==false) {
				$isingroup = true;
			} else {
				$isingroup = false;
			}
			$overwriteBody = 1;
			$body = "<div class=breadcrumb>$curBreadcrumb</div>\n";
			$body .= "<h2>Withdraw Question</h2>";
			$body .= "<form method=post action=\"addquestions.php?cid=$cid&aid=$aid&withdraw=".Sanitize::encodeStringForDisplay($_GET['withdraw'])."\">";
			if ($isingroup) {
				$body .= '<p><b>This question is part of a group of questions</b>.  </p>';
				$body .= '<input type=radio name="withdrawtype" value="groupzero" > Set points possible and all student scores to zero <b>for all questions in group</b><br/>';
				$body .= '<input type=radio name="withdrawtype" value="groupfull" checked="1"> Set all student scores to points possible <b>for all questions in group</b><br/>';
				$body .= '<input type=radio name="withdrawtype" value="full" > Set all student scores to points possible <b>for this question only</b>';
			} else {
				$body .= '<input type=radio name="withdrawtype" value="zero" > Set points possible and all student scores to zero.<br/>';
				$body .= '<input type=radio name="withdrawtype" value="full" checked="1"> Set all student scores to points possible. This only applies to students who <b>opened</b> the assessment. Students who never accessed the assessment will still have grade of N/A';
			}
			$body .= '<p>This action can <b>not</b> be undone.</p>';
			$body .= '<p><input type=submit value="Withdraw Question">';
			$body .= "<input type=button value=\"Nevermind\" class=\"secondarybtn\" onClick=\"window.location='addquestions.php?cid=$cid&aid=$aid'\"></p>\n";

			$body .= '</form>';
		}

	}
}

echo '<div id="simple">';
if ($overwriteBody==1) {
	echo $body;
} else {

//var_dump($jsarr);
?>
	<script type="text/javascript">
		var curcid = <?php echo $cid ?>;
		var curaid = <?php echo $aid ?>;
		var defpoints = <?php echo (int) Sanitize::onlyInt($defpoints); ?>;
		var AHAHsaveurl = '<?php echo $GLOBALS['basesiteurl'] ?>/course/addquestionssave.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>';
		var curlibs = '<?php echo Sanitize::encodeStringForJavascript($searchlibs); ?>';
	</script>
	<script type="text/javascript" src="<?php echo $imasroot ?>/javascript/tablesorter.js"></script>

	<div class="breadcrumb"><?php echo $curBreadcrumb ?></div>

	<div id="headeraddquestions" class="pagetitle"><h1>Add/Remove Questions
		<img src="<?php echo $imasroot ?>/img/help.gif" alt="Help" onClick="window.open('<?php echo $imasroot ?>/help.php?section=addingquestionstoanassessment','help','top=0,width=400,height=500,scrollbars=1,left='+(screen.width-420))"/>
	</h1></div>
<?php
	echo '<div class="cp"><a href="addassessment.php?id='.Sanitize::onlyInt($_GET['aid']).'&amp;cid='.$cid.'">'._('Assessment Settings').'</a></div>';
	if ($beentaken) {
?>
	<h2>Warning</h2>
	<p>This assessment has already been taken.  Adding or removing questions, or changing a
		question's settings (point value, penalty, attempts) now would majorly mess things up.
		If you want to make these changes, you need to clear all existing assessment attempts
	</p>
	<p><input type=button value="Clear Assessment Attempts" onclick="window.location='addquestions.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>&clearattempts=ask'">
	</p>
<?php
	}
?>
	<h2>Questions in Assessment - <?php echo Sanitize::encodeStringForDisplay($page_assessmentName); ?></h2>

<?php
	if ($itemorder == '') {
		echo "<p>No Questions currently in assessment</p>\n";

		echo '<a href="#" onclick="this.style.display=\'none\';document.getElementById(\'helpwithadding\').style.display=\'block\';return false;">';
		echo "<img src=\"$imasroot/img/help.gif\" alt=\"Help\"/> ";
		echo 'How do I find questions to add?</a>';
		echo '<div id="helpwithadding" style="display:none">';
		if ($sessiondata['selfrom'.$aid]=='lib') {
			echo "<p>You are currently set to select questions from the question libraries.  If you would like to select questions from ";
			echo "assessments you've already created, click the <b>Select From Assessments</b> button below</p>";
			echo "<p>To find questions to add from the question libraries:";
			echo "<ol><li>Click the <b>Select Libraries</b> button below to pop open the library selector</li>";
			echo " <li>In the library selector, open up the topics of interest, and click the checkbox to select libraries to use</li>";
			echo " <li>Scroll down in the library selector, and click the <b>Use Libraries</b> button</li> ";
			echo " <li>On this page, click the <b>Search</b> button to list the questions in the libraries selected.<br/>  You can limit the listing by entering a sepecific search term in the box provided first, or leave it blank to view all questions in the chosen libraries</li>";
			echo "</ol>";
		} else if ($sessiondata['selfrom'.$aid]=='assm') {
			echo "<p>You are currently set to select questions existing assessments.  If you would like to select questions from ";
			echo "the question libraries, click the <b>Select From Libraries</b> button below</p>";
			echo "<p>To find questions to add from existing assessments:";
			echo "<ol><li>Use the checkboxes to select the assessments you want to pull questions from</li>";
			echo " <li>Click <b>Use these Assessments</b> button to list the questions in the assessments selected</li>";
			echo "</ol>";
		}
		echo "<p>To select questions and add them:</p><ul>";
		echo " <li>Click the <b>Preview</b> button after the question description to view an example of the question</li>";
		echo " <li>Use the checkboxes to mark the questions you want to use</li>";
		echo " <li>Click the <b>Add</b> button above the question list to add the questions to your assessment</li> ";
		echo "  </ul>";
		echo '</div>';

	} else {
?>
	<form id="curqform" method="post" action="addquestions.php?modqs=true&aid=<?php echo $aid ?>&cid=<?php echo $cid ?>">
<?php
		if (!$beentaken) {
			/*
			Use select boxes to
		<select name=group id=group>
			<option value="0"<?php echo $grp0Selected ?>>Rearrange questions</option>
			<option value="1"<?php echo $grp1Selected ?>>Group questions</option>
		</select>
		<br/>
		*/
?>

		
		With Selected: <input type=button value="Remove" onclick="removeSelected()" />
				<input type=hidden value="Group" onclick="groupSelected()" />
				<input type="submit" value="Change Settings" onclick="return confirm_textseg_dirty()"/>

<?php
		}
?>
		<span id="submitnotice" class=noticetext></span>
		<div id="curqtbl" class="curqtbl"></div>

	</form>
	<p>Assessment points total: <span id="pttotal" class="pttotal"></span></p>
	<?php if (isset($introconvertmsg)) {echo $introconvertmsg;}?>
	<script>
		var itemarray = <?php echo json_encode($jsarr, JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS); ?>;
		var beentaken = <?php echo ($beentaken) ? 1:0; ?>;
		var displaymethod = "<?php echo Sanitize::encodeStringForDisplay($displaymethod); ?>";
		/*document.getElementById("curqtbl").innerHTML = generateTable();
		initeditor("selector","div.textsegment",null,true ,editorSetup);
		tinymce.init({
			selector: "h4.textsegment",
			inline: true,
			menubar: false,
			statusbar: false,
			branding: false,
			plugins: ["charmap"],
			toolbar: "charmap saveclose",
			setup: editorSetup
		});
		*/
		<?php
			if($displaymethod == "JustInTime"){
				if($justintimeorder != ""){
					echo "var justintimeorder = ".$justintimeorder.";";
				} else{
					echo "var justintimeorder = [];";
				}
				echo "$(addSavetoggle())";
			}
		?>
		//$(refreshTable);

		function addSavetoggle(){
			$("#curqform").prepend('<label class="switch"><input type="checkbox" id="savechanges" checked><div class="slider round"></div></label>');
			$("#savechanges").change(function() {
				if($(this).is(":checked")) {
					$(".slider").removeClass("fiu-yellow");
					submitChanges()
				} else{
				}
			});
		}
		function checkforMathJax(callback){
			if(typeof MathJax == 'undefined'){
				setTimeout(function() {
					checkforMathJax(callback)
				}, 100);
			} else{
				callback();
			}
		}
		
		$(checkforMathJax(function() {refreshTable();}));
	</script>
<?php
	}
	if ($displaymethod=='VideoCue') {
		echo '<p><input type=button value="Define Video Cues" onClick="window.location=\'addvideotimes.php?cid='.$cid.'&aid='.$aid.'\'"/></p>';
	}
?>
	<p>
		<input type=button value="Done" title="Exit back to course page" onClick="window.location='course.php?cid=<?php echo $cid ?>'">
		<input type=button value="Assessment Settings" title="Modify assessment settings" onClick="window.location='addassessment.php?cid=<?php echo $cid ?>&id=<?php echo $aid ?>'">
		<input type=hidden value="Categorize Questions" title="Categorize questions by outcome or other groupings" onClick="window.location='categorize.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>'">
		<input type=button value="Create Print Version" onClick="window.location='<?php
		if (isset($CFG['GEN']['pandocserver'])) {
			echo 'printlayoutword.php?cid='.$cid.'&aid='.$aid;
		} else {
			echo 'printtest.php?cid='.$cid.'&aid='.$aid;
		}
		?>'">

		<input type=hidden value="Define End Messages" title="Customize messages to display based on the assessment score" onClick="window.location='assessendmsg.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>'">
		<input type=button value="Preview" title="Preview this assessment" onClick="window.open('<?php echo $imasroot;?>/assessment/showtest.php?cid=<?php echo $cid ?>&id=<?php echo $aid ?>','Testing','width='+(.4*screen.width)+',height='+(.8*screen.height)+',scrollbars=1,resizable=1,status=1,top=20,left='+(.6*screen.width-20))">
	</p>

<?php
//<input type=button value="Select Libraries" onClick="libselect()">

	//POTENTIAL QUESTIONS
	if ($sessiondata['selfrom'.$aid]=='lib') { //selecting from libraries
		if (!$beentaken) {
?>

	<h2>Potential Questions</h2>
	<form method=post action="addquestions.php?aid=<?php echo $aid ?>&cid=<?php echo $cid ?>">

		In Libraries:
		<span id="libnames"><?php echo Sanitize::encodeStringForDisplay($lnames); ?></span>
		<input type=hidden name="libs" id="libs"  value="<?php echo Sanitize::encodeStringForDisplay($searchlibs); ?>">
		<input type="button" value="Select Libraries" onClick="GB_show('Library Select','libtree2.php?libtree=popup&libs='+curlibs,500,500)" />
		or <input type=button value="Select From Assessments" onClick="window.location='addquestions.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>&selfrom=assm'">
		<br>
		Search:
		<input type=text size=15 name=search value="<?php echo $search ?>">
		<span tabindex="0" data-tip="Search all libraries, not just selected ones" onmouseenter="tipshow(this)" onfocus="tipshow(this)" onmouseleave="tipout()" onblur="tipout()">
		<input type=checkbox name="searchall" value="1" <?php writeHtmlChecked($searchall,1,0) ?> />
		Search all libs</span>
		<span tabindex="0" data-tip="List only questions I own" onmouseenter="tipshow(this)" onfocus="tipshow(this)" onmouseleave="tipout()" onblur="tipout()">
		<input type=checkbox name="searchmine" value="1" <?php writeHtmlChecked($searchmine,1,0) ?> />
		Mine only</span>
		<span tabindex="0" data-tip="Exclude questions already in assessment" onmouseenter="tipshow(this)" onfocus="tipshow(this)" onmouseleave="tipout()" onblur="tipout()">
		<input type=checkbox name="newonly" value="1" <?php writeHtmlChecked($newonly,1,0) ?> />
		Exclude added</span>
		<input type=submit value=Search>
		<input type=button value="Add New Question" onclick="window.location='moddataset.php?aid=<?php echo $aid ?>&cid=<?php echo $cid ?>'">

	</form>
<?php
			if ($searchall==1 && trim($search)=='' && $searchmine==0) {
				echo "Must provide a search term when searching all libraries";
			} elseif (isset($search)) {
				if ($noSearchResults) {
					echo "<p>No Questions matched search</p>\n";
				} else {
?>
		<form id="selq" method=post action="addquestions.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>&addset=true">

		Checked: 
		<input name="add" type=submit value="Add" />
		<input name="addquick" type=submit value="Add (using defaults)">
		<input type=button value="Preview Selected" onclick="previewsel('selq')" />

		<table cellpadding="5" id="myTable" class="gb" style="clear:both; position:relative;">
			<thead>
				<tr><th>&nbsp;</th><th>Description</th><th>&nbsp;</th><th>ID</th><th>Preview</th><th>Type</th>
					<?php echo $page_libRowHeader ?>
					<th>Times Used</th>
					<?php if ($page_useavgtimes) {?><th><span onmouseenter="tipshow(this,'Average time, in minutes, this question has taken students')" onmouseleave="tipout()">Avg Time</span></th><?php } ?>
					<th>Mine</th><th>Actions</th>
					<?php if ($searchall==0) { echo '<th><span onmouseenter="tipshow(this,\'Flag a question if it is in the wrong library\')" onmouseleave="tipout()">Wrong Lib</span></th>';} ?>
				</tr>
			</thead>
			<tbody>
<?php
				$alt=0;
				for ($j=0; $j<count($page_libstouse); $j++) {

					if ($searchall==0) {
						if ($alt==0) {echo "<tr class=even>"; $alt=1;} else {echo "<tr class=odd>"; $alt=0;}
						echo '<td></td>';
						echo '<td>';
						echo '<b>' . Sanitize::encodeStringForDisplay($lnamesarr[$page_libstouse[$j]]) . '</b>';
						echo '</td>';
						for ($k=0;$k<9;$k++) {echo '<td></td>';}
						echo '</tr>';
					}

					for ($i=0;$i<count($page_libqids[$page_libstouse[$j]]); $i++) {
						$qid =$page_libqids[$page_libstouse[$j]][$i];
						if ($alt==0) {echo "<tr class=even>"; $alt=1;} else {echo "<tr class=odd>"; $alt=0;}
?>

					<td><?php echo $page_questionTable[$qid]['checkbox']; ?></td>
					<td><?php echo $page_questionTable[$qid]['desc']; ?></td>
					<td class="nowrap">
					   <div <?php if ($page_questionTable[$qid]['cap']) {echo 'class="ccvid"';}?>><?php echo $page_questionTable[$qid]['extref'] ?></div>
					</td>
					<td><?php echo Sanitize::encodeStringForDisplay($qid); ?></td>
					<td><?php echo $page_questionTable[$qid]['preview']; ?></td>
					<td><?php echo Sanitize::encodeStringForDisplay($page_questionTable[$qid]['type']); ?></td>
<?php
						if ($searchall==1) {
?>
					<td><?php echo $page_questionTable[$qid]['lib'] ?></td>
<?php
						}
?>
					<td class=c><?php
					echo Sanitize::encodeStringForDisplay($page_questionTable[$qid]['times']); ?>
					</td>
					<?php if ($page_useavgtimes) {?><td class="c"><?php
					if (isset($page_questionTable[$qid]['qdata'])) {
						echo '<span onmouseenter="tipshow(this,\'Avg score on first try: '.round($page_questionTable[$qid]['qdata'][0]).'%';
						echo '<br/>Avg time on first try: '.round($page_questionTable[$qid]['qdata'][1]/60,1).' min<br/>N='.$page_questionTable[$qid]['qdata'][2].'\')" onmouseleave="tipout()">';
					} else {
						echo '<span>';
					}
					echo $page_questionTable[$qid]['avgtime'].'</span>'; ?></td> <?php }?>
					<td><?php echo $page_questionTable[$qid]['mine'] ?></td>
					<td><div class="dropdown">
					  <a role="button" tabindex=0 class="dropdown-toggle arrow-down" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					    Action</a>
					  <ul role="menu" class="dropdown-menu dropdown-menu-right">
					   <li><?php echo $page_questionTable[$qid]['add']; ?></li>
					   <li><?php echo $page_questionTable[$qid]['src']; ?></li>
					   <li><?php echo $page_questionTable[$qid]['templ']; ?></li>
					  </ul>
					</td>
					<?php if ($searchall==0) {
						if ($page_questionTable[$qid]['junkflag']==1) {
							echo "<td class=c><img class=\"pointer\" id=\"tag{$page_questionTable[$qid]['libitemid']}\" src=\"$imasroot/img/flagfilled.gif\" onClick=\"toggleJunkFlag({$page_questionTable[$qid]['libitemid']});return false;\" alt=\"Flagged\" /></td>";
						} else {
							echo "<td class=c><img class=\"pointer\" id=\"tag{$page_questionTable[$qid]['libitemid']}\" src=\"$imasroot/img/flagempty.gif\" onClick=\"toggleJunkFlag({$page_questionTable[$qid]['libitemid']});return false;\" alt=\"Not flagged\" /></td>";
						}
					} ?>
				</tr>
<?php
					}
				}
				if ($searchall==1 && ($searchlimited || $offset>0)) {
					echo '<tr><td></td><td><i>'._('Search cut off at 300 results');
					echo '<br>'._('Showing ').($offset+1).'-'.($offset + 300).'. ';
					if ($offset>0) {
						$prevoffset = max($offset-300, 0);
						echo "<a href=\"addquestions.php?cid=$cid&aid=$aid&offset=$prevoffset\">"._('Previous').'</a> ';
					}
					if ($searchlimited) {
						$nextoffset = $offset+300;
						echo "<a href=\"addquestions.php?cid=$cid&aid=$aid&offset=$nextoffset\">"._('Next').'</a> ';
					}
					echo '</i></td></tr>';
				}
?>
			</tbody>
		</table>
		<p>Questions <span style="color:#999">in gray</span> have been added to the assessment.</p>
		<script type="text/javascript">
			initSortTable('myTable',[false,'S','S','N',false,'S',<?php echo ($searchall==1) ? "false, " : ""; ?>'N','N','S',false<?php echo ($searchall==0) ? ",false" : ""; ?>],true);
		    $(".dropdown-toggle").dropdown();
		</script>
	</form>

<?php
				}
			}
		}

	} else if ($sessiondata['selfrom'.$aid]=='assm') { //select from assessments
?>

	<h2>Potential Questions</h2>

<?php
		if (isset($_POST['achecked']) && (count($_POST['achecked'])==0)) {
			echo "<p>No Assessments Selected.  Select at least one assessment.</p>";
		} elseif (isset($sessiondata['aidstolist'.$aid])) { //list questions
?>
	<form id="selq" method=post action="addquestions.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>&addset=true">

		<input type=button value="Select Assessments" onClick="window.location='addquestions.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>&clearassmt=1'">
		or <input type=button value="Select From Libraries" onClick="window.location='addquestions.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>&selfrom=lib'">
		<br/>

		Check: <a href="#" onclick="return chkAllNone('selq','nchecked[]',true)">All</a> <a href="#" onclick="return chkAllNone('selq','nchecked[]',false)">None</a>
		<input name="add" type=submit value="Add" />
		<input name="addquick" type=submit value="Add Selected (using defaults)">
		<input type=button value="Preview Selected" onclick="previewsel('selq')" />

		<table cellpadding=5 id=myTable class=gb>
			<thead>
				<tr>
					<th> </th><th>Description</th><th></th><th>ID</td><th>Preview</th><th>Type</th><th>Times Used</th><th>Mine</th><th>Add</th><th>Source</th><th>Use as Template</th>
				</tr>
			</thead>
			<tbody>
<?php
			$alt=0;
			for ($i=0; $i<count($page_assessmentQuestions['aiddesc']);$i++) {
				if ($alt==0) {echo "<tr class=even>"; $alt=1;} else {echo "<tr class=odd>"; $alt=0;}
?>
				<td></td>
				<td><b><?php echo Sanitize::encodeStringForDisplay($page_assessmentQuestions['aiddesc'][$i]); ?></b></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
			</tr>
<?php
				for ($x=0;$x<count($page_assessmentQuestions[$i]['desc']);$x++) {
					if ($alt==0) {echo "<tr class=even>"; $alt=1;} else {echo "<tr class=odd>"; $alt=0;}
?>
				<td><?php echo $page_assessmentQuestions[$i]['checkbox'][$x]; ?></td>
				<td><?php echo $page_assessmentQuestions[$i]['desc'][$x]; ?></td>
				<td class="nowrap">
				  <div <?php if ($page_assessmentQuestions[$i]['cap'][$x]) {echo 'class="ccvid"';}?>><?php echo $page_assessmentQuestions[$i]['extref'][$x]; ?></div>
				</td>
				<td><?php echo Sanitize::onlyInt($page_assessmentQuestions[$i]['qsetid'][$x]); ?></td>
				<td><?php echo $page_assessmentQuestions[$i]['preview'][$x]; ?></td>
				<td><?php echo Sanitize::encodeStringForDisplay($page_assessmentQuestions[$i]['type'][$x]); ?></td>
				<td class=c><?php echo Sanitize::onlyInt($page_assessmentQuestions[$i]['times'][$x]); ?></td>
				<td><?php echo $page_assessmentQuestions[$i]['mine'][$x]; ?></td>
				<td class=c><?php echo $page_assessmentQuestions[$i]['add'][$x]; ?></td>
				<td class=c><?php echo $page_assessmentQuestions[$i]['src'][$x]; ?></td>
				<td class=c><?php echo $page_assessmentQuestions[$i]['templ'][$x]; ?></td>
			</tr>

<?php
				}
			}
?>
			</tbody>
		</table>

		<script type="text/javascript">
			initSortTable('myTable',Array(false,'S','N',false,'S','N','S',false,false,false),true);
		</script>
		</form>

<?php
		} else {  //choose assessments
?>
		<h3>Choose assessments to take questions from</h3>
		<form id="sela" method=post action="addquestions.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>">
		Check: <a href="#" onclick="return chkAllNone('sela','achecked[]',true)">All</a> <a href="#" onclick="return chkAllNone('sela','achecked[]',false)">None</a>
		<input type=submit value="Use these Assessments" /> or
		<input type=button value="Select From Libraries" onClick="window.location='addquestions.php?cid=<?php echo $cid ?>&aid=<?php echo $aid ?>&selfrom=lib'">

		<table cellpadding=5 id=myTable class=gb>
			<thead>
			<tr><th></th><th>Assessment</th><th>Summary</th></tr>
			</thead>
			<tbody>
<?php

			$alt=0;
			for ($i=0;$i<count($page_assessmentList);$i++) {
				if ($alt==0) {echo "<tr class=even>"; $alt=1;} else {echo "<tr class=odd>"; $alt=0;}
?>

				<td><input type=checkbox name='achecked[]' value='<?php echo $page_assessmentList[$i]['id'] ?>'></td>
				<td><?php echo $page_assessmentList[$i]['name'] ?></td>
				<td><?php echo $page_assessmentList[$i]['summary'] ?></td>
			</tr>
<?php
			}
?>

			</tbody>
		</table>
		<script type=\"text/javascript\">
			initSortTable('myTable',Array(false,'S','S',false,false,false),true);
		</script>
	</form>

<?php
		}

	}

}
echo '</div>';
?>
