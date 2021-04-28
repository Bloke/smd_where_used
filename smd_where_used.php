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

$plugin['version'] = '0.4.0';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'https://stefdawson.com/';
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

$plugin['textpack'] = <<< EOT
#@language en, en-gb, en-us
#@admin-side
smd_wu => Where used
#@smd_wu
smd_wu_admin_plugins => [ A+P ]
smd_wu_public_plugins => [ P ]
smd_wu_exclusion => Exclude
smd_wu_inclusion => Include
smd_wu_filter => Filter:
smd_wu_match_case => Match case
smd_wu_no_orphans_found => No orphans found.
smd_wu_orphan_results => Possible orphans
smd_wu_prefs_link => Article search fields
smd_wu_search_lbl => Find:
smd_wu_search_where_lbl => Look at:
smd_wu_stylesheets => Stylesheets
smd_wu_whole_word => Whole words
EOT;

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/**
 * smd_where_used
 *
 * A Textpattern CMS plugin for finding which forms/pages/articles/plugins/text
 * have been used where in your site design.
 *
 * @author Stef Dawson
 * @link   https://stefdawson.com/
 */

if (txpinterface === 'admin') {
    new smd_wu();
}

/**
 * Admin-side user interface.
 */
class smd_wu
{
    /**
     * The plugin's event as registered in Txp.
     *
     * @var string
     */
    protected $event = 'smd_wu';

    /**
     * List of places offered to look for content matches
     *
     * @var array
     */
    protected $places = array('sections', 'pages', 'forms', 'stylesheets', 'articles');

    /**
     * List of default article fieldsto search
     *
     * @var string (comma-separated list)
     */
    protected $fields = 'Body, Excerpt, override_form, Section, Title, Keywords';

    /**
     * Constructor to set up callbacks and environment.
     */
    public function __construct()
    {
        add_privs($this->event, '1,2');

        register_tab('extensions', $this->event, gTxt($this->event));
        register_callback(array($this, $this->event), $this->event);
    }

    /**
     * Plugin jumpoff point.
     *
     * @param  string $evt Textpattern event
     * @param  string $stp Textpattern step (action)
     */
    public function smd_wu($evt, $stp)
    {
        $available_steps = array(
            'ui'       => false,
            'search'   => true,
            'prefsave' => true,
        );

        if (!$stp or !bouncer($stp, $available_steps)) {
            $stp = 'ui';
        }

        $this->$stp();
    }

    /**
     * Draw the user interface search options and input boxes.
     *
     * @param  string  $message  Feedback message to display
     * @param  string  $what     String term to search for
     * @param  array   $where    Options that denote where to search for the term
     * @return string            HTML
     */
    public function ui($message = '', $what= '', $where = array())
    {
        $instance = Txp::get('Textpattern\Skin\Skin');

        $sel = empty($where['places']) ? json_decode(get_pref('smd_wu_look_in')) : $where['places'];
        $sel = empty($sel) ? $this->places : $sel;

        $incl = empty($where['include']) ? 0 : $where['include'];
        $case = empty($where['case']) ? 0 : $where['case'];
        $mode = empty($where['mode']) ? 0 : $where['mode'];
        $skin = !isset($where['skin']) ? $instance->getEditing() : $where['skin'];
        $whole = empty($where['whole']) ? 0 : $where['whole'];
        $plugtype = empty($where['plugtype']) ? 5 : $where['plugtype'];

        pagetop(gTxt($this->event), $message);

        // Todo: introduce 3, 4, 5 plugin type support.
        $btnSearch = fInput('submit', 'smd_wu_search', gTxt('search'), 'publish')
            .eInput($this->event)
            .sInput('search')
            .tInput()
            .hInput('plugtype', (($plugtype == 1) ? 0 : 1));

        $skins = $instance->setName($skin)->getInstalled();
        $out = array();

        // TODO: use latest grid for layout.
        $out[] = '<form method="post" name="smd_wu_form">';
        $out[] = inputLabel(
                'skin',
                selectInput('skin', $skins, $skin, true, 0, 'skin'),
                'skin'
            );
        $out[] = startTable('list');
        $out[] = tr(
            fLabelCell(gTxt('smd_wu_search_lbl'))
            . tdcs(
                fInput('text', 'search_for', stripslashes($what), '', '', '', 30, '', 'smd_search_for')
                , 5)
            . tda($btnSearch));

        $meths = array(0 => gTxt('smd_wu_inclusion'), 1 => gTxt('smd_wu_exclusion'));
        $out[] = tr(
            fLabelCell(gTxt('smd_wu_filter'))
                .tdcs(
                    radioSet($meths, 'meth', (($incl == 1) ? 1 : 0))
                    ." | "
                    .checkbox('whole', 1, (($whole == 1) ? 1 : 0), '', 'smd_wu_whole')
                    .n.tag(gTxt('smd_wu_whole_word'), 'label', array('for' => 'smd_wu_whole'))
                    ." | "
                    .checkbox('case', 1, (($case == 1) ? 1 : 0), '', 'smd_wu_case')
                    .n.tag(gTxt('smd_wu_match_case'), 'label', array('for' => 'smd_wu_case'))
                    , 5));

        $lookAt = '';

        foreach ($this->places as $here) {
            $lookAt .= fLabelCell(
                checkbox('places[]', $here, in_array($here, $sel), '', 'smd_wu_look_'.$here)
                .n.tag(ucfirst(gTxt($here)), 'label', array('for' => 'smd_wu_look_'.$here))
            );
        }

        $out[] = tr(fLabelCell(gTxt('smd_wu_search_where_lbl')) . $lookAt);
        $out[] = endTable();
        $out[] = '</form>';

        // Render the prefs checkboxes.
        $cols = getThings('describe `'.PFX.'textpattern`');
        $flds = do_list(get_pref('smd_wu_article_fields'));
        $flds = empty($flds[0]) ? do_list($this->fields) : $flds;

        $out[] = '<div id="smd_wu_prefwrap"><a id="smd_wu_preftog" href="#">'.gTxt('smd_wu_prefs_link').'</a>';
        $out[] = '<form method="post" id="smd_wu_prefs" style="display:none;"><ul class="smd_grid">';

        foreach ($cols as $col) {
            $out[] = tag(checkbox('smd_wu_article_fields[]', $col, (in_array($col, $flds) ? 1 : 0), '', 'smd_wu_artfield_'.$col).n.tag($col, 'label', array('for' => 'smd_wu_artfield_'.$col)), 'li');
        }

        $out[] = '</ul>'.fInput('submit', 'smd_wu_prefsave', gTxt('save'), 'smallerbox', '', '', '', '', 'smd_wu_prefsave');
        $out[] = '</form></div>';
        $out[] = <<<EOJS
<script>
jQuery(function() {
    jQuery("#smd_search_for").focus();
    jQuery("#smd_wu_prefs").hide();
    jQuery("#smd_wu_preftog").click(function() {
        jQuery("#smd_wu_prefs").toggle();
        return false;
    });
    jQuery("#smd_wu_prefsave").click(function() {
        var out = [];
        jQuery("#smd_wu_prefs input:checked").each(function() {
            out.push(jQuery(this).val());
        });

        sendAsyncEvent({
                event: textpattern.event,
                step: 'prefsave',
                smd_wu_article_fields: out.join(',')
        });
        jQuery("#smd_wu_prefs").toggle();
        return false;
    });
});
</script>
<style>
#smd_wu_prefwrap {
    margin:0 1em 1em;
}
.smd_grid {
    display: flex;
    flex-wrap: wrap;
    list-style-type: none;
}
.smd_grid li {
    padding: 0 1em;
    width: 40%;
}
@media (min-width:48em) {
    .smd_grid li {
        width: 21%;
    }
}
</style>
EOJS;

        echo join(n, $out);
    }

    /**
     * [smd_wu_prefsave description]
     *
     */
    public function prefsave()
    {
        $cols = getThings('describe `'.PFX.'textpattern`');
        $fields = do_list(gps('smd_wu_article_fields'));
        $oflds = array();

        foreach ($fields as $fld) {
            if (in_array($fld, $cols)) {
                $oflds[] = $fld;
            }
        }

        set_pref('smd_wu_article_fields', join(',', $oflds), 'smd_wu', PREF_HIDDEN, 'text_input');
        send_xml_response();
    }

    /**
     * Perform the search operation
     *
     * @return string HTML
     */
    public function search()
    {
        extract(doSlash(gpsa(array('search_for'))));

        $whole = gps('whole');
        $case = gps('case'); // 0 = case insensitive, 1 = case sensitive.
        $caseSense = ($case == 1) ? ' BINARY ' : '';
        $meth = gps('meth'); // 0 = include, 1 = exclude
        $plugtype = gps('plugtype'); // 0 = public, 1 = 0+admin, 2 = 1+library.
        $plugtype = (is_numeric($plugtype) && $plugtype < 3) ? $plugtype : 0;
        $joinme = ($meth == 0) ? " OR " : " AND ";
        $places = gps('places');
        $places = is_array($places) ? $places : array();
        set_pref('smd_wu_look_in', json_encode($places), 'smd_wu', PREF_HIDDEN, 'text_input');
        $mode = ($search_for) ? 0 : 1;
        $skin = gps('skin');
        $skin = ($skin) ? Txp::get('Textpattern\Skin\Skin')->setName($skin) : '';

        $payload = array(
            'case' => $case,
            'mode' => $mode,
            'skin' => $skin,
            'whole' => $whole,
            'places' => $places,
            'include' => $meth,
            'plugtype' => $plugtype,
        );

        // @todo assign to $out[] instead of direct echo.
        echo $this->ui('', $search_for, $payload);

        $artflds = get_pref('smd_wu_article_fields');
        $artflds = empty($artflds) ? $this->fields : $artflds;

        // Entries in the placeTable array are:
        //  0: Table to search
        //  1: Column to search
        //  2: Column(s) to return
        //  3: Heading to display if results found
        //  4: Event of destination URL
        //  5: Step of destination URL
        //  6: Additional URL vars
        //  7: Additional URL vars replacement (used in strtr)
        //  8: Additional criteria required in query
        $placeTable = array(
            'pages' => array(
                'txp_page',
                'user_html',
                'name, skin',
                gTxt('pages'),
                'page',
                '',
                'name, skin',
                '{name},{skin}',
                ($skin ? "skin='".doSlash($skin)."'" : '')
            ),
            'forms' => array(
                'txp_form',
                'form',
                'name, skin',
                gTxt('forms'),
                'form',
                'form_edit',
                'name, skin',
                '{name}, {skin}',
                ($skin ? "skin='".doSlash($skin)."'" : '')
            ),
            'articles' => array(
                'textpattern',
                $artflds,
                'ID,title',
                gTxt('articles'),
                'article',
                'edit',
                'ID',
                '{ID}',
                ''
            ),
            'sections' => array(
                'txp_section',
                'page,css',
                'name',
                gTxt('sections'),
                'section',
                'section_edit',
                'name',
                '{name}',
                ''
            ),
            'stylesheets' => array(
                'txp_css',
                'css',
                'name, skin',
                gTxt('smd_wu_stylesheets'),
                'css',
                '',
                'name, skin',
                '{name}, {skin}',
                ($skin ? "skin='".doSlash($skin)."'" : '')
            ),
        );

        $rs = array();

        echo startTable('list');

        $colHead = array();
        $colBody = array();

        if ($search_for) {
            echo tr(tdcs(tag(gTxt('search_results'), 'h2'), 5));

            foreach ($places as $place) {
                $crow = $placeTable[$place];
                $where = $extra = array();
                $witems = do_list($crow[1]);
                $display = do_list($crow[2]);

                foreach ($witems as $item) {
                    $where[] = $caseSense . $item . (($meth==1) ? ' NOT' : '') . (($whole==1) ? ' REGEXP \'[[:<:]]'.$search_for.'[[:>:]]\'' : " LIKE '%$search_for%'");
                }

                if (!empty($crow[8])) {
                    $extra[] = ' AND '.$crow[8];
                }

                $extraQuery = join(' ',$extra);

                $rs = safe_rows($crow[2], $crow[0], '('.join($joinme, $where).')'.$extraQuery.' ORDER BY '.$display[0]);
                $colHead[] = td(strong($crow[3]));

                if ($rs) {
                    $out = '<ul>';

                    foreach ($rs as $row) {
                        $hlink = '';
                        $vars = '';

                        foreach ($display as $col) {
                            $urlvars = do_list($crow[6]);
                            $urlreps = do_list($crow[7]);
                            $urlout = array();

                            foreach ($urlvars as $idx => $urlvar) {
                                $from = '{'.$urlvar.'}';
                                $urlrep = $urlreps[$idx];
                                $urlout[$urlvar] = strtr($urlrep, array($from => $row[$urlvar]));
                            }

                            $vars = join_qs(array("event" => $crow[4], "step" => $crow[5]) + $urlout);
                            $hlink .= ($hlink ? ' | ' : '') . $row[$col];
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
            // No search criteria, so show orphans.
            echo tr(tdcs(tag(gTxt('smd_wu_orphan_results'), 'h2'), 5));

            // Reprogram the places/placeTable.
            $artkey = array_search('articles', $places);

            if ($artkey !== false) {
                unset($places[$artkey]);
            }

            // pages/forms/sections to ignore because they are static and cannot be deleted.
            $pageInfo = Txp::get('Textpattern\Skin\Page');
            $formInfo = Txp::get('Textpattern\Skin\Form');
            $essentials = array(
                'sections' => array('default'),
                'pages'    => $pageInfo->getEssential('name'),
                'forms'    => $formInfo->getEssential('name')
            );

            $places[] = 'plugins';

            // Overwrite some of the extraction criteria for the orphan search.
            $placeTable['plugins'] = array('txp_plugin', 'name', 'name', gTxt('plugins'), 'plugin', 'plugin', 'name:crit, search_method', '{name}, name');
            $placeTable['forms'][8] = $skin ? "tf.skin='".doSlash($skin)."'" : '';
            $placeTable['pages'][8] = $skin ? "tp.skin='".doSlash($skin)."'" : '';
            $placeTable['stylesheets'][1] = 'name';
            $placeTable['stylesheets'][8] = $skin ? "tc.skin='".doSlash($skin)."'" : '';
            $placeTable['forms'][0] = "SELECT tf.name, tf.skin FROM " .safe_pfx('txp_form'). " AS tf WHERE tf.name NOT IN (" .doQuote(implode("','",$essentials['forms'])). ") ".($placeTable['forms'][8] ? ' AND '.$placeTable['forms'][8] : '')." ORDER BY tf.name"; // Would be nice to exclude forms that reference forms here instead of iterating through them later.
            $placeTable['pages'][0] = "SELECT tp.name, tp.skin FROM " .safe_pfx('txp_section'). " AS ts RIGHT JOIN " .safe_pfx('txp_page'). " AS tp ON ts.page = tp.name WHERE ts.page IS NULL AND tp.name NOT IN (" .doQuote(implode("','",$essentials['pages'])). ") ".($placeTable['pages'][8] ? ' AND '.$placeTable['pages'][8] : '')." ORDER BY tp.name";
            $placeTable['plugins'][0] = "SELECT tg.name, tg.code FROM " .safe_pfx('txp_plugin'). " AS tg WHERE type < " .($plugtype+1). " AND tg.name != 'smd_where_used' ORDER BY tg.name";
            $placeTable['sections'][0] = "SELECT ts.name FROM " .safe_pfx('txp_section'). " AS ts LEFT JOIN " .safe_pfx('textpattern'). " AS txp ON ts.name = txp.section WHERE ID IS NULL AND ts.name NOT IN (" .doQuote(implode("','",$essentials['sections'])). ") ORDER BY ts.name";
            $placeTable['stylesheets'][0] = "SELECT tc.name, tc.skin FROM " .safe_pfx('txp_section'). " AS ts RIGHT JOIN " .safe_pfx('txp_css'). " AS tc ON ts.css = tc.name WHERE ts.css IS NULL ".($placeTable['stylesheets'][8] ? ' AND '.$placeTable['stylesheets'][8] : '')." ORDER BY tc.name";

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

            // Work on a column at a time.
            foreach ($places as $place) {
                $crow = $placeTable[$place];
                $colRefs = do_list($crow[2]);
                $colHead[] = td(strong($crow[3] . (($place == 'plugins') ? (($plugtype==0) ? gTxt('smd_wu_public_plugins') : gTxt('smd_wu_admin_plugins') ) : '') ));
                $rs = getRows($crow[0]);

                if ($rs) {
                    $out = '<ul>';

                    foreach ($rs as $row) {
                        // Count the no of records matching this name in each table.
                        // Any time this goes above zero, the item is used and it can be ignored.
                        $fnaliases = array();

                        if (array_key_exists($place, $cTable)) {
                            // Plugin functions are not necessarily the name of the plugin itself.
                            // Find all function definitions and build a list of aliases.
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

                            // If the item has not been found, flag it.
                            if ($recs == 0) {
                                $colNames = $colRefs;
                            } else {
                                $colNames = array();
                            }
                        } else {
                            $colNames = $colRefs;
                        }

                        // Make up the string to display, and hyperlink it if directed.
                        $hlink = '';
                        $vars = '';

                        foreach ($colNames as $col) {
                            $urlvars = do_list($crow[6]);
                            $urlreps = do_list($crow[7]);
                            $urlout = array();

                            foreach ($urlvars as $idx => $urlvar) {
                                $alias = do_list($urlvar, ':');
                                $from = '{'.$alias[0].'}';
                                $to = isset($alias[1]) ? $alias[1] : $urlvar;
                                $urlrep = $urlreps[$idx];
                                $urlout[$to] = isset($row[$alias[0]]) ? strtr($urlrep, array($from => $row[$alias[0]])) : $urlrep;
                            }

                            $vars = join_qs(array("event" => $crow[4], "step" => $crow[5]) + $urlout);
                            $hlink .= ($hlink ? ' | ' : '') . $row[$col];
                        }

                        $out .= ($hlink) ? '<li>' . (($vars) ? '<a href="index.php'.$vars.'">'.$hlink.'</a> ' : $hlink) . '</li>': '';
                    }

                    $out .= '</ul>';
                    $colBody[] = td($out);
                } else {
                    $colBody[] = td(gTxt('smd_wu_no_orphans_found'));
                }
            }
            echo tr(join(" ", $colHead));
            echo tr(join(" ", $colBody));
        }
        echo endTable();
    }

}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. smd_where_used

Really simple admin-side plugin to help find what you need to edit/delete. If you have a tonne of forms or pages and want to tidy stuff up it can be a bit of a pain to find what's actually in use.

Use this plugin to search your sections, pages, forms or articles for references to plugins, or other pages/forms/stylesheets or even just plain text in articles / stylesheets.

h2. Installation / Uninstallation

Download the plugin from either "textpattern.org":https://plugins.textpattern.com/plugins/smd_where_used, the "software page":https://stefdawson.com/sw, or "GitHub":https://github.com/Bloke/smd_where_used, paste the code into the Textpattern _Admin->Plugins_ panel, install and enable the plugin. Visit the "forum thread":https://forum.textpattern.com/viewtopic.php?id=27493 for more info and to report the success (or otherwise) of this plugin.

Uninstall by simply deleting the plugin from the _Admin->Plugins_ panel.

h2. Usage: find stuff in use

Visit the Extensions tab and Click ‘Where used' to access the search form. Type some stuff to find, select where you want to look and hit ‘Search' or press Enter. Your requested locations will be searched for that term and the results tabulated, with hyperlinks to the offending items so you can quickly edit them.

If you choose a Theme from the selector at the top of the panel the search will only be performed within that chosen theme. If you select the empty entry in the Theme selector, all themes will be searched. The theme that is related to each found asset is displayed after a pipe (|) character, and the links will jump you immediately to the correct asset in the given theme. It will also set it as the working theme.

You can decide whether to search for the term (“Include”) or to search for stuff NOT containing that term (“Exclude”). You may also choose whether your search term matches/does not match a whole word or is case sensitive.

This is what the plugin looks at when you select a particular checkbox from the "Look at" row:

* 'Sections' searches every Section for pages or stylesheets with the matching name.
* 'Pages' searches every Page for forms / plugins / text with the matching name.
* 'Forms' searches every Form for plugins / other forms / text with the matching name.
* 'Stylesheets' searches every Stylesheet for text with the matching name.
* 'Articles' searches (by default) every Article section, body, excerpt, override_form, title and keyword for mention of the text you specify. Click the _Article search fields_ link to see a list of database columns that you may search. Check the ones you wish to consider and click _Save_.

h2. Usage: finding stuff that might be orphaned

Leave the ‘Find' box empty and click ‘Search'. The plugin will search for orphans. Orphans are defined as follows:

* Any Page that is not assigned to a Section.
* Any Stylesheet that is not assigned to a Section.
* Any Section that has no Articles in it (excluding ‘default' which cannot have an article anyway).
* Any Plugin that is not referenced from an Article (body/excerpt) or another Form or Page.
* Any Form that has no reference to it in any Article (body/excerpt/override_form) or another Form or Page.

Notes:

* Orphaned articles don't make sense so they are omitted. The checkbox is ignored.
* Essential sections / pages / forms that cannot be deleted are not listed.
* Plugins are always displayed because there's no check box for it. The reason is that smd_where_used does not allow searching within a plugin for a reference to a word, but it does check other places for references _to_ plugins.
* The Plugin list can be toggled. It begins by showing both admin and public (A+P) plugins. If you wish to see public-only (P) plugins, click ‘Search' again to toggle the list.
* Just because an item is listed as orphaned does _not necessarily mean it is not used_! For example, rvm_maintenance checks for the existence of an @error_503@ Page. Since it is never assigned to a Section it will be listed as orphaned. If you have a dedicated stylesheet for maintenance mode that, too, will be shown as orphaned. In short, be careful and make a backup :-)
* Plugins such as pap_contact_cleaner and rvm_maintenance are listed as orphans even though they are used by other plugins / usually disabled. If you are unsure about a plugin, check it by typing a partial tag name into the search box.

h2. Author

"Stef Dawson":https://stefdawson.com/sw
# --- END PLUGIN HELP ---
-->
<?php
}
?>