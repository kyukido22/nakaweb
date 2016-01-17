<?php

session_start();
if (!isset($_SESSION["userid"])) {
	header('Location: login.php');
	exit ;
}

$starttime = microtime(TRUE);
require '/var/www/phplib/logitv2.php';
require '/var/www/phplib/PDOconnectDB.php';
require '/var/www/phplib/cleanuserinput.php';
require '/var/www/phplib/wc2lib.php';

static $logname = 'recordpayment';
startthelog($logname, TRUE);

$dbconn = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname);
GetTheHTMLs('EN-US', 0, $dbconn, $logname);

logit($logname, 'Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$results = new stdClass();
$results -> success = FALSE;
$results -> errortext = null;
$cancontinue = TRUE;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	/*
	 * record the payment
	 */

	$dbconn = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname);
	foreach ($_SESSION['invoices'] as $key => $value) {
		if ($_POST['checkno' . $value] != '') {
			logit($logname, '  updataing:' . $value);
			// create a pg conection

			try {
				$theq = " update invoices";
				$theq .= " set checknumber=:checknumber";
				$theq .= " ,paymentreceived=now()";
				$theq .= " ,receivedby=:receivedby";
				$theq .= " where invoiceid=:invoiceid";
				$pdoquery = $dbconn -> prepare($theq);
				$pdoquery -> execute(array(//
				':checknumber' => clean_user_input($_POST['checkno' . $value]), //
				':receivedby' => $_SESSION["userid"], //
				':invoiceid' => $value));

			} catch (PDOException $e) {
				logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
				$results -> errortext = $e -> getMessage();
				$cancontinue = FALSE;
			}
		}
	}

	unset($_SESSION['invoices']);
	logit($logname, 'GETting back to recordpayment');
	header('Location: recordpayment.php');
	exit ;

} else {

	// get invoices
	$dbconnw = PDOconnect('nakaweb', $_SESSION["clientdefaults"]["host"], $logname);
	$theq = " select fullname,invoiceid,invoicedate,paymentreceived,login,invoiceamount,";
	$theq .= " case when login is null then ";
	$theq .= '    \'<input type="text" name="checkno\'||invoiceid::text||\'">\'';
	$theq .= " else checknumber end as checknumber";
	$theq .= " from invoices i";
	$theq .= " join client c on i.schoolid=c.clientid";
	$theq .= " left join users u on u.userid=receivedby";
	$theq .= " order by paymentreceived desc, invoicedate desc, invoiceamount desc";
	try {
		$pdoquery = $dbconnw -> prepare($theq);
		$pdoquery -> setFetchMode(PDO::FETCH_OBJ);
		$pdoquery -> execute();
		$invoices = $pdoquery -> fetchAll();

		unset($_SESSION['invoices']);
		$i = 0;
		foreach ($invoices as $key => $value) {
			$_SESSION['invoices'][$i] = $value -> invoiceid;
			$i++;
		}

	} catch (PDOException $e) {
		logit($logname, '  **ERROR** on line ' . __LINE__ . ' with query - ' . $theq . ' ' . $e -> getMessage());
		$results -> errortext = $e -> getMessage();
		$cancontinue = FALSE;
	}
}

$_SESSION['clientdefaults']['pagetitle'] = 'Record Invoice Payments';

$thehtml = LoadTheHTML('page_recordpayment', array(//
'detail_invoices' => $invoices), $logname, 1, 1);

echo $thehtml;
?>