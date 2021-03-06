<?php
/**
 *	MyAlerts Core Plugin File
 *
 *	A simple notification/alert system for MyBB
 *
 *	@author Euan T. <euan@euantor.com>
 *	@version 1.00
 *	@package MyAlerts
 */

if (!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

define('MYALERTS_PLUGIN_PATH', MYBB_ROOT.'inc/plugins/MyAlerts/');

if(!defined("PLUGINLIBRARY"))
{
	define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

function myalerts_info()
{
	return array(
		'name'          =>  'MyAlerts',
		'description'   =>  'A simple notifications/alerts system for MyBB',
		'website'       =>  '',
		'author'        =>  'euantor',
		'authorsite'    =>  'http://euantor.com',
		'version'       =>  '1.00',
		'guid'          =>  '',
		'compatibility' =>  '16*',
		);
}

function myalerts_install()
{
	global $db, $cache;

	$plugin_info = myalerts_info();
	$euantor_plugins = $cache->read('euantor_plugins');
	$euantor_plugins['myalerts'] = array(
		'title'     =>  'MyAlerts',
		'version'   =>  $plugin_info['version'],
		);
	$cache->update('euantor_plugins', $euantor_plugins);

	if (!$db->table_exists('alerts'))
	{
		$db->write_query('CREATE TABLE `'.TABLE_PREFIX.'alerts` (
			`id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`uid` INT(10) NOT NULL,
			`unread` TINYINT(4) NOT NULL DEFAULT \'1\',
			`dateline` BIGINT(30) NOT NULL,
			`type` VARCHAR(25) NOT NULL,
			`tid` INT(10),
			`from` INT(10),
			`content` TEXT
			) ENGINE=MyISAM '.$db->build_create_table_collation().';');
	}

	$db->add_column('users', 'myalerts_settings', 'TEXT NULL');
	$myalertsSettings = array(
		'reputation'	=>	1,
		'pm'			=>	1,
		'buddylist'		=>	1,
		'quoted'		=>	1,
		'thread_reply'	=>	1,
		);
	$db->update_query('users', array('myalerts_settings' => $db->escape_string(json_encode($myalertsSettings))), '1');
}

function myalerts_is_installed()
{
	global $db;
	return $db->table_exists('alerts');
}

function myalerts_uninstall()
{
	global $db;

	if(!file_exists(PLUGINLIBRARY))
	{
		flash_message("The selected plugin could not be uninstalled because <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing.", "error");
		admin_redirect("index.php?module=config-plugins");
	}

	global $PL;
	$PL or require_once PLUGINLIBRARY;

	$db->drop_table('alerts');
	$PL->settings_delete('myalerts', true);
	$PL->templates_delete('myalerts');
	$db->drop_column('users', 'myalerts_settings');
}

function myalerts_activate()
{
	global $mybb, $db, $lang;

	if (!$lang->myalerts)
	{
		$lang->load('myalerts');
	}

	if(!file_exists(PLUGINLIBRARY))
	{
		flash_message($lang->myalerts_pluginlibrary_missing, "error");
		admin_redirect("index.php?module=config-plugins");
	}

	$this_version = myalerts_info();
	$this_version = $this_version['version'];
	require_once MYALERTS_PLUGIN_PATH.'/Alerts.class.php';

	if (Alerts::version != $this_version)
	{
		flash_message($lang->sprintf($lang->myalerts_class_outdated, $this_version, Alerts::version), "error");
		admin_redirect("index.php?module=config-plugins");
	}

	global $PL;
	$PL or require_once PLUGINLIBRARY;

	$PL->settings('myalerts',
		$lang->setting_group_myalerts,
		$lang->setting_group_myalerts_desc,
		array(
			'enabled'	=>	array(
				'title'			=>	$lang->setting_myalerts_enabled,
				'description'	=>	$lang->setting_myalerts_enabled_desc,
				'value'			=>	'1',
				),
			'perpage'   =>  array(
				'title'         =>  $lang->setting_myalerts_perpage,
				'description'   =>  $lang->setting_myalerts_perpage_desc,
				'value'         =>  '10',
				'optionscode'   =>  'text',
				),
			'dropdown_limit'  =>  array(
				'title'         =>  $lang->setting_myalerts_dropdown_limit,
				'description'   =>  $lang->setting_myalerts_dropdown_limit_desc,
				'value'         =>  '5',
				'optionscode'	=>	'text',
				),
			'autorefresh'   =>  array(
				'title'         =>  $lang->setting_myalerts_autorefresh,
				'description'   =>  $lang->setting_myalerts_autorefresh_desc,
				'value'         =>  '0',
				'optionscode'   =>  'text',
				),
			'alert_rep' =>  array(
				'title'         =>  $lang->setting_myalerts_alert_rep,
				'description'   =>  $lang->setting_myalerts_alert_rep_desc,
				'value'         =>  '1',
				),
			'alert_pm'  =>  array(
				'title'         =>  $lang->setting_myalerts_alert_pm,
				'description'   =>  $lang->setting_myalerts_alert_pm_desc,
				'value'         =>  '1',
				),
			'alert_buddylist'  =>  array(
				'title'         =>  $lang->setting_myalerts_alert_buddylist,
				'description'   =>  $lang->setting_myalerts_alert_buddylist_desc,
				'value'         =>  '1',
				),
			'alert_quoted'  =>  array(
				'title'         =>  $lang->setting_myalerts_alert_quoted,
				'description'   =>  $lang->setting_myalerts_alert_quoted_desc,
				'value'         =>  '1',
				),
			'alert_post_threadauthor'  =>  array(
				'title'         =>  $lang->setting_myalerts_alert_post_threadauthor,
				'description'   =>  $lang->setting_myalerts_alert_post_threadauthor_desc,
				'value'         =>  '1',
				),
			)
	);

	$PL->templates('myalerts',
		'MyAlerts',
		array(
			'page'      =>  '<html>
	<head>
		<title>{$lang->myalerts_page_title} - {$mybb->settings[\'bbname\']}</title>
		<script type="text/javascript">
			<!--
				var myalerts_autorefresh = {$mybb->settings[\'myalerts_autorefresh\']};
			// -->
		</script>
		{$headerinclude}
	</head>
	<body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				{$usercpnav}
				<td valign="top">
					<div class="float_right">
						{$multipage}
					</div>
					<div class="clear"></div>
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
						<thead>
							<tr>
								<th class="thead" colspan="2">
									<strong>{$lang->myalerts_page_title}</strong>
									<div class="float_right">
										<a id="getUnreadAlerts" href="{$mybb->settings[\'bburl\']}/usercp.php?action=alerts">{$lang->myalerts_page_getnew}</a>
									</div>
								 </th>
							</tr>
						</thead>
						<tbody id="latestAlertsListing">
							{$alertsListing}
						</tbody>
					</table>
					<div class="float_right">
						{$multipage}
					</div>
					<br class="clear" />
				</td>
			</tr>
		</table>
		{$footer}
	</body>
	</html>',
			'settings_page'      =>  '<html>
	<head>
		<title>{$lang->myalerts_settings_page_title} - {$mybb->settings[\'bbname\']}</title>
		{$headerinclude}
	</head>
	<body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				{$usercpnav}
				<td valign="top">
					<form action="usercp.php?action=alert_settings" method="post">
						<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
						<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
							<thead>
								<tr>
									<th class="thead" colspan="1">
										<strong>{$lang->myalerts_settings_page_title}</strong>
									 </th>
								</tr>
							</thead>
							<tbody>
								{$alertSettings}
							</tbody>
						</table>
						<div style="text-align:center;">
							<input type="submit" value="{$lang->myalerts_settings_save}" />
						</div>
					</form>
				</td>
			</tr>
		</table>
		{$footer}
	</body>
	</html>',
			'setting_row'	=>	'<tr>
	<td class="{$altbg}">
		<label for="input_{$key}"><input type="checkbox" name="{$key}" id="input_{$key}"{$checked} /> &nbsp; {$langline}</label>
	</td>
</tr>',
			'headericon'	=>	'<span class="myalerts_popup_wrapper">
	&mdash; <a href="{$mybb->settings[\'bburl\']}/usercp.php?action=alerts" class="unreadAlerts myalerts_popup_hook" id="unreadAlerts_menu">Alerts ({$mybb->user[\'unreadAlerts\']})</a>
	<div id="unreadAlerts_menu_popup" class="myalerts_popup" style="display:none;">
		<div class="popupTitle">{$lang->myalerts_page_title}</div>
		<ol>
		{$alerts}
		</ol>
		<div class="popupFooter"><a href="usercp.php?action=alerts">{$lang->myalerts_usercp_nav_alerts}</a></div>
	</div>
</span>',
			'alert_row' =>  '<tr class="alert_row {$alertRowType}Row{$unreadAlert}" id="alert_row_{$alert[\'id\']}">
	<td class="{$altbg}" width="50">
		<a class="avatar" href="{$alert[\'userLink\']}"><img src="{$alert[\'avatar\']}" alt="{$alert[\'username\']}\'s avatar" width="48" height="48" /></a>
	</td>
	<td class="{$altbg}">
		{$alert[\'message\']}
	</td>
</tr>',
			'alert_row_no_alerts' =>  '<tr class="alert_row noAlertsRow">
	<td class="{$altbg}" colspan="2" style="text-align:center;">
		{$lang->myalerts_no_alerts}
	</td>
</tr>',
			'alert_row_popup' =>  '<li class="alert_row {$alertRowType}Row{$unreadAlert}">
	<a class="avatar" href="{$alert[\'userLink\']}"><img src="{$alert[\'avatar\']}" alt="{$alert[\'username\']}\'s avatar" width="24" height="24" /></a>
	<div class="alertContent">
		{$alert[\'message\']}
	</div>
</li>',
			'alert_row_popup_no_alerts' =>  '<li class="alert_row noAlertsRow">
	{$lang->myalerts_no_alerts}
</li>',
			'usercp_nav' => '<tr>
	<td class="tcat">
		<div class="expcolimage">
			<img src="{$theme[\'imgdir\']}/collapse{$collapsedimg[\'usercpalerts\']}.gif" id="usercpalerts_img" class="expander" alt="[-]" title="[-]" />
		</div>
		<div>
			<span class="smalltext">
				<strong>{$lang->myalerts_usercp_nav}</strong>
			</span>
		</div>
	</td>
</tr>
<tbody style="{$collapsed[\'usercpalerts_e\']}" id="usercpalerts_e">
	<tr>
		<td class="trow1 smalltext">
			<a href="usercp.php?action=alerts" class="usercp_nav_item usercp_nav_myalerts">{$lang->myalerts_usercp_nav_alerts}</a>
		</td>
	</tr>
	<tr>
		<td class="trow1 smalltext">
			<a href="usercp.php?action=alert_settings" class="usercp_nav_item usercp_nav_options">{$lang->myalerts_usercp_nav_settings}</a>
		</td>
	</tr>
</tbody>',
		)
	);

	//  Add our stylesheet to make our alerts notice look nicer. Making use of CSS3 gradients here because I'm lazy. based on the default theme's colours

	$stylesheet = '.unreadAlerts {
	display: inline-block;
}

.usercp_nav_myalerts {
	background:url(\'images/usercp/bell.png\') no-repeat left center;
}

.myalerts_popup ol {
	list-style:none;
	margin:0;
	padding:0;
}
	.myalerts_popup li {
		min-height:24px;
		padding:2px 4px;
		border-bottom:1px solid #D4D4D4;
	}
	.myalerts_popup li .avatar {
		float:left;
		height:24px;
		width:24px;
	}
	.myalerts_popup li .alertContent {
		margin-left:30px;
		font-size:11px;
	}
	.unreadAlert {
		font-weight:bold;
		background:#FFFBD9;
	}

.myalerts_popup_wrapper{
	position:relative;
}

.myalerts_popup_wrapper .myalerts_popup {
	background:#fff;
	width:350px;
	max-width:350px;
	box-shadow:0 0 10px rgba(0,0,0,0.2);
	position:absolute;
	left:0;
}
	.myalerts_popup .popupTitle {
		font-weight:bold;
		margin:0 2px;
		padding:2px;
		border-bottom:1px solid #D4D4D4;
	}
	.myalerts_popup .popupFooter {
		padding:4px;
		background:#EFEFEF;
		box-shadow:inset 0 1px 0 0 rgba(255,255,255,0.2);
	}';

	$insertArray = array(
		'name'          => 'Alerts.css',
		'tid'           => '1',
		'stylesheet'    => $db->escape_string($stylesheet),
		'cachefile'     => 'Alerts.css',
		'lastmodified'  => TIME_NOW
	);

	require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';

	$sid = $db->insert_query('themestylesheets', $insertArray);

	if(!cache_stylesheet($theme['tid'], 'Alerts.css', $stylesheet))
	{
		$db->update_query('themestylesheets', array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
	}

	$query = $db->simple_select('themes', 'tid');
	while($theme = $db->fetch_array($query))
	{
		update_theme_stylesheet_list($theme['tid']);
	}

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	// Add our JS. We need jQuery and myalerts.js. For jQuery, we check it hasn't already been loaded then load 1.7.2 from google's CDN
	find_replace_templatesets('headerinclude', "#".preg_quote('{$stylesheets}')."#i", '<script type="text/javascript">
if (typeof jQuery == \'undefined\')
{
	document.write(unescape("%3Cscript src=\'http://code.jquery.com/jquery-1.7.2.min.js\' type=\'text/javascript\'%3E%3C/script%3E"));
}
</script>
<script type="text/javascript">
	var unreadAlerts = {$mybb->user[\'unreadAlerts\']}
</script>
<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/myalerts.js"></script>'."\n".'{$stylesheets}');
	find_replace_templatesets('header_welcomeblock_member', "#".preg_quote('{$admincplink}')."#i", '{$admincplink}'."\n".'<myalerts_headericon>'."\n");

	// Helpdocs
	$helpsection = $db->insert_query('helpsections', array(
		'name'              =>  $lang->myalerts_helpsection_name,
		'description'       =>  $lang->myalerts_helpsection_desc,
		'usetranslation'    =>  1,
		'enabled'           =>  1,
		'disporder'         =>  3,
		));

	$helpDocuments = array(
		0   =>  array(
			'sid'               =>  (int) $helpsection,
			'name'              =>  $db->escape_string($lang->myalerts_help_info),
			'description'       =>  $db->escape_string($lang->myalerts_help_info_desc),
			'document'          =>  $db->escape_string($lang->myalerts_help_info_document),
			'usetranslation'    =>  1,
			'enabled'           =>  1,
			'disporder'         =>  1,
			),
		1   =>  array(
			'sid'               =>  (int) $helpsection,
			'name'              =>  $db->escape_string($lang->myalerts_help_alert_types),
			'description'       =>  $db->escape_string($lang->myalerts_help_alert_types_desc),
			'document'          =>  $db->escape_string($lang->myalerts_help_alert_types_document),
			'usetranslation'    =>  1,
			'enabled'           =>  1,
			'disporder'         =>  2,
			),
		);

	foreach ($helpDocuments as $document)
	{
		$db->insert_query('helpdocs', $document);
	}
}

function myalerts_deactivate()
{
	global $db, $lang;

	require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
	$db->delete_query('themestylesheets', 'name = \'Alerts.css\'');
	$query = $db->simple_select('themes', 'tid');
	while($theme = $db->fetch_array($query))
	{
		update_theme_stylesheet_list($theme['tid']);
	}

	if (!$lang->myalerts)
	{
		$lang->load('myalerts');
	}

	$sid = (int) $db->fetch_field($db->simple_select('helpsections', 'sid', 'name = \''.$db->escape_string($lang->myalerts_helpsection_name).'\''), 'sid');
	$db->delete_query('helpsections', 'sid = '.$sid);
	$db->delete_query('helpdocs', 'sid = '.$sid);

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('headerinclude', "#".preg_quote('<script type="text/javascript">
if (typeof jQuery == \'undefined\')
{
	document.write(unescape("%3Cscript src=\'http://code.jquery.com/jquery-1.7.2.min.js\' type=\'text/javascript\'%3E%3C/script%3E"));
}
</script>
<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/myalerts.js"></script>'."\n")."#i", '');
	find_replace_templatesets('header_welcomeblock_member', "#".preg_quote("\n".'<myalerts_headericon>'."\n")."#i", '');
}

global $settings, $mybb;

if ($settings['myalerts_enabled'])
{
	$plugins->add_hook('pre_output_page', 'myalerts_pre_output_page');
}
function myalerts_pre_output_page(&$contents)
{
	global $templates, $mybb, $lang, $myalerts_headericon, $Alerts, $plugins;

	if ($mybb->user['uid'])
	{
		if (!$lang->myalerts)
		{
			$lang->load('myalerts');
		}

		try
		{
			$userAlerts = $Alerts->getAlerts(0, $mybb->settings['myalerts_dropdown_limit']);
		}
		catch (Exception $e)
		{
		}

		$alerts = '';

		if (is_array($userAlerts) AND count($userAlerts) > 0)
		{
			foreach ($userAlerts as $alert)
			{
				$alert['userLink'] = get_profile_link($alert['uid']);
				$alert['user'] = build_profile_link($alert['username'], $alert['uid']);
				$alert['dateline'] = my_date($mybb->settings['dateformat'], $alert['dateline'])." ".my_date($mybb->settings['timeformat'], $alert['dateline']);

				if ($alert['unread'] == 1)
				{
					$unreadAlert = ' unreadAlert';
				}
				else
				{
					$unreadAlert = '';
				}

				$plugins->run_hooks('myalerts_popup_output_start');

				if ($alert['type'] == 'rep' AND $mybb->settings['myalerts_alert_rep'])
				{
					$alert['message'] = $lang->sprintf($lang->myalerts_rep, $alert['user'], $alert['dateline']);
					$alertRowType = 'reputationAlert';
				}
				elseif ($alert['type'] == 'pm' AND $mybb->settings['myalerts_alert_pm'])
				{
					$alert['message'] = $lang->sprintf($lang->myalerts_pm, $alert['user'], "<a href=\"{$mybb->settings['bburl']}/private.php?action=read&amp;pmid=".(int) $alert['content']['pm_id']."\">".htmlspecialchars_uni($alert['content']['pm_title'])."</a>", $alert['dateline']);
					$alertRowType = 'pmAlert';
				}
				elseif ($alert['type'] == 'buddylist' AND $mybb->settings['myalerts_alert_buddylist'])
				{
					$alert['message'] = $lang->sprintf($lang->myalerts_buddylist, $alert['user'], $alert['dateline']);
					$alertRowType = 'buddylistAlert';
				}
				elseif ($alert['type'] == 'quoted' AND $mybb->settings['myalerts_alert_quoted'])
				{
					$alert['postLink'] = $mybb->settings['bburl'].'/'.get_post_link($alert['content']['pid'], $alert['content']['tid']).'#pid'.$alert['content']['pid'];
					$alert['message'] = $lang->sprintf($lang->myalerts_quoted, $alert['user'], $alert['postLink'], $alert['dateline']);
					$alertRowType = 'quotedAlert';
				}
				elseif ($alert['type'] == 'post_threadauthor' AND $mybb->settings['myalerts_alert_post_threadauthor'])
				{
					$alert['threadLink'] = $mybb->settings['bburl'].'/'.get_thread_link($alert['content']['tid'], 0, 'newpost');
					$alert['message'] = $lang->sprintf($lang->myalerts_post_threadauthor, $alert['user'], $alert['threadLink'], htmlspecialchars_uni($alert['content']['t_subject']), $alert['dateline']);
					$alertRowType = 'postAlert';
				}

				$plugins->run_hooks('myalerts_popup_output_end');

				eval("\$alerts .= \"".$templates->get('myalerts_alert_row_popup')."\";");

				$readAlerts[] = $alert['id'];
			}
		}
		else
		{
			eval("\$alerts = \"".$templates->get('myalerts_alert_row_no_alerts')."\";");
		}

		eval("\$myalerts_headericon = \"".$templates->get('myalerts_headericon')."\";");

		$contents = str_replace('<myalerts_headericon>', $myalerts_headericon, $contents);

		return $contents;
	}
}

if ($settings['myalerts_enabled'])
{
	$plugins->add_hook('global_start', 'myalerts_global');
}
function myalerts_global()
{
	global $mybb, $templatelist;

	if (isset($templatelist))
	{
		$templatelist .= ',';
	}

	$templatelist .= 'myalerts_headericon';

	if (THIS_SCRIPT == 'usercp.php')
	{
		$templatelist .= ',myalerts_usercp_nav';
	}

	if (THIS_SCRIPT == 'usercp.php' AND $mybb->input['action'] == 'alerts')
	{
		$templatelist .= ',myalerts_page,myalerts_alert_row,multipage_page_current,multipage_page,multipage_nextpage,multipage';
	}

	if ($mybb->user['uid'])
	{
		global $Alerts, $db, $lang;
		require_once MYALERTS_PLUGIN_PATH.'Alerts.class.php';
		try
		{
			$Alerts = new Alerts($mybb, $db);
		}
		catch (Exception $e)
		{
			die($e->getMessage());
		}

		if (!$lang->myalerts)
		{
			$lang->load('myalerts');
		}

		$mybb->user['unreadAlerts'] = $Alerts->getNumUnreadAlerts();
	}
}

if ($settings['myalerts_enabled'])
{
	$plugins->add_hook('build_friendly_wol_location_end', 'myalerts_online_location');
}
function myalerts_online_location(&$plugin_array)
{
	global $mybb, $lang;

	if (!$lang->myalerts)
	{
		$lang->load('myalerts');
	}

	if ($plugin_array['user_activity']['activity'] == 'usercp' AND my_strpos($plugin_array['user_activity']['location'], 'alerts'))
	{
		$plugin_array['location_name'] = $lang->myalerts_online_location_listing;
	}
}

if ($settings['myalerts_enabled'])
{
	$plugins->add_hook('misc_help_helpdoc_start', 'myalerts_helpdoc');
}
function myalerts_helpdoc()
{
	global $helpdoc, $lang, $mybb;

	if (!$lang->myalerts)
	{
		$lang->load('myalerts');
	}

	if ($helpdoc['name'] == $lang->myalerts_help_alert_types)
	{
		if ($mybb->settings['myalerts_alert_rep'])
		{
			$helpdoc['document'] .= $lang->myalerts_help_alert_types_rep;
		}

		if ($mybb->settings['myalerts_alert_pm'])
		{
			$helpdoc['document'] .= $lang->myalerts_help_alert_types_pm;
		}

		if ($mybb->settings['myalerts_alert_buddylist'])
		{
			$helpdoc['document'] .= $lang->myalerts_help_alert_types_buddylist;
		}

		if ($mybb->settings['myalerts_alert_quoted'])
		{
			$helpdoc['document'] .= $lang->myalerts_help_alert_types_quoted;
		}

		if ($mybb->settings['myalerts_alert_post_threadauthor'])
		{
			$helpdoc['document'] .= $lang->myalerts_help_alert_types_post_threadauthor;
		}
	}
}

if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_rep'])
{
	$plugins->add_hook('reputation_do_add_process', 'myalerts_addAlert_rep');
}
function myalerts_addAlert_rep()
{
	global $mybb, $Alerts, $reputation;

	$Alerts->addAlert($reputation['uid'], 'rep', 0, $mybb->user['uid'], array());
}

if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_pm'])
{
	$plugins->add_hook('private_do_send_end', 'myalerts_addAlert_pm');
}
function myalerts_addAlert_pm()
{
	global $mybb, $Alerts, $db, $pm, $pmhandler;

	$pmUsers = array_map("trim", $pm['to']);
	$pmUsers = array_unique($pmUsers);

	$users = array();
	$userArray = array();

	foreach ($pmUsers as $user)
	{
		$users[] = $db->escape_string($user);
	}

	if (count($users) > 0)
	{
		$query = $db->simple_select('users', 'uid', "LOWER(username) IN ('".my_strtolower(implode("','", $users))."')");
	}

	$users = array();

	while ($user = $db->fetch_array($query))
	{
		$users[] = $user['uid'];
	}

	$Alerts->addMassAlert($users, 'pm', 0, $mybb->user['uid'], array(
		'pm_title'  =>  $pm['subject'],
		'pm_id'     =>  $pmhandler->pmid,
		)
	);
}

if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_buddylist'])
{
	$plugins->add_hook('usercp_do_editlists_end', 'myalerts_alert_buddylist');
}
function myalerts_alert_buddylist()
{
	global $mybb;

	if ($mybb->input['manage'] != 'ignore' && !isset($mybb->input['delete']))
	{
		global $Alerts, $db;

		$addUsers = explode(",", $mybb->input['add_username']);
		$addUsers = array_map("trim", $addUsers);
		$addUsers = array_unique($addUsers);

		$users = array();
		$userArray = array();

		foreach ($addUsers as $user)
		{
			$users[] = $db->escape_string($user);
		}

		if (count($users) > 0)
		{
			$query = $db->simple_select('users', 'uid', "LOWER(username) IN ('".my_strtolower(implode("','", $users))."')");
		}

		$user = array();

		while($user = $db->fetch_array($query))
		{
			$userArray[] = $user['uid'];
		}

		$Alerts->addMassAlert($userArray, 'buddylist', 0, $mybb->user['uid'], array());
	}
}

if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_quoted'])
{
	$plugins->add_hook('newreply_do_newreply_end', 'myalerts_alert_quoted');
}
function myalerts_alert_quoted()
{
	global $mybb, $Alerts, $db, $pid, $post;

	$message = $post['message'];

	$pattern = "#\[quote=([\"']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#esi";

	preg_match_all($pattern, $message, $match);

	$matches = array_merge($match[2], $match[3]);

	foreach($matches as $key => $value)
	{
		if (empty($value))
		{
			unset($matches[$key]);
		}
	}

	$users = array_values($matches);

	if (!empty($users))
	{
		foreach ($users as $value)
		{
			$queryArray[] = $db->escape_string($value);
		}

		$uids = $db->write_query('SELECT `uid` FROM `'.TABLE_PREFIX.'users` WHERE username IN (\''.my_strtolower(implode("','", $queryArray)).'\') AND uid != '.$mybb->user['uid']);

		$userList = array();

		while ($uid = $db->fetch_array($uids))
		{
			$userList[] = (int) $uid['uid'];
		}

		if (!empty($userList) && is_array($userList))
		{
			$Alerts->addMassAlert($userList, 'quoted', 0, $mybb->user['uid'], array(
				'tid'       =>  $post['tid'],
				'pid'       =>  $pid,
				'subject'   =>  $post['subject'],
				));
		}
	}
}

if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_post_threadauthor'])
{
	$plugins->add_hook('datahandler_post_insert_post', 'myalerts_alert_post_threadauthor');
}
function myalerts_alert_post_threadauthor(&$post)
{
	global $mybb, $Alerts, $db;

	if (!$post->data['savedraft'])
	{
		if ($post->post_insert_data['tid'] == 0)
		{
			$query = $db->simple_select('threads', 'uid,subject', 'tid = '.$post->data['tid'], array('limit' => '1'));
			$thread = $db->fetch_array($query);
		}
		else
		{
			$query = $db->simple_select('threads', 'uid,subject', 'tid = '.$post->post_insert_data['tid'], array('limit' => '1'));
			$thread = $db->fetch_array($query);
		}

		if ($thread['uid'] != $mybb->user['uid'])
		{
			//check if alerted for this thread already
			$query = $db->simple_select('alerts', 'id', 'tid = '.(int) $post->post_insert_data['tid'].' AND unread = 1');

			if ($db->num_rows($query) < 1)
			{
				$Alerts->addAlert($thread['uid'], 'post_threadauthor', (int) $post->post_insert_data['tid'], $mybb->user['uid'], array(
					'tid'       =>  $post->post_insert_data['tid'],
					't_subject' =>  $thread['subject'],
					));
			}
		}
	}
}

if ($settings['myalerts_enabled'])
{
	$plugins->add_hook('usercp_menu', 'myalerts_usercp_menu', 20);
}
function myalerts_usercp_menu()
{
	global $mybb, $templates, $theme, $usercpmenu, $lang, $collapsed, $collapsedimg;

	if (!$lang->myalerts)
	{
		$lang->load('myalerts');
	}

	if ($mybb->user['unreadAlerts'] > 0)
	{
		$lang->myalerts_usercp_nav_alerts = '<strong>'.$lang->myalerts_usercp_nav_alerts.' ('.$mybb->user['unreadAlerts'].')</strong>';
	}

	eval("\$usercpmenu .= \"".$templates->get('myalerts_usercp_nav')."\";");
}

if ($settings['myalerts_enabled'])
{
	$plugins->add_hook('usercp_start', 'myalerts_page');
}
function myalerts_page()
{
	global $mybb;

	if ($mybb->input['action'] == 'alerts')
	{
		global $Alerts, $db, $lang, $theme, $templates, $headerinclude, $header, $footer, $plugins, $usercpnav;

		if (!$lang->myalerts)
		{
			$lang->load('myalerts');
		}

		add_breadcrumb($lang->nav_usercp, 'usercp.php');
		add_breadcrumb($lang->myalerts_page_title, 'usercp.php?action=alerts');

		$numAlerts = $Alerts->getNumAlerts();
		$page = (int) $mybb->input['page'];
		$pages = ceil($numAlerts / $mybb->settings['myalerts_perpage']);

		if ($page > $pages OR $page <= 0)
		{
			$page = 1;
		}

		if ($page)
		{
			$start = ($page - 1) * $mybb->settings['myalerts_perpage'];
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		$multipage = multipage($numAlerts, $mybb->settings['myalerts_perpage'], $page, "usercp.php?action=alerts");

		try
		{
			$alertsList = $Alerts->getAlerts($start);
		}
		catch (Exception $e)
		{
			die($e->getMessage());
		}

		$readAlerts = array();

		if ($numAlerts > 0)
		{
			foreach ($alertsList as $alert)
			{
				$altbg = alt_trow();
				$alert['userLink'] = get_profile_link($alert['uid']);
				$alert['user'] = build_profile_link($alert['username'], $alert['uid']);
				$alert['dateline'] = my_date($mybb->settings['dateformat'], $alert['dateline'])." ".my_date($mybb->settings['timeformat'], $alert['dateline']);

				if ($alert['unread'] == 1)
				{
					$unreadAlert = ' unreadAlert';
				}
				else
				{
					$unreadAlert = '';
				}

				$plugins->run_hooks('myalerts_page_output_start');

				if ($alert['type'] == 'rep' AND $mybb->settings['myalerts_alert_rep'])
				{
					$alert['message'] = $lang->sprintf($lang->myalerts_rep, $alert['user'], $alert['dateline']);
					$alertRowType = 'reputationAlert';
				}
				elseif ($alert['type'] == 'pm' AND $mybb->settings['myalerts_alert_pm'])
				{
					$alert['message'] = $lang->sprintf($lang->myalerts_pm, $alert['user'], "<a href=\"{$mybb->settings['bburl']}/private.php?action=read&amp;pmid=".(int) $alert['content']['pm_id']."\">".htmlspecialchars_uni($alert['content']['pm_title'])."</a>", $alert['dateline']);
					$alertRowType = 'pmAlert';
				}
				elseif ($alert['type'] == 'buddylist' AND $mybb->settings['myalerts_alert_buddylist'])
				{
					$alert['message'] = $lang->sprintf($lang->myalerts_buddylist, $alert['user'], $alert['dateline']);
					$alertRowType = 'buddylistAlert';
				}
				elseif ($alert['type'] == 'quoted' AND $mybb->settings['myalerts_alert_quoted'])
				{
					$alert['postLink'] = $mybb->settings['bburl'].'/'.get_post_link($alert['content']['pid'], $alert['content']['tid']).'#pid'.$alert['content']['pid'];
					$alert['message'] = $lang->sprintf($lang->myalerts_quoted, $alert['user'], $alert['postLink'], $alert['dateline']);
					$alertRowType = 'quotedAlert';
				}
				elseif ($alert['type'] == 'post_threadauthor' AND $mybb->settings['myalerts_alert_post_threadauthor'])
				{
					$alert['threadLink'] = $mybb->settings['bburl'].'/'.get_thread_link($alert['content']['tid'], 0, 'newpost');
					$alert['message'] = $lang->sprintf($lang->myalerts_post_threadauthor, $alert['user'], $alert['threadLink'], htmlspecialchars_uni($alert['content']['t_subject']), $alert['dateline']);
					$alertRowType = 'postAlert';
				}

				$plugins->run_hooks('myalerts_page_output_end');

				eval("\$alertsListing .= \"".$templates->get('myalerts_alert_row')."\";");

				$readAlerts[] = $alert['id'];
			}
		}
		else
		{
			eval("\$alertsListing = \"".$templates->get('myalerts_alert_row_no_alerts')."\";");
		}

		$Alerts->markRead($readAlerts);

		eval("\$content = \"".$templates->get('myalerts_page')."\";");
		output_page($content);
	}

	if ($mybb->input['action'] == 'alert_settings')
	{
		global $db, $lang, $theme, $templates, $headerinclude, $header, $footer, $plugins, $usercpnav;

		if (!$lang->myalerts)
		{
			$lang->load('myalerts');
		}

		if ($mybb->request_method == 'post')
		{
			verify_post_check($mybb->input['my_post_key']);

			$temp_settings = $mybb->input;
			$allowed_settings = array(
				'reputation',
				'pm',
				'buddylist',
				'quoted',
				'thread_reply'
				);
			$plugins->run_hooks('myalerts_allowed_settings');

			$settings = array_intersect_key($temp_settings, array_flip($allowed_settings));

			//	Seeing as unchecked checkboxes just aren't sent, we need an array of all the possible settings, defaulted to 0 (or off) to merge
			$possible_settings = array(
				'reputation'	=>	0,
				'pm'			=>	0,
				'buddylist'		=>	0,
				'quoted'		=>	0,
				'thread_reply'	=>	0,
				);
			$plugins->run_hooks('myalerts_possible_settings');

			$settings = array_merge($possible_settings, $settings);

			$settings = json_encode($settings);

			if ($db->update_query('users', array('myalerts_settings' => $db->escape_string($settings)), 'uid = '.(int) $mybb->user['uid']))
			{
				redirect('usercp.php?action=alert_settings', $lang->myalerts_settings_updated, $lang->myalerts_settings_updated_title);
			}
		}
		else
		{
			$settings = $db->fetch_field($db->simple_select('users', 'myalerts_settings', 'uid = '.(int) $mybb->user['uid'], array('limit' => 1)), 'myalerts_settings');
			$settings = json_decode($settings);

			foreach ($settings as $key => $value)
			{
				$altbg = alt_trow();
				//	variable variables. What fun! http://php.net/manual/en/language.variables.variable.php
				$tempkey = 'myalerts_setting_'.$key;
				$langline = $lang->$tempkey;

				$checked = '';
				if ($value)
				{
					$checked = ' checked="checked"';
				}

				eval("\$alertSettings .= \"".$templates->get('myalerts_setting_row')."\";");
			}

			eval("\$content = \"".$templates->get('myalerts_settings_page')."\";");
			output_page($content);
		}
	}
}

if ($settings['myalerts_enabled'])
{
	$plugins->add_hook('xmlhttp', 'myalerts_xmlhttp');
}
function myalerts_xmlhttp()
{
	global $mybb, $db, $lang, $templates, $plugins;

	require_once MYALERTS_PLUGIN_PATH.'Alerts.class.php';
	try
	{
		$Alerts = new Alerts($mybb, $db);
	}
	catch (Exception $e)
	{
		die($e->getMessage());
	}

	if (!$lang->myalerts)
	{
		$lang->load('myalerts');
	}

	if ($mybb->input['action'] == 'getNewAlerts')
	{
		try
		{
			$newAlerts = $Alerts->getUnreadAlerts();
		}
		catch (Exception $e)
		{
			die($e->getMessage());
		}

		if (!empty($newAlerts) AND is_array($newAlerts))
		{
			$alertsListing = '';
			$markRead = array();

			foreach ($newAlerts as $alert)
			{
				$altbg = alt_trow();
				$alert['userLink'] = get_profile_link($alert['uid']);
				$alert['user'] = build_profile_link($alert['username'], $alert['uid']);
				$alert['dateline'] = my_date($mybb->settings['dateformat'], $alert['dateline'])." ".my_date($mybb->settings['timeformat'], $alert['dateline']);

				$plugins->run_hooks('myalerts_xmlhttp_output_start');

				if ($alert['type'] == 'rep' AND $mybb->settings['myalerts_alert_rep'])
				{
					$alert['message'] = $lang->sprintf($lang->myalerts_rep, $alert['user'], $alert['dateline']);
				}
				elseif ($alert['type'] == 'pm' AND $mybb->settings['myalerts_alert_pm'])
				{
					$alert['message'] = $lang->sprintf($lang->myalerts_pm, $alert['user'], "<a href=\"{$mybb->settings['bburl']}/private.php?action=read&amp;pmid=".(int) $alert['content']['pm_id']."\">".htmlspecialchars_uni($alert['content']['pm_title'])."</a>", $alert['dateline']);
				}
				elseif ($alert['type'] == 'buddylist' AND $mybb->settings['myalerts_alert_buddylist'])
				{
					$alert['message'] = $lang->sprintf($lang->myalerts_buddylist, $alert['user'], $alert['dateline']);
				}
				elseif ($alert['type'] == 'quoted' AND $mybb->settings['myalerts_alert_quoted'])
				{
					$alert['postLink'] = $mybb->settings['bburl'].'/'.get_post_link($alert['content']['pid'], $alert['content']['tid']).'#pid'.$alert['content']['pid'];
					$alert['message'] = $lang->sprintf($lang->myalerts_quoted, $alert['user'], $alert['postLink'], $alert['dateline']);
				}
				elseif ($alert['type'] == 'post_threadauthor' AND $mybb->settings['myalerts_alert_post_threadauthor'])
				{
					$alert['threadLink'] = $mybb->settings['bburl'].'/'.get_thread_link($alert['content']['tid'], 0, 'newpost');
					$alert['message'] = $lang->sprintf($lang->myalerts_post_threadauthor, $alert['user'], $alert['threadLink'], htmlspecialchars_uni($alert['content']['t_subject']), $alert['dateline']);
				}

				$plugins->run_hooks('myalerts_xmlhttp_output_end');

				$alertinfo = $alert['message'];

				if (isset($mybb->input['from']) AND $mybb->input['from'] == 'header')
				{
					eval("\$alertsListing .= \"".$templates->get('myalerts_alert_row_popup')."\";");
				}
				else
				{
					eval("\$alertsListing .= \"".$templates->get('myalerts_alert_row')."\";");
				}

				$markRead[] = $alert['id'];
			}

			$Alerts->markRead($markRead);
		}
		else
		{
			if ($mybb->input['from'] == 'header')
			{
				$alertinfo = $lang->myalerts_no_new_alerts;

				eval("\$alertsListing = \"".$templates->get('myalerts_alert_row_popup')."\";");
			}
		}

		echo $alertsListing;
	}

	if ($mybb->input['action'] == 'getNumUnreadAlerts')
	{
		echo $Alerts->getNumUnreadAlerts();
	}
}
?>
