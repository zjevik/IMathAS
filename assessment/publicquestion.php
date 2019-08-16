<?php
//IMathAS:  Frontend of testing engine - manages administration of assessments
//(c) 2006 David Lippman

    require("../init_without_validate.php");
	ini_set('session.gc_maxlifetime',86400);
    session_start([
        'session.use_cookies' => 0,
        'cookie_lifetime' => 86400,
        'use_only_cookies'=> 0,
        'session.use_trans_sid' => 1
	]);
	$qid = null;
	if (!isset($_SESSION['data']) || isset($_GET['qid'])) {
		$sessiondata = array();

		$qid = Sanitize::onlyInt($_GET['qid']);
		$questions = Array($qid);
		$qi = Array($qid => Array(
			'qid' => $qid,
            'questionsetid' => $qid,
            'category' => 0,
            'penalty' => 9999,
            'attempts' => 0,
            'regen' => 0,
			'showans' => 'A',
			'points' => 10,
            'withdrawn' => 0,
            'showhints' => 0,
            'fixedseeds' => NULL,
            'allowregen' => 1,
            'showansduring' => NULL,
            'showansafterlast' => NULL
		));
		//$bestscores[$i] = -1;
		$sessiondata['mathdisp'] = 1;
		$sessiondata['livepreview'] = 1;
		$sessiondata['userprefs']['drawentry'] = 1;

		if ($qi[$questions[0]]['fixedseeds'] !== null && $qi[$questions[0]]['fixedseeds'] != '') {
			$fs = explode(',',$qi[$questions[0]]['fixedseeds']);
			if (count($fs)>1) {
				//find existing seed and use next one
				$k = array_search($seeds[0], $fs);
				$seeds[0] = $fs[($k+1)%count($fs)];
			}
		} else {
			$seeds[0] = rand(1,9999);
		}
		
	} else {
		$sessiondata = unserialize(base64_decode($_SESSION['data']));

		$questions = $sessiondata['questions'];
		$qi = $sessiondata['qi'];
		$qid = $sessiondata['qid'];
		$attempts = $sessiondata['attempts'];
		$lastanswers = $sessiondata['lastanswers'];
		$scores = $sessiondata['attempts'];
		$seeds = $sessiondata['seeds'];
		$bestscores = $sessiondata['bestscores'];
	}
	if (isset($_POST['verattempts'])){
		$attempts[0] = $_POST['verattempts'];
	}
	//Look to see if a hook file is defined, and include if it is
	if (isset($CFG['hooks']['assessment/publicquestion'])) {
		require(__DIR__.'/../'.$CFG['hooks']['assessment/publicquestion']);
	}

	//check if the question is in a public library
	$query = 'SELECT LI.id FROM imas_library_items LI INNER JOIN imas_libraries LIB ON LI.libid = LIB.id WHERE public = 1 AND qsetid = :qid';
	$stm = $DBH->prepare($query);
	$stm->execute(array(':qid'=>$qid));
	if($stm->rowCount() == 0){
		echo _('The requested question is not public. Please contact your instructor.');
		exit;
	}

	if (!isset($CFG['TE']['navicons'])) {
		 $CFG['TE']['navicons'] = array(
			 'untried'=>'te_blue_arrow.png',
			 'canretrywrong'=>'te_red_redo.png',
			 'canretrypartial'=>'te_yellow_redo.png',
			 'noretry'=>'te_blank.gif',
			 'correct'=>'te_green_check.png',
			 'wrong'=>'te_red_ex.png',
			 'partial'=>'te_yellow_check.png');

	}
	if (isset($instrPreviewId)) {
		$teacherid=$instrPreviewId;
	}

	$actas = false;
	$isreview = true;
	
	
	$useexception = false;
	$inexception = false;
	$exceptionduedate = 0;
	$locationdata = Array();
	$showeachscore = true;
	$allowregen = true;
	$immediatereattempt = true;
	$testsettings['displaymethod'] = "SkipAround";

	include("displayq2.php");
	include("testutil.php");
	include("asidutil.php");

	//error_reporting(0);  //prevents output of error messages

	
	$testid = 0;
    $asid = 0;

	

	//if submitting, verify it's the correct assessment
	if (isset($_POST['asidverify']) && $_POST['asidverify']!=$testid) {
		echo "<html><body>", _('Error.  It appears you have opened another assessment since you opened this one. ');
		echo _('Only one open assessment can be handled at a time. Please reopen the assessment and try again. ');
		echo "<a href=\"../course/course.php?cid={$testsettings['courseid']}\">", _('Return to course page'), "</a>";
		echo '</body></html>';
		exit;
	}
	//verify group is ok
	if ($testsettings['isgroup']>0 && !$isteacher &&  ($line['agroupid']==0 || ($sessiondata['groupid']>0 && $line['agroupid']!=$sessiondata['groupid']))) {
		echo "<html><body>", _('Error.  Looks like your group has changed for this assessment. Please reopen the assessment and try again.');
		echo "<a href=\"../course/course.php?cid={$testsettings['courseid']}\">", _('Return to course page'), "</a>";
		echo '</body></html>';
		exit;
	}
	
	$ltiexception = false;

	//}
	$superdone = false;
	
	srand();

	$allowregen = true;
	$showeachscore = true;
	$noindivscores = false;
	$reviewatend = false;
	$reattemptduring = true;
	$showhints = true;
	$showtips = 2;
	$useeqnhelper = 4;
	$regenonreattempt = false;
	
    

	$reloadqi = false;
	if (isset($_GET['reattempt'])) {
		if ($_GET['reattempt']=="all") {
			for ($i = 0; $i<count($questions); $i++) {
				if ($attempts[$i]<$qi[$questions[$i]]['attempts'] || $qi[$questions[$i]]['attempts']==0) {
					//$scores[$i] = -1;
					if ($noindivscores && !$reattemptduring) { //clear scores if could have viewed
						$bestscores[$i] = -1;
						$bestrawscores[$i] = -1;
					}
					if (!in_array($i,$reattempting)) {
						$reattempting[] = $i;
					}
					if (($regenonreattempt && $qi[$questions[$i]]['regen']==0) || $qi[$questions[$i]]['regen']==1) {
						if ($noindivscores) {
							$lastanswers[$i] = '';
							$scores[$i] = -1;
						}
						if (($testsettings['shuffle']&4)==4) {
							//all stu same seed; don't change seed
						} else if (($testsettings['shuffle']&2)==2 && $i>0) {  //all q same seed
							$seeds[$i] = $seeds[0];
						} else if ($qi[$questions[$i]]['fixedseeds'] !== null && $qi[$questions[$i]]['fixedseeds'] != '') {
							$fs = explode(',',$qi[$questions[$i]]['fixedseeds']);
							if (count($fs)>1) {
								//find existing seed and use next one
								$k = array_search($seeds[$i], $fs);
								$seeds[$i] = $fs[($k+1)%count($fs)];
							}
						} else {
							$seeds[$i] = rand(1,9999);
						}
						if (!$isreview) {
							if (newqfromgroup($i)) {
								$reloadqi = true;
							}
						}
						if (isset($qi[$questions[$i]]['answeights'])) {
							$reloadqi = true;
						}
					}
				}
			}
		} else if ($_GET['reattempt']=="canimprove") {
			$remainingposs = getallremainingpossible($qi,$questions,$testsettings,$attempts);
			for ($i = 0; $i<count($questions); $i++) {
				if ($attempts[$i]<$qi[$questions[$i]]['attempts'] || $qi[$questions[$i]]['attempts']==0) {
					if ($noindivscores || getpts($scores[$i])<$remainingposs[$i]) {
						//$scores[$i] = -1;
						if (!in_array($i,$reattempting)) {
							$reattempting[] = $i;
						}
						if (($regenonreattempt && $qi[$questions[$i]]['regen']==0) || $qi[$questions[$i]]['regen']==1) {
							if ($qi[$questions[$i]]['fixedseeds'] !== null && $qi[$questions[$i]]['fixedseeds'] != '') {
								$fs = explode(',',$qi[$questions[$i]]['fixedseeds']);
								if (count($fs)>1) {
									//find existing seed and use next one
									$k = array_search($seeds[$i], $fs);
									$seeds[$i] = $fs[($k+1)%count($fs)];
								}
							} else {
								$seeds[$i] = rand(1,9999);
							}
							if (!$isreview) {
								if (newqfromgroup($i)) {
									$reloadqi = true;
								}
							}
							if (isset($qi[$questions[$i]]['answeights'])) {
								$reloadqi = true;
							}
						}
					}
				}
			}
		} else {
			$toclear = $_GET['reattempt'];
			
		}
	}
	if (isset($_GET['regen']) && $allowregen && $qi[$questions[$_GET['regen']]]['allowregen']==1) {
		if (!isset($sessiondata['regendelay'])) {
			$sessiondata['regendelay'] = 2;
		}
		$doexit = false;
		if (isset($sessiondata['lastregen'])) {
			if ($now-$sessiondata['lastregen']<$sessiondata['regendelay']) {
				$sessiondata['regendelay'] = 5;
				echo '<html><body><p>Hey, about slowing down and trying the problem before hitting regen?  Wait 5 seconds before trying again.</p><p></body></html>';
				$stm = $DBH->prepare("INSERT INTO imas_log (time,log) VALUES (:time, :log)");
				$stm->execute(array(':time'=>$now, ':log'=>"Quickregen triggered by $userid"));
				if (!isset($sessiondata['regenwarnings'])) {
					$sessiondata['regenwarnings'] = 1;
				} else {
					$sessiondata['regenwarnings']++;
				}
				if ($sessiondata['regenwarnings']>10) {
					$stm = $DBH->prepare("INSERT INTO imas_log (time,log) VALUES (:time, :log)");
					$stm->execute(array(':time'=>$now, ':log'=>"Over 10 regen warnings triggered by $userid"));
				}
				$doexit = true;
			}
			if ($now - $sessiondata['lastregen'] > 20) {
				$sessiondata['regendelay'] = 2;
			}
		}
		$sessiondata['lastregen'] = $now;
		writesessiondata();
		if ($doexit) { exit;}
		srand();
		$toregen = $_GET['regen'];

		if ($qi[$questions[$toregen]]['fixedseeds'] !== null && $qi[$questions[$toregen]]['fixedseeds'] != '') {
			$fs = explode(',',$qi[$questions[$toregen]]['fixedseeds']);
			if (count($fs)>1) {
				//find existing seed and use next one
				$k = array_search($seeds[$toregen], $fs);
				$seeds[$toregen] = $fs[($k+1)%count($fs)];
			}
		} else {
			$seeds[$toregen] = rand(1,9999);
		}

		$scores[$toregen] = -1;
		$rawscores[$toregen] = -1;
		$attempts[$toregen] = 0;
		$newla = array();
		deletefilesifnotused($lastanswers[$toregen],$bestlastanswers[$toregen]);
		$laarr = explode('##',$lastanswers[$toregen]);
		foreach ($laarr as $lael) {
			if ($lael=="ReGen") {
				$newla[] = "ReGen";
			}
		}
		$newla[] = "ReGen";
		$lastanswers[$toregen] = implode('##',$newla);
		$loc = array_search($toregen,$reattempting);
		if ($loc!==false) {
			array_splice($reattempting,$loc,1);
		}
		if (!$isreview) {
			if (newqfromgroup($toregen)) {
				$reloadqi = true;
			}
		}
		if (isset($qi[$questions[$toregen]]['answeights'])) {
			$reloadqi = true;
		}
	}
	if (isset($_GET['regenall']) && $allowregen) {
		srand();
		if ($_GET['regenall']=="missed") {
			for ($i = 0; $i<count($questions); $i++) {
				if (getpts($scores[$i])<$qi[$questions[$i]]['points'] && $qi[$questions[$i]]['allowregen']==1) {
					$scores[$i] = -1;
					$rawscores[$i] = -1;
					$attempts[$i] = 0;
					if ($qi[$questions[$i]]['fixedseeds'] !== null && $qi[$questions[$i]]['fixedseeds'] != '') {
						$fs = explode(',',$qi[$questions[$i]]['fixedseeds']);
						if (count($fs)>1) {
							//find existing seed and use next one
							$k = array_search($seeds[$i], $fs);
							$seeds[$i] = $fs[($k+1)%count($fs)];
						}
					} else {
						$seeds[$i] = rand(1,9999);
					}
					$newla = array();
					deletefilesifnotused($lastanswers[$i],$bestlastanswers[$i]);
					$laarr = explode('##',$lastanswers[$i]);
					foreach ($laarr as $lael) {
						if ($lael=="ReGen") {
							$newla[] = "ReGen";
						}
					}
					$newla[] = "ReGen";
					$lastanswers[$i] = implode('##',$newla);
					$loc = array_search($i,$reattempting);
					if ($loc!==false) {
						array_splice($reattempting,$loc,1);
					}
					if (isset($qi[$questions[$i]]['answeights'])) {
						$reloadqi = true;
					}
				}
			}
		} else if ($_GET['regenall']=="all") {
			for ($i = 0; $i<count($questions); $i++) {
				if ($qi[$questions[$i]]['allowregen']==0) {
					continue;
				}
				$scores[$i] = -1;
				$rawscores[$i] = -1;
				$attempts[$i] = 0;
				if (($testsettings['shuffle']&4)==4) {
					//all stu same seed; don't change seed
				} else if (($testsettings['shuffle']&2)==2 && $i>0) {  //all q same seed
					$seeds[$i] = $seeds[0];
				} else if ($qi[$questions[$i]]['fixedseeds'] !== null && $qi[$questions[$i]]['fixedseeds'] != '') {
					$fs = explode(',',$qi[$questions[$i]]['fixedseeds']);
					if (count($fs)>1) {
						//find existing seed and use next one
						$k = array_search($seeds[$i], $fs);
						$seeds[$i] = $fs[($k+1)%count($fs)];
					}
				} else {
					$seeds[$i] = rand(1,9999);
				}
				$newla = array();
				deletefilesifnotused($lastanswers[$i],$bestlastanswers[$i]);
				$laarr = explode('##',$lastanswers[$i]);
				foreach ($laarr as $lael) {
					if ($lael=="ReGen") {
						$newla[] = "ReGen";
					}
				}
				$newla[] = "ReGen";
				$lastanswers[$i] = implode('##',$newla);
				$reattempting = array();
				if (isset($qi[$questions[$i]]['answeights'])) {
					$reloadqi = true;
				}
			}
		} else if ($_GET['regenall']=="fromscratch" && $testsettings['testtype']=="Practice" && !$isreview) {
			require_once("../includes/filehandler.php");
			//deleteasidfilesbyquery(array('userid'=>$userid,'assessmentid'=>$testsettings['id']),1);
			deleteasidfilesbyquery2('userid',$userid,$testsettings['id'],1);
			$stm = $DBH->prepare("DELETE FROM imas_assessment_sessions WHERE userid=:userid AND assessmentid=:assessmentid LIMIT 1");
			$stm->execute(array(':userid'=>$userid, ':assessmentid'=>$testsettings['id']));
			header('Location: ' . $GLOBALS['basesiteurl'] . "/assessment/publicquestion.php?cid={$testsettings['courseid']}&id={$testsettings['id']}");
			exit;
		}


	}
	if (isset($_GET['jumptoans']) && $testsettings['showans']==='J') {
		$tojump = $_GET['jumptoans'];
		$attempts[$tojump]=$qi[$questions[$tojump]]['attempts'];
		if ($scores[$tojump]<0){
			$scores[$tojump] = 0;
			$rawscores[$tojump] = 0;
		}
		$reloadqi = true;
	}

	if ($reloadqi) {
		$qi = getquestioninfo($questions,$testsettings);
	}

    
	$isdiag = true;
	if ($isdiag) {
		$diagid = $sessiondata['isdiag'];
		$hideAllHeaderNav = true;
	}
	$isltilimited = (isset($sessiondata['ltiitemtype']) && $sessiondata['ltiitemtype']==0 && $sessiondata['ltirole']=='learner');


	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	$useeditor = 1;
if (!isset($_REQUEST['embedpostback']) && empty($_POST['backgroundsaveforlater'])) {

	$cid = $testsettings['courseid'];
	
	if ($sessiondata['intreereader']) {
		$flexwidth = true;
	}
	require("header.php");
	if ($testsettings['noprint'] == 1) {
		echo '<style type="text/css" media="print"> div.question, div.todoquestion, div.inactive { display: none;} </style>';
	}

	if (!$isdiag && !$isltilimited && !$sessiondata['intreereader']) {
		if (isset($sessiondata['actas'])) {
			echo "<div class=breadcrumb>$breadcrumbbase <a href=\"../course/course.php?cid={$testsettings['courseid']}\">{$sessiondata['coursename']}</a> ";
			echo "&gt; <a href=\"../course/gb-viewasid.php?cid={$testsettings['courseid']}&amp;asid=$testid&amp;uid={$sessiondata['actas']}\">", _('Gradebook Detail'), "</a> ";
			echo "&gt; ", _('View as student'), "</div>";
		} else {
			echo "<div class=breadcrumb>";
			//echo "<span style=\"float:right;\" class=\"hideinmobile\">$userfullname</span>";
			if (!isset($usernameinheader) || $usernameinheader==false) {
			}
			if (isset($sessiondata['ltiitemtype']) && $sessiondata['ltiitemtype']==0) {
				echo "$breadcrumbbase ", _('Assessment'), "</div>";
			} else {
				if(isset($sessiondata['ltiitemtype'])){
					echo "$breadcrumbbase <span href=\"../course/course.php?cid={$testsettings['courseid']}\">{$sessiondata['coursename']}</span> ";
				} else{
					echo "$breadcrumbbase <a href=\"../course/course.php?cid={$testsettings['courseid']}\">{$sessiondata['coursename']}</a> ";
				}

				echo "&gt; ", _('Assessment'), "</div>";
			}
		}
	} 
	if ((!$sessiondata['isteacher'] || isset($sessiondata['actas'])) && ($testsettings['isgroup']==1 || $testsettings['isgroup']==2) && ($sessiondata['groupid']==0 || isset($_GET['addgrpmem']))) {
		if (isset($_POST['grpsubmit'])) {
			if ($sessiondata['groupid']==0) {
				echo '<p>', _('Group error - lost group info'), '</p>';
			}
			$fieldstocopy = 'assessmentid,agroupid,questions,seeds,scores,attempts,lastanswers,starttime,endtime,bestseeds,bestattempts,bestscores,bestlastanswers,feedback,reviewseeds,reviewattempts,reviewscores,reviewlastanswers,reattempting,reviewreattempting,ver';
			$stm = $DBH->prepare("SELECT $fieldstocopy FROM imas_assessment_sessions WHERE id=:id");
			$stm->execute(array(':id'=>$testid));
			$rowgrptest = $stm->fetch(PDO::FETCH_ASSOC);
			$loginfo = "$userfullname creating group. ";
			if (isset($CFG['GEN']['newpasswords'])) {
				require_once("../includes/password.php");
			}
			for ($i=1;$i<$testsettings['groupmax'];$i++) {
				if (isset($_POST['user'.$i]) && $_POST['user'.$i]!=0) {
					$stm = $DBH->prepare("SELECT password,LastName,FirstName FROM imas_users WHERE id=:id");
					$stm->execute(array(':id'=>$_POST['user'.$i]));
					$thisuser = $stm->fetch(PDO::FETCH_ASSOC);
					$thisusername = $thisuser['FirstName'] . ' ' . $thisuser['LastName'];
					if ($testsettings['isgroup']==1) {
						$actualpw = $thisuser['password'];
						$md5pw = md5($_POST['pw'.$i]);
						if (!($actualpw==$md5pw || (isset($CFG['GEN']['newpasswords']) && password_verify($_POST['pw'.$i],$actualpw)))) {
							echo "<p>" . Sanitize::encodeStringForDisplay($thisusername) . ": ", _('password incorrect'), "</p>";
							$errcnt++;
							continue;
						}
					}

					$thisuser = $_POST['user'.$i];
					$stm = $DBH->prepare("SELECT id,agroupid FROM imas_assessment_sessions WHERE userid=:userid AND assessmentid=:assessmentid ORDER BY id LIMIT 1");
					$stm->execute(array(':userid'=>$_POST['user'.$i], ':assessmentid'=>$testsettings['id']));
					if ($stm->rowCount()>0) {
						$row = $stm->fetch(PDO::FETCH_NUM);
						if ($row[1]>0) {
							echo "<p>", _(sprintf('%s already has a group.  No change made'), Sanitize::encodeStringForDisplay($thisusername)), "</p>";
							$loginfo .= "$thisusername already in group. ";
						} else {
							$stm = $DBH->prepare("INSERT INTO imas_stugroupmembers (userid,stugroupid) VALUES (:userid,:stugroupid)");
							$stm->execute(array(':userid'=>$_POST['user'.$i], ':stugroupid'=>$sessiondata['groupid']));

							$fieldstocopy = explode(',',$fieldstocopy);
							$sets = array();
							foreach ($fieldstocopy as $k=>$val) {
								$sets[] = "$val=:$val";
							}
							$setslist = implode(',',$sets);
							$stm = $DBH->prepare("UPDATE imas_assessment_sessions SET $setslist WHERE id=:id");
							$stm->execute(array(':id'=>$row[0]) + $rowgrptest);

							//$query = "UPDATE imas_assessment_sessions SET assessmentid='{$rowgrptest[0]}',agroupid='{$rowgrptest[1]}',questions='{$rowgrptest[2]}'";
							//$query .= ",seeds='{$rowgrptest[3]}',scores='{$rowgrptest[4]}',attempts='{$rowgrptest[5]}',lastanswers='{$rowgrptest[6]}',";
							//$query .= "starttime='{$rowgrptest[7]}',endtime='{$rowgrptest[8]}',bestseeds='{$rowgrptest[9]}',bestattempts='{$rowgrptest[10]}',";
							//$query .= "bestscores='{$rowgrptest[11]}',bestlastanswers='{$rowgrptest[12]}'  WHERE id='{$row[0]}'";
							//$query = "UPDATE imas_assessment_sessions SET agroupid='$agroupid' WHERE id='{$row[0]}'";
							echo "<p>", _(sprintf('%s added to group, overwriting existing attempt.'), Sanitize::encodeStringForDisplay($thisusername)), "</p>";
							$loginfo .= "$thisusername switched to group. ";
						}
					} else {
						$stm = $DBH->prepare("INSERT INTO imas_stugroupmembers (userid,stugroupid) VALUES (:userid,:stugroupid)");
						$stm->execute(array(':userid'=>$_POST['user'.$i], ':stugroupid'=>$sessiondata['groupid']));

						$fieldphs = ':'.implode(',:', explode(',', $fieldstocopy));
						$query = "INSERT INTO imas_assessment_sessions (userid,$fieldstocopy) VALUES (:userid,$fieldphs)";
						$stm = $DBH->prepare($query);
						$stm->execute(array(':userid'=>$_POST['user'.$i]) + $rowgrptest);
						echo "<p>", _(sprintf('%s added to group.'), Sanitize::encodeStringForDisplay($thisusername)), "</p>";
						$loginfo .= "$thisusername added to group. ";
					}
				}
			}
			$now = time();
			if (isset($GLOBALS['CFG']['log'])) {
				$stm = $DBH->prepare("INSERT INTO imas_log (time,log) VALUES (:time, :log)");
				$stm->execute(array(':time'=>$now, ':log'=>$loginfo));
			}
		} else {
			echo '<div id="headershowtest" class="pagetitle"><h1>', _('Select group members'), '</h1></div>';
			if ($sessiondata['groupid']==0) {
				//a group should already exist
				$query = 'SELECT i_sg.id FROM imas_stugroups as i_sg JOIN imas_stugroupmembers as i_sgm ON i_sg.id=i_sgm.stugroupid ';
				$query .= "WHERE i_sgm.userid=:userid AND i_sg.groupsetid=:groupsetid";
				$stm = $DBH->prepare($query);
				$stm->execute(array(':userid'=>$userid, ':groupsetid'=>$testsettings['groupsetid']));
				if ($stm->rowCount()==0) {
					echo '<p>', _('Group error.  Please try reaccessing the assessment from the course page'), '</p>';
				}
				$agroupid = $stm->fetchColumn(0);
				$sessiondata['groupid'] = $agroupid;
				writesessiondata();
			} else {
				$agroupid = $sessiondata['groupid'];
			}


			echo _('Current Group Members:'), " <ul>";
			$curgrp = array();
			$query = "SELECT imas_users.id,imas_users.FirstName,imas_users.LastName FROM imas_users,imas_stugroupmembers WHERE ";
			$query .= "imas_users.id=imas_stugroupmembers.userid AND imas_stugroupmembers.stugroupid=:stugroupid ORDER BY imas_users.LastName,imas_users.FirstName";
			$stm = $DBH->prepare($query);
			$stm->execute(array(':stugroupid'=>$sessiondata['groupid']));
			while ($row = $stm->fetch(PDO::FETCH_NUM)) {
				$curgrp[0] = $row[0];
				echo sprintf("<li>%s, %s</li>", Sanitize::encodeStringForDisplay($row[2]), Sanitize::encodeStringForDisplay($row[1]));
			}
			echo "</ul>";

			$curinagrp = array();
			$query = 'SELECT i_sgm.userid FROM imas_stugroups as i_sg JOIN imas_stugroupmembers as i_sgm ON i_sg.id=i_sgm.stugroupid ';
			$query .= "WHERE i_sg.groupsetid=:groupsetid";
			$stm = $DBH->prepare($query);
			$stm->execute(array(':groupsetid'=>$testsettings['groupsetid']));
			while ($row = $stm->fetch(PDO::FETCH_NUM)) {
				$curinagrp[] = $row[0];
			}
			$curids = array_map('intval', $curinagrp);
			$curids_query_placeholders = Sanitize::generateQueryPlaceholders($curids);
			$selops = '<option value="0">' . _('Select a name..') . '</option>';
			$query = "SELECT imas_users.id,imas_users.FirstName,imas_users.LastName FROM imas_users,imas_students ";
			$query .= "WHERE imas_users.id=imas_students.userid AND imas_students.courseid=? ";
			$query .= "AND imas_users.id NOT IN ($curids_query_placeholders) ORDER BY imas_users.LastName,imas_users.FirstName";
			$stm = $DBH->prepare($query);
			$stm->execute(array_merge(array($testsettings['courseid']), $curids));
			while ($row = $stm->fetch(PDO::FETCH_NUM)) {
				$selops .= sprintf('<option value="%d">%s, %s</option>', $row[0], Sanitize::encodeStringForDisplay($row[2]), Sanitize::encodeStringForDisplay($row[1]));
			}
			//TODO i18n
			echo '<p>';
			if ($testsettings['isgroup']==1) {
				echo _('Each group member (other than the currently logged in student) to be added should select their name and enter their password here.');
			} else {
				echo _('Each group member (other than the currently logged in student) to be added should select their name here.');
			}
			echo '</p>';
			echo '<form method="post" enctype="multipart/form-data" action="publicquestion.php?addgrpmem=true">';
			echo "<input type=\"hidden\" name=\"asidverify\" value=\"$testid\" />";
			echo '<input type="hidden" name="disptime" value="'.time().'" />';
			echo "<input type=\"hidden\" name=\"isreview\" value=\"". ($isreview?1:0) ."\" />";
			for ($i=1;$i<$testsettings['groupmax']-count($curgrp)+1;$i++) {
				echo '<br />', _('Username'), ': <select name="user'.$i.'">'.$selops.'</select> ';
				if ($testsettings['isgroup']==1) {
					echo _('Password'), ': <input type="password" name="pw'.$i.'" autocomplete="off"/>'."\n";
				}
			}
			echo '<p><input type=submit name="grpsubmit" value="', _('Record Group and Continue'), '"/></p>';
			echo '</form>';
			require("../footer.php");
			writesessiondata();
			exit;
		}
	}
	/*
	no need to do anything in this case
	if ((!$sessiondata['isteacher'] || isset($sessiondata['actas'])) && $testsettings['isgroup']==3  && $sessiondata['groupid']==0) {
		//double check not already added to group by someone else
		$query = "SELECT agroupid FROM imas_assessment_sessions WHERE id='$testid'";
		$result = mysql_query($query) or die("Query failed : $query:" . mysql_error());
		$agroupid = mysql_result($result,0,0);
		if ($agroupid==0) { //really has no group, create group
			$query = "UPDATE imas_assessment_sessions SET agroupid='$testid' WHERE id='$testid'";
			mysql_query($query) or die("Query failed : $query:" . mysql_error());
			$agroupid = $testid;
		} else {
			echo "<p>Someone already added you to a group.  Using that group.</p>";
		}
		$sessiondata['groupid'] = $agroupid;
		writesessiondata();
	}
	*/

	//if was added to existing group, need to reload $questions, etc
	echo '<div id="headershowtest" class="pagetitle">';
	echo "<h1>{$testsettings['name']}</h1></div>\n";
	if (isset($sessiondata['actas'])) {
		echo '<p style="color: red;">', _('Teacher Acting as ');
		$stm = $DBH->prepare("SELECT LastName, FirstName FROM imas_users WHERE id=:id");
		$stm->execute(array(':id'=>$sessiondata['actas']));
		$row = $stm->fetch(PDO::FETCH_NUM);
		echo $row[1].' '.$row[0];
		echo '<p>';
	}
	echo '<div class="clear"></div>';

	if ($testsettings['testtype']=="Practice" && !$isreview) {
		echo "<div class=right><span style=\"color:#f00\">" . _("Practice Assessment") . ".</span>  <a href=\"publicquestion.php?regenall=fromscratch\">", _('Create new version.'), "</a></div>";
	}

	$restrictedtimelimit = false;
} else {
	require_once("../filter/filter.php");
}
	//identify question-specific  intro/instruction
	//comes in format [Q 1-3] in intro
	$introhaspages = false;
	if (strpos($testsettings['intro'],'[Q')!==false) {
		$testsettings['intro'] = preg_replace('/((<span|<strong|<em)[^>]*>)?\[Q\s+(\d+(\-(\d+))?)\s*\]((<\/span|<\/strong|<\/em)[^>]*>)?/','[Q $3]',$testsettings['intro']);
		if(preg_match_all('/\<p[^>]*>\s*\[Q\s+(\d+)(\-(\d+))?\s*\]\s*<\/p>/',$testsettings['intro'],$introdividers,PREG_SET_ORDER)) {
			$intropieces = preg_split('/\<p[^>]*>\s*\[Q\s+(\d+)(\-(\d+))?\s*\]\s*<\/p>/',$testsettings['intro']);
			foreach ($introdividers as $k=>$v) {
				if (count($v)==4) {
					$introdividers[$k][2] = $v[3];
				} else if (count($v)==2) {
					$introdividers[$k][2] = $v[1];
				}
			}
			$testsettings['intro'] = array_shift($intropieces);
		}
		$introhaspages = ($testsettings['displaymethod'] == "Embed" && strpos($testsettings['intro'],'[PAGE')!==false);
	} else if (count($introjson)>1) {
		$intropieces = array();
		$introdividers = array();
		$lastdisplaybefore = -1;
		$textsegcnt = -1;
		for ($i=1;$i<count($introjson);$i++) {
			if (isset($introjson[$i]['ispage']) && $introjson[$i]['ispage']==1 && $testsettings['displaymethod'] == "Embed") {
				$introjson[$i]['text'] = '[PAGE '.strip_tags(str_replace(array("\n","\r","]"),array(' ',' ','&#93;'), $introjson[$i]['pagetitle'])).']'.$introjson[$i]['text'];
				$introhaspages = true;
	}
			if ($introjson[$i]['displayBefore'] == $lastdisplaybefore) {
				$intropieces[$textsegcnt] .= $introjson[$i]['text'];
			} else {
				$textsegcnt++;
				if (!isset($introjson[$i]['forntype'])) {$introjson[$i]['forntype'] = 0;}
				$introdividers[$textsegcnt] = array(0,$introjson[$i]['displayBefore']+1, $introjson[$i]['displayUntil']+1, $introjson[$i]['forntype']);
				$intropieces[$textsegcnt] = $introjson[$i]['text'];
			}

			$lastdisplaybefore = $introjson[$i]['displayBefore'];
		}
	} else {
		$introhaspages = ($testsettings['displaymethod'] == "Embed" && strpos($testsettings['intro'],'[PAGE')!==false);
	}
	if (isset($_GET['action'])) {
		if (($_GET['action']=="skip" || $_GET['action']=="seq") && trim($testsettings['intro'])!='') {
			echo '<div class="right"><a href="#" aria-controls="intro" aria-expanded="false" onclick="togglemainintroshow(this);return false;">'._("Show Intro/Instructions").'</a></div>';
			//echo "<div class=right><span onclick=\"document.getElementById('intro').className='intro';\"><a href=\"#\">", _('Show Instructions'), "</a></span></div>\n";
		}
		if ($_GET['action']=="skip") {

			if (isset($_GET['score'])) { //score a problem
				$qn = $_GET['score'];

				if ($_POST['verattempts']!=$attempts[$qn]) {
					echo "<p>", _('This question has been submittted since you viewed it, and that grade is shown below.  Your answer just submitted was not scored.'), "</p>";
				} else {
					if (isset($_POST['disptime']) && !$isreview) {
						$used = $now - intval($_POST['disptime']);
						$timesontask[$qn] .= (($timesontask[$qn]=='') ? '':'~').$used;
					}
					$GLOBALS['scoremessages'] = '';
					$GLOBALS['questionmanualgrade'] = false;
					$rawscore = scorequestion($qn);

					$immediatereattempt = true;
					
					//record score
					//recordtestdata();
				}
			   if (!$superdone) {
				echo filter("<div id=intro role=region aria-label=\""._('Intro or instructions!!')."\" class=hidden aria-hidden=true aria-expanded=false>{$testsettings['intro']}</div>\n");
				//$lefttodo = shownavbar($questions,$scores,$qn,$testsettings['showcat'],$testsettings['extrefs']);

				echo "<div>\n";
				echo "<div class=\"screenreader\" id=\"beginquestions\">"._('Start of Questions')."</div>\n";
				if ($GLOBALS['scoremessages'] != '') {
					echo '<p>'.$GLOBALS['scoremessages'].'</p>';
				}

				if ($showeachscore) {
					$possible = $qi[$questions[$qn]]['points'];
					echo "<p>";
					echo _('Score on last attempt: ');
					echo printscore($scores[$qn],$qn);
					echo "</p>\n";
					if ($GLOBALS['questionmanualgrade'] == true) {
						echo '<p><strong>', _('Note:'), '</strong> ', _('This question contains parts that can not be auto-graded.  Those parts will count as a score of 0 until they are graded by your instructor'), '</p>';
					}


				} else {
					echo '<p>'._('Question Scored').'</p>';
				}

				$reattemptsremain = false;
				if (hasreattempts($qn)) {
					$reattemptsremain = true;
				}

				if ($allowregen && $qi[$questions[$qn]]['allowregen']==1) {
					echo '<p>';
					
					if ($reattemptsremain && !$immediatereattempt && $reattemptduring) {
						echo "<a href=\"publicquestion.php?action=skip&amp;to=$qn&amp;reattempt=$qn\">", _('Reattempt last question'), "</a>, ";
					}
					$regenhref = $GLOBALS['basesiteurl'].'/assessment/'."publicquestion.php?action=skip&amp;to=$qn&amp;regen=$qn";
					echo '<button type=button onclick="window.location.href=\''.$regenhref.'\'">'._('Try another similar question').'</button>';
					//echo "<a href=\"publicquestion.php?action=skip&amp;to=$qn&amp;regen=$qn\">", _('Try another similar question'), "</a>";
					if ($immediatereattempt) {
						echo _(" or reattempt last question below.");
					}
					echo "</p>\n";
				} else if ($reattemptsremain && !$immediatereattempt && $reattemptduring) {
					echo "<p><a href=\"publicquestion.php?action=skip&amp;to=$qn&amp;reattempt=$qn\">", _('Reattempt last question'), "</a>";
					if ($lefttodo > 0) {
						echo  _(", or select another question");
					}
					echo '</p>';
				} else {
					if ($reattemptsremain && $immediatereattempt && $reattemptduring) {
						echo "<p>"._('Reattempt last question below, or select another question').'</p>';
					} else {
						echo "<p>"._('Select another question').'</p>';
					}
				}
				
				if ((!$reattemptsremain || $regenonreattempt) && $showeachscore && $testsettings['showans']!='N') {
					//TODO i18n
					unset($GLOBALS['nocolormark']);
					echo "<p>" . _("This question, with your last answer");
					if (($qi[$questions[$qn]]['showansafterlast'] && !$reattemptsremain) ||
							($qi[$questions[$qn]]['showansduring'] && $qi[$questions[$qn]]['showans']<=$attempts[$qn]) ||
							($qi[$questions[$qn]]['showans']=='R' && $regenonreattempt)) {
						echo _(" and correct answer");
						$showcorrectnow = true;
					} else {
						$showcorrectnow = false;
					}

					echo _(', is displayed below') . '</p>';
					if (!$noraw && $showeachscore && $GLOBALS['questionmanualgrade'] != true) {
						//$colors = scorestocolors($rawscores[$qn], '', $qi[$questions[$qn]]['answeights'], $noraw);
						if (strpos($rawscores[$qn],'~')!==false) {
							$colors = explode('~',$rawscores[$qn]);
						} else {
							$colors = array($rawscores[$qn]);
						}
					} else {
						$colors = array();
					}
					if ($showcorrectnow) {
						displayq($qn,$qi[$questions[$qn]]['questionsetid'],$seeds[$qn],2,false,$attempts[$qn],false,false,false,$colors);
					} else {
						displayq($qn,$qi[$questions[$qn]]['questionsetid'],$seeds[$qn],false,false,$attempts[$qn],false,false,false,$colors);
					}
					$contactlinks = showquestioncontactlinks($qn);
					if ($contactlinks!='' && !$sessiondata['istutorial']) {
						echo '<div class="review">'.$contactlinks.'</div>';
					}

				} else if ($immediatereattempt) {
					$next = $qn;
					if (isset($intropieces)) {
						foreach ($introdividers as $k=>$v) {
							if ($v[1]<=$next+1 && $next+1<=$v[2]) {//right divider
								if ($next+1==$v[1] || !empty($v[3])) {
									echo '<div><a href="#" id="introtoggle'.$k.'" onclick="toggleintroshow('.$k.'); return false;" aria-controls="intropiece'.$k.'" aria-expanded="true">';
									echo _('Hide Question Information'), '</a></div>';
									echo '<div class="intro" role=region aria-label="'._('Pre-question text').'" aria-expanded="true" id="intropiece'.$k.'">'.filter($intropieces[$k]).'</div>';
								} else {
									echo '<div><a href="#" id="introtoggle'.$k.'" onclick="toggleintroshow('.$k.'); return false;" aria-controls="intropiece'.$k.'" aria-expanded="false">';
									echo _('Show Question Information'), '</a></div>';
									echo '<div class="intro" role=region aria-label="'._('Pre-question text').'" aria-expanded="false" aria-hidden="true" style="display:none;" id="intropiece'.$k.'">'.filter($intropieces[$k]).'</div>';
								}
								break;
							}
						}
					}
					echo "<form id=\"qform\" method=\"post\" enctype=\"multipart/form-data\" action=\"publicquestion.php?action=skip&amp;score=$next\" onsubmit=\"return doonsubmit(this)\">\n";
					echo "<input type=\"hidden\" name=\"asidverify\" value=\"$testid\" />";
					echo '<input type="hidden" name="disptime" value="'.time().'" />';
					echo "<input type=\"hidden\" name=\"isreview\" value=\"". ($isreview?1:0) ."\" />";
					echo "<input type=\"hidden\" name=\"verattempts\" value=\"{$attempts[$next]}\" />";
					echo "<div class=\"screenreader\" id=\"beginquestions\">"._('Start of Questions')."</div>\n";
					basicshowq($next);
					showqinfobar($next,true,true);
					echo '<input type="submit" class="btn" value="'. _('Submit'). '" />';
					if ((($testsettings['showans']=='J' && $qi[$questions[$next]]['showans']=='0') || $qi[$questions[$next]]['showans']=='J') && $qi[$questions[$next]]['attempts']>0) {
						echo ' <input type="button" class="btn" value="', _('Jump to Answer'), '" onclick="if (confirm(\'', _('If you jump to the answer, you must generate a new version to earn credit'), '\')) {window.location = \'publicquestion.php?action=skip&amp;jumptoans='.$next.'&amp;to='.$next.'\'}"/>';
					}
					echo "</form>\n";

				}

				echo "</div>\n";
			    }
			} else if (isset($_GET['to'])) { //jump to a problem
				$next = $_GET['to'];       
				echo filter("<div id=intro role=region aria-label=\""._('Intro or instructions')."\"  class=hidden aria-hidden=true aria-expanded=false>{$testsettings['intro']}</div>\n");

				//$lefttodo = shownavbar($questions,$scores,$next,$testsettings['showcat'],$testsettings['extrefs']);
				if (unans($scores[$next]) || amreattempting($next)) {
					echo "<div >\n";
					if (isset($intropieces)) {
						foreach ($introdividers as $k=>$v) {
							if ($v[1]<=$next+1 && $next+1<=$v[2]) {//right divider
								if ($next+1==$v[1] || !empty($v[3])) {
									echo '<div><a href="#" id="introtoggle'.$k.'" onclick="toggleintroshow('.$k.'); return false;" aria-controls="intropiece'.$k.'" aria-expanded="true">';
									echo _('Hide Question Information'), '</a></div>';
									echo '<div class="intro" role=region aria-label="'._('Pre-question text').'" aria-expanded="true" id="intropiece'.$k.'">'.filter($intropieces[$k]).'</div>';
								} else {
									echo '<div><a href="#" id="introtoggle'.$k.'" onclick="toggleintroshow('.$k.'); return false;" aria-controls="intropiece'.$k.'" aria-expanded="false">';
									echo _('Show Question Information'), '</a></div>';
									echo '<div class="intro" role=region aria-label="'._('Pre-question text').'" aria-expanded="false" aria-hidden="true" style="display:none;" id="intropiece'.$k.'">'.filter($intropieces[$k]).'</div>';
								}
								break;
							}
						}
					}
					echo "<form id=\"qform\" method=\"post\" enctype=\"multipart/form-data\" action=\"publicquestion.php?action=skip&amp;score=$next\" onsubmit=\"return doonsubmit(this)\">\n";
					echo "<input type=\"hidden\" name=\"asidverify\" value=\"$testid\" />";
					echo '<input type="hidden" name="disptime" value="'.time().'" />';
					echo "<input type=\"hidden\" name=\"isreview\" value=\"". ($isreview?1:0) ."\" />";
					echo "<input type=\"hidden\" name=\"verattempts\" value=\"{$attempts[$next]}\" />";
					echo "<div class=\"screenreader\" id=\"beginquestions\">"._('Start of Questions')."</div>\n";
					basicshowq($next);
					showqinfobar($next,true,true);
					echo '<input type="submit" class="btn" value="'. _('Submit'). '" />';
					if ((($testsettings['showans']=='J' && $qi[$questions[$next]]['showans']=='0') || $qi[$questions[$next]]['showans']=='J') && $qi[$questions[$next]]['attempts']>0) {
						echo ' <input type="button" class="btn" value="', _('Jump to Answer'), '" onclick="if (confirm(\'', _('If you jump to the answer, you must generate a new version to earn credit'), '\')) {window.location = \'publicquestion.php?action=skip&amp;jumptoans='.$next.'&amp;to='.$next.'\'}"/>';
					}
					echo "</form>\n";
					if (isset($intropieces) && $next==count($questions)-1) {
						foreach ($introdividers as $k=>$v) {
							if ($v[1]==$next+2) {//right divider
								echo '<div><a href="#" id="introtoggle'.$k.'" onclick="toggleintroshow('.$k.'); return false;" aria-controls="intropiece'.$k.'" aria-expanded="true">';
								echo _('Hide Question Information'), '</a></div>';
								echo '<div class="intro" role=region aria-label="'._('Pre-question text').'" aria-expanded="true" id="intropiece'.$k.'">'.filter($intropieces[$k]).'</div>';								
							}
						}
					}
					echo "</div>\n";
				} else {
					echo "<div class=inset>\n";
					echo "<div class=\"screenreader\" id=\"beginquestions\">"._('Start of Questions')."</div>\n";
					if (!isset($_GET['jumptoans'])) {
						echo _("You've already done this problem."), "\n";
					}
					$reattemptsremain = false;
					if ($showeachscore) {
						$possible = $qi[$questions[$next]]['points'];
						echo "<p>", _('Score on last attempt: ');
						echo printscore($scores[$next],$next);
						echo "</p>\n";
					}
					if (hasreattempts($next)) {
						if ($reattemptduring) {
							echo "<p><a href=\"publicquestion.php?action=skip&amp;to=$next&amp;reattempt=$next\">", _('Reattempt this question'), "</a></p>\n";
						}
						$reattemptsremain = true;
					}
					if ($allowregen && $qi[$questions[$next]]['allowregen']==1) {
						$regenhref = $GLOBALS['basesiteurl'].'/assessment/'."publicquestion.php?action=skip&amp;to=$next&amp;regen=$next";
						echo '<p><button type=button onclick="window.location.href=\''.$regenhref.'\'">'._('Try another similar question').'</button></p>';
						//echo "<p><a href=\"publicquestion.php?action=skip&amp;to=$next&amp;regen=$next\">", _('Try another similar question'), "</a></p>\n";
					}
					if ($lefttodo == 0 && $testsettings['testtype']!="NoScores") {
						echo "<a href=\"publicquestion.php?action=skip&amp;done=true\">", _('When you are done, click here to see a summary of your score'), "</a>\n";
					}
					if ($testsettings['showans']!='N') {// && $showeachscore) {  //(!$reattemptsremain || $regenonreattempt) &&
						unset($GLOBALS['nocolormark']);
						echo "<p>", _('Question with last attempt is displayed for your review only'), "</p>";

						if (!$noraw && $showeachscore) {
							//$colors = scorestocolors($rawscores[$next], '', $qi[$questions[$next]]['answeights'], $noraw);
							if (strpos($rawscores[$next],'~')!==false) {
								$colors = explode('~',$rawscores[$next]);
							} else {
								$colors = array($rawscores[$next]);
							}
						} else {
							$colors = array();
						}
						$qshowans = (($qi[$questions[$next]]['showansafterlast'] && !$reattemptsremain) ||
								($qi[$questions[$next]]['showansduring'] && $attempts[$next]>=$qi[$questions[$next]]['showans']) ||
								($qi[$questions[$next]]['showans']=='R' && $regenonreattempt));
						if ($qshowans) {
							displayq($next,$qi[$questions[$next]]['questionsetid'],$seeds[$next],2,false,$attempts[$next],false,false,false,$colors);
						} else {
							displayq($next,$qi[$questions[$next]]['questionsetid'],$seeds[$next],false,false,$attempts[$next],false,false,false,$colors);
						}
						$contactlinks = showquestioncontactlinks($next);
						if ($contactlinks!='') {
							echo '<div class="review">'.$contactlinks.'</div>';
						}
					}
					echo "</div>\n";
				}
			}
			if (isset($_GET['done'])) { //are all done

				$shown = showscores($questions,$attempts,$testsettings);
				endtest($testsettings);
				if ($shown) {leavetestmsg();}
			}
		} 
	} else { //starting test display
		$canimprove = false;
		$hasreattempts = false;
		$ptsearned = 0;
		$perfectscore = false;

		for ($j=0; $j<count($questions);$j++) {
			$canimproveq[$j] = canimprove($j);
			$hasreattemptsq[$j] = hasreattempts($j);
			if ($canimproveq[$j]) {
				$canimprove = true;
			}
			if ($hasreattemptsq[$j]) {
				$hasreattempts = true;
			}
			$ptsearned += getpts($scores[$j]);
		}
		if ($testsettings['timelimit']>0 && !$isreview && !$superdone && $remaining < 0) {
			echo '<script type="text/javascript">';
			echo 'initstack.push(function() {';
			if ($timelimitkickout) {
				echo 'alert("', _('Your time limit has expired.  If you try to submit any questions, your submissions will be rejected.'), '");';
			} else {
				echo 'alert("', _('Your time limit has expired.  If you submit any questions, your assessment will be marked overtime, and will have to be reviewed by your instructor.'), '");';
			}
			echo '});</script>';
		}

		if ($testsettings['isgroup']>0) {
			$testsettings['intro'] .= "<p><span class=noticetext >" . _('This is a group assessment.  Any changes affect all group members.') . "</span><br/>";
			if (!$isteacher || isset($sessiondata['actas'])) {
				$testsettings['intro'] .= _('Group Members:') . " <ul>";
				$query = "SELECT imas_users.id,imas_users.FirstName,imas_users.LastName FROM imas_users,imas_assessment_sessions WHERE ";
				$query .= "imas_users.id=imas_assessment_sessions.userid AND imas_assessment_sessions.agroupid=:agroupid ";
				$query .= "AND imas_assessment_sessions.assessmentid=:assessmentid ORDER BY imas_users.LastName,imas_users.FirstName";
				$stm = $DBH->prepare($query);
				$stm->execute(array(':agroupid'=>$sessiondata['groupid'], ':assessmentid'=>$testsettings['id']));
				while ($row = $stm->fetch(PDO::FETCH_NUM)) {
					$curgrp[] = $row[0];
					$testsettings['intro'] .= "<li>{$row[2]}, {$row[1]}</li>";
				}
				$testsettings['intro'] .= "</ul>";

				if ($testsettings['isgroup']==1 || $testsettings['isgroup']==2) {
					if (count($curgrp)<$testsettings['groupmax']) {
						$testsettings['intro'] .= "<a href=\"publicquestion.php?addgrpmem=true\">" . _('Add Group Members') . "</a></p>";
					} else {
						$testsettings['intro'] .= '</p>';
					}
				} else {
					$testsettings['intro'] .= '</p>';
				}
			}
		}
		if ($ptsearned==totalpointspossible($qi)) {
			$perfectscore = true;
		}
		{
			$i = 0;
			if ($i == count($questions)) {
				startoftestmessage($perfectscore,$hasreattempts,$allowregen,$noindivscores,$testsettings['testtype']=="NoScores");

				leavetestmsg();
			} else {
				echo "<form id=\"qform\" method=\"post\" enctype=\"multipart/form-data\" action=\"publicquestion.php?action=skip&amp;score=$i\" onsubmit=\"return doonsubmit(this)\">\n";
				echo "<input type=\"hidden\" name=\"asidverify\" value=\"$testid\" />";
				echo '<input type="hidden" name="disptime" value="'.time().'" />';
				echo "<input type=\"hidden\" name=\"isreview\" value=\"". ($isreview?1:0) ."\" />";
				echo "<input type=\"hidden\" name=\"verattempts\" value=\"{$attempts[$i]}\" />";
				echo "<div class=\"screenreader\" id=\"beginquestions\">"._('Start of Questions')."</div>\n";
				
				basicshowq($i);
				showqinfobar($i,true,true);
				echo '<input type="submit" class="btn" value="', _('Submit'), '" />';
				if ((($testsettings['showans']=='J' && $qi[$questions[$i]]['showans']=='0') || $qi[$questions[$i]]['showans']=='J') && $qi[$questions[$i]]['attempts']>0) {
					echo ' <input type="button" class="btn" value="', _('Jump to Answer'), '" onclick="if (confirm(\'', _('If you jump to the answer, you must generate a new version to earn credit'), '\')) {window.location = \'publicquestion.php?action=skip&amp;jumptoans='.$i.'&amp;to='.$i.'\'}"/>';
				}
				echo "</form>\n";
			}
		}
	}
	//IP:  eqntips

	require("../footer.php");

	function showembedupdatescript() {
		global $imasroot,$scores,$bestscores,$showeachscore,$qi,$questions,$testsettings;

		$jsonbits = array();
		$pgposs = 0;
		for($j=0;$j<count($scores);$j++) {
			$bit = "\"q$j\":[0,";
			if (unans($scores[$j])) {
				$cntunans++;
				$bit .= "1,";
			} else {
				$bit .= "0,";
			}
			if (canimprove($j)) {
				$cntcanimp++;
				$bit .= "1,";
			} else {
				$bit .= "0,";
			}
			$curpts = getpts($bestscores[$j]);
			if ($curpts<0) { $curpts = 0;}
			$bit .= $curpts.']';
			$pgposs += $qi[$questions[$j]]['points'];
			$pgpts += $curpts;
			$jsonbits[] = $bit;
		}
		echo '<script type="text/javascript">var embedattemptedtrack = {'.implode(',',$jsonbits).'}; </script>';
		echo '<script type="text/javascript">function updateembednav() {
			var unanscnt = 0;
			var canimpcnt = 0;
			var pts = 0;
			var qcnt = 0;
			for (var i in embedattemptedtrack) {
				if (embedattemptedtrack[i][1]==1) {
					unanscnt++;
				}
				if (embedattemptedtrack[i][2]==1) {
					canimpcnt++;
				}
				pts += embedattemptedtrack[i][3];
				qcnt++;
			}
			var status = 0;';
			//REMOVED to make consistent with load-time calculations
			//if ($showeachscore) {
			//	echo 'if (pts == '.$pgposs.') {status=2;} else if (unanscnt<qcnt) {status=1;}';
			//} else {
				echo 'if (unanscnt == 0) { status = 2;} else if (unanscnt<qcnt) {status=1;}';
			//}
			echo 'if (top !== self) {
				try {
					top.updateTRunans("'.$testsettings['id'].'", status);
				} catch (e) {}
			}
		      }</script>';
	}

	function showvideoembednavbar($viddata) {
		global $imasroot,$scores,$bestscores,$showeachscore,$qi,$questions,$testsettings;
		/*viddata[0] should be video id.  After that, should be [
		0: title for previous video segment,
		1: time to showQ / end of video segment, (in seconds)
		2: qn,
		3: time to jump to if right (and time for next link to start at) (in seconds)
		4: provide a link to watch directly after Q (T/F),
		5: title for the part immediately following the Q]
		*/
		echo '<div id="videonav" class="navbar videocued" role="navigation" aria-label="'._("Video and question navigation").'">';
		echo "<a href=\"#beginquestions\" class=\"screenreader\">", _('Skip Navigation'), "</a>\n";
		echo '<ul class="navlist">';
		$timetoshow = 0;
		for ($i=0; $i<count($viddata); $i++) {
			echo '<li>';
			echo '<a href="#" onclick="thumbSet.jumpToTime('.$timetoshow.',true);return false;">'.$viddata[$i][0].'</a>';
			if (isset($viddata[$i][2])) {
				echo '<br/>&nbsp;&nbsp;<a style="font-size:75%;" href="#" onclick="thumbSet.jumpToQ('.$viddata[$i][1].',false);return false;">', _('Jump to Question'), '</a>';
				if (isset($viddata[$i][4]) && $viddata[$i][4]==true) {
					echo '<br/>&nbsp;&nbsp;<a style="font-size:75%;" href="#" onclick="thumbSet.jumpToTime('.$viddata[$i][1].',true);return false;">'.$viddata[$i][5].'</a>';
				}
			}
			if (isset($viddata[$i][3])) {
				$timetoshow = $viddata[$i][3];
			} else if (isset($viddata[$i][1])) {
				$timetoshow = $viddata[$i][1];
			}
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
		showembedupdatescript();
	}

	function showembednavbar($pginfo,$curpg) {
		global $imasroot,$scores,$bestscores,$showeachscore,$qi,$questions,$testsettings;

		echo '<div class="navbar fixedonscroll" role="navigation" aria-label="'._("Page and question navigation").'">';
		echo "<a href=\"#beginquestions\" class=\"screenreader\">", _('Skip Navigation'), "</a>\n";
		echo "<h3>", _('Pages'), "</h3>\n";
		echo '<ul class="navlist">';
		$jsonbits = array();
		$max = (count($pginfo)-1)/2;
		$totposs = 0;
		for ($i = 0; $i < $max; $i++) {
			echo '<li>';
			if ($curpg == $i) { echo "<span class=current>";}
			if (trim($pginfo[2*$i+1])=='') {
				$pginfo[2*$i+1] =  $i+1;
			}
			echo '<a href="publicquestion.php?page='.$i.'">'.$pginfo[2*$i+1].'</a>';
			if ($curpg == $i) { echo "</span>";}

			preg_match_all('/\[QUESTION\s+(\d+)\s*\]/',$pginfo[2*$i+2],$matches,PREG_PATTERN_ORDER);
			if (isset($matches[1]) && count($matches[1])>0) {
				$qmin = min($matches[1])-1;
				$qmax = max($matches[1]);

				$cntunans = 0;
				$cntcanimp = 0;
				$pgposs = 0;
				$pgpts = 0;
				for($j=$qmin;$j<$qmax;$j++) {
					$bit = "\"q$j\":[$i,";
					if (unans($scores[$j])) {
						$cntunans++;
						$bit .= "1,";
					} else {
						$bit .= "0,";
					}
					if (canimprove($j)) {
						$cntcanimp++;
						$bit .= "1,";
					} else {
						$bit .= "0,";
					}
					$curpts = getpts($bestscores[$j]);
					if ($curpts<0) { $curpts = 0;}
					$bit .= $curpts.']';
					$pgposs += $qi[$questions[$j]]['points'];
					$pgpts += $curpts;
					$jsonbits[] = $bit;
				}
				echo '<br/>';

				//if (false && $showeachscore) {
				///	echo "<br/><span id=\"embednavcanimp$i\" style=\"margin-left:8px\">$cntcanimp</span> can be improved";
				//}
				echo '<span style="margin-left:8px">';
				if ($showeachscore) {
					echo " <span id=\"embednavscore$i\">".round($pgpts,1)." " .(($pgpts==1) ? _("point") : _("points"))."</span> " . _("out of") . " $pgposs";
				} else {
					echo " <span id=\"embednavunans$i\">$cntunans</span> " . _("unattempted");
				}
				echo '</span>';
				$totposs += $pgposs;
			}
			echo "</li>\n";
		}
		echo '</ul>';
		echo '<script type="text/javascript">var embedattemptedtrack = {'.implode(',',$jsonbits).'}; </script>';
		echo '<script type="text/javascript">function updateembednav() {
			var unanscnt = [];
			var unanstot = 0; var ptstot = 0;
			var canimpcnt = [];
			var pgpts = [];
			var pgmax = -1;
			var qcnt = 0;
			for (var i in embedattemptedtrack) {
				if (embedattemptedtrack[i][0] > pgmax) {
					pgmax = embedattemptedtrack[i][0];
				}
				qcnt++;
			}
			for (var i=0; i<=pgmax; i++) {
				unanscnt[i] = 0;
				canimpcnt[i] = 0;
				pgpts[i] = 0;

			}
			for (var i in embedattemptedtrack) {
				if (embedattemptedtrack[i][1]==1) {
					unanscnt[embedattemptedtrack[i][0]]++;
					unanstot++;
				}
				if (embedattemptedtrack[i][2]==1) {
					canimpcnt[embedattemptedtrack[i][0]]++;
				}
				pgpts[embedattemptedtrack[i][0]] += embedattemptedtrack[i][3];
				ptstot += embedattemptedtrack[i][3];
			}
			for (var i=0; i<=pgmax; i++) {
				';
		//if (false && $showeachscore) {
		//		echo 'document.getElementById("embednavcanimp"+i).innerHTML = canimpcnt[i];';
		//}
		if ($showeachscore) {
				echo 'var el = document.getElementById("embednavscore"+i);';
				echo 'if (el != null) {';
				echo '	el.innerHTML = pgpts[i] + ((pgpts[i]==1) ? " point" : " points");';
		} else {
				echo 'var el = document.getElementById("embednavunans"+i);';
				echo 'if (el != null) {';
				echo '	el.innerHTML = unanscnt[i];';
		}

		echo '}}
			var status = 0;';
			if ($showeachscore) {
				echo 'if (ptstot == '.$totposs.') {status=2} else if (unanstot<qcnt) {status=1;}';
			} else {
				echo 'if (unanstot == 0) { status = 2;} else if (unanstot<qcnt) {status=1;}';
			}
			echo 'if (top !== self) {
				try {
					top.updateTRunans("'.$testsettings['id'].'", status);
				} catch (e) {}
			}
		}</script>';


		echo '</div>';
	}

	

	function shownavbar($questions,$scores,$current,$showcat,$extrefs) {
		global $imasroot,$isteacher,$isdiag,$testsettings,$attempts,$qi,$allowregen,$bestscores,$isreview,$showeachscore,$noindivscores,$CFG;
		$todo = 0;
		$earned = 0;
		$poss = 0;
		echo '<div class="navbar" role="navigation" aria-label="'._("Question navigation").'">';
		echo "<a href=\"#beginquestions\" class=\"screenreader\">", _('Skip Navigation'), "</a>\n";
		$extrefs = json_decode($extrefs, true);
		if ($extrefs !== null && count($extrefs)>0) {
			echo '<h3>'._('Resources').'</h3>';
			echo '<ul class=qlist>';
			foreach ($extrefs as $extref) {
				if (!$isteacher) {
					$rec = "data-base=\"assessintro-{$testsettings['id']}\"";
				} else {
					$rec = '';
				}
				echo '<li><a target="_blank" '.$rec.' href="'.Sanitize::url($extref['link']).'">'.Sanitize::encodeStringForDisplay($extref['label']).'</a></li>';
			}
			echo '</ul>';
		}
		echo "<h3>", _('Questions'), "</h3>\n";
		echo "<ul class=qlist>\n";
		for ($i = 0; $i < count($questions); $i++) {
			echo "<li>";
			if ($current == $i) { echo "<span class=current>";}
			if (unans($scores[$i]) || amreattempting($i)) {
				$todo++;
			}
			/*
			$icon = '';
			if ($attempts[$i]==0) {
				$icon = "full";
			} else if (hasreattempts($i)) {
				$icon = "half";
			} else {
				$icon = "empty";
			}
			echo "<img src=\"$imasroot/img/aicon/left$icon.gif\"/>";
			$icon = '';
			if (unans($bestscores[$i]) || getpts($bestscores[$i])==0) {
				$icon .= "empty";
			} else if (getpts($bestscores[$i]) == $qi[$questions[$i]]['points']) {
				$icon .= "full";
			} else {
				$icon .= "half";
			}
			if (!canimprovebest($i) && !$allowregen && $icon!='full') {
				$icon .= "ci";
			}
			echo "<img src=\"$imasroot/img/aicon/right$icon.gif\"/>";
			*/
			if ($isreview) {
				$thisscore = getpts($scores[$i]);
			} else {
				$thisscore = getpts($bestscores[$i]);
			}
			if ((unans($scores[$i]) && $attempts[$i]==0) || ($noindivscores && amreattempting($i))) {
				if (isset($CFG['TE']['navicons'])) {
					echo "<img alt=\"" . _("untried") . "\" src=\"$imasroot/img/{$CFG['TE']['navicons']['untried']}\"/> ";
				} else {
				echo "<img alt=\"" - _("untried") . "\" src=\"$imasroot/img/q_fullbox.gif\"/> ";
				}
			} else if (canimprove($i) && !$noindivscores) {
				if (isset($CFG['TE']['navicons'])) {
					if ($thisscore==0 || $noindivscores) {
						echo "<img alt=\"" . _("incorrect - can retry") . "\" src=\"$imasroot/img/{$CFG['TE']['navicons']['canretrywrong']}\"/> ";
					} else {
						echo "<img alt=\"" . _("partially correct - can retry") . "\" src=\"$imasroot/img/{$CFG['TE']['navicons']['canretrypartial']}\"/> ";
					}
				} else {
				echo "<img alt=\"" . _("can retry"). "\" src=\"$imasroot/img/q_halfbox.gif\"/> ";
				}
			} else {
				if (isset($CFG['TE']['navicons'])) {
					if (!$showeachscore) {
						echo "<img alt=\"" . _("cannot retry") . "\" src=\"$imasroot/img/{$CFG['TE']['navicons']['noretry']}\"/> ";
					} else {
						if ($thisscore == $qi[$questions[$i]]['points']) {
							echo "<img alt=\"" . _("correct") . "\" src=\"$imasroot/img/{$CFG['TE']['navicons']['correct']}\"/> ";
						} else if ($thisscore==0) {
							echo "<img alt=\"" . _("incorrect - cannot retry") . "\" src=\"$imasroot/img/{$CFG['TE']['navicons']['wrong']}\"/> ";
						} else {
							echo "<img alt=\"" . _("partially correct - cannot retry") . "\" src=\"$imasroot/img/{$CFG['TE']['navicons']['partial']}\"/> ";
						}
					}
				} else {
					echo "<img alt=\"" . _("cannot retry") . "\" src=\"$imasroot/img/q_emptybox.gif\"/> ";
				}
			}


			if ($showcat>1 && $qi[$questions[$i]]['category']!='0') {
				if ($qi[$questions[$i]]['withdrawn']==1) {
					echo "<a href=\"publicquestion.php?action=skip&amp;to=$i\"><span class=\"withdrawn\">". ($i+1) . ") {$qi[$questions[$i]]['category']}</span></a>";
				} else {
					echo "<a href=\"publicquestion.php?action=skip&amp;to=$i\">". ($i+1) . ") {$qi[$questions[$i]]['category']}</a>";
				}
			} else {
				if ($qi[$questions[$i]]['withdrawn']==1) {
					echo "<a href=\"publicquestion.php?action=skip&amp;to=$i\"><span class=\"withdrawn\">Q ". ($i+1) . "</span></a>";
				} else {
					echo "<a href=\"publicquestion.php?action=skip&amp;to=$i\">Q ". ($i+1) . "</a>";
				}
			}
			if ($showeachscore) {
				if (($isreview && canimprove($i)) || (!$isreview && canimprovebest($i))) {
					echo ' (';
				} else {
					echo ' [';
				}
				if ($isreview) {
					$thisscore = getpts($scores[$i]);
				} else {
					$thisscore = getpts($bestscores[$i]);
				}
				if ($thisscore<0) {
					echo '0';
				} else {
					echo $thisscore;
					$earned += $thisscore;
				}
				echo '/'.$qi[$questions[$i]]['points'];
				$poss += $qi[$questions[$i]]['points'];
				if (($isreview && canimprove($i)) || (!$isreview && canimprovebest($i))) {
					echo ')';
				} else {
					echo ']';
				}
			}

			if ($current == $i) { echo "</span>";}

			echo "</li>\n";
		}
		echo "</ul>";
		if ($showeachscore) {
			if ($isreview) {
				echo "<p>", _('Review: ');
			} else {
				echo "<p>", _('Grade: ');
			}
			echo "$earned/$poss</p>";
		}
		if (!$isdiag && $testsettings['noprint']==0) {
			echo "<p><a href=\"#\" onclick=\"window.open('$imasroot/assessment/printtest.php','printver','width=400,height=300,toolbar=1,menubar=1,scrollbars=1,resizable=1,status=1,top=20,left='+(screen.width-420));return false;\">", _('Print Version'), "</a></p> ";
		}

		echo "</div>\n";
		return $todo;
	}

	function showscores($questions,&$attempts,$testsettings) {
		global $DBH,$regenonreattempt,$reattempting,$isdiag,$allowregen,$isreview,$noindivscores,$reattemptduring,$scores,$bestscores,$qi,$superdone,$timelimitkickout, $reviewatend;

		$total = 0;
		$lastattempttotal = 0;
		for ($i =0; $i < count($bestscores);$i++) {
			if (getpts($bestscores[$i])>0) { $total += getpts($bestscores[$i]);}
			if (getpts($scores[$i])>0) { $lastattempttotal += getpts($scores[$i]);}
			if (!$reattemptduring) {
				if ($scores[$i]=='-1' || amreattempting($i)) {
					//burn attempt
					$attempts[$i]++;
					$scores[$i] = 0;
					$loc = array_search($i,$reattempting);
					if ($loc!==false) {
						array_splice($reattempting,$loc,1);
					}
				} else {
					//clear out unans for multipart
					$scores[$i] = str_replace('-1','0',$scores[$i]);
				}
			}
		}
		$totpossible = totalpointspossible($qi);
		$average = round(100*((float)$total)/((float)$totpossible),1);

		$doendredirect = false;
		$outmsg = '';
		if ($testsettings['endmsg']!='') {
			$endmsg = unserialize($testsettings['endmsg']);
			$redirecturl = '';
			if (isset($endmsg['msgs'])) {
				foreach ($endmsg['msgs'] as $sc=>$msg) { //array must be reverse sorted
					if (($endmsg['type']==0 && $total>=$sc) || ($endmsg['type']==1 && $average>=$sc)) {
						$outmsg = $msg;
						break;
					}
				}
				if ($outmsg=='') {
					$outmsg = $endmsg['def'];
				}
				if (!isset($endmsg['commonmsg'])) {$endmsg['commonmsg']='';}

				if (strpos($outmsg,'redirectto:')!==false) {
					$redirecturl = trim(substr($outmsg,11));
					echo "<input type=\"button\" value=\"", _('Continue'), "\" onclick=\"window.location.href='$redirecturl'\"/>";
					return false;
				}
			}
		}


		echo "<h2>", _('Scores:'), "</h2>\n";

		global $testid;


		if ($testsettings['testtype']!="NoScores") {

			echo "<p>", sprintf(_('Total Points on Last Attempts:  %d out of %d possible'), $lastattempttotal, $totpossible), "</p>\n";

			//if ($total<$testsettings['minscore']) {
			if (($testsettings['minscore']<10000 && $total<$testsettings['minscore']) || ($testsettings['minscore']>10000 && $total<($testsettings['minscore']-10000)/100*$totpossible)) {
				echo "<p><b>", sprintf(_('Total Points Earned:  %d out of %d possible: '), $total, $totpossible);
			} else {
				echo "<p><b>", sprintf(_('Total Points in Gradebook: %d out of %d possible: '), $total, $totpossible);
			}

			echo "$average % </b></p>\n";

			if ($outmsg!='') {
				echo "<p class=noticetext style=\"font-weight: bold;\">$outmsg</p>";
				if ($endmsg['commonmsg']!='' && $endmsg['commonmsg']!='<p></p>') {
					echo $endmsg['commonmsg'];
				}
			}

			//if ($total<$testsettings['minscore']) {
			if (($testsettings['minscore']<10000 && $total<$testsettings['minscore']) || ($testsettings['minscore']>10000 && $total<($testsettings['minscore']-10000)/100*$totpossible)) {
				if ($testsettings['minscore']<10000) {
					$reqscore = $testsettings['minscore'];
				} else {
					$reqscore = ($testsettings['minscore']-10000).'%';
				}
				echo "<p><span class=noticetext><b>", sprintf(_('A score of %s is required to receive credit for this assessment'), $reqscore), "<br/>", _('Grade in Gradebook: No Credit (NC)'), "</span></p> ";
			}
		} else {
			echo "<p><b>", _('Your scores have been recorded for this assessment.'), "</b></p>";
		}

		//if timelimit is exceeded
		$now = time();
		if (!$timelimitkickout && ($testsettings['timelimit']>0) && (($now-$GLOBALS['starttime']) > $testsettings['timelimit'])) {
			$over = $now-$GLOBALS['starttime'] - $testsettings['timelimit'];
			echo "<p>", _('Time limit exceeded by'), " ";
			if ($over > 60) {
				$overmin = floor($over/60);
				echo "$overmin ", _('minutes'), ", ";
				$over = $over - $overmin*60;
			}
			echo "$over ", _('seconds'), ".<br/>\n";
			echo _('Grade is subject to acceptance by the instructor'), "</p>\n";
		}


		if (!$superdone && $_GET["action"] != "jitskip") { // $total < $totpossible &&
			if ($noindivscores && hasreattemptsany()) {
				echo "<p>", _('<a href="publicquestion.php?reattempt=all">Reattempt assessment</a> on questions allowed (note: where reattempts are allowed, all scores, correct and incorrect, will be cleared)'), "</p>";
			} else {
				if (canimproveany()) {
					echo "<p>", _('<a href="publicquestion.php?reattempt=canimprove">Reattempt assessment</a> on questions that can be improved where allowed'), "</p>";
				}
				if (hasreattemptsany()) {
					echo "<p>", _('<a href="publicquestion.php?reattempt=all">Reattempt assessment</a> on all questions where allowed'), "</p>";
				}
			}

			if ($allowregen) {
				echo "<p>", _('<a href="publicquestion.php?regenall=missed">Try similar problems</a> for all questions with less than perfect scores where allowed.'), "</p>";
				echo "<p>", _('<a href="publicquestion.php?regenall=all">Try similar problems</a> for all questions where allowed.'), "</p>";
			}
		}
		if ($testsettings['testtype']!="NoScores") {
			$hascatset = false;
			foreach($qi as $qii) {
				if ($qii['category']!='0') {
					$hascatset = true;
					break;
				}
			}
			if ($hascatset) {
				include("../assessment/catscores.php");
				catscores($questions,$bestscores,$testsettings['defpoints'],$testsettings['defoutcome'],$testsettings['courseid']);
			}
		}
		return true;

	}

	function endtest($testsettings) {

		//unset($sessiondata['sessiontestid']);
	}
	function leavetestmsg($or = '') {
		global $isdiag, $diagid, $sessiondata, $testsettings;
		$isltilimited = (isset($sessiondata['ltiitemtype']) && $sessiondata['ltiitemtype']==0);
		echo '<p>';
		echo $or;
		if ($isdiag) {
			if ($or != '') {
				echo ' '._('or').' ';
			}
			echo "<a href=\"../diag/index.php?id=$diagid\">", _('Exit Assessment'), "</a>\n";
		} else if ($isltilimited || $sessiondata['intreereader']) {

		} else {
			if ($or != '') {
				echo ' '._('or').' ';
			}
			echo "<a href=\"../course/course.php?cid={$testsettings['courseid']}\">", _('Return to Course Page'), "</a>\n";
		}
		echo '</p>';
	}
	function getSummaryConfirm() {
		global $reattemptduring, $scores;
		if (!$reattemptduring && in_array(-1,$scores)) {
			$oc = ' onclick="return confirm(\'';
			$oc .= _('Viewing the score summary will use up an attempt on any unanswered questions. Continue?');
			$oc .= '\')" ';
			return $oc;
		} else {
			return '';
		}
	}
writesessiondata();
	function writesessiondata() {
		global $sessiondata,$qi,$attempts,$lastanswers,$scores,$seeds,$qid,$questions,$bestscores;

		$sessiondata['attempts'] = $attempts;
		$sessiondata['lastanswers'] = $lastanswers;
		$sessiondata['scores'] = $scores;
		$sessiondata['seeds'] = $seeds;
		$sessiondata['qid'] = $qid;
		$sessiondata['questions'] = $questions;
		$sessiondata['qi'] = $qi;
		$sessiondata['bestscores'] = $bestscores;
		$sessiondata['attempts'] = $attempts;
		$sessiondata['attempts'] = $attempts;
		$sessiondata['attempts'] = $attempts;
		$sessiondata['attempts'] = $attempts;

		$_SESSION['data'] = base64_encode(serialize($sessiondata));
	}
?>
<script type="text/javascript">
$("div:contains('Score in gradebook')").each(function(){
    $(this).html($(this).html().replace(' Score in gradebook:',''));
});

$(function(){
	if(window.name == ""){
		window.name = "0";
	}
	//hide the rest of the parts
	$(".parts").slice($(".ansgrn").size()+1).hide();

	//disable changing correct answers
	$(".ansgrn").prop("readonly",true);

	if($(".ansgrn").size() != parseInt(window.name)){
		//we answered another part correctly
		window.name = $(".ansgrn").size();
		$("#verattempts").val(0);
	} else if (hints.length > parseInt(window.name) && $("#verattempts").val() > 0){
		//another wrong answer
		var hinttxt = hints[parseInt(window.name)].length > $("#verattempts").val()?hints[parseInt(window.name)][$("#verattempts").val()-1]:hints[parseInt(window.name)][hints[parseInt(window.name)].length-1];
		$(".parts").eq(parseInt(window.name)).append("<p><i>Hint:</i> "+hinttxt+"</p>");
	}
});
</script>   