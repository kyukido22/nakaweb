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

static $logname = 'editstudent';
$o_logit = new ooplogit($logname, TRUE);
$o_logit->logit('Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$dbconn = new PDOconnect($_SESSION["clientdefaults"]["dbname"], $o_logit, true);

$results = new stdClass();
$results->success = FALSE;
$results->errortext = null;
$cancontinue = TRUE;

if (key_exists('dlStudent', $_GET)) {
	$stu_index = $_GET["dlStudent"];

	//-1 indicates a new student to be created
	// create a pg conection

	$_SESSION['buttoneditstudent'] = '  <form action="editstudent.php" method="post">';
	$_SESSION['buttoneditstudent'] .= '  <input class="button" type="submit" value=" Save Changes " />';
	$_SESSION['buttoneditstudent'] .= '  <input type="hidden" name="dlStudent" value="' . $stu_index . '" />';

	// basic student info
	$theq = "select stu_index,";
	$theq .= ColAsInputField("last_name", '', '', 'required placeholder="last name"') . ',';
	$theq .= ColAsInputField("first_name", '', '', 'required placeholder="first name"') . ',';
	$theq .= ColAsInputField("middle_name", '1') . ',';
	$theq .= ColAsInputField("m_last_name") . ',';
	$theq .= ColAsInputField("m_first_name") . ',';
	$theq .= ColAsInputField("m_middle_name", '1') . ',';
	$theq .= ColAsInputField("f_last_name") . ',';
	$theq .= ColAsInputField("f_first_name") . ',';
	$theq .= ColAsInputField("f_middle_name", '1') . ',';
	$theq .= ColAsInputField("address") . ',';
	$theq .= ColAsInputField("city") . ',';
	$theq .= ColAsInputField("state", '1') . ',';
	$theq .= ColAsInputField("zip", '3') . ',';
	$theq .= ColAsInputField("birthday", '6', 'now()', '', 'date') . ',';
	$theq .= ColAsInputField("start_date", '6', 'now()', '', 'date') . ',';
	$theq .= ColAsInputField("phone1", '', '', 'placeholder="123-123-1234" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" title="Please user the format 123-123-1234"', 'tel') . ',';
	$theq .= ColAsInputField("phone1_type", '3', '', 'placeholder="ph type"') . ',';
	$theq .= ColAsInputField("phone2", '', '', 'placeholder="123-123-1234" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" title="Please user the format 123-123-1234"', 'tel') . ',';
	$theq .= ColAsInputField("phone2_type", '3', '', 'placeholder="ph type"') . ',';
	$theq .= ColAsInputField("phone3", '', '', 'placeholder="123-123-1234" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" title="Please user the format 123-123-1234"', 'tel') . ',';
	$theq .= ColAsInputField("phone3_type", '3', '', 'placeholder="ph type"') . ',';
	$theq .= ColAsInputField("email", '30', '', '', 'email') . ',';
	$theq .= "  student_type, sex, primary_contact,";
	$theq .= "split_part(age(birthday)::text,' ',1)||' '||split_part(age(birthday)::text,' ',2)as age,";
	$theq .= "split_part(age(start_date)::text,' ',1)||' '||split_part(age(start_date)::text,' ',2)as trainingage";
	$theq .= ' from students s ';
	$theq .= ' left join sysdef.student_type st on st.short_name=s.student_type ';
	$theq .= ' where s.stu_index=:stu_index';
	$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
		$studentdata, true);

	// combo box for sex
	if ($studentdata[0]->sex != 'F') {
		$thebox = '<select name="cbsex">';
		$thebox .= '<option value="M" selected>Male</option>';
		$thebox .= '<option value="F">Female</option>';
		$thebox .= "</select>";
	} else {
		$thebox = '<select name="cbsex">';
		$thebox .= '<option value="M">Male</option>';
		$thebox .= '<option value="F" selected>Female</option>';
		$thebox .= "</select>";
	}

	$studentdata[0]->sex = $thebox;

	// combo box for student types
	$theq = 'select distinct stt_index,short_name,stt_description, s2.student_type as thisstudent';
	$theq .= '  from sysdef.student_type s1';
	$theq .= ' join (select distinct student_type from students';
	$theq .= "      union all select 'A'";
	$theq .= "      union all select 'LOA'";
	$theq .= ' )  s on s.student_type=s1.short_name';
	$theq .= ' left join (';
	$theq .= ' select student_type';
	$theq .= ' from students where stu_index=:stu_index) s2 on s1.short_name=s2.student_type';
	$theq .= ' order by stt_index';
	$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
		$studenttypes, true);

	$thebox = "<select name=\"cbstudenttype\">";
	foreach ($studenttypes as $key => $value) {
		if ($value->thisstudent != '') {
			$thebox .= "<option value=\"" . $value->short_name . "\" selected>" .
			$value->stt_description . "</option>";
		} else {
			$thebox .= "<option value=\"" . $value->short_name . "\">" .
			$value->stt_description . "</option>\n";
		}
	}
	$thebox .= "</select>";

	$studentdata[0]->stt_description = $thebox;

	//rank info

	if ($stu_index == -1) {
		$studentranks[0] = new stdClass();
		$studentranks[0]->fed_id = '<input type="text" name="fed_id" size="5" placeholder="MC16-05">';
		$studentranks[0]->clt_description = CreateArtCB($dbconn, $logname);
		$studentranks[0]->test_date = '<input type="date" name="test_date" value="' . date('Y-m-d') . '">';
		$studentranks[0]->srk_description = '<select name="rankvaue">';
		for ($i = 12; $i > 0; $i--) {
			$studentranks[0]->srk_description .= ' <option value="' . $i . '">' . abs($i) . ' Kup </option>';
		}
		for ($i = -1; $i > -10; $i--) {
			$studentranks[0]->srk_description .= ' <option value="' . $i . '">' . abs($i) . ' Dan </option>';
		}
		$studentranks[0]->srk_description .= '</select>';

	} else {
		$theq = 'select *';

		$theq .= ' from students s ';
		$theq .= ' left join ranks r on r.stu_index=s.stu_index ';
		$theq .= ' left join sysdef.rank_names rn on rn.srk_index=r.srk_index ';
		$theq .= ' left join sysdef.class_type ct on ct.clt_index=rn.clt_index ';
		$theq .= ' where s.stu_index=:stu_index';
		$theq .= ' order by clt_seq,srk_seq';
		$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
			$studentranks, true);
	}

	//notes
	$theq = 'select * from notes ';
	$theq .= ' where stu_index=:stu_index';
	$theq .= " and employee<>'*MA'";
	$theq .= ' order by note_timestamp desc';
	$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
		$rows, true);

	$notes[0] = new stdClass();
	$notes[0]->note_timestamp = 'New Note';
	$notes[0]->employee = $_SESSION["initials"];
	$notes[0]->note_text = ' <input type="text" name="note-note_text" size="70%">';

	foreach ($rows as $key => $noterow) {
		$notes[$key + 1] = $noterow;
	}

	//medical alerts
	$theq = 'select * from notes ';
	$theq .= ' where stu_index=:stu_index';
	$theq .= " and employee='*MA'";
	$theq .= ' order by note_timestamp desc';
	$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
		$rows, true);

	$medalert[0] = new stdClass();
	$medalert[0]->note_timestamp = 'NewAlert';
	$medalert[0]->employee = ' <input type="hidden" name="ma-employee" value="MA*">';
	$medalert[0]->note_text = ' <input type="text" name="ma-note_text" size="70%">';

	foreach ($rows as $key => $notrow) {
		$medalert[$key + 1] = $noterow;
	}

	$theq = 'select * from contracts c ';
	$theq .= ' join sysdef.programs p on p.pro_index=c.pro_index ';
	$theq .= ' join transactions t on c.con_index=t.con_index ';
	$theq .= ' where stu_index=:stu_index';
	$theq .= '   and c.active=true';
	$theq .= ' order by start_date desc';
	$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
		$contractsa, true);

	$theq = 'select * from contracts c ';
	$theq .= ' join sysdef.programs p on p.pro_index=c.pro_index ';
	$theq .= ' join transactions t on c.con_index=t.con_index ';
	$theq .= ' where stu_index=:stu_index';
	$theq .= '   and c.active=false';
	$theq .= ' order by start_date desc';
	$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
		$contractsi, true);

	$_SESSION['clientdefaults']['pagetitle'] = 'Student Info';

	if ($stu_index == -1) {
		$_SESSION["addrankbutton"] = '';
	} else {
		$_SESSION["addrankbutton"] = '<a href="addarank.php?dlStudent=' . $stu_index . '">' .
			'<input class="button" type="button" value=" Add a Rank " /></a>';
	}

	$thehtml = LoadTheHTML(null, 'page_main', array( //
		'shared_student' => $studentdata, //
		'shared_parents' => $studentdata, //
		'shared_contact' => $studentdata, //
		'shared_detail_ranks' => $studentranks, //
		'page_main_header_contractsa' => $contractsa, //
		'page_main_detail_contractsa' => $contractsa, //
		'page_main_header_contractsi' => $contractsi, //
		'page_main_detail_contractsi' => $contractsi, //
		'page_main_detail_medicalalert' => $medalert, //
		'page_main_detail_notes' => $notes, //
	), $o_logit, 1, 1);

	echo $thehtml;

} elseif (key_exists('dlStudent', $_POST)) {
	// write updates to database and go backto student selection screen
	$stu_index = $_POST["dlStudent"];

	if ($stu_index == -1) {
		//-1 indicates that this is a new student so we need to get the next id
		// and do an insert first

		$theq = "select nextval('seq_students') as stu_index";
		$cancontinue = $dbconn->fetchIt($theq, null, $rows, true);
		$stu_index = $rows[0]->stu_index;

		$theq = "insert into students (stu_index) values (:stu_index)";
		$cancontinue = $dbconn->executeIt($theq, array(':stu_index' => $stu_index), true);

		//figure out what the srk_index must be
		$theq = 'select * from sysdef.rank_names';
		$theq .= ' where clt_index=:clt_index';
		$theq .= ' and srk_seq=:srk_seq';
		$cancontinue = $dbconn->fetchIt($theq, array(
			':clt_index' => clean_user_input($_POST['artid']),
			':srk_seq' => clean_user_input($_POST['rankvaue'])), $rows, true);
		$srk_index = $rows[0]->srk_index;

		$theq = "insert into ranks (stu_index, srk_index, test_date, fed_id, current_rank) values";
		$theq .= "(:stu_index, :srk_index, :test_date, :fed_id, true)";
		$cancontinue = $dbconn->executeIt($theq, array(
			':stu_index' => $stu_index,
			':srk_index' => $srk_index,
			':test_date' => clean_user_input($_POST['test_date']),
			':fed_id' => clean_user_input($_POST['fed_id'])), true);

		$studentranks[0]->fed_id = '<input type="text" name="fed_id" size="5" placeholder="MC16-05">';
		$studentranks[0]->clt_description = CreateArtCB($dbconn, $logname);
		$studentranks[0]->test_date = '<input type="date" name="test_date" value="' . date('Y-m-d') . '">';
		$studentranks[0]->srk_description = '<select name="rankvaue">';
		for ($i = -12; $i < 0; $i++) {
			$studentranks[0]->srk_description .= ' <option value="' . $i . '">' . abs($i) . ' Kup </option>';
		}
		for ($i = 1; $i < 10; $i++) {
			$studentranks[0]->srk_description .= ' <option value="' . $i . '">' . abs($i) . ' Dan </option>';
		}
		$studentranks[0]->srk_description .= '</select>';

	}

	$theq = 'update students set';
	$theq .= " sex=:cbsex,";
	$theq .= " last_name=:last_name,";
	$theq .= " first_name=:first_name,";
	$theq .= " middle_name=:middle_name,";
	$theq .= " f_last_name=:f_last_name,";
	$theq .= " f_first_name=:f_first_name,";
	$theq .= " f_middle_name=:f_middle_name,";
	$theq .= " m_last_name=:m_last_name,";
	$theq .= " m_first_name=:m_first_name,";
	$theq .= " m_middle_name=:m_middle_name,";
	$theq .= " address=:address,";
	$theq .= " city=:city,";
	$theq .= " state=:state,";
	$theq .= " zip=:zip,";
	$theq .= " birthday=:birthday,";
	$theq .= " start_date=:start_date,";
	$theq .= " phone1=:phone1,";
	$theq .= " phone1_type=:phone1_type,";
	$theq .= " phone2=:phone2,";
	$theq .= " phone2_type=:phone2_type,";
	$theq .= " phone3=:phone3,";
	$theq .= " phone3_type=:phone3_type,";
	$theq .= " student_type=:cbstudenttype,";
	$theq .= " email=:email";
	$theq .= ' where stu_index=:stu_index';
	$cancontinue = $dbconn->executeIt($theq, array(
		':stu_index' => $stu_index,
		":cbstudenttype" => clean_user_input($_POST["cbstudenttype"]),
		":cbsex" => clean_user_input($_POST["cbsex"]),
		":last_name" => clean_user_input($_POST["last_name"]),
		":first_name" => clean_user_input($_POST["first_name"]),
		":middle_name" => clean_user_input($_POST["middle_name"]),
		":f_last_name" => clean_user_input($_POST["f_last_name"]),
		":f_first_name" => clean_user_input($_POST["f_first_name"]),
		":f_middle_name" => clean_user_input($_POST["f_middle_name"]),
		":m_last_name" => clean_user_input($_POST["m_last_name"]),
		":m_first_name" => clean_user_input($_POST["m_first_name"]),
		":m_middle_name" => clean_user_input($_POST["m_middle_name"]),
		":address" => clean_user_input($_POST["address"]),
		":city" => clean_user_input($_POST["city"]),
		":state" => clean_user_input($_POST["state"]),
		":zip" => clean_user_input($_POST["zip"]),
		":birthday" => clean_user_input($_POST["birthday"]),
		":start_date" => clean_user_input($_POST["start_date"]),
		":phone1" => clean_user_input($_POST["phone1"]),
		":phone1_type" => clean_user_input($_POST["phone1_type"]),
		":phone2" => clean_user_input($_POST["phone2"]),
		":phone2_type" => clean_user_input($_POST["phone2_type"]),
		":phone3" => clean_user_input($_POST["phone3"]),
		":phone3_type" => clean_user_input($_POST["phone3_type"]),
		":email" => clean_user_input($_POST["email"])), true);

	//notes
	if ($_POST["note-note_text"] != '') {
		$theq = 'insert into notes(employee, note_text, stu_index, note_timestamp) values';
		$theq .= ' (:employee,:note_text,:stu_index,now())';
		$cancontinue = $dbconn->executeIt($theq, array(
			':stu_index' => $stu_index,
			":employee" => $_SESSION["initials"],
			":note_text" => clean_user_input($_POST["note-note_text"])), true);
	}

	//medical alerts
	if ($_POST["ma-note_text"] != '') {
		$theq = 'insert into notes(employee, note_text, stu_index, note_timestamp) values';
		$theq .= " ('*MA',:note_text,:stu_index,now())";
		$cancontinue = $dbconn->executeIt($theq, array(
			':stu_index' => $stu_index, //
			":note_text" => clean_user_input($_POST["ma-note_text"])), true);
	}

	$o_logit->logit('going back to main.php');
	header('Location: main.php?dlStudent=' . $stu_index);
	exit;

}
?>