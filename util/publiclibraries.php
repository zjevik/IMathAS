<?php
//IMathAS:  Main admin page
//(c) 2006 David Lippman

/*** master php includes *******/
require("../init.php");
require("../includes/htmlutil.php");

 //set some page specific variables and counters
$overwriteBody = 0;
$body = "";
$pagetitle = "Publicly accessible libraries";
$helpicon = "";

//data manipulation here
$isadmin = false;
$isgrpadmin = false;

	//CHECK PERMISSIONS AND SET FLAGS
if ($myrights<20) {
 	$overwriteBody = 1;
	$body = "You need to log in as a teacher to access this page";
} elseif (isset($_GET['cid']) && $_GET['cid']=="admin" && $myrights <75) {
 	$overwriteBody = 1;
	$body = "You need to log in as an admin to access this page";
} elseif (!(isset($_GET['cid'])) && $myrights < 75) {
 	$overwriteBody = 1;
	$body = "Please access this page from the menu links only.";
} else {	//PERMISSIONS ARE OK, PERFORM DATA MANIPULATION

	$cid = Sanitize::courseId($_GET['cid']);
	if ($cid=='admin') {
		if ($myrights >74 && $myrights<100) {
			$isgrpadmin = true;
		} else if ($myrights == 100) {
			$isadmin = true;
		}
	}

	$now = time();

	$curBreadcrumb = "<a href=\"../index.php\">Home</a>";
	if ($isadmin || $isgrpadmin) {
		$curBreadcrumb .= " &gt; <a href=\"$imasroot/util/utils.php\">Utils</a> \n";
	}

	if (isset($_POST['update'])) {
        if (!isset($_POST['nchecked'])) {
            $stm = $DBH->prepare("UPDATE imas_libraries SET public=0");
            $stm->execute();
        } else {
            $stm = $DBH->prepare("UPDATE imas_libraries SET public=0");
            $stm->execute();
            $llist = implode(",", array_map('intval', $_POST['nchecked']));
            $stm = $DBH->prepare("UPDATE imas_libraries SET public=1 WHERE id IN ($llist)");
            $stm->execute();
        }
    } 
     { //DEFAULT PROCESSING HERE
		$pagetitle = "Publicly accessible libraries";
		$helpicon = "";
		$curBreadcrumb .= " &gt; Publicly accessible libraries ";
		
		$qarr = array();
		$query = "SELECT imas_libraries.id,imas_libraries.name,imas_libraries.ownerid,imas_libraries.userights,imas_libraries.federationlevel,imas_libraries.sortorder,imas_libraries.parent,imas_libraries.groupid,imas_libraries.public,count(imas_library_items.id) AS count ";
		$query .= "FROM imas_libraries LEFT JOIN imas_library_items ON imas_library_items.libid=imas_libraries.id and imas_library_items.deleted=0 ";
		$query .= "WHERE imas_libraries.deleted=0 ";
		if ($isadmin) {
			//no filter
		} else if ($isgrpadmin) {
			//any group owned library or visible to all
			$query .= "AND (imas_libraries.groupid=:groupid OR imas_libraries.userights>2) ";
			$qarr[':groupid'] = $groupid;
		} else {
			//owned, group
			$query .= "AND ((imas_libraries.ownerid=:userid OR imas_libraries.userights>2) ";
			$query .= "OR (imas_libraries.userights>0 AND imas_libraries.userights<3 AND imas_libraries.groupid=:groupid)) ";
			$qarr[':groupid'] = $groupid;
			$qarr[':userid'] = $userid;
		}
		$query .= "GROUP BY imas_libraries.id ORDER BY imas_libraries.name ASC,imas_libraries.federationlevel DESC,imas_libraries.id";
		$stm = $DBH->prepare($query);
		$stm->execute($qarr);
		$rights = array();
		$sortorder = array();
		while ($line = $stm->fetch(PDO::FETCH_ASSOC)) {
			$id = $line['id'];
			$name = $line['name'];
			$parent = $line['parent'];
			$qcount[$id] = $line['count'];
			$ltlibs[$parent][] = $id;
			$parents[$id] = $parent;
			$names[$id] = $name;
			$rights[$id] = $line['userights'];
			$sortorder[$id] = $line['sortorder'];
			$ownerids[$id] = $line['ownerid'];
			$groupids[$id] = $line['groupid'];
            $federated[$id] = ($line['federationlevel']>0);
            $public[$id] = $line['public'];
		}

		$page_appliesToMsg = (!$isadmin) ? "(Only applies to your libraries)" : "";
	}
}

$placeinhead = "<script type=\"text/javascript\" src=\"$imasroot/javascript/libtree.js\"></script>\n";
$placeinhead .= "<style type=\"text/css\">\n<!--\n@import url(\"$imasroot/course/libtree.css\");\n-->\n</style>\n";
/******* begin html output ********/
GLOBAL $hideAllHeaderNav;
$hideAllHeaderNav = true;
require("../header.php");

if ($overwriteBody==1) {
	echo $body;
} else {
?>
	<script>
	var curlibs = '<?php echo Sanitize::encodeStringForJavascript($parent1); ?>';
	function libselect() {
		window.open('libtree2.php?cid=<?php echo $cid ?>&libtree=popup&select=parent&selectrights=1&type=radio&libs='+curlibs,'libtree','width=400,height='+(.7*screen.height)+',scrollbars=1,resizable=1,status=1,top=20,left='+(screen.width-420));
	}
	function setlib(libs) {
		document.getElementById("libs").value = libs;
		curlibs = libs;
	}
	function setlibnames(libn) {
		document.getElementById("libnames").innerHTML = libn;
	}
	</script>


	<style type="text/css">
	ul.base ul {
		border-top: 1px solid #ddd;
	}
	ul.base li {
		border-bottom: 1px solid #ddd;
		padding-top: 5px;
	}
	span.fedico {
		color: #aaa;
	}
	</style>

	<div class=breadcrumb><?php echo $curBreadcrumb; ?></div>
	<div id="headermanagelibs" class="pagetitle"><h1><?php echo $pagetitle; echo $helpicon; ?></h1></div>

<?php
	 { //DEFAULT DISPLAY

		echo $page_AdminModeMsg;
?>

<?php
		foreach ($rights as $k=>$n) {
			setparentrights($k);
		}

		$qcount[0] = addupchildqs(0);
?>

	<form id="qform" method=post action="publiclibraries.php?cid=<?php echo $cid ?>">
		<div>
			Check: 
            <input type=submit name="update" value="Update">
			<?php echo $page_appliesToMsg ?>

		</div>
		<p>
			Root

			<ul class=base>
<?php
		$count = 0;

		if (isset($ltlibs[0])) {
            printlist(0);
		}
?>
			</ul>
		</p>
		<p>
			<b>Color Code</b><br/>
			<span class=r8>Open to all</span><br/>
			<span class=r4>Closed</span><br/>
			<span class=r5>Open to group, closed to others</span><br/>
			<span class=r2>Open to group, private to others</span><br/>
			<span class=r1>Closed to group, private to others</span><br/>
			<span class=r0>Private</span>
		</p>

	</form>
<?php
	}


}

require("../footer.php");


function printlist($parent) {
	global $names,$ltlibs,$count,$qcount,$cid,$rights,$sortorder,$ownerids,$userid,$isadmin,$groupids,$groupid,$isgrpadmin,$federated,$public;
	$arr = $ltlibs[$parent];

	if ($sortorder[$parent]==1) {
		$orderarr = array();
		foreach ($arr as $child) {
			$orderarr[$child] = $names[$child];
		}
		natcasesort($orderarr);
		$arr = array_keys($orderarr);
	}
	if ($parent==0 && $isadmin) {
		$arr[] = -2;
		$arr[] = -3;
		$names[-2] = "Root Level Private Libraries";
		$names[-3] = "Root Level Group Libraries";
		$rights[-2] = 0;
		$rights[-3] = 2;
		$ltlibs[-2] = array();
		$ltlibs[-3] = array();
	}

	foreach ($arr as $child) {
		if ($isadmin && $parent==0 && $rights[$child]<5 && $child>=0 && $ownerids[$child]!=$userid && ($rights[$child]==0 || $groupids[$child]!=$groupid)) {
			if ($rights[$child]==0) {
				$ltlibs[-2][] = $child;
			} else {
				$ltlibs[-3][] = $child;
			}
			continue;
		}
		//if ($rights[$child]>0 || $ownerids[$child]==$userid || $isadmin) {
		if ($rights[$child]>2 || ($rights[$child]>0 && $groupids[$child]==$groupid) || $ownerids[$child]==$userid || ($isgrpadmin && $groupids[$child]==$groupid) ||$isadmin) {
			if (!$isadmin) {
				if ($rights[$child]==5 && $groupids[$child]!=$groupid) {
					$rights[$child]=4;  //adjust coloring
				}
			}
			if (isset($ltlibs[$child])) { //library has children
				//echo "<li><input type=button id=\"b$count\" value=\"-\" onClick=\"toggle($count)\"> {$names[$child]}";
				echo "<li class=lihdr><span class=dd>-</span><span class=\"hdr btn\" id=\"bn" . Sanitize::encodeStringForDisplay($child) . "\" onClick=\"toggle('n" . Sanitize::encodeStringForJavascript($child) . "')\">+</span> ";
				if ($child>=0) {
					echo "<input ".(($public[$child]==1)?"checked":"")." type=checkbox name=\"nchecked[]\" value=" . Sanitize::encodeStringForDisplay($child) . "> ";
				}
                echo "<span class=hdr onClick=\"toggle('n" . Sanitize::encodeStringForJavascript($child) . "')\"><span class=\"r" . Sanitize::encodeStringForDisplay($rights[$child]) . "\">" . Sanitize::encodeStringForDisplay($names[$child]) ;
				if ($federated[$child]) {
					echo ' <span class=fedico title="Federated">&lrarr;</span>';
				}
				echo "</span> </span>\n";
				//if ($isadmin) {
				if ($child>=0) {
				  echo " ({$qcount[$child]}) ";

					
				}
				echo "<ul class=hide id=\"n" . Sanitize::encodeStringForDisplay($child) . "\">\n";
				$count++;
				printlist($child);
				echo "</ul></li>\n";

			} else if ($child>=0) {  //no children

                echo "<li><span class=dd>-</span><input ".(($public[$child]==1)?"checked":"")." type=checkbox name=\"nchecked[]\" value=" . Sanitize::encodeStringForDisplay($child) . "> <span class=\"r" . Sanitize::encodeStringForDisplay($rights[$child]) . "\">" . Sanitize::encodeStringForDisplay($names[$child]);
				if ($federated[$child]) {
					echo ' <span class=fedico title="Federated">&lrarr;</span>';
				}
				echo "</span> ";
				//if ($isadmin) {
				  echo " ({$qcount[$child]}) ";
				//}
				echo "</li>\n";


			}
		}
	}
}

function addupchildqs($p) {
	global $qcount,$ltlibs;
	if (isset($ltlibs[$p])) { //if library has children
		foreach ($ltlibs[$p] as $child) {
			$qcount[$p] += addupchildqs($child);
		}
	}
	return $qcount[$p];
}

function setparentrights($alibid) {
	global $rights,$parents;
	if ($parents[$alibid]>0) {
		if ($rights[$parents[$alibid]] < $rights[$alibid]) {
		//if (($rights[$parents[$alibid]]>2 && $rights[$alibid]<3) || ($rights[$alibid]==0 && $rights[$parents[$alibid]]>0)) {
			$rights[$parents[$alibid]] = $rights[$alibid];
		}
		setparentrights($parents[$alibid]);
	}
}

?>
