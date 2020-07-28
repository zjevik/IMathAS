<?php

//Add location fields to imas_assessments
$DBH->beginTransaction();

$query = "ALTER TABLE `imas_assessments` 
ADD COLUMN `SBGgoals` TINYINT UNSIGNED NULL DEFAULT 3;";
  $res = $DBH->query($query);
  if ($res===false) {
	   echo "<p>Query failed: ($query) : " . $DBH->errorInfo() . "</p>";
	 $DBH->rollBack();
	 return false;
  }

$query = "ALTER TABLE `imas_assessments` 
ADD COLUMN `SBGtime` INT UNSIGNED NULL DEFAULT 600;";
  $res = $DBH->query($query);
  if ($res===false) {
	   echo "<p>Query failed: ($query) : " . $DBH->errorInfo() . "</p>";
	 $DBH->rollBack();
	 return false;
  }
  
$DBH->commit();

echo "<p style='color: green;'>✓ Added columns to imas_assessments for SBG assignments</p>";

return true;