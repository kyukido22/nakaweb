<?php

session_start();

//erase any old seesion vars

session_unset();

$starttime = microtime(TRUE);
if (PHP_OS == 'WINNT') {
	require_once 'C:\inetpub\phplib\ooplogit.php';
	require_once 'C:\inetpub\phplib\oopPDOconnectDB.php';
	require_once 'C:\inetpub\phplib\cleanuserinput.php';
	require_once 'C:\inetpub\phplib\weblib.php';
	$_SESSION['testing'] = true;
} else {
	require_once '/var/www/phplib/ooplogit.php';
	require_once '/var/www/phplib/oopPDOconnectDB.php';
	require_once '/var/www/phplib/cleanuserinput.php';
	require_once '/var/www/phplib/weblib.php';
	require_once '/var/www/phplib/PHPMailer-master/class.phpmailer.php';
	$_SESSION['testing'] = false;
}

//email invoice
$email = new PHPMailer();
$email->From = 'info@naka.com';
$email->FromName = 'NAKA Website';
$email->Subject = 'NAKA Invoice';
$email->Body = 'testing email service';
$email->AddAddress('john.cantin@gmail.com');
//$email->AddAttachment($xlsstore . 'naka' . $invoiceid . '.xls', $invoiceid . '.xls');
$o_logit->logit('   sending email to: ' .
	$_SESSION["useremail"] . '  ' . $_SESSION["treasureremail"] . '  john.cantin@gmail.com');

if (!$email->Send()) {
	$o_logit->logit('   **ERROR** EMAIL FAILED: ' . $email->ErrorInfo);
} else {
	$o_logit->logit('   email sent');
}

static $logname = 'login';
$o_logit = new ooplogit($logname, TRUE);

$results = new stdClass();
$results->success = FALSE;
$results->errortext = null;
$cancontinue = TRUE;

//set some default values
$_SESSION['errortext'] = '';
$_SESSION['clientdefaults'][0] = 0;
//$_SESSION['local'] = 'EN-US';
$_SESSION["othercssorjs"] = '';

//wc2 control database host
$_SESSION["dbhost"] = 'localhost';

$dbconn = new PDOconnect('nakaweb', $_SESSION["dbhost"], $o_logit, true);

GetTheHTMLs('EN-US', 0, $dbconn, $o_logit);

if (key_exists('LOGIN', $_POST) and key_exists('PASSWORD', $_POST)) {
	$o_logit->logit('Login: ' . $_POST["LOGIN"] . ' Password: ' . $_POST["PASSWORD"]);

	//check the username and password
	$theq = " select *";
	$theq .= " from users u";
	$theq .= " join clientuser cu on u.userid=cu.userid";
	$theq .= " join client c on c.clientid=cu.clientid";
	$theq .= " where login=:login";
	$theq .= "  and thepassword = :password";
	$theq .= "  and c.locked=false";
	$theq .= "  and u.locked=false";
	$cancontinue = $dbconn->fetchIt($theq, array(
		':login' => clean_user_input($_POST["LOGIN"]),
		':password' => clean_user_input($_POST["PASSWORD"])), $rows, true);

	if ($cancontinue) {
		if ($dbconn->records == 0) {
			// not a valid user/password
			//$o_logit->logit( __LINE__);
			$results->errortext = 'Sorry, that username/password is invalid';
		} else {
			$row = $rows[0];
			// valid user/password
			if ($row->locked == TRUE) {
				//$o_logit->logit( __LINE__);
				$results->errortext = 'Sorry, that user is locked';
			} else {
				// must be ok to let them in
				//                $o_logit->logit( __LINE__);
				$_SESSION["userid"] = $row->userid;
				$_SESSION["userlogin"] = $row->login;
				$_SESSION["useremail"] = $row->email;
				$_SESSION["superuser"] = $row->superuser;
				$_SESSION["treasurer"] = $row->treasurer;
				$_SESSION["initials"] = $row->initials;
				$_SESSION["username"] = $row->firstname . ' ' . $row->lastname;
				$_SESSION["usercompany"] = $row->company;
				$_SESSION["userlanguage"] = $row->thelanguage;
				$_SESSION['clientdefaults']["schoollogo"] = $row->schoollogo;
				if ($_SESSION["treasurer"] == true) {
					$_SESSION["treasurermenu"] = '<a href="recordpayment.php">Receive Payments</a>&nbsp;&nbsp;';
				} else {
					$_SESSION["treasurermenu"] = '';
				}

				// get tresurers email
				$theq = " select email";
				$theq .= " from users u";
				$theq .= " where treasurer=true";
				$theq .= "  and u.locked=false";
				$cancontinue = $dbconn->fetchIt($theq, null, $rows, true);
				$_SESSION["treasureremail"] = $rows[0]->email;

				$results->success = TRUE;

				//update lastvisit
				$theq = " update users set lastvisit=now()";
				$theq .= " where userid=:userid";
				$cancontinue = $dbconn->executeIt($theq,
					array(':userid' => $_SESSION["userid"]), true);

				//record loginhistory
				$theq = " insert into loginhistory (userid,logints,loginfrom)";
				$theq .= " values (:userid,now(), :loginfrom)";
				$cancontinue = $dbconn->executeIt($theq,
					array(':userid' => $_SESSION["userid"],
						':loginfrom' => $_SERVER["REMOTE_ADDR"]), $rows, true);
				$row = $rows[0];

				header('Location: clientselector.php');
				exit;
			}
		}
	}
} else {
	$results->success = TRUE;
	$results->errortext = 'someone looking at the login page';
}

if (!$results->success) {
	$_SESSION['errortext'] = $results->errortext;
}

$thehtml = LoadTheHTML($dbconn, 'page_login', null, $o_logit, 1, 1);

$_SESSION['errortext'] = '';

if ($thehtml == '') {
	$results->errortext = 'no HTML found at: ' . __LINE__;
	$cancontinue = FALSE;
}

$thehtml = str_replace('  ', '', $thehtml);

echo $thehtml;

if (!$results->success) {

	$o_logit->logit("    **ERROR** something went wrong in " . __FILE__ . " Error text is: " . $results->errortext);
} else {
	$totaltime = microtime(TRUE) - $starttime;
	$o_logit->logit(json_encode($results));
	$o_logit->logit("    That took: " . $totaltime . " seconds");
}
?>
