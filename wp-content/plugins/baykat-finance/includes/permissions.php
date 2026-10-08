<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vérifie l'accès à la page Finance.
 */
function baykat_finance_user_can_access() {
    return current_user_can( 'read' );
}
