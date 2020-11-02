<?php
if (!defined('ABSPATH')) die('-1');

if (!class_exists("WD_ASP_SearchOverride_Filter")) {
    /**
     * Class WD_ASP_SearchOverride_Filter
     *
     * Handles search override filters
     *
     * @class         WD_ASP_SearchOverride_Filter
     * @version       1.0
     * @package       AjaxSearchPro/Classes/Filters
     * @category      Class
     * @author        Ernest Marcinko
     */
    class WD_ASP_SearchOverride_Filter extends WD_ASP_Filter_Abstract {

        public function handle() {}

        public function override($posts, $wp_query) {
            global $wd_asp;

            // Is this a search query? Has the override been executed?
            // !isset() instead of empty(), because it can be an empty string
            if (!$wp_query->is_main_query() ||  !isset($wp_query->query_vars['s']) || !isset($_GET['s']) ) {
                return $posts;
            }

            $s_data = array();
            // If get method is used, then the cookies are not present or not used
            if (isset($_GET['p_asp_data']) || isset($_GET['np_asp_data'])) {
                $_p_data = isset($_GET['p_asp_data']) ? $_GET['p_asp_data'] : $_GET['np_asp_data'];
                $_p_id = isset($_GET['p_asid']) ? $_GET['p_asid'] : $_GET['np_asid'];
                parse_str(base64_decode($_p_data), $s_data);
            } else if (
                isset($_COOKIE['asp_data'], $_COOKIE['asp_phrase']) &&
                $_COOKIE['asp_phrase'] == $_GET['s']
            ) {
                parse_str($_COOKIE['asp_data'], $s_data);
                $_POST['np_asp_data'] = $_COOKIE['asp_data'];
                $_POST['np_asid'] = $_COOKIE['asp_id'];
                $_p_id = $_COOKIE['asp_id'];
            } else {
                // Something is not right
                return $posts;
            }

            // The get_query_var() is malfunctioning in some cases!!! use $_GET['paged']
            //$paged = (get_query_var('paged') != 0) ? get_query_var('paged') : 1;
            if ( isset($_GET['paged']) ) {
                $paged = $_GET['paged'];
            } else if ( isset($wp_query->query_vars['paged']) ) {
                $paged = $wp_query->query_vars['paged'];
            } else {
                $paged = 1;
            }

            $instance = wd_asp()->instances->get($_p_id);
            $sd = $instance['data'];

            $paged = $paged <= 0 ? 1 : $paged;
            $posts_per_page = $sd['results_per_page'];
            $posts_per_page = $posts_per_page == 0 ? 1 : $posts_per_page;

            $s_data = apply_filters('asp_search_override_data', $s_data, $posts, $wp_query, $_p_id, $_GET['s']);

            // A possible exit point for the user, if he sets the _abort argument
            if ( isset($s_data['_abort']) )
                return $posts;

            $args = array(
                "s" => $_GET['s'],
                "_ajax_search" => false,
                "posts_per_page" => $posts_per_page,
                "page"  => $paged
            );

            $args = $this->getAdditionalArgs($args);

            if ( count($s_data) == 0 )
                $asp_query = new ASP_Query($args, $_p_id);
            else
                $asp_query = new ASP_Query($args, $_p_id, $s_data);

            $res = $asp_query->posts;

            $wp_query->found_posts = $asp_query->found_posts;
            if (($wp_query->found_posts / $posts_per_page) > 1)
                $wp_query->max_num_pages = ceil($wp_query->found_posts / $posts_per_page);
            else
                $wp_query->max_num_pages = 0;

            return $res;
        }

        public function getAdditionalArgs( $args ) {
            global $wpdb;

            // Separate case for WooCommerce
            if ( isset($_GET['post_type']) && $_GET['post_type'] == 'product') {
                // WooCommerce price filter
                if ( isset($_GET['min_price'], $_GET['max_price']) ) {
                    $args['post_meta_filter'][] = array(
                        'key'     => '_price',         // meta key
                        'value'   => array( ($_GET['min_price'] + 0), ($_GET['max_price'] + 0) ),
                        'operator' => 'BETWEEN'
                    );
                }

                // WooCommerce custom Ordering
                if ( isset($_GET['orderby']) ) {
                    $o_by = str_replace(' ', '', (strtolower($_GET['orderby'])));
                    switch ( $o_by ) {
                        case 'popularity':
                            $args['post_primary_order'] = 'customfp DESC';
                            $args['post_primary_order_metatype'] = 'numeric';
                            $args['_post_primary_order_metakey'] = 'total_sales';
                            break;
                        case 'rating':
                            // Custom query args here
                            $args['cpt_query']['fields'] = "(
                                SELECT
                                    IF(AVG( $wpdb->commentmeta.meta_value ) IS NULL, 0, AVG( $wpdb->commentmeta.meta_value ))
                                FROM
                                    $wpdb->comments
                                    LEFT JOIN $wpdb->commentmeta ON($wpdb->comments.comment_ID = $wpdb->commentmeta.comment_id)
                                WHERE
                                    $wpdb->posts.ID = $wpdb->comments.comment_post_ID
                                    AND ( $wpdb->commentmeta.meta_key = 'rating' OR $wpdb->commentmeta.meta_key IS null )
                            ) as average_rating, ";
                            $args['cpt_query']['orderby'] = "average_rating DESC, ";

                            // Force different field order for index table
                            $args['post_primary_order'] = 'average_rating DESC';
                            break;
                        case 'date':
                            $args['post_primary_order'] = 'post_date DESC';
                            break;
                        case 'price':
                            $args['post_primary_order'] = 'customfp ASC';
                            $args['post_primary_order_metatype'] = 'numeric';
                            $args['_post_primary_order_metakey'] = '_price';
                            break;
                        case 'price-desc':
                            $args['post_primary_order'] = 'customfp DESC';
                            $args['post_primary_order_metatype'] = 'numeric';
                            $args['_post_primary_order_metakey'] = '_price';
                            break;
                    }
                }
            } else if ( isset($_GET['orderby']) ) {
                $o_by = str_replace(' ', '', (strtolower($_GET['orderby'])));
                $o_by_arg = '';
                if ( in_array($o_by, array('id', 'post_id', 'post_title', 'post_date')) ) {
                    $o_by_resolve = array(
                        'id' => 'id', 'post_id' => 'id',
                        'post_title' =>'post_title',
                        'post_date' => 'post_date'
                    );
                    $o_by_arg = $o_by_resolve[$o_by];
                    if ( isset($_GET['order']) ) {
                        $o_way = str_replace(' ', '', strtolower($_GET['order']));
                        if ( in_array($o_way, array('asc', 'desc')) )
                            $o_by_arg .= ' ' . $o_way;
                    }
                }

                if ( $o_by_arg != '' ) {
                    $args['post_primary_order'] = $o_by_arg;
                }
            }

            return $args;
        }

        public function fixUrls( $url, $post, $leavename ) {
            if (isset($post->asp_guid))
                return $post->asp_guid;
            return $url;
        }

        /**
         * The Genesis framework requires special treatment
         *
         * @param $output
         * @param $wrap
         * @param $title
         * @return mixed
         */
        public function fixUrlsGenesis( $output, $wrap, $title ) {
            global $post;

            if ( isset($post, $post->asp_guid) && is_object($post) && function_exists('genesis_markup') ) {
                $pattern = "/(?<=href=(\"|'))[^\"']+(?=(\"|'))/";
                $title = preg_replace($pattern, $post->asp_guid, $title);

                $output = genesis_markup( array(
                    'open'    => "<{$wrap} %s>",
                    'close'   => "</{$wrap}>",
                    'content' => $title,
                    'context' => 'entry-title',
                    'params'  => array(
                        'wrap'  => $wrap,
                    ),
                    'echo'    => false,
                ) );
            }

            return $output;
        }

        // ------------------------------------------------------------
        //   ---------------- SINGLETON SPECIFIC --------------------
        // ------------------------------------------------------------
        public static function getInstance() {
            if ( ! ( self::$_instance instanceof self ) ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }
    }
}