<?php
/*
Plugin Name: WinkPress Affiliate Links Manager
Plugin URI: http://winkpress.com/walm/
Description: Manage affiliate links in WordPress
Author: WinkPress
Author URI: http://winkpress.com/
Version: 1.0
*/
require('stubs.php');
require('tracking.php');

global $wpdb;
define('WALM_LINKS', $wpdb->prefix . 'walm_links');

class walm
{
	function walm()
	{
		add_action('admin_menu', array(&$this, 'on_admin_menu'));
		add_action('init', array(&$this, 'redirect'));
		register_activation_hook(__FILE__, array(&$this, 'on_activation'));
	}

	function print_feedback($walm_errors)
	{
		if (is_wp_error($walm_errors) && $walm_errors->get_error_codes())
		{
			$walm_codes = $walm_errors->get_error_codes();

			foreach($walm_codes as $walm_code)
			{
				echo '<div id="message" class="error"><p>' . $walm_errors->get_error_message($walm_code) . '</p></div>';
			}
		}
		else
		{
			if ($_GET['action'] == 'edit' && isset($_POST['walm_action']))
			{
				echo '<div id="message" class="updated"><p>Link updated successfully. Check <a href="' .
				admin_url('link-manager.php?page=walm&amp;tab=view-links') . '">links list</a>.</p></div>';
			}

			if (isset($_POST['walm_action']) && !isset($_GET['action']))
			{
				echo '<div id="message" class="updated"><p>Link added successfully. Check <a href="' .
				admin_url('link-manager.php?page=walm&amp;tab=view-links') . '">links list</a>.</p></div>';
			}

			if ($_GET['page'] == 'walm' && $_GET['action'] == 'delete' && $_GET['tab'] == 'view-links' && !isset($_POST['walm-bulk']))
			{
				echo '<div id="message" class="updated"><p>Link has been deleted.</p></div>';
			}

			if ($_GET['page'] == 'walm' && $_POST['walm-bulk'] == 'delete' && $_GET['tab'] == 'view-links')
			{
				echo '<div id="message" class="updated"><p>Selected links have been deleted.</p></div>';
			}
		}
	}

	function save_settings()
	{
		global $wpdb;
		$walm_errors = new WP_Error();

		// ADD NEW LINK VALIDATION
		if ($_GET['page'] == 'walm' && isset($_POST['walm_action']))
		{
			if (!current_user_can('manage_options'))
				wp_die(__('Cheatin&#8217; uh?'));

			check_admin_referer('walm-post');

			$_POST['id'] = esc_html($_POST['link_id']);
			$_POST['name'] = esc_html($_POST['link_name']);
			$_POST['original'] = $_POST['original_link'];
			$_POST['short'] = esc_html(strtolower($_POST['short_link']));

			if (empty($_POST['name']))
			{
				$walm_errors->add('empty-link-name', "You forgot to give your link a name.");
			}
			else
			{
				if (!isset($_GET['link_id']))
				{
					$query = "SELECT id FROM `" . WALM_LINKS . "` WHERE `name` = '" . $_POST['name'] . "';";
					$duplicate_exists = $wpdb->get_var($query);
					if ($duplicate_exists)
					{
						$walm_errors->add('duplicate-link-name', "Another link with the same name already exists.");
					}
				}
			}

			if (empty($_POST['original']))
			{
				$walm_errors->add('empty-original-link', "You forgot to enter an original link.");
			}
			else
			{
				$is_url = filter_var($_POST['original'], FILTER_VALIDATE_URL);

				if (!$is_url)
				{
					$walm_errors->add('original-link-bad-form',
					"Your original link doesn't look like a real link: <code>" . stripcslashes($_POST['original']) . "</code>.");
				}
			}

			if (empty($_POST['short']))
			{
				$walm_errors->add('empty-short-link', "You forgot to enter a short link.");
			}
			else
			{
				$short_has_stub = filter_var($_POST['short'], FILTER_VALIDATE_REGEXP,
																 array("options" => array("regexp" => "/.*?\/.*/i")));

				if (!$short_has_stub)
				{
					$walm_errors->add('missing-stub',
									"A short link should <strong>at least</strong> be two levels, i.e. <code>first-level/second-level</code>.");
				}
				else
				{
					$query = "SELECT id FROM `" . WALM_LINKS . "` WHERE `short` = '" . trim($_POST['short'], '/') . "';";
					$duplicate_exists = $wpdb->get_var($query);

					if ($duplicate_exists && $_GET['link_id'] != $duplicate_exists)
					{
						$walm_errors->add('duplicate-short-link', "A duplicate short link already exists.");
					}
					else
					{
						$final_url = get_bloginfo('url') . '/' . $_POST['short'];
						$is_url = filter_var($final_url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED);

						if ($is_url === false)
						{
							$walm_errors->add('short-link-bad-form',
										"Your short link doesn't look like a real link: <code>" . stripcslashes($final_url) . "</code>.");
						}
						else
						{
							$stub = WalmStubs::get($_POST['short']);

							global $wp_rewrite;

							$rules = $wp_rewrite->rewrite_rules();

							foreach ($rules as $rule => $key)
							{
								$slug = explode('/', $rule);
								$slug = $slug[0];

								$slugs_arr[] = trim($slug, ")(");
							}

							if (is_array($slugs_arr))
							{
								$slugs_arr = array_unique($slugs_arr);
								if (in_array($stub, $slugs_arr))
								{
									$walm_errors->add('short-link-permalink-conflict',
											"You have an existing URL in your site that begins with <code>" .
											$stub . "</code>. Choose a different stub to avoid potential conflicts");
								}
							}
						}
					}
				}
			}

			if (!$walm_errors->get_error_codes())
			{
				$stub = WalmStubs::get($_POST['short']);
				WalmStubs::add($stub);

				if ($_POST['walm_action'] == 'add' && !$this->insert_link($_POST))
				{
					$walm_errors->add('add-link-failure', "Link couldn't be added. Please double check your values.");
				}
			}
		}

		// PROCESS BULK ACTIONS
		if ($_GET['page'] == 'walm' && $_POST['walm-bulk'])
		{
			if (!current_user_can('manage_options'))
				wp_die(__('Cheatin&#8217; uh?'));

			check_admin_referer('walm-bulk-action');

			$links_to_be_deleted = $_POST['checked_links'];

			if (is_array($links_to_be_deleted))
			{
				foreach($links_to_be_deleted as $link_id)
				{
					if (!$this->delete_link($link_id))
					{
						if (!$msg_added)
						{
							$walm_errors->add('link-bulk-delete-failure', "Error. Some links couldn't be deleted.");
							$msg_added = true;
						}
					}
				}
			}

		}

		if ($_GET['page'] == 'walm' && isset($_GET['action']) && !isset($_POST['walm-bulk']))
		{
			$action = $_GET['action'];
			$link_id = $_GET['link_id'];

			if (!current_user_can('manage_options'))
				wp_die(__('Cheatin&#8217; uh?'));

			switch($action)
			{
				case 'delete':
					if (!$wpdb->query($wpdb->prepare("DELETE FROM " .	WALM_LINKS . " WHERE id = %d", $link_id)))
						$walm_errors->add('link-delete-failure', "Error. Link couldn't be deleted.");
					break;
			}
		}

		return $walm_errors;
	}

	// create the admin menu
	function on_admin_menu()
	{
		$this->pagehook = add_links_page('WinkPress Affiliate Links Manager',
																		 'Affiliate links',
																		 'activate_plugins',
																		 'walm',
																		 array(&$this, 'view'));
	}

	function plugin_admin_url()
	{
		return admin_url('link-manager.php?page=walm');
	}

	//executed to show the plugins complete admin page
	function view()
	{
?>
		<div id="walm" class="wrap">
		<?php screen_icon('link-manager'); ?>
		<h2>Affiliate Links</h2>

		<ul class="subsubsub">
<?php
		$tabs = array(
			'' => 'Add an affiliate link',
			'view-links' => 'See all links');

		$tabhtml = array();

		foreach ($tabs as $tab => $title)
		{
			if ($tab == '')
			{
				$class = ($tab == $_GET['tab']) ? ' class="current"' : '';
				$tabhtml[] =	'<li><a href="' .
											admin_url('link-manager.php?page=walm') .
											'"' . $class . ">$title</a>";
			}
			else
			{
				$class = ($tab == $_GET['tab']) ? ' class="current"' : '';
				$tabhtml[] =	'<li><a href="' .
											admin_url('link-manager.php?page=walm&amp;tab=' . $tab) .
											'"' . $class . ">$title</a>";
			}
		}

		echo implode( " |</li>\n", $tabhtml ) . '</li>';
?>
	</ul>
	<br style="clear: both;" />
	<?php $walm_errors = $this->save_settings(); ?>
	<?php $this->print_feedback($walm_errors); ?>
	<?php
		switch($_GET['tab'])
		{
			case null:
				$this->view_add_link();
				break;
			case 'view-links':
				$this->view_links();
				break;
		}
?>
		<div style="margin-top: 20px; font-size: 10px;" class="description">If you use this plugin to manage your affiliate links, please support me by linking to my homepage, <a href="http://winkpress.com/">WinkPress</a>.</div>
		</div>
<?php
	}

	function view_add_link() { include('view/add-link.php'); }
	function view_links() { include('view/view-links.php'); }

	function delete_link($link_id)
	{
		global $wpdb;
		return $wpdb->query($wpdb->prepare("DELETE FROM " .	WALM_LINKS . " WHERE id = %d", $link_id));
	}
	function insert_link($linkdata, $error = false)
	{
		global $wpdb;

		$defaults = array('id' => 0, 'name' => '', 'original' => '', 'short' => '');

		$linkdata = wp_parse_args($linkdata, $defaults);

		extract(stripslashes_deep($linkdata), EXTR_SKIP);

		$update = false;

		if (!empty($id))
			$update = true;

		if ((trim($original) == '') || (trim($short) == '') || (trim($name) == ''))
			return 0;

		if (!filter_var($original, FILTER_VALIDATE_URL))
			return 0;


		$short = trim($short, '/');

		if ($update)
		{
			$values = array(
				'name' => $name,
				'original' => $original,
				'short' => $short);

			if (false === $wpdb->update(WALM_LINKS, $values, compact('id')))
			{
					return 0;
			}
		}
		else
		{
			$values = array(
				'name' => $name,
				'original' => $original,
				'short' => $short);

			if (false === $wpdb->insert(WALM_LINKS, $values))
			{
					return 0;
			}
			$id = (int) $wpdb->insert_id;
		}

		return $id;
	}

	function redirect()
	{
		global $wpdb;

		// Build URL
		$pageURL = 'http';
 		if ($_SERVER["HTTPS"] == "on") { $pageURL .= "s"; }
 		$pageURL .= "://";

		if ($_SERVER["SERVER_PORT"] != "80")
		{
			$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		}
		else
		{
			$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		}

		$stub = WalmStubs::get($pageURL);
		$stubs = get_option("walm-trigger");

		if (!is_array($stubs)) return;

		// If URL has a stub that we know -- do the stuff.
		if (in_array($stub, $stubs))
		{
			$url = parse_url($pageURL);
			$short = explode(get_bloginfo('url') . "/", $url['scheme']."://".$url['host'].$url['path']);
			$short = trim($short[1], '/');

			$query = "SELECT name,original FROM `" . WALM_LINKS . "` WHERE `short` = '$short';";
			$redirect = $wpdb->get_row($query, ARRAY_A);

			if (is_null($redirect))
				return;

			extract($redirect); // $original, $name

			if (!empty($_GET['goto']))
			{
 				$pos = strpos($url['query'], 'goto=');
				$original = substr_replace($url['query'], '', $pos, strlen('goto='));
			}

			if (!empty($original))
			{
				wp_redirect($original, 301);
				exit;
			}
		}
	}

	function on_activation()
	{
	   global $wpdb;

	   $table_name = $wpdb->prefix . "walm_links";

	   if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
	   {
			$sql =	"CREATE TABLE " . $table_name . " (
					id mediumint(9) NOT NULL AUTO_INCREMENT,
					name varchar(255) NOT NULL,
					original varchar(1000) NOT NULL,
					short varchar(300) NOT NULL,
					date TIMESTAMP,
					UNIQUE KEY id (id));";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
	   }
	}
}
$walm = new walm();
