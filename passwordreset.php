<?php

session_start();

$starttime = microtime(TRUE);
if (PHP_OS == 'WINNT') {
    require_once 'C:\inetpub\phplib\logitv2.php';
    require_once 'C:\inetpub\phplib\PDOconnectDB.php';
    require_once 'C:\inetpub\phplib\cleanuserinput.php';
    require_once 'C:\inetpub\phplib\wc2lib.php';
    require_once 'C:\inetpub\phplib\PHPMailer-master\class.phpmailer.php';
} else {
    require_once '/var/www/phplib/logitv2.php';
    require_once '/var/www/phplib/PDOconnectDB.php';
    require_once '/var/www/phplib/cleanuserinput.php';
    require_once '/var/www/phplib/wc2lib.php';
    require_once '/var/www/phplib/PHPMailer-master/class.phpmailer.php';
}

static $logname = 'passwordreset';
startthelog($logname, TRUE);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

//set some default values
$_SESSION['clientdefaults'][0] = 0;
$_SESSION['local'] = 'EN-US';
$_SESSION["othercssorjs"] = '';

//wc2 control database host
$_SESSION["dbhost"] = 'localhost';

$dbconn = PDOconnect('nakaweb', $_SESSION["dbhost"], $logname, true);

GetTheHTMLs('EN-US', 0, $dbconn, $logname);

if (key_exists('reset', $_POST)) {
    logit($logname, 'Login: ' . $_POST["reset"]);

    //check the username and password
    $theq = " select *";
    $theq .= " from users u";
    $theq .= " join clientuser cu on u.userid=cu.userid";
    $theq .= " join client c on c.clientid=cu.clientid";
    $theq .= " where login=:login";
    $theq .= "  and c.locked=false";
    $theq .= "  and u.locked=false";
    try {
        $pdoquery = $dbconn -> prepare($theq);
        $pdoquery -> setFetchMode(PDO::FETCH_OBJ);
        $pdoquery -> execute(array(':login' => clean_user_input($_POST["reset"])));
        $row = $pdoquery -> fetch();

    } catch (PDOException $e) {
        logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
        $cancontinue = FALSE;
    }

    // dont admit that the username is valid or not
    $results -> errortext = //
    'If the username you entered is in our system ' . //
    'you will recieve an email with a temporary ' . //
    'password sent your email address. './/
    'Don\'t forget to check your spam folder.';

    if ($cancontinue) {
        if (!$row) {
            // not a valid user/password
            logit($logname, 'username is invalid');
            $results -> success = TRUE;
        } else {
            // valid user/password
            logit($logname, 'username is valid');

            //record the request
            $theq = " insert into passwordreset (username,ip,requestts)";
            $theq .= " values (:username,:ip,now())";
            try {
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute(array(//
                ':username' => clean_user_input($_POST["reset"]), //
                ':ip' => $_SERVER["REMOTE_ADDR"]));
            } catch (PDOException $e) {
                logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                $cancontinue = FALSE;
            }

            // update user's password
            $newpassword = substr(md5(rand()), 8, 6);
            logit($logname, '  generated password is:' . $newpassword);
            $theq = " update users";
            $theq .= " set thepassword=:newpassword";
            $theq .= " where userid=:userid";
            try {
                $pdoquery = $dbconn -> prepare($theq);
                $pdoquery -> execute(array(//
                ':newpassword' => $newpassword, //
                ':userid' => $row -> userid));
            } catch (PDOException $e) {
                logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
                $cancontinue = FALSE;
            }

            //email user
            $email = new PHPMailer();
            $email -> From = 'info@naka.com';
            $email -> FromName = 'NAKA Website';
            $email -> Subject = 'Password Reset';
            $email -> Body = 'Your temporary password is: ' . $newpassword;
            $email -> AddAddress($row -> email);
            logit($logname, '   sending email');
            if (!$email -> Send()) {
                logit($logname, '   **ERROR** EMAIL FAILED: ' . $email -> ErrorInfo);
            } else {
                logit($logname, '   email sent');
            }

            $results -> success = TRUE;

        }

    }

    $_SESSION['errortext'] = $results -> errortext;
    logit($logname, $_SESSION['errortext']);
    logit($logname, 'refresing display with get');
    header('Location: passwordreset.php');
    exit ;

} else {
    $results -> success = TRUE;
    $results -> errortext = 'just someone looking at the password reset page';
}

$thehtml = LoadTheHTML('page_passwordreset', null, $logname, 1, 1);
$_SESSION['errortext'] = '';

if ($thehtml == '') {
    $results -> errortext = 'no HTML found at: ' . __LINE__;
    $cancontinue = FALSE;
}

echo $thehtml;

if (!$results -> success) {
    logit($logname, "    **ERROR** something went wrong in " . __FILE__ . " Error text is: " . $results -> errortext);
} else {
    $totaltime = microtime(TRUE) - $starttime;
    logit($logname, json_encode($results));
    logit($logname, "    That took: " . $totaltime . " seconds");
}
?>
