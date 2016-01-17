<?php

/*
 * 
select 
student_type, last_name, first_name, middle_name, sex,
  m_last_name, m_first_name, m_middle_name, f_last_name,
  f_first_name, f_middle_name, primary_contact, address,
  city, state, zip, birthday, start_date, phone1, phone1_type,
  phone2, phone2_type, phone3, phone3_type, email, 
  user_text_1, user_text_2, user_text_3, user_text_4, user_text_5,
  user_date_1, user_date_2, user_date_3, user_date_4, user_date_5,
  user_num_1, user_num_2, user_num_3, user_num_4, user_num_5,
short_name as art,srk_seq as kup,test_date,fed_id

 from students s
join ranks r on s.stu_index=r.stu_index
join sysdef.rank_names rn on rn.srk_index=r.srk_index
join sysdef.class_type c on c.clt_index=rn.clt_index
where last_name in ('Abatte','Abdallah','Byk','Warzynski')
and current_rank=true
 * 
 */



session_start();
if (!isset($_SESSION["userid"])) {
    header('Location: login.php');
    exit ;
}


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
