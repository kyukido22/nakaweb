<?php

session_start();
if (!isset($_SESSION["userid"])) {
	header('Location: login.php');
	exit;
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

static $logname = 'setuptest';

$dbconn = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname);
GetTheHTMLs('EN-US', 0, $dbconn, $logname);

startthelog($logname, TRUE);
logit($logname, 'Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

// create a pg conection
$dbconn = PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $logname);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
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
try {
	$pdoquery = $dbconn -> prepare($theq);
	$pdoquery -> setFetchMode(PDO::FETCH_OBJ);
	$pdoquery -> execute();
	$_SESSION['artselection'] = '<select name="artid">';
	while ($row = $pdoquery -> fetch()) {
		$_SESSION['artselection'] .= ' <option value="' . $row -> clt_index . '">' . $row -> clt_description . '</option>';
	}
	$_SESSION['artselection'] .= '</select>';

} catch (PDOException $e) {
	logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
	$results -> errortext = $e -> getMessage();
	$cancontinue = FALSE;
}

$_SESSION['todaysdate'] = date('Y-m-d');

$_SESSION['clientdefaults']['pagetitle'] = 'Record Test';

$thehtml = LoadTheHTML('page_setuptest', null, $logname, 1, 1);

echo $thehtml;
?>