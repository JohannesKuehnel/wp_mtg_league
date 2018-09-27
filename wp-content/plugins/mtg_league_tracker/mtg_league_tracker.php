<?php
/*
Plugin Name: MTG League Tracker
Description: A plugin to upload MTG tournament results from WER to keep track of league standings.
Version: 0.1.0
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

Copyright 2018 Johannes Kühnel
*/

if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

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

function mtglt_league_post_type() {
    register_post_type('mtglt_league',
        array(
            'labels'        => array(
                'name'          => __('Leagues'),
                'singular_name' => __('League')
            ),
            'public'        => true,
            'has_archive'   => true,
            'rewrite'       => array( 'slug' => 'leagues' )
        )
    );
}
add_action('init', 'mtglt_league_post_type');

function mtglt_tournament_post_type() {
    register_post_type('mtglt_tournament',
        array(
            'labels'        => array(
                'name'          => __('Tournaments'),
                'singular_name' => __('Tournament')
            ),
            'public'        => true,
            'has_archive'   => true,
            'rewrite'       => array( 'slug' => 'tournaments' )
        )
    );
}
add_action('init', 'mtglt_tournament_post_type');

function mtglt_tournament_box_html($post)
{
    $meta_value = get_post_meta($post->ID, '_mtglt_league_meta_key', true);
    $args = [
        'post_type'      => 'mtglt_league',
        'posts_per_page' => 10,
    ];
    $loop = new WP_Query($args);
    
    ?>
    <label for="mtglt_league_field">League</label>
    <select name="mtglt_league_field" id="mtglt_league_field" class="postbox">
        <option value="">Select a League...</option>
        <?php
            while ($loop->have_posts()) {
                $loop->the_post();
                echo '<option value="' . get_the_ID() . '" ' . selected($meta_value, get_the_ID()) . '>';
                the_title();
                echo '</option>';
            }
            wp_reset_postdata();

            // TODO: add option for date
            // TODO: add option for location
            // TODO: add option for format
            // TODO: add result upload
        ?>
    </select>
    <?php
}

function mtglt_tournament_save_postdata($post_id)
{
    if (array_key_exists('mtglt_league_field', $_POST)) {
        update_post_meta(
            $post_id,
            '_mtglt_league_meta_key',
            $_POST['mtglt_league_field']
        );
    }
}
add_action('save_post', 'mtglt_tournament_save_postdata');

function mtglt_add_tournament_box()
{
    $screens = ['mtglt_tournament'];
    foreach ($screens as $screen) {
        add_meta_box(
            'mtglt_tournament_box_id',           // Unique ID
            'Tournament Settings',  // Box title
            'mtglt_tournament_box_html',  // Content callback, must be of type callable
            $screen                   // Post type
        );
    }
}
add_action('add_meta_boxes', 'mtglt_add_tournament_box');

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