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
	$xlsstore = 'C:\inetpub\phplogs\invoices\\';
} else {
	require_once '/var/www/phplib/ooplogit.php';
	require_once '/var/www/phplib/oopPDOconnectDB.php';
	require_once '/var/www/phplib/cleanuserinput.php';
	require_once '/var/www/phplib/weblib.php';
	$xlsstore = '/var/tmp/invoices/';
}

static $logname = 'school';
$o_logit = new ooplogit($logname, TRUE);
$o_logit->logit('Client:' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$results = new stdClass();
$results->success = FALSE;
$results->errortext = null;
$cancontinue = TRUE;

// create a pg conection
$dbconn = new PDOconnect($_SESSION["clientdefaults"]["dbname"], $o_logit, true);

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
$dbconn->fetchIt($theq, null, $schooldata, true);
// remember the school address info for invoices
$_SESSION['schoolname'] = $schooldata[0]->schoolname;
$_SESSION['schooladdress'] = $schooldata[0]->schooladdress;
$_SESSION['schooladdress2'] = $schooldata[0]->schooladdress2;
$_SESSION['schoolcity'] = $schooldata[0]->schoolcity;
$_SESSION['schoolstate'] = $schooldata[0]->schoolstate;
$_SESSION['schoolzip'] = $schooldata[0]->schoolzip;
$_SESSION['schoolphone'] = $schooldata[0]->schoolphone;

$dbconn = new PDOconnect('nakaweb', $o_logit, true);
// superuser info
$theq = 'select login as userlogin,\'********\' as userthepassword,thelanguage as userthelanguage,u.userid,';
$theq .= ' firstname as userfirstname,lastname as userlastname,email as useremail,address1 as useraddress1,';
$theq .= ' address2 as useraddress2,city as usercity,state as userstate,zip as userzip,phone as userphone';
$theq .= ' from users u';
$theq .= ' join clientuser c on c.userid=u.userid';
$theq .= ' where clientid=:schoolid';
$theq .= " and superuser=true";
$theq .= ' order by lastname,firstname';
$dbconn->fetchIt($theq, array(':schoolid' => $_SESSION["clientdefaults"]["clientid"]),
	$superuserdata, true);

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
$dbconn->fetchIt($theq, array(':schoolid' => $_SESSION["clientdefaults"]["clientid"]),
	$userdata, true);

// test info
$theq = 'select testdate,clt_description,i.invoiceid,invoicedate,invoiceamount,paymentreceived,login';
$theq .= ' from tests t';
$theq .= ' join invoices i on i.invoiceid=t.invoiceid';
$theq .= " join (select * from dblink('" .
str_replace(';', '', $ini_array['connections'][$_SESSION["clientdefaults"]["dbname"]]) .
	' password=' . $ini_array['general']['password'] .
	' user=' . $ini_array['general']['user'] . "','";
$theq .= " 		select clt_index, clt_seq, short_name, clt_description from sysdef.class_type') as (";
$theq .= " 		clt_index integer,";
$theq .= " 		clt_seq integer,";
$theq .= " 		short_name character varying(5),";
$theq .= " 		clt_description character varying(20))) r on r.clt_index=t.artid";
$theq .= ' left join users u on u.userid=i.receivedby';
$theq .= ' where t.schoolid=:schoolid';
$theq .= ' order by testdate desc, i.invoiceid desc';
$dbconn->fetchIt($theq, array(':schoolid' => $_SESSION["clientdefaults"]["clientid"]),
	$testdata, true);

foreach ($testdata as $key => $value) {
	if (file_exists($xlsstore . 'naka' . $value->invoiceid . '.xls')) {
		$value->invoicelink = '<a class="invoicelinks" href="invoicedownload.php?file=' .
		base64_encode('naka' . $value->invoiceid . '.xls') .
		'">' . $value->invoiceid . '</a>';
	} else {
		$value->invoicelink = $value->invoiceid;
	}
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

$thehtml = LoadTheHTML($dbconn, 'page_school', array(
	'shared_header_schooldetails' => $schooldata,

	'shared_header_superuserdetails' => $superuserdata,
	'shared_detail_superuserdetails' => $superuserdata,

	'shared_header_userdetails' => $userdata,
	'shared_detail_userdetails' => $userdata,

	'page_school_detail_tests' => $testdata,
	'page_school_header_tests' => $testdata,
), $o_logit, 1, 1);

echo $thehtml;
?>