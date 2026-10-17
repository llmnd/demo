<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_parcelles( $process_actions = true ) {
    $user_id = get_current_user_id();
    $message = null;

    if ( $process_actions ) {
        $message = baykat_farm_process_record_action( $user_id, 'parcel' );
        if ( ! $message ) {
            $message = baykat_farm_process_parcel_submission( $user_id );
        }
        if ( ! $message ) {
            $message = baykat_farm_process_image_update( $user_id, 'parcel' );
        }
    }
    $farms = baykat_farm_get_user_farms( $user_id );
    $editable_farms = baykat_farm_filter_editable_records( $farms );
    $parcels = baykat_farm_get_user_parcels( $user_id );
    $statuses = array(
        'planned'   => 'Planifiée',
        'growing'   => 'En cours',
        'harvested' => 'Récoltée',
        'paused'    => 'En pause',
    );
    ?>
    <div class="baykat-farm-content">
        <?php if ( $message ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-<?php echo esc_attr( $message['type'] ); ?>" role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $message['message'] ); ?></div>
        <?php endif; ?>

        <?php if ( $editable_farms ) : ?>
            <section class="baykat-farm-panel baykat-farm-add-panel" aria-labelledby="baykat-farm-parcel-form-heading">
                <details class="baykat-farm-add-form">
                    <summary id="baykat-farm-parcel-form-title"><span class="baykat-farm-eyebrow">Nouvelle parcelle</span><strong id="baykat-farm-parcel-form-heading">Ajouter une parcelle</strong><span class="baykat-farm-add-form-icon" aria-hidden="true">+</span></summary>
                    <form class="baykat-farm-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'baykat_add_parcel', '_wpnonce' ); ?>
                    <p class="baykat-farm-add-form-description">Choisissez d’abord la ferme, puis donnez un nom simple à la parcelle. Sa superficie et sa photo peuvent être ajoutées plus tard.</p>
                    <div class="baykat-farm-field">
                        <label for="baykat_parcel_farm">Ferme</label>
                        <select name="farm_id" id="baykat_parcel_farm" required>
                            <option value="">Choisir une ferme</option>
                            <?php foreach ( $editable_farms as $farm ) : ?>
                                <option value="<?php echo esc_attr( $farm->id ); ?>"><?php echo esc_html( $farm->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_parcel_name">Nom de la parcelle</label>
                        <input type="text" name="parcel_name" id="baykat_parcel_name" maxlength="190" placeholder="Ex. : Champ près du puits" required>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_parcel_area">Superficie (m², facultatif)</label>
                        <input type="number" name="parcel_area_m2" id="baykat_parcel_area" min="0.01" step="0.01">
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_parcel_status">Statut</label>
                        <select name="parcel_status" id="baykat_parcel_status" required>
                            <?php foreach ( $statuses as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php baykat_farm_render_image_field( 'parcel_image', 'Photo de la parcelle' ); ?>
                    <button class="baykat-finance-button" type="submit" name="baykat_add_parcel" value="1">Enregistrer la parcelle</button>
                    </form>
                </details>
            </section>
        <?php elseif ( ! $farms ) : ?>
            <section class="baykat-farm-panel baykat-farm-empty-state">
                <p>Ajoutez d'abord une ferme dans la <a href="<?php echo esc_url( add_query_arg( 'module', 'vue-ensemble', get_permalink() ) ); ?>">Vue d'ensemble</a> pour pouvoir créer ses parcelles.</p>
            </section>
        <?php else : ?>
            <p class="baykat-farm-empty-state">Votre rôle actuel autorise uniquement la consultation des fermes partagées avec vous.</p>
        <?php endif; ?>

        <section class="baykat-farm-panel" aria-labelledby="baykat-farm-parcel-list-title">
            <header class="baykat-farm-panel-heading"><span class="baykat-farm-eyebrow">Votre terrain</span><h3 id="baykat-farm-parcel-list-title">Parcelles enregistrées</h3></header>
            <?php if ( $parcels ) : ?>
                <?php baykat_farm_render_record_filters( 'baykat-farm-parcel-list', 'Rechercher une parcelle', $statuses ); ?>
                <div class="baykat-farm-record-grid baykat-farm-visual-grid" id="baykat-farm-parcel-list">
                    <?php foreach ( $parcels as $parcel ) : ?>
                        <article
                            id="baykat-parcel-record-<?php echo esc_attr( $parcel->id ); ?>"
                            class="baykat-farm-record-card baykat-farm-visual-card"
                            data-farm-record-card
                            data-parcel-record-card
                            data-parcel-farm-id="<?php echo esc_attr( $parcel->farm_id ); ?>"
                            data-parcel-id="<?php echo esc_attr( $parcel->id ); ?>"
                            data-search-text="<?php echo esc_attr( $parcel->farm_name . ' ' . $parcel->name . ' ' . ( isset( $statuses[ $parcel->status ] ) ? $statuses[ $parcel->status ] : $parcel->status ) . ' ' . ( null === $parcel->area_m2 ? '' : $parcel->area_m2 ) ); ?>"
                            data-farm-record-filter-value="<?php echo esc_attr( $parcel->status ); ?>"
                        >
                            <?php echo baykat_farm_render_image( $parcel->image_id, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div class="baykat-farm-visual-card-body">
                                <span class="baykat-farm-eyebrow"><?php echo esc_html( $parcel->farm_name ); ?></span>
                                <h4><?php echo esc_html( $parcel->name ); ?></h4>
                                <div class="baykat-farm-card-meta">
                                    <span><?php echo null === $parcel->area_m2 ? 'Superficie non renseignée' : esc_html( number_format( (float) $parcel->area_m2, 2, ',', ' ' ) . ' m²' ); ?></span>
                                    <span class="baykat-farm-status"><?php echo esc_html( isset( $statuses[ $parcel->status ] ) ? $statuses[ $parcel->status ] : $parcel->status ); ?></span>
                                </div>
                                <button class="baykat-farm-open-record" type="button" data-open-parcel-modal="<?php echo esc_attr( $parcel->id ); ?>">Voir la parcelle</button>
                                <?php if ( in_array( $parcel->access_role, array( 'owner', 'manager' ), true ) ) : ?>
                                <?php baykat_farm_render_image_update_form( 'parcel', $parcel->id, $parcel->image_id ? 'Changer la photo' : 'Ajouter une photo' ); ?>
                                <details class="baykat-farm-record-actions">
                                    <summary>Modifier la parcelle</summary>
                                    <form class="baykat-farm-record-form" method="post">
                                        <?php wp_nonce_field( 'baykat_update_parcel', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_update_parcel_id" value="<?php echo esc_attr( $parcel->id ); ?>">
                                        <div class="baykat-farm-field">
                                            <label for="baykat_parcel_edit_farm_<?php echo esc_attr( $parcel->id ); ?>">Ferme</label>
                                            <select name="farm_id" id="baykat_parcel_edit_farm_<?php echo esc_attr( $parcel->id ); ?>" required>
                                                <?php foreach ( $editable_farms as $farm ) : ?>
                                                    <option value="<?php echo esc_attr( $farm->id ); ?>" <?php selected( (int) $parcel->farm_id, (int) $farm->id ); ?>><?php echo esc_html( $farm->name ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_parcel_edit_name_<?php echo esc_attr( $parcel->id ); ?>">Nom</label>
                                            <input type="text" name="parcel_name" id="baykat_parcel_edit_name_<?php echo esc_attr( $parcel->id ); ?>" maxlength="190" value="<?php echo esc_attr( $parcel->name ); ?>" required>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_parcel_edit_area_<?php echo esc_attr( $parcel->id ); ?>">Superficie (m²)</label>
                                            <input type="number" name="parcel_area_m2" id="baykat_parcel_edit_area_<?php echo esc_attr( $parcel->id ); ?>" min="0.01" step="0.01" value="<?php echo esc_attr( $parcel->area_m2 ); ?>">
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_parcel_edit_status_<?php echo esc_attr( $parcel->id ); ?>">Statut</label>
                                            <select name="parcel_status" id="baykat_parcel_edit_status_<?php echo esc_attr( $parcel->id ); ?>" required>
                                                <?php foreach ( $statuses as $slug => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $parcel->status, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button class="baykat-finance-button" type="submit" name="baykat_update_parcel" value="1">Enregistrer les modifications</button>
                                    </form>
                                    <form class="baykat-farm-delete-form" method="post">
                                        <?php wp_nonce_field( 'baykat_delete_parcel', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_delete_parcel_id" value="<?php echo esc_attr( $parcel->id ); ?>">
                                        <button class="baykat-finance-button baykat-farm-delete-button" type="submit" name="baykat_delete_parcel" value="1">Supprimer la parcelle</button>
                                    </form>
                                </details>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="baykat-farm-empty-state">Aucune parcelle enregistrée pour le moment.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php
}