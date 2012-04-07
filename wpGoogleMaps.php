<?php
/*
Plugin Name: WP Google Maps
Plugin URI: http://www.wpgmaps.com
Description: The easiest to use Google Maps plugin! Create custom Google Maps with high quality markers containing locations, descriptions, images and links. Add your customized map to your WordPress posts and/or pages quickly and easily with the supplied shortcode. No fuss.
Version: 4.6
Author: WP Google Maps
Author URI: http://www.wpgmaps.com
*/

global $wpgmza_version;
global $wpgmza_p_version;
global $wpgmza_t;
global $wpgmza_tblname;
global $wpgmza_tblname_maps;
global $wpdb;
global $wpgmza_p;
global $wpgmza_g;
global $short_code_active;
global $wpgmza_current_map_id;
global $debug;
global $debug_step;
global $debug_start;
$debug = false;
$debug_step = 0;
$wpgmza_p = false;
$wpgmza_g = false;
$wpgmza_tblname = $wpdb->prefix . "wpgmza";
$wpgmza_tblname_maps = $wpdb->prefix . "wpgmza_maps";
$wpgmza_version = "4.6";
$wpgmza_p_version = "4.6";
$wpgmza_t = "basic";

add_action('admin_head', 'wpgmaps_head');
add_action('admin_footer', 'wpgmaps_reload_map_on_post');
register_activation_hook( __FILE__, 'wpgmaps_activate' );
register_deactivation_hook( __FILE__, 'wpgmaps_deactivate' );
add_action('init', 'wpgmaps_init');
add_action('save_post', 'wpgmaps_save_postdata');
add_action('admin_menu', 'wpgmaps_admin_menu');
add_filter('widget_text', 'do_shortcode');

$debug_start = (float) array_sum(explode(' ',microtime()));



function wpgmaps_activate() {
    global $wpdb;
    global $wpgmza_version;
    $table_name = $wpdb->prefix . "wpgmza";
    $table_name_maps = $wpdb->prefix . "wpgmza_maps";

    wpgmaps_debugger("activate_start");


    wpgmaps_handle_db();

    $wpgmza_data = get_option("WPGMZA");
    if (!$wpgmza_data) {
        // load first map as an example map (i.e. if the user has not installed this plugin before, this must run
        $res_maps = $wpdb->get_results("SELECT * FROM $table_name_maps");
        $wpdb->show_errors();
        if (!$res_maps) { $rows_affected = $wpdb->insert( $table_name_maps, array(
                                                                    "map_title" => "Your first map",
                                                                    "map_start_lat" => "51.5081290",
                                                                    "map_start_lng" => "-0.1280050",
                                                                    "map_width" => "600",
                                                                    "map_height" => "400",
                                                                    "map_start_location" => "51.5081290,-0.1280050",
                                                                    "map_start_zoom" => "5",
                                                                    "directions_enabled" => '1',
                                                                    "default_marker" => "0",
                                                                    "alignment" => "0",
                                                                    "styling_enabled" => "0",
                                                                    "styling_json" => "",
                                                                    "active" => "0",
                                                                    "type" => "1")
                                                                    ); }
    } else {
        $rows_affected = $wpdb->insert( $table_name_maps, array(    "map_start_lat" => "".$wpgmza_data['map_start_lat']."",
                                                                    "map_start_lng" => "".$wpgmza_data['map_start_lng']."",
                                                                    "map_title" => "Your Map",
                                                                    "map_width" => "".$wpgmza_data['map_width']."",
                                                                    "map_height" => "".$wpgmza_data['map_height']."",
                                                                    "map_start_location" => "".$wpgmza_data['map_start_lat'].",".$wpgmza_data['map_start_lng']."",
                                                                    "map_start_zoom" => "".$wpgmza_data['map_start_zoom']."",
                                                                    "default_marker" => "".$wpgmza_data['map_default_marker']."",
                                                                    "type" => "".$wpgmza_data['map_type']."",
                                                                    "alignment" => "".$wpgmza_data['map_align']."",
                                                                    "styling_enabled" => "0",
                                                                    "styling_json" => "",
                                                                    "active" => "0",
                                                                    "directions_enabled" => "".$wpgmza_data['directions_enabled'].""
                                                                ) );
        delete_option("WPGMZA");

    }
    // load first marker as an example marker
    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE `map_id` = '1'");
    if (!$results) { $rows_affected = $wpdb->insert( $table_name, array( 'map_id' => '1', 'address' => 'London', 'lat' => '51.5081290', 'lng' => '-0.1280050' ) ); }




    wpgmza_cURL_response("activate");
    //check to see if you have write permissions to the plugin folder (version 2.2)
    if (!wpgmaps_check_permissions()) { wpgmaps_permission_warning(); } else { wpgmaps_update_all_xml_file(); }
    wpgmaps_debugger("activate_end");
}
function wpgmaps_deactivate() { wpgmza_cURL_response("deactivate"); }
function wpgmaps_init() { wp_enqueue_script("jquery"); }

function wpgmaps_reload_map_on_post() {
    wpgmaps_debugger("reload_map_start");
    if (isset($_POST['wpgmza_savemap'])){

        $res = wpgmza_get_map_data($_GET['map_id']);
        $wpgmza_lat = $res->map_start_lat;
        $wpgmza_lng = $res->map_start_lng;
        $wpgmza_width = $res->map_width;
        $wpgmza_height = $res->map_height;
        $wpgmza_map_type = $res->type;
        if (!$wpgmza_map_type || $wpgmza_map_type == "" || $wpgmza_map_type == "1") { $wpgmza_map_type = "ROADMAP"; }
        else if ($wpgmza_map_type == "2") { $wpgmza_map_type = "SATELLITE"; }
        else if ($wpgmza_map_type == "3") { $wpgmza_map_type = "HYBRID"; }
        else if ($wpgmza_map_type == "4") { $wpgmza_map_type = "TERRAIN"; }
        else { $wpgmza_map_type = "ROADMAP"; }
        $start_zoom = $res->map_start_zoom;
        if ($start_zoom < 1 || !$start_zoom) { $start_zoom = 5; }
        if (!$wpgmza_lat || !$wpgmza_lng) { $wpgmza_lat = "51.5081290"; $wpgmza_lng = "-0.1280050"; }

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
            MYMAP.placeMarkers('<?php echo wpgmaps_get_marker_url($_GET['map_id']); ?>?u='+UniqueCode,<?php echo $_GET['map_id']; ?>);
            
        });
        </script>
        <?php
    }
    wpgmaps_debugger("reload_map_end");


}
function wpgmaps_get_marker_url($mapid) {

    if (!$mapid) {
        $mapid = $_POST['map_id'];
    }
    if (!$mapid) {
        $mapid = $_GET['map_id'];
    }
    if (!$mapid) {
        global $wpgmza_current_map_id;
        $mapid = $wpgmza_current_map_id;
    }

    if (is_multisite()) {
        global $blog_id;
        return wpgmaps_get_plugin_url()."/".$blog_id."-".$mapid."markers.xml";
    } else {
        return wpgmaps_get_plugin_url()."/".$mapid."markers.xml";
    }



}


function wpgmaps_admin_edit_marker_javascript() {
    wpgmaps_debugger("edit_marker_start");

    $res = wpgmza_get_marker_data($_GET['id']);
        $wpgmza_lat = $res->lat;
        $wpgmza_lng = $res->lng;
        $wpgmza_map_type = "ROADMAP";


        ?>
        <script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
        <link rel='stylesheet' id='wpgooglemaps-css'  href='<?php echo wpgmaps_get_plugin_url(); ?>/css/wpgmza_style.css' type='text/css' media='all' />
        <link rel="stylesheet" type="text/css" media="all" href="<?php echo wpgmaps_get_plugin_url(); ?>/css/data_table.css" />
        <script type="text/javascript" src="<?php echo wpgmaps_get_plugin_url(); ?>/js/jquery.dataTables.js"></script>
        <script type="text/javascript" >
            jQuery(document).ready(function(){
                    function wpgmza_InitMap() {
                        var myLatLng = new google.maps.LatLng(<?php echo $wpgmza_lat; ?>,<?php echo $wpgmza_lng; ?>);
                        MYMAP.init('#wpgmza_map', myLatLng, 15);
                    }
                    jQuery("#wpgmza_map").css({
                        height:400,
                        width:400
                    });
                    wpgmza_InitMap();
            });

            var MYMAP = {
                map: null,
                bounds: null
            }
            MYMAP.init = function(selector, latLng, zoom) {
                  var myOptions = {
                    zoom:zoom,
                    center: latLng,
                    mapTypeId: google.maps.MapTypeId.<?php echo $wpgmza_map_type; ?>
                  }
                this.map = new google.maps.Map(jQuery(selector)[0], myOptions);
                this.bounds = new google.maps.LatLngBounds();

                updateMarkerPosition(latLng);


                var marker = new google.maps.Marker({
                    position: latLng,
                    map: this.map,
                    draggable: true
                });
                google.maps.event.addListener(marker, 'drag', function() {
                    updateMarkerPosition(marker.getPosition());
                });
            }
            function updateMarkerPosition(latLng) {
                jQuery("#wpgmaps_marker_lat").val(latLng.lat());
                jQuery("#wpgmaps_marker_lng").val(latLng.lng());
            }


        </script>
        <?php

    wpgmaps_debugger("edit_marker_end");

}

function wpgmaps_admin_javascript_basic() {
    global $wpdb;
    global $wpgmza_tblname_maps;
    $ajax_nonce = wp_create_nonce("wpgmza");
    wpgmaps_debugger("admin_js_basic_start");

    if (is_admin() && $_GET['page'] == 'wp-google-maps-menu' && $_GET['action'] == "edit_marker") {
        wpgmaps_admin_edit_marker_javascript();

    }

    else if (is_admin() && $_GET['page'] == 'wp-google-maps-menu' && $_GET['action'] == "edit") {

        if ($debug) { echo ""; }

        if (!$_GET['map_id']) { break; }
        wpgmaps_update_xml_file($_GET['map_id']);
        //$wpgmza_data = get_option('WPGMZA');

        $res = wpgmza_get_map_data($_GET['map_id']);

        $wpgmza_lat = $res->map_start_lat;
        $wpgmza_lng = $res->map_start_lng;
        $wpgmza_width = $res->map_width;
        $wpgmza_height = $res->map_height;
        $wpgmza_map_type = $res->type;
        if (!$wpgmza_map_type || $wpgmza_map_type == "" || $wpgmza_map_type == "1") { $wpgmza_map_type = "ROADMAP"; }
        else if ($wpgmza_map_type == "2") { $wpgmza_map_type = "SATELLITE"; }
        else if ($wpgmza_map_type == "3") { $wpgmza_map_type = "HYBRID"; }
        else if ($wpgmza_map_type == "4") { $wpgmza_map_type = "TERRAIN"; }
        else { $wpgmza_map_type = "ROADMAP"; }
        $start_zoom = $res->map_start_zoom;
        if ($start_zoom < 1 || !$start_zoom) { $start_zoom = 5; }
        if (!$wpgmza_lat || !$wpgmza_lng) { $wpgmza_lat = "51.5081290"; $wpgmza_lng = "-0.1280050"; }       


    ?>
    <script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
    <link rel='stylesheet' id='wpgooglemaps-css'  href='<?php echo wpgmaps_get_plugin_url(); ?>/css/wpgmza_style.css' type='text/css' media='all' />
    <link rel="stylesheet" type="text/css" media="all" href="<?php echo wpgmaps_get_plugin_url(); ?>/css/data_table.css" />
    <script type="text/javascript" src="<?php echo wpgmaps_get_plugin_url(); ?>/js/jquery.dataTables.js"></script>
    <script type="text/javascript" >
    jQuery(function() {


                jQuery(document).ready(function(){
                    wpgmzaTable = jQuery('#wpgmza_table').dataTable({
                        "bProcessing": true,
                        "aaSorting": [[ 0, "desc" ]]
                    });
                    function wpgmza_reinitialisetbl() {
                        wpgmzaTable.fnClearTable( 0 );
                        wpgmzaTable = jQuery('#wpgmza_table').dataTable({
                            "bProcessing": true
                        });
                    }
                    function wpgmza_InitMap() {
                        var myLatLng = new google.maps.LatLng(<?php echo $wpgmza_lat; ?>,<?php echo $wpgmza_lng; ?>);
                        MYMAP.init('#wpgmza_map', myLatLng, <?php echo $start_zoom; ?>);
                        UniqueCode=Math.round(Math.random()*10000);
                        MYMAP.placeMarkers('<?php echo wpgmaps_get_plugin_url(); ?>/markers.xml?u='+UniqueCode,<?php echo $_GET['map_id']; ?>);
                    }

                    jQuery("#wpgmza_map").css({
                        height:<?php echo $wpgmza_height; ?>,
                        width:<?php echo $wpgmza_width; ?>

                    });
                    var geocoder = new google.maps.Geocoder();
                    wpgmza_InitMap();


                    
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
                                wpgmza_InitMap();
                                jQuery("#wpgmza_marker_holder").html(response);
                                wpgmza_reinitialisetbl();
                                //jQuery("#wpgmza_tr_"+cur_id).css("display","none");
                        });
                                

                    });


                    jQuery(".wpgmza_edit_btn").live("click", function() {
                        var cur_id = jQuery(this).attr("id");
                        var wpgmza_edit_address = jQuery("#wpgmza_hid_marker_address_"+cur_id).val();
                        var wpgmza_edit_title = jQuery("#wpgmza_hid_marker_title_"+cur_id).val();
                        jQuery("#wpgmza_edit_id").val(cur_id);
                        jQuery("#wpgmza_add_address").val(wpgmza_edit_address);
                        jQuery("#wpgmza_add_title").val(wpgmza_edit_title);
                        jQuery("#wpgmza_addmarker_div").hide();
                        jQuery("#wpgmza_editmarker_div").show();
                    });

                    

                    jQuery("#wpgmza_addmarker").click(function(){
                        jQuery("#wpgmza_addmarker").hide();
                        jQuery("#wpgmza_addmarker_loading").show();

                        var wpgm_address = "0";
                        var wpgm_gps = "0";
                        var wpgm_map_id = "0";
                        if (document.getElementsByName("wpgmza_add_address").length > 0) { wpgm_address = jQuery("#wpgmza_add_address").val(); }
                        if (document.getElementsByName("wpgmza_id").length > 0) { wpgm_map_id = jQuery("#wpgmza_id").val(); }

                        geocoder.geocode( { 'address': wpgm_address}, function(results, status) {
                            if (status == google.maps.GeocoderStatus.OK) {
                                wpgm_gps = String(results[0].geometry.location);
                                var latlng1 = wpgm_gps.replace("(","");
                                var latlng2 = latlng1.replace(")","");
                                var latlngStr = latlng2.split(",",2);
                                var wpgm_lat = parseFloat(latlngStr[0]);
                                var wpgm_lng = parseFloat(latlngStr[1]);

                                var data = {
                                    action: 'add_marker',
                                    security: '<?php echo $ajax_nonce; ?>',
                                    map_id: wpgm_map_id,
                                    address: wpgm_address,
                                    lat: wpgm_lat,
                                    lng: wpgm_lng
                                };
                                        
                                jQuery.post(ajaxurl, data, function(response) {
                                        wpgmza_InitMap();
                                        jQuery("#wpgmza_marker_holder").html(response);
                                        jQuery("#wpgmza_addmarker").show();
                                        jQuery("#wpgmza_addmarker_loading").hide();
                                        wpgmza_reinitialisetbl();
                                });
                                
                            } else {
                                alert("Geocode was not successful for the following reason: " + status);
                            }
                        });
                        

                    });


                    jQuery("#wpgmza_editmarker").click(function(){
                    
                        jQuery("#wpgmza_editmarker_div").hide();
                        jQuery("#wpgmza_editmarker_loading").show();


                        var wpgm_edit_id;
                        wpgm_edit_id = parseInt(jQuery("#wpgmza_edit_id").val());
                        var wpgm_address = "0";
                        var wpgm_map_id = "0";
                        var wpgm_gps = "0";
                        if (document.getElementsByName("wpgmza_add_address").length > 0) { wpgm_address = jQuery("#wpgmza_add_address").val(); }
                        if (document.getElementsByName("wpgmza_id").length > 0) { wpgm_map_id = jQuery("#wpgmza_id").val(); }

                        
                        geocoder.geocode( { 'address': wpgm_address}, function(results, status) {
                            if (status == google.maps.GeocoderStatus.OK) {
                                wpgm_gps = String(results[0].geometry.location);
                                var latlng1 = wpgm_gps.replace("(","");
                                var latlng2 = latlng1.replace(")","");
                                var latlngStr = latlng2.split(",",2);
                                var wpgm_lat = parseFloat(latlngStr[0]);
                                var wpgm_lng = parseFloat(latlngStr[1]);

                                var data = {
                                    action: 'edit_marker',
                                    security: '<?php echo $ajax_nonce; ?>',
                                    map_id: wpgm_map_id,
                                    edit_id: wpgm_edit_id,
                                    address: wpgm_address,
                                    lat: wpgm_lat,
                                    lng: wpgm_lng
                                };

                                jQuery.post(ajaxurl, data, function(response) {
                                    wpgmza_InitMap();
                                    jQuery("#wpgmza_add_address").val("");
                                    jQuery("#wpgmza_add_title").val("");
                                    jQuery("#wpgmza_marker_holder").html(response);
                                    jQuery("#wpgmza_addmarker_div").show();
                                    jQuery("#wpgmza_editmarker_loading").hide();
                                    jQuery("#wpgmza_edit_id").val("");
                                    wpgmza_reinitialisetbl();
                                });

                            } else {
                                alert("Geocode was not successful for the following reason: " + status);
                            }
                        });



                    });
            });

            });

            
            
            var MYMAP = {
                map: null,
                bounds: null
            }
            MYMAP.init = function(selector, latLng, zoom) {
                  var myOptions = {
                    zoom:zoom,
                    center: latLng,
                    mapTypeId: google.maps.MapTypeId.<?php echo $wpgmza_map_type; ?>
                  }
                this.map = new google.maps.Map(jQuery(selector)[0], myOptions);
                this.bounds = new google.maps.LatLngBounds();


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


            }


            MYMAP.placeMarkers = function(filename,map_id) {
                marker_array = [];
                    jQuery.get(filename, function(xml){
                            jQuery(xml).find("marker").each(function(){
                                    var wpmgza_map_id = jQuery(this).find('map_id').text();
                                    if (wpmgza_map_id == map_id) {
                                        var wpmgza_address = jQuery(this).find('address').text();
                                        var lat = jQuery(this).find('lat').text();
                                        var lng = jQuery(this).find('lng').text();
                                        var point = new google.maps.LatLng(parseFloat(lat),parseFloat(lng));
                                        MYMAP.bounds.extend(point);
                                        var marker = new google.maps.Marker({
                                                position: point,
                                                map: MYMAP.map


                                        });
                                        var infoWindow = new google.maps.InfoWindow();
                                        var html='<strong>'+wpmgza_address+'</strong>';

                                        google.maps.event.addListener(marker, 'click', function() {
                                                infoWindow.setContent(html);
                                                infoWindow.open(MYMAP.map, marker);
                                        });
                                        //MYMAP.map.fitBounds(MYMAP.bounds);

                                    }

                            });
                    });
            }

            


            

        </script>
<?php
}
    wpgmaps_debugger("admin_js_basic_end");

}


function wpgmaps_user_javascript_basic() {
    global $short_code_active;
    global $wpgmza_current_map_id;
    wpgmaps_debugger("u_js_b_start");

    if ($short_code_active) { 

        $ajax_nonce = wp_create_nonce("wpgmza");


        $res = wpgmza_get_map_data($wpgmza_current_map_id);
        

        $wpgmza_lat = $res->map_start_lat;
        $wpgmza_lng = $res->map_start_lng;
        $wpgmza_width = $res->map_width;
        $wpgmza_height = $res->map_height;
        $wpgmza_map_type = $res->type;
        if (!$wpgmza_map_type || $wpgmza_map_type == "" || $wpgmza_map_type == "1") { $wpgmza_map_type = "ROADMAP"; }
        else if ($wpgmza_map_type == "2") { $wpgmza_map_type = "SATELLITE"; }
        else if ($wpgmza_map_type == "3") { $wpgmza_map_type = "HYBRID"; }
        else if ($wpgmza_map_type == "4") { $wpgmza_map_type = "TERRAIN"; }
        else { $wpgmza_map_type = "ROADMAP"; }
        $start_zoom = $res->map_start_zoom;
        if ($start_zoom < 1 || !$start_zoom) { $start_zoom = 5; }
        if (!$wpgmza_lat || !$wpgmza_lng) { $wpgmza_lat = "51.5081290"; $wpgmza_lng = "-0.1280050"; }

        ?>
        <script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
        <link rel='stylesheet' id='wpgooglemaps-css'  href='<?php echo wpgmaps_get_plugin_url(); ?>/css/wpgmza_style.css' type='text/css' media='all' />
        <script type="text/javascript" >
        

        jQuery(function() {



        jQuery(document).ready(function(){


            jQuery("#wpgmza_map").css({
                height:<?php echo $wpgmza_height; ?>,
                width:<?php echo $wpgmza_width; ?>

            });
            var myLatLng = new google.maps.LatLng(<?php echo $wpgmza_lat; ?>,<?php echo $wpgmza_lng; ?>);
            MYMAP.init('#wpgmza_map', myLatLng, <?php echo $start_zoom; ?>);
            UniqueCode=Math.round(Math.random()*10000);
            MYMAP.placeMarkers('<?php echo wpgmaps_get_plugin_url(); ?>/markers.xml?u='+UniqueCode,<?php echo $wpgmza_current_map_id; ?>);


            });

        });
        var MYMAP = {
            map: null,
            bounds: null
        }
        MYMAP.init = function(selector, latLng, zoom) {
          var myOptions = {
            zoom:zoom,
            center: latLng,
            mapTypeId: google.maps.MapTypeId.<?php echo $wpgmza_map_type; ?>
          }

          this.map = new google.maps.Map(jQuery(selector)[0], myOptions);
          this.bounds = new google.maps.LatLngBounds();

        }



        MYMAP.placeMarkers = function(filename,map_id) {

            jQuery.get(filename, function(xml){
                    jQuery(xml).find("marker").each(function(){
                                    var wpmgza_map_id = jQuery(this).find('map_id').text();

                                    if (wpmgza_map_id == map_id) {
                                        var wpmgza_address = jQuery(this).find('address').text();
                                        var lat = jQuery(this).find('lat').text();
                                        var lng = jQuery(this).find('lng').text();

                                        var point = new google.maps.LatLng(parseFloat(lat),parseFloat(lng));
                                        MYMAP.bounds.extend(point);
                                        var marker = new google.maps.Marker({
                                                position: point,
                                                map: MYMAP.map
                                            });
                                        var infoWindow = new google.maps.InfoWindow();
                                        var html='<strong>'+wpmgza_address+'</strong>';

                                        google.maps.event.addListener(marker, 'click', function(evt) {
                                                infoWindow.setContent(html);
                                                infoWindow.open(MYMAP.map, marker);

                                        });
                                    }
                    });
            });
    }

        </script>
<?php
    }
    wpgmaps_debugger("u_js_b_end");
}




function wpgmaps_update_xml_file($mapid) {

    wpgmaps_debugger("update_xml_start");


    if (!$mapid) {
        $mapid = $_POST['map_id'];
    }
    if (!$mapid) {
        $mapid = $_GET['map_id'];
    }
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
        WHERE `map_id` = '$mapid'
	
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
        $anim = $result->anim;
        $mtitle = $result->title;
        $map_id = $result->map_id;

        $channel = $channel_main->appendChild($dom->createElement('marker'));
        $title = $channel->appendChild($dom->createElement('map_id'));
        $title->appendChild($dom->CreateTextNode($map_id));
        $title = $channel->appendChild($dom->createElement('title'));
        $title->appendChild($dom->CreateTextNode($mtitle));
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
        $bd = $channel->appendChild($dom->createElement('anim'));
        $bd->appendChild($dom->CreateTextNode($anim));

        
    }
    if (is_multisite()) {
        global $blog_id;
        @$dom->save(WP_PLUGIN_DIR.'/'.plugin_basename(dirname(__FILE__)).'/'.$blog_id.'-'.$mapid.'markers.xml');
    } else {

        @$dom->save(WP_PLUGIN_DIR.'/'.plugin_basename(dirname(__FILE__)).'/'.$mapid.'markers.xml');
    }
    wpgmaps_debugger("update_xml_end");




}



function wpgmaps_update_all_xml_file($mapid) {
    // create all XML files
    wpgmaps_debugger("update_all_xml_start");

    global $wpdb;
    $table_name = $wpdb->prefix . "wpgmza";
    $results = $wpdb->get_results(
	"
	SELECT *
	FROM $table_name GROUP BY `map_id`
	"
    );

    foreach ( $results as $result ) {
        $map_id = $result->map_id;
        wpgmaps_update_xml_file($map_id);
    }

    wpgmaps_debugger("update_all_xml_end");


}



function wpgmaps_action_callback_basic() {
        global $wpdb;
        global $wpgmza_tblname;
        global $wpgmza_p;
        $check = check_ajax_referer( 'wpgmza', 'security' );
        $table_name = $wpdb->prefix . "wpgmza";

        if ($check == 1) {

            if ($_POST['action'] == "add_marker") {
                  $rows_affected = $wpdb->insert( $table_name, array( 'map_id' => $_POST['map_id'], 'address' => $_POST['address'], 'lat' => $_POST['lat'], 'lng' => $_POST['lng'] ) );
                  wpgmaps_update_xml_file($_POST['map_id']);
                  echo wpgmza_return_marker_list($_POST['map_id']);
           }
            if ($_POST['action'] == "edit_marker") {
                  $cur_id = $_POST['edit_id'];
                  $rows_affected = $wpdb->query( $wpdb->prepare( "UPDATE $table_name SET address = %s, lat = %f, lng = %f WHERE id = %d", $_POST['address'], $_POST['lat'], $_POST['lng'], $cur_id) );
                  wpgmaps_update_xml_file($_POST['map_id']);
                  echo wpgmza_return_marker_list($_POST['map_id']);
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
                wpgmaps_update_xml_file($_POST['map_id']);
                echo wpgmza_return_marker_list($_POST['map_id']);

            }
        }

	die(); // this is required to return a proper result

}

function wpgmaps_load_maps_api() {
    wpgmaps_debugger("load_maps_api_start");
    wp_enqueue_script('google-maps' , 'http://maps.google.com/maps/api/js?sensor=true' , false , '3');
    wpgmaps_debugger("load_maps_api_end");
}

function wpgmaps_tag_basic( $atts ) {
        wpgmaps_debugger("tag_basic_start");
        global $wpgmza_current_map_id;
        extract( shortcode_atts( array(
		'id' => '1'
	), $atts ) );
        
        $wpgmza_current_map_id = $atts['id'];

        $res = wpgmza_get_map_data($atts['id']);

        //$wpgmza_data = get_option('WPGMZA');
        $map_align = $res->alignment;

        

        if (!$map_align || $map_align == "" || $map_align == "1") { $map_align = "float:left;"; }
        else if ($map_align == "2") { $map_align = "margin-left:auto !important; margin-right:auto; !important; align:center;"; }
        else if ($map_align == "3") { $map_align = "float:right;"; }
        else if ($map_align == "4") { $map_align = ""; }
        $map_style = "style=\"display:block; width:".$res->map_width."px; height:".$res->map_height."px; $map_align\"";
        

        $ret_msg = "
            <style>
            #wpgmza_map img { max-width:none !important; }
            </style>
            <div id=\"wpgmza_map\" $map_style>&nbsp;</div>


        ";
        return $ret_msg;
        wpgmaps_debugger("tag_basic_end");
}

function wpgmaps_get_plugin_url() {
    if ( !function_exists('plugins_url') )
        return get_option('siteurl') . '/wp-content/plugins/' . plugin_basename(dirname(__FILE__));
        return plugins_url(plugin_basename(dirname(__FILE__)));
}

function wpgmaps_head() {
    wpgmaps_debugger("head_start");

    global $wpgmza_tblname_maps;

    if (isset($_POST['wpgmza_savemap'])){
        global $wpdb;

        $map_id = attribute_escape($_POST['wpgmza_id']);
        $map_title = attribute_escape($_POST['wpgmza_title']);
        $map_height = attribute_escape($_POST['wpgmza_height']);
        $map_width = attribute_escape($_POST['wpgmza_width']);
        $map_start_location = attribute_escape($_POST['wpgmza_start_location']);
        $map_start_zoom = intval($_POST['wpgmza_start_zoom']);
        $type = intval($_POST['wpgmza_map_type']);
        $alignment = intval($_POST['wpgmza_map_align']);
        $directions_enabled = intval($_POST['wpgmza_directions']);
        $gps = explode(",",$map_start_location);
        $map_start_lat = $gps[0];
        $map_start_lng = $gps[1];
        $map_default_marker = $_POST['upload_default_marker'];


        $rows_affected = $wpdb->query( $wpdb->prepare(
                "UPDATE $wpgmza_tblname_maps SET
                map_title = %s,
                map_width = %s,
                map_height = %s,
                map_start_lat = %f,
                map_start_lng = %f,
                map_start_location = %s,
                map_start_zoom = %d,
                default_marker = %s,
                type = %d,
                alignment = %d,
                directions_enabled = %d
                WHERE id = %d",

                $map_title,
                $map_width,
                $map_height,
                $map_start_lat,
                $map_start_lng,
                $map_start_location,
                $map_start_zoom,
                $map_default_marker,
                $type,
                $alignment,
                $directions_enabled,
                $map_id)
        );




        //update_option('WPGMZA', $data);
        echo "
        <div class='updated'>
            Your settings have been saved.
        </div>
        ";
   }

   else if (isset($_POST['wpgmza_save_maker_location'])){
        global $wpdb;
        global $wpgmza_tblname;
        $mid = attribute_escape($_POST['wpgmaps_marker_id']);
        $wpgmaps_marker_lat = attribute_escape($_POST['wpgmaps_marker_lat']);
        $wpgmaps_marker_lng = attribute_escape($_POST['wpgmaps_marker_lng']);

        $rows_affected = $wpdb->query( $wpdb->prepare(
                "UPDATE $wpgmza_tblname SET
                lat = %s,
                lng = %s
                WHERE id = %d",

                $wpgmaps_marker_lat,
                $wpgmaps_marker_lng,
                $mid)
        );




        //update_option('WPGMZA', $data);
        echo "
        <div class='updated'>
            Your marker location has been saved.
        </div>
        
        ";
   }



    wpgmaps_debugger("head_end");

}






function wpgmaps_admin_menu() {
    add_menu_page('WPGoogle Maps', 'Maps', 'manage_options', 'wp-google-maps-menu', 'wpgmaps_menu_layout', wpgmaps_get_plugin_url()."/images/map_app_small.png");
    if (function_exists(wpgmza_pro_advanced_menu)) {
        add_submenu_page('wp-google-maps-menu', 'WP Google Maps - Advanced Options', 'Advanced', 'manage_options' , 'wp-google-maps-menu-advanced', 'wpgmaps_menu_advanced_layout');
    }

//    add_options_page('WP Google Maps', 'WP Google Maps', 'manage_options', 'wp-google-maps-menu', 'wpgmaps_menu_layout');
}
function wpgmaps_menu_layout() {
    //check to see if we have write permissions to the plugin folder
    //
    //
    wpgmaps_debugger("menu_start");
    

    if (!$_GET['action']) { 
        
        wpgmza_map_page();

    } else {


        if ($_GET['action'] == "trash" && isset($_GET['map_id'])) {

            if ($_GET['s'] == "1") {
                if (wpgmaps_trash_map($_GET['map_id'])) {
                    //wp_redirect( admin_url('admin.php?page=wp-google-maps-menu') );
                    echo "<script>window.location = \"".get_option('siteurl')."/wp-admin/admin.php?page=wp-google-maps-menu\"</script>";
                } else {
                    echo "There was a problem deleting the map.";
                }
            } else {
                $res = wpgmza_get_map_data($_GET['map_id']);
                echo "<h2>Delete your map</h2><p>Are you sure you want to delete the map <strong>\"".$res->map_title."?\"</strong> <br /><a href='?page=wp-google-maps-menu&action=trash&map_id=".$_GET['map_id']."&s=1'>Yes</a> | <a href='?page=wp-google-maps-menu'>No</a></p>";
            }


        }
        else if ($_GET['action'] == "edit_marker" && isset($_GET['id'])) {

            wpgmza_edit_marker($_GET['id']);

        }
        else {

            if (function_exists(wpgmza_register_pro_version)) {

                    $prov = get_option("WPGMZA_PRO");
                    $wpgmza_pro_version = $prov['version'];
                    if (floatval($wpgmza_pro_version) < 3 || $wpgmza_pro_version == null) {
                        wpgmaps_upgrade_notice();
                    } else {
                        wpgmza_pro_menu();
                    }


            } else {
                wpgmza_basic_menu();

            }

        }
    }
    
    wpgmaps_debugger("menu_end");

}
function wpgmaps_menu_advanced_layout() {
    if (function_exists(wpgmza_register_pro_version)) {
        wpgmza_pro_advanced_menu();
    }

}

function wpgmza_map_page() {
    wpgmaps_debugger("map_page_start");

    if (function_exists(wpgmza_register_pro_version)) {
        echo"<div class=\"wrap\"><div id=\"icon-edit\" class=\"icon32 icon32-posts-post\"><br></div><h2>Your Maps <a href=\"admin.php?page=wp-google-maps-menu&action=new\" class=\"add-new-h2\">Add New</a></h2>";
        wpgmaps_check_versions();
        wpgmaps_list_maps();
    } else {
        echo"<div class=\"wrap\"><div id=\"icon-edit\" class=\"icon32 icon32-posts-post\"><br></div><h2>Your Maps</h2>";
        echo"<p><i><a href='http://www.wpgmaps.com/purchase-professional-version/' title='Pro Version'>Create unlimited maps</a> with the <a href='http://www.wpgmaps.com/purchase-professional-version/' title='Pro Version'>Pro Version</a> of WP Google Maps for only <strong>$9.99!</strong></i></p>";
        wpgmaps_list_maps();


    }
    echo "</div>";
    wpgmaps_debugger("map_page_end");
    
}
function wpgmaps_list_maps() {
    wpgmaps_debugger("list_maps_start");

    global $wpdb;
    global $wpgmza_tblname_maps;

    if ($wpgmza_tblname_maps) { $table_name = $wpgmza_tblname_maps; } else { $table_name = $wpdb->prefix . "wpgmza_maps"; }


    $results = $wpdb->get_results(
	"
	SELECT *
	FROM $table_name
        WHERE `active` = 0 
        ORDER BY `id` DESC
	"
    );
    echo "

      <table class=\"wp-list-table widefat fixed \" cellspacing=\"0\">
	<thead>
	<tr>
		<th scope='col' id='id' class='manage-column column-id sortable desc'  style=''><span>ID</span></th>
                <th scope='col' id='map_title' class='manage-column column-map_title sortable desc'  style=''><span>Title</span></th>
                <th scope='col' id='map_width' class='manage-column column-map_width' style=\"\">Width</th>
                <th scope='col' id='map_height' class='manage-column column-map_height'  style=\"\">Height</th>
                <th scope='col' id='type' class='manage-column column-type sortable desc'  style=\"\"><span>Type</span></th>
        </tr>
	</thead>
        <tbody id=\"the-list\" class='list:wp_list_text_link'>
";
    foreach ( $results as $result ) {
        if ($result->type == "1") { $map_type = "Roadmap"; }
        else if ($result->type == "2") { $map_type = "Satellite"; }
        else if ($result->type == "3") { $map_type = "Hybrid"; }
        else if ($result->type == "4") { $map_type = "Terrain"; }

        echo "<tr id=\"record_".$result->id."\">";
        echo "<td class='id column-id'>".$result->id."</td>";
        echo "<td class='map_title column-map_title'><strong><big><a href=\"?page=wp-google-maps-menu&action=edit&map_id=".$result->id."\" title=\"Edit\">".$result->map_title."</a></big></strong><br /><a href=\"?page=wp-google-maps-menu&action=edit&map_id=".$result->id."\" title=\"Edit\">Edit</a> | <a href=\"?page=wp-google-maps-menu&action=trash&map_id=".$result->id."\" title=\"Trash\">Trash</a></td>";
        echo "<td class='map_width column-map_width'>".$result->map_width."</td>";
        echo "<td class='map_width column-map_height'>".$result->map_height."</td>";
        echo "<td class='type column-type'>".$map_type."</td>";
        echo "</tr>";


    }
    echo "</table>";
    wpgmaps_debugger("list_maps_end");
}

function wpgmaps_check_versions() {
    wpgmaps_debugger("check_versions_start");

    $prov = get_option("WPGMZA_PRO");
    $wpgmza_pro_version = $prov['version'];
    if (floatval($wpgmza_pro_version) < 3 || $wpgmza_pro_version == null) {
        wpgmaps_upgrade_notice();
    }


    wpgmaps_debugger("check_versions_end");
}

function wpgmza_basic_menu() {
    wpgmaps_debugger("bm_start");

    
    global $wpgmza_tblname_maps;
    global $wpdb;
    if (!wpgmaps_check_permissions()) { wpgmaps_permission_warning(); }
    if ($_GET['action'] == "edit" && isset($_GET['map_id'])) {
        
        $res = wpgmza_get_map_data($_GET['map_id']);
        

       if ($res->map_start_zoom) { $wpgmza_zoom[intval($res->map_start_zoom)] = "SELECTED"; } else { $wpgmza_zoom[8] = "SELECTED"; }
       if ($res->type) { $wpgmza_map_type[intval($res->type)] = "SELECTED"; } else { $wpgmza_map_type[1] = "SELECTED"; }
       if ($res->alignment) { $wpgmza_map_align[intval($res->alignment)] = "SELECTED"; } else { $wpgmza_map_align[1] = "SELECTED"; }


        $wpgmza_act = "disabled readonly";
        $wpgmza_act_msg = "<span style=\"font-size:16px; color:#666;\">Add custom icons, titles, descriptions, pictures and links to your markers with the \"<a href=\"http://www.wpgmaps.com/purchase-professional-version/\" title=\"Pro Edition\" target=\"_BLANK\">Pro Edition</a>\" of this plugin for just <strong>$9.99</strong></span>";
        $wpgmza_csv = "<p><a href=\"http://www.wpgmaps.com/\" title=\"Pro Edition\">Purchase the Pro Edition</a> of WP Google Maps and save your markers to a CSV file!</p>";
    }
        echo "
           <div class='wrap'>
                <h1>WP Google Maps</h1>
                <div class='wide'>

                    <h2>Map Settings</h2>
                    <form action='' method='post' id='wpgmaps_options'>
                    <p></p>

                    <input type='hidden' name='http_referer' value='".$_SERVER['PHP_SELF']."' />
                    <input type='hidden' name='wpgmza_id' id='wpgmza_id' value='".$res->id."' />
                    <input id='wpgmza_start_location' name='wpgmza_start_location' type='hidden' size='40' maxlength='100' value='".$res->map_start_location."' />
                    <select id='wpgmza_start_zoom' name='wpgmza_start_zoom' style=\"display:none;\">
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
                    <table>
                        <tr>
                            <td>Short code:</td>
                            <td><input type='text' readonly name='shortcode' style='font-size:18px; text-align:center;' value='[wpgmza id=\"".$res->id."\"]' /> <small><i>copy this into your post or page to display the map</i></td>
                        </tr>
                        <tr>
                            <td>Map Name:</td>
                            <td><input id='wpgmza_title' name='wpgmza_title' type='text' size='20' maxlength='50' value='".$res->map_title."' /></td>
                        </tr>
                        <tr>
                             <td>Width:</td>
                             <td><input id='wpgmza_width' name='wpgmza_width' type='text' size='4' maxlength='4' value='".$res->map_width."' /> px </td>
                        </tr>
                        <tr>
                            <td>Height:</td>
                            <td><input id='wpgmza_height' name='wpgmza_height' type='text' size='4' maxlength='4' value='".$res->map_height."' /> px</td>
                        </tr>
                        <tr>
                            <td>Default Marker Image:</td>
                            <td><input id=\"upload_default_marker\" name=\"upload_default_marker\" type='hidden' size='35' maxlength='700' value='' ".$wpgmza_act."/> <input id=\"upload_default_marker_btn\" type=\"button\" value=\"Upload Image\" $wpgmza_act /><small><i> available in the <a href=\"http://www.wpgmaps.com/purchase-professional-version/\" title=\"Pro Edition\" target=\"_BLANK\">Pro Edition</a> only.   </i></small></td>
                        </tr>
                        <tr>
                            <td>Map type:</td>
                            <td><select id='wpgmza_map_type' name='wpgmza_map_type'>
                                <option value=\"1\" ".$wpgmza_map_type[1].">Roadmap</option>
                                <option value=\"2\" ".$wpgmza_map_type[2].">Satellite</option>
                                <option value=\"3\" ".$wpgmza_map_type[3].">Hybrid</option>
                                <option value=\"4\" ".$wpgmza_map_type[4].">Terrain</option>
                            </select>
                            </td>
                        </tr>
                        <tr>
                            <td>Map Alignment:</td>
                            <td><select id='wpgmza_map_align' name='wpgmza_map_align'>
                                <option value=\"1\" ".$wpgmza_map_align[1].">Left</option>
                                <option value=\"2\" ".$wpgmza_map_align[2].">Center</option>
                                <option value=\"3\" ".$wpgmza_map_align[3].">Right</option>
                                <option value=\"4\" ".$wpgmza_map_align[4].">None</option>
                            </select>
                            </td>
                        </tr>


                        </table>
                            <div id=\"wpgmaps_save_reminder\" style=\"display:none;\"><span style=\"font-size:16px; color:#1C62B9;\">Remember to save your map!</span></div>
                            <p class='submit'><input type='submit' name='wpgmza_savemap' class='button-primary' value='Save Map &raquo;' /></p>
                            <p style=\"width:600px; color:#808080;\">Tip: Use your mouse to change the layout of your map. When you have positioned the map to your desired location, press \"Save Map\" to keep your settings.</p>


                            <div id=\"wpgmza_map\">&nbsp;</div>
                            <div style=\"display:block; overflow:auto; background-color:#FFFBCC; padding:10px; border:1px solid #E6DB55; margin-top:5px; margin-bottom:5px;\">
                                <h2 style=\"padding-top:0; margin-top:0;\">Add a marker</h2>
                                <p>
                                <table>
                                <input type=\"hidden\" name=\"wpgmza_edit_id\" id=\"wpgmza_edit_id\" value=\"\" />
                                <tr>
                                    <td>Title: </td>
                                    <td><input id='wpgmza_add_title' name='wpgmza_add_title' type='text' size='35' maxlength='200' value='' $wpgmza_act /> &nbsp;<br /></td>

                                </tr>
                                <tr>
                                    <td>Address/GPS: </td>
                                    <td><input id='wpgmza_add_address' name='wpgmza_add_address' type='text' size='35' maxlength='200' value='' /> &nbsp;<br /></td>

                                </tr>

                                <tr><td>Description: </td><td><input id='wpgmza_add_desc' name='wpgmza_add_desc' type='text' size='35' maxlength='300' value='' ".$wpgmza_act."/>  &nbsp;<br /></td></tr>
                                <tr><td>Pic URL: </td><td><input id='wpgmza_add_pic' name=\"wpgmza_add_pic\" type='text' size='35' maxlength='700' value='' ".$wpgmza_act."/> <input id=\"upload_image_button\" type=\"button\" value=\"Upload Image\" $wpgmza_act /><br /></td></tr>
                                <tr><td>Link URL: </td><td><input id='wpgmza_link_url' name='wpgmza_link_url' type='text' size='35' maxlength='700' value='' ".$wpgmza_act." /></td></tr>
                                <tr><td>Custom Marker: </td><td><input id='wpgmza_add_custom_marker' name=\"wpgmza_add_custom_marker\" type='hidden' size='35' maxlength='700' value='' ".$wpgmza_act."/> <input id=\"upload_custom_marker_button\" type=\"button\" value=\"Upload Image\" $wpgmza_act /> &nbsp;</td></tr>
                                <tr>
                                    <td>Animation: </td>
                                    <td>
                                        <select name=\"wpgmza_animation\" id=\"wpgmza_animation\" readonly disabled>
                                            <option value=\"0\">None</option>
                                            <option value=\"1\">Bounce</option>
                                            <option value=\"2\">Drop</option>
                                    </td>
                                </tr>

                                <tr>
                                    <td></td>
                                    <td>
                                        <span id=\"wpgmza_addmarker_div\"><input type=\"button\" class='button-primary' id='wpgmza_addmarker' value='Add Marker' /></span> <span id=\"wpgmza_addmarker_loading\" style=\"display:none;\">Adding...</span>
                                        <span id=\"wpgmza_editmarker_div\" style=\"display:none;\"><input type=\"button\" id='wpgmza_editmarker'  class='button-primary' value='Save Marker' /></span><span id=\"wpgmza_editmarker_loading\" style=\"display:none;\">Saving...</span>
                                    </td>

                                </tr>

                                </table>
                            </div>
                            <p>$wpgmza_act_msg</p>
                            <h2 style=\"padding-top:0; margin-top:0;\">Your Markers</h2>
                            <div id=\"wpgmza_marker_holder\">
                                ".wpgmza_return_marker_list($_GET['map_id'])."
                            </div>

                            <br /><br />$wpgmza_csv

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

                    <p><br /><br />WP Google Maps encourages you to make use of the amazing icons created by Nicolas Mollet's Maps Icons Collection <a href='http://mapicons.nicolasmollet.com'>http://mapicons.nicolasmollet.com/</a> and to credit him when doing so.</p>
                </div>


            </div>



        ";

    

    wpgmaps_debugger("bm_end");

}



function wpgmza_edit_marker($mid) {
    global $wpgmza_tblname_maps;
    global $wpdb;
    if ($_GET['action'] == "edit_marker" && isset($mid)) {
        $res = wpgmza_get_marker_data($mid);
        echo "
           <div class='wrap'>
                <h1>WP Google Maps</h1>
                <div class='wide'>

                    <h2>Edit Marker Location ID#$mid</h2>
                    <form action='?page=wp-google-maps-menu&action=edit&map_id=".$res->map_id."' method='post' id='wpgmaps_edit_marker'>
                    <p></p>

                    <input type='hidden' name='wpgmaps_marker_id' id='wpgmaps_marker_id' value='".$mid."' />
                    <div id=\"wpgmaps_status\"></div>
                    <table>

                        <tr>
                            <td>Marker Latitude:</td>
                            <td><input id='wpgmaps_marker_lat' name='wpgmaps_marker_lat' type='text' size='15' maxlength='100' value='".$res->lat."' /></td>
                        </tr>
                        <tr>
                            <td>Marker Longitude:</td>
                            <td><input id='wpgmaps_marker_lng' name='wpgmaps_marker_lng' type='text' size='15' maxlength='100' value='".$res->lng."' /></td>
                        </tr>

                    </table>
                    <p class='submit'><input type='submit' name='wpgmza_save_maker_location' class='button-primary' value='Save Marker Location &raquo;' /></p>
                    <p style=\"width:600px; color:#808080;\">Tip: Use your mouse to change the location of the marker. Simply click and drag it to your desired location.</p>


                    <div id=\"wpgmza_map\">&nbsp;</div>
                    
                    <p>$wpgmza_act_msg</p>
                            
                            

                    </form>
                </div>


            </div>



        ";

    }



}





function wpgmaps_admin_scripts() {
    wpgmaps_debugger("admin_scripts_start");
    wp_enqueue_script('media-upload');
    wp_enqueue_script('thickbox');
    wp_register_script('my-upload', WP_PLUGIN_URL.'/'.plugin_basename(dirname(__FILE__)).'/upload.js', array('jquery','media-upload','thickbox'));
    wp_enqueue_script('my-upload');
    wpgmaps_debugger("admin_scripts_end");

}

function wpgmaps_admin_styles() {
    wp_enqueue_style('thickbox');
}

if (isset($_GET['page']) && $_GET['page'] == 'wp-google-maps-menu') {
    wpgmaps_debugger("load_scripts_styles_start");
    
    add_action('admin_print_scripts', 'wpgmaps_admin_scripts');
    add_action('admin_print_styles', 'wpgmaps_admin_styles');
    wpgmaps_debugger("load_scripts_styles_end");
}




function wpgmza_return_marker_list($map_id) {
    wpgmaps_debugger("return_marker_start");

    global $wpdb;
    global $wpgmza_tblname;

    $marker_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpgmza_tblname WHERE `map_id` = '$map_id';" ) );
    if ($marker_count > 2000) {
        return "There are too many markers to make use of the live edit function. The maximum amount for this functionality is 2000 markers. Anything more than that number would crash your browser. In order to edit your markers, you would need to download the table in CSV format, edit it and re-upload it.";
    } else {



    $results = $wpdb->get_results("
	SELECT *
	FROM $wpgmza_tblname
	WHERE `map_id` = '$map_id' ORDER BY `id` DESC
    ");
    $wpgmza_tmp .= "
        <table id=\"wpgmza_table\" class=\"display\" cellspacing=\"0\" cellpadding=\"0\">
        <thead>
        <tr>
            <th><strong>ID</strong></th>
            <th><strong>Icon</strong></th>
            <th><strong>Title</strong></th>
            <th><strong>Address</strong></th>
            <th><strong>Description</strong></th>
            <th><strong>Image</strong></th>
            <th><strong>Link</strong></th>
            <th><strong>Action</strong></th>
        </tr>
        </thead>
        <tbody>
";


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
                <td height=\"40\">".$icon."<input type=\"hidden\" id=\"wpgmza_hid_marker_icon_".$result->id."\" value=\"".$result->icon."\" /><input type=\"hidden\" id=\"wpgmza_hid_marker_anim_".$result->id."\" value=\"".$result->anim."\" /></td>
                <td>".$result->title."<input type=\"hidden\" id=\"wpgmza_hid_marker_title_".$result->id."\" value=\"".$result->title."\" /></td>
                <td>".$result->address."<input type=\"hidden\" id=\"wpgmza_hid_marker_address_".$result->id."\" value=\"".$result->address."\" /></td>
                <td>".$result->desc."<input type=\"hidden\" id=\"wpgmza_hid_marker_desc_".$result->id."\" value=\"".$result->desc."\" /></td>
                <td>$pic<input type=\"hidden\" id=\"wpgmza_hid_marker_pic_".$result->id."\" value=\"".$result->pic."\" /></td>
                <td>$linktd<input type=\"hidden\" id=\"wpgmza_hid_marker_link_".$result->id."\" value=\"".$result->link."\" /></td>
                <td>
                    <a href=\"#wpgmaps_marker\" title=\"Edit this marker\" class=\"wpgmza_edit_btn\" id=\"".$result->id."\">Edit</a> |
                    <a href=\"?page=wp-google-maps-menu&action=edit_marker&id=".$result->id."\" title=\"Edit this marker\" class=\"wpgmza_edit_btn\" id=\"".$result->id."\">Edit Location</a> |
                    <a href=\"javascript:void(0);\" title=\"Delete this marker\" class=\"wpgmza_del_btn\" id=\"".$result->id."\">Delete</a>
                </td>
            </tr>";
    }
    $wpgmza_tmp .= "</tbody></table>";

    wpgmaps_debugger("return_marker_end");
    return $wpgmza_tmp;
    }
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

if (function_exists(wpgmza_register_pro_version)) {
    add_action('wp_ajax_add_marker', 'wpgmaps_action_callback_pro');
    add_action('wp_ajax_delete_marker', 'wpgmaps_action_callback_pro');
    add_action('wp_ajax_edit_marker', 'wpgmaps_action_callback_pro');
    add_action('template_redirect','wpgmaps_check_shortcode');
    
    if (function_exists(wpgmza_register_gold_version)) {
        add_action('wp_footer', 'wpgmaps_user_javascript_gold');
        add_action('admin_head', 'wpgmaps_admin_javascript_gold');
    } else {
        add_action('wp_footer', 'wpgmaps_user_javascript_pro');
        add_action('admin_head', 'wpgmaps_admin_javascript_pro');
    }
    add_shortcode( 'wpgmza', 'wpgmaps_tag_pro' );
} else {
    add_action('admin_head', 'wpgmaps_admin_javascript_basic');
    add_action('wp_ajax_add_marker', 'wpgmaps_action_callback_basic');
    add_action('wp_ajax_delete_marker', 'wpgmaps_action_callback_basic');
    add_action('wp_ajax_edit_marker', 'wpgmaps_action_callback_basic');
    add_action('template_redirect','wpgmaps_check_shortcode');
    add_action('wp_footer', 'wpgmaps_user_javascript_basic');
    add_shortcode( 'wpgmza', 'wpgmaps_tag_basic' );
}


function wpgmaps_check_shortcode() {
    wpgmaps_debugger("check_for_sc_start");
    global $posts;
    global $short_code_active;
    $short_code_active = false;
      $pattern = get_shortcode_regex();

      foreach ($posts as $post) {
          preg_match_all('/'.$pattern.'/s', $post->post_content, $matches);
          foreach ($matches as $match) {
            if (is_array($match)) {
                foreach($match as $key => $val) {
                    $pos = strpos($val, "wpgmza");
                    if ($pos === false) { } else { $short_code_active = true; }
                }
            }
          }
      }
    wpgmaps_debugger("check_for_sc_end");
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
    wpgmaps_debugger("update_db_start");

    global $wpgmza_version;
    if (get_option('wpgmza_db_version') != $wpgmza_version) {
        wpgmaps_handle_db();
    }
    wpgmaps_debugger("update_db_end");
}


add_action('plugins_loaded', 'wpgmaps_update_db_check');

function wpgmaps_handle_db() {
   wpgmaps_debugger("handle_db_start");

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
          `anim` varchar(3) NOT NULL,
          `title` varchar(700) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
    ";

   require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   dbDelta($sql);

    $table_name = $wpdb->prefix . "wpgmza_maps";
    $sql = "
        CREATE TABLE `".$table_name."` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `map_title` varchar(50) NOT NULL,
          `map_width` varchar(6) NOT NULL,
          `map_height` varchar(6) NOT NULL,
          `map_start_lat` varchar(700) NOT NULL,
          `map_start_lng` varchar(700) NOT NULL,
          `map_start_location` varchar(700) NOT NULL,
          `map_start_zoom` INT(10) NOT NULL,
          `default_marker` varchar(700) NOT NULL,
          `type` INT(10) NOT NULL,
          `alignment` INT(10) NOT NULL,
          `directions_enabled` INT(10) NOT NULL,
          `styling_enabled` INT(10) NOT NULL,
          `styling_json` mediumtext NOT NULL,
          `active` INT(1) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
    ";

   require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   dbDelta($sql);



   add_option("wpgmza_db_version", $wpgmza_version);
   update_option("wpgmza_db_version",$wpgmza_version);
   wpgmaps_debugger("handle_db_end");
}

function wpgmza_get_map_data($map_id) {
    global $wpdb;
    global $wpgmza_tblname_maps;
    
    $result = $wpdb->get_results("
        SELECT *
        FROM $wpgmza_tblname_maps
        WHERE `id` = '".$map_id."' LIMIT 1
    ");

    $res = $result[0];
    return $res;

}
function wpgmza_get_marker_data($mid) {
    global $wpdb;
    global $wpgmza_tblname;

    $result = $wpdb->get_results("
        SELECT *
        FROM $wpgmza_tblname
        WHERE `id` = '".$mid."' LIMIT 1
    ");

    $res = $result[0];
    return $res;

}
function wpgmaps_upgrade_notice() {
    echo "<div class='error below-h1'>
        <big><big>
            <p>Dear Pro User. <br /></p>

            <p>We recently upgraded the WP Google Maps plugin to include functionality for <strong>multiple maps.</strong>
            You need to upgrade your Pro version to the <strong>latest version</strong> in order for the plugin to continue
            working. We apologise for the inconvenience but would urge you to consider that we are attempting to make this
            the best map plugin available on the market. There was a big need for multiple maps and the only way we could
            achieve this was to make major changes to the code, thus resulting in the need for the latest version!<br /></p>

            <p>You should have already received an email with the download link for the latest version, if not please
            <big><a href='http://www.wpgmaps.com/d/wp-google-maps-pro.zip' target='_blank'>download it here</a></big>! (This link will only be available
            for 1 week, thereafter please <a href='http://www.wpgmaps.com/contact-us/'>contact us</a>)<br /><br /></p>

            <p><strong>Installation Instructions:</strong><br />
            <ul>
                <li>- Once downloaded, please <strong>deactivate</strong> and <strong>delete</strong> your old Pro plugin (your marker information wont be affected at all).</li>
                <li>- <a href=\"".get_option('siteurl')."/wp-admin/plugin-install.php?tab=upload\" target=\"_BLANK\">Upload the new</a> plugin ZIP file.</li>
                <li>- You will notice the left hand navigation has now changed from \"WP Google Maps\" to just \"Maps\".</li>
                <li>- Enjoy creating multiple maps!</li>

            </p>

            <p>If you run into any bugs, please let me know so that I can get it sorted out ASAP</p>

            <p>Kind regards,<br /><a href='http://www.wpgmaps.com/'>WP Google Maps</a></p>
        </big></big>
    </div>";
}
function wpgmaps_trash_map($map_id) {
    global $wpdb;
    global $wpgmza_tblname_maps;
    if (isset($map_id)) {
        $rows_affected = $wpdb->query( $wpdb->prepare( "UPDATE $wpgmza_tblname_maps SET active = %d WHERE id = %d", 1, $map_id) );
        return true;
    } else {
        return false;
    }


}

function wpgmaps_debugger($section) {
    
    global $debug;
    global $debug_start;
    global $debug_step;
    if ($debug) {
        $end = (float) array_sum(explode(' ',microtime()));
        echo "<!-- $section processing time: ". sprintf("%.4f", ($end-$debug_start))." seconds\n -->";
    }

}
?>