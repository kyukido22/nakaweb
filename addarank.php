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

static $logname = 'addarank';
$o_logit = new ooplogit($logname, TRUE);
$o_logit->logit('Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

function CreateLimitedArtCB($pdoconn, $logname, $stu_index) {
	$theq = 'select c.* ';
	$theq .= 'from sysdef.class_type c ';
	$theq .= 'full join ( ';
	$theq .= '    select distinct short_name  ';
	$theq .= '    from ranks r ';
	$theq .= '    join sysdef.rank_names rn on r.srk_index=rn.srk_index ';
	$theq .= '    join sysdef.class_type c on c.clt_index=rn.clt_index ';
	$theq .= '    where stu_index=:stu_index ';
	$theq .= ')x on c.short_name=x.short_name ';
	$theq .= 'where active=true ';
	$theq .= 'and x.short_name is null ';
	$theq .= 'order by clt_seq ';

	$pdoconn->fetchIt($theq, array(':stu_index' => $stu_index), $rows, true);
	$res = '<select name="artid">';
	foreach ($rows as $key => $row) {
		$res .= ' <option value="' . $row->clt_index . '">' . $row->clt_description . '</option>';
	}
	$res .= '</select>';

	return ($res);
}

$dbconn = new PDOconnect($_SESSION["clientdefaults"]["dbname"], $o_logit, true);

$results = new stdClass();
$results->success = FALSE;
$results->errortext = null;
$cancontinue = TRUE;

if (key_exists('dlStudent', $_GET)) {
	$o_logit->logit('displaying add a rank page');
	$stu_index = $_GET["dlStudent"];

	// basic student info
	$theq = "select *,";
	$theq .= "split_part(age(birthday)::text,' ',1)||' '||split_part(age(birthday)::text,' ',2)as age,";
	$theq .= "split_part(age(start_date)::text,' ',1)||' '||split_part(age(start_date)::text,' ',2)as trainingage";
	$theq .= ' from students s ';
	$theq .= ' left join sysdef.student_type st on st.short_name=s.student_type ';
	$theq .= ' where s.stu_index=:stu_index';
	$cancontinue = $dbconn->fetchIt($theq, array(':stu_index' => $stu_index),
		$studentdata, true);

	//if ($stu_index == -1) {
	$studentranks[0] = new stdClass();
	$studentranks[0]->fed_id = '<input type="text" name="fed_id" size="5" placeholder="MC16-05">';
	$studentranks[0]->clt_description = CreateLimitedArtCB($dbconn, $logname, $stu_index);
	$studentranks[0]->test_date = '<input type="date" name="test_date" value="' . date('Y-m-d') . '">';
	$studentranks[0]->srk_description = '<select name="rankvalue">';
	for ($i = 12; $i > 0; $i--) {
		$studentranks[0]->srk_description .= ' <option value="' . $i . '">' . abs($i) . ' Kup </option>';
	}
	for ($i = -1; $i > -10; $i--) {
		$studentranks[0]->srk_description .= ' <option value="' . $i . '">' . abs($i) . ' Dan </option>';
	}
	$studentranks[0]->srk_description .= '</select>';

	// } else {
	//     $theq = 'select *';

	//     $theq .= ' from students s ';
	//     $theq .= ' left join ranks r on r.stu_index=s.stu_index ';
	//     $theq .= ' left join sysdef.rank_names rn on rn.srk_index=r.srk_index ';
	//     $theq .= ' left join sysdef.class_type ct on ct.clt_index=rn.clt_index ';
	//     $theq .= ' where s.stu_index=:stu_index';
	//     $theq .= ' order by clt_seq,srk_seq';
	//     $cancontinue=$dbconn->fetchIt($theq,array(':stu_index' => $stu_index),
	//         $studentranks,true);
	// }

	$_SESSION['addrankbutton'] = '<form action="addarank.php" method="post">' . //
	'<input type="hidden" name="dlStudent" value="' . $stu_index . '">' . //
	'<input class="button" type="submit" value=" Add This Rank " />';

	$_SESSION['buttoneditstudent'] = '';
	$_SESSION['clientdefaults']['pagetitle'] = 'Add a Rank';
	$thehtml = LoadTheHTML(null, 'page_addarank',
		array('shared_detail_ranks' => $studentranks,
			'shared_student' => $studentdata), $o_logit, 1, 1);

	echo $thehtml;

	$_SESSION['errortext'] = '';

} elseif (key_exists('dlStudent', $_POST)) {
	$o_logit->logit('user hit the "add this rank" button.');
	// write updates to database and go backto edit student screen
	$stu_index = $_POST["dlStudent"];

	//figure out what the srk_index must be
	$theq = 'select * from sysdef.rank_names';
	$theq .= ' where clt_index=:clt_index';
	$theq .= ' and srk_seq=:srk_seq';
	$cancontinue = $dbconn->fetchIt($theq, array(
		':clt_index' => clean_user_input($_POST['artid']),
		':srk_seq' => clean_user_input($_POST['rankvalue'])), $rows, true);
	if ($dbconn->records == 0) {
		$_SESSION['errortext'] =
			"There's no \"name\" defined for that rank/art combination.<br>" .
			"This is either a data error, or you selected a rank that<br>" .
			"does not exist for that art.";
		$o_logit->logit('user selected invalid rank/art combination.');
		$o_logit->logit('going back to addarank.php');
		header('Location: addarank.php?dlStudent=' . $stu_index);
	} else {
		$srk_index = $rows[0]->srk_index;

		$theq = "insert into ranks (stu_index, srk_index, test_date, fed_id, current_rank) values";
		$theq .= "(:stu_index, :srk_index, :test_date, :fed_id, true)";
		$cancontinue = $dbconn->executeIt($theq, array(
			':stu_index' => $stu_index,
			':srk_index' => $srk_index,
			':test_date' => clean_user_input($_POST['test_date']),
			':fed_id' => clean_user_input($_POST['fed_id'])), true);

		$o_logit->logit('going back to main.php');
		header('Location: main.php?dlStudent=' . $stu_index);
	}
}
?>