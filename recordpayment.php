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

static $logname = 'recordpayment';
$o_logit = new ooplogit($logname, TRUE);
$o_logit->logit('Client:"' . $_SESSION["clientdefaults"]["dbname"] . ' user:' . $_SESSION["userlogin"]);

$results = new stdClass();
$results->success = FALSE;
$results->errortext = null;
$cancontinue = TRUE;

$dbconn = new PDOconnect('nakaweb', $o_logit, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	/*
		     * record the payment
	*/

	foreach ($_SESSION['invoices'] as $key => $value) {
		//        if (key_exists('checkno' . $value, $_POST)) {
		if ($_POST['checkno' . $value] != '') {
			$o_logit->logit('  updataing:' . $value);
			// create a pg conection

			$theq = " update invoices";
			$theq .= " set checknumber=:checknumber";
			$theq .= " ,paymentreceived=now()";
			$theq .= " ,receivedby=:receivedby";
			$theq .= " where invoiceid=:invoiceid";
			$dbconn->executeIt($theq, array(
				':checknumber' => clean_user_input($_POST['checkno' . $value]),
				':receivedby' => $_SESSION["userid"],
				':invoiceid' => $value), true);
		}
	}

	unset($_SESSION['invoices']);
	$o_logit->logit('GETting back to recordpayment');
	header('Location: recordpayment.php');
	exit;

} else {

	// get invoices
	$theq = " select fullname,invoiceid,invoicedate,paymentreceived,login,invoiceamount,";
	$theq .= " case when login is null then ";
	$theq .= '    \'<input type="text" class="thecheck" name="checkno\'||invoiceid::text||\'">\'';
	$theq .= " else checknumber end as checknumber";
	$theq .= " from invoices i";
	$theq .= " join client c on i.schoolid=c.clientid";
	$theq .= " left join users u on u.userid=receivedby";
	$theq .= " order by paymentreceived desc, invoicedate desc, invoiceamount desc";
	$dbconn->fetchIt($theq, null, $invoices, true);

	unset($_SESSION['invoices']);
	foreach ($invoices as $key => $value) {
		$_SESSION['invoices'][$key] = $value->invoiceid;
	}
}

$_SESSION['clientdefaults']['pagetitle'] = 'Record Invoice Payments';

$thehtml = LoadTheHTML(null, 'page_recordpayment', array( //
	'page_recordpayment_detail_invoices' => $invoices,
	'page_recordpayment_header_invoices' => $invoices,
), $o_logit, 1, 1);

echo $thehtml;
?>