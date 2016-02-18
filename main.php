<?php

session_start();
if (!isset($_SESSION["userid"])) {
    header('Location: login.php');
    exit ;
}

$starttime = microtime(TRUE);
if (PHP_OS == 'WINNT') {
    require 'C:\inetpub\phplib\logitv2.php';
    require 'C:\inetpub\phplib\PDOconnectDB.php';
    require 'C:\inetpub\phplib\cleanuserinput.php';
    require 'C:\inetpub\phplib\wc2lib.php';
} else {
    require '/var/www/phplib/logitv2.php';
    require '/var/www/phplib/PDOconnectDB.php';
    require '/var/www/phplib/cleanuserinput.php';
    require '/var/www/phplib/wc2lib.php';
}

static $logname = 'main';
startthelog($logname, TRUE);
logit($logname, 'Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

// create a pg conection
$dbconn = PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $logname);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

$stu_index = $_GET["dlStudent"];


$_SESSION['buttoneditstudent'] = '  <form action="editstudent.php">';
$_SESSION['buttoneditstudent'] .= '  <input class="button" type="submit" value=" Edit Student " />';
$_SESSION['buttoneditstudent'] .= '  <input type="hidden" name="dlStudent" value="' . $stu_index . '" />';



// basic student info
$theq = "select *,age(birthday) as age,age(start_date) as trainingage";
$theq .= ' from students s ';
$theq .= ' left join sysdef.student_type st on st.short_name=s.student_type ';
$theq .= ' where s.stu_index=:stu_index';
try {
    $pdoquery = $dbconn -> prepare($theq);
    $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
    $pdoquery -> execute(array(':stu_index' => $stu_index));
    $studentdata = $pdoquery -> fetchAll();
} catch (PDOException $e) {
    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
    $results -> errortext = $e -> getMessage();
    $cancontinue = FALSE;
}

//rank info
$theq = 'select * from students s ';
$theq .= ' left join ranks r on r.stu_index=s.stu_index ';
$theq .= ' left join sysdef.rank_names rn on rn.srk_index=r.srk_index ';
$theq .= ' left join sysdef.class_type ct on ct.clt_index=rn.clt_index ';
$theq .= ' where s.stu_index=:stu_index';
$theq .= ' order by clt_seq,srk_seq';
try {
    $pdoqueryranks = $dbconn -> prepare($theq);
    $pdoqueryranks -> setFetchMode(PDO::FETCH_OBJ);
    $pdoqueryranks -> execute(array(':stu_index' => $stu_index));
} catch (PDOException $e) {
    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
    $results -> errortext = $e -> getMessage();
    $cancontinue = FALSE;
}

//notes
$theq = 'select * from notes ';
$theq .= ' where stu_index=:stu_index';
$theq .= " and employee<>'*MA'";
$theq .= ' order by note_timestamp desc';
try {
    $pdoquerynotes = $dbconn -> prepare($theq);
    $pdoquerynotes -> setFetchMode(PDO::FETCH_OBJ);
    $pdoquerynotes -> execute(array(':stu_index' => $stu_index));
} catch (PDOException $e) {
    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
    $results -> errortext = $e -> getMessage();
    $cancontinue = FALSE;
}

//medical alerts
$theq = 'select * from notes ';
$theq .= ' where stu_index=:stu_index';
$theq .= " and employee='*MA'";
$theq .= ' order by note_timestamp desc';
try {
    $pdoquerymed = $dbconn -> prepare($theq);
    $pdoquerymed -> setFetchMode(PDO::FETCH_OBJ);
    $pdoquerymed -> execute(array(':stu_index' => $stu_index));
} catch (PDOException $e) {
    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
    $results -> errortext = $e -> getMessage();
    $cancontinue = FALSE;
}

$theq = 'select * from contracts c ';
$theq .= ' join sysdef.programs p on p.pro_index=c.pro_index ';
$theq .= ' join transactions t on c.con_index=t.con_index ';
$theq .= ' where stu_index=:student';
$theq .= '   and c.active=true';
$theq .= ' order by start_date desc';
try {
    $pdoqueryactive = $dbconn -> prepare($theq);
    $pdoqueryactive -> setFetchMode(PDO::FETCH_OBJ);
    $pdoqueryactive -> execute(array(':student' => $stu_index));
    $contractsa = $pdoqueryactive -> fetchAll();
} catch (PDOException $e) {
    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
    $results -> errortext = $e -> getMessage();
    $cancontinue = FALSE;
}

$theq = 'select * from contracts c ';
$theq .= ' join sysdef.programs p on p.pro_index=c.pro_index ';
$theq .= ' join transactions t on c.con_index=t.con_index ';
$theq .= ' where stu_index=:student';
$theq .= '   and c.active=false';
$theq .= ' order by start_date desc';
try {
    $pdoqueryinactive = $dbconn -> prepare($theq);
    $pdoqueryinactive -> setFetchMode(PDO::FETCH_OBJ);
    $pdoqueryinactive -> execute(array(':student' => $stu_index));
    $contractsi = $pdoqueryinactive -> fetchAll();
} catch (PDOException $e) {
    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
    $results -> errortext = $e -> getMessage();
    $cancontinue = FALSE;
}

$_SESSION['clientdefaults']['pagetitle'] = 'Student Info';

$thehtml = LoadTheHTML('page_main', array(//
'shared_student' => $studentdata, //
'shared_parents' => $studentdata, //
'shared_contact' => $studentdata, //
'detail_ranks' => $pdoqueryranks -> fetchAll(), //
'header_contractsa' => $contractsa, //
'detail_contractsa' => $contractsa, //
'header_contractsi' => $contractsi, //
'detail_contractsi' => $contractsi, //
'detail_medicalalert' => $pdoquerymed -> fetchAll(), //
'detail_notes' => $pdoquerynotes -> fetchAll()//
), $logname, 1, 1);

echo $thehtml;
?>