<?php

//Add location fields to imas_assessments
$DBH->beginTransaction();

 $query = "ALTER TABLE `imas_assessments` ADD `locradius` smallint(6) DEFAULT NULL,
 ADD `loclat` decimal(10,8) DEFAULT NULL,
 ADD `loclng` decimal(11,8) DEFAULT NULL,
 ADD `loctype` tinyint(1) unsigned NOT NULL";
 $res = $DBH->query($query);
 if ($res===false) {
 	 echo "<p>Query failed: ($query) : " . $DBH->errorInfo() . "</p>";
	$DBH->rollBack();
	return false;
 }
  
$DBH->commit();

echo "<p style='color: green;'>✓ Added columns to imas_assessments for Live Poll location</p>";

return true;
