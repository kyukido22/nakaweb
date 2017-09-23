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

static $logname = 'setuptest';

$o_logit = new ooplogit($logname, TRUE);
$o_logit->logit('Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

// create a pg conection
$dbconn = new PDOconnect($_SESSION["clientdefaults"]["dbname"], $o_logit, true);

$results = new stdClass();
$results->success = FALSE;
$results->errortext = null;
$cancontinue = TRUE;
unset($_SESSION['testdetails']);

// get list of possible arts
$theq = " select distinct clt_description,ct.clt_index,clt_seq";
$theq .= " from students s";
$theq .= " join ranks r on s.stu_index=r.stu_index";
$theq .= " join sysdef.rank_names rn on rn.srk_index=r.srk_index";
$theq .= " join sysdef.class_type ct on ct.clt_index=rn.clt_index";
$theq .= " where current_rank=true";
$theq .= " and student_type in ('A','ANP','APC')";
$theq .= ' order by clt_seq';
$cancontinue = $dbconn->fetchIt($theq, null, $rows, true);
$_SESSION['artselection'] = '<select name="artid">';
foreach ($rows as $key => $row) {
	$_SESSION['artselection'] .= ' <option value="' . $row->clt_index . '">' . $row->clt_description . '</option>';
}
$_SESSION['artselection'] .= '</select>';

$_SESSION['todaysdate'] = date('Y-m-d');

$_SESSION['clientdefaults']['pagetitle'] = 'Record Test';

$thehtml = LoadTheHTML(null, 'page_setuptest', null, $o_logit, 1, 1);

echo $thehtml;
?>