<?php
if ($_GET['action'] == 'edit' && isset($_GET['link_id']))
{
	global $wpdb;
	$link = $wpdb->get_row("SELECT * FROM " . WALM_LINKS . " WHERE id = '" . $_GET['link_id'] . "';");
	$name = (isset($_POST['link_name'])) ? stripcslashes($_POST['link_name']) : $link->name;
	$original = (isset($_POST['original_link'])) ? stripslashes($_POST['original_link']) : $link->original;
	$short = (isset($_POST['short_link'])) ? stripcslashes($_POST['short_link']) : trailingslashit($link->short);
	$link_id = $_GET['link_id'];
	$button_text = 'Update link';
}
else
{
	$name = stripcslashes($_POST['link_name']);
	$original = stripslashes($_POST['original_link']);
	$short = stripslashes($_POST['short_link']);
	$button_text = 'Add link';
}

?>
<div id="add-new">
<form action="" method="post">
	<?php wp_nonce_field('walm-post'); ?>
	<input type="hidden" name="action" value="walm_post" />

	<div id="poststuff">
		<div id="post-body-content">
			<div id="link-name" class="stuffbox">
				<h3><label for="link_name">Link name</label></h3>
				<div class="inside">
					<input style="width: 98%;" type="text" name="link_name" size="30" value="<?php echo $name; ?>" id="link_name" />
				    <p>Give your affiliate link a human friendly name.</p>
				</div>
			</div>
			<div id="original-link" class="stuffbox">
				<h3><label for="original_link">Original link</label></h3>
				<div class="inside">
					<input style="width: 98%;" type="text" name="original_link" size="30" value="<?php echo $original; ?>" id="original_link" />
				    <p>Paste the original affiliate link that you got from the vendor.</p>
				</div>
			</div>
			<div id="short-link" class="stuffbox">
				<h3><label for="short_link">Short link</label></h3>
				<div class="inside">
					<table>
						<tr>
							<td style="white-space:nowrap;"><?php bloginfo('url'); ?>/</td><td style="width: 98%;"><input <?php echo $onfocus; ?> name="short_link" id="short_link" style="width: 100%;" type="text" value="<?php echo $short; ?>" class="text"></td>
						</tr>
					</table>
				</div>
			</div>
			<div class="submitbox" id="submitlink">
					<input name="save" type="submit" class="button-primary" id="publish" value="<?php echo $button_text; ?>" />
					<input name="walm_action" type="hidden" value="add" />
					<input name="link_id" type="hidden" value="<?php echo $link_id; ?>" />
			</div>
		</div>
	</div>
	<br class="clear"/>
</form>
</div>
