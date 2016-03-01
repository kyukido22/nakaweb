<?php

session_start();
if (!isset($_SESSION["userid"])) {
    header('Location: login.php');
    exit ;
}

$starttime = microtime(TRUE);
if (PHP_OS == 'WINNT') {
    require_once 'C:\inetpub\phplib\logitv2.php';
    require_once 'C:\inetpub\phplib\PDOconnectDB.php';
    require_once 'C:\inetpub\phplib\cleanuserinput.php';
    require_once 'C:\inetpub\phplib\wc2lib.php';
    require_once 'C:\inetpub\phplib\PHPExcel-1.8\Classes\PHPExcel.php';
    require_once 'C:\inetpub\phplib\PHPMailer-master\class.phpmailer.php';
    $xlsstore = 'C:\inetpub\phplogs\invoices\\';
    $images = '.\images\\';
} else {
    require_once '/var/www/phplib/logitv2.php';
    require_once '/var/www/phplib/PDOconnectDB.php';
    require_once '/var/www/phplib/cleanuserinput.php';
    require_once '/var/www/phplib/wc2lib.php';
    require_once '/var/www/phplib/PHPExcel-1.8/Classes/PHPExcel.php';
    require_once '/var/www/phplib/PHPMailer-master/class.phpmailer.php';
    $xlsstore = '/tmp/invoices/';
    $images = './images/';
}

static $logname = 'recordtest';
startthelog($logname, TRUE);

$dbconnw = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname);
$dbconn = PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $logname);
GetTheHTMLs('EN-US', 0, $dbconnw, $logname);

logit($logname, 'Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

// create a pg conection
$dbconn = PDOconnect($_SESSION["clientdefaults"]["dbname"], $_SESSION["clientdefaults"]["host"], $logname);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;
$_SESSION['feestring'] = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_POST['step'] == 'recordit') {

        /*
         * record the test
         */

        // get invoiceid
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

        //get rank fees
        $dbconn = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname);
        $theq = " select torank,fee";
        $theq .= " from testfees";
        $theq .= " order by torank";
        try {
            $pdoquery = $dbconnw -> prepare($theq);
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

        //create invoice now so we can write into it when interating though
        // students
        $objPHPExcel = new PHPExcel();

        // Set document properties
        $objPHPExcel -> getProperties() -> setCreator("NAKA") -> setTitle("NAKA Invoice #" . $invoiceid);

        //set headers
        $row = 18;
        $objPHPExcel -> getActiveSheet() -> getStyle('A' . $row . ':E' . $row) -> getFont() -> setBold(true);
        $objPHPExcel -> getActiveSheet() -> setCellValue('A' . $row, 'STUDENT NAME');
        $objPHPExcel -> getActiveSheet() -> setCellValue('B' . $row, 'TESTS');
        $objPHPExcel -> getActiveSheet() -> setCellValue('C' . $row, 'NEW RANK');
        $objPHPExcel -> getActiveSheet() -> setCellValue('D' . $row, 'FED FEE');
        $objPHPExcel -> getActiveSheet() -> setCellValue('E' . $row, 'TOTAL FEES');
        $objPHPExcel -> getActiveSheet() -> getColumnDimension('A') -> setWidth(25);
        $objPHPExcel -> getActiveSheet() -> getColumnDimension('B') -> setWidth(12);
        $objPHPExcel -> getActiveSheet() -> getColumnDimension('C') -> setWidth(15);
        $objPHPExcel -> getActiveSheet() -> getColumnDimension('D') -> setWidth(10);
        $objPHPExcel -> getActiveSheet() -> getColumnDimension('E') -> setWidth(12);

        $row++;

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
            logit($logname, '  updataing:' . $value["stu_index"] . ' ' . $value["first_name"] . ' ' . $value["last_name"] . '    To new rank:' . $value["newsrk_index"]);
            try {
                //check for existing rankid
                $theq = " select fed_id from ranks";
                $theq .= " where stu_index=:stu_index";
                $theq .= " and current_rank=true";
                $theq .= " and srk_index in (select srk_index from";
                $theq .= " sysdef.rank_names where clt_index=:artid)";
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
                $pdoquery -> execute(array(//
                ':artid' => $_SESSION['artid'], //
                ':stu_index' => $value["stu_index"]));
                $ranks = $pdoquery -> fetch();

                if ($ranks -> fed_id != '') {
                    $nextid = $ranks -> fed_id;
                    logit($logname, '  existing id is:' . $nextid);

                } else {
                    // need to get next rankid

                    // check if any ids exist for this client/year
                    $theq = " select count(*) as counter from fedids";
                    $theq .= " where clientid=:clientid";
                    $theq .= " and year=:year";
                    $pdoquery = $dbconnw -> prepare($theq);
                    $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
                    $pdoquery -> execute(array(//
                    ':year' => substr($_SESSION['testdate'], 0, 4), //
                    ':clientid' => $_SESSION["clientdefaults"]["clientid"]));
                    $fedidnumber = $pdoquery -> fetch();

                    if ($fedidnumber -> counter == 0) {
                        //must be a new year/client, need to add a new record
                        $theq = " insert into fedids";
                        $theq .= " (clientid,fedid,year) values (:clientid, 0, :year)";
                        $pdoquery = $dbconnw -> prepare($theq);
                        $pdoquery -> execute(array(//
                        ':year' => substr($_SESSION['testdate'], 0, 4), //
                        ':clientid' => $_SESSION["clientdefaults"]["clientid"]));
                    }
                    //increment id
                    $theq = " update fedids set fedid=fedid+1";
                    $theq .= " where clientid=:clientid";
                    $theq .= " and year=:year";
                    $theq .= " returning fedid";
                    $pdoquery = $dbconnw -> prepare($theq);
                    $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
                    $pdoquery -> execute(array(//
                    ':year' => substr($_SESSION['testdate'], 0, 4), //
                    ':clientid' => $_SESSION["clientdefaults"]["clientid"]));
                    $fedidnumber = $pdoquery -> fetch();
                    $nextid = $fedidnumber -> fedid;

                    $nextid = $_SESSION["clientdefaults"]["fedidprefix"] . substr($_SESSION['testdate'], 2, 2) . '-' . $nextid;
                    logit($logname, '  new id is:' . $nextid);
                }


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
                $theq = " insert into ranks ( stu_index, srk_index, test_date, current_rank, invoiceid,fed_id)";
                $theq .= " values (:stu_index, :srk_index, :test_date, true, :invoiceid, :nextid)";
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute(array(//
                ':srk_index' => $value["newsrk_index"], //
                ':test_date' => $_SESSION['testdate'], //
                ':invoiceid' => $invoiceid, //
                ':nextid' => $nextid, //
                ':stu_index' => $value["stu_index"]));


                //write out data to excel
                $objPHPExcel -> getActiveSheet() -> setCellValue('A' . $row, $value['first_name'] . ' ' . $value['last_name']);
                $objPHPExcel -> getActiveSheet() -> setCellValue('B' . $row, $_POST['skip' . $value["stu_index"]]);
                $objPHPExcel -> getActiveSheet() -> setCellValue('C' . $row, $value['srk_description']);
                if (isset($_POST['nofeereason' . $value["stu_index"]])) {
                    $objPHPExcel -> getActiveSheet() -> setCellValue('D' . $row, $_POST['nofeereason' . $value["stu_index"]]);
                } elseif ($_POST['membershipfee' . $value["stu_index"]] == 'yes') {
                    $objPHPExcel -> getActiveSheet() -> setCellValue('D' . $row, $testfees[-99]);
                } else {
                    $objPHPExcel -> getActiveSheet() -> setCellValue('D' . $row, '0');
                }
                $objPHPExcel -> getActiveSheet() -> setCellValue('E' . $row, $_POST['totalfee' . $value["stu_index"]]);
                $row++;

            } catch (PDOException $e) {
                logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                $results -> errortext = $e -> getMessage();
                $cancontinue = FALSE;
            }
        }
        if ($cancontinue) {
            logit($logname, '  everything has updated corectly, so send out the invoice');

            // make the invoice pretty

            // Add naka logo to the worksheet
            $objDrawing = new PHPExcel_Worksheet_Drawing();
            $objDrawing -> setPath($images . 'nakabanner.png');
            $objDrawing -> setHeight(90);
            $objDrawing -> setWorksheet($objPHPExcel -> getActiveSheet());

            $objPHPExcel -> setActiveSheetIndex(0);
            $objPHPExcel -> getActiveSheet() -> setCellValue('A1', 'NAKA');
            $objPHPExcel -> getActiveSheet() -> getStyle('A1') -> getFont() -> setSize(20);
            $objPHPExcel -> getActiveSheet() -> getStyle('A1') -> getFont() -> setBold(true);
            $objPHPExcel -> getActiveSheet() -> getRowDimension(1) -> setRowHeight(50);

            $objPHPExcel -> getActiveSheet() -> setCellValue('E1', 'INVOICE');
            $objPHPExcel -> getActiveSheet() -> getStyle('E1') -> getFont() -> setSize(28);
            $objPHPExcel -> getActiveSheet() -> getStyle('E1') -> getFont() -> setBold(true);
            $objPHPExcel -> getActiveSheet() -> getStyle('E1') -> getFont() -> getColor() -> setARGB("808080");
            $objPHPExcel -> getActiveSheet() -> getStyle('E1') -> getAlignment() -> setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);

            $objPHPExcel -> getActiveSheet() -> setCellValue('A4', 'REMIT TO:');
            $objPHPExcel -> getActiveSheet() -> getStyle('A4') -> getFont() -> setBold(true);
            $objPHPExcel -> getActiveSheet() -> setCellValue('A5', '1323 N Riverside Dr');
            $objPHPExcel -> getActiveSheet() -> setCellValue('A6', 'McHenry, IL  60050');
            $objPHPExcel -> getActiveSheet() -> setCellValue('A7', '815-344-2900');
            $objPHPExcel -> getActiveSheet() -> setCellValue('A8', 'www.KiMudo.com');

            $objPHPExcel -> getActiveSheet() -> setCellValue('E6', 'Invoice #: ' . $invoiceid);
            $objPHPExcel -> getActiveSheet() -> setCellValue('E7', 'Invoice Date: ' . date("m/d/Y"));
            $objPHPExcel -> getActiveSheet() -> setCellValue('E8', 'Test Date: ' . $_SESSION['testdate']);

            $objPHPExcel -> getActiveSheet() -> setCellValue('A11', 'REMITTING SCHOOL:');
            $objPHPExcel -> getActiveSheet() -> getStyle('A11') -> getFont() -> setBold(true);
            $objPHPExcel -> getActiveSheet() -> setCellValue('A12', $_SESSION['schoolname']);
            $objPHPExcel -> getActiveSheet() -> setCellValue('A13', $_SESSION['schooladdress']);
            $objPHPExcel -> getActiveSheet() -> setCellValue('A14', $_SESSION['schooladdress2']);
            $objPHPExcel -> getActiveSheet() -> setCellValue('A15', $_SESSION['schoolcity'] . ' ' . $_SESSION['schoolstate'] . ', ' . $_SESSION['schoolzip']);
            $objPHPExcel -> getActiveSheet() -> setCellValue('A16', $_SESSION['schoolphone']);

            $objPHPExcel -> getActiveSheet() -> getStyle('C5:C99') -> getNumberFormat() -> setFormatCode('$#,##0.00');
            $objPHPExcel -> getActiveSheet() -> getStyle('E5:E99') -> getNumberFormat() -> setFormatCode('$#,##0.00');

            $objPHPExcel -> getActiveSheet() -> setCellValue('A' . $row, 'TOTAL DUE');
            $objPHPExcel -> getActiveSheet() -> setCellValue('E' . $row, '=SUM(E5:E' . ($row - 1) . ')');
            $objPHPExcel -> getActiveSheet() -> getStyle('A' . $row . ':E' . $row) -> getFont() -> setBold(true);

            $objPHPExcel -> getActiveSheet() -> getStyle('B4:B99') -> getAlignment() -> setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
            $objPHPExcel -> getActiveSheet() -> getStyle('C4:C99') -> getAlignment() -> setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
            $objPHPExcel -> getActiveSheet() -> getStyle('D4:D99') -> getAlignment() -> setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
            $objPHPExcel -> getActiveSheet() -> getStyle('E4:E99') -> getAlignment() -> setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);

            // footers
            $objPHPExcel -> getActiveSheet() -> setCellValue('A' . ($row + 2), 'Thank You for your Prompt Payment!');
            $objPHPExcel -> getActiveSheet() -> setCellValue('A' . ($row + 3), 'Checks can be payable to: NAKA LLC');
            $objPHPExcel -> getActiveSheet() -> setCellValue('A' . ($row + 5), 'Please email new member forms to:  NAKAKiMudo@gmail.com');

            for ($i = 0; $i < 5; $i++) {
                $objPHPExcel -> getActiveSheet() -> getStyle(PHPExcel_Cell::stringFromColumnIndex($i) . '4') -> getFont() -> setBold(true);
            }

            // save excel file
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            logit($logname, '   SAVING ' . $xlsstore . 'naka' . $invoiceid . '.xls');
            $objWriter -> save($xlsstore . 'naka' . $invoiceid . '.xls');

            //email invoice
            $email = new PHPMailer();
            $email -> From = 'info@naka.com';
            $email -> FromName = 'NAKA Website';
            $email -> Subject = 'NAKA Invoice';
            $email -> Body = 'Invoice for test';
            $email -> AddAddress($_SESSION["useremail"], $_SESSION["treasureremail"]);
            $email -> AddAttachment($xlsstore . 'naka' . $invoiceid . '.xls', $invoiceid . '.xls');
            logit($logname, '   sending email');
            if (!$email -> Send()) {
                logit($logname, '   **ERROR** EMAIL FAILED: ' . $email -> ErrorInfo);
            } else {
                logit($logname, '   email sent');
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


        // load data to be displayed
        $i = 0;
        $_SESSION['testfees'] = 0;
        foreach ($_SESSION["activestudents"] as $key => $value) {
            // loop though all the students to see if they tested
            if (isset($_POST["tested" . $value['stu_index']])) {
                logit($logname, $value['first_name'] . ' ' . $value['last_name'] . ' tested');

                //load the array $studentdata for display
                $studentdata[$i]['first_name'] = $value['first_name'];
                $studentdata[$i]['last_name'] = $value['last_name'];
                $studentdata[$i]['srk_description'] = $value['srk_description'];
                $studentdata[$i]['stu_index'] = $value['stu_index'];
                $studentdata[$i]['srk_seq'] = $value['srk_seq'];
                $studentdata[$i]['srk_index'] = $value['srk_index'];
                $studentdata[$i]['membershipfeecheckbox'] = '';


                if ($_POST['skipped' . $value['stu_index']] == '') {
                    $skip = 1;
                } else {
                    $skip = $_POST['skipped' . $value['stu_index']];
                    logit($logname, '   they skipped ' . $skip);
                }
                $studentdata[$i]['skip'] = $skip;

                //if moving from kup to dan need to skip "0"
                if (($studentdata[$i]['srk_seq'] > 0) and ($studentdata[$i]['srk_seq'] - $skip <= 0)) {
                    $skip++;
                }

                // get the srk_index of the new rank
                // locate the index in the array then subract 1 (or the amount of
                // ranks skipped)
                $newrankindex = array_search($studentdata[$i]['srk_index'], $rankindexs) - $skip;

                //recordtestcheckbox is now being used to display the new rank
                $studentdata[$i]['recordtestcheckbox'] = '<td>' . $ranknames[$newrankindex] . '</td>';
                $studentdata[$i]['newsrk_index'] = $rankindexs[$newrankindex];

                logit($logname, '  their new ranks is ' . $newrankindex . ' ' . $ranknames[$newrankindex]);

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
                    logit($logname, '  test fee is ' . $totalfee);
                }

                if (isset($_POST["membershipfee" . $value['stu_index']])) {
                    $totalfee = $totalfee + $testfees[-99];
                    $studentdata[$i]['recordtestskipped'] = '<td>New Member</td>';
                    logit($logname, '  they are also paying NAKA membership fees');
                    $nakafee = 'yes';
                } else if ($_POST["testcount" . $value['stu_index']] < 2) {
                    $studentdata[$i]['recordtestskipped'] = '<td><input name="nofeereason' . $value['stu_index'] . '" required></td>';
                    logit($logname, '  for some reason they are NOT paying NAKA membership fees');
                    $nakafee = 'no';
                } else {
                    $studentdata[$i]['recordtestskipped'] = '<td>N/A</td>';
                    $nakafee = 'no';
                }

                // put the student's test fee and skipped count onto the form for
                // later use.
                $_SESSION['feestring'] .= '<input type="hidden" name="totalfee' . $value['stu_index'] . '" value="' . $totalfee . '">';
                $_SESSION['feestring'] .= '<input type="hidden" name="skip' . $value['stu_index'] . '" value="' . $skip . '">';
                $_SESSION['feestring'] .= '<input type="hidden" name="membershipfee' . $value["stu_index"] . '" value="' . $nakafee . '">';
                //recordtestskipped will display the test fee for this student
                $studentdata[$i]['membershipfeecheckbox'] = '<td>$' . number_format($totalfee, 2, '.', '') . '</td>';
                $_SESSION['testfees'] = $_SESSION['testfees'] + $totalfee;

                $i++;
            }
        }

        // set headers
        $_SESSION['testdetails'] = $studentdata;
        $_SESSION['step'] = 'recordit';
        $_SESSION['recordtestbuttontitle'] = ' Submit Test ';
        $_SESSION['recordtestcol1name'] = 'New Rank';
        $_SESSION['recordtestcol2name'] = 'Total Fee';
        $_SESSION['recordtestcol3name'] = 'Why no<br>Member fee?';
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
    $theq .= ' \'<td><input type="checkbox" name="tested\'||s.stu_index||\'">\'||';
    $theq .= ' \'<input type="hidden" name="testcount\'||s.stu_index||\'" value="\'||tests::integer||\'"></td>\' as recordtestcheckbox,';
    $theq .= ' case when tests < 2';
    $theq .= ' then \'<td><input type="checkbox" name="membershipfee\'||s.stu_index||\'" checked></td>\'';
    $theq .= ' else \'<td>N/A</td>\'';
    $theq .= ' end as membershipfeecheckbox,';
    $theq .= ' \'<td><input type="number" min="0" max="4" name="skipped\'||s.stu_index||\'"></td>\' as recordtestskipped, r.srk_index';
    $theq .= " from students s";
    $theq .= " join ranks r on s.stu_index=r.stu_index";
    $theq .= " join sysdef.rank_names rn on rn.srk_index=r.srk_index";
    $theq .= " join sysdef.class_type ct on ct.clt_index=rn.clt_index";
    $theq .= " left join (select stu_index,count(*) as tests from ranks group by 1) rc on rc.stu_index=s.stu_index";
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
    $_SESSION['recordtestcol2name'] = 'Pay New<br>Member Fee';
    $_SESSION['recordtestcol3name'] = 'Skipped';
}

$_SESSION['clientdefaults']['pagetitle'] = 'Record Test';

$thehtml = LoadTheHTML('page_recordtest', array(//
'detail_recordtest' => $studentdata), $logname, 1, 1);

echo $thehtml;
?>