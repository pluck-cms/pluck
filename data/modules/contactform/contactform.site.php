<?php
function contactform_theme_main() {
	global $lang_contact3, $lang_contact4, $lang_contact5, $lang_contact6, $lang_contact7, $lang_contact8, $lang_contact9, $lang_contact10;

	//Define some variables.
	if (isset($_POST['name']))
		$name = $_POST['name'];
	if (isset($_POST['sender']))
		$sender = $_POST['sender'];
	if (isset($_POST['message']))
		$message = $_POST['message'];

	//Then show the contactform.
	?>
		<form method="post" action="" id="contactform">
			<div>
				<label for="name"><?php echo $lang_contact3; ?></label>
				<br />
				<input name="name" id="name" type="text" />
				<br />
				<label for="sender"><?php echo $lang_contact4; ?></label>
				<br />
				<input name="sender" id="sender" type="text" />
				<br />
				<label for="message"><?php echo $lang_contact5; ?></label>
				<br />
				<textarea name="message" id="message" rows="7" cols="45"></textarea>
				<br />
				<input type="submit" name="Submit" value="<?php echo $lang_contact10; ?>" />
			</div>
		</form>
	<?php
	//If the the contactform was submitted.
	if (isset($_POST['Submit'])) {
		//Check if all fields were filled.
		if ($name && $sender && $message) {
			//TODO: We need a better way to check for spam.

			//Sanitize the fields.
			$name = sanitize($name);
			$sender = sanitize($sender);
			$message = sanitize($message);

			//Change enters in their html-equivalents.
			$message = str_replace ("\n",'<br>', $message);

			//Now we're going to send our email.
			if (mail(EMAIL, $lang_contact7.$name, '<html><body>'.$message.'</body></html>', 'From: '.$sender."\n".'Content-type: text/html; charset=utf-8'))
				echo $lang_contact8;
			//If email couldn't be send.
			else
				echo $lang_contact9;
		}
		//If not all fields were filled.
		else
			echo '<span class="red">'.$lang_contact6.'</span>';
	}
}
?>