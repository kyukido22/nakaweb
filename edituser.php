<?php

session_start();

$starttime = microtime(TRUE);
require '/var/www/phplib/logitv2.php';
require '/var/www/phplib/PDOconnectDB.php';
require '/var/www/phplib/cleanuserinput.php';
require '/var/www/phplib/wc2lib.php';

static $logname = 'edituser';
startthelog($logname, TRUE);
logit($logname, 'Client:' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

// create a pg conection
$dbconn = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname);

$userid = $_POST["userid"];

if (key_exists('firstname', $_POST)) {
	// was called by self, so do update
	logit($logname, '  called by self');

	// first check if this is an add or an update
	if ($userid == -1) {
		try {

			//this is and add, so we need to get the next userid
			$theq = 'select max(userid)+1 as newid from users';
			$pdoquery = $dbconn -> prepare($theq);
			$pdoquery -> setFetchMode(PDO::FETCH_OBJ);
			$pdoquery -> execute();
			$row = $pdoquery -> fetch();
			$userid = $row -> newid;

			//create blank record so that the later update will work find
			$theq = 'insert into users (userid) values (:userid)';
			$pdoquery = $dbconn -> prepare($theq);
			$pdoquery -> execute(array(':userid' => $userid));

			//connect new user to client
			$theq = 'insert into clientuser (clientid, userid) values (:clientid, :userid)';
			$pdoquery = $dbconn -> prepare($theq);
			$pdoquery -> execute(array(':userid' => $userid, //
			':clientid' => $_SESSION["clientdefaults"]["clientid"]));

		} catch (PDOException $e) {
			logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
			$results -> errortext = $e -> getMessage();
			$cancontinue = FALSE;
		}
	}

	logit($logname, '  updating user');
	$theq = 'update users';
	$theq .= ' set firstname= :firstname,';
	$theq .= ' lastname=:lastname,';
	if ($_POST["userid"] == -1) {
		// -1 means adding new user
		$theq .= ' login=:login,';
		$theq .= ' thepassword=:userpassword,';
	}
	$theq .= ' email=:email,';
	$theq .= ' address1=:address1,';
	$theq .= ' address2=:address2,';
	$theq .= ' city=:city,';
	$theq .= ' state=:state,';
	$theq .= ' zip=:zip,';
	$theq .= ' phone=:phone,';
	$theq .= ' locked=:locked';
	$theq .= ' where userid=:userid';
	try {
		$pdoquery = $dbconn -> prepare($theq);
		$pdoquery -> setFetchMode(PDO::FETCH_OBJ);
		if ($_POST["userid"] == -1) {
			$pdoquery -> execute(array(':userid' => $userid, //
			':login' => clean_user_input($_POST["userlogin"]), //
			':userpassword' => clean_user_input($_POST["userpassword"]), //
			':firstname' => clean_user_input($_POST["firstname"]), //
			':lastname' => clean_user_input($_POST["lastname"]), //
			':locked' => $_POST["locked"], //
			':email' => clean_user_input($_POST["email"]), //
			':address1' => clean_user_input($_POST["address1"]), //
			':address2' => clean_user_input($_POST["address2"]), //
			':city' => clean_user_input($_POST["city"]), //
			':state' => clean_user_input($_POST["state"]), //
			':zip' => clean_user_input($_POST["zip"]), //
			':phone' => clean_user_input($_POST["phone"])));
		} else {
			$pdoquery -> execute(array(':userid' => $userid, //
			':firstname' => clean_user_input($_POST["firstname"]), //
			':lastname' => clean_user_input($_POST["lastname"]), //
			':locked' => $_POST["locked"], //
			':email' => clean_user_input($_POST["email"]), //
			':address1' => clean_user_input($_POST["address1"]), //
			':address2' => clean_user_input($_POST["address2"]), //
			':city' => clean_user_input($_POST["city"]), //
			':state' => clean_user_input($_POST["state"]), //
			':zip' => clean_user_input($_POST["zip"]), //
			':phone' => clean_user_input($_POST["phone"])));
		}

	} catch (PDOException $e) {
		logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
		$results -> errortext = $e -> getMessage();
		$cancontinue = FALSE;
	}

	logit($logname, 'going back to school');
	header('Location: school.php');
	exit ;
}

// user info
$theq = 'select thelanguage as userthelanguage,u.userid,locked,';
if ($userid == -1) {
	$theq .= ' \'<input type="text" name="userlogin" value="\'||coalesce(login,\'\')||\'">\'  as userlogin,';
	$theq .= ' \'<input type="text" name="userpassword" value="\'||coalesce(thepassword,\'\')||\'">\'  as userthepassword,';
} else {
	$theq .= ' login as userlogin,thepassword as userthepassword,';
}
$theq .= ' \'<input type="text" name="firstname" value="\'||coalesce(firstname,\'\')||\'">\' as userfirstname,';
$theq .= ' \'<input type="text" name="lastname" value="\'||coalesce(lastname,\'\')||\'">\' as userlastname,';
$theq .= ' \'<input type="text" name="email" value="\'||coalesce(email,\'\')||\'">\' as useremail,';
$theq .= ' \'<input type="text" name="address1" value="\'||coalesce(address1,\'\')||\'">\' as useraddress1,';
$theq .= ' \'<input type="text" name="address2" value="\'||coalesce(address2,\'\')||\'">\' as useraddress2,';
$theq .= ' \'<input type="text" name="city" value="\'||coalesce(city,\'\')||\'">\' as usercity,';
$theq .= ' \'<input type="text" name="state" value="\'||coalesce(state,\'\')||\'">\' as userstate,';
$theq .= ' \'<input type="text" name="zip" value="\'||coalesce(zip,\'\')||\'">\' as userzip,';
$theq .= ' \'<input type="text" name="phone" value="\'||coalesce(phone,\'\')||\'">\' as userphone,';
$theq .= ' case when locked then \'Disabled\' else \'Enabled\' end as lockeddisplay';
$theq .= ' from users u';
$theq .= ' where userid=:userid';
try {
	$pdoquery = $dbconn -> prepare($theq);
	$pdoquery -> setFetchMode(PDO::FETCH_OBJ);
	$pdoquery -> execute(array(':userid' => $userid));
	$userdata = $pdoquery -> fetchAll();
} catch (PDOException $e) {
	logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
	$results -> errortext = $e -> getMessage();
	$cancontinue = FALSE;
}

if ($userdata[0] -> locked == true) {
	$enabledselected = '';
	$disabledselected = 'selected';

} else {
	$enabledselected = 'selected';
	$disabledselected = '';
}

$_SESSION['createnewuserbutton'] = "";
$_SESSION['clientdefaults']['pagetitle'] = 'Edit User';
$_SESSION['buttontextuser'] = ' Save ';
$_SESSION['cancelbutton'] = '&nbsp;&nbsp;<a href="school.php"><input type="button" value=" Cancel " /></a>' . //
"<br>Enabled: <select name=\"locked\">" . /////
"<option value=\"false\" " . $enabledselected . ">True</option>" . //
"<option value=\"true\" " . $disabledselected . ">False</option>" . //
"</select>";

$thehtml = LoadTheHTML('page_edituser', array('detail_userdetails' => $userdata), //
$logname, 1, 1);

echo $thehtml;
?>
