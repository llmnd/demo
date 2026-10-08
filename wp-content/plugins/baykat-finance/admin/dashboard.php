<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'baykat_finance_register_menu' );

/**
 * Enregistre le menu Finance dans l'administration WordPress.
 */
function baykat_finance_register_menu() {
    add_menu_page(
        'Gestion financière',
        'Finance',
        'read',
        'baykat-finance',
        'baykat_finance_render_dashboard',
        'dashicons-chart-area',
        25
    );
}

/**
 * Prépare les données et affiche la page Finance.
 */
function baykat_finance_render_dashboard() {
    if ( ! baykat_finance_user_can_access() ) {
        wp_die( esc_html__( 'Vous n’avez pas l’autorisation d’accéder à cette page.', 'baykat-finance' ) );
    }

    $user_id       = get_current_user_id();
    $notice        = baykat_finance_process_transaction_submission( $user_id );
    $dashboard_data = baykat_finance_get_user_transactions( $user_id );

    $total_income  = $dashboard_data['total_income'];
    $total_expense = $dashboard_data['total_expense'];
    $balance       = $dashboard_data['balance'];
    $transactions  = $dashboard_data['transactions'];

    require BAYKAT_FINANCE_DIR . 'public/finance-page.php';
}
