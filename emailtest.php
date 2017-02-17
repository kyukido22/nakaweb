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
$email->Body = 'testing email service t2 - there will be mulitple versions of this test.';
$email->Body .= 'please reply to all and let me know if you get this. <br>';
$email->Body .= "email->AddAddress('john.cantin@gmail.com');<br>
	email->AddCC('tcurtis8651@gmail.com');<br>
	email->AddCC('cdgray@roundlakeflyingdragons.com');<br>
	email->AddBCC('jcantin@strategicfuse.com');";

$email->AddAddress('john.cantin@gmail.com');
$email->AddCC('cdgray@roundlakeflyingdragons.com');
$email->AddCC('tcurtis8651@gmail.com');
$email->AddBCC('jcantin@strategicfuse.com');
//$email->AddAttachment($xlsstore . 'naka' . $invoiceid . '.xls', $invoiceid . '.xls');
echo '   sending email to:  john.cantin@gmail.com';

if (!$email->Send()) {
	echo '   **ERROR** EMAIL FAILED: ' . $email->ErrorInfo;
} else {
	echo '   email sent';
}
?>