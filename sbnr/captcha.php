<?php
//Copyright (c) 2019-2020 Divested Computing Group
//
//This program is free software: you can redistribute it and/or modify
//it under the terms of the GNU Lesser General Public License as published by
//the Free Software Foundation, either version 3 of the License, or
//(at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU Lesser General Public License for more details.
//
//You should have received a copy of the GNU Lesser General Public License
//along with this program.  If not, see <https://www.gnu.org/licenses/>.

function generateRandomColor($image) {
	imagecolorallocate($image, rand(20, 255), rand(20, 255), rand(20, 255));
}

function applyRandomImageFilter($image) {
	switch(random_int(0, 4)) {
		case 0:
			//do nothing
			break;
		case 1:
			imagefilter($image, IMG_FILTER_EDGEDETECT);
			break;
		case 2:
			imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
			break;
		case 3:
			imagefilter($image, IMG_FILTER_MEAN_REMOVAL);
			break;
		case 4:
			imagefilter($image, IMG_FILTER_SMOOTH, 1);
			break;
	}
}

function generateTextCaptcha($captchaText) {
	$captcha = imagecreate(300, 100);
	generateRandomColor($captcha); //Set random background color
	$textColor = imagecolorallocate($captcha, 0, 0, 0); //Black text
	imagettftext($captcha, 24, rand(-10, 10), rand(15, 150), 50, $textColor, getRandomFile("./sbnr/captcha_fonts/"), $captchaText); //Draw the captcha text
	for ($i = 0; $i < rand(8, 20); $i++) { //Add lines
		imagestring($captcha, 1, rand(0, 300), rand(0, 100), generateRandomString(rand(4, 20), false), $textColor);
		imageline($captcha, rand(0, 300), rand(0, 100), rand(0, 300), rand(0, 100), $textColor);
	}
	for ($i = 0; $i < rand(1, 8); $i++) { //Add letters
		imagestring($captcha, 3, rand(0, 300), rand(0, 100), generateRandomString(6, false), $textColor);
	}
	//applyRandomImageFilter($captcha);
	return $captcha;
}

function getCaptchaText($clear = true) {
	if($clear) { clearCaptchaStore(); }
	$captchaText = generateRandomString(6, true);
	appendCaptchaStore($captchaText, implode(' ', str_split($captchaText)));
	return generateTextCaptcha($captchaText);
}

function getCaptchaMath($clear = true) {
	if($clear) { clearCaptchaStore(); }
	$num1 = random_int(0, 20);
	$num2 = random_int(0, 20);
	$place = random_int(0, 1);
	if($place) { $captchaText = "= "; }
	$captchaText .= $num1 . " + " . $num2;
	if(!$place) { $captchaText .= " ="; }
	appendCaptchaStore(($num1 + $num2), $captchaText);
	return generateTextCaptcha($captchaText);
}

function getRandomVoice() {
	switch (random_int(0, 5)) {
		case 0: return "m" . random_int(1, 8);
		case 1: return "f" . random_int(1, 5);
		case 2: return "robosoft" . random_int(3, 8);
		case 3: return "whisper";
		case 4: return "whisperf";
		case 5: return "croak";
	}
}

function getCaptchaAudioContent() {
	if(strlen($_SESSION['SBNR_CAPTCHA_SPEAK']) > 0 && !isLikelyBot()) {
		ob_start();
			passthru('espeak-ng -v en+' . getRandomVoice(). ' -s10 -k2 -z --stdout "' . escapeshellarg($_SESSION['SBNR_CAPTCHA_SPEAK']) . '" | lame --silent -b 16 - -');
			$soundFile = ob_get_contents();
		ob_end_clean();
		header('Content-Type: audio/mpeg');
		print($soundFile);
	} else {
		http_response_code(400);
	} //Don't generate the "expensive" audio captcha if it might be a bot to prevent DoS
}

function getCaptchaAudio() {
	if(!isLikelyBot()) {
		return '<audio controls src="sbnr/captcha_audio.php" preload="none"></audio>';
	} else {
		return ""; //Prevent including the audio element if it is useless
	}
}

function getCaptchaRandom($clear = true) {
	switch(random_int(0, 1)) {
		case 0:
			return getCaptchaText($clear);
		case 1:
			return getCaptchaMath($clear);
	}
}

function getCaptchaMultiple($numParts = 2) {
	clearCaptchaStore();
	$outputImage = imagecreatetruecolor(300, 100 * $numParts);
	for ($i = 0; $i < $numParts; $i++) {
		imagecopy($outputImage, getCaptchaRandom(false), 0, 100 * $i, 0, 0, 300, 100);
	}
	return $outputImage;
}

function getJSChallenge($haystack = 255) {
	$strAnswer = generateRandomString(8, true);
	$strAnswerHash = hash("sha512", $strAnswer);
	$_SESSION['SBNR_CAPTCHA_ANSWER_JS'] = $strAnswer;
	$arrPotentials = array($strAnswer);
	for ($i = 0; $i < $haystack; $i++) {
		array_push($arrPotentials, generateRandomString(8, false));
	}
	shuffle($arrPotentials); //XXX: this is not secure

	foreach ($arrPotentials as $potential) {
		$strPotentials .= "'" . $potential . "',";
	}

$challenge = '
<script type="text/javascript">
window.onload = (event) => {
	let strAnswerHash = "' . $strAnswerHash . '";
	let arrPotentials = [' . $strPotentials . '];

	arrPotentials.every(async function(item, index, array) {
		const hash = await sha512(item);
		if(hash == strAnswerHash) {
			document.getElementById("txtJSChallenge").value = item;
			console.log("SBNR JS Challenge: Solved at " + index);
			return false;
		}
		return true;
	});
};
</script>';
	return $challenge;
}

function checkJSChallengeAnswer($response) {
	return $response === $_SESSION['SBNR_CAPTCHA_ANSWER_JS'];
}

function clearCaptchaStore() {
	$_SESSION['SBNR_CAPTCHA_ANSWER'] = "";
	$_SESSION['SBNR_CAPTCHA_SPEAK'] = "";
}

function appendCaptchaStore($answer, $raw) {
	$_SESSION['SBNR_CAPTCHA_ANSWER'] .= " " . $answer;
	if(strlen($_SESSION['SBNR_CAPTCHA_SPEAK']) > 0) {
		$_SESSION['SBNR_CAPTCHA_SPEAK'] .= " and";
	}
	$_SESSION['SBNR_CAPTCHA_SPEAK'] .= " " . $raw;
}

function relaxString($text) {
	$text = strtolower($text); //Set to lowercase
	$text = preg_replace('/\s+/', '', $text); //Remove all whitespace
	return $text;
}

function checkCaptchaAnswer($response, $relaxed) {
	if($relaxed) {
		$response = relaxString($response);
		$_SESSION['SBNR_CAPTCHA_ANSWER'] = relaxString($_SESSION['SBNR_CAPTCHA_ANSWER']);
	}
	return $response == $_SESSION['SBNR_CAPTCHA_ANSWER'];
}

function getImage($image) {
	ob_start();
		imagepng($image);
		$imagePNG = ob_get_contents();
	ob_end_clean();
	return $imagePNG;
}

function getImageBase64($image) {
	$imagePNG = getImage($image);
	return 'data:image/png;base64,' . base64_encode($imagePNG);
}

?>
