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
TODO: create result table on install - see https://codex.wordpress.org/Creating_Tables_with_Plugins
TODO: create player table on install - see https://codex.wordpress.org/Creating_Tables_with_Plugins
TODO: process and save results on save - see old/parse_xml.php and old/upload.php
TODO: add delete button to meta box
*/

if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

function mtglt_plugin_activate(){
    if ( ! is_plugin_active( 'the-events-calendar/the-events-calendar.php' ) and current_user_can( 'activate_plugins' ) ) {
        wp_die('Sorry, but this plugin requires The Events Calendar to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
    }
}
register_activation_hook( __FILE__, 'mtglt_plugin_activate' );

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

// add .xml to the list of allowed file types
function result_file_mime_types($mime_types){
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
    wp_enqueue_script('MTGLT_JS_Admin', plugins_url( '/js/admin.js', __FILE__ ), array('jquery','media-upload','thickbox'), 1.3, true);
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
    if( !current_user_can( 'edit_post' ) ) return;
 
    $fields = [
        'mtglt_result_file'
    ];
    foreach($fields as $field)
    {
        if( isset( $_POST[$field] ) )
        {
            update_post_meta( $post_id, $field, $_POST[$field] );
        }
    }
}
add_action('save_post', 'mtglt_tournament_save_postdata');



function mgtlt_league_shortcode( $atts = [] ) {
    $atts = array_change_key_case((array)$atts, CASE_LOWER);
    $mtglt_atts = shortcode_atts(
        array(
            'id' => '0'
        ),
        $atts,
        'league'
    );

    $args = [
        'post_type'      => 'mtglt_tournament',
        'posts_per_page' => 10,
        'meta_key'       => '_mtglt_league_meta_key',
        'meta_value'     => $mtglt_atts['id']
    ];
    $loop = new WP_Query($args);
    $output = '<ul>';
    while ($loop->have_posts()) {
        $loop->the_post();
        $output .= '<li>';
        $output .= the_title('', '', false);
        $output .= '</li>';
    }
    $output .= '</ul>';
    wp_reset_postdata();
    return $output;
}

function mgtlt_tournament_shortcode( $atts = [] ) {
    $atts = array_change_key_case((array)$atts, CASE_LOWER);
    $mtglt_atts = shortcode_atts(
        array(
            'id' => '0',
            'top8' => 'false'
        ),
        $atts,
        'tournament'
    );
    return 'test';
}

function mgtlt_shortcode_init() {
    add_shortcode( 'league', 'mgtlt_league_shortcode' );
    add_shortcode( 'tournament', 'mgtlt_tournament_shortcode' );
}
add_action('init', 'mgtlt_shortcode_init');

?>