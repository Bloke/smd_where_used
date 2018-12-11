<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'smd_where_used';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.30';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'http://stefdawson.com/';
$plugin['description'] = 'Find which forms/pages/articles/plugins/text have been used where in your design';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '4';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
// -------------------------------------------------------------
if (@txpinterface == 'admin') {
	add_privs('smd_wu','1,2');

	// Extensions tab
	register_tab('extensions', 'smd_wu', smd_wu_gTxt('smd_wu'));
	register_callback('smd_wu', 'smd_wu');
}

// -------------------------------------------------------------
function smd_wu($event, $step) {
	if(!$step or !in_array($step, array(
			'smd_wu_search',
			'smd_wu_prefsave',
		))) {
		smd_wu_showform('');
	} else $step();
}

// -------------------------------------------------------------
function smd_wu_showform($message, $term='', $sel=array(), $incl=0, $whole=0, $mode=0, $plugtype=0, $case=0) {
	pagetop(smd_wu_gTxt('smd_wu'),$message);
	$btnSearch = fInput('submit', 'smd_wu_search', gTxt('search'), 'smallerbox').sInput('smd_wu_search').hInput('plugtype', (($plugtype==1) ? 0 : 1));
	$btnStyle = ' style="border:0;height:25px"';
	$place = array('sections','pages','forms','stylesheets','articles');
	$sel = empty($sel) ? unserialize(get_pref('smd_wu_look_in')) : $sel;
	$sel = empty($sel) ? $place : $sel;

	echo '<form method="post" name="smd_wu_form">';
	echo startTable('list');
	echo tr(fLabelCell(smd_wu_gTxt('search_lbl')) . tdcs(fInput('text', 'search_for', stripslashes($term), '', '', '', 30, '', 'smd_search_for'), 5) . tda($btnSearch, $btnStyle));
	echo tr(fLabelCell(smd_wu_gTxt('filter')).tdcs(radio('meth',0,(($incl==0)?1:0)).smd_wu_gTxt('inclusion')." " . radio('meth',1,(($incl==1)?1:0)).smd_wu_gTxt('exclusion'). " | " . smd_wu_gTxt('whole_word').checkbox('whole', 1, (($whole==1)?1:0)). " | " . smd_wu_gTxt('match_case').checkbox('case', 1, (($case==1)?1:0)), 5));
	$out = '';
	foreach ($place as $here) {
		$out .= fLabelCell(ucfirst(gTxt($here)) . checkbox('places[]', $here, in_array($here,$sel)));
	}
	echo tr(fLabelCell(smd_wu_gTxt('search_where_lbl')) . $out);
	echo endTable();
	echo '</form>';

	// Render the prefs checkboxes
	$cols = getThings('describe `'.PFX.'textpattern`');
	$flds = do_list(get_pref('smd_wu_article_fields'));
	$flds = empty($flds[0]) ? do_list('Body,Excerpt,override_form,Section,Title,Keywords') : $flds;

	echo '<div id="smd_wu_prefwrap"><a id="smd_wu_preftog" href="#">'.smd_wu_gTxt('prefs_link').'</a>';
	echo '<form method="post" id="smd_wu_prefs" style="display:none;">';
	foreach ($cols as $col) {
		echo br.checkbox('smd_wu_article_fields[]', $col, (in_array($col, $flds) ? 1 : 0)).sp.$col;
	}
	echo fInput('submit', 'smd_wu_prefsave', gTxt('save'), 'smallerbox', '', '', '', '', 'smd_wu_prefsave');
	echo '</form></div>';
	echo <<<EOJS
<script type="text/javascript">
jQuery(function() {
	jQuery("#smd_search_for").focus();
	jQuery("#smd_wu_prefs").hide();
	jQuery("#smd_wu_preftog").click(function() {
		jQuery("#smd_wu_prefs").toggle('fast');
		return false;
	});
	jQuery("#smd_wu_prefsave").click(function() {
		var out = [];
		jQuery("#smd_wu_prefs input:checked").each(function() {
			out.push(jQuery(this).val());
		});

		sendAsyncEvent(
			{
				event: textpattern.event,
				step: 'smd_wu_prefsave',
				smd_wu_article_fields: out.join(',')
			}
		);
		jQuery("#smd_wu_prefs").toggle('fast');
		return false;
	});
});
</script>
<style type="text/css">
#smd_wu_prefwrap {
	width:140px;
	margin:10px auto 0;
}
</style>
EOJS;
}

// -------------------------------------------------------------
function smd_wu_prefsave() {
	$cols = getThings('describe `'.PFX.'textpattern`');
	$fields = do_list(gps('smd_wu_article_fields'));
	$oflds = array();
	foreach($fields as $fld) {
		if (in_array($fld, $cols)) {
			$oflds[] = $fld;
		}
	}
	set_pref('smd_wu_article_fields', join(',',$oflds), 'smd_wu', PREF_HIDDEN, 'text_input');
	send_xml_response();
}

// -------------------------------------------------------------
function smd_wu_search() {
	extract(doSlash(gpsa(array('search_for'))));
	$whole = gps('whole');
	$case = gps('case'); // 0 = case insensitive, 1 = case sensitive
	$caseSense = ($case==1) ? ' BINARY ' : '';
	$meth = gps('meth'); // 0 = include, 1 = exclude
	$plugtype = gps('plugtype'); // 0 = public, 1 = 0+admin, 2 = 1+library
	$plugtype = (is_numeric($plugtype) && $plugtype < 3) ? $plugtype : 0;
	$joinme = ($meth == 0) ? " OR " : " AND ";
	$places = gps('places');
	$places = is_array($places) ? $places : array();
	set_pref('smd_wu_look_in', serialize($places), 'smd_wu', PREF_HIDDEN, 'text_input');
	$mode = ($search_for) ? 0 : 1;
	smd_wu_showform('', $search_for, $places, $meth, $whole, $mode, $plugtype, $case);

	$artflds = get_pref('smd_wu_article_fields');
	$artflds = empty($artflds) ? 'Body,Excerpt,override_form,Section,Title,Keywords' : $artflds;

	// Entries in the placeTable array are:
	//  0: Table to search
	//  1: Column to search
	//  2: Column to return
	//  3: Heading to display if results found
	//  4: Event of destination URL
	//  5: Step of destination URL
	//  6: Additional URL var
	//  7: Additional URL var replacement (used in strtr)
	$placeTable = array(
		'pages' => array('txp_page', 'user_html', 'name', gTxt('pages'), 'page', '', 'name', '{name}', ''),
		'forms' => array('txp_form', 'form', 'name', gTxt('forms'), 'form', 'form_edit', 'name', '{name}', ''),
		'articles' => array('textpattern', $artflds, 'ID,title', gTxt('articles'), 'article', 'edit', 'ID', '{ID}', ''),
		'sections' => array('txp_section', 'page,css', 'name', gTxt('sections'), 'section', '', '#', 'section-{name}', ''),
		'stylesheets' => array('txp_css', 'css', 'name', smd_wu_gTxt('stylesheets'), 'css', '', 'name', '{name}', ''),
	);

	$rs = array();
	echo n.'<hr width="50%" />';
	echo startTable('list');
	$colHead = array();
	$colBody = array();

	if ($search_for) {
		echo tr(tdcs(tag(gTxt('search_results'), 'h2'), 5));
		foreach ($places as $place) {
			$crow = $placeTable[$place];
			$where = array();
			$witems = do_list($crow[1]);
			$list = do_list($crow[2]);

			foreach ($witems as $item) {
				$where[] = $caseSense . $item . (($meth==1) ? ' NOT' : '') . (($whole==1) ? ' REGEXP \'[[:<:]]'.$search_for.'[[:>:]]\'' : " LIKE '%$search_for%'");
			}
			$rs = safe_rows($crow[2], $crow[0], '('.join($joinme, $where).') ORDER BY '.$list[0]);
			$colHead[] = td(strong($crow[3]));
			if ($rs) {
				$out = '<ul>';
				foreach ($rs as $row) {
					$hlink = '';
					$vars = '';
					foreach ($list as $col) {
						$from = '{'.$col.'}';
						if (strpos($crow[7], $from) !== false) {
							if ($crow[6] == "#") {
								$vars = join_qs(array("event" => $crow[4], "step" => $crow[5])).$crow[6].strtr($crow[7], array($from => $row[$col]));
							} else {
								$vars = join_qs(array("event" => $crow[4], "step" => $crow[5], $crow[6] => strtr($crow[7], array($from => $row[$col]))));
							}
						}
						$hlink .= $row[$col]." ";
					}
					$out .= ($hlink) ? '<li>' . (($vars) ? '<a href="index.php'.$vars.'">'.$hlink.'</a> ' : $hlink) . '</li>': '';
				}
				$out .= '</ul>';
				$colBody[] = td($out);
			} else {
				$colBody[] = td(gTxt('no_results_found'));
			}
		}
		echo tr(join(" ", $colHead));
		echo tr(join(" ", $colBody));
	} else {
		// No search criteria, so show orphans
		echo tr(tdcs(tag(smd_wu_gTxt('orphan_results'), 'h2'), 5));

		// Reprogram the places/placeTable
		$artkey = array_search('articles', $places);
		if ($artkey !== false) {
			unset($places[$artkey]);
		}

		// pages/forms/sections to ignore because they are static and cannot be deleted
		$essentials = array(
			'sections' => array('default'),
			'pages' => array('error_default'),
			'forms' => array('comments','comments_display','comment_form','default','Links','files'), // copied from txp_forms
		);

		$places[] = 'plugins';

		$placeTable['plugins'] = array('txp_plugin', 'name', 'name', gTxt('plugins'), 'plugin', '', '#', '{name}');
		$placeTable['stylesheets'] = array('txp_css', 'name', 'name', smd_wu_gTxt('stylesheets'), 'css', '', 'name', '{name}');

		$placeTable['forms'][0] = "SELECT tf.name FROM " .safe_pfx('txp_form'). " AS tf WHERE tf.name NOT IN (" .doQuote(implode("','",$essentials['forms'])). ") ORDER BY tf.name"; // Would be nice to exclude forms that reference forms here instead of iterating through them later
		$placeTable['pages'][0] = "SELECT tp.name FROM " .safe_pfx('txp_section'). " AS ts RIGHT JOIN " .safe_pfx('txp_page'). " AS tp ON ts.page = tp.name WHERE ts.page IS NULL AND tp.name NOT IN (" .doQuote(implode("','",$essentials['pages'])). ") ORDER BY tp.name";
		$placeTable['plugins'][0] = "SELECT tg.name, tg.code FROM " .safe_pfx('txp_plugin'). " AS tg WHERE type < " .($plugtype+1). " AND tg.name != 'smd_where_used' ORDER BY tg.name";
		$placeTable['sections'][0] = "SELECT ts.name FROM " .safe_pfx('txp_section'). " AS ts LEFT JOIN " .safe_pfx('textpattern'). " AS txp ON ts.name = txp.section WHERE ID IS NULL AND ts.name NOT IN (" .doQuote(implode("','",$essentials['sections'])). ") ORDER BY ts.name";
		$placeTable['stylesheets'][0] = "SELECT tc.name FROM " .safe_pfx('txp_section'). " AS ts RIGHT JOIN " .safe_pfx('txp_css'). " AS tc ON ts.css = tc.name WHERE ts.page IS NULL ORDER BY tc.name";

		// For "awkward" queries that can't be done in one shot, there are three things required per $place:
		//  1: txp table name
		//  2: list of columns to compare
		//  3: method of comparison per column (0 = "direct match", 1 = "like")
		$cTable = array(
			'plugins' => array(
				'txp_form' => array('form' => 1),
				'txp_page' => array('user_html' => 1),
				'textpattern' => array('Body' => 1, 'Excerpt' => 1),
			),
			'forms' => array(
				'txp_form' => array('form' => 1),
				'txp_page' => array('user_html' => 1),
				'textpattern' => array('Body' => 1, 'Excerpt' => 1, 'override_form' => 0),
			),
		);

		// Work on a column at a time
		foreach ($places as $place) {
			$crow = $placeTable[$place];
			$colRefs = do_list($crow[2]);
			$colHead[] = td(strong($crow[3] . (($place == 'plugins') ? (($plugtype==0) ? smd_wu_gTxt('public_plugins') : smd_wu_gTxt('admin_plugins') ) : '') ));
			$rs = startRows($crow[0]);
			if ($rs) {
				$out = '<ul>';
				while ($row = nextRow($rs)) {
					// Count the no of records matching this name in each table.
					// Any time this goes above zero, the item is used and it can be ignored
					$fnaliases = array();
					if (array_key_exists($place, $cTable)) {
						// Plugin functions are not necessarily the name of the plugin itself.
						// Find all function definitions and build a list of aliases
						if ($place == 'plugins') {
							$re = '/function\s+([A-Za-z0-9_]+)\s*\(/';
							$num = preg_match_all($re, $row['code'], $fnaliases);
						}

						$recs = 0;
						foreach ($cTable[$place] as $tbl => $colInfo) {
							if ($recs == 0) {
								$where = array();
								foreach ($colInfo as $colName => $qryType) {
									$where[] = $colName . (($qryType==0) ? " = '" .$row[$colRefs[0]]. "'" : " LIKE '%" .$row[$colRefs[0]]. "%'");
									if (count($fnaliases) > 1) {
										foreach ($fnaliases[1] as $fnalias) {
											$where[] = $colName . (($qryType==0) ? " = '" .$fnalias. "'" : " LIKE '%" .$fnalias. "%'");
										}
									}
								}
								$where = join(" OR ", $where);
								$recs += safe_count($tbl, $where);
							}
						}

						// If the item has not been found, flag it
						if ($recs == 0) {
							$colNames = $colRefs;
						} else {
							$colNames = array();
						}
					} else {
						$colNames = $colRefs;
					}

					// Make up the string to display, and hyperlink it if directed
					$hlink = '';
					$vars = '';
					foreach ($colNames as $col) {
						$from = '{'.$col.'}';
						if (strpos($crow[7], $from) !== false) {
							if ($crow[6] == "#") {
								$vars = join_qs(array("event" => $crow[4], "step" => $crow[5])).$crow[6].strtr($crow[7], array($from => $row[$col]));
							} else {
								$vars = join_qs(array("event" => $crow[4], "step" => $crow[5], $crow[6] => strtr($crow[7], array($from => $row[$col]))));
							}
						}
						$hlink .= $row[$col]." ";
					}
					$out .= ($hlink) ? '<li>' . (($vars) ? '<a href="index.php'.$vars.'">'.$hlink.'</a> ' : $hlink) . '</li>': '';
				}
				$out .= '</ul>';
				$colBody[] = td($out);
			} else {
				$colBody[] = td(smd_wu_gTxt('no_orphans_found'));
			}
		}
		echo tr(join(" ", $colHead));
		echo tr(join(" ", $colBody));
	}
	echo endTable();
}

// -------------------------------------------------------------
// Plugin-specific replacement strings - localise as required
// -------------------------------------------------------------
function smd_wu_gTxt($what, $atts = array()) {
	$lang = array(
		'admin_plugins' => ' [ A+P ]',
		'public_plugins' => ' [ P ]',
		'exclusion' => 'Exclude',
		'inclusion' => 'Include',
		'filter' => 'Filter:',
		'match_case' => 'Match case',
		'no_orphans_found' => 'No orphans found.',
		'orphan_results' => 'Possible orphans',
		'prefs_link' => 'Article search fields',
		'search_lbl' => 'Find:',
		'search_where_lbl' => 'Look at:',
		'stylesheets' => 'Stylesheets',
		'smd_wu' => 'Where used',
		'whole_word' => 'Whole words',
	);
	return strtr($lang[$what], $atts);
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN CSS ---
<style type="text/css">
#smd_help { line-height:1.3 ;}
#smd_help code { font-weight:bold; font: 105%/130% "Courier New", courier, monospace; background-color: #FFFFCC;}
#smd_help code.block { font-weight:normal; border:1px dotted #999; background-color: #f0e68c; display:block; margin:10px 10px 20px; padding:10px; }
#smd_help a:link, #smd_help a:visited { color: blue; text-decoration: none; border-bottom: 1px solid blue; padding-bottom:1px;}
#smd_help a:hover, #smd_help a:active { color: blue; text-decoration: none; border-bottom: 2px solid blue; padding-bottom:1px;}
#smd_help h1 { color: #369; font: 20px Georgia, sans-serif; margin: 0; text-align: center; }
#smd_help h2 { border-bottom: 1px solid black; padding:10px 0 0; color: #369; font: 17px Georgia, sans-serif; }
#smd_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
#smd_help h4 { font: bold 11px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0 ;text-transform: uppercase; }
#smd_help .att-name { font-weight:bold; }
#smd_help .mand { background:#eee; border:1px dotted #999; }
#smd_help table {width:100%; text-align:center; padding-bottom:1em;}
#smd_help td, #smd_help th {border:1px solid #999; padding:.5em 0;}
</style>
# --- END PLUGIN CSS ---
-->
<!--
# --- BEGIN PLUGIN HELP ---
<div id="smd_help">

	<h1>smd_where_used</h1>

	<p>Really simple admin-side plugin to help find what you need to edit/delete. If you have a tonne of forms or pages and want to tidy stuff up it can be a bit of a pain to find what&#8217;s actually in use.</p>

	<p>So use this plugin to search your sections, pages, forms or articles for references to plugins, or other pages/forms/stylesheets or even just plain text in articles / stylesheets.</p>

	<h2 class="author">Author</h2>

	<p><a href="http://stefdawson.com/commentForm">Stef Dawson</a></p>

	<h2 class="install">Installation / Uninstallation</h2>

	<p>Download the plugin from either <a href="http://textpattern.org/plugins/984/smd_where_used">textpattern.org</a>, or the <a href="http://stefdawson.com/sw">software page</a>, paste the code into the <span class="caps">TXP</span> Admin -&gt; Plugins pane, install and enable the plugin. Visit the <a href="http://forum.textpattern.com/viewtopic.php?id=27493">forum thread</a> for more info and to report the success (or otherwise) of this plugin.</p>

	<p>Uninstall by simply deleting the plugin from the Admin-&gt;Plugins pane.</p>

	<h2 class="usage">Usage</h2>

	<p>Visit the Extensions tab and Click &#8216;Where used&#8217; to access the search form. Type some stuff to find, select where you want to look and hit &#8216;Search&#8217; or press Enter. Your requested locations will be searched for that term and the results tabulated, with hyperlinks to the offending items so you can quickly edit them.</p>

	<p>You can decide whether to search for the term (&#8220;Include&#8221;) or to search for stuff <span class="caps">NOT</span> containing that term (&#8220;Exclude&#8221;). You may also choose whether your search term matches/does not match a whole word or is case sensitive.</p>

	<p>This is what the plugin looks at when you select a particular checkbox from the &#8220;Look at&#8221; row:</p>

	<ul>
		<li>&#8216;Sections&#8217; searches every Section for pages or stylesheets with the matching name</li>
		<li>&#8216;Pages&#8217; searches every Page for forms / plugins / text with the matching name</li>
		<li>&#8216;Forms&#8217; searches every Form for plugins / other forms / text with the matching name</li>
		<li>&#8216;Stylesheets&#8217; searches every Stylesheet for text with the matching name</li>
		<li>&#8216;Articles&#8217; searches (by default) every Article section, body, excerpt, override_form, title and keyword for mention of the text you specify. Click the <em>Article search fields</em> button to see a list of database columns that you may search. Check the ones you wish to consider and click <em>Save</em></li>
	</ul>

	<p>If, however, you leave the &#8216;Find&#8217; box empty and click &#8216;Search&#8217; the plugin will search for orphans. Orphans are defined as follows:</p>

	<ul>
		<li>Any Page that is not assigned to a Section</li>
		<li>Any Stylesheet that is not assigned to a Section</li>
		<li>Any Section that has no Articles in it (excluding ‘default’ which cannot have an article anyway)</li>
		<li>Any Plugin that is not referenced from an Article (body/excerpt) or another Form or Page</li>
		<li>Any Form that has no reference to it in any Article (body/excerpt/override_form) or another Form or Page</li>
	</ul>

	<p>Notes:</p>

	<ul>
		<li>Orphaned articles don&#8217;t make sense so they are omitted. The checkbox is ignored</li>
		<li>Essential sections / pages / forms that cannot be deleted are not listed</li>
		<li>Plugins are always displayed because there&#8217;s no check box for it. The reason is that smd_where_used does not allow searching within a plugin for a reference to a word, but it does check other places for references <em>to</em> plugins</li>
		<li>The Plugin list can be toggled. It begins by showing both admin and public (A+P) plugins. If you wish to see public-only (P) plugins, click &#8216;Search&#8217; again to toggle the list</li>
		<li>Just because an item is listed as orphaned does <em>not necessarily mean it is not used</em>! For example, rvm_maintenance checks for the existence of an <code>error_503</code> Page. Since it is never assigned to a Section it will be listed as orphaned. If you have a dedicated stylesheet for maintenance mode that, too, will be shown as orphaned. In short, be careful and make a backup :-)</li>
		<li>Plugins such as pap_contact_cleaner and rvm_maintenance are listed as orphans even though they are used by other plugins / usually disabled. If you are unsure about a plugin, check it by typing a partial tag name into the search box</li>
	</ul>

	<h2 class="changelog">Changelog</h2>

	<ul>
		<li>16 Jun 08 | v0.1 | Initial release</li>
		<li>16 Jun 08 | v0.11 | Added section search and override_form</li>
		<li>16 Jun 08 | v0.12 | Added include/exclude (thanks mrdale)</li>
		<li>16 Jun 08 | v0.13 | Added ability to match whole words (thanks zanza)</li>
		<li>17 Jun 08 | v0.14 | Added orphan search (thanks uli/net-carver)</li>
		<li>18 Jun 08 | v0.15 | Fixed plugin list so it does a deep search inside the source code for tag names</li>
		<li>18 Jun 08 | v0.16 | Added admin/client side plugin toggle ; searches now extended to title and keywords (both thanks uli)</li>
		<li>18 Jun 08 | v0.17 | Articles are linked with ID and title (thanks uli)</li>
		<li>18 Jun 08 | v0.18 | Added case sensitive searches (thanks geoff777)</li>
		<li>11 Mar 09 | v0.19 | Added auto-focus to search box</li>
		<li>12 Aug 09 | v0.2 | Requires <span class="caps">TXP</span> 4.2.0 ; added auto-prefs for &#8216;look in&#8217; and article search fields pref (thanks uli)</li>
		<li>19 May 10 | v0.3 | Requires <span class="caps">TXP</span> 4.3.0 ; added searching inside Stylesheets</li>
	</ul>

</div>
# --- END PLUGIN HELP ---
-->
<?php
}
?>