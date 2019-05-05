<?php 
//various line utility functions

global $allowedmacros,$isteacher;
array_push($allowedmacros,"isteacher");


//function isteacher()
//returns true if a user is has a teacher role in the course and false if the user is a student
function isteacher() {
	return $GLOBALS['sessiondata']['isteacher'];
}

?>
		
		