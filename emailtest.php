<?php

session_start();

//erase any old seesion vars

session_unset();

$starttime = microtime(TRUE);
if (PHP_OS == 'WINNT') {
	$_SESSION['testing'] = true;
} else {
	require_once '/var/www/phplib/PHPMailer-master/class.phpmailer.php';
	$_SESSION['testing'] = false;
}

//email invoice
$email = new PHPMailer();
$email->From = 'info@naka.com';
$email->FromName = 'NAKA Website';
$email->Subject = 'NAKA Invoice';
$email->Body = 'testing email service - please let me know if you get this.';
$email->AddAddress('john.cantin@gmail.com');
$email->AddCC('cdgray@roundlakeflyingdragons.com');
$email->AddBCC('jcantin@strategicfuse.com');
//$email->AddAttachment($xlsstore . 'naka' . $invoiceid . '.xls', $invoiceid . '.xls');
echo '   sending email to:  john.cantin@gmail.com';

if (!$email->Send()) {
	echo '   **ERROR** EMAIL FAILED: ' . $email->ErrorInfo;
} else {
	echo '   email sent';
}
?>