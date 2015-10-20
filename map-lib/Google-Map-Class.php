<?php
class GMP_Google_Map {
    protected $display_all = false;

    public function __construct($params = array()) {
        if(isset($params['display_all'])) {
            $this->display_all = $params['display_all'];
        }
    }

    public function run( $height, $id ) {
		$this->element_id = $id;
        add_action( 'wp_footer', array( $this, 'javascript_include' ) );
		return $this->display_map( $height );
    }

    public function display_map( $height='650', $id='map_canvas' ) {
		return '<div id="'.esc_attr( $this->element_id ).'" style="height:' .absint( $height ) .'px;"></div>';
    }

    public function javascript_include() {
		global $map_included;

		//load plugin options
		$options_arr = get_option( 'gmp_params' );

		//get map type setting
		$display_type = ( $options_arr["post_gmp_map_type"] ) ? $options_arr["post_gmp_map_type"] : 'ROADMAP';

		$javascript = '';
		$js_footer = '';

		$javascript = $this->build_marker_javascript();

		if ( ! $map_included ) {
			$js_footer .= '<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>';
		}

		if ( $this->element_id == 'map_canvas' ) {
			$js_footer .= '<script type="text/javascript">';
			$js_footer .= 'function wds_map_markers_initialize() {';
			$js_footer .= ' var coords = new google.maps.LatLng( \'0\', \'0\' );';
			$js_footer .= '	var mapOptions = {';
			$js_footer .= '	  zoom: 10,';
			$js_footer .= '	  center: coords,';
			$js_footer .= '	  mapTypeId: google.maps.MapTypeId.' .esc_js( $display_type );
			$js_footer .= '	};';
			$js_footer .= '    var map = new google.maps.Map( document.getElementById( "map_canvas" ), mapOptions );';
			$js_footer .= '    var bounds = new google.maps.LatLngBounds();';
			$js_footer .= '    var infowindow = new google.maps.InfoWindow();';
			$js_footer .= $javascript;
			$js_footer .= '}';
			$js_footer .= 'setTimeout( "wds_map_markers_initialize()", 10 );';
			$js_footer .= '</script>';
		} elseif ( $this->element_id == 'map_canvas_shortcode' ) {
			$js_footer .= '<script type="text/javascript">';
			$js_footer .= 'function wds_map_markers_initialize_shortcode() {';
			$js_footer .= '    var coords = new google.maps.LatLng( \'0\', \'0\' );';
			$js_footer .= '	var mapOptions = {';
			$js_footer .= '	  zoom: 10,';
			$js_footer .= '	  center: coords,';
			$js_footer .= '	  mapTypeId: google.maps.MapTypeId.' .esc_js( $display_type );
			$js_footer .= '	};';
			$js_footer .= '    var map = new google.maps.Map( document.getElementById( "map_canvas_shortcode" ), mapOptions );';
			$js_footer .= '    var bounds = new google.maps.LatLngBounds();';
			$js_footer .= '    var infowindow = new google.maps.InfoWindow();';
			$js_footer .= $javascript;
			$js_footer .= '}';
			$js_footer .= 'setTimeout( "wds_map_markers_initialize_shortcode()", 10 );';
			$js_footer .= '</script>';
		}

		$map_included = true;

		echo $js_footer;

    }

    public function build_marker_javascript( $class = '' ) {
        global $post, $wpdb;

        if($this->display_all) { // Retrive meta from all posts => mash up all maps
            $query = "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key='gmp_arr'";
            $res = $wpdb->get_results($query);
            foreach ($res as $row) {
                $rowData = unserialize($row->meta_value);
                $rowData['post_id'] = $row->post_id;
                $gmp_arr[] = $rowData;
            }
        }
        else {
		  $gmp_arr = get_post_meta( $post->ID, 'gmp_arr', false );
        }


        for ( $row = 0; $row < count( $gmp_arr ); $row++ ) {
            $post_id = isset($gmp_arr[$row]['post_id']) ? $gmp_arr[$row]['post_id'] : $post->ID;

            $title      = $gmp_arr[$row]["gmp_title"];
            $desc       = $gmp_arr[$row]["gmp_description"];
            $lat        = $gmp_arr[$row]["gmp_lat"];
            $lng        = $gmp_arr[$row]["gmp_long"];
            $address    = $gmp_arr[$row]["gmp_address1"];
            $address2   = $gmp_arr[$row]["gmp_address2"];
            $marker     = $gmp_arr[$row]["gmp_marker"];

            $location_id    = $post_id;
            $featimg        = $this->get_listing_thumbnail( NULL, $post_id );
            $entry_url      = get_permalink( $post_id );
            $post_type      = get_post_field('post_type',$post_id); // get_post_type( $post );
            $html           = get_post_field('post_content',$post_id);

            if ( $lat && $lng ) {

                $args[$row]=array(
                    'post_id'	=> $post_id,
                    'post_type' => $post_type,
                    'address_title' => $title,
                    'address'   => $address,
                    'address2'	=> $address2,
                    'lat'		=> $lat,
                    'lng'		=> $lng,
                    'url'		=> $entry_url,
                    'img'		=> $featimg,
                    'title'		=> htmlentities( get_post_field('post_title',$post_id), ENT_QUOTES ),
                    'html'		=> $html,
                    'class'		=> $class,
                    'marker'    => $marker,                    
                    'desc'      => $desc,
                );
            }
        }

        return $this->EL_wds_map_load_markers( $args,'200px','100%','no' );
    }

    public function get_listing_thumbnail( $listing_post_type='', $post_id ) {
		//future feature
		$feat_image = '';

        return $feat_image;
    }

    public function EL_wds_map_load_markers( $args_arr=array(), $map_height="400px", $map_width="100%", $echo="yes" ) {
        global $gmp_display;

		$markers = 0;
		$return = '';

        if ( empty( $args_arr ) ) {
            return $return;
        }
        //extract our post meta early, so that we actually get ALL meta fields. Before we kept getting just first one.
        //Don't ask me how we were getting multiple markers for the different addresses.
        $id = $args_arr[0]['post_id'];
        $gmp_arr = get_post_meta( $id, 'gmp_arr', false );

        foreach ( $args_arr as $args ) {

            extract( $args, EXTR_OVERWRITE );

			$gmp_marker = ( !empty( $args['marker'] ) ) ? $args['marker'] : 'blue-dot.png';


            $content = '<div><p>';


            if( !empty( $args['address_title'] )) {
                $content .= esc_js( $args['address_title'] ).'<br />';
            }

            if( !empty( $args['desc'] )) {
                $content .= esc_js( $args['desc'] ).'<br />';
            }

            if(empty($args['address_title']) && empty($args['desc'])) {
                $address = esc_js( $args['address'] );
                if ( !empty( $args['address2'] ) )
                    $address .= '<br/>' . esc_js( $args['address2'] );
                
                $content .= $address.'<br />';
            }

            // Add link a link where the post this address comes from
            if($this->display_all)
                $content .= '<a href=\"'.$args['url'].'\" title=\"'.$args['title'].'\">'.$args['title'].'</a>';

            $content .= '</p></div>';

			$return .= 'var icon = new google.maps.MarkerImage( "' . plugins_url( '/markers/' . $gmp_marker, dirname( __FILE__ ) ) . '");';

            //$content = $img . $title;
            $id = absint( $post_id ) . '_' . $markers;

            //TODO : on click on marker, close all others infoWindow

            $return .=
                'var myLatLng = new google.maps.LatLng('.esc_js( $lat ).','.esc_js( $lng ).');
                bounds.extend(myLatLng);
                var marker' . $id . ' = new google.maps.Marker({
                    map: map, icon: icon, position:
                    new google.maps.LatLng('.esc_js( $lat ).','.esc_js( $lng ).')
                });

                var contentString' . $id . ' = "' . $content . '";
                var infowindow' . $id . ' = new google.maps.InfoWindow({
                    content: contentString' . $id . '
                });
                google.maps.event.addListener(marker' . $id . ', "click", function() {
                    console.log(\'lalala\');
                    infowindow' . $id . '.open(map, marker' . $id . ');
                });';
            $markers++;
        }

        if ( $markers == 1 ) {
        	$return .= 'map.setCenter(bounds.getCenter());'; // Set center and zoom out/in from here.
        } else {
        	// If more than one marker we want to fit all markers in a bound area. No Zoom.
        	$return .= 'map.fitBounds(bounds);';
		}

		return $return;

    }

    public static function htmlentitiesCallback( &$string, $key = null ) {
        $string = htmlentities( $string );
    }
}