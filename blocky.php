<?php
/*
 * Plugin Name: Blocky! - Additional Content Sections
 * Plugin URI: http://cameronjones.x10.mx/projects/blocky
 * Description: Add additional sections to your page content - no theme editing required!
 * Version: 1.1.1
 * Author: Cameron Jones
 * Author URI: http://cameronjones.x10.mx
 * Text Domain: blocky
 * License: GPLv2
 
 * Copyright 2015  Cameron Jones  (email : cameronjonesweb@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 */

defined( 'ABSPATH' ) or die( /*'No script kiddies please!'*/ );

add_action( 'add_meta_boxes', 'blocky_dynamic_add_custom_box' );
add_action( 'save_post', 'blocky_dynamic_save_postdata' );
add_action( 'admin_enqueue_scripts', 'blocky_admin_resources' );
add_action( 'wp_ajax_nopriv_ajax_wp_editor', 'blocky_ajax_wp_editor' );
add_action( 'wp_ajax_ajax_wp_editor', 'blocky_ajax_wp_editor' );

add_filter( 'the_content', 'blocky_content_filter' );
add_filter( 'tiny_mce_before_init', 'blocky_get_TinyMCE_Settings' );

global $TinyMCE_settings;

function get_additional_content( $postID = NULL ) {
	$return = array();
	if( isset( $postID ) && !empty( $postID ) ) {
		$blocky_post_id = $postID;
	} else {
		global $post;
		$blocky_post_id = $post->ID;
	}
	
	$blocky_additional_content = get_post_meta( $blocky_post_id, 'blocky_extra_content' );
	if( isset( $blocky_additional_content ) && !empty( $blocky_additional_content ) ) {
		foreach( $blocky_additional_content[0] as $blocky_section ){
			$return[] = array( 'class' => $blocky_section['class'], 'content' => $blocky_section['content'] );
		}
	}
	return $return;
}

function blocky_content_filter( $content ) {
	
	global $post;
	
	$blocky_additional_content = get_post_meta( $post->ID, 'blocky_extra_content' );
	//Get tag setting
	$blocky_tag_setting = get_option('blocky_tag');
	if( empty( $blocky_tag_setting ) ) {
		$blocky_tag = 'div';
	} else {
		$blocky_tag = $blocky_tag_setting;
	}
	if( isset( $blocky_tag ) && !empty( $blocky_tag ) ) {
		$blocky_opentag = '<' . $blocky_tag . '>';
		$blocky_closetag = '</' . $blocky_tag . '>';
	}
	
	$blocky_new_content = NULL;
		$blocky_new_content .= $blocky_opentag;
		$blocky_new_content .= $content;
		$blocky_new_content .= $blocky_closetag;
		if( isset( $blocky_additional_content ) && !empty( $blocky_additional_content ) ) {
		foreach( $blocky_additional_content[0] as $blocky_section ){
			$blocky_new_content .= str_replace( '>', ' class="' . $blocky_section['class'] . '" data-blocky-version="1.1.0">', $blocky_opentag );
			$blocky_new_content .= do_shortcode( $blocky_section['content'] );
			$blocky_new_content .= $blocky_closetag;
		}
	}
	return $blocky_new_content;
}


// 

// Do something with the data entered

//Enqueue admin resources
function blocky_admin_resources() {
        wp_enqueue_style(  'Blocky! Admin CSS', plugin_dir_url( __FILE__ ) . '/css/admin.css' );
}

// Adds a box to the main column on the Post and Page edit screens
function blocky_dynamic_add_custom_box() {
	$blocky_post_types = get_option( 'blocky_post_types' );
	//$blocky_post_types = get_post_types( '', 'names' ); 
	if( isset( $blocky_post_types ) && !empty( $blocky_post_types ) ) {
		foreach( $blocky_post_types as $blocky_post_type => $active ){
			add_meta_box( 'blocky_meta_box', __( 'Additional Content', 'additional_content_sections' ), 'blocky_dynamic_inner_custom_box', $blocky_post_type );
		}
	}
}

function blocky_ajax_wp_editor() {
	wp_editor( '', $_GET['id'] . '_' . $_GET['count'], array( 'textarea_name' => $_GET['id'] . '[' . $_GET['count'] . ']' ) );
	wp_enqueue_media();
	\_WP_Editors::enqueue_scripts();
    print_footer_scripts();
    \_WP_Editors::editor_js();
	
	die();
}

// Prints the box content
function blocky_dynamic_inner_custom_box() {
    global $post,$TinyMCE_settings;
	$experimental = get_option( 'blocky_experimental_editor' );
	//var_dump($TinyMCE_settings);
    // Use nonce for verification
    wp_nonce_field( plugin_basename( __FILE__ ), 'blocky_dynamicMeta_noncename' );
    ?>
    <div id="meta_inner">
    <?php

    //get the saved meta as an arry
    $extra_content = get_post_meta($post->ID,'blocky_extra_content',true);
    $count = 0;
    if ( isset( $extra_content ) && !empty( $extra_content) && count( $extra_content ) > 0 ) {
        foreach( $extra_content as $section ) {
			echo '<div id="extra_content_section_' . $count . '" class="extra_content_section">';
			echo '<h3>Section ' . $count . '</h3>';
			echo '<p>Section class: <input type="text" name="blocky_extra_content[' . $count . '][class]" value="' . $section['class'] . '" /></p>';
			wp_editor( $section['content'], 'blocky_extra_content_' . $count, array( 'textarea_name' => 'blocky_extra_content[' . $count . '][content]', 'textarea_rows' => 15 ) );
			echo '<div class="remove_content button deletion">Remove</div>';
			echo '</div>';
			$count++;
        }
    }

    ?>
	<span id="new_content_area"></span>
	<a class="add_content button button-primary"><?php _e('Add new Content Section'); ?></a>
    <?php if( $experimental === 'true' ) {?>
	<script>
    var $ =jQuery.noConflict();
    $(document).ready(function() {
        var count = <?php echo $count-1; ?>;
        $(".add_content").click(function() {
			$.ajax({
				url : '<?php echo admin_url('admin-ajax.php'); ?>',
				data : { 
					id: 'blocky_extra_content',
					action: 'ajax_wp_editor',
					count: count
				},
				method : 'get',
				cache: false,
				success : function(data){
					
					if (data != 0) {
						var new_section = '<div id="extra_content_section_' + count + '" class="extra_content_section"><h3>Section ' + count + '</h3>';
						new_section += '<p>Section class: <input type="text" name="blocky_extra_content[' + count + '][class]" /></p>';
						new_section += data;
						new_section += '<div class="remove_content button error">Remove</div></div>';
						$('#new_content_area').append( new_section );
					}
				}
			});
			tinymce.execCommand('mceAddControl', false, 'extra_content_'+count+'');
	        count = count + 1;
            return false;
        });
        $(".remove_content").live('click', function() {
            $(this).parent().remove();
        });
    });
    </script>
<?php 
	} else { ?>
	<script>
	    var $ =jQuery.noConflict();
    $(document).ready(function() {
        var count = <?php echo $count; ?>;
        $(".add_content").click(function() {
			var new_section = '<div id="extra_content_section_' + count + '" class="extra_content_section"><h3>Section ' + count + '</h3>';
			new_section += '<p><em>You will need to save your post in order to enable the media uploader and plain text editor for this section.</em></p>';
			new_section += '<p>Section class: <input type="text" name="blocky_extra_content[' + count + '][class]" /></p>';
			new_section += '<textarea name="blocky_extra_content[' + count + '][content]" id="extra_content_'+count+'" class="tinymce"></textarea>'; //AJAX to add new editor
			new_section += '<div class="remove_content button error">Remove</div></div>';
			$('#new_content_area').append( new_section );
			tinymce.init({
				selector: ".tinymce",
				file: false,
				height: 300,
				<?php
				foreach ( $TinyMCE_settings as $name => $value ) {
					if( $name != 'selector' ){
						if( substr( $value, 0, 1 ) == '{' ) {
							echo $name . ": {" . substr( $value, 1, -1 ) . "},\n";
						} else {
							echo $name . ": '" . $value . "',\n";
						}
					}
				}
				?>
			});
			tinymce.execCommand('mceAddControl', false, 'extra_content_'+count+'');
	        count = count + 1;
            return false;
        });
        $(".remove_content").live('click', function() {
            $(this).parent().remove();
        });
    });
	</script>
	<?php } ?>
    </div>
<?php }

/* When the post is saved, saves our custom data */
function blocky_dynamic_save_postdata( $post_id ) {
    // verify if this is an auto save routine. 
    // If it is our form has not been submitted, so we dont want to do anything
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
        return;

    // verify this came from the our screen and with proper authorization,
    // because save_post can be triggered at other times
    if ( !isset( $_POST['blocky_dynamicMeta_noncename'] ) )
        return;

    if ( !wp_verify_nonce( $_POST['blocky_dynamicMeta_noncename'], plugin_basename( __FILE__ ) ) )
        return;

    // OK, we're authenticated: we need to find and save the data

    $blocky_extra_content = $_POST['blocky_extra_content'];
	
	$post_type = get_post_type( $post_id );
	$allowed = wp_kses_allowed_html( $post_type );
	foreach( $blocky_extra_content as $content ){
		$content = wp_kses( $content, $allowed );
	}

    update_post_meta( $post_id, 'blocky_extra_content', $blocky_extra_content );
}

function blocky_get_TinyMCE_Settings( $in ) {
	global $TinyMCE_settings;
	$TinyMCE_settings = $in;
	return $in;
}

// create custom plugin settings menu
add_action('admin_menu', 'blocky_admin_menu');

function blocky_admin_menu() {

	//SVG Icon for settings page - http://www.mobilefish.com/services/base64/base64.php
	$icon = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTYuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+CjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iTGF5ZXJfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeD0iMHB4IiB5PSIwcHgiCgkgd2lkdGg9IjQ4cHgiIGhlaWdodD0iNDhweCIgdmlld0JveD0iMCAwIDQ4IDQ4IiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCA0OCA0ODsiIHhtbDpzcGFjZT0icHJlc2VydmUiPgo8Zz4KCTxwb2x5Z29uIHN0eWxlPSJmaWxsOiNGRkZGRkY7IiBwb2ludHM9IjQwLjYwNyw0MC42MDcgMzQuOTUyLDQwLjYwNyAzNC45NTIsNDcuMzkzIDQ3LjM5Myw0Ny4zOTMgNDcuMzkzLDM0Ljk1MiA0MC42MDcsMzQuOTUyIAkiLz4KCTxyZWN0IHg9IjE4LjUyNCIgeT0iNDAuNjA3IiBzdHlsZT0iZmlsbDojRkZGRkZGOyIgd2lkdGg9IjEwLjk1MyIgaGVpZ2h0PSI2Ljc4NSIvPgoJPHBvbHlnb24gc3R5bGU9ImZpbGw6I0ZGRkZGRjsiIHBvaW50cz0iNy4zOTMsMzQuOTUyIDAuNjA3LDM0Ljk1MiAwLjYwNyw0Ny4zOTMgMTMuMDQ3LDQ3LjM5MyAxMy4wNDcsNDAuNjA3IDcuMzkzLDQwLjYwNyAJIi8+Cgk8cmVjdCB4PSIwLjYwNyIgeT0iMTguNTI0IiBzdHlsZT0iZmlsbDojRkZGRkZGOyIgd2lkdGg9IjYuNzg1IiBoZWlnaHQ9IjEwLjk1MyIvPgoJPHBvbHlnb24gc3R5bGU9ImZpbGw6I0ZGRkZGRjsiIHBvaW50cz0iNy4zOTMsNy4zOTMgMTMuMDQ3LDcuMzkzIDEzLjA0NywwLjYwNyAwLjYwNywwLjYwNyAwLjYwNywxMy4wNDcgNy4zOTMsMTMuMDQ3IAkiLz4KCTxyZWN0IHg9IjE4LjUyNCIgeT0iMC42MDciIHN0eWxlPSJmaWxsOiNGRkZGRkY7IiB3aWR0aD0iMTAuOTUzIiBoZWlnaHQ9IjYuNzg1Ii8+Cgk8cG9seWdvbiBzdHlsZT0iZmlsbDojRkZGRkZGOyIgcG9pbnRzPSIzNC45NTIsMC42MDcgMzQuOTUyLDcuMzkzIDQwLjYwNyw3LjM5MyA0MC42MDcsMTMuMDQ3IDQ3LjM5MywxMy4wNDcgNDcuMzkzLDAuNjA3IAkiLz4KCTxyZWN0IHg9IjQwLjYwNyIgeT0iMTguNTI0IiBzdHlsZT0iZmlsbDojRkZGRkZGOyIgd2lkdGg9IjYuNzg1IiBoZWlnaHQ9IjEwLjk1MyIvPgoJPHBvbHlnb24gc3R5bGU9ImZpbGw6I0ZGRkZGRjsiIHBvaW50cz0iMjYuMTksMzYuMDUxIDI2LjE5LDI2LjgyMSAzNS40MjYsMjYuODIxIDM1LjQyNiwyMi41NDUgMjYuMTksMjIuNTQ1IDI2LjE5LDEzLjMxMSAKCQkyMS45MiwxMy4zMTEgMjEuOTIsMjIuNTQ1IDEyLjY4MiwyMi41NDUgMTIuNjgyLDI2LjgyMSAyMS45MiwyNi44MjEgMjEuOTIsMzYuMDUxIAkiLz4KPC9nPgo8L3N2Zz4=';
	//create new top-level menu
	add_menu_page('Blocky! Settings', 'Blocky!', 'administrator', 'blocky-settings', 'blocky_settings_page' , $icon );

	//call register settings function
	add_action( 'admin_init', 'blocky_settings' );
}


function blocky_settings() {
	//register our settings
	register_setting( 'blocky_settings', 'blocky_tag' );
	register_setting( 'blocky_settings', 'blocky_experimental_editor' );
	register_setting( 'blocky_settings', 'blocky_post_types' );
}

function blocky_settings_page() {
	$blocky_post_types = get_post_types( '', 'names' ); 
?>
<div class="wrap">
<h2>Blocky!</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'blocky_settings' ); ?>
    <?php do_settings_sections( 'blocky_settings' ); ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Content Sections Tag (default div)</th>
            <td><input type="text" name="blocky_tag" value="<?php echo esc_attr( get_option('blocky_tag') ); ?>" placeholder="div" /></td>
        </tr>
       <!-- <tr valign="top">
            <th scope="row">Use experimental editor (use at your own risk, will break things)</th>
            <td><input type="checkbox" name="blocky_experimental_editor" <?php checked( get_option( 'blocky_experimental_editor' ), 'true' );?> value="true" /></td>
        </tr>-->
        <tr valign="top">
            <th scope="row">Post types</th>
            <td>
              	<?php $checked = get_option( 'blocky_post_types' );?>
				<?php foreach( $blocky_post_types as $post_type ) {?>
                	<?php if( $post_type != 'revision' && $post_type != 'nav_menu_item' ) {?>
	                	<p><label><input type="checkbox" name="blocky_post_types[<?php echo $post_type;?>]" <?php if( isset( $checked[$post_type] ) && !empty( $checked[$post_type] ) ) { echo 'checked'; }?>  value="true" /><?php echo ucfirst( $post_type );?></label></p>
                    <?php } ?>
                <?php } ?>
            </td>
        </tr>
    </table>
    
    <?php submit_button(); ?>

</form>
</div>
<?php }	