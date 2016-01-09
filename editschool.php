<?php

session_start();

$starttime = microtime(TRUE);
require '/var/www/phplib/logitv2.php';
require '/var/www/phplib/PDOconnectDB.php';
require '/var/www/phplib/cleanuserinput.php';
require '/var/www/phplib/wc2lib.php';

static $logname = 'editschool';
startthelog($logname, TRUE);
logit($logname, 'Client:' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

function updatesysdef($key, $value, $dbconn, $logname) {
	try {
		$theq = " delete from sysdef.system_defaults where sd_item=:key";
		$pdoquery = $dbconn -> prepare($theq);
		$pdoquery -> execute(array(":key" => $key));

		$theq = " insert into sysdef.system_defaults (sd_value,sd_item)";
		$theq .= " values (:value,:key)";
		$pdoquery = $dbconn -> prepare($theq);
		$pdoquery -> execute(array(":key" => $key, ":value" => $value));

	} catch (PDOException $e) {
		logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
		$results -> errortext = $e -> getMessage();
		$cancontinue = FALSE;
	}
}

// create a pg conection
$dbconn = PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $logname);

//var_dump($_POST);
if (key_exists('schoolname', $_POST)) {
	// was called by self so do update
	logit($logname, 'updating school');

	updatesysdef("School Name", $_POST["schoolname"], $dbconn, $logname);
	updatesysdef("School Address", $_POST["schooladdress"], $dbconn, $logname);
	updatesysdef("School Address2", $_POST["schooladdress2"], $dbconn, $logname);
	updatesysdef("School City", $_POST["schoolcity"], $dbconn, $logname);
	updatesysdef("School State", $_POST["schoolstate"], $dbconn, $logname);
	updatesysdef("School Zip", $_POST["schoolzip"], $dbconn, $logname);
	updatesysdef("School Phone", $_POST["schoolphone"], $dbconn, $logname);

	logit($logname, 'going back to school');
	header('Location: school.php');
	exit ;
}

//school info
$theq = 'select ';
$theq .= ' \'<input type="text" name="schoolname" value="\'||coalesce(schoolname,\'\')||\'">\' as schoolname,';
$theq .= ' \'<input type="text" name="schooladdress" value="\'||coalesce(schooladdress,\'\')||\'">\' as schooladdress,';
$theq .= ' \'<input type="text" name="schooladdress2" value="\'||coalesce(schooladdress2,\'\')||\'">\' as schooladdress2,';
$theq .= ' \'<input type="text" name="schoolcity" value="\'||coalesce(schoolcity,\'\')||\'">\' as schoolcity,';
$theq .= ' \'<input type="text" name="schoolstate" value="\'||coalesce(schoolstate,\'\')||\'">\' as schoolstate,';
$theq .= ' \'<input type="text" name="schoolzip" value="\'||coalesce(schoolzip,\'\')||\'">\' as schoolzip,';
$theq .= ' \'<input type="text" name="schoolphone" value="\'||coalesce(schoolphone,\'\')||\'">\' as schoolphone';
$theq .= ' from crosstab($$';
$theq .= " select 1,replace(sd_item,' ',''),sd_value from sysdef.system_defaults";
$theq .= " where lower(sd_item) in (";
$theq .= " 'school name','school address','school address2',";
$theq .= " 'school city','school state','school zip','school phone')";
$theq .= " $$,$$";
$theq .= "           select 'SchoolName' as seq";
$theq .= " union all select 'SchoolAddress'";
$theq .= " union all select 'SchoolAddress2'";
$theq .= " union all select 'SchoolCity'";
$theq .= " union all select 'SchoolState'";
$theq .= " union all select 'SchoolZip'";
$theq .= " union all select 'SchoolPhone'";
$theq .= " $$) as (";
$theq .= " rowid integer,";
$theq .= " schoolname text,";
$theq .= " schooladdress text,";
$theq .= " schooladdress2 text,";
$theq .= " schoolcity text,";
$theq .= " schoolstate text,";
$theq .= " schoolzip text,";
$theq .= " schoolphone text)";
try {
	$pdoquery = $dbconn -> prepare($theq);
	$pdoquery -> setFetchMode(PDO::FETCH_OBJ);
	$pdoquery -> execute();
	$schooldata = $pdoquery -> fetchAll();
} catch (PDOException $e) {
	logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
	$results -> errortext = $e -> getMessage();
	$cancontinue = FALSE;
}

$_SESSION['clientdefaults']['pagetitle'] = 'Edit User';
$_SESSION['buttontextschool'] = ' Save ';
$_SESSION['cancelbutton']='&nbsp;&nbsp;<a href="school.php"><input type="button" value=" Cancel " /></a>';
$_SESSION['editstudentsbutton'] = '';

$thehtml = LoadTheHTML('page_editschool', array('header_schooldetails' => $schooldata), //
$logname, 1, 1);

echo $thehtml;
?>
