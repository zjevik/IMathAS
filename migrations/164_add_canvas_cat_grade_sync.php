<?php

//Add location fields to imas_assessments
$DBH->beginTransaction();

$query = "ALTER TABLE `imathasdb`.`imas_assessments` 
 ADD INDEX `displaymet` USING BTREE (`displaymethod`(2) ASC);";
  $res = $DBH->query($query);
  if ($res===false) {
	   echo "<p>Query failed: ($query) : " . $DBH->errorInfo() . "</p>";
	 $DBH->rollBack();
	 return false;
  }

$query = "ALTER TABLE `imathasdb`.`imas_assessments` 
 ADD COLUMN `gbcatweight` TINYINT UNSIGNED NULL DEFAULT 0 AFTER `loctype`;";
 $res = $DBH->query($query);
 if ($res===false) {
 	 echo "<p>Query failed: ($query) : " . $DBH->errorInfo() . "</p>";
	$DBH->rollBack();
	return false;
 }

$query = "CREATE TABLE `imas_lti_gbcat` (
	`hash` varchar(45) NOT NULL,
	`userid` int(10) unsigned NOT NULL,
	`assessmentid` int(10) unsigned NOT NULL,
	`lti_sourcedid` text NOT NULL,
	`grade` float unsigned NOT NULL DEFAULT '0',
	UNIQUE KEY `hash_UNIQUE` (`hash`),
	KEY `useridkey` (`userid`,`assessmentid`)
  )";
 $res = $DBH->query($query);
 if ($res===false) {
 	 echo "<p>Query failed: ($query) : " . $DBH->errorInfo() . "</p>";
	$DBH->rollBack();
	return false;
 }

$query = "CREATE TABLE `imas_lti_gbcatqueue` (
	`hash` char(32) NOT NULL,
	`userid` int(10) unsigned NOT NULL,
	`gbcategory` int(10) unsigned NOT NULL,
	`courseid` int(10) unsigned NOT NULL,
	`timestamp` datetime NOT NULL,
	PRIMARY KEY (`hash`)
  )";
 $res = $DBH->query($query);
 if ($res===false) {
 	 echo "<p>Query failed: ($query) : " . $DBH->errorInfo() . "</p>";
	$DBH->rollBack();
	return false;
 }
  
$DBH->commit();

echo "<p style='color: green;'>✓ Added column to imas_assessments for Canvas gradesync</p>";
echo "<p style='color: green;'>✓ Added tables imas_lti_gbcat, imas_lti_gbcatqueue</p>";

return true;
