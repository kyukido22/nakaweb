<?php

session_start();

$starttime = microtime(TRUE);
require '/var/www/phplib/logitv2.php';
require '/var/www/phplib/PDOconnectDB.php';
require '/var/www/phplib/wc2lib.php';

static $logname = 'select';
startthelog($logname, TRUE);
logit($logname, 'Client:' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

$dbconn = PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $logname);

$theq = 'select * from students s ';
$theq .= " where student_type in ('A','ANP','APC')";
$theq .= ' order by last_name,first_name';
try {
	$pdoquery = $dbconn -> prepare($theq);
	$pdoquery -> setFetchMode(PDO::FETCH_OBJ);
	$pdoquery -> execute();
} catch (PDOException $e) {
	logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
	$results -> errortext = $e -> getMessage();
	$cancontinue = FALSE;
}

$_SESSION['activestudents'] = '<form method="post" action="main.php">' . //
"<select name=\"dlStudent\" onchange=\"this.form.submit()\">" . //
"<option value=\"1\" selected>Select a Student</option>";
while ($data = $pdoquery -> fetch()) {
	if (array_key_exists("dlStudent", $_POST) and ($_POST["dlStudent"] == $data -> stu_index)) {
		$_SESSION['activestudents'] .= "<option value=\"" . $data -> stu_index . "\" selected>" . $data -> last_name . ', ' . $data -> first_name . ' ' . $data -> middle_name . "</option>";
	} else {
		$_SESSION['activestudents'] .= "<option value=\"" . $data -> stu_index . "\">" . $data -> last_name . ', ' . $data -> first_name . ' ' . $data -> middle_name . "</option>\n";
	}
}
$_SESSION['activestudents'] .= "</select></form>";

$theq = 'select * from students s ';
$theq .= " where student_type not in ('A','ANP','APC')";
$theq .= ' order by last_name,first_name';
try {
	$pdoquery = $dbconn -> prepare($theq);
	$pdoquery -> setFetchMode(PDO::FETCH_OBJ);
	$pdoquery -> execute();
} catch (PDOException $e) {
	logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
	$results -> errortext = $e -> getMessage();
	$cancontinue = FALSE;
}

$_SESSION['inactivestudents'] = '<form method="post" action="main.php">'.//
 "<select name=\"dlStudent\" onchange=\"this.form.submit()\">".//
 "<option value=\"1\" selected>Select a Student</option>";
while ($data = $pdoquery -> fetch()) {
	if (array_key_exists("dlStudent", $_POST) and ($_POST["dlStudent"] == $data -> stu_index)) {
		$_SESSION['inactivestudents'] .= "<option value=\"" . $data -> stu_index . "\" selected>" . $data -> last_name . ', ' . $data -> first_name . ' ' . $data -> middle_name . "</option>";
	} else {
		$_SESSION['inactivestudents'] .= "<option value=\"" . $data -> stu_index . "\">" . $data -> last_name . ', ' . $data -> first_name . ' ' . $data -> middle_name . "</option>\n";
	}
}
$_SESSION['inactivestudents'] .= "</select></form>";


$thehtml = LoadTheHTML('page_selectstudent', null, $logname, 1, 1);
if ($thehtml == '') {
	$results -> errortext = 'no HTML found at: ' . __LINE__;
	$cancontinue = FALSE;
}

$thehtml = str_replace('  ', '', $thehtml);

echo $thehtml;


?>
