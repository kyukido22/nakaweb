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
if (PHP_OS == 'WINNT') {
    require 'C:\inetpub\phplib\logitv2.php';
    require 'C:\inetpub\phplib\PDOconnectDB.php';
    require 'C:\inetpub\phplib\wc2lib.php';
    $tempfileloc='c:\temp\\';
} else {
    require '/var/www/phplib/logitv2.php';
    require '/var/www/phplib/PDOconnectDB.php';
    require '/var/www/phplib/wc2lib.php';
    $tempfileloc='/tmp/';
}

static $logname = 'selectstudent';
startthelog($logname, TRUE);
logit($logname, 'Client:' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

$dbconn = PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $logname, true);
if (!isset($_SESSION['errormessage'])) {
    $_SESSION['errormessage'] = '';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // upload file logic
    if (isset($_FILES['image'])) {
        $file_name = $_FILES['image']['name'];
        $file_size = $_FILES['image']['size'];
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_type = $_FILES['image']['type'];
        $file_ext = explode('.', $file_name);
        $file_ext = strtolower(end($file_ext));
        logit($logname, '    File selected: ' . $file_name . ' ' . $file_size . ' ' . $file_tmp . ' ' . $file_ext);


        if ($file_ext != 'csv') {
            $_SESSION['errormessage'] = "Invalid file type, please choose a CSV file.";
            logit($logname, '  invalid file type');
        }

        if ($file_size > 1048576) {
            $_SESSION['errormessage'] = 'File size must be less than 1 MB';
            logit($logname, '  file to big');
        }

        if ($_SESSION['errormessage'] == '') {
            move_uploaded_file($file_tmp, $tempfileloc . $file_name);
            $_SESSION['errormessage'] = "File uploaded.";
            logit($logname, '  file uploaded');

            // now try to import the list
            try {
                $theq = "drop table if exists studentimport_a;";
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = "create temp table studentimport_a (theline text);";
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = "copy studentimport_a from '".$tempfileloc. $file_name . "';";
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = "delete from studentimport_a where theline like '%first_name%';";
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = 'update studentimport_a set theline=replace(theline,\',"",\',\',~N~,\');';
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = 'update studentimport_a set theline=replace(theline,\',""\',\',~N~\');';
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = 'update studentimport_a set theline=replace(theline,\',,\',\',~N~,\');';
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = 'update studentimport_a set theline=replace(theline,\',,\',\',~N~,\');';
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = "copy studentimport_a to '".$tempfileloc."studentload_a.csv';";
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();


                $theq = 'drop table if exists studentimport';
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = 'create temp table studentimport (';
                $theq .= 'student_type character varying(5),';
                $theq .= 'last_name character varying(20),';
                $theq .= 'first_name character varying(15),';
                $theq .= 'middle_name character varying(15),';
                $theq .= 'sex character varying(1),';
                $theq .= 'm_last_name character varying(20),';
                $theq .= 'm_first_name character varying(15),';
                $theq .= 'm_middle_name character varying(15),';
                $theq .= 'f_last_name character varying(20),';
                $theq .= 'f_first_name character varying(15),';
                $theq .= 'f_middle_name character varying(15),';
                $theq .= 'primary_contact character varying(1),';
                $theq .= 'address character varying(50),';
                $theq .= 'city character varying(20),';
                $theq .= 'state character varying(2),';
                $theq .= 'zip character varying(10),';
                $theq .= 'birthday date,';
                $theq .= 'start_date date,';
                $theq .= 'phone1 character varying(20),';
                $theq .= 'phone1_type character varying(5),';
                $theq .= 'phone2 character varying(20),';
                $theq .= 'phone2_type character varying(5),';
                $theq .= 'phone3 character varying(20),';
                $theq .= 'phone3_type character varying(5),';
                $theq .= 'email character varying(50),';
                $theq .= 'user_text_1 character varying(100),';
                $theq .= 'user_text_2 character varying(100),';
                $theq .= 'user_text_3 character varying(100),';
                $theq .= 'user_text_4 character varying(100),';
                $theq .= 'user_text_5 character varying(100),';
                $theq .= 'user_date_1 date,';
                $theq .= 'user_date_2 date,';
                $theq .= 'user_date_3 date,';
                $theq .= 'user_date_4 date,';
                $theq .= 'user_date_5 date,';
                $theq .= 'user_num_1 numeric(7,2),';
                $theq .= 'user_num_2 numeric(7,2),';
                $theq .= 'user_num_3 numeric(7,2),';
                $theq .= 'user_num_4 numeric(7,2),';
                $theq .= 'user_num_5 numeric(7,2),';
                $theq .= 'art character varying(5),';
                $theq .= 'kup smallint,';
                $theq .= 'test_date date,';
                $theq .= 'fed_id character varying(10))';
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = "copy studentimport from '".$tempfileloc."studentload_a.csv' CSV Header null '~N~';";
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $theq = 'alter table studentimport';
                $theq .= ' add column stu_index integer NOT NULL DEFAULT nextval(\'seq_students\')';
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();


                $theq = 'insert into students (stu_index,student_type, last_name, first_name,';
                $theq .= '  middle_name, sex, m_last_name, m_first_name, m_middle_name,';
                $theq .= '  f_last_name, f_first_name, f_middle_name, primary_contact, address,';
                $theq .= '  city, state, zip, birthday, start_date, phone1, phone1_type,';
                $theq .= '  phone2, phone2_type, phone3, phone3_type, email,';
                $theq .= '  user_text_1, user_text_2, user_text_3, user_text_4, user_text_5,';
                $theq .= '  user_date_1, user_date_2, user_date_3, user_date_4, user_date_5,';
                $theq .= '  user_num_1, user_num_2, user_num_3, user_num_4, user_num_5)';
                $theq .= ' select  stu_index,student_type, last_name, first_name, middle_name, sex,';
                $theq .= '  m_last_name, m_first_name, m_middle_name, f_last_name,';
                $theq .= '  f_first_name, f_middle_name, primary_contact, address,';
                $theq .= '  city, state, zip, birthday, start_date, phone1, phone1_type,';
                $theq .= '  phone2, phone2_type, phone3, phone3_type, email,';
                $theq .= '  user_text_1, user_text_2, user_text_3, user_text_4, user_text_5,';
                $theq .= '  user_date_1, user_date_2, user_date_3, user_date_4, user_date_5,';
                $theq .= '  user_num_1, user_num_2, user_num_3, user_num_4, user_num_5';
                $theq .= ' from studentimport';
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $_SESSION['errormessage'] = $pdoquery -> rowCount() . ' Students added.';

                $theq = 'insert into ranks(stu_index, srk_index,';
                $theq .= ' test_date, fed_id, current_rank)';
                $theq .= ' select stu_index,srk_index,test_date,fed_id,true';
                $theq .= ' from studentimport i';
                $theq .= ' join sysdef.class_type c on c.short_name=i.art';
                $theq .= ' join sysdef.rank_names r on r.clt_index=c.clt_index and srk_seq=kup';
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute();
                $_SESSION['errormessage'] .= '<br>' . $pdoquery -> rowCount() . ' Ranks added.';

            } catch (PDOException $e) {
                logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                $results -> errortext = $e -> getMessage();
                $_SESSION['errormessage'] = $results -> errortext;
                $cancontinue = FALSE;
            }


        }
    }

    logit($logname, 'refresing browswer with GET');
    header('Location: selectstudent.php');
    exit ;
}

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

$_SESSION['activestudents'] = '<form action="main.php">' . //
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

$_SESSION['inactivestudents'] = '<form action="main.php">' . //
"<select name=\"dlStudent\" onchange=\"this.form.submit()\">" . //
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

$_SESSION['errormessage'] = '';

echo $thehtml;
?>
