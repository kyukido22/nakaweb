<?php

session_start();

$starttime = microtime(TRUE);
if (PHP_OS == 'WINNT') {
	require_once 'C:\inetpub\phplib\ooplogit.php';
	require_once 'C:\inetpub\phplib\oopPDOconnectDB.php';
	require_once 'C:\inetpub\phplib\cleanuserinput.php';
	require_once 'C:\inetpub\phplib\weblib.php';
	require_once 'C:\inetpub\phplib\PHPMailer-master\class.phpmailer.php';
} else {
	require_once '/var/www/phplib/ooplogit.php';
	require_once '/var/www/phplib/oopPDOconnectDB.php';
	require_once '/var/www/phplib/cleanuserinput.php';
	require_once '/var/www/phplib/weblib.php';
	require_once '/var/www/phplib/PHPMailer-master/class.phpmailer.php';
}

static $logname = 'passwordreset';
$o_logit = new ooplogit($logname, TRUE);

$results = new stdClass();
$results->success = FALSE;
$results->errortext = null;
$cancontinue = TRUE;

//set some default values
$_SESSION['clientdefaults'][0] = 0;
//$_SESSION['local'] = 'EN-US';
$_SESSION["othercssorjs"] = '';

//wc2 control database host
$_SESSION["dbhost"] = 'localhost';

$dbconn = new PDOconnect('nakaweb', $_SESSION["dbhost"], $o_logit, true);

GetTheHTMLs('EN-US', 0, $dbconn, $o_logit);

if (key_exists('reset', $_POST)) {
	$o_logit->logit('Login: ' . $_POST["reset"]);

	//check the username and password
	$theq = " select *";
	$theq .= " from users u";
	$theq .= " join clientuser cu on u.userid=cu.userid";
	$theq .= " join client c on c.clientid=cu.clientid";
	$theq .= " where login=:login";
	$theq .= "  and c.locked=false";
	$theq .= "  and u.locked=false";
	$cancontinue = $dbconn->fetchIt($theq, array(
		':login' => clean_user_input($_POST["reset"])), $rows, true);
	$row = $rows[0];

	// dont admit that the username is valid or not
	$results->errortext = //
	'If the username you entered is in our system ' . //
	'you will recieve an email with a temporary ' . //
	'password sent your email address. ' . //
	'Don\'t forget to check your spam folder.';

	if ($cancontinue) {
		if (!$row) {
			// not a valid user/password
			$o_logit->logit('username is invalid');
			$results->success = TRUE;
		} else {
			// valid user/password
			$o_logit->logit('username is valid');

			//record the request
			$theq = " insert into passwordreset (username,ip,requestts)";
			$theq .= " values (:username,:ip,now())";
			$cancontinue = $dbconn->executeIt($theq, array(
				':username' => clean_user_input($_POST["reset"]), //
				':ip' => $_SERVER["REMOTE_ADDR"]), true);

			// update user's password
			$newpassword = substr(md5(rand()), 8, 6);
			$o_logit->logit('  generated password is:' . $newpassword);
			$theq = " update users";
			$theq .= " set thepassword=:newpassword";
			$theq .= " where userid=:userid";
			$cancontinue = $dbconn->executeIt($theq, array(
				':newpassword' => $newpassword,
				':userid' => $row->userid), true);

			//email user
			$email = new PHPMailer();
			$email->From = 'info@naka.com';
			$email->FromName = 'NAKA Website';
			$email->Subject = 'Password Reset';
			$email->Body = 'Your temporary password is: ' . $newpassword;
			$email->AddAddress($row->email);
			$o_logit->logit('   sending email');
			if (!$email->Send()) {
				$o_logit->logit('   **ERROR** EMAIL FAILED: ' . $email->ErrorInfo);
			} else {
				$o_logit->logit('   email sent');
			}

			$results->success = TRUE;

		}

	}

	$_SESSION['errortext'] = $results->errortext;
	$o_logit->logit($_SESSION['errortext']);
	$o_logit->logit('refresing display with get');
	header('Location: passwordreset.php');
	exit;

} else {
	$results->success = TRUE;
	$results->errortext = 'just someone looking at the password reset page';
}

$thehtml = LoadTheHTML(null, 'page_passwordreset', null, $o_logit, 1, 1);
$_SESSION['errortext'] = '';

if ($thehtml == '') {
	$results->errortext = 'no HTML found at: ' . __LINE__;
	$cancontinue = FALSE;
}

echo $thehtml;

if (!$results->success) {
	$o_logit->logit("    **ERROR** something went wrong in " . __FILE__ . " Error text is: " . $results->errortext);
} else {
	$totaltime = microtime(TRUE) - $starttime;
	$o_logit->logit(json_encode($results));
	$o_logit->logit("    That took: " . $totaltime . " seconds");
}
?>
