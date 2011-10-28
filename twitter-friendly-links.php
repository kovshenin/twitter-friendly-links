<?php
/*
Plugin Name: Twitter Friendly Links
Plugin URI: http://kovshenin.com/wordpress/plugins/twitter-friendly-links/
Description: Your very own TinyURL within your OWN domain! If you DO promote your blog posts in Twitter, then you MUST make your links look cool!
Author: Konstantin Kovshenin
Version: 0.5
Author URI: http://kovshenin.com/

	License

    Twitter Friendly Links
    Copyright (C) 2009 Konstantin Kovshenin (kovshenin@live.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
*/

class TwitterFriendlyLinks {
	var $settings = array();
	var $defaultsettings = array();
	var $notices = array();

	function TwitterFriendlyLinks() {
		global $tfl_version;
		$this->defaultsettings = array(
			'version' => 50,
			'style' => '',		// default style is example.com/123
			'format' => 'generic', // default format is generic (numbers only)
			'redirect' => 302,		// temporary redirect by default
			'posts_enabled' => true, // posts enabled by default
			'pages_enabled' => false,	// pages disabled by default
			'attachments_enabled' => false, // attachments disabled by default
			
			'shortlink_base' => get_option('home'),
			
			'twitter_tools_fix' => false, // disabled by deafult
			'askapache_google_404' => false,
			'tweet_this_fix' => false,
			'sociable_fix' => false,
			'retweet_anywhere_fix' => false,
			
			'ga_tracking' => '',
			
			'html_shortlink_rel' => false,
			'http_shortlink_rel' => false,
			'rel_canonical' => false,
			
			'tfl_core_notice' => 0,
		);

		// Setup the settings by using the default as a base and then adding in any changed values
		// This allows settings arrays from old versions to be used even though they are missing values
		$usersettings = (array) get_option('twitter_friendly_links');
		
		if (!isset($usersettings['version']))
			$usersettings['version'] = 0;
		
		$this->settings = $this->defaultsettings;
		if ( $usersettings !== $this->defaultsettings ) {
			foreach ( (array) $usersettings as $key1 => $value1 ) {
				if ( is_array($value1) ) {
					foreach ( $value1 as $key2 => $value2 ) {
						$this->settings[$key1][$key2] = $value2;
					}
				} else {
					$this->settings[$key1] = $value1;
				}
			}
		}
		
		// Register general hooks
		add_filter('template_redirect', array(&$this, 'template_redirect'), 10, 2);
		add_action('admin_menu', array(&$this, 'admin_menu'));
		add_action('admin_notices', array(&$this, 'admin_notices'));
		
		add_filter("manage_posts_columns", array(&$this, "edit_columns"));
		add_action("manage_posts_custom_column", array(&$this, "custom_columns"));
		
		// Filters and Actions
		if ($this->settings['twitter_tools_fix'])
			add_filter('tweet_blog_post_url', 'permalink_to_twitter_link', 10, 1);
		if ($this->settings['tweet_this_fix'])
			add_filter('the_content', 'tfl_tweet_this_fix', 10);
		if ($this->settings['sociable_fix'])
			add_filter('sociable_link', 'tfl_sociable_fix');
		if ($this->settings['retweet_anywhere_fix'])
			add_filter('retweet-anywhere-shorteners', 'tfl_retweet_anywhere_fix', 10, 1);
			
		if ($this->settings['html_shortlink_rel'] || $this->settings['rel_canonical'])
			add_action('wp_head', array(&$this, 'wp_head'));
			
		// Notify the administrator if permalinks are switched off
		$permalink_structure = (isset($_POST['permalink_structure'])) ? $_POST['permalink_structure'] : get_option('permalink_structure');
		if ($permalink_structure == "")
			$this->notices[] = 'You have to <a href="options-permalink.php">change your permalink structure</a> for Twitter Friendly Links to work (don not use default).';

		$this->form_data();
	}
	
	function edit_columns($columns)
	{
		$columns['tfl'] = 'Twitter Link';		
		return $columns;
	}
	
	function custom_columns($column)
	{
		global $post;
		if ($column == 'tfl')
		{
			echo '<a href="' . twitter_link() . '">' . twitter_link() . '</a>';
		}
	}
	
	function form_data()
	{
		if (isset($_POST['twitter-friendly-links-submit']))
		{
			$this->settings['style'] = $_POST['style'];
			$this->settings['format'] = $_POST['format'];
			$this->settings['redirect'] = $_POST['redirect'];
			$this->settings['shortlink_base'] = $_POST['shortlink_base'];
			
			$this->settings['posts_enabled'] = ($_POST['posts_enabled'] == 'checked') ? true : false;
			$this->settings['pages_enabled'] = ($_POST['pages_enabled'] == 'checked') ? true : false;
			$this->settings['attachments_enabled'] = ($_POST['attachments_enabled'] == 'checked') ? true : false;
			
			$this->settings['twitter_tools_fix'] = ($_POST['twitter_tools_fix'] == 'checked') ? true : false;
			$this->settings['askapache_google_404'] = ($_POST['askapache_google_404'] == 'checked') ? true : false;
			$this->settings['tweet_this_fix'] = ($_POST['tweet_this_fix'] == 'checked') ? true : false;
			$this->settings['sociable_fix'] = ($_POST['sociable_fix'] == 'checked') ? true : false;
			$this->settings['retweet_anywhere_fix'] = ($_POST['retweet_anywhere_fix'] == 'checked') ? true : false;
			
			$this->settings['ga_tracking'] = $_POST['ga_tracking'];
			
			$this->settings['html_shortlink_rel'] = ($_POST['html_shortlink_rel'] == 'checked') ? true : false;
			$this->settings['http_shortlink_rel'] = ($_POST['http_shortlink_rel'] == 'checked') ? true : false;
			$this->settings['rel_canonical'] = ($_POST['rel_canonical'] == 'checked') ? true : false;
			
			$this->save_settings();
			
			$this->notices[] = 'Your settings have been saved!';
		}
	}
	
	function save_settings()
	{
		update_option('twitter_friendly_links', $this->settings);
		return true;
	}
	
	function wp_head()
	{
		// Mainly for relations
		if (($this->settings['posts_enabled'] && is_single()) || ($this->settings['pages_enabled'] && is_page()) || ($this->settings['attachments_enabled'] && is_attachment()))
		{
			global $post;
			$post_id = $post->ID;
		
			if ($this->settings["html_shortlink_rel"])
			{
				$short_url = twitter_link($post_id);
				echo "<link rel=\"shortlink\" href=\"$short_url\" />\n";
			}
			if ($this->settings["rel_canonical"])
			{
				$permalink = get_permalink($post_id);
				echo "<link rel=\"canonical\" href=\"$permalink\" />\n";
			}
		}
	}

	function template_redirect($requested_url=null, $do_redirect=true) {
		global $wp;
		global $wp_query;
		
		if (is_404())
		{
			$style = $this->settings["style"];
			$format = $this->settings["format"];
			$redirect = $this->settings["redirect"];

			$ga_tracking = (strlen($this->settings["ga_tracking"]) > 1) ? "?".$this->settings["ga_tracking"] : "";
			
			$request = $wp->request;
			if (ereg("^{$style}([0-9a-z]+)/?$", $request, $regs))
			{
				// Fix for the AskApache Google 404 plugin
				$this->settings["askapache_google_404"] = ($this->settings["askapache_google_404"] == "checked") ? true : false;
				if ($this->settings["askapache_google_404"])
				{
					global $AskApacheGoogle404;
					remove_action("template_redirect", array($AskApacheGoogle404, 'template_redirect'));
				}

				$post_id = $regs[1];
				if ($format == "base32")
					$post_id = tfl_base32($post_id, true);

				if (is_numeric($post_id))
				{
					global $wp_query;
					$posts = new WP_Query("p=$post_id&post_type=any");
					if ($posts->have_posts())
					{
						$posts->the_post();
						$post = $posts->post;
						
						if (!$this->settings["posts_enabled"] && $post->post_type == "post") return;
						if (!$this->settings["pages_enabled"] && $post->post_type == "page") return;
						if (!$this->settings["attachments_enabled"] && $post->post_type == "attachment") return;
						
						status_header(200);
						wp_redirect(get_permalink().$ga_tracking, $redirect);
						exit();
					}
				}
			}
		}
		elseif (is_singular())
		{

			global $post;

			// Link relations
			if ($this->settings["http_shortlink_rel"])
				if (($this->settings["posts_enabled"] && is_single()) || ($this->settings["pages_enabled"] && is_page()) || ($this->settings["attachments_enabled"] && is_attachment()))
					header("Link: <" . twitter_link($post_id) . ">; rel=shortlink");
		}		
	}

	function admin_menu() {
		$twitter_friendly_admin = add_options_page('Twitter Friendly Links', 'Twitter Friendly Links', 8, __FILE__, array(&$this, 'options'));
		add_meta_box("twitter_friendly_id", "Twitter Stuff", array(&$this, "admin_menu_inner_box"), "post", "side");
		if ($this->settings["pages_enabled"]) add_meta_box("twitter_friendly_id", "Twitter Stuff", array(&$this, "admin_menu_inner_box"), "page", "side");
	}

	function options() {
		$style = $this->settings["style"];
		$format = $this->settings["format"];
		$redirect = $this->settings["redirect"];
		$shortlink_base = $this->settings["shortlink_base"];
		$posts_enabled = ($this->settings["posts_enabled"]) ? "checked" : "";
		$pages_enabled = ($this->settings["pages_enabled"]) ? "checked" : "";
		$attachments_enabled = ($this->settings["attachments_enabled"]) ? "checked" : "";
		
		$twitter_tools_fix = ($this->settings["twitter_tools_fix"]) ? "checked" : "";
		$askapache_google_404 = ($this->settings["askapache_google_404"]) ? "checked" : "";
		$tweet_this_fix = ($this->settings["tweet_this_fix"]) ? "checked" : "";
		$sociable_fix = ($this->settings["sociable_fix"]) ? "checked" : "";
		$retweet_anywhere_fix = ($this->settings['retweet_anywhere_fix']) ? 'checked' : '';

		$ga_tracking = $this->settings["ga_tracking"];
		
		$html_shortlink_rel = ($this->settings["html_shortlink_rel"]) ? "checked" : "";
		$http_shortlink_rel = ($this->settings["http_shortlink_rel"]) ? "checked" : "";
		$rel_canonical = ($this->settings["rel_canonical"]) ? "checked" : "";
		
		$selected[$redirect] = " selected=\"selected\"";
		$selected[$format] = " selected=\"selected\"";

		if ($format == "generic") $link_preview = "123";
		elseif ($format = "base32") $link_preview = "7e1";
	?>
		<div class="wrap">
		<h2>Twitter Friendly Links Setup</h2>
		<h3>General Settings</h3>
		<p>Make sure you get this right from the first time because changing this afterward will affect all your previous twitter friendly links and you're most likely to get 404 error messages on your old links. For more information please visit <a href="http://kovshenin.com/wordpress/plugins/twitter-friendly-links/">Twitter Friendly Links</a>.</p>
		<form method="post">
			<input type="hidden" value="1" name="twitter-friendly-links-submit"/>
			<table class="form-table" style="margin-bottom:10px;">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="shortlink_base">Shortlink base URL</label></th>
					<td>
						<input type="text" value="<?php echo $shortlink_base; ?>" id="shortlink_base" name="shortlink_base" />
						<span class="setting-description"><?php echo get_option("home"); ?> by default (read more about <a href="http://kovshenin.com/wordpress/plugins/twitter-friendly-links/#shorter">even shorter links</a>)</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="style">Shortlinks prefix</label></th>
					<td>
						<input type="text"  value="<?php echo $style; ?>" id="style" name="style"/>
						<span class="setting-description"><?php echo $shortlink_base; ?>/<strong>prefix</strong><?php echo $link_preview; ?> (blank by default)</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="format">Format</label></th>
					<td>
						<select name="format" id="format">
							<option value="generic"<?php echo $selected["generic"]; ?>>Generic (numbers only)</option>
							<option value="base32"<?php echo $selected["base32"]; ?>>Alphanumeric (base32 encoded)</option>
						</select>
						<span class="setting-description">Generic by default</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label>Enable shortlinks for</label></th>
					<td>
						<input type="checkbox" value="checked" <?php echo $posts_enabled; ?> id="posts_enabled" name="posts_enabled"/>
						<span class="setting-description">Posts<br /></span>

						<input type="checkbox" value="checked" <?php echo $pages_enabled; ?> id="pages_enabled" name="pages_enabled"/>
						<span class="setting-description">Pages<br /></span>
						
						<input type="checkbox" value="checked" <?php echo $attachments_enabled; ?> id="attachments_enabled" name="attachments_enabled"/>
						<span class="setting-description">Attachments</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="redirect">Redirection type</label></th>
					<td>
						<select name="redirect" id="redirect">
							<option value="302"<?php echo $selected[302]; ?>>302 Found (Temporary redirect)</option>
							<option value="301"<?php echo $selected[301]; ?>>301 Moved Permanently</option>
						</select>
						<span class="setting-description">302 by default</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="style">Tag destination links</label></th>
					<td>
						<input type="text" style="min-width:25em;" value="<?php echo $ga_tracking; ?>" id="ga_tracking" name="ga_tracking" /><br />
						<span class="setting-description">You can tag your destination links for Google Analytics Tracking. For example: <code>utm_source=twitter&amp;utm_medium=shortlink&amp;utm_campaign=shortlinks</code>. You can generate a tagged link using the <a href="https://www.google.com/support/googleanalytics/bin/answer.py?hl=en&answer=55578">Google Analytics URL Builder</a>. Do not include the website address in the input box above. Start from utm_source. This string will be appended to the destination address. Leave blank to disable.</span>
					</td>
				</tr>
			</tbody>
			</table>

		<h3>Link Relations</h3>
		<p>Search engines and URL shorteners. Bunch of thoughts on linking relations, so here are the main options.</p>

			<table class="form-table" style="margin-bottom:10px;">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="html_shortlink_rel">HTML Shortlink relation</label></th>
					<td>
						<input type="checkbox" value="checked" <?php echo $html_shortlink_rel; ?> id="html_shortlink_rel" name="html_shortlink_rel" />
						<span class="setting-description">Adds a link rel=&quot;shortlink&quot; to the head section of your posts and (if enabled) pages. <a href="http://purl.org/net/shortlink">Specification</a>.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="http_shortlink_rel">HTTP Shortlink relation</label></th>
					<td>
						<input type="checkbox" value="checked" <?php echo $http_shortlink_rel; ?> id="http_shortlink_rel" name="http_shortlink_rel" />
						<span class="setting-description">Passes a link rel=&quot;shortlink&quot; along with the HTTP responses of your posts and (if enabled) pages. <a href="http://purl.org/net/shortlink">Specification</a>.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="rel_canonical">Canonical relation</label></th>
					<td>
						<input type="checkbox" value="checked" <?php echo $rel_canonical; ?> id="rel_canonical" name="rel_canonical" />
						<span class="setting-description">Adds a link rel=&quot;canonical&quot; href=&quot;permalink&quot; to your HTML head in posts and (if enabled) pages.</span>
					</td>
				</tr>
			</tbody>
			</table>
			
		<h3>Compatibility</h3>
		<p>If you use any of the plugins listed below and you are experiencing problems with short linking, enable the fixes. If there's no fix for a plugin you're using you may request it on the <a href="http://kovshenin.com/wordpress/plugins/twitter-friendly-links/">Twitter Friendly Links</a> page.</p>
		<p>I would also like to thank <a href="http://twitter.com/eight7teen">Josh Jones</a> for his great implementation of TFL into his awesome <a href="http://sexybookmarks.net">SexyBookmarks</a> plugin. Well done Josh!</p>
			<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="retweet_anywhere_fix">Retweet Anywhere</label></th>
					<td>
						<input type="checkbox" value="checked" <?php echo $retweet_anywhere_fix; ?> id="retweet_anywhere_fix" name="retweet_anywhere_fix"/>
						<span class="setting-description">Fix for the <a href="http://wordpress.org/extend/plugins/retweet-anywhere/">Retweet Anywhere</a> plugin.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="twitter_tools_fix">Twitter Tools</label></th>
					<td>
						<input type="checkbox" value="checked" <?php echo $twitter_tools_fix; ?> id="twitter_tools_fix" name="twitter_tools_fix"/>
						<span class="setting-description">Linking fix for the <a href="http://wordpress.org/extend/plugins/twitter-tools/">Twitter Tools</a> plugin. Described <a href="http://kovshenin.com/archives/compatibility-twitter-tools-twitter-friendly-links/">here</a></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="tweet_this_fix">Tweet-This</label></th>
					<td>
						<input type="checkbox" value="checked" <?php echo $tweet_this_fix; ?> id="tweet_this_fix" name="tweet_this_fix"/>
						<span class="setting-description">Linking fix for the <a href="http://wordpress.org/extend/plugins/tweet-this/">Tweet This</a> plugin.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="askapache_google_404">AskApache Google 404</label></th>
					<td>
						<input type="checkbox" value="checked" <?php echo $askapache_google_404; ?> id="askapache_google_404" name="askapache_google_404"/>
						<span class="setting-description">Fix for the <a href="http://wordpress.org/extend/plugins/askapache-google-404/">AskApache Google 404</a> plugin.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="sociable">Sociable</label></th>
					<td>
						<input type="checkbox" value="checked" <?php echo $sociable_fix; ?> id="sociable_fix" name="sociable_fix"/>
						<span class="setting-description">Fix for the <a href="http://wordpress.org/extend/plugins/sociable/">Sociable</a> plugin.</span>
					</td>
				</tr>
			</tbody>
			</table>

			<p class="submit">
				<input type="submit" value="Save Changes" class="button-primary" name="Submit"/>
			</p>
		</form>
		</div>
	<?php
	}

	function admin_menu_inner_box($post) {
		if ($post->post_status != 'publish') {
			echo '<p>Please publish to get Twitter links.</p>';
			return;
		}
		
		$post_id = $post->ID;
		
		$friendly_link = twitter_link($post_id);
		$title_length = utf8_strlen($post->post_title);
		$link_length = utf8_strlen($friendly_link);
		$overall_length = $title_length + $link_length;
		$excess = 140 - $overall_length;
		
		if ($excess < 0) {
			$excess *= -1;
			$title = mb_substr($post->post_title,0,$title_length-($excess+4),'UTF-8') . '... ';
		}
		else {
			$title = $post->post_title . ' ';
		}
		
		echo "<p><strong>Friendly link:</strong> <a href=\"{$friendly_link}\">{$friendly_link}</a></p>";
		echo "<p><strong>Tweet:</strong> {$title}<a href=\"{$friendly_link}\">{$friendly_link}</a></p>";
		echo "<p style=\"text-align: right;\"><strong><a href=\"http://twitter.com/home/?status=" . urlencode($title) . urlencode($friendly_link) . "\">Tweet this</a> &raquo;</strong></p>";
	}
	
	function admin_notices()
	{
		$this->notices = array_unique($this->notices);
		foreach($this->notices as $key => $value)
		{
			echo '<div id="tfl-info" class="updated fade"><p><strong>Twitter Friendly Links</strong>: ' . $value . '</p></div>';
		}
	}
}

add_action('init', 'TwitterFriendlyLinks'); function TwitterFriendlyLinks() { global $TwitterFriendlyLinks; $TwitterFriendlyLinks = new TwitterFriendlyLinks(); }

/**
 * Returns a Twitter Friendly link using the ID of a post
 *
 * @param int $post_id Optional. The ID of the post you'd like a Twitter Friendly link for. If not supplied, taken from The_Loop
 * @return string Returns the Twitter Friendly link 
 */
function twitter_link($post_id = 0) {
	$options = get_option('twitter_friendly_links');
	$style = $options['style'];
	$home = $options['shortlink_base'];
	
	if ($post_id == 0)
	{
		global $post;
		$post_id = $post->ID;
	}

	if ($options['format'] == 'base32')
		$friendly_link = $home . '/' . $style . tfl_base32($post_id);
	else	
		$friendly_link = $home . '/' . $style . $post_id;
	
	return $friendly_link;
}

/**
 * Returns a Twitter Friendly link using the permalink of a post
 *
 * @param string $permalink The URL of the permalink that should be converted to a Twitter Friendly link
 * @return string Returns the Twitter Friendly link 
 */
function permalink_to_twitter_link($permalink)
{
	$post_id = url_to_postid($permalink);
	return twitter_link($post_id);
}

function tfl_tweet_this_fix($content) {
	global $post;
	$twitter_link = twitter_link();
	$content = preg_replace("/href=\\\"http:\/\/twitter.com\/home\/?\?status=([^\\\"]+)\\\"/", "href=\"http://twitter.com/home/?status=" . urlencode($post->post_title . " " . $twitter_link) . "\"", $content);
	return $content;
}

function tfl_sociable_fix($content) {
	global $post;
	$twitter_link = twitter_link();
	$content = preg_replace("/href=\\\"http:\/\/twitter.com\/home\/?\?status=([^\\\"]+)\\\"/", "href=\"http://twitter.com/home/?status=" . urlencode($post->post_title . " " . $twitter_link) . "\"", $content);
	return $content;
}

function tfl_retweet_anywhere_fix($shorteners)
{
	$shorteners['twitter-friendly-links'] = array(
		'name' => 'Twitter Friendly Links',
		'callback' => 'permalink_to_twitter_link'
	);
	
	return $shorteners;
}

function tfl_base32($str, $reverse = false) {
	if (!$reverse)
	{
		$post_id = intval($str);
		return base_convert($post_id + 10000, 10, 36);
	}
	else
	{
		$post_id = base_convert($str, 36, 10) - 10000;
		return $post_id;
	}
}

if (!function_exists("utf8_strlen")) {
	function utf8_strlen($s) {
	    $c = strlen($s); $l = 0;
	    for ($i = 0; $i < $c; ++$i) if ((ord($s[$i]) & 0xC0) != 0x80) ++$l;
	    return $l;
	}
}