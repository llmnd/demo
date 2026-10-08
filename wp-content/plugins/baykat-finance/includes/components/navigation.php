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
    <nav class="baykat-farm-navigation" aria-label="Navigation de Gérer ma ferme">
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
