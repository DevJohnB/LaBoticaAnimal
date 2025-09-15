<?php
namespace PetIA\Controllers;

class CatalogController {
    public function register_routes() {
        $namespace = 'petia-app-bridge/v1';
        register_rest_route( $namespace, '/product-categories', [
            'methods'  => 'GET',
            'callback' => [ $this, 'handle_product_categories' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/products', [
            'methods'  => 'GET',
            'callback' => [ $this, 'handle_products' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/brands', [
            'methods'  => 'GET',
            'callback' => [ $this, 'handle_brands' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle_product_categories( \WP_REST_Request $request ) {
        if ( ! function_exists( 'get_terms' ) ) {
            return [];
        }
        $terms = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'pad_counts' => true,
            ]
        );
        $data  = array_map(
            fn( $t ) => [
                'id'     => $t->term_id,
                'name'   => $t->name,
                'parent' => $t->parent,
                'count'  => (int) $t->count,
            ],
            $terms
        );
        return $data;
    }

    private function normalize_attribute_key( $key ) {
        return 0 === strpos( $key, 'attribute_' ) ? $key : 'attribute_' . $key;
    }

    public function handle_products( \WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return [];
        }
        $args = [ 'limit' => -1 ];
        $category = $request->get_param( 'category' );
        if ( $category ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => array_map( 'intval', (array) $category ),
                ],
            ];
        }
        $products = wc_get_products( $args );
        $data     = [];
        foreach ( $products as $product ) {
            $image_id = $product->get_image_id();
            $item     = [
                'id'             => $product->get_id(),
                'name'           => $product->get_name(),
                'price'          => (float) $product->get_price(),
                'formatted_price' => function_exists( 'wc_price' ) ? wc_price( $product->get_price() ) : null,
                'image'          => $image_id ? wp_get_attachment_url( $image_id ) : '',
                'type'           => $product->get_type(),
            ];
            if ( 'variable' === $product->get_type() ) {
                $item['min_price'] = (float) $product->get_variation_price( 'min', true );
                $item['max_price'] = (float) $product->get_variation_price( 'max', true );
                if ( function_exists( 'wc_price' ) ) {
                    $item['formatted_min_price'] = wc_price( $item['min_price'] );
                    $item['formatted_max_price'] = wc_price( $item['max_price'] );
                }
                $attributes = [];
                foreach ( $product->get_variation_attributes() as $taxonomy => $options ) {
                    $attribute_label   = wc_attribute_label( $taxonomy );
                    $attribute_options = [];
                    foreach ( array_values( $options ) as $option ) {
                        $term              = get_term_by( 'slug', $option, $taxonomy );
                        $attribute_options[] = [
                            'value' => $option,
                            'label' => $term ? $term->name : $option,
                        ];
                    }
                    $attributes[ $this->normalize_attribute_key( $taxonomy ) ] = [
                        'label'   => $attribute_label,
                        'options' => $attribute_options,
                    ];
                }
                $item['attributes'] = $attributes;
                $item['variations'] = array_map(
                    function( $variation ) {
                        $normalized_attributes = [];
                        foreach ( $variation['attributes'] as $key => $value ) {
                            $normalized_attributes[ $this->normalize_attribute_key( $key ) ] = $value;
                        }
                        return [
                            'id'         => $variation['variation_id'],
                            'attributes' => $normalized_attributes,
                        ];
                    },
                    $product->get_available_variations()
                );
            }
            $data[] = $item;
        }
        return $data;
    }

    public function handle_brands( \WP_REST_Request $request ) {
        if ( ! function_exists( 'get_terms' ) ) {
            return [];
        }
        $terms = get_terms( [ 'taxonomy' => 'product_brand', 'hide_empty' => false ] );
        return wp_list_pluck( $terms, 'name', 'term_id' );
    }
}
