<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_report_farm_ids( $user_id ) {
    $farms = baykat_farm_get_user_farms( $user_id );
    return array_map( 'intval', wp_list_pluck( $farms, 'id' ) );
}

function baykat_farm_report_csv_value( $value ) {
    $value = (string) $value;
    if ( is_numeric( $value ) ) {
        return $value;
    }
    if ( preg_match( '/^[\s]*[=+\-@]/', $value ) ) {
        return "'" . $value;
    }
    return $value;
}

function baykat_farm_export_report_csv() {
    if ( ! is_user_logged_in() || ! baykat_finance_user_can_access() ) {
        wp_die( esc_html__( 'Accès refusé.', 'baykat-finance' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'baykat_export_farm_csv' );

    global $wpdb;
    $user_id = get_current_user_id();
    $farm_ids = baykat_farm_report_farm_ids( $user_id );
    if ( ! $farm_ids ) {
        wp_die( esc_html__( 'Aucune ferme accessible à exporter.', 'baykat-finance' ), '', array( 'response' => 404 ) );
    }

    $farm_scope = implode( ', ', $farm_ids );
    $transactions_table = baykat_finance_get_table_name();
    $farms_table = baykat_farm_table_name( 'farms' );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $crops_table = baykat_farm_table_name( 'crops' );
    $today = current_time( 'Y-m-d' );
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="baykat-rapport-agricole-' . $today . '.csv"' );
    $output = fopen( 'php://output', 'w' );
    if ( false === $output ) {
        wp_die( esc_html__( 'Impossible de générer le fichier CSV.', 'baykat-finance' ), '', array( 'response' => 500 ) );
    }
    fwrite( $output, "\xEF\xBB\xBF" );
    fputcsv( $output, array( 'Enregistrement', 'Date d’opération', 'Date de saisie', 'État', 'Ferme', 'Parcelle', 'Culture', 'Détail', 'Quantité', 'Unité', 'Revenus FCFA', 'Dépenses FCFA', 'Marge FCFA' ), ';' );

    $last_id = 0;
    do {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT transactions.id, transactions.operation_date, transactions.created_at, transactions.type, transactions.category,
                        transactions.description, transactions.item_name, transactions.quantity, transactions.unit,
                        transactions.amount, transactions.cancelled_at, farms.name AS farm_name, parcels.name AS parcel_name, crops.name AS crop_name
                 FROM $transactions_table AS transactions
                 LEFT JOIN $farms_table AS farms ON farms.id = transactions.farm_id
                 LEFT JOIN $parcels_table AS parcels ON parcels.id = transactions.parcel_id
                 LEFT JOIN $crops_table AS crops ON crops.id = transactions.crop_id
                 WHERE transactions.farm_id IN ($farm_scope) AND transactions.id > %d
                 ORDER BY transactions.id ASC
                 LIMIT 500",
                $last_id
            )
        );
        foreach ( (array) $rows as $row ) {
            $is_cancelled = ! empty( $row->cancelled_at );
            $income = ! $is_cancelled && 'income' === $row->type ? (float) $row->amount : 0;
            $expense = ! $is_cancelled && 'expense' === $row->type ? (float) $row->amount : 0;
            fputcsv(
                $output,
                array_map(
                    'baykat_farm_report_csv_value',
                    array(
                        'Transaction ' . $row->category,
                        $row->operation_date,
                        $row->created_at,
                        $is_cancelled ? 'Annulée le ' . $row->cancelled_at : 'Active',
                        $row->farm_name,
                        $row->parcel_name,
                        $row->crop_name,
                        $row->description ? $row->description : $row->item_name,
                        $row->quantity,
                        $row->unit,
                        number_format( $income, 2, '.', '' ),
                        number_format( $expense, 2, '.', '' ),
                        number_format( $income - $expense, 2, '.', '' ),
                    )
                ),
                ';'
            );
            $last_id = (int) $row->id;
        }
    } while ( count( (array) $rows ) === 500 );

    $crops = $wpdb->get_results(
        "SELECT crops.id, crops.name, crops.harvested_quantity, crops.harvested_unit,
                farms.name AS farm_name, parcels.name AS parcel_name,
                COALESCE(SUM(CASE WHEN transactions.type = 'income' AND transactions.cancelled_at IS NULL THEN transactions.amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN transactions.type = 'expense' AND transactions.cancelled_at IS NULL THEN transactions.amount ELSE 0 END), 0) AS expense
         FROM $crops_table AS crops
         INNER JOIN $parcels_table AS parcels ON parcels.id = crops.parcel_id
         INNER JOIN $farms_table AS farms ON farms.id = parcels.farm_id
         LEFT JOIN $transactions_table AS transactions ON transactions.crop_id = crops.id
         WHERE farms.id IN ($farm_scope)
         GROUP BY crops.id, crops.name, crops.harvested_quantity, crops.harvested_unit, farms.name, parcels.name
         ORDER BY farms.name ASC, parcels.name ASC, crops.name ASC"
    );
    foreach ( (array) $crops as $crop ) {
        fputcsv(
            $output,
            array_map(
                'baykat_farm_report_csv_value',
                array(
                    'Rentabilité culture',
                    '',
                    $crop->farm_name,
                    $crop->parcel_name,
                    $crop->name,
                    '',
                    $crop->harvested_quantity,
                    $crop->harvested_unit,
                    number_format( (float) $crop->income, 2, '.', '' ),
                    number_format( (float) $crop->expense, 2, '.', '' ),
                    number_format( (float) $crop->income - (float) $crop->expense, 2, '.', '' ),
                )
            ),
            ';'
        );
    }
    fclose( $output );
    exit;
}
add_action( 'admin_post_baykat_export_farm_csv', 'baykat_farm_export_report_csv' );

function baykat_farm_render_reports() {
    $user_id = get_current_user_id();
    $farms = baykat_farm_get_user_farms( $user_id );
    ?>
    <div class="baykat-farm-content">
        <header class="baykat-farm-section-heading">
            <span class="baykat-farm-eyebrow">Téléchargements</span>
            <h2>Rapports et exports</h2>
            <p>Exportez les transactions accessibles et la rentabilité directe des cultures au format CSV, compatible avec les tableurs.</p>
        </header>
        <?php if ( $farms ) : ?>
            <section class="baykat-farm-panel">
                <h3>Rapport agricole complet</h3>
                <p>Le fichier comprend les revenus, dépenses, quantités achetées, récoltes saisies et marges calculées pour les cultures auxquelles des transactions sont associées.</p>
                <a class="baykat-finance-button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=baykat_export_farm_csv' ), 'baykat_export_farm_csv' ) ); ?>">Télécharger le rapport CSV</a>
            </section>
        <?php else : ?>
            <p class="baykat-farm-empty-state">Aucune ferme accessible à exporter.</p>
        <?php endif; ?>
    </div>
    <?php
}
