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

static $logname = 'main';
$o_logit = new ooplogit($logname, TRUE);
$o_logit->logit('Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

// create a pg conection
$dbconn = new PDOconnect($_SESSION["clientdefaults"]["dbname"], $o_logit, true);

$results = new stdClass();
$results->success = FALSE;
$results->errortext = null;
$cancontinue = TRUE;

$stu_index = $_GET["dlStudent"];
$_SESSION["addrankbutton"] = '';

$_SESSION['buttoneditstudent'] = '  <form action="editstudent.php">';
$_SESSION['buttoneditstudent'] .= '  <input class="button" type="submit" value=" Edit Student " />';
$_SESSION['buttoneditstudent'] .= '  <input type="hidden" name="dlStudent" value="' . $stu_index . '" />';

// basic student info
$theq = "select *,";
$theq .= "split_part(age(birthday)::text,' ',1)||' '||split_part(age(birthday)::text,' ',2)as age,";
$theq .= "split_part(age(start_date)::text,' ',1)||' '||split_part(age(start_date)::text,' ',2)as trainingage";
$theq .= ' from students s ';
$theq .= ' left join sysdef.student_type st on st.short_name=s.student_type ';
$theq .= ' where s.stu_index=:stu_index';
$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
	$studentdata, true);

//rank info
$theq = 'select * from students s ';
$theq .= ' left join ranks r on r.stu_index=s.stu_index ';
$theq .= ' left join sysdef.rank_names rn on rn.srk_index=r.srk_index ';
$theq .= ' left join sysdef.class_type ct on ct.clt_index=rn.clt_index ';
$theq .= ' where s.stu_index=:stu_index';
$theq .= ' order by clt_seq,srk_seq';
$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
	$rankdata, true);

//notes
$theq = 'select * from notes ';
$theq .= ' where stu_index=:stu_index';
$theq .= " and employee<>'*MA'";
$theq .= ' order by note_timestamp desc';
$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
	$notedata, true);

//medical alerts
$theq = 'select * from notes ';
$theq .= ' where stu_index=:stu_index';
$theq .= " and employee='*MA'";
$theq .= ' order by note_timestamp desc';
$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
	$meddata, true);

$theq = 'select * from contracts c ';
$theq .= ' join sysdef.programs p on p.pro_index=c.pro_index ';
$theq .= ' join transactions t on c.con_index=t.con_index ';
$theq .= ' where stu_index=:stu_index';
$theq .= '   and c.active=true';
$theq .= ' order by start_date desc';
$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
	$activedata, true);

$theq = 'select * from contracts c ';
$theq .= ' join sysdef.programs p on p.pro_index=c.pro_index ';
$theq .= ' join transactions t on c.con_index=t.con_index ';
$theq .= ' where stu_index=:stu_index';
$theq .= '   and c.active=false';
$theq .= ' order by start_date desc';
$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
	$inactivedata, true);

$_SESSION['clientdefaults']['pagetitle'] = 'Student Info';

$thehtml = LoadTheHTML(null, 'page_main', array( //
	'shared_student' => $studentdata, //
	'shared_parents' => $studentdata, //
	'shared_contact' => $studentdata, //
	'shared_detail_ranks' => $rankdata, //
	'shared_header_ranks' => $rankdata, //
	'page_main_header_contractsa' => $activedata, //
	'page_main_detail_contractsa' => $activedata, //
	'page_main_header_contractsi' => $inactivedata, //
	'page_main_detail_contractsi' => $inactivedata, //
	'page_main_header_medicalalert' => $meddata, //
	'page_main_detail_medicalalert' => $meddata, //
	'page_main_header_notes' => $notedata, //
	'page_main_detail_notes' => $notedata, //
), $o_logit, 1, 1);

echo $thehtml;
?>