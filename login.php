<?php
/* Iris api
 *
 * author: john cantin - apr 2015
 *
 *
 *
 */
session_start();

//erase any old seesion vars

session_unset();

$starttime = microtime(TRUE);
require '/var/www/phplib/logitv2.php';
require '/var/www/phplib/PDOconnectDB.php';
require '/var/www/phplib/cleanuserinput.php';
require '/var/www/phplib/wc2lib.php';

static $logname = 'login';
startthelog($logname, TRUE);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

//set some default values
$_SESSION['errortext'] = '';
$_SESSION['clientdefaults'][0] = 0;
$_SESSION['local'] = 'EN-US';

//wc2 control database host
$_SESSION["dbhost"] = 'localhost';

$dbconn = PDOconnect('nakaweb', $_SESSION["dbhost"], $logname);

GetTheHTMLs('EN-US', 0, $dbconn, $logname);

if (key_exists('LOGIN', $_POST) and key_exists('PASSWORD', $_POST)) {
	logit($logname, 'Login: ' . $_POST["LOGIN"] . ' Password: ' . $_POST["PASSWORD"]);

	//check the username and password
	$theq = " select *";
	$theq .= " from users u";
	$theq .= " join clientuser cu on u.userid=cu.userid";
	$theq .= " join client c on c.clientid=cu.clientid";
	$theq .= " where login=:login";
	$theq .= "  and thepassword = :password";
	$theq .= "  and c.locked=false";
	$theq .= "  and u.locked=false";
	try {
		$pdoquery = $dbconn -> prepare($theq);
		$pdoquery -> setFetchMode(PDO::FETCH_OBJ);
		$pdoquery -> execute(array(//
		':login' => clean_user_input($_POST["LOGIN"]), //
		':password' => clean_user_input($_POST["PASSWORD"])));
		$row = $pdoquery -> fetch();

	} catch (PDOException $e) {
		logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
		$cancontinue = FALSE;
	}

	if ($cancontinue) {
		if (!$row) {
			// not a valid user/password
			//logit($logname, __LINE__);
			$results -> errortext = 'Sorry, that username/password is invalid';
		} else {
			// valid user/password
			if ($row -> locked == TRUE) {
				//logit($logname, __LINE__);
				$results -> errortext = 'Sorry, that user is locked';
			} else {
				// must be ok to let them in
				//                logit($logname, __LINE__);
				$_SESSION["userid"] = $row -> userid;
				$_SESSION["userlogin"] = $row -> login;
				$_SESSION["superuser"] = $row -> superuser;
				$_SESSION["treasurer"] = $row -> treasurer;
				$_SESSION["username"] = $row -> firstname . ' ' . $row -> lastname;
				$_SESSION["usercompany"] = $row -> company;
				$_SESSION["userlanguage"] = $row -> thelanguage;
				$_SESSION['clientdefaults']["schoollogo"] = $row -> schoollogo;
				$results -> success = TRUE;

				//update lastvisit
				$theq = " update users set lastvisit=now()";
				$theq .= " where userid=:userid";
				try {
					$pdoquery = $dbconn -> prepare($theq);
					$pdoquery -> execute(array(//
					':userid' => $_SESSION["userid"]));
					$row = $pdoquery -> fetch();

				} catch (PDOException $e) {
					logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
					$cancontinue = FALSE;
				}

				header('Location: clientselector.php');

			}
		}
	}
} else {
	$results -> success = TRUE;
	$results -> errortext = 'someone looking at the login page';
}

if (!$results -> success) {
	$_SESSION['errortext'] = $results -> errortext;
}

$thehtml = LoadTheHTML('page_login', null, $logname, 1, 1);

if ($thehtml == '') {
	$results -> errortext = 'no HTML found at: ' . __LINE__;
	$cancontinue = FALSE;
}

$thehtml = str_replace('  ', '', $thehtml);

echo $thehtml;

if (!$results -> success) {

	logit($logname, "    **ERROR** something went wrong in " . __FILE__ . " Error text is: " . $results -> errortext);
} else {
	$totaltime = microtime(TRUE) - $starttime;
	logit($logname, json_encode($results));
	logit($logname, "    That took: " . $totaltime . " seconds");
}
?>
