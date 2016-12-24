<?php

session_start();
if (!isset($_SESSION["userid"])) {
	header('Location: login.php');
	exit;
}

$starttime = microtime(TRUE);
if (PHP_OS == 'WINNT') {
	require_once 'C:\inetpub\phplib\ooplogit.php';
	require_once 'C:\inetpub\phplib\oopPDOconnectDB.php';
	require_once 'C:\inetpub\phplib\cleanuserinput.php';
	require_once 'C:\inetpub\phplib\weblib.php';
} else {
	require_once '/var/www/phplib/ooplogit.php';
	require_once '/var/www/phplib/oopPDOconnectDB.php';
	require_once '/var/www/phplib/cleanuserinput.php';
	require_once '/var/www/phplib/weblib.php';
}

static $logname = 'editschool';
$o_logit = new ooplogit($logname, TRUE);

$o_logit->logit('Client:' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$results = new stdClass();
$results->success = FALSE;
$results->errortext = null;
$cancontinue = TRUE;

function updatesysdef($key, $value, $dbconn, $o_logit) {
	$theq = " delete from sysdef.system_defaults where sd_item=:key";
	$dbconn->executeIt($theq, array(":key" => $key), true);

	$theq = " insert into sysdef.system_defaults (sd_value,sd_item)";
	$theq .= " values (:value,:key)";
	$dbconn->executeIt($theq, array(":key" => $key, ":value" => $value), true);
}

// create a pg conection
$dbconn = new PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $o_logit, true);

if (key_exists('schoolname', $_POST)) {
	// was called by self so do update
	$o_logit->logit('updating school');

	updatesysdef("School Name", $_POST["schoolname"], $dbconn, $o_logit);
	updatesysdef("School Address", $_POST["schooladdress"], $dbconn, $o_logit);
	updatesysdef("School Address2", $_POST["schooladdress2"], $dbconn, $o_logit);
	updatesysdef("School City", $_POST["schoolcity"], $dbconn, $o_logit);
	updatesysdef("School State", $_POST["schoolstate"], $dbconn, $o_logit);
	updatesysdef("School Zip", $_POST["schoolzip"], $dbconn, $o_logit);
	updatesysdef("School Phone", $_POST["schoolphone"], $dbconn, $o_logit);

	$o_logit->logit('going back to school');
	header('Location: school.php');
	exit;
}

//school info
$theq = 'select ';
$theq .= ColAsInputField("schoolname", '30', '', 'required') . ',';
$theq .= ColAsInputField("schooladdress", '20', '', 'required') . ',';
$theq .= ColAsInputField("schooladdress2", '20') . ',';
$theq .= ColAsInputField("schoolcity", '15', '', 'required') . ',';
$theq .= ColAsInputField("schoolstate", '1', '', 'required') . ',';
$theq .= ColAsInputField("schoolzip", '', '2', 'required') . ',';
$theq .= ColAsInputField("schoolphone", '8', '', 'placeholder="123-123-1234" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" title="Please user the format 123-123-1234"', 'tel');

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
$dbconn->fetchIt($theq, null, $schooldata, true);

$_SESSION['post'] = 'method="post"';

$_SESSION['clientdefaults']['pagetitle'] = 'Edit School';
$_SESSION['buttontextschool'] = ' Save ';
$_SESSION['cancelbutton'] = '&nbsp;&nbsp;<a href="school.php"><input class="button" type="submit" value=" Cancel " /></a>';
$_SESSION['editstudentsbutton'] = '';

$thehtml = LoadTheHTML(null, 'page_editschool',
	array('header_schooldetails' => $schooldata),
	$o_logit, 1, 1);

echo $thehtml;

$_SESSION['post'] = '';
?>