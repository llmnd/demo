<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_archive_action( $type, $id, $intent, $label ) {
    ?>
    <form method="post" class="baykat-farm-archive-action">
        <?php wp_nonce_field( 'baykat_' . $intent . '_' . $type . '_' . $id, '_wpnonce' ); ?>
        <input type="hidden" name="archive_type" value="<?php echo esc_attr( $type ); ?>">
        <input type="hidden" name="archive_id" value="<?php echo esc_attr( $id ); ?>">
        <input type="hidden" name="archive_intent" value="<?php echo esc_attr( $intent ); ?>">
        <button class="baykat-finance-button" type="submit" name="baykat_archive_record" value="1"><?php echo esc_html( $label ); ?></button>
    </form>
    <?php
}

function baykat_farm_render_archives() {
    $user_id = get_current_user_id();
    $message = baykat_farm_process_archive_submission( $user_id );
    global $wpdb;

    $farms_table = baykat_farm_table_name( 'farms' );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $campaigns_table = baykat_farm_campaign_table_name();
    $farms = $wpdb->get_results(
        $wpdb->prepare( "SELECT id, name, archived_at FROM $farms_table WHERE user_id = %d AND archived_at IS NOT NULL ORDER BY archived_at DESC", $user_id )
    );
    $query_failed = '' !== $wpdb->last_error;
    $parcels = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT parcels.id, parcels.name, parcels.archived_at, farms.name AS farm_name
             FROM $parcels_table AS parcels
             INNER JOIN $farms_table AS farms ON farms.id = parcels.farm_id
             WHERE farms.user_id = %d AND parcels.archived_at IS NOT NULL
             ORDER BY parcels.archived_at DESC",
            $user_id
        )
    );
    $query_failed = $query_failed || '' !== $wpdb->last_error;
    $campaigns = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT campaigns.id, campaigns.campaign_type, campaigns.crop_name, campaigns.archived_at,
                    farms.name AS farm_name, parcels.name AS parcel_name
             FROM $campaigns_table AS campaigns
             LEFT JOIN $farms_table AS farms ON farms.id = campaigns.farm_id
             LEFT JOIN $parcels_table AS parcels ON parcels.id = campaigns.parcel_id
             WHERE campaigns.archived_at IS NOT NULL
                AND ((campaigns.campaign_type = 'simulation' AND campaigns.user_id = %d)
                    OR (campaigns.campaign_type = 'real' AND farms.user_id = %d))
             ORDER BY campaigns.archived_at DESC",
            $user_id,
            $user_id
        )
    );
    $query_failed = $query_failed || '' !== $wpdb->last_error;

    if ( $query_failed ) {
        $message = array( 'type' => 'error', 'message' => 'Impossible de charger les archives. Veuillez réessayer.' );
        $farms = $parcels = $campaigns = array();
    }
    ?>
    <div class="baykat-farm-content">
        <header class="baykat-farm-section-heading">
            <span class="baykat-farm-eyebrow">Historique conservé</span>
            <h2>Archives</h2>
            <p>Les fermes, parcelles et campagnes archivées restent conservées. Les éléments restaurés réapparaissent dans les listes actives.</p>
        </header>
        <?php if ( $message ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-<?php echo esc_attr( $message['type'] ); ?>" role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $message['message'] ); ?></div>
        <?php endif; ?>

        <?php foreach ( array( 'Fermes' => $farms, 'Parcelles' => $parcels, 'Campagnes' => $campaigns ) as $label => $records ) : ?>
            <section class="baykat-farm-panel">
                <header class="baykat-farm-panel-heading"><h3><?php echo esc_html( $label ); ?></h3></header>
                <?php if ( $records ) : ?>
                    <ul class="baykat-farm-archive-list">
                        <?php foreach ( $records as $record ) : ?>
                            <?php
                            $type = 'Fermes' === $label ? 'farm' : ( 'Parcelles' === $label ? 'parcel' : 'campaign' );
                            $description = isset( $record->farm_name )
                                ? $record->farm_name . ' — ' . $record->name
                                : ( isset( $record->crop_name ) ? $record->crop_name . ( 'simulation' === $record->campaign_type ? ' — Simulation' : ' — ' . $record->farm_name . ' / ' . $record->parcel_name ) : $record->name );
                            ?>
                            <li>
                                <span><strong><?php echo esc_html( $description ); ?></strong><small>Archivé le <?php echo esc_html( $record->archived_at ); ?></small></span>
                                <?php baykat_farm_render_archive_action( $type, (int) $record->id, 'restore', 'Restaurer' ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="baykat-farm-empty-state">Aucun élément archivé.</p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <?php
}
