<?php
/*
 * (c) 2012 Damien Corpataux
 *
 * LICENSE
 * This library is licensed under the GNU GPL v3.0 license,
 * accessible at http://www.gnu.org/licenses/gpl-3.0.html
 *
**/

/**
 * Create files from .in files, replacing environment variables.
 * For example: default.ini will be created from default.ini.in
 *
 * Note: This script is a draft and is not complete
**/

require_once(dirname(__file__).'/../util.php');

init();
process();

/**
 * Sets up script useful information
 */
function init() {
    require_once(dirname(__file__).'/../../lib/Util/Bootstrap.php');
    new xBootstrap('script');
}

/**
 * Processes .in files
 */
function process() {
var_dump(php_uname('n'));
var_dump(xContext::dump());
die();

    foreach (get_files() as $in_file) {
        $in_content = file_get_contents($in_file);
        $content = $in_content;
        $replacements = array(); //TODO: populate this from ?
        foreach ($replacements as $key => $replacement) {
            $content = str_replace("%{$key}%", $replacement, $content);
        }
        $file = substr($in_file, 0, -strlen('.in'));
        file_put_contents($file, $content);
    }
}

/**
 * Returns an array containing the .in files to be processed
 * @return array
 */
function get_files() {
    return explore_dir(xContext::$basepath, 'in');
}

/**
 *
 *
 */
function get_env_file() {
}


// Util function (moved from in ../util.php file)

/**
 * Outputs a string on stdout, optionally indenting it
 * @param string $msg The string to output
 * @param int $indent_level The indentation level
 */
function o($msg = '', $indent_level = 0) {
    $indent = '';
    for ($i=0; $i<$indent_level; $i++) $indent .= '*';
    if (strlen($indent)) $indent .= ' ';
    print "{$indent}{$msg}\n";
}
?>