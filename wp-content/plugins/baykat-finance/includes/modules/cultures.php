<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_cultures( $process_actions = true ) {
    $user_id = get_current_user_id();
    $message = null;

    if ( $process_actions ) {
        $message = baykat_farm_process_crop_harvest( $user_id );
    }
    if ( $process_actions && ! $message ) {
        $message = baykat_farm_process_record_action( $user_id, 'crop' );
        if ( ! $message ) {
            $message = baykat_farm_process_crop_submission( $user_id );
        }
        if ( ! $message ) {
            $message = baykat_farm_process_image_update( $user_id, 'crop' );
        }
    }
    $parcels = baykat_farm_get_user_parcels( $user_id );
    $editable_parcels = baykat_farm_filter_editable_records( $parcels );
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

        <?php if ( $editable_parcels ) : ?>
            <section class="baykat-farm-panel baykat-farm-add-panel" aria-labelledby="baykat-farm-crop-form-title">
                <details class="baykat-farm-add-form">
                    <summary id="baykat-farm-crop-form-title"><span class="baykat-farm-eyebrow">Nouvelle plantation</span><strong>Ajouter une culture</strong><span class="baykat-farm-add-form-icon" aria-hidden="true">+</span></summary>
                    <form class="baykat-farm-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'baykat_add_crop', '_wpnonce' ); ?>
                    <p class="baykat-farm-add-form-description">Reliez la culture à sa parcelle et enregistrez sa plantation. Quand vous récolterez, utilisez « Enregistrer une récolte » sur sa fiche pour saisir la quantité et la date; le statut sera mis à jour automatiquement.</p>
                    <div class="baykat-farm-field baykat-farm-field-wide">
                        <label for="baykat_crop_parcel">Parcelle</label>
                        <select name="parcel_id" id="baykat_crop_parcel" required>
                            <option value="">Choisir une parcelle</option>
                            <?php foreach ( $editable_parcels as $parcel ) : ?>
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
                            <?php foreach ( array_intersect_key( $statuses, array_flip( array( 'planned', 'growing', 'paused' ) ) ) as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php baykat_farm_render_image_field( 'crop_image', 'Photo de la culture' ); ?>
                    <button class="baykat-finance-button" type="submit" name="baykat_add_crop" value="1">Enregistrer la culture</button>
                    </form>
                </details>
            </section>
        <?php elseif ( ! $parcels ) : ?>
            <section class="baykat-farm-panel baykat-farm-empty-state"><p>Créez une ferme et une parcelle avant d’ajouter une culture.</p></section>
        <?php else : ?>
            <p class="baykat-farm-empty-state">Votre rôle actuel autorise uniquement la consultation des cultures partagées avec vous.</p>
        <?php endif; ?>

        <section class="baykat-farm-panel" aria-labelledby="baykat-farm-crop-list-title">
            <header class="baykat-farm-panel-heading"><span class="baykat-farm-eyebrow">Cultures suivies</span><h3 id="baykat-farm-crop-list-title">Cultures enregistrées</h3></header>
            <?php if ( $crops ) : ?>
                <?php baykat_farm_render_record_filters( 'baykat-farm-crop-list', 'Rechercher une culture', $statuses ); ?>
                <div class="baykat-farm-record-grid baykat-farm-visual-grid" id="baykat-farm-crop-list">
                    <?php foreach ( $crops as $crop ) : ?>
                        <article
                            class="baykat-farm-record-card baykat-farm-visual-card"
                            data-farm-record-card
                            data-farm-id="<?php echo esc_attr( $crop->farm_id ); ?>"
                            data-parcel-id="<?php echo esc_attr( $crop->parcel_id ); ?>"
                            data-search-text="<?php echo esc_attr( $crop->farm_name . ' ' . $crop->parcel_name . ' ' . $crop->name . ' ' . $crop->variety . ' ' . ( isset( $statuses[ $crop->status ] ) ? $statuses[ $crop->status ] : $crop->status ) . ' ' . $crop->planted_at . ' ' . $crop->expected_harvest_date ); ?>"
                            data-farm-record-filter-value="<?php echo esc_attr( $crop->status ); ?>"
                        >
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
                                <?php if ( ! empty( $crop->harvested_quantity ) ) : ?>
                                    <p class="baykat-farm-card-dates">Récolté : <?php echo esc_html( number_format( (float) $crop->harvested_quantity, 3, ',', ' ' ) . ' ' . $crop->harvested_unit ); ?></p>
                                <?php endif; ?>
                                <?php if ( in_array( $crop->access_role, array( 'owner', 'manager' ), true ) ) : ?>
                                <?php if ( in_array( $crop->status, array( 'growing', 'harvested' ), true ) ) : ?>
                                <?php $crop_harvests = baykat_farm_get_crop_harvests( (int) $crop->id ); ?>
                                <details class="baykat-farm-record-actions baykat-farm-harvest-actions">
                                    <summary>Enregistrer une récolte</summary>
                                    <form class="baykat-farm-form baykat-farm-harvest-form" method="post">
                                        <?php wp_nonce_field( 'baykat_record_harvest', '_wpnonce' ); ?>
                                        <input type="hidden" name="crop_id" value="<?php echo esc_attr( $crop->id ); ?>">
                                        <p class="baykat-farm-add-form-description">Saisissez la quantité obtenue lors de cette récolte, pas le total cumulé. Le total de <?php echo esc_html( $crop->name ); ?> sera recalculé automatiquement.</p>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_crop_harvest_quantity_<?php echo esc_attr( $crop->id ); ?>">Quantité récoltée</label>
                                            <input type="number" name="harvest_quantity" id="baykat_crop_harvest_quantity_<?php echo esc_attr( $crop->id ); ?>" min="0.001" max="999999999999.999" step="0.001" inputmode="decimal" placeholder="Ex. : 10" required>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_crop_harvest_unit_<?php echo esc_attr( $crop->id ); ?>">Unité</label>
                                            <input type="text" name="harvest_unit" id="baykat_crop_harvest_unit_<?php echo esc_attr( $crop->id ); ?>" maxlength="40" placeholder="Ex. : kg, panier" value="<?php echo esc_attr( $crop->harvested_unit ); ?>" <?php echo $crop->harvested_unit ? 'readonly' : ''; ?> required>
                                            <?php if ( $crop->harvested_unit ) : ?>
                                                <span class="baykat-farm-field-help">L’unité est conservée pour toutes les récoltes de cette culture.</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_crop_harvest_date_<?php echo esc_attr( $crop->id ); ?>">Date de récolte</label>
                                            <input type="date" name="harvest_date" id="baykat_crop_harvest_date_<?php echo esc_attr( $crop->id ); ?>" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                                        </div>
                                        <div class="baykat-farm-field baykat-farm-field-wide">
                                            <label for="baykat_crop_harvest_notes_<?php echo esc_attr( $crop->id ); ?>">Note (facultatif)</label>
                                            <textarea name="harvest_notes" id="baykat_crop_harvest_notes_<?php echo esc_attr( $crop->id ); ?>" rows="2" placeholder="Ex. : première cueillette"></textarea>
                                        </div>
                                        <button class="baykat-finance-button" type="submit" name="baykat_record_harvest" value="1">Enregistrer cette récolte</button>
                                    </form>
                                    <?php if ( $crop_harvests ) : ?>
                                        <details class="baykat-farm-harvest-history">
                                            <summary>Journal des récoltes (<?php echo esc_html( number_format_i18n( count( $crop_harvests ) ) ); ?>)</summary>
                                            <ul>
                                                <?php foreach ( $crop_harvests as $harvest ) : ?>
                                                    <li>
                                                        <strong><?php echo esc_html( number_format( (float) $harvest->quantity, 3, ',', ' ' ) . ' ' . $harvest->unit ); ?></strong>
                                                        <span><?php echo esc_html( $harvest->harvest_date ? mysql2date( 'j M Y', $harvest->harvest_date ) : 'Date antérieure non précisée' ); ?></span>
                                                        <?php $harvest_recorder = get_userdata( (int) $harvest->recorded_by ); ?>
                                                        <?php if ( $harvest_recorder ) : ?>
                                                            <small>Enregistrée par <?php echo esc_html( $harvest_recorder->display_name ); ?></small>
                                                        <?php endif; ?>
                                                        <?php if ( $harvest->notes ) : ?>
                                                            <small><?php echo esc_html( $harvest->notes ); ?></small>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php endif; ?>
                                </details>
                                <?php endif; ?>
                                <?php baykat_farm_render_image_update_form( 'crop', $crop->id, $crop->image_id ? 'Changer la photo' : 'Ajouter une photo' ); ?>
                                <details class="baykat-farm-record-actions">
                                    <summary>Modifier la culture</summary>
                                    <form class="baykat-farm-record-form" method="post">
                                        <?php wp_nonce_field( 'baykat_update_crop', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_update_crop_id" value="<?php echo esc_attr( $crop->id ); ?>">
                                        <div class="baykat-farm-field">
                                            <label for="baykat_crop_edit_parcel_<?php echo esc_attr( $crop->id ); ?>">Parcelle</label>
                                            <select name="parcel_id" id="baykat_crop_edit_parcel_<?php echo esc_attr( $crop->id ); ?>" required>
                                                <?php foreach ( $editable_parcels as $parcel ) : ?>
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
                                                    <?php if ( ( 'harvested' !== $slug || 'harvested' === $crop->status ) && ( 'harvested' !== $crop->status || (float) $crop->harvested_quantity <= 0 || 'harvested' === $slug ) ) : ?>
                                                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $crop->status, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                                                    <?php endif; ?>
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
                                <?php endif; ?>
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
