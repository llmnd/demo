<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retourne l'URL de la page qui héberge l'application de gestion.
 */
function baykat_farm_get_management_page_url() {
    static $application_url = null;

    if ( null !== $application_url ) {
        return $application_url;
    }

    $application_url = '';
    $pages = get_posts(
        array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        )
    );

    foreach ( $pages as $page_id ) {
        $page = get_post( $page_id );

        if (
            $page instanceof WP_Post
            && (
                has_shortcode( $page->post_content, 'gerer_ma_ferme' )
                || has_shortcode( $page->post_content, 'baykat_farm' )
            )
        ) {
            $application_url = get_permalink( $page_id );
            break;
        }
    }

    return $application_url;
}

/**
 * Masque la barre WordPress uniquement sur les pages qui hébergent l’application agricole.
 */
function baykat_farm_filter_admin_bar_visibility( $show ) {
    if ( is_admin() ) {
        return $show;
    }

    $post = get_queried_object();
    if (
        $post instanceof WP_Post
        && (
            has_shortcode( $post->post_content, 'gerer_ma_ferme' )
            || has_shortcode( $post->post_content, 'baykat_farm' )
        )
    ) {
        return false;
    }

    return $show;
}
add_filter( 'show_admin_bar', 'baykat_farm_filter_admin_bar_visibility' );

/**
 * Affiche l'application agricole à partir de son shortcode principal.
 */
function baykat_farm_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div class="baykat-farm-app baykat-farm-login"><p>Connectez-vous à votre compte BAYKAT, puis ouvrez « Gérer ma ferme » depuis votre espace client.</p></div>';
    }

    if ( ! baykat_finance_user_can_access() ) {
        return '<div class="baykat-farm-app"><p>Vous n\'avez pas l\'autorisation d\'accéder à cette application.</p></div>';
    }

    $modules = array(
        'vue-ensemble' => array(
            'label'    => 'Vue d\'ensemble',
            'callback' => 'baykat_farm_render_dashboard',
        ),
        'parcelles' => array(
            'label'    => 'Parcelles',
            'callback' => 'baykat_farm_render_parcelles',
        ),
        'campagnes' => array(
            'label'    => 'Campagnes',
            'callback' => 'baykat_farm_render_campaigns',
        ),
        'archives' => array(
            'label'    => 'Archives',
            'callback' => 'baykat_farm_render_archives',
        ),
        'activites' => array(
            'label'    => 'Activités',
            'callback' => 'baykat_farm_render_activites',
        ),
        'finances' => array(
            'label'    => 'Finances',
            'callback' => 'baykat_farm_render_finances',
        ),
        'rapports' => array(
            'label'    => 'Rapports',
            'callback' => 'baykat_farm_render_reports',
        ),
    );
    if ( BAYKAT_FARM_V1_SHOW_ADVANCED_STOCKS ) {
        $modules['stocks'] = array(
            'label'    => 'Stocks',
            'callback' => 'baykat_farm_render_stocks',
        );
    }

    $requested_module = isset( $_GET['module'] ) && is_string( $_GET['module'] )
        ? sanitize_key( wp_unslash( $_GET['module'] ) )
        : 'vue-ensemble';

    $active_module = isset( $modules[ $requested_module ] )
        ? $requested_module
        : 'vue-ensemble';

    $base_url     = get_permalink();
    $current_user = wp_get_current_user();

    ob_start();
    ?>
    <main class="baykat-farm-app">
        <header class="baykat-farm-site-header">
            <button
                class="baykat-farm-mobile-menu-toggle"
                type="button"
                aria-label="Ouvrir le menu de navigation"
                aria-expanded="false"
                aria-controls="baykat-farm-site-navigation baykat-farm-module-navigation"
                data-mobile-menu-toggle
            >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 7h16"></path>
                    <path d="M4 12h16"></path>
                    <path d="M4 17h16"></path>
                </svg>
            </button>
            <a class="baykat-farm-site-logo" href="<?php echo esc_url( get_permalink() ); ?>">
                <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
            </a>
            <nav class="baykat-farm-site-navigation" id="baykat-farm-site-navigation" aria-label="Navigation principale">
                <span>Gestion de ferme</span>
                <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">Déconnexion</a>
            </nav>
            <div class="baykat-farm-user">
                <span class="baykat-farm-user-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="8" r="3.25"></circle>
                        <path d="M5.75 19a6.25 6.25 0 0 1 12.5 0"></path>
                    </svg>
                </span>
                <span><?php echo esc_html( $current_user->display_name ); ?></span>
            </div>
        </header>

        <?php baykat_farm_render_navigation( $modules, $active_module, $base_url ); ?>

        <div class="baykat-farm-module" id="baykat-farm-module-content">
            <?php
            $renderer = $modules[ $active_module ]['callback'];

            if ( 'baykat_farm_render_finances' === $renderer ) {
                echo $renderer(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                $renderer();
            }
            ?>
        </div>
    </main>
    <?php

    return ob_get_clean();
}

add_shortcode( 'baykat_farm', 'baykat_farm_shortcode' );
add_shortcode( 'gerer_ma_ferme', 'baykat_farm_shortcode' );

function baykat_farm_add_body_class( $classes ) {
    $post = get_queried_object();

    if (
        $post instanceof WP_Post
        && (
            has_shortcode( $post->post_content, 'baykat_farm' )
            || has_shortcode( $post->post_content, 'gerer_ma_ferme' )
        )
    ) {
        $classes[] = 'baykat-farm-page';

        if ( ! is_user_logged_in() ) {
            $classes[] = 'baykat-farm-login-page';
        }
    }

    return $classes;
}

add_filter( 'body_class', 'baykat_farm_add_body_class' );