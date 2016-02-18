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

static $logname = 'editstudent';
startthelog($logname, TRUE);
logit($logname, 'Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$dbconn = PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $logname);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

if (key_exists('dlStudent', $_GET)) {
    $stu_index = $_GET["dlStudent"];
    // create a pg conection

    $_SESSION['buttoneditstudent'] = '  <form action="editstudent.php" method="post">';
    $_SESSION['buttoneditstudent'] .= '  <input class="button" type="submit" value=" Save Changes " />';
    $_SESSION['buttoneditstudent'] .= '  <input type="hidden" name="dlStudent" value="' . $stu_index . '" />';

    // basic student info
    $theq = "select stu_index,";
    $theq .= ColAsInputField("last_name") . ',';
    $theq .= ColAsInputField("first_name") . ',';
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
    $theq .= ColAsInputField("birthday", '8', 'now()') . ',';
    $theq .= ColAsInputField("start_date", '8', 'now()') . ',';
    $theq .= ColAsInputField("phone1") . ',';
    $theq .= ColAsInputField("phone1_type", '3') . ',';
    $theq .= ColAsInputField("phone2") . ',';
    $theq .= ColAsInputField("phone2_type", '3') . ',';
    $theq .= ColAsInputField("phone3") . ',';
    $theq .= ColAsInputField("phone3_type", '3') . ',';
    $theq .= ColAsInputField("email", '30') . ',';
    $theq .= "  student_type, sex, primary_contact,age(birthday) as age, age(start_date) as trainingage";
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



    $theq = "  select distinct stt_index,s1.student_type,stt_description, s2.student_type as thisstudent";
    $theq .= ' from students s1';
    $theq .= ' join sysdef.student_type on short_name=s1.student_type';
    $theq .= ' left join (select student_type from students where stu_index=:stu_index) s2 on s1.student_type=s2.student_type';
    $theq .= ' order by stt_index';
    try {
        $pdoquery = $dbconn -> prepare($theq);
        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
        $pdoquery -> execute(array(':stu_index' => $stu_index));
        $studenttypes = $pdoquery -> fetchAll();
        $studentdata[0] -> stt_description = "<select name=\"cbstudenttype\">";
        foreach ($studenttypes as $key => $value) {
            if ($value -> thisstudent != '') {
                $studentdata[0] -> stt_description .= "<option value=\"" . $value -> student_type . "\" selected>" . $value -> stt_description . "</option>";
            } else {
                $studentdata[0] -> stt_description .= "<option value=\"" . $value -> student_type . "\">" . $value -> stt_description . "</option>\n";
            }
        }
        $studentdata[0] -> stt_description .= "</select>";

    } catch (PDOException $e) {
        logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
        $results -> errortext = $e -> getMessage();
        $cancontinue = FALSE;
    }


    /*
     "P";"V.I.P."
     "A";"Active"
     "APC";"Active (Punch-card)"
     "ANP";"Active (Non-paying)"
     "APW";"Active (Pee Wee)"
     "LOA";"Leave of Absence"
     "DNE";"Did not Enroll"
     "P W";"Prospect for WISE"
     "WISE";"WISE Student"
     "P BP";"Prospect from BP"
     "P PH";"Prospect from phone"
     "P M";"Prospect from Movie"
     "P E";"Prospect from Expo"
     "R";"Rolledex entry"

     *
     */

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
        $pdoquery = $dbconn -> prepare($theq);
        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
        $pdoquery -> execute(array(':stu_index' => $stu_index));

        $notes[0] = new stdClass();
        $notes[0] -> note_timestamp = 'New Note';
        $notes[0] -> employee = $_SESSION["initials"];
        $notes[0] -> note_text = ' <input type="text" name="note-note_text" size="100%">';

        $i = 1;
        while ($noterow = $pdoquery -> fetch()) {
            $notes[$i] = $noterow;
            $i++;
        }

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
        $pdoquery = $dbconn -> prepare($theq);
        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
        $pdoquery -> execute(array(':stu_index' => $stu_index));

        $medalert[0] = new stdClass();
        $medalert[0] -> note_timestamp = 'New Alert';
        $medalert[0] -> employee = ' <input type="hidden" name="ma-employee" value="MA*">';
        $medalert[0] -> note_text = ' <input type="text" name="ma-note_text" size="100%">';

        $i = 1;
        while ($noterow = $pdoquery -> fetch()) {
            $medalert[$i] = $noterow;
            $i++;
        }
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
    'detail_medicalalert' => $medalert, //
    'detail_notes' => $notes//
    ), $logname, 1, 1);

    echo $thehtml;




} elseif (key_exists('dlStudent', $_POST)) {
    // write updates to database and go backto student selection screen
    $stu_index = $_POST["dlStudent"];

    $theq = 'update students set';
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
    try {
        $pdoquery = $dbconn -> prepare($theq);
        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
        $pdoquery -> execute(array(':stu_index' => $stu_index, //
        ":cbstudenttype" => clean_user_input($_POST["cbstudenttype"]), //
        ":last_name" => clean_user_input($_POST["last_name"]), //
        ":first_name" => clean_user_input($_POST["first_name"]), //
        ":middle_name" => clean_user_input($_POST["middle_name"]), //
        ":f_last_name" => clean_user_input($_POST["f_last_name"]), //
        ":f_first_name" => clean_user_input($_POST["f_first_name"]), //
        ":f_middle_name" => clean_user_input($_POST["f_middle_name"]), //
        ":m_last_name" => clean_user_input($_POST["m_last_name"]), //
        ":m_first_name" => clean_user_input($_POST["m_first_name"]), //
        ":m_middle_name" => clean_user_input($_POST["m_middle_name"]), //
        ":address" => clean_user_input($_POST["address"]), //
        ":city" => clean_user_input($_POST["city"]), //
        ":state" => clean_user_input($_POST["state"]), //
        ":zip" => clean_user_input($_POST["zip"]), //
        ":birthday" => clean_user_input($_POST["birthday"]), //
        ":start_date" => clean_user_input($_POST["start_date"]), //
        ":phone1" => clean_user_input($_POST["phone1"]), //
        ":phone1_type" => clean_user_input($_POST["phone1_type"]), //
        ":phone2" => clean_user_input($_POST["phone2"]), //
        ":phone2_type" => clean_user_input($_POST["phone2_type"]), //
        ":phone3" => clean_user_input($_POST["phone3"]), //
        ":phone3_type" => clean_user_input($_POST["phone3_type"]), //
        ":email" => clean_user_input($_POST["email"])));
    } catch (PDOException $e) {
        logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
        $results -> errortext = $e -> getMessage();
        $cancontinue = FALSE;
    }

    //notes
    if ($_POST["note-note_text"] != '') {
        $theq = 'insert into notes(employee, note_text, stu_index, note_timestamp) values';
        $theq .= ' (:employee,:note_text,:stu_index,now())';

        try {
            $pdoquerynotes = $dbconn -> prepare($theq);
            $pdoquerynotes -> execute(array(':stu_index' => $stu_index, //
            ":employee" => $_SESSION["initials"], //
            ":note_text" => clean_user_input($_POST["note-note_text"])));

        } catch (PDOException $e) {
            logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
            $results -> errortext = $e -> getMessage();
            $cancontinue = FALSE;
        }
    }

    //medical alerts
    if ($_POST["ma-note_text"] != '') {
        $theq = 'insert into notes(employee, note_text, stu_index, note_timestamp) values';
        $theq .= " ('*MA',:note_text,:stu_index,now())";

        try {
            $pdoquerynotes = $dbconn -> prepare($theq);
            $pdoquerynotes -> execute(array(':stu_index' => $stu_index, //
            ":note_text" => clean_user_input($_POST["ma-note_text"])));

        } catch (PDOException $e) {
            logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
            $results -> errortext = $e -> getMessage();
            $cancontinue = FALSE;
        }
    }


    logit($logname, 'going back to main.php');
    header('Location: main.php?dlStudent='.$stu_index);
    exit ;


}
?>