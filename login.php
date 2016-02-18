<?php

session_start();

//erase any old seesion vars

session_unset();

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

static $logname = 'login';
startthelog($logname, TRUE);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

//set some default values
$_SESSION['errortext'] = '';
$_SESSION['clientdefaults'][0] = 0;
$_SESSION['local'] = 'EN-US';
$_SESSION["othercssorjs"] = '';

//wc2 control database host
$_SESSION["dbhost"] = 'localhost';

$dbconn = PDOconnect('nakaweb', $_SESSION["dbhost"], $logname);

GetTheHTMLs('EN-US', 0, $dbconn, $logname);

if (key_exists('LOGIN', $_POST) and key_exists('PASSWORD', $_POST)) {
    logit($logname, 'Login: ' . $_POST["LOGIN"] . ' Password: ' . $_POST["PASSWORD"]);

    //check the username and password
    $theq = " select *";
    $theq .= " from users u";
    $theq .= " join clientuser cu on u.userid=cu.userid";
    $theq .= " join client c on c.clientid=cu.clientid";
    $theq .= " where login=:login";
    $theq .= "  and thepassword = :password";
    $theq .= "  and c.locked=false";
    $theq .= "  and u.locked=false";
    try {
        $pdoquery = $dbconn -> prepare($theq);
        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
        $pdoquery -> execute(array(//
        ':login' => clean_user_input($_POST["LOGIN"]), //
        ':password' => clean_user_input($_POST["PASSWORD"])));
        $row = $pdoquery -> fetch();

    } catch (PDOException $e) {
        logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
        $cancontinue = FALSE;
    }

    if ($cancontinue) {
        if (!$row) {
            // not a valid user/password
            //logit($logname, __LINE__);
            $results -> errortext = 'Sorry, that username/password is invalid';
        } else {
            // valid user/password
            if ($row -> locked == TRUE) {
                //logit($logname, __LINE__);
                $results -> errortext = 'Sorry, that user is locked';
            } else {
                // must be ok to let them in
                //                logit($logname, __LINE__);
                $_SESSION["userid"] = $row -> userid;
                $_SESSION["userlogin"] = $row -> login;
                $_SESSION["useremail"] = $row -> email;
                $_SESSION["superuser"] = $row -> superuser;
                $_SESSION["treasurer"] = $row -> treasurer;
                $_SESSION["initials"] = $row -> initials;
                $_SESSION["username"] = $row -> firstname . ' ' . $row -> lastname;
                $_SESSION["usercompany"] = $row -> company;
                $_SESSION["userlanguage"] = $row -> thelanguage;
                $_SESSION['clientdefaults']["schoollogo"] = $row -> schoollogo;
                if ($_SESSION["treasurer"] == true) {
                    $_SESSION["treasurermenu"] = '<a href="recordpayment.php">Receive Payments</a>&nbsp;&nbsp;';
                } else {
                    $_SESSION["treasurermenu"] = '';
                }

                // get tresurers email
                $theq = " select email";
                $theq .= " from users u";
                $theq .= " where treasurer=true";
                $theq .= "  and u.locked=false";
                try {
                    $pdoquery = $dbconn -> prepare($theq);
                    $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
                    $pdoquery -> execute();
                    $row = $pdoquery -> fetch();
                    $_SESSION["treasureremail"] = $row -> email;
                } catch (PDOException $e) {
                    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                    $cancontinue = FALSE;
                }

                $results -> success = TRUE;

                //update lastvisit
                $theq = " update users set lastvisit=now()";
                $theq .= " where userid=:userid";
                try {
                    $pdoquery = $dbconn -> prepare($theq);
                    $pdoquery -> execute(array(//
                    ':userid' => $_SESSION["userid"]));
                    $row = $pdoquery -> fetch();

                } catch (PDOException $e) {
                    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                    $cancontinue = FALSE;
                }

                //record loginhistory
                //update lastvisit
                $theq = " insert into loginhistory (userid,logints,loginfrom)";
                $theq .= " values (:userid,now(), :loginfrom)";
                try {
                    $pdoquery = $dbconn -> prepare($theq);
                    $pdoquery -> execute(array(//
                    ':userid' => $_SESSION["userid"], //
                    ':loginfrom' => $_SERVER["REMOTE_ADDR"]));
                    $row = $pdoquery -> fetch();

                } catch (PDOException $e) {
                    logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                    $cancontinue = FALSE;
                }

                header('Location: clientselector.php');
                exit ;
            }
        }
    }
} else {
    $results -> success = TRUE;
    $results -> errortext = 'someone looking at the login page';
}

if (!$results -> success) {
    $_SESSION['errortext'] = $results -> errortext;
}

$thehtml = LoadTheHTML('page_login', null, $logname, 1, 1);

if ($thehtml == '') {
    $results -> errortext = 'no HTML found at: ' . __LINE__;
    $cancontinue = FALSE;
}

$thehtml = str_replace('  ', '', $thehtml);

echo $thehtml;

if (!$results -> success) {

    logit($logname, "    **ERROR** something went wrong in " . __FILE__ . " Error text is: " . $results -> errortext);
} else {
    $totaltime = microtime(TRUE) - $starttime;
    logit($logname, json_encode($results));
    logit($logname, "    That took: " . $totaltime . " seconds");
}
?>
