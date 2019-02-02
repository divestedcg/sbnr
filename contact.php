<?php

include "sbnr/config.php";
if($SBNR_CONTACT_ENABLED === false) { exit; }

include "sbnr/security.php";
include "sbnr/utils.php";
include "sbnr/pre.php";

if(isset($_POST["CSRF_TOKEN"], $_POST["txtName"], $_POST["txtPhone"], $_POST["txtMessage"])) {
	if(noHTML($_POST["CSRF_TOKEN"]) === $_SESSION['SBNR_CSRF_TOKEN']) {
		$name = noHTML(base64_decode(urldecode($_POST["txtName"])));
		$number = preg_replace("/[^0-9]/", '', noHTML(base64_decode(urldecode($_POST["txtPhone"]))));
		$message = noHTML(base64_decode(urldecode($_POST["txtMessage"])));
		if(strlen($name) <= $SBNR_CONTACT_MAX_LENGTH_NAME
			&& strlen($number) >= $SBNR_CONTACT_MIN_LENGTH_PHONE_NUMBER
			&& strlen($number) <= $SBNR_CONTACT_MAX_LENGTH_PHONE_NUMBER
			&& strlen($message) <= $SBNR_CONTACT_MAX_LENGTH_MESSAGE) {

			$msentinel = generateRandomString($SBNR_CONTACT_MESSAGE_PREFIX_LENGTH);

			if ($SBNR_CONTACT_GEOIP) {
				$geoIP = $_SERVER["MM_COUNTRY_CODE"];
				if(strlen($_SERVER["MM_CITY_NAME"] .  $_SERVER["MM_REGION_CODE"]) > 1) {
					$geoIP = $_SERVER["MM_CITY_NAME"] . ", " . $_SERVER["MM_REGION_CODE"] . " " . $_SERVER["MM_COUNTRY_CODE"];
				}
				$location = "[" . $msentinel . "] Location: " . $geoIP . "\n";
			}

			$message = "[" . $msentinel . "] MESSAGE START\n" .
					"[" . $msentinel . "] Name: " . $name . "\n" .
					"[" . $msentinel . "] Phone Number: " . $number . "\n" .
					$location .
					"[" . $msentinel . "] Message: \n" . $message . "\n" .
					"[" . $msentinel . "] MESSAGE END";

			exec("echo " . escapeshellarg($message) . " | sendxmpp -f " . $SBNR_CONTACT_SENDXMPP_CONFIG . " -t " . $SBNR_CONTACT_SENDXMPP_RECEIPENT);
			print("Message Sent!");
		} else {
			generateErrorPageBasic(406);
		}
	} else {
		generateErrorPageBasic(401);
	}
} else {
	generateErrorPageBasic(400);
}

$_SESSION['SBNR_CSRF_TOKEN'] = bin2hex(random_bytes(32)); //Always renew the token to prevent brute forcing

?>
