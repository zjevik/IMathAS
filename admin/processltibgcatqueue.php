<?php
//(c) 2019 Ondrej Zjevik
/* Modified from 
	MathAS: process LTI message queue 
   by David Lippman
*/

/* 
  To use the LTI queue, you'll need to either set up a cron job to call this
  script, or call it using a scheduled web call with the authcode option.
  It should be called every minute.

*/

//IMathAS: process LTI message queue
//(c) 2018 David Lippman

/*
  To use the LTI queue, you'll need to either set up a cron job to call this
  script, or call it using a scheduled web call with the authcode option.
  It should be called every minute.
  
  Config options (in config.php):
  To enable using LTI queue:
     $CFG['LTI']['usequeue'] = true; 
     
  The delay between getting an update request and sending it on (def: 5min)
     $CFG['LTI']['queuedelay'] = (# of minutes);
     
  The number of simultaneous curl calls to make (def: 10)
  	 $CFG['LTI']['queuebatch'] = (# of calls);
  
  Authcode to pass in query string if calling as scheduled web service;
  Call processltiqueue.php?authcode=thiscode
     $CFG['LTI']['authcode'] = "thecode";
     
  To log results in /admin/import/ltiqueue.log:
     $CFG['LTI']['logltiqueue'] = true;
*/

require("../init_without_validate.php");
require_once("../course/gbtable2.php");
require_once("../includes/ltioutcomes.php");

if (php_sapi_name() == "cli") { 
	//running command line - no need for auth code
} else if (!isset($CFG['LTI']['authcode'])) {
	echo 'You need to set $CFG[\'LTI\'][\'authcode\'] in config.php';
	exit;
} else if (!isset($_GET['authcode']) || $CFG['LTI']['authcode']!=$_GET['authcode']) {
	echo 'No authcode or invalid authcode provided';
	exit;
}

global $DBH,$cid,$isteacher,$istutor,$tutorid,$userid,$catfilter,$secfilter,$timefilter,$lnfilter,$isdiag;
global $sel1name,$sel2name,$canviewall,$lastlogin,$logincnt,$hidelocked,$latepasshrs,$includeendmsg;
global $hidesection,$hidecode,$exceptionfuncs,$courseenddate;
$isteacher = true;
$canviewall = true;
$catfilter = -1;
$secfilter = -1;
$includecategoryID = true;
$gbtbls = array();

//limit run to not run longer than 55 sec
ini_set("max_execution_time", "55");
//since set_time_limit doesn't count time doing stream/socket calls, we'll
//measure execution time ourselves too
$scriptStartTime = time();

/*
  pull all the possible updated from 
  
  table imas_lti_gbcatqueue
	hash, userid, gbcategory, courseid
  table imas_lti_gbcat
	id, userid, assessmentid, lti_sourcedid, score

  push the grades that need to be synchronized to 

  table imas_ltiqueue
     hash, sourcedid, grade, sendon
     index sendon

*/


//pull all lti gradebook category queue items ready to send; we'll process until we're done or timeout
$stm = $DBH->prepare('SELECT t1.hash,t1.userid,gbcategory,courseid,TIMESTAMPDIFF(SECOND,t1.timestamp,NOW()) as ageofcreation,assessmentid,lti_sourcedid,grade FROM imas_lti_gbcatqueue t1 LEFT JOIN imas_lti_gbcat t2 ON t1.userid = t2.userid ORDER BY t1.timestamp DESC');
$stm->execute();
$cntsuccess = 0;
$updatednow = 0;
$availshow = 2;

while ($row = $stm->fetch(PDO::FETCH_ASSOC)) {
	//echo "reading record ".$row['userid'].'<br/>';
	$cid = $row['courseid'];

	if(!isset($gbtbls[$cid])){
		$gbtbls[$cid] = gbtable();
	}
	$gbt = $gbtbls[$cid];
	//print_r($row);
	//print_r($gbt);
	//echo(json_encode($gbt));

	//get calctype for current category
	$calctype;
	foreach ($gbt[0][2] as $key => $el) {
		if($el[10] == $row['gbcategory']){
			$calctype = $el[13];
			break;
		}
	}

	//get canvas connected assignments in the same category
	$cgassignmentsid = array();
	$stm2 = $DBH->prepare('SELECT id,gbcatweight FROM imas_assessments WHERE displaymethod = :displaymethod AND courseid = :cid AND gbcategory = :gbcategory');
	$stm2->execute(Array(':cid'=>$cid, ':gbcategory'=>$row['gbcategory'], ':displaymethod'=>"CanvasGradebook"));
	while ($row2 = $stm2->fetch(PDO::FETCH_ASSOC)) {
		$cgassignmentsid[$row2['id']] = $row2['gbcatweight'];
	}
	if(count($cgassignmentsid) == 0){
		$DBH->exec('DELETE FROM imas_lti_gbcatqueue WHERE hash = "'.$row['hash'].'"');
		continue;
	}
	
	//print_r($cgassignmentsid);

	$alist = array();
	foreach ($gbt[0][1] as $key => $el) {
		//print_r($el);
		if($el[1]==$row['gbcategory'] && !isset($cgassignmentsid[$el[7]])){
			$alist[] = $key;
		}
	}
	//print_r($alist);
	//print_r($gbt[0]);

	foreach (array_slice($gbt,1) as $student) {
		if($student[4][0] < 0) continue;
		//print_r($student);
		foreach ($cgassignmentsid as $aid=>$perc) {
			$toDrop = 0;
			//count the number of dropped assignments.
			for ($i=0;$i<count($gbt[0][1]);$i++) {
				if(isset($student[1][$i][5]) && $student[1][$i][5]&4 && $gbt[0][1][$i][1]==$row['gbcategory']){
					$toDrop++;
					$student[1][$i][5] = $student[1][$i][5]&(~(6)); //remove drop mark
				}
			}
			//drop assignments
			for($i=0; $i<$toDrop; $i++){
				$minScore = PHP_INT_MAX;
				$minScoreIdx = -1;

				for ($j=0;$j<count($gbt[0][1]);$j++) {
					//skip already dropped
					if(isset($student[1][$j][5]) && $student[1][$j][5] & 6){
						continue;
					}
					if (($gbt[0][1][$j][4]==0 || $gbt[0][1][$j][4]==3)) { //skip if hidden
						continue;
					}
					if ($gbt[0][1][$j][3]>$availshow) {
						continue;
					}
					if ($gbt[0][1][$j][1]!=$row['gbcategory']) {//skip if wrong category
						continue;
					}
					if (!$gbt[0][1][$j][21]){
						continue;
					}
					$score = 0;
					if(isset($student[1][$j][0]) && ($student[1][$j][0] > 0 || $student[1][$j][21])) {
						$score = $perc*($calctype==0?$gbt[0][1][$j][2]:1) + (100-$perc)*($calctype==0?$student[1][$j][0]:$student[1][$j][0]/$gbt[0][1][$j][2]);
					}
					if($minScore > $score){
						$minScore = $score;
						$minScoreIdx = $j;
					}
				}
				$student[1][$minScoreIdx][5] = $student[1][$minScoreIdx][5] | 6; //add drop mark
			}

			$numerator = 0;
			$denominator = 0;
			foreach ($alist as $el) {
				if( 
					(isset($student[1][$el][5]) && $student[1][$el][5] & 6) || // dropped
					!$gbt[0][1][$el][21] || // didn't use the assessment
					(($gbt[0][1][$el][4]==0 || $gbt[0][1][$el][4]==3)) || // hidden
					($gbt[0][1][$el][3]>$availshow) ||
					($gbt[0][1][$el][1]!=$row['gbcategory']) || //skip if wrong category
					(!$gbt[0][1][$el][21])
				 ){
					 //skip hidden, dropped, etc. assignments
				} else
				{
					if(isset($student[1][$el][0]) && ($student[1][$el][0] > 0 || $student[1][$el][21])) 
						$numerator += $perc/100*($calctype==0?$gbt[0][1][$el][2]:1) + (100-$perc)/100*($calctype==0?$student[1][$el][0]:$student[1][$el][0]/$gbt[0][1][$el][2]);
					$denominator += $calctype==0?$gbt[0][1][$el][2]:1;
				}
				
			}
			//grade 0-1
			$grade = $denominator==0?1:round($numerator/$denominator,4);
			//echo $student[4][0].": Student: ".$student[0][0]." -> ".$grade."| ( ".$numerator." / ".$denominator." )\n";
		
			$stm2 = $DBH->prepare('SELECT * FROM imas_lti_gbcat WHERE userid = :uid AND assessmentid = :aid');
			$stm2->execute(Array(':uid'=>$student[4][0],':aid'=>$aid));
			$stu = $stm2->fetch(PDO::FETCH_ASSOC);
			//print_r($stu);
			//echo "true: ".$stu['grade']."<->".$grade."\n";

			if($stm2->rowCount()>0 && ($stu['grade'] != $grade || $row['ageofcreation']>0) ){
				//echo "updating: ".$stu['userid']." from ".$stu['grade']." to ".$grade."\n";
				$cntsuccess++;
				$stm2 = $DBH->prepare('UPDATE imas_lti_gbcat SET grade=:grade WHERE userid = :uid AND assessmentid = :aid');
				$stm2->execute(Array(':grade'=>$grade,':uid'=>$student[4][0],':aid'=>$aid));
				
				//send grade
				addToLTIQueue($stu['lti_sourcedid'],$grade,$row['ageofcreation']>0?true:false);
			}
		}
		//echo "<br>";
	}

	$DBH->exec("DELETE FROM imas_lti_gbcatqueue WHERE hash = '".$row['hash']."'");
	
}

echo "Done in ".(time() - $scriptStartTime);

if (!empty($CFG['LTI']['logltiqueue'])) {
	$logfilename = __DIR__ . '/import/ltiqueue.log';
	if (file_exists($logfilename) && filesize($logfilename)>100000) { //restart log if over 100k
		$logFile = fopen($logfilename, "w+");
	} else {
		$logFile = fopen($logfilename, "a+");
	}
	$timespent = time() - $scriptStartTime;
	fwrite($logFile, date("j-m-y,H:i:s",time()). ". $cntsuccess\n");
	fclose($logFile);
}
