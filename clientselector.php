<?php


session_start();

$starttime = microtime(TRUE);
require '/var/www/phplib/logitv2.php';
require '/var/www/phplib/PDOconnectDB.php';
require '/var/www/phplib/cleanuserinput.php';
require '/var/www/phplib/wc2lib.php';


static $logname = 'clientselector';
startthelog($logname, TRUE);

$cancontinue = TRUE;


function SetSessionVals($clientuserrecord, $PDOconn, $logname) {
    $cancontinue = TRUE;

    //load client details
    $_SESSION['clientdefaults']['clientid'] = $clientuserrecord -> clientid;

    $theq = 'select * from client where clientid=:clientid';
    try {
        $pdoquery = $PDOconn -> prepare($theq);
        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
        $pdoquery -> execute(array('clientid' => $_SESSION["clientdefaults"]["clientid"]));
        $client = $pdoquery -> fetch();
    } catch (PDOException $e) {
        logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
        $cancontinue = FALSE;
    }

    if ($cancontinue) {
        $_SESSION["clientdefaults"]["dbname"] = $client -> dbname;
        $_SESSION["clientdefaults"]["host"] = $client -> host;
        $_SESSION["clientdefaults"]["fullname"] = $client -> fullname;
		$_SESSION["othercssorjs"]='';
    }

    if ($cancontinue) {
        $cancontinue = GetTheHTMLs($_SESSION["userlanguage"], $_SESSION["clientdefaults"]["clientid"], $PDOconn, $logname);
    }

    if ($cancontinue) {
        header('Location: school.php');
    }
    return $cancontinue;
}

/*
 * if this program is called with GET params for tag and client (must have been self called)
 * then, check that the user has rights to that (to prevent cheating) and put them in.
 */

$PDOconn = PDOconnect('nakaweb', $_SESSION["dbhost"], $logname);

if (key_exists('clientid', $_GET)) {

    $theq = 'select * ';
    $theq .= ' from clientuser ';
    $theq .= ' where userid = :userid';
    $theq .= ' and clientid = :clientid';
    try {
        $pdoquery = $PDOconn -> prepare($theq);
        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
        $pdoquery -> execute(array(//
        ':userid' => $_SESSION["userid"], //
        ':clientid' => $_GET["clientid"]));
        $row = $pdoquery -> fetch();
        if ($pdoquery -> rowCount() != 1) {
            logit($logname, '  looks like the user is cheating!  NOT letting them in!');
            $cancontinue = FALSE;
            echo 'CHEATER!!!';
            exit ;
        } else {
            logit($logname, '  user selected valid cid: ' . $_GET["CID"] . ' tag: ' . $_GET["TAG"]);

            SetSessionVals($row, $PDOconn, $logname);
        }

    } catch (PDOException $e) {
        logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
        $cancontinue = FALSE;
    }
} else {
    /*
     * if this program is called without GET params (must have been called by login.php)
     * then...
     *      if user only has one tag just put them in
     *      if user has multiple tags then display a list for them to select
     */


    // check if user has access to more than one account

    $theq = " select *";
    $theq .= " from clientuser cu";
    $theq .= ' join client c on c.clientid=cu.clientid';
    $theq .= " where userid=:userid ";
    $theq .= ' order by fullname';

    try {
        $pdoquery = $PDOconn -> prepare($theq);
        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
        $pdoquery -> execute(array(//
        ':userid' => $_SESSION["userid"]));
        $row = $pdoquery -> fetch();

    } catch (PDOException $e) {
        logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
        $cancontinue = FALSE;
    }

    if ($cancontinue) {
        if ($pdoquery -> rowCount() == 1) {
            // only one tag so just put them in
            logit($logname, '  user only has 1 tag value, bypassing client selection screen');
            $_SESSION["multiaccount"] = FALSE;
            SetSessionVals($row, $PDOconn, $logname);

        } else {

            // multiple tags, so need to display one of the account selection screens
            logit($logname, '  user has multiple tag values');
            $_SESSION["multiaccount"] = TRUE;

            // check if user has access to more than one CLIENT
            $theq = 'select distinct c.clientid, fullname, dbname, host';
            $theq .= ' from clientuser cu';
            $theq .= ' join client c on cu.clientid = c.clientid';
            $theq .= ' where cu.userid = :userid';
            try {
                $pdoquery = $PDOconn -> prepare($theq);
                $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
                $pdoquery -> execute(array(':userid' => $_SESSION["userid"]));
                $row = $pdoquery -> fetch();

            } catch (PDOException $e) {
                logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                $cancontinue = FALSE;
            }
            if ($cancontinue) {
                if ($pdoquery -> rowCount() == 1) {
                    logit($logname, '  user has multiple tags accross one client');
                    //mulitiple accounts/geos but only one client, so we can make a prettier menu for them

                    $_SESSION["clientdefaults"]["clientid"] = $row -> clientid;
                    $_SESSION["clientdefaults"]["host"] = $row -> host;
                    $_SESSION["clientdefaults"]["dbname"] = $row -> dbname;
                    $dblinker = "left outer join (select * from dblink('dbname='" . $row -> dbname . "' host='" . $row -> host . "' user=pg_admin_php password=adminsms_pg8',";

                    $theq = "select tag,COALESCE(acname,g1name,g2name,g3name,g4name,'No Desc Found') as acname,";
                    $theq .= '  b.clientid,taglevel';
                    $theq .= ' from clientuser a';
                    $theq .= ' join client b on a.clientid = b.clientid';
                    $theq .= $dblinker;
                    $theq .= "     'select acnumber,acname from account')";
                    $theq .= '     AS t1(acnumber varchar(20),';
                    $theq .= '     acname varchar(50))) c on a.tag=acnumber and taglevel=5';
                    $theq .= $dblinker;
                    $theq .= "     'select geo1code,manager from geotabl1')";
                    $theq .= '     AS t1(geo1code varchar(15),';
                    $theq .= '     g1name varchar(50))) g1 on a.tag=geo1code and taglevel=1';
                    $theq .= $dblinker;
                    $theq .= "     'select geo2code,manager from geotabl2')";
                    $theq .= '     AS t1(geo2code varchar(15),';
                    $theq .= '     g2name varchar(50))) g2 on a.tag=geo2code and taglevel=2';
                    $theq .= $dblinker;
                    $theq .= "     'select geo3code,manager from geotabl3')";
                    $theq .= '     AS t1(geo3code varchar(15),';
                    $theq .= '     g3name varchar(50))) g3 on a.tag=geo3code and taglevel=3';
                    $theq .= $dblinker;
                    $theq .= "     'select geo4code,manager from geotabl4')";
                    $theq .= '     AS t1(geo4code varchar(15),';
                    $theq .= '     g4name varchar(50))) g4 on a.tag=geo4code and taglevel=4';
                    $theq .= ' where a.client_id = :clientid';
                    $theq .= '  and a.user_id = :userid';
                    $theq .= ' order by acname, tag';
                    try {
                        $pdoquery = $PDOconn -> prepare($theq);
                        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
                        $pdoquery -> execute(array(//
                        ':clientid' => $_SESSION["clientdefaults"]["clientid"], //
                        ':userid' => $_SESSION["userid"]));
                    } catch (PDOException $e) {
                        logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                        $cancontinue = FALSE;
                    }

                    $row = $pdoquery -> fetch();

                } else {
                    // accounts range over multiple clients
                    logit($logname, '  user has multiple tags accross multiple clients');
                    $theq = 'select c.clientid, fullname, '.$_SESSION["userid"].' as userid';
                    $theq .= ' from client c';
                    $theq .= ' join clientuser cu on c.clientid = cu.clientid';
                    $theq .= " where userid = :userid";
                    $theq .= ' order by fullname';
                    try {
                        $pdoquery = $PDOconn -> prepare($theq);
                        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
                        $pdoquery -> execute(array(':userid' => $_SESSION["userid"]));
                    } catch (PDOException $e) {
                        logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                        $cancontinue = FALSE;
                    }

                    $_SESSION['clientdefaults']['fullname']='Select a School';

                    $thehtml = LoadTheHTML('page_clientselect', array('detail_clients' => $pdoquery -> fetchAll()), $logname, 1, 1);

                    if ($thehtml == '') {
                        $results -> errortext = 'no HTML found at: ' . __LINE__;
                        $cancontinue = FALSE;
                    }
                    echo $thehtml;
                } // multiple clients
            }
        }
    } // not a GET call
}


$totaltime = microtime(TRUE) - $starttime;

if ($totaltime > 0.5) {
    logit($logname, "    That took a REALLY long time: " . $totaltime . " seconds");
} elseif ($totaltime > 0.25) {
    logit($logname, "    That took a long time: " . $totaltime . " seconds");
} elseif ($totaltime > 0.1) {
    logit($logname, "    That took kinda long: " . $totaltime . " seconds");
} else {
    logit($logname, "    That took: " . $totaltime . " seconds");
}

//echo "ho there";
?>
