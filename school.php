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
static $logname = 'school';
startthelog($logname, TRUE);

$dbconn = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname,true);
GetTheHTMLs('EN-US', 0, $dbconn, $logname);

logit($logname, 'Client:' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

// create a pg conection
$dbconn = PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $logname, true);

//school info
$theq = 'select * from crosstab($$';
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
    // remember the school address info for invoices
    $_SESSION['schoolname'] = $schooldata[0] -> schoolname;
    $_SESSION['schooladdress'] = $schooldata[0] -> schooladdress;
    $_SESSION['schooladdress2'] = $schooldata[0] -> schooladdress2;
    $_SESSION['schoolcity'] = $schooldata[0] -> schoolcity;
    $_SESSION['schoolstate'] = $schooldata[0] -> schoolstate;
    $_SESSION['schoolzip'] = $schooldata[0] -> schoolzip;
    $_SESSION['schoolphone'] = $schooldata[0] -> schoolphone;
} catch (PDOException $e) {
    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
    $results -> errortext = $e -> getMessage();
    $cancontinue = FALSE;
}

$dbconn = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname, true);
// superuser info
$theq = 'select login as userlogin,\'********\' as userthepassword,thelanguage as userthelanguage,u.userid,';
$theq .= ' firstname as userfirstname,lastname as userlastname,email as useremail,address1 as useraddress1,';
$theq .= ' address2 as useraddress2,city as usercity,state as userstate,zip as userzip,phone as userphone';
$theq .= ' from users u';
$theq .= ' join clientuser c on c.userid=u.userid';
$theq .= ' where clientid=:schoolid';
$theq .= " and superuser=true";
$theq .= ' order by lastname,firstname';
try {
    $pdoquery = $dbconn -> prepare($theq);
    $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
    $pdoquery -> execute(array(':schoolid' => $_SESSION["clientdefaults"]["clientid"]));
    $superuserdata = $pdoquery -> fetchAll();
} catch (PDOException $e) {
    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
    $results -> errortext = $e -> getMessage();
    $cancontinue = FALSE;
}

// defuser info
$theq = 'select login as userlogin,\'********\' as userthepassword,thelanguage as userthelanguage,u.userid,';
$theq .= ' firstname as userfirstname,lastname as userlastname,email as useremail,address1 as useraddress1,';
$theq .= ' address2 as useraddress2,city as usercity,state as userstate,zip as userzip,phone as userphone,';
$theq .= ' case when u.locked then \'Disabled\' else \'Enabled\' end as lockeddisplay';
$theq .= ' from users u';
$theq .= ' join clientuser c on c.userid=u.userid';
$theq .= ' where clientid=:schoolid';
$theq .= " and superuser=false";
$theq .= ' order by locked,lastname,firstname';
try {
    $pdoquery = $dbconn -> prepare($theq);
    $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
    $pdoquery -> execute(array(':schoolid' => $_SESSION["clientdefaults"]["clientid"]));
    $userdata = $pdoquery -> fetchAll();
} catch (PDOException $e) {
    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
    $results -> errortext = $e -> getMessage();
    $cancontinue = FALSE;
}

// test info
$theq = 'select testdate,clt_description,i.invoiceid,invoicedate,invoiceamount,paymentreceived,login';
$theq .= ' from tests t';
$theq .= ' join invoices i on i.invoiceid=t.invoiceid';
if (PHP_OS == 'WINNT') {
    $theq .= " join (select * from dblink('host=localhost dbname=mfd user=postgres password=123PASSword$%^','";
} else {
    $theq .= " join (select * from dblink('host=localhost dbname=winmam1 user=postgres password=123PASSword$%^ port=54494','";
}
$theq .= " 		select clt_index, clt_seq, short_name, clt_description from sysdef.class_type') as (";
$theq .= " 		clt_index integer,";
$theq .= " 		clt_seq integer,";
$theq .= " 		short_name character varying(5),";
$theq .= " 		clt_description character varying(20))) r on r.clt_index=t.artid";
$theq .= ' left join users u on u.userid=i.receivedby';
$theq .= ' where t.schoolid=:schoolid';
$theq .= ' order by testdate desc, i.invoiceid desc';
try {
    $pdoquery = $dbconn -> prepare($theq);
    $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
    $pdoquery -> execute(array(':schoolid' => $_SESSION["clientdefaults"]["clientid"]));
    $testdata = $pdoquery -> fetchAll();
} catch (PDOException $e) {
    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
    $results -> errortext = $e -> getMessage();
    $cancontinue = FALSE;
}

if ($_SESSION['superuser'] == true) {
    $_SESSION['createnewuserbutton'] = '<form action="edituser.php">' . //
    '<input type="hidden" name="userid" value="-1">' . //
    '<input class="button" type="submit" value=" Add User " /></form>';
} else {
    $_SESSION['createnewuserbutton'] = '';
}
$_SESSION['buttontextuser'] = ' Edit User ';
$_SESSION['buttontextschool'] = ' Edit School ';
$_SESSION['clientdefaults']['pagetitle'] = 'School Details';
$_SESSION['cancelbutton'] = '';
$_SESSION['editstudentsbutton'] = '<form action="selectstudent.php"><input class="button" type="submit" value=" Students " /></form>';

$thehtml = LoadTheHTML('page_school', array(//
'header_schooldetails' => $schooldata, //
'detail_superuserdetails' => $superuserdata, //
'detail_edituserdetails' => $userdata, //
'detail_tests' => $testdata//
), $logname, 1, 1);

echo $thehtml;
?>