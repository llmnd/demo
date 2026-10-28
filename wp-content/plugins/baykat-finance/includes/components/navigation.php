<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Affiche la navigation interne de l'application ferme.
 *
 * @param array  $modules    Modules disponibles.
 * @param string $active    Identifiant du module actif.
 * @param string $base_url  URL de la page qui héberge l'application.
 */
function baykat_farm_render_navigation( $modules, $active, $base_url ) {
    ?>
    <nav class="baykat-farm-navigation" id="baykat-farm-module-navigation" aria-label="Navigation de Gérer ma ferme">
        <?php foreach ( $modules as $slug => $module ) : ?>
            <?php
            $is_active = $slug === $active;
            $module_url = add_query_arg( 'module', $slug, $base_url );
            ?>
            <a
                class="baykat-farm-navigation-link<?php echo $is_active ? ' is-active' : ''; ?>"
                href="<?php echo esc_url( $module_url ); ?>"
                <?php echo $is_active ? 'aria-current="page"' : ''; ?>
            >
                <?php echo esc_html( $module['label'] ); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}

/**
 * Affiche les outils de recherche et de filtrage d'une liste d'enregistrements.
 *
 * @param string $list_id      Identifiant de la grille à filtrer.
 * @param string $search_label Libellé du champ de recherche.
 * @param array  $filters      Filtres disponibles, sous la forme identifiant => libellé.
 * @param string $filter_label Libellé du filtre facultatif.
 * @param bool   $show_filter  Afficher le filtre sans ouvrir la recherche.
 */
function baykat_farm_render_record_filters( $list_id, $search_label, $filters = array(), $filter_label = 'Filtrer par statut', $show_filter = false ) {
    $search_id = sanitize_html_class( $list_id . '-search' );
    $filter_id = sanitize_html_class( $list_id . '-filter' );
    ?>
    <div class="baykat-farm-record-tools<?php echo $filters ? ' has-filter' : ''; ?>">
        <button
            class="baykat-farm-search-toggle"
            type="button"
            aria-label="Afficher la recherche"
            aria-expanded="false"
            aria-controls="<?php echo esc_attr( $search_id ); ?>"
            data-toggle-record-search
        >
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="10.8" cy="10.8" r="6.3"></circle>
                <path d="m15.5 15.5 4.2 4.2"></path>
            </svg>
        </button>
        <div class="baykat-farm-record-tool" data-record-search-tool hidden>
            <label for="<?php echo esc_attr( $search_id ); ?>"><?php echo esc_html( $search_label ); ?></label>
            <input
                type="search"
                id="<?php echo esc_attr( $search_id ); ?>"
                placeholder="Nom, ferme ou mot-clé"
                autocomplete="off"
                data-farm-record-search
                aria-controls="<?php echo esc_attr( $list_id ); ?>"
            >
        </div>
        <?php if ( $filters ) : ?>
            <div class="baykat-farm-record-tool" data-record-filter-tool<?php echo $show_filter ? ' data-record-filter-persistent' : ' hidden'; ?>>
                <label for="<?php echo esc_attr( $filter_id ); ?>"><?php echo esc_html( $filter_label ); ?></label>
                <select
                    id="<?php echo esc_attr( $filter_id ); ?>"
                    data-farm-record-filter
                    aria-controls="<?php echo esc_attr( $list_id ); ?>"
                >
                    <option value="all">Tout afficher</option>
                    <?php foreach ( $filters as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <p class="baykat-farm-record-count" data-farm-record-count role="status" aria-live="polite"></p>
    </div>
    <p class="baykat-farm-empty-state baykat-farm-filter-empty" data-farm-record-empty hidden>
        Aucun résultat ne correspond à votre recherche. Essayez un autre mot-clé ou filtre.
    </p>
    <?php
}
