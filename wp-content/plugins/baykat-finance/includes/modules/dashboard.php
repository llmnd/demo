<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_dashboard() {
    $user_id = get_current_user_id();
    $message = baykat_farm_process_record_action( $user_id, 'farm' );
    if ( ! $message ) {
        $message = baykat_farm_process_farm_submission( $user_id );
    }
    if ( ! $message ) {
        $message = baykat_farm_process_image_update( $user_id, 'farm' );
    }
    $farms = baykat_farm_get_user_farms( $user_id );

    global $wpdb;
    $transactions = baykat_finance_get_user_transactions( $user_id );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $crops_table = baykat_farm_table_name( 'crops' );
    $activities_table = baykat_farm_table_name( 'activities' );
    $counts = array(
        'parcels'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $parcels_table WHERE user_id = %d", $user_id ) ),
        'crops'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $crops_table WHERE user_id = %d", $user_id ) ),
        'activities' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $activities_table WHERE user_id = %d", $user_id ) ),
    );
    ?>
    <div class="baykat-farm-content">
        <header class="baykat-farm-section-heading">
            <span class="baykat-farm-eyebrow">Votre espace agricole</span>
            <h2>Vue d’ensemble</h2>
            <p>Enregistrez vos fermes et retrouvez les principaux indicateurs de votre activité.</p>
        </header>

        <?php if ( $message ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-<?php echo esc_attr( $message['type'] ); ?>" role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>">
                <?php echo esc_html( $message['message'] ); ?>
            </div>
        <?php endif; ?>

        <div class="baykat-farm-stat-grid">
            <article class="baykat-farm-stat-card">
                <span>Fermes</span>
                <strong><?php echo esc_html( number_format_i18n( count( $farms ) ) ); ?></strong>
            </article>
            <article class="baykat-farm-stat-card">
                <span>Parcelles</span>
                <strong><?php echo esc_html( number_format_i18n( $counts['parcels'] ) ); ?></strong>
            </article>
            <article class="baykat-farm-stat-card">
                <span>Cultures</span>
                <strong><?php echo esc_html( number_format_i18n( $counts['crops'] ) ); ?></strong>
            </article>
            <article class="baykat-farm-stat-card">
                <span>Activités</span>
                <strong><?php echo esc_html( number_format_i18n( $counts['activities'] ) ); ?></strong>
            </article>
            <article class="baykat-farm-stat-card">
                <span>Solde Finance</span>
                <strong><?php echo esc_html( number_format( $transactions['balance'], 0, ',', ' ' ) ); ?> FCFA</strong>
            </article>
        </div>

        <section id="baykat-farm-add-title" class="baykat-farm-panel" aria-labelledby="baykat-farm-add-heading">
            <header class="baykat-farm-panel-heading">
                <span class="baykat-farm-eyebrow">Nouvelle exploitation</span>
                <h3 id="baykat-farm-add-heading">Ajouter une ferme</h3>
            </header>
            <form class="baykat-farm-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'baykat_add_farm', '_wpnonce' ); ?>
                <div class="baykat-farm-field">
                    <label for="baykat_farm_name">Nom de la ferme</label>
                    <input type="text" name="farm_name" id="baykat_farm_name" maxlength="190" required>
                </div>
                <div class="baykat-farm-field">
                    <label for="baykat_farm_location">Localisation</label>
                    <input type="text" name="farm_location" id="baykat_farm_location" maxlength="190" placeholder="Village, commune ou région">
                </div>
                <div class="baykat-farm-field">
                    <label for="baykat_farm_area_ha">Superficie (hectares, facultatif)</label>
                    <input type="number" name="farm_area_ha" id="baykat_farm_area_ha" min="0.001" step="0.001">
                </div>
                <?php baykat_farm_render_image_field( 'farm_image', 'Photo de la ferme' ); ?>
                <button class="baykat-finance-button" type="submit" name="baykat_add_farm" value="1">Enregistrer la ferme</button>
            </form>
        </section>

        <section class="baykat-farm-panel" aria-labelledby="baykat-farm-list-title">
            <header class="baykat-farm-panel-heading">
                <span class="baykat-farm-eyebrow">Mes exploitations</span>
                <h3 id="baykat-farm-list-title">Fermes enregistrées</h3>
            </header>
            <?php if ( $farms ) : ?>
                <div class="baykat-farm-record-grid baykat-farm-visual-grid">
                    <?php foreach ( $farms as $farm ) : ?>
                        <article class="baykat-farm-record-card baykat-farm-visual-card">
                            <?php echo baykat_farm_render_image( $farm->image_id, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div class="baykat-farm-visual-card-body">
                                <h4><?php echo esc_html( $farm->name ); ?></h4>
                                <p><?php echo esc_html( $farm->location ? $farm->location : 'Localisation non renseignée' ); ?></p>
                                <?php if ( null !== $farm->area_ha ) : ?>
                                    <span><?php echo esc_html( number_format( (float) $farm->area_ha, 3, ',', ' ' ) ); ?> ha</span>
                                <?php endif; ?>
                                <?php baykat_farm_render_image_update_form( 'farm', $farm->id, $farm->image_id ? 'Changer la photo' : 'Ajouter une photo' ); ?>
                                <details class="baykat-farm-record-actions">
                                    <summary>Modifier la ferme</summary>
                                    <form class="baykat-farm-record-form" method="post">
                                        <?php wp_nonce_field( 'baykat_update_farm', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_update_farm_id" value="<?php echo esc_attr( $farm->id ); ?>">
                                        <div class="baykat-farm-field">
                                            <label for="baykat_farm_edit_name_<?php echo esc_attr( $farm->id ); ?>">Nom</label>
                                            <input type="text" name="farm_name" id="baykat_farm_edit_name_<?php echo esc_attr( $farm->id ); ?>" maxlength="190" value="<?php echo esc_attr( $farm->name ); ?>" required>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_farm_edit_location_<?php echo esc_attr( $farm->id ); ?>">Localisation</label>
                                            <input type="text" name="farm_location" id="baykat_farm_edit_location_<?php echo esc_attr( $farm->id ); ?>" maxlength="190" value="<?php echo esc_attr( $farm->location ); ?>">
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_farm_edit_area_<?php echo esc_attr( $farm->id ); ?>">Superficie (ha)</label>
                                            <input type="number" name="farm_area_ha" id="baykat_farm_edit_area_<?php echo esc_attr( $farm->id ); ?>" min="0.001" step="0.001" value="<?php echo esc_attr( $farm->area_ha ); ?>">
                                        </div>
                                        <button class="baykat-finance-button" type="submit" name="baykat_update_farm" value="1">Enregistrer les modifications</button>
                                    </form>
                                    <form class="baykat-farm-delete-form" method="post">
                                        <?php wp_nonce_field( 'baykat_delete_farm', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_delete_farm_id" value="<?php echo esc_attr( $farm->id ); ?>">
                                        <button class="baykat-finance-button baykat-farm-delete-button" type="submit" name="baykat_delete_farm" value="1">Supprimer la ferme</button>
                                    </form>
                                </details>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="baykat-farm-empty-state">Aucune ferme enregistrée. Ajoutez votre première ferme ci-dessus.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php
}
