<?php
/**
 * Plugin Name: woocommerece order edit by user
 * Description: To allow user to edit order while its state is processing.
 * Version: 1.0
 * Author: Sahil Gulati
 * Author URI: http://www.facebook.com/sahilgulati007
 */

// ----------------
// 1. Allow Order Again for Processing Status

add_filter( 'woocommerce_valid_order_statuses_for_order_again', 'sg_order_again_statuses' );

function sg_order_again_statuses( $statuses ) {
    $statuses[] = array('processing','onhold');
    return $statuses;
}

// ----------------
// 2. Add Order Actions @ My Account

add_filter( 'woocommerce_my_account_my_orders_actions', 'sg_add_edit_order_my_account_orders_actions', 50, 2 );

function sg_add_edit_order_my_account_orders_actions( $actions, $order ) {
    if ( $order->has_status( 'processing' ) ) {
        $actions['edit-order'] = array(
            'url'  => wp_nonce_url( add_query_arg( array( 'order_again' => $order->get_id(), 'edit_order' => $order->get_id() ) ), 'woocommerce-order_again' ),
            'name' => __( 'Edit Order', 'woocommerce' )
        );
    }
    return $actions;
}

// ----------------
// 3. Detect Edit Order Action and Store in Session

add_action( 'woocommerce_cart_loaded_from_session', 'sg_detect_edit_order' );

function sg_detect_edit_order( $cart ) {
    if ( isset( $_GET['edit_order'] ) ) WC()->session->set( 'edit_order', absint( $_GET['edit_order'] ) );
}

// ----------------
// 4. Display Cart Notice re: Edited Order

add_action( 'woocommerce_before_cart', 'sg_show_me_session' );

function sg_show_me_session() {
    if ( ! is_cart() ) return;
    $edited = WC()->session->get('edit_order');
    if ( ! empty( $edited ) ) {
        $order = new WC_Order( $edited );
        $credit = $order->get_total();
        wc_print_notice( 'A credit of ' . wc_price($credit) . ' has been applied to this new order. Feel free to add products to it or change other details such as delivery date.', 'notice' );
    }
}

// ----------------
// 5. Calculate New Total if Edited Order

add_action( 'woocommerce_cart_calculate_fees', 'sg_use_edit_order_total', 20, 1 );

function sg_use_edit_order_total( $cart ) {

    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $edited = WC()->session->get('edit_order');
    if ( ! empty( $edited ) ) {
        $order = new WC_Order( $edited );
        $credit = -1 * $order->get_total();
        $cart->add_fee( 'Credit', $credit );
    }

}

// ----------------
// 6. Save Order Action if New Order is Placed

add_action( 'woocommerce_checkout_update_order_meta', 'sg_save_edit_order' );

function sg_save_edit_order( $order_id ) {
    $edited = WC()->session->get('edit_order');
    if ( ! empty( $edited ) ) {
        // update this new order
        update_post_meta( $order_id, '_edit_order', $edited );
        $neworder = new WC_Order( $order_id );
        $oldorder_edit = get_edit_post_link( $edited );
        $neworder->add_order_note( 'Order placed after editing. Old order number: <a href="' . $oldorder_edit . '">' . $edited . '</a>' );
        // cancel previous order
        $oldorder = new WC_Order( $edited );
        $neworder_edit = get_edit_post_link( $order_id );
        $oldorder->update_status( 'cancelled', 'Order cancelled after editing. New order number: <a href="' . $neworder_edit . '">' . $order_id . '</a> -' );
    }
}