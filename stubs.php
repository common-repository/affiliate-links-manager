<?php
class WalmStubs
{
	function get($string)
	{
		if (filter_var($string, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED))
		{
			$string = substr($string, strlen(get_bloginfo('url')));
		}
		$string = trim($string, '/');

		preg_match('/^(.*?)\//', $string, $matches);

		return $matches[1];
	}
	function add($string)
	{
		$arr = get_option('walm-trigger');

		if (is_array($arr))
		{
			if (!in_array($string, $arr))
			{
				$arr[] = $string;
				update_option('walm-trigger', $arr);
			}
		}
		else
		{
			unset($arr);
			$arr[] = $string;
			update_option('walm-trigger', $arr);
		}
	}
	function delete($string)
	{
		$arr = get_option('walm-trigger');
		if ($key = array_search($string, $arr))
		{
			unset($arr[$key]);
			$arr = array_values($arr);
			update_option($arr);
		}
	}
}
