<?php
/*
Plugin Name: wpCommentTwit
Plugin URI: http://www.tyleringram.com
Description: Sends a Direct Message via Twitter to the blog owner about a new comment that was left.
Author: Tyler Ingram
Version: 0.5
Author URI: http://www.tyleringram.com
*/

$wpComment_plugin_name = 'wpCommentTwit';
$wpComment_plugin_prefix = 'wpCommentTwit_';
$wpComment_plugin_ver = '0.5';

function twit_send_dm($username, $password, $target, $text)
{
	global $wpComment_plugin_prefix;

	$ch = curl_init('http://twitter.com/direct_messages/new.xml');
	$headers = array('X-Twitter-Client: ', 'X-Twitter-Client-Version: ', 'X-Twitter-Client-URL: ');

	if($username !== false && $password !== false)  {
		curl_setopt($ch, CURLOPT_USERPWD, $username .':'. $password); 
	} else {
		die;
	}
	$data = 'user=' . $target . '&text=' . urlencode($text);

	curl_setopt ($ch, CURLOPT_POST, true); 
	curl_setopt ($ch, CURLOPT_POSTFIELDS, $data);
         
	curl_setopt($ch, CURLOPT_VERBOSE, 1); 
	curl_setopt($ch, CURLOPT_HEADER, 0); 
	curl_setopt($ch, CURLOPT_USERAGENT, $wpComment_plugin_name .' '. $wpComment_plugin_ver);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	$response = curl_exec($ch); 
	
	curl_close($ch); 
}

function twit_comment_published($comment_ID)
{
	global $wpComment_plugin_prefix;
	global $wpdb;

	$message = get_option($wpComment_plugin_prefix . 'message');
	
	$wpComment_username = get_option($wpComment_plugin_prefix . 'username', 0);
	$wpComment_password = get_option($wpComment_plugin_prefix . 'password', 0);
	$wpComment_apikey = get_option($wpComment_plugin_prefix . 'apikey', 0);
	$wpComment_bitlyuser = get_option($wpComment_plugin_prefix . 'bitlyuser', 0);
	
	$s = "SELECT comment_ID, comment_approved, comment_post_ID FROM wp_comments WHERE comment_ID = '{$comment_ID}' AND comment_approved = 1 AND comment_type = ''";
	$q = $wpdb->get_results($s, ARRAY_A);
	
	if(count($q) > 0) {		
		$url = get_permalink($q[0]['comment_post_ID']). "#comment-{$comment_ID}";				// Get the URL of the Post that is being commented on

		$bitly = "http://api.bit.ly/shorten?version=2.0.1&longUrl=". urlencode($url) . "&login=". $wpComment_bitlyuser . "&apiKey=".$wpComment_apikey."&format=json&history=1";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $bitly);
		curl_setopt($ch, CURL_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		$result = curl_exec($ch);
		curl_close($ch);
		
		$obj = json_decode($result, true);
		
		if($obj['errorCode'] == 0) {
			$newURL = $obj['results'][$url]['shortUrl'];
			
			$message .= ' - '. $newURL;
			// Send DM
			twit_send_dm($wpComment_username, $wpComment_password, $wpComment_username, $message);
		} // else we do nothing
	}

}

function wpCommentTwit_options_subpanel()
{
	global $wpComment_plugin_name;
	global $wpComment_plugin_ver;
	global $wpComment_plugin_prefix;

  	if (isset($_POST['info_update'])) 
	{
		if (isset($_POST['username'])) {
			$username = trim($_POST['username']);
		} else {
			$username = '';
		}

		if (isset($_POST['password'])) {
			$password = trim($_POST['password']);
		} else {
			$password = '';
		}
		if(isset($_POST['target'])) {
			$target = trim($_POST['target']);
		} else {
			$target = '';
		}
		if(isset($_POST['apikey'])) {
			$apikey = trim($_POST['apikey']);
		} else {
			$apikey = '';
		}
		if(isset($_POST['bitlyuser'])) {
			$bitlyuser = trim($_POST['bitlyuser']);
		} else {
			$bitlyuser = '';
		}

		if (isset($_POST['message'])) {
			$message = $_POST['message'];
		} else {
			$message = '';
		}

		update_option($wpComment_plugin_prefix . 'username', $username);
		update_option($wpComment_plugin_prefix . 'password', $password);
		update_option($wpComment_plugin_prefix . 'apikey', $apikey);
		update_option($wpComment_plugin_prefix . 'bitlyuser', $bitlyuser);
		update_option($wpComment_plugin_prefix . 'message', stripslashes($message));

	} 

	$username = get_option($wpComment_plugin_prefix . 'username');
	$password = get_option($wpComment_plugin_prefix . 'password');
	$message = get_option($wpComment_plugin_prefix . 'message');
	$apikey = get_option($wpComment_plugin_prefix . 'apikey');
	$bitlyuser = get_option($wpComment_plugin_prefix . 'bitlyuser');
	
	if (strlen($message) == 0) {
		$message = "A new comment has been posted on ". bloginfo('name'); 
		update_option($wpComment_plugin_prefix . 'message', $message);
	}

	echo '<div class=wrap><form method="post">';
	echo '<h2>' . $wpComment_plugin_name . ' Options</h2>';
	echo "<small>Version: {$wpComment_plugin_ver}</small><br />";

	?>	
		<p>wpCommentTwit is a plugin that will notify you of a new comment on your blog by DirectMessage (DM) via Twitter.</p>
		<p>You will only receive a DM if the comment is not flagged as spam or was approved manually.</p>
		<p>
		<p>Currently this plugin utilizes the <a href="http://bit.ly">bit.ly</a> hash format for shortening URLs.</p>
		<h3 style="text-decoration: underline;">General Options</h3>

		<p><strong><cite>Twitter Username</cite></strong> - Your Twitter account username and the account the DM will be sent to. </p>
		<p><strong><cite>Twitter Password</cite></strong> - Your Twitter account password for the above account. </p>
		<p><strong><cite>Bit.ly API Key</cite></strong> - Sign up for a <a href="http://bit.ly">Bit.ly</a> account (takes less than 30 seconds) to access your Username &amp; API Key</p>
		<p><strong><cite>Bit.ly Username</cite></strong> Username for which you log into <a href="http://bit.ly/account">http://bit.ly</a></p>
		<p><strong><cite>Message</cite></strong> - The text you want to send to yourself. The shortened URL will be attached after this value.</p>
		<hr />
		<table class="form-table" cols="2">
			<tr><th>Twitter Username</th><td><input type="text" name="username" value="<?php echo($username); ?>" /></td></tr>
			<tr><th>Twitter Password</th><td><input type="password" name="password" value="<?php echo($password); ?>" /></td></tr>
			<tr><th>Bit.ly API Key</th><td><input type="text" name="apikey" value="<?php echo $apikey;?>" /></td><tr>
			<tr><th>Bit.ly Username</th><td><input type="text" name="bitlyuser" value="<?php echo $bitlyuser;?>" /></td><tr>
			<tr><th>Message</th><td><input type="text" name="message" value="<?php echo(htmlentities($message)); ?>" size="70" maxlength="140"/><br /> <small>Please keep below 100 characters.</small></td></tr>
		</table>
		<div class="submit"><input type="submit" name="info_update" value="Update Options" /></div>
		</form>
	
		<small>Please help me out and </small> <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
			<input type="hidden" name="cmd" value="_xclick">
			<input type="hidden" name="business" value="gimli_aa@hotmail.com">
			<input type="hidden" name="item_name" value="Tyler Ingram">
			<input type="hidden" name="page_style" value="PayPal">
			<input type="hidden" name="buyer_credit_promo_code" value="">
			<input type="hidden" name="buyer_credit_product_category" value="">
			<input type="hidden" name="buyer_credit_shipping_method" value="">
			<input type="hidden" name="buyer_credit_user_address_change" value="">
			<input type="hidden" name="no_shipping" value="0">
			<input type="hidden" name="no_note" value="1">
			<input type="hidden" name="currency_code" value="USD">
			<input type="hidden" name="tax" value="0">
			<input type="hidden" name="lc" value="CA">
			<input type="hidden" name="bn" value="PP-DonationsBF">
			<input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but21.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">

		</form>
		<p style="font-size: 8pt;"><strong>wpCommentTwit</strong> was created and maintained by Tyler Ingram - <a href="http://www.dynamicshark.com">www.dynamicshark.com</a> - 
			<a href="http://www.twitter.com/TylerIngram">Follow Me Twitter Too!</a></p>

	<?php
	echo('</div>');
}

function wpCommentTwit_add_plugin_option() {
	global $wpComment_plugin_name;
	if (function_exists('add_options_page')) {
		add_options_page($wpComment_plugin_name, $wpComment_plugin_name, 0, basename(__FILE__), 'wpCommentTwit_options_subpanel');
    }	
}

add_action('admin_menu', 'wpCommentTwit_add_plugin_option');

add_action('comment_post', 'twit_comment_published');

?>
