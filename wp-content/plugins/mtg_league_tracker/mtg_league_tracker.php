<?php
/*
Plugin Name: MTG League Tracker
Description: A plugin to upload MTG tournament results from WER to keep track of league standings.
Version: 0.2
Author: Johannes Kühnel
Author URI: https://www.kuehnel.co.at/
License: MIT
*/

/*
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

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

// fix for upload limitation - https://codepen.io/chriscoyier/post/wordpress-4-7-1-svg-upload
add_filter( 'wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
    $filetype = wp_check_filetype( $filename, $mimes );
  
    return [
        'ext'             => $filetype['ext'],
        'type'            => $filetype['type'],
        'proper_filename' => $data['proper_filename']
    ];
  
}, 10, 4 );

// add .xml to the list of allowed file types
function result_file_mime_types($mime_types) {
    $mime_types['xml'] = 'text/xml';
    return $mime_types;
}
add_filter('upload_mimes', 'result_file_mime_types');

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
    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if( !isset( $_POST['meta_box_nonce'] ) || !wp_verify_nonce( $_POST['meta_box_nonce'], 'mtglt_nonce' ) ) return;

    if( isset( $_POST['mtglt_result_file'] ) )
    {
        update_post_meta( $post_id, 'mtglt_result_file', $_POST['mtglt_result_file'] );
        require_once( 'parse_xml.php' );

        $parsed = parse_url( $_POST['mtglt_result_file'] );
        $url    = '..' . dirname( $parsed [ 'path' ] ) . '/' . rawurlencode( basename( $parsed[ 'path' ] ) );
        $tournament = parse_tournament($url);
        if(!$tournament) return; // TODO: add admin notice on fail

        $players = $tournament['players'];

        global $wpdb;
        $players_table_name = $wpdb->prefix . MTGLT_PLAYERS_TABLE_NAME;
        $results_table_name = $wpdb->prefix . MTGLT_RESULTS_TABLE_NAME;
        
        $tournament_id = $post_id;

        // TODO: move point settings to dedicated option page
        $point_schema = array(
            array(50, 35, 25, 25, 15, 15, 15, 15),
            array(60, 45, 30, 30, 20, 20, 20, 20),
            array(70, 55, 35, 35, 25, 25, 25, 25),
        );
        $schema_index = count($players) <= 16 ? 0 : (count($players) <= 32 ? 1 : 2);

        $has_results = $wpdb->get_var( "SELECT COUNT(*) FROM $results_table_name WHERE $results_table_name.tournament_id = $tournament_id" );
        if( $has_results ) {
            // TODO: add admin notice or confirmation prompt
            $wpdb->delete( $results_table_name, array(
                'tournament_id' => $tournament_id
            ), '%d' );
        }

        foreach ($players as $player) {
            $player->points = $player->rank <= 8 ? $point_schema[$schema_index][$player->rank - 1] : 5;
            $wpdb->replace( $players_table_name, array(
                'dci' => $player->dci,
                'name' => utf8_decode($player->name)
            ), array(
                '%s',
                '%s'
            ) );
            $wpdb->insert( $results_table_name, array(
                'player_dci' => $player->dci,
                'tournament_id' => $tournament_id,
                'rank' => $player->rank,
                'points' => $player->points
            ), array(
                '%s',
                '%d',
                '%d',
                '%d'
            ) );
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

/*
function add_top8_before_event($before) {
    $before .= "<a class='mtglt-top8-link' href='#mtglt-top8'><h3>Zu den Top 8</h3></a>";
    return $before;
}
add_filter('tribe_events_before_html', 'add_top8_before_event');
*/
function add_top8_after_event($after) {
    $tournament = get_the_ID();

    if( tribe_is_event_category() || tribe_is_events_home() ) return $after;

    global $wpdb;
    $players_table_name = $wpdb->prefix . MTGLT_PLAYERS_TABLE_NAME;
    $results_table_name = $wpdb->prefix . MTGLT_RESULTS_TABLE_NAME;

    $sql = "SELECT $results_table_name.rank, $players_table_name.name, $players_table_name.dci, SUM($results_table_name.points) as points FROM $players_table_name, $results_table_name WHERE $players_table_name.dci = $results_table_name.player_dci AND $results_table_name.tournament_id = $tournament GROUP BY $players_table_name.name ORDER BY $results_table_name.rank ASC LIMIT 8";
    $result = $wpdb->get_results($sql);
    $after = "<div class='mtglt-top8'>";
    $after .= "<h3><a id='mtglt-top8'></a>Top 8</h3>";
    if (count($result) > 0) {
        $after .= "<table class='mtglt-top8-table'>\n";
        $after .= "<tr><th>Platz</th><th>Name</th><th>DCI #</th><th>Points</th></tr>\n";
        foreach ($result as $key => $row) {
            $after .= "<tr><td>" . $row->rank . "</td><td>" . $row->name . "</td><td>" . $row->dci . "</td><td>" . $row->points . "</td></tr>\n";
        }
        $after .= "</table>\n";
    } else {
        $after .= "Keine Ergebnisse hinterlegt\n";
    }
    $after .= "</div>";

    return $after;
}
add_filter('tribe_events_before_html', 'add_top8_after_event');

function mgtlt_standings_shortcode( $atts = [] ) {
    $atts = array_change_key_case((array)$atts, CASE_LOWER);
    $mtglt_atts = shortcode_atts(
        array(
            'season' => date("Y"),
            'format' => 'legacy',
            'type' => 'open-series'
        ),
        $atts,
        'standings'
    );

    $season = $mtglt_atts['season'];
    $format = strtolower($mtglt_atts['format']);
    $type = strtolower($mtglt_atts['type']);
    
    global $wpdb;
    $players_table_name = $wpdb->prefix . MTGLT_PLAYERS_TABLE_NAME;
    $results_table_name = $wpdb->prefix . MTGLT_RESULTS_TABLE_NAME;
    $posts_table = $wpdb->prefix . 'posts';

    $args = array(
        'start_date' => date("Y-m-d H:i:s", mktime(0, 0, 0, 1, 1, $season)),
        'end_date' => date("Y-m-d H:i:s", mktime(59, 59, 23, 31, 12, $season))
    );
    $events = tribe_get_events($args);
    $events = array_filter( $events, function($event) use($format, $type) {
        $categories = tribe_get_event_cat_slugs ( $event->ID );
        $is_correct_type = in_array($type, $categories);
        $is_correct_format = in_array($format, $categories);
        return $is_correct_type && $is_correct_format;
    });
    $event_ids = implode(',', array_map(function($post){
        return $post->ID;
    }, $events));

    $sql = "SELECT $players_table_name.name, $players_table_name.dci, SUM($results_table_name.points) as points FROM $players_table_name, $results_table_name WHERE $players_table_name.dci = $results_table_name.player_dci AND FIND_IN_SET($results_table_name.tournament_id, '$event_ids') GROUP BY $players_table_name.name ORDER BY points DESC, name ASC";
    $result = $wpdb->get_results($sql);
    $output = "<div class='mtglt-standings'>";
    if (count($result) > 0) {
        $output .= "<table class='mtglt-standings-table $format'>\n";
        $output .= "<tr><th>Name</th><th>DCI #</th><th>Points</th></tr>\n";
        foreach ($result as $key => $row) {
            $output .= "<tr><td>" . $row->name . "</td><td>" . $row->dci . "</td><td>" . $row->points . "</td></tr>\n";
        }
        $output .= "</table>\n";

        // Display past events
        $output .= "<h3 class='mtglt-standings-tournaments $format'>Ber&uuml;cksichtigte Turniere</h3>";
        $output .= "<ul>";
        foreach ($events as $key => $event) {
            // Remove events without result files
            $has_results = tribe_get_event_meta($event->ID, "mtglt_result_file");
            if (!$has_results) {
                continue;
            }
            $output .= "<li>" . date("d.m.Y", strtotime($event->EventStartDate)) . " " . $event->post_title . "</li>\n";
        }
        $output .= "</ul>";
    } else {
        $output .= "Keine Turniere hinterlegt\n";
    }
    $output .= "</div>";

    return $output;
}

function mgtlt_shortcode_init() {
    add_shortcode( 'standings', 'mgtlt_standings_shortcode' );
}
add_action('init', 'mgtlt_shortcode_init');

?>