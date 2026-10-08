<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_cultures() {
    $user_id = get_current_user_id();
    $message = baykat_farm_process_record_action( $user_id, 'crop' );
    if ( ! $message ) {
        $message = baykat_farm_process_crop_submission( $user_id );
    }
    if ( ! $message ) {
        $message = baykat_farm_process_image_update( $user_id, 'crop' );
    }
    $parcels = baykat_farm_get_user_parcels( $user_id );
    $crops = baykat_farm_get_user_crops( $user_id );
    $statuses = array(
        'planned'   => 'Planifiée',
        'growing'   => 'En croissance',
        'harvested' => 'Récoltée',
        'paused'    => 'En pause',
    );
    ?>
    <div class="baykat-farm-content">
        <header class="baykat-farm-section-heading">
            <span class="baykat-farm-eyebrow">Suivi des plantations</span>
            <h2>Cultures</h2>
            <p>Enregistrez les cultures et leurs dates sur vos parcelles.</p>
        </header>
        <?php if ( $message ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-<?php echo esc_attr( $message['type'] ); ?>" role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $message['message'] ); ?></div>
        <?php endif; ?>

        <?php if ( $parcels ) : ?>
            <section class="baykat-farm-panel" aria-labelledby="baykat-farm-crop-form-title">
                <header class="baykat-farm-panel-heading"><span class="baykat-farm-eyebrow">Nouvelle plantation</span><h3 id="baykat-farm-crop-form-title">Ajouter une culture</h3></header>
                <form class="baykat-farm-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'baykat_add_crop', '_wpnonce' ); ?>
                    <div class="baykat-farm-field baykat-farm-field-wide">
                        <label for="baykat_crop_parcel">Parcelle</label>
                        <select name="parcel_id" id="baykat_crop_parcel" required>
                            <option value="">Choisir une parcelle</option>
                            <?php foreach ( $parcels as $parcel ) : ?>
                                <option value="<?php echo esc_attr( $parcel->id ); ?>"><?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_crop_name">Culture</label>
                        <input type="text" name="crop_name" id="baykat_crop_name" maxlength="190" placeholder="Ex. : Tomate" required>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_crop_variety">Variété (facultatif)</label>
                        <input type="text" name="crop_variety" id="baykat_crop_variety" maxlength="190">
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_planted_at">Date de plantation</label>
                        <input type="date" name="planted_at" id="baykat_planted_at">
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_expected_harvest_date">Récolte prévue</label>
                        <input type="date" name="expected_harvest_date" id="baykat_expected_harvest_date">
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_crop_status">Statut</label>
                        <select name="crop_status" id="baykat_crop_status" required>
                            <?php foreach ( $statuses as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php baykat_farm_render_image_field( 'crop_image', 'Photo de la culture' ); ?>
                    <button class="baykat-finance-button" type="submit" name="baykat_add_crop" value="1">Enregistrer la culture</button>
                </form>
            </section>
        <?php else : ?>
            <section class="baykat-farm-panel baykat-farm-empty-state"><p>Créez une ferme et une parcelle avant d’ajouter une culture.</p></section>
        <?php endif; ?>

        <section class="baykat-farm-panel" aria-labelledby="baykat-farm-crop-list-title">
            <header class="baykat-farm-panel-heading"><span class="baykat-farm-eyebrow">Cultures suivies</span><h3 id="baykat-farm-crop-list-title">Cultures enregistrées</h3></header>
            <?php if ( $crops ) : ?>
                <div class="baykat-farm-record-grid baykat-farm-visual-grid">
                    <?php foreach ( $crops as $crop ) : ?>
                        <article class="baykat-farm-record-card baykat-farm-visual-card">
                            <?php echo baykat_farm_render_image( $crop->image_id, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div class="baykat-farm-visual-card-body">
                                <span class="baykat-farm-eyebrow"><?php echo esc_html( $crop->farm_name . ' — ' . $crop->parcel_name ); ?></span>
                                <h4><?php echo esc_html( $crop->name ); ?></h4>
                                <div class="baykat-farm-card-meta">
                                    <span><?php echo esc_html( $crop->variety ? $crop->variety : 'Variété non renseignée' ); ?></span>
                                    <span class="baykat-farm-status"><?php echo esc_html( isset( $statuses[ $crop->status ] ) ? $statuses[ $crop->status ] : $crop->status ); ?></span>
                                </div>
                                <p class="baykat-farm-card-dates">
                                    Plantation : <?php echo esc_html( $crop->planted_at ? $crop->planted_at : '—' ); ?>
                                    <span aria-hidden="true"> · </span>
                                    Récolte prévue : <?php echo esc_html( $crop->expected_harvest_date ? $crop->expected_harvest_date : '—' ); ?>
                                </p>
                                <?php baykat_farm_render_image_update_form( 'crop', $crop->id, $crop->image_id ? 'Changer la photo' : 'Ajouter une photo' ); ?>
                                <details class="baykat-farm-record-actions">
                                    <summary>Modifier la culture</summary>
                                    <form class="baykat-farm-record-form" method="post">
                                        <?php wp_nonce_field( 'baykat_update_crop', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_update_crop_id" value="<?php echo esc_attr( $crop->id ); ?>">
                                        <div class="baykat-farm-field">
                                            <label for="baykat_crop_edit_parcel_<?php echo esc_attr( $crop->id ); ?>">Parcelle</label>
                                            <select name="parcel_id" id="baykat_crop_edit_parcel_<?php echo esc_attr( $crop->id ); ?>" required>
                                                <?php foreach ( $parcels as $parcel ) : ?>
                                                    <option value="<?php echo esc_attr( $parcel->id ); ?>" <?php selected( (int) $crop->parcel_id, (int) $parcel->id ); ?>><?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_crop_edit_name_<?php echo esc_attr( $crop->id ); ?>">Culture</label>
                                            <input type="text" name="crop_name" id="baykat_crop_edit_name_<?php echo esc_attr( $crop->id ); ?>" maxlength="190" value="<?php echo esc_attr( $crop->name ); ?>" required>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_crop_edit_variety_<?php echo esc_attr( $crop->id ); ?>">Variété</label>
                                            <input type="text" name="crop_variety" id="baykat_crop_edit_variety_<?php echo esc_attr( $crop->id ); ?>" maxlength="190" value="<?php echo esc_attr( $crop->variety ); ?>">
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_crop_edit_planted_<?php echo esc_attr( $crop->id ); ?>">Date de plantation</label>
                                            <input type="date" name="planted_at" id="baykat_crop_edit_planted_<?php echo esc_attr( $crop->id ); ?>" value="<?php echo esc_attr( $crop->planted_at ); ?>">
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_crop_edit_harvest_<?php echo esc_attr( $crop->id ); ?>">Récolte prévue</label>
                                            <input type="date" name="expected_harvest_date" id="baykat_crop_edit_harvest_<?php echo esc_attr( $crop->id ); ?>" value="<?php echo esc_attr( $crop->expected_harvest_date ); ?>">
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_crop_edit_status_<?php echo esc_attr( $crop->id ); ?>">Statut</label>
                                            <select name="crop_status" id="baykat_crop_edit_status_<?php echo esc_attr( $crop->id ); ?>" required>
                                                <?php foreach ( $statuses as $slug => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $crop->status, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button class="baykat-finance-button" type="submit" name="baykat_update_crop" value="1">Enregistrer les modifications</button>
                                    </form>
                                    <form class="baykat-farm-delete-form" method="post">
                                        <?php wp_nonce_field( 'baykat_delete_crop', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_delete_crop_id" value="<?php echo esc_attr( $crop->id ); ?>">
                                        <button class="baykat-finance-button baykat-farm-delete-button" type="submit" name="baykat_delete_crop" value="1">Supprimer la culture</button>
                                    </form>
                                </details>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="baykat-farm-empty-state">Aucune culture enregistrée pour le moment.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php
}
