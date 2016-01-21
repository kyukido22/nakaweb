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

static $logname = 'recordtest';
startthelog($logname, TRUE);

$dbconn = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname);
GetTheHTMLs('EN-US', 0, $dbconn, $logname);

logit($logname, 'Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

// create a pg conection
$dbconn = PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $logname);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_POST['step'] == 'recordit') {
        /*
         * record the test
         */

        // get invoiceid
        $dbconnw = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname);
        $theq = " insert into invoices (schoolid, invoicedate, invoiceamount) values";
        $theq .= " (:schoolid, now(), :invoiceamount)";
        $theq .= " returning invoiceid";
        try {
            $pdoquery = $dbconnw -> prepare($theq);
            $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
            $pdoquery -> execute(array(//
            ':schoolid' => $_SESSION['clientdefaults']['clientid'], //
            ':invoiceamount' => $_SESSION['testfees']));
            $row = $pdoquery -> fetch();
            $invoiceid = $row -> invoiceid;
        } catch (PDOException $e) {
            logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
            $results -> errortext = $e -> getMessage();
            $cancontinue = FALSE;
        }

        $theq = " insert into tests (schoolid, invoiceid, testdate, artid)";
        $theq .= " values (:schoolid, :invoiceid, :testdate, :artid)";
        try {
            $pdoquery = $dbconnw -> prepare($theq);
            $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
            $pdoquery -> execute(array(//
            ':schoolid' => $_SESSION['clientdefaults']['clientid'], //
            ':artid' => $_SESSION['artid'], //
            ':testdate' => $_SESSION['testdate'], //
            ':invoiceid' => $invoiceid));
        } catch (PDOException $e) {
            logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
            $results -> errortext = $e -> getMessage();
            $cancontinue = FALSE;
        }

        foreach ($_SESSION['testdetails'] as $key => $value) {
            logit($logname, '  updataing:' . $value["stu_index"] . $value["first_name"] . ' ' . $value["last_name"] . ' new rank:' . $value["newsrk_index"]);
            try {
                //clear current_rank flag on old rank(s)
                $theq = " update ranks set current_rank=false";
                $theq .= " where stu_index=:stu_index";
                $theq .= " and srk_index in (select srk_index from";
                $theq .= " sysdef.rank_names where clt_index=:artid)";
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute(array(//
                ':artid' => $_SESSION['artid'], //
                ':stu_index' => $value["stu_index"]));

                // insert the new rank record
                $theq = " insert into ranks ( stu_index, srk_index, test_date, current_rank, invoiceid)";
                $theq .= " values (:stu_index, :srk_index, :test_date, true, :invoiceid)";
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute(array(//
                ':srk_index' => $value["newsrk_index"], //
                ':test_date' => $_SESSION['testdate'], //
                ':invoiceid' => $invoiceid, //
                ':stu_index' => $value["stu_index"]));
            } catch (PDOException $e) {
                logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                $results -> errortext = $e -> getMessage();
                $cancontinue = FALSE;
            }

        }

        unset($_SESSION['testdetails']);
        logit($logname, 'going back to school');
        header('Location: school.php');
        exit ;
    }

    if ($_POST['step'] == 'verifyit') {

        //get rank names for selected art
        $theq = " select srk_seq,srk_description,srk_index";
        $theq .= " from sysdef.rank_names rn";
        $theq .= " where clt_index=:clt_index";
        $theq .= " and active=true";
        $theq .= " order by srk_seq";
        try {
            $pdoquery = $dbconn -> prepare($theq);
            $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
            $pdoquery -> execute(array(':clt_index' => $_SESSION['recordtestartid']));
            $i = 0;
            while ($row = $pdoquery -> fetch()) {
                //			$ranknames[$i] = $row -> srk_description;
                //$rankindexs[$i] = $row -> srk_index;
                //$rankseq[$i] = $row -> srk_seq;
                $ranknames[$row -> srk_seq] = $row -> srk_description;
                $rankindexs[$row -> srk_seq] = $row -> srk_index;
                //$rankseq[$i] = $row -> srk_seq;
                $i++;
            }
            //var_dump($rankindexs);
        } catch (PDOException $e) {
            logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
            $results -> errortext = $e -> getMessage();
            $cancontinue = FALSE;
        }

        //get rank fees
        $dbconn = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname);
        $theq = " select torank,fee";
        $theq .= " from testfees";
        $theq .= " order by torank";
        try {
            $pdoquery = $dbconn -> prepare($theq);
            $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
            $pdoquery -> execute();
            while ($row = $pdoquery -> fetch()) {
                $testfees[$row -> torank] = $row -> fee;
            }
            //var_dump($testfees);
        } catch (PDOException $e) {
            logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
            $results -> errortext = $e -> getMessage();
            $cancontinue = FALSE;
        }

        // load data to be displayed
        $i = 0;
        $_SESSION['testfees'] = 0;
        foreach ($_SESSION["activestudents"] as $key => $value) {
            // loop though all the students to see if they tested
            if (isset($_POST["tested" . $value['stu_index']])) {

                $studentdata[$i]['first_name'] = $value['first_name'];
                $studentdata[$i]['last_name'] = $value['last_name'];
                $studentdata[$i]['srk_description'] = $value['srk_description'];
                $studentdata[$i]['stu_index'] = $value['stu_index'];
                $studentdata[$i]['srk_seq'] = $value['srk_seq'];
                $studentdata[$i]['srk_index'] = $value['srk_index'];
                if ($_POST['skipped' . $value['stu_index']] == '') {
                    $skip = 1;
                } else {
                    $skip = $_POST['skipped' . $value['stu_index']];
                }

                //if moving from kup to dan need to skip "0"
                //echo $studentdata[$i]['srk_seq'] . ' ' .
                // ($studentdata[$i]['srk_seq'] - $skip) . '<br>';
                if (($studentdata[$i]['srk_seq'] > 0) and ($studentdata[$i]['srk_seq'] - $skip <= 0)) {
                    $skip++;
                    //		echo 'did it';
                }

                // get the srk_index of the new rank
                // locate the index in the array then subract 1 (or the amount of
                // ranks skipped)
                $newrankindex = array_search($studentdata[$i]['srk_index'], $rankindexs) - $skip;

                $studentdata[$i]['recordtestcheckbox'] = '<td>' . $ranknames[$newrankindex] . '</td>';
                $studentdata[$i]['newsrk_index'] = $rankindexs[$newrankindex];

                // calculate test fees
                // rank seq counts down from 11 to 1 then negative for black belt
                // ranks, 0 isnt used
                //echo $studentdata[$i]['first_name'] . ' ' .
                // $studentdata[$i]['srk_seq']	 . ' ' . $rankseq[$newrankindex] .
                // '<Br>';
                $totalfee = 0;
                for ($j = $studentdata[$i]['srk_seq'] - 1; $j > $studentdata[$i]['srk_seq'] - $skip - 1; $j--) {
                    //echo $j . ' ' . $studentdata[$i]['srk_seq'] . ' ' .
                    // $rankseq[$newrankindex] . ' '.$testfees[$j].'<Br>';
                    if (isset($testfees[$j])) {
                        $totalfee = $totalfee + $testfees[$j];
                    }
                }
                $_SESSION['testfees'] = $_SESSION['testfees'] + $totalfee;
                $studentdata[$i]['recordtestskipped'] = '<td>$' . number_format($totalfee, 2, '.', '') . '</td>';
                $i++;
            }
        }

        //remember who tested
        $_SESSION['testdetails'] = $studentdata;
        $_SESSION['step'] = 'recordit';
        $_SESSION['recordtestbuttontitle'] = ' Submit Test ';
        $_SESSION['recordtestcol1name'] = 'New Rank';
        $_SESSION['recordtestcol2name'] = 'Total Fee';
        //	var_dump($_SESSION['testdetails']);
    }
} else {
    //	was a GET
    /*
     * display all the student's to be promoted
     */

    $_SESSION['artid'] = clean_user_input($_GET['artid']);
    $_SESSION['testdate'] = clean_user_input($_GET['testdate']);

    // display check list of student's
    SetupSortingSessionVals('recordtest', array('last_name', 'first_name'), $logname);

    // active students and their ranks
    $theq = " select s.stu_index,first_name,last_name,srk_description,srk_seq,";
    $theq .= ' \'<td><input type="checkbox" name="tested\'||s.stu_index||\'"></td>\' as recordtestcheckbox,';
    $theq .= ' \'<td><input name="skipped\'||s.stu_index||\'"></td>\' as recordtestskipped, r.srk_index';
    $theq .= " from students s";
    $theq .= " join ranks r on s.stu_index=r.stu_index";
    $theq .= " join sysdef.rank_names rn on rn.srk_index=r.srk_index";
    $theq .= " join sysdef.class_type ct on ct.clt_index=rn.clt_index";
    $theq .= " where current_rank=true";
    $theq .= " and student_type in ('A','ANP','APC')";
    $theq .= " and ct.clt_index=:artid";
    $theq .= ' order by last_name,first_name';
    logit($logname, $theq);
    try {
        $pdoquery = $dbconn -> prepare($theq);
        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
        $pdoquery -> execute(array(':artid' => $_SESSION['artid']));

        // ?? why assiing another session var to the art id??
        $_SESSION['recordtestartid'] = clean_user_input($_GET['artid']);
        $studentdata = $pdoquery -> fetchAll();
        unset($_SESSION['activestudents']);
        $i = 0;
        foreach ($studentdata as $key => $value) {
            //echo $value -> stu_index.' ';
            $_SESSION['activestudents'][$i]['stu_index'] = $value -> stu_index;
            $_SESSION['activestudents'][$i]['first_name'] = $value -> first_name;
            $_SESSION['activestudents'][$i]['last_name'] = $value -> last_name;
            $_SESSION['activestudents'][$i]['srk_description'] = $value -> srk_description;
            $_SESSION['activestudents'][$i]['srk_index'] = $value -> srk_index;
            $_SESSION['activestudents'][$i]['srk_seq'] = $value -> srk_seq;
            $i++;
        }
    } catch (PDOException $e) {
        logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
        $results -> errortext = $e -> getMessage();
        $cancontinue = FALSE;
    }

    $_SESSION['step'] = 'verifyit';
    $_SESSION['recordtestbuttontitle'] = ' Verify Ranks ';
    $_SESSION['recordtestcol1name'] = 'Tested';
    $_SESSION['recordtestcol2name'] = 'Skipped';
}

$_SESSION['clientdefaults']['pagetitle'] = 'Record Test';

$thehtml = LoadTheHTML('page_recordtest', array(//
'detail_recordtest' => $studentdata), $logname, 1, 1);

echo $thehtml;
?>
