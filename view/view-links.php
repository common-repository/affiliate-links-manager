<?php
global $wpdb;
$query = "SELECT * FROM " . WALM_LINKS . " ORDER BY date DESC;";
$links = $wpdb->get_results($query, OBJECT);
?>
<?php if ($links): ?>
<style>
#walm th { white-space: nowrap; }
#walm .textcenter { text-align: center; }
</style>
<form action="" method="post">
<?php wp_nonce_field('walm-bulk-action'); ?>
<div class="tablenav top">
	<div class="alignleft actions">
		<select name="walm-bulk">
			<option selected="selected" value="-1">Bulk Actions</option>
			<option value="delete">Delete</option>
		</select>
		<input type="submit" value="Apply" class="button-secondary action" id="doaction" name="">
	</div>
	<br class="clear">
</div>
<table class="widefat" cellspacing="0" id="walm">
	<thead>
		<tr>
			<th class="check-column" style="" " id="cb" scope="col"><input type="checkbox"></th>
			<th scope="col" id="name">Name</th>
			<th scope="col" id="short">Short link</th>
			<th class="textcenter" scope="col" id="original">Destination</th>
			<th class="textcenter" scope="col" id="date">Modified on</th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<th class="check-column" id="cb" scope="col"><input type="checkbox"></th>
			<th scope="col" id="name">Name</th>
			<th scope="col" style="" id="short">Short link</th>
			<th class="textcenter" scope="col" id="original">Destination</th>
			<th class="textcenter" scope="col" id="date">Modified on</th>
		</tr>
	</tfoot>

	<tbody>
<?php

foreach($links as $link)
{
	$name = esc_attr($link->name);
	$short = $link->short;
	$id = $link->id;
	$date = date('d-M-o', strtotime($link->date));

	$original = $link->original;
	$alternate = ($alternate == 'class="alternate"') ? '' : 'class="alternate"';

	$edit_link	= admin_url("link-manager.php?page=walm&amp;action=edit&amp;link_id=$id");

?>
	<tr <?php echo $alternate; ?>>
		<th class="check-column"><input type="checkbox" value="<?php echo $id; ?>" name="checked_links[]"></th>
		<td>
			<strong><a href="<?php echo $edit_link; ?>" title="Edit <?php echo $name; ?>"><?php echo $name; ?></a></strong>
			<div class="row-actions">
				<span class="edit">
					<a href="<?php echo $edit_link; ?>" title="Edit <?php echo $name; ?>">Edit</a> |
				</span>
				<span class="delete">
					<a title="Delete <?php echo $name; ?>" onclick="if ( confirm('You are about to delete <?php echo $name; ?>.\n\'Cancel\' to stop, \'OK\' to delete.') ) { return true;} return false;" href="<?php echo wp_nonce_url('link-manager.php?page=walm&amp;tab=view-links&amp;action=delete&amp;link_id=' . $id); ?>" class="submitdelete">Delete</a>
				</span>
			</div>
		</td>
		<?php $print_short = get_bloginfo('url') . '/' . $short . '/'; ?>
		<td><a href="<?php echo $print_short; ?>" target="_blank" title="Open <?php echo $name; ?> in a new window..."><?php echo $print_short; ?></a></td>
		<td class="textcenter"><a href="<?php echo $original; ?>" target="_blank" title="Redirects to: <?php echo $original; ?>"><img src="<?php echo plugins_url('affiliate-links-manager'); ?>/right-arrow.gif" alt="Right arrow" /></a></td>
		<td class="textcenter"><?php echo $date; ?></td>
	</tr>

<?php
}
?>
	</tbody>
</table>
</form>
<?php
else:
	echo "<p>You don't have any links.</p>";
endif;
