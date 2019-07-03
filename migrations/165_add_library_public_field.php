<?php

//Add location fields to imas_assessments
$DBH->beginTransaction();

$query = "ALTER TABLE `imas_libraries` 
ADD COLUMN `public` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `deleted`;";
  $res = $DBH->query($query);
  if ($res===false) {
	   echo "<p>Query failed: ($query) : " . $DBH->errorInfo() . "</p>";
	 $DBH->rollBack();
	 return false;
  }
  
$DBH->commit();

echo "<p style='color: green;'>✓ Added column to imas_libraries for public libraries</p>";

return true;
