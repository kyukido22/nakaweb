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
	require_once 'C:\inetpub\phplib\weblib.php';
} else {
	require_once '/var/www/phplib/ooplogit.php';
	require_once '/var/www/phplib/oopPDOconnectDB.php';
	require_once '/var/www/phplib/weblib.php';
}

static $logname = 'clientselector';
$o_logit = new ooplogit($logname, TRUE);

$cancontinue = TRUE;

function SetSessionVals($clientuserrecord, $dbconn, $o_logit) {
	$cancontinue = TRUE;

	$o_logit->logit('--SetSessionVals--START');
	//load client details
	$_SESSION['clientdefaults']['clientid'] = $clientuserrecord->clientid;

	$theq = 'select * from client where clientid=:clientid';
	$cancontinue = $dbconn->fetchIt($theq,
		array('clientid' => $_SESSION["clientdefaults"]["clientid"]),
		$rows, true);

	if ($cancontinue) {
		$o_logit->logit('--SetSessionVals-- setting client defaults');
		$client = $rows[0];
		$_SESSION["clientdefaults"]["dbname"] = $client->dbname;
		$_SESSION["clientdefaults"]["host"] = $client->host;
		$_SESSION["clientdefaults"]["fullname"] = $client->fullname;
		$_SESSION["clientdefaults"]["schoollogo"] = $client->schoollogo;
		$_SESSION["clientdefaults"]["fedidprefix"] = $client->fedidprefix;
		$o_logit->logit('--SetSessionVals-- sending user to school.php');
		header('Location: school.php');
	}

	$o_logit->logit('--SetSessionVals--END');

	return $cancontinue;
}

/*
 * if this program is called with GET params for tag and client (must have been
 * self called) then, check that the user has rights to that (to prevent
 * cheating) and put them in.
 */

$dbconn = new PDOconnect('nakaweb', $_SESSION["dbhost"], $o_logit, true);

if (key_exists('clientid', $_GET)) {

	$theq = 'select * ';
	$theq .= ' from clientuser ';
	$theq .= ' where userid = :userid';
	$theq .= ' and clientid = :clientid';
	$cancontinue = $dbconn->fetchIt($theq, array(
		':userid' => $_SESSION["userid"],
		':clientid' => $_GET["clientid"]), $rows, true);
	if ($dbconn->records != 1) {
		$o_logit->logit('  looks like the user is cheating!  NOT letting them in!');
		$cancontinue = FALSE;
		echo 'CHEATER!!!';
		exit;
	} else {
		SetSessionVals($rows[0], $dbconn, $o_logit);
	}

} else {
	/*
		     * if this program is called without GET params (must have been called by
		     * login.php)
		     * then...
		     *      if user only has one tag just put them in
		     *      if user has multiple tags then display a list for them to select
	*/

	// check if user has access to more than one account

	$theq = " select *";
	$theq .= " from clientuser cu";
	$theq .= ' join client c on c.clientid=cu.clientid';
	$theq .= " where userid=:userid ";
	$theq .= ' order by fullname';
	$cancontinue = $dbconn->fetchIt($theq,
		array(':userid' => $_SESSION["userid"]),
		$rows, true);
	$row = $rows[0];

	if ($cancontinue) {
		if ($dbconn->records == 1) {
			// only one tag so just put them in
			$o_logit->logit('  user only has 1 tag value, bypassing client selection screen');
			$_SESSION["multiaccount"] = FALSE;
			SetSessionVals($row, $PDOconn, $o_logit);

		} else {

			// multiple tags, so need to display one of the account selection
			// screens
			$o_logit->logit('  user has multiple tag values');
			$_SESSION["multiaccount"] = TRUE;

			// check if user has access to more than one CLIENT
			$theq = 'select distinct c.clientid, fullname, dbname, host';
			$theq .= ' from clientuser cu';
			$theq .= ' join client c on cu.clientid = c.clientid';
			$theq .= ' where cu.userid = :userid';
			$cancontinue = $dbconn->fetchIt($theq, array(':userid' => $_SESSION["userid"]),
				$rows, true);
			$row = $rows[0];

			if ($cancontinue) {

				$o_logit->logit('  user has access to multiple clients');
				$theq = 'select c.clientid, fullname, ' . $_SESSION["userid"] . ' as userid';
				$theq .= ' from client c';
				$theq .= ' join clientuser cu on c.clientid = cu.clientid';
				$theq .= " where userid = :userid";
				$theq .= ' order by fullname';
				$cancontinue = $dbconn->fetchIt($theq,
					array(':userid' => $_SESSION["userid"]),
					$rows, true);

				$_SESSION['clientdefaults']['fullname'] = 'Select a School';

				$thehtml = LoadTheHTML($dbconn, 'page_clientselect',
					array('detail_clients' => $rows), $o_logit, 1, 1);

				if ($thehtml == '') {
					$results->errortext = 'no HTML found at: ' . __LINE__;
					$cancontinue = FALSE;
				}
				echo $thehtml;

			}
		}
	} // not a GET call
}

$totaltime = microtime(TRUE) - $starttime;

if ($totaltime > 0.5) {
	$o_logit->logit("    That took a REALLY long time: " . $totaltime . " seconds");
} elseif ($totaltime > 0.25) {
	$o_logit->logit("    That took a long time: " . $totaltime . " seconds");
} elseif ($totaltime > 0.1) {
	$o_logit->logit("    That took kinda long: " . $totaltime . " seconds");
} else {
	$o_logit->logit("    That took: " . $totaltime . " seconds");
}

//echo "ho there";
?>
