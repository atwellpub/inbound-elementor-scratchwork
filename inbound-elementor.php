<?php
/*
Plugin Name: Inbound Extension - Elementor Builder Support
Plugin URI: http://www.inboundnow.com/
Description: Adds landing pages support to the Enfold Template Builder
Version: 1.0.1
Author: Inbound Now
Contributors: Hudson Atwell
Author URI: https://www.inboundnow.com/
*/


if (!class_exists('Inbound_Elementor_Builder')) {


    class Inbound_Elementor_Builder {

        /**
         *  Initialize class
         */
        public function __construct() {
            self::define_constants();
            self::load_hooks();
        }


        /**
         *  Define constants
         */
        public static function define_constants() {
            define('INBOUND_ELEMENTOR_TEMPLATE_BUILDER_CURRENT_VERSION', '1.0.8');
            define('INBOUND_ELEMENTOR_TEMPLATE_BUILDER_LABEL', __('Avia Template Builder Integration', 'inbound-pro'));
            define('INBOUND_ELEMENTOR_TEMPLATE_BUILDER_SLUG', 'inbound-cornerstone-builder');
            define('INBOUND_ELEMENTOR_TEMPLATE_BUILDER_FILE', __FILE__);
            define('INBOUND_ELEMENTOR_TEMPLATE_BUILDER_REMOTE_ITEM_NAME', 'cornerstone-page-builder-integration');
            define('INBOUND_ELEMENTOR_TEMPLATE_BUILDER_PATH', realpath(dirname(__FILE__)) . '/');
            $upload_dir = wp_upload_dir();
            $url = (!strstr(INBOUND_ELEMENTOR_TEMPLATE_BUILDER_PATH, 'plugins')) ? $upload_dir['baseurl'] . '/inbound-pro/extensions/' . plugin_basename(basename(__DIR__)) . '/' : WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/';
            define('INBOUND_ELEMENTOR_TEMPLATE_BUILDER_URLPATH', $url);
        }

        /**
         * Load Hooks & Filters
         */
        public static function load_hooks() {

            /*  add variation controls when builder is loaded */
            if (isset($_GET['action']) && $_GET['action'] == 'elementor') {
                add_action('elementor/editor/footer', array(__CLASS__, 'show_variation_controls'));
            }

            /** need to filter _elementor_data metadata to handle variation switching  */

            /* update localize preview url */
            add_filter( 'elementor/document/urls/preview' , array(__CLASS__, 'filter_preview') ,10 ,2 );

            /* save variation */
            add_action('elementor/db/before_save' , array( __CLASS__ , 'save_elementor_data') , 10 , 2);
            //add_filter('elementor/documents/ajax_save/return_data' , array( __CLASS__ , 'save_content') , 10 , 2);

            /* load variation */
            //add_filter('elementor/frontend/builder_content_data' , array( __CLASS__ , 'load_landing_page_frontend') , 10 , 2);

            /* remove native landing page filters */
            add_action('init' , function() {
                if(!isset($_GET['elementor-preview'])) {
                    remove_filter('the_content', array( 'Landing_Pages_Template_Switcher' , 'display_landing_page_content' ) , 10, 2);
                    remove_filter('get_the_content', array( 'Landing_Pages_Template_Switcher' , 'display_landing_page_content' ) , 10, 2);
                }
            });

            add_filter( 'get_post_metadata', array( __CLASS__ , 'load_landing_page_preview' ), 100 , 4  );

            if (is_admin()) {
                /* Setup Automatic Updating & Licensing */
                add_action('admin_init', array(__CLASS__, 'license_setup'));
            }

        }

        public static function filter_preview( $url  ) {

            if (isset($_GET['lp-variation-id'])) {
                $url = $url . '&lp-variation-id=' . $_GET['lp-variation-id'];
            }

            return $url;
        }

        public static function show_variation_controls() {
            global $post;
            $variations = Landing_Pages_Variations::get_variations($post->ID);
            $json = json_encode($variations);
            $edit_url = admin_url('post.php?post='.$post->ID.'&action=elementor&lp-variation-id=');
            ?>
            <script>

                jQuery(window).load(function() {
                    var variations = <?php echo $json; ?>;
                    var controller = jQuery('<div id="landing-pages-variation-controller"></div>');
                    controller.css('background-color','#fff')
                    controller.css('text-align','left')
                    controller.css('width','100%')
                    controller.css('padding-left','10px')

                    for (key in variations) {
                        var a = jQuery('<a></a>');
                        var href = '<?php echo $edit_url; ?>'+key;
                        a.attr('href',href);
                        a.html('Edit Variation ' + key + ' ');
                        a.css('margin-right' , '15px');
                        a.css('color' , '#000');
                        a.appendTo(controller);
                    }
                    controller.prependTo('#elementor-preview-responsive-wrapper');
                });
            </script>
            <?php
        }


        public function save_elementor_data( $post_id , $is_meta )   {
            global $post;

            if ( $post->post_type !='landing-page' ) {
                return;
            }

            $actions = json_decode(stripslashes($_POST['actions']),true);
            $elements =  wp_slash( wp_json_encode( $actions['save_builder']['data']['elements'] ) );

            $referral = $_SERVER["HTTP_REFERER"];
            $parts = explode('lp-variation-id=' , $referral);
            $variation_id = $parts[1];
            $post_content = $post->post_content;

            error_log('save_elementor_data');
            error_log('variation id');
            error_log($variation_id);

            if ( $variation_id > 0 ) {
                $content_key = 'content' . '-' . $variation_id;
                $elementor_key = 'inboundnow_elementor_data' . '-' . $variation_id;
            } else {
                $content_key = 'content';
                $elementor_key = 'inboundnow_elementor_data';
            }


            update_post_meta( $post->ID  , $content_key , $post_content );
            update_post_meta( $post->ID  , $elementor_key , $elements );

        }

        public function save_content( $data , $document )   {
            global $post;

            $post = $document->get_post();

            if ( $post->post_type !='landing-page' ) {
                return;
            }

            error_log('save_content');

            $referral = $_SERVER["HTTP_REFERER"];
            $parts = explode('lp-variation-id=' , $referral);
            $variation_id = $parts[1];
            $post_content = $post->post_content;

            if ( $variation_id > 0 ) {
                $content_key = 'content' . '-' . $variation_id;
            } else {
                $content_key = 'content';
            }

            update_post_meta( $post->ID  , $content_key , $post_content );

            return $data;
        }


        public function load_landing_page_frontend( $data , $post_id )   {
            global $post;


            if ( $post->post_type !='landing-page' ) {
                return;
            }

            $variation_id = Landing_Pages_Variations::get_current_variation_id();

            if ( $variation_id > 0 ) {
                $elementor_key = 'inboundnow_elementor_data' . '-' . $variation_id;
            } else {
                $elementor_key = 'inboundnow_elementor_data';
            }

            error_log('load_landing_page_frontend');
            error_log('variation id');
            error_log($variation_id);

            //error_log('loading ' . $elementor_key);
            $elements_json = get_post_meta( $post->ID  , $elementor_key , true );

            $elements = json_decode(stripslashes($elements_json),true);

            return $elements;

        }


        public function load_landing_page_preview( $metadata, $object_id, $meta_key, $single )   {
            global $post;


            if ( $post->post_type !='landing-page' ) {
                return;
            }

            if ( !isset( $meta_key ) || $meta_key != '_elementor_data' ) {
                return $metadata;
            }

            $variation_id = Landing_Pages_Variations::get_current_variation_id();

            if ( $variation_id > 0 ) {
                $elementor_key = 'inboundnow_elementor_data' . '-' . $variation_id;
            } else {
                $elementor_key = 'inboundnow_elementor_data';
            }

            $elementor_data = get_post_meta( $post->ID  , $elementor_key , true );

            return $elementor_data;

        }


        /**
         * Setups Software Update API
         */
        public static function license_setup() {

            /* ignore these hooks if inbound pro is active */
            if (defined('INBOUND_PRO_CURRENT_VERSION')) {
                return;
            }

            /*PREPARE THIS EXTENSION FOR LICESNING*/
            if (class_exists('Inbound_License')) {
                $license = new Inbound_License(INBOUND_ELEMENTOR_TEMPLATE_BUILDER_FILE, INBOUND_ELEMENTOR_TEMPLATE_BUILDER_LABEL, INBOUND_ELEMENTOR_TEMPLATE_BUILDER_SLUG, INBOUND_ELEMENTOR_TEMPLATE_BUILDER_CURRENT_VERSION, INBOUND_ELEMENTOR_TEMPLATE_BUILDER_REMOTE_ITEM_NAME);
            }
        }

    }


    new Inbound_Elementor_Builder();

}
