<?php

session_start();
if (!isset($_SESSION["userid"])) {
    header('Location: login.php');
    exit ;
}

$starttime = microtime(TRUE);
if (PHP_OS == 'WINNT') {
    require 'C:\inetpub\phplib\logitv2.php';
    require 'C:\inetpub\phplib\PDOconnectDB.php';
    require 'C:\inetpub\phplib\cleanuserinput.php';
    require 'C:\inetpub\phplib\wc2lib.php';
} else {
    require '/var/www/phplib/logitv2.php';
    require '/var/www/phplib/PDOconnectDB.php';
    require '/var/www/phplib/cleanuserinput.php';
    require '/var/www/phplib/wc2lib.php';
}

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

$_SESSION['post'] = 'method="post"';

$_SESSION['clientdefaults']['pagetitle'] = 'Edit School';
$_SESSION['buttontextschool'] = ' Save ';
$_SESSION['cancelbutton'] = '&nbsp;&nbsp;<a href="school.php"><input class="button" type="submit" value=" Cancel " /></a>';
$_SESSION['editstudentsbutton'] = '';

$thehtml = LoadTheHTML('page_editschool', array(//
'header_schooldetails' => $schooldata), //
$logname, 1, 1);

echo $thehtml;

$_SESSION['post'] = '';
?>