<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_finance_item_section( $category, $process_actions = true ) {
    if ( ! in_array( $category, array( 'input', 'sale' ), true ) ) {
        return '';
    }
    if ( 'sale' === $category ) {
        return '';
    }

    $user_id = get_current_user_id();
    $message = $process_actions
        ? baykat_finance_process_sale_cancellation( $user_id )
        : null;
    if ( $process_actions && ! $message ) {
        $message = baykat_finance_process_transaction_submission( $user_id, $category );
    }
    $finance_data = baykat_finance_get_user_transactions( $user_id, $category );
    $parcels = baykat_farm_get_user_parcels( $user_id );
    $editable_parcels = baykat_farm_filter_editable_records( $parcels );
    $farms = baykat_farm_get_user_farms( $user_id );
    $activities = baykat_farm_get_user_activities( $user_id );
    $editable_activities = baykat_farm_filter_editable_records( $activities );
    $crops = baykat_farm_get_user_crops( $user_id );
    $editable_crops = baykat_farm_filter_editable_records( $crops );
    $campaigns = baykat_farm_campaign_get_user_campaigns( $user_id );
    $campaigns_error = is_wp_error( $campaigns );
    if ( $campaigns_error ) {
        $campaigns = array();
    }
    $editable_campaigns = array_values(
        array_filter(
            $campaigns,
            function ( $campaign ) use ( $user_id ) {
                return 'real' === $campaign->campaign_type
                    && baykat_farm_user_can_edit_farm( (int) $campaign->farm_id, $user_id );
            }
        )
    );
    $sale_crops = 'sale' === $category ? baykat_finance_get_harvestable_crops( $user_id ) : array();
    $sale_crops_error = is_wp_error( $sale_crops );
    if ( $sale_crops_error ) {
        $sale_crops = array();
    }
    $campaign_by_crop = array();
    foreach ( $editable_campaigns as $campaign ) {
        $campaign_by_crop[ (int) $campaign->crop_id ] = (int) $campaign->id;
    }
    foreach ( $sale_crops as $sale_crop ) {
        $sale_crop->campaign_id = isset( $campaign_by_crop[ (int) $sale_crop->id ] )
            ? $campaign_by_crop[ (int) $sale_crop->id ]
            : 0;
    }
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

    if ( 'intrants' === $active_section ) {
        echo baykat_farm_render_finance_item_section( 'input', $process_actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
