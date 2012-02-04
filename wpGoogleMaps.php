<?php
/*
Plugin Name: WP Google Maps
Plugin URI: http://www.wpgmaps.com
Description: Create custom Google Maps with high quality markers containing locations, descriptions, images and links. Add your customized map to your WordPress posts and/or pages quickly and easily with the supplied shortcode. No fuss.
Version: 3.1
Author: Nick Duncan
Author URI: http://www.wpgmaps.com
*/

global $wpgmza_version;
global $wpgmza_p_version;
global $wpgmza_t;
global $wpgmza_tblname;
global $wpdb;
global $wpgmza_p;
$wpgmza_p = false;
$wpgmza_tblname = $wpdb->prefix . "wpgmza";
$wpgmza_version = "3.1";
$wpgmza_p_version = "3.1";
$wpgmza_t = "basic";
@include_once dirname( __FILE__ ) . '/pro/wpgmaps_pro.php';
add_action('admin_head', 'wpgmaps_head');
add_action('admin_footer', 'wpgmaps_reload_map_on_post');
register_activation_hook( __FILE__, 'wpgmaps_activate' );
register_deactivation_hook( __FILE__, 'wpgmaps_deactivate' );
add_action('init', 'wpgmaps_load_jquery');
add_action('save_post', 'wpgmaps_save_postdata');
add_action('admin_menu', 'wpgmaps_admin_menu');



function wpgmaps_activate() {

    global $wpdb;
    global $wpgmza_version;
    
    $table_name = $wpdb->prefix . "wpgmza";
    
    wpgmaps_handle_db();


    $wpgmza_data = get_option("WPGMZA");
    if (!$wpgmza_data) { add_option("WPGMZA",array("map_start_lat" => "51.5081290", "map_start_lng" => "-0.1280050", "map_width" => "600", "map_height" => "400", "map_start_location" => "London", "map_start_zoom" => "5", "directions_enable" => '1')); }
    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE `map_id` = '1'");
    if (!$results) { $rows_affected = $wpdb->insert( $table_name, array( 'map_id' => '1', 'address' => 'London', 'lat' => '51.5081290', 'lng' => '-0.1280050' ) ); }

    wpgmza_cURL_response("activate");


    //check to see if you have write permissions to the plugin folder (version 2.2)
    if (!wpgmaps_check_permissions()) { wpgmaps_permission_warning(); } else { wpgmaps_update_xml_file(); }

    
    




}
function wpgmaps_deactivate() {
    //delete_option("WPGMZA");
    wpgmza_cURL_response("deactivate");
}

function wpgmaps_load_jquery() {
    @session_start();
    $_SESSION['wpgmaps_dir'] = wpgmaps_get_plugin_url();
    wp_enqueue_script("jquery");

}

function wpgmaps_reload_map_on_post() {
    if (isset($_POST['wpgmza_savemap'])){
        $wpgmza_data = get_option('WPGMZA');
        $wpgmza_lat = $wpgmza_data['map_start_lat'];
        $wpgmza_lng = $wpgmza_data['map_start_lng'];
        $wpgmza_width = $wpgmza_data['map_width'];
        $wpgmza_height = $wpgmza_data['map_height'];
        $wpgmza_default_icon = $wpgmza_data['map_default_marker'];
        $start_zoom = $wpgmza_data['map_start_zoom'];
        if ($start_zoom < 1 || !$start_zoom) { $start_zoom = 5; }
        if (!$wpgmza_lat || !$wpgmza_lng) { $wpgmza_lat = "51.5081290"; $wpgmza_lng = "-0.1280050"; } // show London
    
        ?>
        <script type="text/javascript" >
        jQuery(function() {
            jQuery("#wpgmza_map").css({
		height:<?php echo $wpgmza_height; ?>,
		width:<?php echo $wpgmza_width; ?>

            });
            var myLatLng = new google.maps.LatLng(<?php echo $wpgmza_lat; ?>,<?php echo $wpgmza_lng; ?>);
            MYMAP.init('#wpgmza_map', myLatLng, <?php echo $start_zoom; ?>);
            UniqueCode=Math.round(Math.random()*10010);
            MYMAP.placeMarkers('<?php echo wpgmaps_get_plugin_url(); ?>/markers.xml?u='+UniqueCode);
            
        });
        </script>
        <?php
    }


}

function wpgmaps_admin_javascript_basic() {
    $ajax_nonce = wp_create_nonce("wpgmza");

    if (is_admin() && $_GET['page'] == 'wp-google-maps-menu') {
        wpgmaps_update_xml_file();

    ?>
    <script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
    <link rel='stylesheet' id='wpgooglemaps-css'  href='<?php echo wpgmaps_get_plugin_url(); ?>/css/wpgmza_style.css' type='text/css' media='all' />
    <script type="text/javascript" >
    jQuery(function() {


            jQuery(document).ready(function(){

                    jQuery(".wpgmza_del_btn").live("click", function() {
                        var cur_id = jQuery(this).attr("id");
                        var wpgm_map_id = "0";
                        if (document.getElementsByName("wpgmza_id").length > 0) { wpgm_map_id = jQuery("#wpgmza_id").val(); }
                        var data = {
                                action: 'delete_marker',
                                security: '<?php echo $ajax_nonce; ?>',
                                map_id: wpgm_map_id,
                                marker_id: cur_id
                        };
                        jQuery.post(ajaxurl, data, function(response) {
                                <?php
                                    $wpgmza_data = get_option('WPGMZA');
                                    $wpgmza_lat = $wpgmza_data['map_start_lat'];
                                    $wpgmza_lng = $wpgmza_data['map_start_lng'];
                                    $wpgmza_width = $wpgmza_data['map_width'];
                                    $wpgmza_height = $wpgmza_data['map_height'];
                                    $start_zoom = $wpgmza_data['map_start_zoom'];
                                    if ($start_zoom < 1 || !$start_zoom) { $start_zoom = 5; }
                                    if (!$wpgmza_lat || !$wpgmza_lng) { $wpgmza_lat = "51.5081290"; $wpgmza_lng = "-0.1280050"; } // show London
                                ?>
                                var myLatLng = new google.maps.LatLng(<?php echo $wpgmza_lat; ?>,<?php echo $wpgmza_lng; ?>);
                                MYMAP.init('#wpgmza_map', myLatLng, <?php echo $start_zoom; ?>);
                                UniqueCode=Math.round(Math.random()*11200);
                                MYMAP.placeMarkers('<?php echo wpgmaps_get_plugin_url(); ?>/markers.xml?u='+UniqueCode);

                                jQuery("#wpgmza_tr_"+cur_id).css("display","none");
                        });

                    });


                    jQuery(".wpgmza_edit_btn").live("click", function() {
                        var cur_id = jQuery(this).attr("id");

                        var wpgmza_edit_address = jQuery("#wpgmza_hid_marker_address_"+cur_id).val();

                        jQuery("#wpgmza_edit_id").val(cur_id);

                        jQuery("#wpgmza_add_address").val(wpgmza_edit_address);

                        jQuery("#wpgmza_addmarker_div").hide();
                        jQuery("#wpgmza_editmarker_div").show();


                    });

                    

                    jQuery("#wpgmza_addmarker").click(function(){
                        jQuery("#wpgmza_addmarker").hide();
                        jQuery("#wpgmza_addmarker_loading").show();

                        var wpgm_address = "0";
                        var wpgm_map_id = "0";
                        if (document.getElementsByName("wpgmza_add_address").length > 0) { wpgm_address = jQuery("#wpgmza_add_address").val(); }
                        if (document.getElementsByName("wpgmza_id").length > 0) { wpgm_map_id = jQuery("#wpgmza_id").val(); }

                        var data = {
                                action: 'add_marker',
                                security: '<?php echo $ajax_nonce; ?>',
                                map_id: wpgm_map_id,
                                address: wpgm_address
                        };
                        jQuery.post(ajaxurl, data, function(response) {
                                UniqueCode=Math.round(Math.random()*10021);
                                MYMAP.placeMarkers('<?php echo wpgmaps_get_plugin_url(); ?>/markers.xml?u='+UniqueCode);
                                jQuery("#wpgmza_marker_holder").html(response);
                                jQuery("#wpgmza_addmarker").show();
                                jQuery("#wpgmza_addmarker_loading").hide();
                                jQuery("#wpgmza_add_address").val("");
                                jQuery("#wpgmza_add_desc").val("");
                                jQuery("#wpgmza_add_pic").val("");
                                jQuery("#wpgmza_link_url").val("");


                        });

                    });


                    jQuery("#wpgmza_editmarker").click(function(){
                    
                        jQuery("#wpgmza_editmarker_div").hide();
                        jQuery("#wpgmza_editmarker_loading").show();


                        var wpgm_edit_id;
                        wpgm_edit_id = parseInt(jQuery("#wpgmza_edit_id").val());
                        var wpgm_address = "0";
                        var wpgm_map_id = "0";
                        if (document.getElementsByName("wpgmza_add_address").length > 0) { wpgm_address = jQuery("#wpgmza_add_address").val(); }
                        if (document.getElementsByName("wpgmza_id").length > 0) { wpgm_map_id = jQuery("#wpgmza_id").val(); }

                        var data = {
                                action: 'edit_marker',
                                security: '<?php echo $ajax_nonce; ?>',
                                map_id: wpgm_map_id,
                                edit_id: wpgm_edit_id,
                                address: wpgm_address
                            };

                        
                        jQuery.post(ajaxurl, data, function(response) {
                                <?php
                                    $wpgmza_data = get_option('WPGMZA');
                                    $wpgmza_lat = $wpgmza_data['map_start_lat'];
                                    $wpgmza_lng = $wpgmza_data['map_start_lng'];
                                    $wpgmza_width = $wpgmza_data['map_width'];
                                    $wpgmza_height = $wpgmza_data['map_height'];
                                    $start_zoom = $wpgmza_data['map_start_zoom'];
                                    if ($start_zoom < 1 || !$start_zoom) { $start_zoom = 5; }
                                    if (!$wpgmza_lat || !$wpgmza_lng) { $wpgmza_lat = "51.5081290"; $wpgmza_lng = "-0.1280050"; } // show London
                                ?>
                                var myLatLng = new google.maps.LatLng(<?php echo $wpgmza_lat; ?>,<?php echo $wpgmza_lng; ?>);
                                MYMAP.init('#wpgmza_map', myLatLng, <?php echo $start_zoom; ?>);
                                UniqueCode=Math.round(Math.random()*11200);
                                MYMAP.placeMarkers('<?php echo wpgmaps_get_plugin_url(); ?>/markers.xml?u='+UniqueCode);

                                jQuery("#wpgmza_marker_holder").html(response);
                                jQuery("#wpgmza_addmarker_div").show();
                                jQuery("#wpgmza_editmarker_loading").hide();


                                jQuery("#wpgmza_add_address").val("");
                                jQuery("#wpgmza_add_desc").val("");
                                jQuery("#wpgmza_add_pic").val("");
                                jQuery("#wpgmza_link_url").val("");
                                jQuery("#wpgmza_edit_id").val("");


                        });

                    });
            });


                <?php

                $wpgmza_data = get_option('WPGMZA');
                $wpgmza_lat = $wpgmza_data['map_start_lat'];
                $wpgmza_lng = $wpgmza_data['map_start_lng'];
                $wpgmza_width = $wpgmza_data['map_width'];
                $wpgmza_height = $wpgmza_data['map_height'];
                $start_zoom = $wpgmza_data['map_start_zoom'];
                if ($start_zoom < 1 || !$start_zoom) { $start_zoom = 5; }
                if (!$wpgmza_lat || !$wpgmza_lng) { $wpgmza_lat = "51.5081290"; $wpgmza_lng = "-0.1280050"; }
                ?>


                jQuery("#wpgmza_map").css({
                    height:<?php echo $wpgmza_height; ?>,
                    width:<?php echo $wpgmza_width; ?>

                });
                var myLatLng = new google.maps.LatLng(<?php echo $wpgmza_lat; ?>,<?php echo $wpgmza_lng; ?>);
                MYMAP.init('#wpgmza_map', myLatLng, <?php echo $start_zoom; ?>);
                UniqueCode=Math.round(Math.random()*10000);
                MYMAP.placeMarkers('<?php echo wpgmaps_get_plugin_url(); ?>/markers.xml?u='+UniqueCode);


            });


            var MYMAP = {
                map: null,
                bounds: null
            }
            MYMAP.init = function(selector, latLng, zoom) {
              var myOptions = {
                zoom:zoom,
                center: latLng,
                mapTypeId: google.maps.MapTypeId.ROADMAP
              }
              this.map = new google.maps.Map(jQuery(selector)[0], myOptions);
                    this.bounds = new google.maps.LatLngBounds();
            }

            MYMAP.placeMarkers = function(filename) {
                    jQuery.get(filename, function(xml){
                            jQuery(xml).find("marker").each(function(){
                                    var wpmgza_address = jQuery(this).find('address').text();

                                    var lat = jQuery(this).find('lat').text();
                                    var lng = jQuery(this).find('lng').text();
                                    var point = new google.maps.LatLng(parseFloat(lat),parseFloat(lng));
                                    MYMAP.bounds.extend(point);
                                    var marker = new google.maps.Marker({
                                            position: point,
                                            map: MYMAP.map,
                                            

                                    });
                                    var infoWindow = new google.maps.InfoWindow();
                                    var html='<strong>'+wpmgza_address+'</strong>';

                                    google.maps.event.addListener(marker, 'click', function() {
                                            infoWindow.setContent(html);
                                            infoWindow.open(MYMAP.map, marker);
                                    });
                                    //MYMAP.map.fitBounds(MYMAP.bounds);

                                    google.maps.event.addListener(MYMAP.map, 'zoom_changed', function() {
                                        zoomLevel = MYMAP.map.getZoom();

                                        jQuery("#wpgmza_start_zoom").val(zoomLevel);
                                        if (zoomLevel == 0) {
                                          MYMAP.map.setZoom(10);
                                        }
                                      });
                                    google.maps.event.addListener(MYMAP.map, 'center_changed', function() {
                                        var location = MYMAP.map.getCenter();
                                        jQuery("#wpgmza_start_location").val(location.lat()+","+location.lng());
                                        jQuery("#wpgmaps_save_reminder").show();
                                    });


                            });
                    });
            }

        </script>
<?php
}

}


function wpgmaps_user_javascript_basic() {
    $ajax_nonce = wp_create_nonce("wpgmza");
    $wpgmza_data = get_option('WPGMZA');
    $wpgmza_lat = $wpgmza_data['map_start_lat'];
    $wpgmza_lng = $wpgmza_data['map_start_lng'];
    $wpgmza_width = $wpgmza_data['map_width'];
    $wpgmza_height = $wpgmza_data['map_height'];
    $wpgmza_default_icon = $wpgmza_data['map_default_marker'];
    $start_zoom = $wpgmza_data['map_start_zoom'];
    if ($start_zoom < 1 || !$start_zoom) { $start_zoom = 5; }
    if (!$wpgmza_lat || !$wpgmza_lng) { $wpgmza_lat = "51.5081290"; $wpgmza_lng = "-0.1280050"; }

    ?>
    <script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
    <link rel='stylesheet' id='wpgooglemaps-css'  href='<?php echo wpgmaps_get_plugin_url(); ?>/css/wpgmza_style.css' type='text/css' media='all' />
    <script type="text/javascript" >
    jQuery(function() {
    
   

    jQuery("#wpgmza_map").css({
        height:<?php echo $wpgmza_height; ?>,
        width:<?php echo $wpgmza_width; ?>

    });
    var myLatLng = new google.maps.LatLng(<?php echo $wpgmza_lat; ?>,<?php echo $wpgmza_lng; ?>);
    MYMAP.init('#wpgmza_map', myLatLng, <?php echo $start_zoom; ?>);
    UniqueCode=Math.round(Math.random()*10000);
    MYMAP.placeMarkers('<?php echo wpgmaps_get_plugin_url(); ?>/markers.xml?u='+UniqueCode);


    });

    
    var MYMAP = {
        map: null,
        bounds: null
    }
    MYMAP.init = function(selector, latLng, zoom) {
      var myOptions = {
        zoom:zoom,
        center: latLng,
        mapTypeId: google.maps.MapTypeId.ROADMAP
      }
      this.map = new google.maps.Map(jQuery(selector)[0], myOptions);
      this.bounds = new google.maps.LatLngBounds();

    }
   

    
    MYMAP.placeMarkers = function(filename) {
        jQuery.get(filename, function(xml){
                jQuery(xml).find("marker").each(function(){
                        var wpmgza_address = jQuery(this).find('address').text();
                        var wpgmza_def_icon = '<?php echo $wpgmza_default_icon; ?>';
                        var wpmgza_mapicon = jQuery(this).find('icon').text();
                        var lat = jQuery(this).find('lat').text();
                        var lng = jQuery(this).find('lng').text();

                        if (wpmgza_mapicon == "" || !wpmgza_mapicon) { if (wpgmza_def_icon != "") { wpmgza_mapicon = '<?php echo $wpgmza_default_icon; ?>'; } }


                        var point = new google.maps.LatLng(parseFloat(lat),parseFloat(lng));
                        MYMAP.bounds.extend(point);
                        var marker = new google.maps.Marker({
                                position: point,
                                map: MYMAP.map,
                                icon: wpmgza_mapicon
                        });
                        var infoWindow = new google.maps.InfoWindow();
                        var html='<strong>'+wpmgza_address+'</strong>';

                        google.maps.event.addListener(marker, 'click', function(evt) {
                                infoWindow.setContent(html);
                                infoWindow.open(MYMAP.map, marker);

                        });



                });
        });
    }

    </script>
<?php

}




function wpgmaps_update_xml_file() {



    global $wpdb;


    $dom = new DOMDocument('1.0');
    $dom->formatOutput = true;


    $channel_main = $dom->createElement('markers');
    $channel = $dom->appendChild($channel_main);

    $table_name = $wpdb->prefix . "wpgmza";

    $results = $wpdb->get_results(
	"
	SELECT *
	FROM $table_name
	WHERE `map_id` = '1'
	"
);

foreach ( $results as $result )
{
	

        $address = $result->address;
        $description = $result->desc;
        $pic = $result->pic;
        if (!$pic) { $pic = ""; }
        $icon = $result->icon;
        if (!$icon) { $icon = ""; }
        $link_url = $result->link;
        if ($link_url) {  } else { $link_url = ""; }
        $lat = $result->lat;
        $lng = $result->lng;


        $channel = $channel_main->appendChild($dom->createElement('marker'));


        $title = $channel->appendChild($dom->createElement('address'));
        $title->appendChild($dom->CreateTextNode($address));

        $desc = $channel->appendChild($dom->createElement('desc'));
        $desc->appendChild($dom->CreateTextNode($description));

        $desc = $channel->appendChild($dom->createElement('pic'));
        $desc->appendChild($dom->CreateTextNode($pic));

        $desc = $channel->appendChild($dom->createElement('icon'));
        $desc->appendChild($dom->CreateTextNode($icon));

        $desc = $channel->appendChild($dom->createElement('linkd'));
        $desc->appendChild($dom->CreateTextNode($link_url));


        $bd = $channel->appendChild($dom->createElement('lat'));
        $bd->appendChild($dom->CreateTextNode($lat));
        $bd = $channel->appendChild($dom->createElement('lng'));
        $bd->appendChild($dom->CreateTextNode($lng));




}



   @$dom->save(WP_PLUGIN_DIR.'/'.plugin_basename(dirname(__FILE__)).'/markers.xml'); // change back when live
}






function wpgmaps_action_callback_basic() {
        global $wpdb;
        global $wpgmza_tblname;
        global $wpgmza_p;
        $check = check_ajax_referer( 'wpgmza', 'security' );
        $table_name = $wpdb->prefix . "wpgmza";
        if ($check == 1) {

            if ($_POST['action'] == "add_marker") {
                  $gps = wpgmza_get_lat_long($_POST['address']);
                  $lat = $gps['lat'];
                  $lng = $gps['lng'];
                  $rows_affected = $wpdb->insert( $table_name, array( 'map_id' => $_POST['map_id'], 'address' => $_POST['address'], 'lat' => $lat, 'lng' => $lng ) );
                  wpgmaps_update_xml_file();
                  echo wpgmza_return_marker_list();
           }
            if ($_POST['action'] == "edit_marker") {
                  $gps = wpgmza_get_lat_long($_POST['address']);
                  $lat = $gps['lat'];
                  $lng = $gps['lng'];
                  $cur_id = $_POST['edit_id'];
                  $rows_affected = $wpdb->query( $wpdb->prepare( "UPDATE $table_name SET address = %s, lat = %f, lng = %f WHERE id = %d", $_POST['address'], $lat, $lng, $cur_id) );
                  wpgmaps_update_xml_file();
                  echo wpgmza_return_marker_list();
           }
            if ($_POST['action'] == "delete_marker") {
                $marker_id = $_POST['marker_id'];
                $wpdb->query(
                        "
                        DELETE FROM $wpgmza_tblname
                        WHERE `id` = '$marker_id'
                        LIMIT 1
                        "
                );
                wpgmaps_update_xml_file();
                echo wpgmza_return_marker_list();

            }
        }

	die(); // this is required to return a proper result

}

function wpgmaps_load_maps_api() {
    wp_enqueue_script('google-maps' , 'http://maps.google.com/maps/api/js?sensor=true' , false , '3');
}

function wpgmaps_tag_basic( $atts ) {
	extract( shortcode_atts( array(
		'id' => 'something'
	), $atts ) );

        $ret_msg = "
            <style>
            #wpgmza_map img { max-width:none !important; }
            </style>
            <div id=\"wpgmza_map\">&nbsp;</div>


        ";
        return $ret_msg;
}

function wpgmaps_get_plugin_url() {
    if ( !function_exists('plugins_url') )
        return get_option('siteurl') . '/wp-content/plugins/' . plugin_basename(dirname(__FILE__));
        return plugins_url(plugin_basename(dirname(__FILE__)));
}

function wpgmaps_head() {


   if (isset($_POST['wpgmza_savemap'])){
       
    $data['map_name'] = attribute_escape($_POST['wpgmza_name']);
    $data['map_height'] = attribute_escape($_POST['wpgmza_height']);
    $data['map_width'] = attribute_escape($_POST['wpgmza_width']);
    $data['map_start_location'] = attribute_escape($_POST['wpgmza_start_location']);
    $data['map_start_zoom'] = intval($_POST['wpgmza_start_zoom']);
    $data['directions_enabled'] = intval($_POST['wpgmza_directions']);
    $gps = wpgmza_get_lat_long($data['map_start_location']);
    $data['map_start_lat'] = $gps['lat'];
    $data['map_start_lng'] = $gps['lng'];
    $data['map_default_marker'] = $_POST['upload_default_marker'];

    update_option('WPGMZA', $data);
    $wpgmza_data = get_option('WPGMZA');
    echo "
    <div class='updated'>
        Your settings have been saved.
    </div>
    ";
   }




}






function wpgmaps_admin_menu() {
    add_menu_page('WP Google Maps', 'WP Google Maps', 'manage_options', 'wp-google-maps-menu', 'wpgmaps_menu_layout', wpgmaps_get_plugin_url()."/images/map_app_small.png");
//    add_options_page('WP Google Maps', 'WP Google Maps', 'manage_options', 'wp-google-maps-menu', 'wpgmaps_menu_layout');
}
function wpgmaps_menu_layout() {

    global $wpgmza_p;


    //check to see if we have write permissions to the plugin folder
    if (!wpgmaps_check_permissions()) { wpgmaps_permission_warning(); }



   if ($wpgmza_p) { wpgmza_pro_menu(); }
   else { wpgmza_basic_menu(); }

}



function wpgmza_basic_menu() {
   $wpgmza_data = get_option('WPGMZA');
   if (!$wpgmza_data['map_id'] || $wpgmza_data['map_id'] == "") { $wpgmza_data['map_id'] = 1; }

   if ($wpgmza_data['map_start_zoom']) { $wpgmza_zoom[$wpgmza_data['map_start_zoom']] = "SELECTED"; } else { $wpgmza_zoom[8] = "SELECTED"; }


    $wpgmza_act = "disabled readonly";
    $wpgmza_act_msg = "<span style=\"font-size:16px; color:#666;\">Add custom icons, descriptions, pictures and links to your markers with the \"<a href=\"http://www.wpgmaps.com/purchase-professional-version/\" title=\"Pro Edition\" target=\"_BLANK\">Pro Edition</a>\" of this plugin for just <strong>$9.99</strong></span>";
    $wpgmza_csv = "<a href=\"http://www.wpgmaps.com/\" title=\"Pro Edition\">Purchase the Pro Edition</a> of WP Google Maps and save your markers to a CSV file!";


    echo "
       <div class='wrap'>
            <h2>WP Google Maps</h2>
            <div class='wide'>
                
                <h3>Create your map</h3>
                <p>Short code: <input type='text' name='shortcode' value='[wpgmza id=\"".$wpgmza_data['map_id']."\"]' /> <small><i>copy this into your post or page to display the map</i></p>
                <form action='' method='post' id='wpgmaps_options'>
                <p></p>

                    <input type='hidden' name='http_referer' value='".$_SERVER['PHP_SELF']."' />
                    <input type='hidden' name='wpgmza_id' id='wpgmza_id' value='".$wpgmza_data['map_id']."' />
                    <input id='wpgmza_start_location' name='wpgmza_start_location' type='hidden' size='40' maxlength='100' value='".$wpgmza_data['map_start_location']."' />
                    <select id='wpgmza_start_zoom' name='wpgmza_start_zoom' hidden>
                        <option value=\"1\" ".$wpgmza_zoom[1].">1</option>
                        <option value=\"2\" ".$wpgmza_zoom[2].">2</option>
                        <option value=\"3\" ".$wpgmza_zoom[3].">3</option>
                        <option value=\"4\" ".$wpgmza_zoom[4].">4</option>
                        <option value=\"5\" ".$wpgmza_zoom[5].">5</option>
                        <option value=\"6\" ".$wpgmza_zoom[6].">6</option>
                        <option value=\"7\" ".$wpgmza_zoom[7].">7</option>
                        <option value=\"8\" ".$wpgmza_zoom[8].">8</option>
                        <option value=\"9\" ".$wpgmza_zoom[9].">9</option>
                        <option value=\"10\" ".$wpgmza_zoom[10].">10</option>
                        <option value=\"11\" ".$wpgmza_zoom[11].">11</option>
                        <option value=\"12\" ".$wpgmza_zoom[12].">12</option>
                        <option value=\"13\" ".$wpgmza_zoom[13].">13</option>
                        <option value=\"14\" ".$wpgmza_zoom[14].">14</option>
                        <option value=\"15\" ".$wpgmza_zoom[15].">15</option>
                        <option value=\"16\" ".$wpgmza_zoom[16].">16</option>
                        <option value=\"17\" ".$wpgmza_zoom[17].">17</option>
                        <option value=\"18\" ".$wpgmza_zoom[18].">18</option>
                        <option value=\"19\" ".$wpgmza_zoom[19].">19</option>
                        <option value=\"20\" ".$wpgmza_zoom[20].">20</option>
                        <option value=\"21\" ".$wpgmza_zoom[21].">21</option>
                    </select>


                        <p>Width: <input id='wpgmza_width' name='wpgmza_width' type='text' size='4' maxlength='4' value='".$wpgmza_data['map_width']."' /> px &nbsp; &nbsp;
                        Height: <input id='wpgmza_height' name='wpgmza_height' type='text' size='4' maxlength='4' value='".$wpgmza_data['map_height']."' /> px</p>


                        <p>Default Marker Image: <input id=\"upload_default_marker\" name=\"upload_default_marker\" type='hidden' size='35' maxlength='700' value='' ".$wpgmza_act."/> <input id=\"upload_default_marker_btn\" type=\"button\" value=\"Upload Image\" $wpgmza_act /><small><i> available in the <a href=\"http://www.wpgmaps.com/purchase-professional-version/\" title=\"Pro Edition\" target=\"_BLANK\">Pro Edition</a> only.   </i></small></p>




                        <p class='submit'><input type='submit' name='wpgmza_savemap' value='Save Map &raquo;' /></p>
                        <div id=\"wpgmaps_save_reminder\" style=\"display:none;\"><span style=\"font-size:16px; color:#1C62B9;\">Remember to save your map!</span></div>
                        <p style=\"width:600px; color:#808080;\">Tip: Use your mouse to change the layout of your map. When you have positioned the map to your desired location, press \"Save Map\" to keep your settings.</p>

                        
                        <div id=\"wpgmza_map\">&nbsp;</div>

                        <h4>Add a marker</h4>
                        <p>
                        <table>
                        <input type=\"hidden\" name=\"wpgmza_edit_id\" id=\"wpgmza_edit_id\" value=\"\" />
                        <tr>
                            <td>Address: </td>
                            <td><input id='wpgmza_add_address' name='wpgmza_add_address' type='text' size='35' maxlength='200' value='' /> &nbsp;<br /></td>
                            
                        </tr>

                        <tr><td>Description: </td><td><input id='wpgmza_add_desc' name='wpgmza_add_desc' type='text' size='35' maxlength='300' value='' ".$wpgmza_act."/>  &nbsp;<br /></td></tr>
                        <tr><td>Pic URL: </td><td><input id='wpgmza_add_pic' name=\"wpgmza_add_pic\" type='text' size='35' maxlength='700' value='' ".$wpgmza_act."/> <input id=\"upload_image_button\" type=\"button\" value=\"Upload Image\" $wpgmza_act /><br /></td></tr>
                        <tr><td>Link URL: </td><td><input id='wpgmza_link_url' name='wpgmza_link_url' type='text' size='35' maxlength='700' value='' ".$wpgmza_act." /></td></tr>
                        <tr><td>Custom Marker: </td><td><input id='wpgmza_add_custom_marker' name=\"wpgmza_add_custom_marker\" type='hidden' size='35' maxlength='700' value='' ".$wpgmza_act."/> <input id=\"upload_custom_marker_button\" type=\"button\" value=\"Upload Image\" $wpgmza_act /> &nbsp;</td></tr>

                        <tr>
                            <td></td>
                            <td>
                                <span id=\"wpgmza_addmarker_div\"><a href='javascript:void(0);' id='wpgmza_addmarker'><big>Add Marker &gt;&gt;</big></a></span> <span id=\"wpgmza_addmarker_loading\" style=\"display:none;\">Adding...</span>
                                <span id=\"wpgmza_editmarker_div\" style=\"display:none;\"><a href='javascript:void(0);' id='wpgmza_editmarker'><big>Save Marker &gt;&gt;</big></a></span><span id=\"wpgmza_editmarker_loading\" style=\"display:none;\">Saving...</span>
                            </td>
                            
                        </tr>

                        </table>
<p>$wpgmza_act_msg</p>
                        <h4>Markers</h4>
                        <div id=\"wpgmza_marker_holder\">
                            ".wpgmza_return_marker_list()."
                        </div>
                        $wpgmza_csv

                        <table>
                            <tr>
                                <td><img src=\"".wpgmaps_get_plugin_url()."/images/custom_markers.jpg\" width=\"160\" style=\"border:3px solid #808080;\" title=\"Add detailed information to your markers!\" alt=\"Add custom markers to your map!\" /><br /><br /></td>
                                <td valign=\"middle\"><span style=\"font-size:18px; color:#666;\">Add detailed information to your markers for only <strong>$9.99</strong>. Click <a href=\"http://www.wpgmaps.com/purchase-professional-version/\" title=\"Pro Edition\" target=\"_BLANK\">here</a></span></td>
                            </tr>
                            <tr>
                                <td><img src=\"".wpgmaps_get_plugin_url()."/images/custom_marker_icons.jpg\" width=\"160\" style=\"border:3px solid #808080;\" title=\"Add custom markers to your map!\" alt=\"Add custom markers to your map!\" /><br /><br /></td>
                                <td valign=\"middle\"><span style=\"font-size:18px; color:#666;\">Add different marker icons, or your own icons to make your map really stand out!</span></td>
                            </tr>
                            <tr>
                                <td><img src=\"".wpgmaps_get_plugin_url()."/images/get_directions.jpg\" width=\"160\" style=\"border:3px solid #808080;\" title=\"Add custom markers to your map!\" alt=\"Add custom markers to your map!\" /><br /><br /></td>
                                <td valign=\"middle\"><span style=\"font-size:18px; color:#666;\">Allow your visitors to get directions to your markers! Click <a href=\"http://www.wpgmaps.com/purchase-professional-version/\" title=\"Pro Edition\" target=\"_BLANK\">here</a></span></td>
                            </tr>
                        </table>

                </form>

                <p>WP Google Maps encourages you to make use of the amazing icons created by Nicolas Mollet's Maps Icons Collection <a href='http://mapicons.nicolasmollet.com'>http://mapicons.nicolasmollet.com/</a></p>
            </div>


        </div>



    ";




}


function my_admin_scripts() {
    wp_enqueue_script('media-upload');
    wp_enqueue_script('thickbox');
    wp_register_script('my-upload', WP_PLUGIN_URL.'/'.plugin_basename(dirname(__FILE__)).'/upload.js', array('jquery','media-upload','thickbox'));
    wp_enqueue_script('my-upload');


}

function my_admin_styles() {
    wp_enqueue_style('thickbox');
}

if (isset($_GET['page']) && $_GET['page'] == 'wp-google-maps-menu') {
    
    add_action('admin_print_scripts', 'my_admin_scripts');
    add_action('admin_print_styles', 'my_admin_styles');
}




function wpgmza_return_marker_list() {
    global $wpdb;
    global $wpgmza_tblname;

    $results = $wpdb->get_results("
	SELECT *
	FROM $wpgmza_tblname
	WHERE `map_id` = '1' ORDER BY `id` DESC
    ");
    $wpgmza_tmp .= "<div style=\"border:1px dashed #666; width:700px; height:300px; display:block; overflow:auto;\"><table width=\"680\" cellspacing=\"5\">";
    $wpgmza_tmp .= "
        <tr>
            <td><strong>ID</strong></td>
            <td><strong>Icon</strong></td>
            <td><strong>Address</strong></td>
            <td><strong>Description</strong></td>
            <td><strong>Image</strong></td>
            <td><strong>Link</strong></td>
            <td><strong>Action</strong></td>
        </tr>";


    $wpgmza_data = get_option('WPGMZA');
    if ($wpgmza_data['map_default_marker']) { $default_icon = "<img src='".$wpgmza_data['map_default_marker']."' />"; } else { $default_icon = "<img src='".wpgmaps_get_plugin_url()."/images/marker.png' />"; }

    foreach ( $results as $result ) {
        $img = $result->pic;
        $link = $result->link;
        $icon = $result->icon;
        
        if (!$img) { $pic = ""; } else { $pic = "<img src=\"".$result->pic."\" width=\"40\" />"; }
        if (!$icon) { $icon = $default_icon; } else { $icon = "<img src='".$result->icon."' />"; }
        if (!$link) { $linktd = ""; } else { $linktd = "<a href=\"".$result->link."\" target=\"_BLANK\" title=\"View this link\">&gt;&gt;</a>"; }
        $wpgmza_tmp .= "
            <tr id=\"wpgmza_tr_".$result->id."\">
                <td height=\"40\">".$result->id."</td>
                <td height=\"40\">".$icon."<input type=\"hidden\" id=\"wpgmza_hid_marker_icon_".$result->id."\" value=\"".$result->icon."\" /></td>
                <td>".$result->address."<input type=\"hidden\" id=\"wpgmza_hid_marker_address_".$result->id."\" value=\"".$result->address."\" /></td>
                <td>".$result->desc."<input type=\"hidden\" id=\"wpgmza_hid_marker_desc_".$result->id."\" value=\"".$result->desc."\" /></td>
                <td>$pic<input type=\"hidden\" id=\"wpgmza_hid_marker_pic_".$result->id."\" value=\"".$result->pic."\" /></td>
                <td>$linktd<input type=\"hidden\" id=\"wpgmza_hid_marker_link_".$result->id."\" value=\"".$result->link."\" /></td>
                <td>
                    <a href=\"javascript:void(0);\" title=\"Edit this marker\" class=\"wpgmza_edit_btn\" id=\"".$result->id."\">Edit</a> |
                    <a href=\"javascript:void(0);\" title=\"Delete this marker\" class=\"wpgmza_del_btn\" id=\"".$result->id."\">Delete</a>
                </td>
            </tr>";
    }
    $wpgmza_tmp .= "</table></div>";

    return $wpgmza_tmp;
}


function wpgmaps_save_postdata( $post_id ) {


  if ( !wp_verify_nonce( $_POST['myplugin_noncename'], plugin_basename(__FILE__) )) {
    return $post_id;
  }

  if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
    return $post_id;

  if ( 'page' == $_POST['post_type'] ) {
    if ( !current_user_can( 'edit_page', $post_id ) )
      return $post_id;
  } else {
    if ( !current_user_can( 'edit_post', $post_id ) )
      return $post_id;
  }


  $mydata = $_POST['myplugin_new_field'];

   return $mydata;
}


function wpgmaps_chmodr($path, $filemode) {
    if (!is_dir($path))
        return chmod($path, $filemode);

    $dh = opendir($path);
    while (($file = readdir($dh)) !== false) {
        if($file != '.' && $file != '..') {
            $fullpath = $path.'/'.$file;
            if(is_link($fullpath))
                return FALSE;
            elseif(!is_dir($fullpath) && !chmod($fullpath, $filemode))
                    return FALSE;
            elseif(!wpgmaps_chmodr($fullpath, $filemode))
                return FALSE;
        }
    }

    closedir($dh);

    if(chmod($path, $filemode))
        return TRUE;
    else
        return FALSE;
}

function wpgmza_get_lat_long($address) {


    define("MAPS_HOST", "maps.google.com");
//    define("KEY", "ABQIAAAA3lby-Oyzu5Elblu1dTk6khRkbnrZzb7DRK_IleYVa0py8MpCNhSBQRpvzubFzk3Bgbu_0aDhfMiAng");
    define("KEY", "AIzaSyABfpdsxk4_64Cdj3gCRlh0HkXXu2hiVBc");
    $base_url = "http://" . MAPS_HOST . "/maps/geo?output=xml" . "&key=" . KEY;


    $geocode_pending = true;



    while ($geocode_pending) {

        $request_url = $base_url . "&q=" . urlencode($address);


        //$xml = simplexml_load_file($request_url) or die("url not loading");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // get the result of http query
        $output = curl_exec($ch);
        curl_close($ch);
        $xml = simplexml_load_string($output) or die("There was a problem connecting to the Google API. Please ensure that cURL is enabled on your server.");

        $status = $xml->Response->Status->code;
        
        if (strcmp($status, "200") == 0) {
          // Successful geocode
          $geocode_pending = false;
          $coordinates = $xml->Response->Placemark->Point->coordinates;
          $coordinatesSplit = split(",", $coordinates);
          // Format: Longitude, Latitude, Altitude
          $gps['lat'] = $coordinatesSplit[1];
          $gps['lng'] = $coordinatesSplit[0];
          return $gps;

        } else if (strcmp($status, "620") == 0) {

          // sent geocodes too fast

          $delay += 100000;

    } else {

          // failure to geocode

          $geocode_pending = false;

          echo "Address " . $address . " failed to geocoded. ";

          echo "Received status " . $status . "

        \n";

    }

    usleep($delay);


}





}


if ($wpgmza_p) {
    add_action('admin_head', 'wpgmaps_admin_javascript_pro');
    add_action('wp_ajax_add_marker', 'wpgmaps_action_callback_pro');
    add_action('wp_ajax_delete_marker', 'wpgmaps_action_callback_pro');
    add_action('wp_ajax_edit_marker', 'wpgmaps_action_callback_pro');
    add_action('wp_head', 'wpgmaps_user_javascript_pro');
    add_shortcode( 'wpgmza', 'wpgmaps_tag_pro' );
} else {
    add_action('admin_head', 'wpgmaps_admin_javascript_basic');
    add_action('wp_ajax_add_marker', 'wpgmaps_action_callback_basic');
    add_action('wp_ajax_delete_marker', 'wpgmaps_action_callback_basic');
    add_action('wp_ajax_edit_marker', 'wpgmaps_action_callback_basic');
    add_action('wp_head', 'wpgmaps_user_javascript_basic');
    add_shortcode( 'wpgmza', 'wpgmaps_tag_basic' );
}


function wpgmza_cURL_response($action) {
    if (function_exists('curl_version')) {
        global $wpgmza_version;
        global $wpgmza_t;
        $request_url = "http://www.wpgmaps.com/api/rec.php?action=$action&dom=".$_SERVER['HTTP_HOST']."&ver=".$wpgmza_version.$wpgmza_t;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
    }

}

function wpgmaps_check_permissions() {
    $filename = dirname( __FILE__ ).'/wpgmaps.tmp';
    $testcontent = "Permission Check\n";
    $handle = @fopen($filename, 'w');
    if (@fwrite($handle, $testcontent) === FALSE) {
        @fclose($handle);
        add_option("wpgmza_permission","n");
        return false;
    }
    else {
        @fclose($handle);
        add_option("wpgmza_permission","y");
        return true;
    }


}
function wpgmaps_permission_warning() {
    echo "<div class='error below-h1'>The plugin directory does not have 'write' permissions. Please enable 'write' permissions (755) for \"".dirname( __FILE__ )."\" in order for this plugin to work! Please see <a href='http://codex.wordpress.org/Changing_File_Permissions#Using_an_FTP_Client'>this page</a> for help on how to do it.</div>";
}


// handle database check upon upgrade
function wpgmaps_update_db_check() {
    global $wpgmza_version;
    if (get_option('wpgmza_db_version') != $wpgmza_version) {
        wpgmaps_handle_db();
    }
}
add_action('plugins_loaded', 'wpgmaps_update_db_check');


function wpgmaps_handle_db() {
   global $wpdb;
   global $wpgmza_version;

   $table_name = $wpdb->prefix . "wpgmza";

    $sql = "
        CREATE TABLE `".$table_name."` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `map_id` int(11) NOT NULL,
          `address` varchar(700) NOT NULL,
          `desc` varchar(700) NOT NULL,
          `pic` varchar(700) NOT NULL,
          `link` varchar(700) NOT NULL,
          `icon` varchar(700) NOT NULL,
          `lat` varchar(100) NOT NULL,
          `lng` varchar(100) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
    ";

   require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   dbDelta($sql);

   add_option("wpgmza_db_version", $wpgmza_version);
}

?>