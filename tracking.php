<?php
/*
 *	TRACKING FUNCTIONS -- STOLEN FROM GOOGLE ANALYTICS FOR WORDPRESS BY YOAST. Arr, GPL FTW!
 */
class WalmTracking
{
	function WalmTracking()
	{
		/*
		* ADD ANALYTICS TRACKING TO ARTICLES, SIDEBAR, EXCERPT AND COMMENTS
		*/
		add_filter('the_content', array(&$this,'track_filter_content'), 99);
		add_filter('widget_text', array(&$this,'track_filter_content'), 99);
		add_filter('the_excerpt', array(&$this,'track_filter_content'), 99);
		add_filter('comment_text', array(&$this,'track_filter_content'), 99);
	}

	function track_filter_content($text)
	{
		if (is_feed())
			return $text;

		static $anchorPattern = '/<a (.*?)href=[\'\"](.*?)\/\/([^\'\"]+?)[\'\"](.*?)>(.*?)<\/a>/i';
		$text = preg_replace_callback($anchorPattern, array(&$this,'track_parse_link'), $text);

		return $text;
	}

	function track_parse_link($matches)
	{
		$origin = $this->track_get_domain($_SERVER["HTTP_HOST"]);
		$target = $this->track_get_domain($matches[3]);

		if ($target['domain'] == $origin['domain'])
		{
			$pageURL = $matches[2] . '//' . $matches[3];
			$stub = WalmStubs::get($pageURL);
			$stubs = get_option("walm-trigger");

			if (!is_array($stubs)) return;

			// If URL has a stub that we know -- do the stuff.
			if (in_array($stub, $stubs))
			{
        $parsedURL = parse_url($pageURL);
				$short = trim($parsedURL['path'], '/');

				$trackBit = "_gaq.push(['_trackPageview','/$short/']);";

				if (preg_match('/onclick=[\'\"](.*?)[\'\"]/i', $matches[4]) > 0)
				{
					// Check for manually tagged outbound clicks, and replace them with the tracking of choice.
					if (preg_match('/.*_track(Pageview|Event).*/i', $matches[4]) > 0) {
						$matches[4] = preg_replace('/onclick=[\'\"](javascript:)?(.*;)?[a-zA-Z0-9]+\._track(Pageview|Event)\([^\)]+\)(;)?(.*)?[\'\"]/i', 'onclick="javascript:' . $trackBit .'$2$5"', $matches[4]);
					} else {
						$matches[4] = preg_replace('/onclick=[\'\"](javascript:)?(.*?)[\'\"]/i', 'onclick="javascript:' . $trackBit .'$2"', $matches[4]);
					}
				}
				else
				{
					$matches[4] = 'onclick="javascript:' . $trackBit . '"' . $matches[4];
				}
			}
		}
		$matches[4] = ($matches[4] == ' ' || empty($matches[4])) ? '' : ' ' . trim($matches[4]);

		$return = '<a ' . $matches[1] . 'href="' . $matches[2] . '//' . $matches[3] . '"' . $matches[4] . '>' . $matches[5] . '</a>';

		return $return;
	}

	function track_get_domain($uri)
	{
		$hostPattern = "/^(http:\/\/)?([^\/]+)/i";
		$domainPatternUS = "/[^\.\/]+\.[^\.\/]+$/";
		$domainPatternUK = "/[^\.\/]+\.[^\.\/]+\.[^\.\/]+$/";

		preg_match($hostPattern, $uri, $matches);
		$host = $matches[2];
		if (preg_match("/.*\..*\..*\..*$/",$host))
			preg_match($domainPatternUK, $host, $matches);
		else
			preg_match($domainPatternUS, $host, $matches);

		return array("domain"=>$matches[0],"host"=>$host);
	}
}
$walm_tracking = new WalmTracking();
