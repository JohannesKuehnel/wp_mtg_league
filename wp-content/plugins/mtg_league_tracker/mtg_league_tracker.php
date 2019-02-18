<?php
/*
Plugin Name: MTG League Tracker
Description: A plugin to upload MTG tournament results from WER to keep track of league standings.
Version: 0.1.1
Author: Johannes Kühnel
Author URI: https://www.kuehnel.co.at/
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Copyright 2019 Johannes Kühnel
*/

/*
TODO: add delete button to meta box
*/

if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

defined('MTGLT_PLAYERS_TABLE_NAME') or define('MTGLT_PLAYERS_TABLE_NAME', 'mtglt_players');
defined('MTGLT_RESULTS_TABLE_NAME') or define('MTGLT_RESULTS_TABLE_NAME', 'mtglt_results');

function mtglt_plugin_activate(){
    if ( ! is_plugin_active( 'the-events-calendar/the-events-calendar.php' ) and current_user_can( 'activate_plugins' ) ) {
        wp_die('Sorry, but this plugin requires The Events Calendar to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
    }
}
register_activation_hook( __FILE__, 'mtglt_plugin_activate' );

function mtglt_install() {
    global $wpdb;
    global $MTGLT_PLAYERS_TABLE_NAME;
    global $MTGLT_RESULTS_TABLE_NAME;

    $charset_collate = $wpdb->get_charset_collate();

    $players_table_name = $wpdb->prefix . MTGLT_PLAYERS_TABLE_NAME;
    $results_table_name = $wpdb->prefix . MTGLT_RESULTS_TABLE_NAME;
    $posts_table_name = $wpdb->prefix . 'posts';

    $players_sql = "CREATE TABLE $players_table_name (
        `dci` varchar(32) NOT NULL DEFAULT '',
        `name` varchar(32) DEFAULT NULL,
        PRIMARY KEY  (`dci`)
    ) $charset_collate;";

    $results_sql = "CREATE TABLE $results_table_name (
        `result_id` bigint(20) NOT NULL AUTO_INCREMENT,
        `player_dci` varchar(32) DEFAULT NULL,
        `tournament_id` bigint(20) DEFAULT NULL,
        `rank` int(11) DEFAULT NULL,
        `points` int(11) NOT NULL,
        PRIMARY KEY  (`result_id`)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $players_sql );
    dbDelta( $results_sql );
}
register_activation_hook( __FILE__, 'mtglt_install' );

// add .xml to the list of allowed file types
function result_file_mime_types($mime_types) {
    $mime_types['xml'] = 'text/xml';
    return $mime_types;
}
add_filter('upload_mimes', 'result_file_mime_types', 1, 1);

function mtglt_add_result_box($post) {
    add_meta_box( 'mtglt-result-file' , __( 'Result File', 'textdomain' ), 'mtglt_file_callback', ['tribe_events'], 'side', 'low' );
}
add_action( 'add_meta_boxes', 'mtglt_add_result_box' );

function mtglt_file_callback($post) {
    wp_nonce_field( 'mtglt_nonce', 'meta_box_nonce' );
    $fileLink = get_post_meta($post->ID, "mtglt_result_file", true);
?>
<label for="mtglt_result_file">Result File</label>
<input id="mtglt_result_file" name="mtglt_result_file" type="text" value="<?= $fileLink ?>" />
<input id="upload_button" type="button" value="Choose File" />
<?php
}

function mtglt_add_admin_scripts($hook) {
    if($hook !== 'post-new.php' && $hook !== 'post.php')
    {
        return;
    }
    wp_enqueue_script('media-upload');
    wp_enqueue_script('thickbox');
    wp_enqueue_script('MTGLT_JS_Admin', plugins_url( '/js/admin.js', __FILE__ ), array('jquery','media-upload','thickbox'), 1.4, true);
    wp_enqueue_style('thickbox');
}
add_action('admin_enqueue_scripts', 'mtglt_add_admin_scripts');

function mtglt_tournament_save_postdata($post_id)
{
    // Bail if we're doing an auto save
    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
 
    // if our nonce isn't there, or we can't verify it, bail
    if( !isset( $_POST['meta_box_nonce'] ) || !wp_verify_nonce( $_POST['meta_box_nonce'], 'mtglt_nonce' ) ) return;
 
    // if our current user can't edit this post, bail
    //if( !current_user_can( 'edit_post' ) ) return;
 
    if( isset( $_POST['mtglt_result_file'] ) )
    {
        update_post_meta( $post_id, 'mtglt_result_file', $_POST['mtglt_result_file'] );
        require_once( 'parse_xml.php' );

        $parsed = parse_url( $_POST['mtglt_result_file'] );
        $url    = '..' . dirname( $parsed [ 'path' ] ) . '/' . rawurlencode( basename( $parsed[ 'path' ] ) );
        $tournament = parse_tournament($url);
        //if(!$tournament) return;
        $date = date('Y-m-d', strtotime($tournament['date'])); // fetch from Event
        $format = $tournament['format']; // fetch from Event Slug
        $tournamentName = get_the_title( $post_id );
        $players = $tournament['players'];

        global $wpdb;
        $players_table_name = $wpdb->prefix . MTGLT_PLAYERS_TABLE_NAME;
        $results_table_name = $wpdb->prefix . MTGLT_RESULTS_TABLE_NAME;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        $tournament_id = $post_id;

        $point_schema = array(
            array(50, 35, 25, 25, 15, 15, 15, 15),
            array(60, 45, 30, 30, 20, 20, 20, 20),
            array(70, 55, 35, 35, 25, 25, 25, 25),
        );
        $schema_index = count($players) <= 16 ? 0 : (count($players) <= 32 ? 1 : 2);

        foreach ($players as $player) {
            // TODO: change to $wpdb->insert()
            $player->points = $player->rank <= 8 ? $point_schema[$schema_index][$player->rank - 1] : 5;
            $sql = "INSERT INTO $players_table_name (dci, name) VALUES (" . $player->dci . ", '" . utf8_decode($player->name) . "')";
            dbDelta($sql);
            $sql = "INSERT INTO $results_table_name (player_dci, tournament_id, rank, points) VALUES (" . $player->dci . ", " . $tournament_id . ", " . $player->rank . ", " . $player->points . ")";
            dbDelta($sql);
        }
    }
}
add_action('save_post', 'mtglt_tournament_save_postdata');

function mtglt_options_page_html()
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // output security fields for the registered setting "mtgtracker_options"
            settings_fields('mtglt_options');
            // output setting sections and their fields
            // (sections are registered for "mtgtracker", each field is registered to a specific section)
            do_settings_sections('mtglt');

            // output save settings button
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

function mtglt_options_page()
{
    add_options_page(
        'MTG League Tracker',
        'MTG League Tracker',
        'manage_options',
        'mtglt',
        'mtglt_options_page_html'
    );
}
add_action('admin_menu', 'mtglt_options_page');

function mgtlt_standings_shortcode( $atts = [] ) {
    $atts = array_change_key_case((array)$atts, CASE_LOWER);
    $mtglt_atts = shortcode_atts(
        array(
            'season' => date("Y"),
            'format' => 'both'
        ),
        $atts,
        'standings'
    );

    $season = $mtglt_atts['season'];
    $format = $mtglt_atts['format'];
    
    global $wpdb;
    $players_table_name = $wpdb->prefix . MTGLT_PLAYERS_TABLE_NAME;
    $results_table_name = $wpdb->prefix . MTGLT_RESULTS_TABLE_NAME;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $sql = "SELECT $players_table_name.name, $players_table_name.dci, SUM($results_table_name.points) as points FROM $players_table_name, $results_table_name WHERE $players_table_name.dci = $results_table_name.player_dci GROUP BY $players_table_name.name ORDER BY points DESC, name ASC";
    $result = $wpdb->get_results($sql);
    $output = "";
    if (count($result) > 0) {
        // TODO: add table styling
        $output .= "<table>\n";
        $output .= "<tr><th>Name</th><th>DCI #</th><th>Points</th></tr>\n";
        foreach ($result as $key => $row) {
            $output .= "<tr><td>" . $row->name . "</td><td>" . $row->dci . "</td><td>" . $row->points . "</td></tr>\n";
        }
        $output .= "</table>\n";
    } else {
        $output .= "Keine Turniere hinterlegt\n";
    }

    return $output;
}

function mgtlt_shortcode_init() {
    add_shortcode( 'standings', 'mgtlt_standings_shortcode' );
}
add_action('init', 'mgtlt_shortcode_init');

?>