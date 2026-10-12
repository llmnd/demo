<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_finance_item_section( $category, $process_actions = true ) {
    if ( ! in_array( $category, array( 'input', 'sale' ), true ) ) {
        return '';
    }

    $user_id = get_current_user_id();
    $message = $process_actions
        ? baykat_finance_process_transaction_submission( $user_id, $category )
        : null;
    $finance_data = baykat_finance_get_user_transactions( $user_id, $category );
    $parcels = baykat_farm_get_user_parcels( $user_id );
    $farms = baykat_farm_get_user_farms( $user_id );
    $page_title = 'sale' === $category ? 'Suivi des ventes' : 'Suivi des intrants';

    ob_start();
    require BAYKAT_FINANCE_DIR . 'public/finance-item-page.php';
    return ob_get_clean();
}

function baykat_farm_render_finances( $process_actions = true ) {
    $sections = array(
        'resume'   => array( 'label' => 'Résumé', 'type' => 'all' ),
        'revenus'  => array( 'label' => 'Revenus', 'type' => 'income' ),
        'depenses' => array( 'label' => 'Dépenses', 'type' => 'expense' ),
        'intrants' => array( 'label' => 'Intrants' ),
        'ventes'   => array( 'label' => 'Ventes' ),
    );

    $requested_section = isset( $_GET['finance_view'] ) && is_string( $_GET['finance_view'] )
        ? sanitize_key( wp_unslash( $_GET['finance_view'] ) )
        : 'resume';

    $active_section = isset( $sections[ $requested_section ] )
        ? $requested_section
        : 'resume';

    $base_url = add_query_arg( 'module', 'finances', get_permalink() );

    ob_start();
    ?>
    <nav class="baykat-farm-finance-navigation" aria-label="Sections financières">
        <?php foreach ( $sections as $slug => $section ) : ?>
            <?php
            $is_active = $slug === $active_section;
            $section_url = add_query_arg( 'finance_view', $slug, $base_url );
            ?>
            <a
                class="baykat-farm-finance-link<?php echo $is_active ? ' is-active' : ''; ?>"
                href="<?php echo esc_url( $section_url ); ?>"
                <?php echo $is_active ? 'aria-current="page"' : ''; ?>
            >
                <?php echo esc_html( $section['label'] ); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php

    if ( in_array( $active_section, array( 'intrants', 'ventes' ), true ) ) {
        $category = 'intrants' === $active_section ? 'input' : 'sale';
        echo baykat_farm_render_finance_item_section( $category, $process_actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return ob_get_clean();
    }

    if ( in_array( $active_section, array( 'revenus', 'depenses', 'resume' ), true ) ) {
        $type = isset( $sections[ $active_section ]['type'] )
            ? $sections[ $active_section ]['type']
            : 'all';
        echo baykat_finance_render_frontend_page( $type, $process_actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    return ob_get_clean();
}
