<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_stocks() {
    $stocks = baykat_farm_get_stock_totals( get_current_user_id() );
    ?>
    <div class="baykat-farm-content">
        <header class="baykat-farm-section-heading">
            <span class="baykat-farm-eyebrow">Intrants enregistrés</span>
            <h2>Stocks</h2>
            <p>Quantités d’intrants cumulées à partir des achats enregistrés. Les sorties de stock ne sont pas encore suivies.</p>
        </header>
        <section class="baykat-farm-panel" aria-labelledby="baykat-farm-stock-list-title">
            <header class="baykat-farm-panel-heading">
                <span class="baykat-farm-eyebrow">Vue de stock</span>
                <h3 id="baykat-farm-stock-list-title">Quantités enregistrées par intrant</h3>
            </header>
            <?php if ( $stocks ) : ?>
                <div class="baykat-farm-table-wrap">
                    <table class="baykat-farm-table">
                        <thead><tr><th>Ferme</th><th>Intrant</th><th>Quantité cumulée</th><th>Coût d’achat cumulé</th></tr></thead>
                        <tbody>
                            <?php foreach ( $stocks as $stock ) : ?>
                                <tr>
                                    <td data-label="Ferme"><?php echo esc_html( $stock->farm_name ); ?></td>
                                    <td data-label="Intrant"><strong><?php echo esc_html( $stock->item_name ); ?></strong></td>
                                    <td data-label="Quantité cumulée"><?php echo esc_html( number_format( (float) $stock->total_quantity, 3, ',', ' ' ) . ' ' . $stock->unit ); ?></td>
                                    <td data-label="Coût d’achat cumulé"><?php echo esc_html( number_format( (float) $stock->total_cost, 0, ',', ' ' ) ); ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p class="baykat-farm-empty-state">Aucun intrant enregistré. Les achats saisis dans Finances → Intrants apparaîtront ici.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php
}
