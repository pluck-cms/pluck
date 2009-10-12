<?php
function contactform_theme_main() {
	global $lang;

	//Define some variables.
	if (isset($_POST['contactform_name']))
		$name = $_POST['contactform_name'];
	if (isset($_POST['contactform_sender']))
		$sender = $_POST['contactform_sender'];
	if (isset($_POST['contactform_message']))
		$message = $_POST['contactform_message'];

	//Then show the contactform.
	?>
		<form method="post" action="" id="contactform">
			<div>
				<label for="contactform_name"><?php echo $lang['general']['name']; ?></label>
				<br />
				<input name="contactform_name" id="contactform_name" type="text" />
				<br />
				<label for="contactform_sender"><?php echo $lang['general']['email']; ?></label>
				<br />
				<input name="contactform_sender" id="contactform_sender" type="text" />
				<br />
				<label for="contactform_message"><?php echo $lang['general']['message']; ?></label>
				<br />
				<textarea name="contactform_message" id="contactform_message" rows="7" cols="45"></textarea>
				<br />
				<input type="submit" name="submit" value="<?php echo $lang['general']['send']; ?>" />
			</div>
		</form>
	<?php
	//If the the contactform was submitted.
	if (isset($_POST['submit'])) {
		//Check if all fields were filled.
		if ($name && $sender && $message) {
			//TODO: We need a better way to check for spam.

			//Sanitize the fields.
			$name = sanitize($name);
			$sender = sanitize($sender);
			$message = sanitize($message);

			//Change enters in their html-equivalents.
			$message = nl2br($message);

			//Now we're going to send our email.
			if (mail(EMAIL, $lang['contactform']['email_title'].$name, '<html><body>'.$message.'</body></html>', 'From: '.$sender."\n".'Content-type: text/html; charset=utf-8'))
				echo $lang['contactform']['been_send'];
			//If email couldn't be send.
			else
				echo $lang['contactform']['not_send'];
		}
		//If not all fields were filled.
		else
			echo '<span class="error">'.$lang['contactform']['fields'].'</span>';
	}
}
?>