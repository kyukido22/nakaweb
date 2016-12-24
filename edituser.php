<?php
/*
when inserting new users, they default to NOT superusers.  superusers can ONLY
be created manualy
 */
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

static $logname = 'edituser';
$o_logit = new ooplogit($logname, TRUE);
$dbconn = new PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $o_logit, true);

$results = new stdClass();
$results->success = FALSE;
$results->errortext = null;
$cancontinue = TRUE;

if (key_exists('firstname', $_POST)) {
	// was called by self, so do update
	$o_logit->logit('  called by self');
	$userid = $_POST["userid"];

	// first check if this is an add or an update
	if ($userid == -1) {
		$o_logit->logit('  inserting new user');
		//this is an add, so we need to get the next userid
		$theq = 'select max(userid)+1 as newid from users';
		$cancontinue = $dbconn->fetchIt($theq, null, $rows, true);
		$userid = $rows[0]->newid;

		//create blank record so that the later update will work find
		$theq = 'insert into users (userid) values (:userid)';
		$cancontinue = $dbconn->executeIt($theq,
			array(':userid' => $userid), $rows, true);

		//connect new user to client
		$theq = 'insert into clientuser (clientid, userid) values (:clientid, :userid)';
		$cancontinue = $dbconn->executeIt($theq, array(':userid' => $userid,
			':clientid' => $_SESSION["clientdefaults"]["clientid"]), $rows, true);
	}

	if ($_POST['thepassword'] != $_POST['thepasswordcheck']) {
		$results->errortext = "Passwords don't match";
		$_SESSION['errortext'] = $results->errortext;
		$cancontinue = FALSE;
	}

	$o_logit->logit('userid: ' . $userid);
	if ($cancontinue) {
		$params = array(':userid' => $userid, //
			':firstname' => clean_user_input($_POST["firstname"]), //
			':lastname' => clean_user_input($_POST["lastname"]), //
			':locked' => $_POST["locked"], //
			':email' => clean_user_input($_POST["email"]), //
			':address1' => clean_user_input($_POST["address1"]), //
			':address2' => clean_user_input($_POST["address2"]), //
			':city' => clean_user_input($_POST["city"]), //
			':state' => clean_user_input($_POST["state"]), //
			':zip' => clean_user_input($_POST["zip"]), //
			':phone' => clean_user_input($_POST["phone"]));

		$o_logit->logit('  updating user');
		$theq = 'update users';
		$theq .= ' set firstname= :firstname,';
		$theq .= ' lastname=:lastname,';
		if (isset($_POST["login"])) {
			$params[':login'] = clean_user_input($_POST["login"]);
			$theq .= ' login=:login,';
		}
		if (isset($_POST["thepassword"])) {
			$params[':thepassword'] = clean_user_input($_POST["thepassword"]);
			$theq .= ' thepassword=:thepassword,';
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
		$cancontinue = $dbconn->executeIt($theq, $params, true);

		$o_logit->logit('going back to school');
		header('Location: school.php');
		exit;
	}
} else {
	$userid = $_GET["userid"];
}
// display user info

$theq = 'select u.userid,locked,';
if ($userid == -1) {
	// new user, allow login and password to be updated
	$o_logit->logit('  inserting new user... need edit boxes for login');
	$theq .= ColAsInputField("login", '15', '', 'required') . ',';
} elseif ($_SESSION['userid'] == $userid or $_SESSION["superuser"]) {
	// user is editing his own data OR user is superuser, allow password to be
	// updated
	$o_logit->logit('  superuser: ' . $_SESSION["superuser"]);
	$o_logit->logit('  user is editing their own data OR this is a super user...');
	$theq .= ColAsInputField("login", '15', '', 'required') . ',';
} else {
	//must be a pleb editing someone elses record, dont allow password updating
	$o_logit->logit('  this is a pleb editing someone elses data... no edit boxes for login or thepassword');
}
$theq .= 'thepassword,thepassword as thepasswordcheck,';
$theq .= 'firstname,lastname,email,address1,address2,city,state,zip,';
//$theq .= ColAsInputField('phone', '', '', 'placeholder="123-123-1234" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" title="Please user the format 123-123-1234"', 'tel', 'userphone') . ',';
$theq .= 'phone,';
$theq .= ' case when locked then \'Disabled\' else \'Enabled\' end as lockeddisplay';
$theq .= ' from users u';
$theq .= ' where userid=:userid';
$cancontinue = $dbconn->fetchIt($theq, array(':userid' => $userid), $userdata, true);

if ($userdata[0]->locked == true) {
	$_SESSION['enabledselected'] = '';
	$_SESSION['disabledselected'] = 'selected';
} else {
	$_SESSION['enabledselected'] = 'selected';
	$_SESSION['disabledselected'] = '';
}

$_SESSION['createnewuserbutton'] = "";
$_SESSION['post'] = 'method="post"';
$_SESSION['clientdefaults']['pagetitle'] = 'Edit User';
$_SESSION['buttontextuser'] = ' Save ';

if ($userid == -1) {
	// new user, allow login and password to be updated
	$thehtml = LoadTheHTML(null, 'page_edituser', array( //
		'detail_editsuperuserdetails' => $userdata,
		'detail_edituserdetails' => null,
		'header_editsuperuserdetails' => $userdata,
		'header_edituserdetails' => null), //
		$o_logit, 1, 1);
} elseif ($_SESSION['userid'] == $userid or $_SESSION["superuser"]) {
	// user is editing his own data OR user is superuser, allow password to be updated
	$thehtml = LoadTheHTML(null, 'page_edituser', array( //
		'detail_editsuperuserdetails' => $userdata,
		'detail_edituserdetails' => null,
		'header_editsuperuserdetails' => $userdata,
		'header_edituserdetails' => null), //
		$o_logit, 1, 1);
} else {
	//must be a pleb editing someone elses record, dont allow password updating
	$thehtml = LoadTheHTML(null, 'page_edituser', array( //
		'detail_edituserdetails' => $userdata,
		'detail_editsuperuserdetails' => null,
		'header_edituserdetails' => $userdata,
		'header_editsuperuserdetails' => null), //
		$o_logit, 1, 1);
}

$_SESSION['post'] = '';
$_SESSION['errortext'] = '';

echo $thehtml;
?>