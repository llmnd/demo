<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_stocks() {
    $user_id = get_current_user_id();
    $message = baykat_farm_process_stock_movement( $user_id );
    $stocks = baykat_farm_get_stock_totals( $user_id );
    $returnable_stock = baykat_farm_get_returnable_stock( $user_id );
    $history = baykat_farm_get_stock_history( $user_id );
    $farms = baykat_farm_get_user_farms( $user_id );
    $editable_farms = baykat_farm_filter_editable_records( $farms );
    $parcels = baykat_farm_get_user_parcels( $user_id );
    $editable_parcels = baykat_farm_filter_editable_records( $parcels );
    $crops = baykat_farm_get_user_crops( $user_id );
    $editable_crops = baykat_farm_filter_editable_records( $crops );
    $activities = baykat_farm_get_user_activities( $user_id );
    $editable_activities = baykat_farm_filter_editable_records( $activities );
    $editable_farm_ids = array_fill_keys( array_map( 'intval', wp_list_pluck( $editable_farms, 'id' ) ), true );
    ?>
    <div class="baykat-farm-content">
        <header class="baykat-farm-section-heading">
            <span class="baykat-farm-eyebrow">Entrées, sorties et inventaire</span>
            <h2>Stocks</h2>
            <p>Enregistrez les approvisionnements, utilisations, transferts, écarts d’inventaire et retours. Les sorties sont refusées si le stock disponible est insuffisant.</p>
        </header>
        <?php if ( $message ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-<?php echo esc_attr( $message['type'] ); ?>" role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $message['message'] ); ?></div>
        <?php endif; ?>

        <?php if ( $editable_farms ) : ?>
            <section class="baykat-farm-panel baykat-farm-add-panel" aria-labelledby="baykat-farm-stock-movement-title">
                <details class="baykat-farm-add-form">
                    <summary id="baykat-farm-stock-movement-title"><span class="baykat-farm-eyebrow">Nouvelle opération</span><strong>Enregistrer un mouvement de stock</strong><span class="baykat-farm-add-form-icon" aria-hidden="true">+</span></summary>
                    <form class="baykat-farm-form baykat-stock-movement-form" method="post">
                        <?php wp_nonce_field( 'baykat_add_stock_movement', '_wpnonce' ); ?>
                        <input type="hidden" name="baykat_add_stock_movement" value="1">
                        <h4 class="baykat-stock-form-heading baykat-farm-field-wide" data-stock-step-label="Type et date">1. Type et date</h4>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_movement_type">Que voulez-vous enregistrer ?</label>
                            <select name="movement_type" id="baykat_stock_movement_type" required>
                                <?php foreach ( baykat_farm_stock_types() as $slug => $label ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_movement_date">Date du mouvement</label>
                            <input type="date" name="movement_date" id="baykat_stock_movement_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                        </div>
                        <p class="baykat-stock-movement-guidance baykat-farm-field-wide" data-stock-movement-guidance aria-live="polite"></p>
                        <h4 class="baykat-stock-form-heading baykat-farm-field-wide" data-stock-step-label="Emplacement concerné">2. Emplacement concerné</h4>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_farm" data-stock-source-farm-label>Ferme concernée</label>
                            <select name="farm_id" id="baykat_stock_farm" data-stock-farm-select="source" required>
                                <option value="">Choisir une ferme</option>
                                <?php foreach ( $editable_farms as $farm ) : ?>
                                    <option value="<?php echo esc_attr( $farm->id ); ?>"><?php echo esc_html( $farm->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_parcel" data-stock-source-parcel-label>Parcelle concernée (facultatif)</label>
                            <select name="parcel_id" id="baykat_stock_parcel">
                                <option value="">Stock général de la ferme</option>
                                <?php foreach ( $editable_parcels as $parcel ) : ?>
                                    <option value="<?php echo esc_attr( $parcel->id ); ?>" data-farm-id="<?php echo esc_attr( $parcel->farm_id ); ?>"><?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <h4 class="baykat-stock-form-heading baykat-farm-field-wide" data-stock-step-label="Intrant et quantité">3. Intrant et quantité</h4>
                        <div class="baykat-farm-field baykat-farm-field-wide" data-stock-prefill-field>
                            <label for="baykat_stock_prefill" data-stock-prefill-label>Partir d’un intrant déjà en stock (facultatif)</label>
                            <select id="baykat_stock_prefill" data-stock-prefill>
                                <option value="">Choisir pour préremplir les champs</option>
                                <?php foreach ( $stocks as $index => $stock ) : ?>
                                    <?php if ( isset( $editable_farm_ids[ (int) $stock->farm_id ] ) ) : ?>
                                        <option value="<?php echo esc_attr( $index ); ?>"
                                            data-farm-id="<?php echo esc_attr( $stock->farm_id ); ?>"
                                            data-parcel-id="<?php echo esc_attr( $stock->parcel_id ); ?>"
                                            data-item-name="<?php echo esc_attr( $stock->item_name ); ?>"
                                            data-unit="<?php echo esc_attr( baykat_farm_normalize_unit( $stock->unit ) ); ?>"
                                            data-quantity="<?php echo esc_attr( $stock->total_quantity ); ?>"
                                        ><?php echo esc_html( $stock->farm_name . ' — ' . $stock->parcel_name . ' — ' . $stock->item_name . ' (' . baykat_farm_format_quantity( $stock->total_quantity ) . ' ' . baykat_farm_normalize_unit( $stock->unit ) . ')' ); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php foreach ( $returnable_stock as $index => $stock ) : ?>
                                    <option value="return-<?php echo esc_attr( $index ); ?>"
                                        data-returnable="true"
                                        data-farm-id="<?php echo esc_attr( $stock->farm_id ); ?>"
                                        data-parcel-id="<?php echo esc_attr( $stock->parcel_id ); ?>"
                                        data-item-name="<?php echo esc_attr( $stock->item_name ); ?>"
                                        data-unit="<?php echo esc_attr( baykat_farm_normalize_unit( $stock->unit ) ); ?>"
                                        data-activity-id="<?php echo esc_attr( $stock->activity_id ); ?>"
                                        data-quantity="<?php echo esc_attr( $stock->returnable_quantity ); ?>"
                                    >Retour — <?php echo esc_html( $stock->farm_name . ' — ' . ( $stock->parcel_name ? $stock->parcel_name : 'Stock général' ) . ' — ' . $stock->activity_title . ' — ' . $stock->item_name . ' (' . baykat_farm_format_quantity( $stock->returnable_quantity ) . ' ' . baykat_farm_normalize_unit( $stock->unit ) . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small data-stock-prefill-help>Choisissez un stock connu pour éviter les erreurs de nom et d’unité.</small>
                        </div>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_item">Nom de l’intrant</label>
                            <input type="text" name="item_name" id="baykat_stock_item" maxlength="190" placeholder="Ex. : Semences de tomate" required>
                        </div>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_unit">Unité</label>
                            <input type="text" name="unit" id="baykat_stock_unit" maxlength="40" placeholder="kg, litre, sac…" required>
                        </div>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_quantity">Quantité du mouvement</label>
                            <input type="number" name="quantity" id="baykat_stock_quantity" min="0.001" step="0.001" inputmode="decimal" placeholder="Ex. : 1" required>
                        </div>
                        <p class="baykat-stock-balance-message baykat-farm-field-wide" data-stock-balance-message aria-live="polite"></p>
                        <h4 class="baykat-stock-form-heading baykat-farm-field-wide" data-stock-step-label="Destination du transfert" data-stock-destination-heading>4. Destination du transfert</h4>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_destination_farm">Ferme de destination (transfert)</label>
                            <select name="destination_farm_id" id="baykat_stock_destination_farm" data-stock-farm-select="destination">
                                <option value="">Choisir une ferme</option>
                                <?php foreach ( $editable_farms as $farm ) : ?>
                                    <option value="<?php echo esc_attr( $farm->id ); ?>"><?php echo esc_html( $farm->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_destination_parcel">Parcelle de destination (facultatif)</label>
                            <select name="destination_parcel_id" id="baykat_stock_destination_parcel">
                                <option value="">Stock général de la ferme</option>
                                <?php foreach ( $editable_parcels as $parcel ) : ?>
                                    <option value="<?php echo esc_attr( $parcel->id ); ?>" data-farm-id="<?php echo esc_attr( $parcel->farm_id ); ?>"><?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <h4 class="baykat-stock-form-heading baykat-farm-field-wide" data-stock-step-label="Rattachement">5. Rattachement</h4>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_crop">Culture concernée (facultatif)</label>
                            <select name="crop_id" id="baykat_stock_crop" data-stock-filter-select>
                                <option value="">Aucune culture</option>
                                <?php foreach ( $editable_crops as $crop ) : ?>
                                    <option value="<?php echo esc_attr( $crop->id ); ?>" data-farm-id="<?php echo esc_attr( $crop->farm_id ); ?>" data-parcel-id="<?php echo esc_attr( $crop->parcel_id ); ?>"><?php echo esc_html( $crop->farm_name . ' — ' . $crop->parcel_name . ' — ' . $crop->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="baykat-farm-field">
                            <label for="baykat_stock_activity">Activité associée (facultatif)</label>
                            <select name="activity_id" id="baykat_stock_activity" data-stock-filter-select>
                                <option value="">Aucune activité</option>
                                <?php foreach ( $editable_activities as $activity ) : ?>
                                    <option value="<?php echo esc_attr( $activity->id ); ?>" data-farm-id="<?php echo esc_attr( $activity->farm_id ); ?>" data-parcel-id="<?php echo esc_attr( $activity->parcel_id ); ?>"><?php echo esc_html( $activity->farm_name . ' — ' . $activity->parcel_name . ' — ' . $activity->title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small data-stock-activity-help>Associez une activité pour garder le contexte de l’utilisation.</small>
                        </div>
                        <h4 class="baykat-stock-form-heading baykat-farm-field-wide" data-stock-step-label="Coût de l’approvisionnement" data-stock-entry-heading>6. Coût de l’approvisionnement</h4>
                        <div class="baykat-farm-field" data-stock-entry-cost>
                            <label for="baykat_stock_amount">Coût total de l’approvisionnement (FCFA)</label>
                            <input type="number" name="amount" id="baykat_stock_amount" min="0.01" step="0.01">
                            <small>Cette entrée sera aussi enregistrée comme dépense d’intrant dans les finances.</small>
                        </div>
                        <h4 class="baykat-stock-form-heading baykat-farm-field-wide" data-stock-step-label="Note facultative">7. Note facultative</h4>
                        <div class="baykat-farm-field baykat-farm-field-wide">
                            <label for="baykat_stock_notes">Détail utile pour le journal (facultatif)</label>
                            <textarea name="movement_notes" id="baykat_stock_notes" rows="2" placeholder="Ex. : transfert vers le magasin de la ferme voisine"></textarea>
                        </div>
                        <button class="baykat-finance-button" type="submit">Enregistrer le mouvement</button>
                    </form>
                </details>
            </section>
        <?php elseif ( ! $farms ) : ?>
            <p class="baykat-farm-empty-state">Créez ou partagez une ferme avec un rôle de gestionnaire pour enregistrer des mouvements de stock.</p>
        <?php else : ?>
            <p class="baykat-farm-empty-state">Votre rôle actuel autorise uniquement la consultation des stocks partagés avec vous.</p>
        <?php endif; ?>

        <section class="baykat-farm-panel" aria-labelledby="baykat-farm-stock-list-title">
            <header class="baykat-farm-panel-heading">
                <span class="baykat-farm-eyebrow">Vue de stock</span>
                <h3 id="baykat-farm-stock-list-title">Quantités disponibles par intrant</h3>
            </header>
            <?php if ( $stocks ) : ?>
                <div class="baykat-farm-table-wrap">
                    <table class="baykat-farm-table">
                        <thead><tr><th>Ferme</th><th>Parcelle</th><th>Intrant</th><th>Quantité disponible</th><th>Achats enregistrés</th></tr></thead>
                        <tbody>
                            <?php foreach ( $stocks as $stock ) : ?>
                                <tr data-stock-record data-farm-id="<?php echo esc_attr( $stock->farm_id ); ?>" data-parcel-id="<?php echo esc_attr( $stock->parcel_id ); ?>">
                                    <td data-label="Ferme"><?php echo esc_html( $stock->farm_name ); ?></td>
                                    <td data-label="Parcelle"><?php echo esc_html( $stock->parcel_name ); ?></td>
                                    <td data-label="Intrant"><strong><?php echo esc_html( $stock->item_name ); ?></strong></td>
                                    <td data-label="Quantité disponible"><?php echo esc_html( baykat_farm_format_quantity( $stock->total_quantity ) . ' ' . baykat_farm_normalize_unit( $stock->unit ) ); ?></td>
                                    <td data-label="Achats enregistrés"><?php echo esc_html( number_format( (float) $stock->total_cost, 0, ',', ' ' ) ); ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p class="baykat-farm-empty-state">Aucun intrant enregistré. Les achats saisis dans Finances → Intrants apparaîtront ici.</p>
            <?php endif; ?>
        </section>

        <section class="baykat-farm-panel" aria-labelledby="baykat-farm-stock-history-title">
            <header class="baykat-farm-panel-heading"><span class="baykat-farm-eyebrow">Journal d’inventaire</span><h3 id="baykat-farm-stock-history-title">Mouvements récents</h3></header>
            <?php if ( $history ) : ?>
                <div class="baykat-farm-table-wrap">
                    <table class="baykat-farm-table">
                        <thead><tr><th>Date</th><th>Mouvement</th><th>Intrant</th><th>Quantité</th><th>Emplacement</th><th>Détail</th></tr></thead>
                        <tbody>
                            <?php foreach ( $history as $movement ) : ?>
                                <tr>
                                    <td data-label="Date"><?php echo esc_html( $movement->movement_date ); ?></td>
                                    <td data-label="Mouvement"><?php echo esc_html( isset( baykat_farm_stock_types()[ $movement->movement_type ] ) ? baykat_farm_stock_types()[ $movement->movement_type ] : $movement->movement_type ); ?></td>
                                    <td data-label="Intrant"><strong><?php echo esc_html( $movement->item_name ); ?></strong></td>
                                    <td data-label="Quantité"><?php echo esc_html( baykat_farm_format_quantity( $movement->quantity ) . ' ' . baykat_farm_normalize_unit( $movement->unit ) ); ?></td>
                                    <td data-label="Emplacement"><?php echo esc_html( $movement->farm_name . ( $movement->parcel_name ? ' — ' . $movement->parcel_name : '' ) ); ?>
                                        <?php if ( ! empty( $movement->destination_farm_name ) ) : ?><small>→ <?php echo esc_html( $movement->destination_farm_name . ( $movement->destination_parcel_name ? ' — ' . $movement->destination_parcel_name : '' ) ); ?></small><?php endif; ?>
                                    </td>
                                    <td data-label="Détail"><?php echo esc_html( $movement->notes ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p class="baykat-farm-empty-state">Aucun mouvement enregistré.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php
}
