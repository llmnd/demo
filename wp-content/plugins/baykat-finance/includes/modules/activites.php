<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_activites() {
    $user_id = get_current_user_id();
    $message = baykat_farm_process_record_action( $user_id, 'activity' );
    if ( ! $message ) {
        $message = baykat_farm_process_activity_submission( $user_id );
    }
    if ( ! $message ) {
        $message = baykat_farm_process_image_update( $user_id, 'activity' );
    }
    $parcels = baykat_farm_get_user_parcels( $user_id );
    $activities = baykat_farm_get_user_activities( $user_id );
    $activity_types = array(
        'sowing'      => 'Semis',
        'watering'    => 'Arrosage',
        'fertilizing' => 'Fertilisation',
        'treatment'   => 'Traitement',
        'harvest'     => 'Récolte',
        'other'       => 'Autre',
    );
    ?>
    <div class="baykat-farm-content">
        <header class="baykat-farm-section-heading">
            <span class="baykat-farm-eyebrow">Journal agricole</span>
            <h2>Activités</h2>
            <p>Consignez les opérations réalisées sur chacune de vos parcelles.</p>
        </header>
        <?php if ( $message ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-<?php echo esc_attr( $message['type'] ); ?>" role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $message['message'] ); ?></div>
        <?php endif; ?>

        <?php if ( $parcels ) : ?>
            <section class="baykat-farm-panel" aria-labelledby="baykat-farm-activity-form-title">
                <header class="baykat-farm-panel-heading"><span class="baykat-farm-eyebrow">Nouvelle opération</span><h3 id="baykat-farm-activity-form-title">Ajouter une activité</h3></header>
                <form class="baykat-farm-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'baykat_add_activity', '_wpnonce' ); ?>
                    <div class="baykat-farm-field baykat-farm-field-wide">
                        <label for="baykat_activity_parcel">Parcelle</label>
                        <select name="parcel_id" id="baykat_activity_parcel" required>
                            <option value="">Choisir une parcelle</option>
                            <?php foreach ( $parcels as $parcel ) : ?>
                                <option value="<?php echo esc_attr( $parcel->id ); ?>"><?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_activity_type">Type d’activité</label>
                        <select name="activity_type" id="baykat_activity_type" required>
                            <?php foreach ( $activity_types as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_activity_title">Titre</label>
                        <input type="text" name="activity_title" id="baykat_activity_title" maxlength="190" placeholder="Ex. : Arrosage de la matinée" required>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_activity_date">Date</label>
                        <input type="date" name="activity_date" id="baykat_activity_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                    </div>
                    <div class="baykat-farm-field baykat-farm-field-wide">
                        <label for="baykat_activity_notes">Notes (facultatif)</label>
                        <textarea name="activity_notes" id="baykat_activity_notes" rows="3"></textarea>
                    </div>
                    <?php baykat_farm_render_image_field( 'activity_image', 'Photo de l’activité' ); ?>
                    <button class="baykat-finance-button" type="submit" name="baykat_add_activity" value="1">Enregistrer l’activité</button>
                </form>
            </section>
        <?php else : ?>
            <section class="baykat-farm-panel baykat-farm-empty-state"><p>Créez d’abord une ferme et une parcelle pour enregistrer une activité.</p></section>
        <?php endif; ?>

        <section class="baykat-farm-panel" aria-labelledby="baykat-farm-activity-list-title">
            <header class="baykat-farm-panel-heading"><span class="baykat-farm-eyebrow">Journal</span><h3 id="baykat-farm-activity-list-title">Activités enregistrées</h3></header>
            <?php if ( $activities ) : ?>
                <div class="baykat-farm-record-grid baykat-farm-visual-grid">
                    <?php foreach ( $activities as $activity ) : ?>
                        <article class="baykat-farm-record-card baykat-farm-visual-card baykat-farm-activity-card">
                            <?php echo baykat_farm_render_image( $activity->image_id, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div class="baykat-farm-visual-card-body">
                                <span class="baykat-farm-eyebrow"><?php echo esc_html( $activity->activity_date ); ?></span>
                                <h4><?php echo esc_html( $activity->title ); ?></h4>
                                <div class="baykat-farm-card-meta">
                                    <span class="baykat-farm-status"><?php echo esc_html( isset( $activity_types[ $activity->activity_type ] ) ? $activity_types[ $activity->activity_type ] : $activity->activity_type ); ?></span>
                                    <span><?php echo esc_html( $activity->farm_name . ' — ' . $activity->parcel_name ); ?></span>
                                </div>
                                <?php if ( $activity->notes ) : ?>
                                    <p class="baykat-farm-card-dates"><?php echo esc_html( $activity->notes ); ?></p>
                                <?php endif; ?>
                                <?php baykat_farm_render_image_update_form( 'activity', $activity->id, $activity->image_id ? 'Changer la photo' : 'Ajouter une photo' ); ?>
                                <details class="baykat-farm-record-actions">
                                    <summary>Modifier l’activité</summary>
                                    <form class="baykat-farm-record-form" method="post">
                                        <?php wp_nonce_field( 'baykat_update_activity', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_update_activity_id" value="<?php echo esc_attr( $activity->id ); ?>">
                                        <div class="baykat-farm-field">
                                            <label for="baykat_activity_edit_parcel_<?php echo esc_attr( $activity->id ); ?>">Parcelle</label>
                                            <select name="parcel_id" id="baykat_activity_edit_parcel_<?php echo esc_attr( $activity->id ); ?>" required>
                                                <?php foreach ( $parcels as $parcel ) : ?>
                                                    <option value="<?php echo esc_attr( $parcel->id ); ?>" <?php selected( (int) $activity->parcel_id, (int) $parcel->id ); ?>><?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_activity_edit_type_<?php echo esc_attr( $activity->id ); ?>">Type d’activité</label>
                                            <select name="activity_type" id="baykat_activity_edit_type_<?php echo esc_attr( $activity->id ); ?>" required>
                                                <?php foreach ( $activity_types as $slug => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $activity->activity_type, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_activity_edit_title_<?php echo esc_attr( $activity->id ); ?>">Titre</label>
                                            <input type="text" name="activity_title" id="baykat_activity_edit_title_<?php echo esc_attr( $activity->id ); ?>" maxlength="190" value="<?php echo esc_attr( $activity->title ); ?>" required>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_activity_edit_date_<?php echo esc_attr( $activity->id ); ?>">Date</label>
                                            <input type="date" name="activity_date" id="baykat_activity_edit_date_<?php echo esc_attr( $activity->id ); ?>" value="<?php echo esc_attr( $activity->activity_date ); ?>" required>
                                        </div>
                                        <div class="baykat-farm-field baykat-farm-field-wide">
                                            <label for="baykat_activity_edit_notes_<?php echo esc_attr( $activity->id ); ?>">Notes</label>
                                            <textarea name="activity_notes" id="baykat_activity_edit_notes_<?php echo esc_attr( $activity->id ); ?>" rows="3"><?php echo esc_textarea( $activity->notes ); ?></textarea>
                                        </div>
                                        <button class="baykat-finance-button" type="submit" name="baykat_update_activity" value="1">Enregistrer les modifications</button>
                                    </form>
                                    <form class="baykat-farm-delete-form" method="post">
                                        <?php wp_nonce_field( 'baykat_delete_activity', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_delete_activity_id" value="<?php echo esc_attr( $activity->id ); ?>">
                                        <button class="baykat-finance-button baykat-farm-delete-button" type="submit" name="baykat_delete_activity" value="1">Supprimer l’activité</button>
                                    </form>
                                </details>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="baykat-farm-empty-state">Aucune activité enregistrée pour le moment.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php
}
