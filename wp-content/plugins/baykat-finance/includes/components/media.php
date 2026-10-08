<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_image_field( $field_name, $label, $id_suffix = '' ) {
    $field_id = 'baykat-farm-image-' . sanitize_html_class( $field_name . $id_suffix );
    ?>
    <div class="baykat-farm-field baykat-farm-field-wide baykat-farm-image-field">
        <label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?> (facultatif)</label>
        <label class="baykat-farm-image-picker" for="<?php echo esc_attr( $field_id ); ?>">
            <span class="baykat-farm-image-preview">
                <span class="baykat-farm-image-placeholder" aria-hidden="true">+</span>
                <img alt="" hidden>
            </span>
            <span class="baykat-farm-image-picker-copy">
                <strong>Ajouter une photo</strong>
                <span>JPG, PNG ou WebP · 5 Mo maximum</span>
            </span>
            <input
                class="baykat-farm-image-input"
                type="file"
                name="<?php echo esc_attr( $field_name ); ?>"
                id="<?php echo esc_attr( $field_id ); ?>"
                accept="image/jpeg,image/png,image/webp"
            >
        </label>
    </div>
    <?php
}

function baykat_farm_render_image_update_form( $entity, $record_id, $label ) {
    $action_name = 'baykat_update_' . $entity . '_image';
    $field_name = $entity . '_image';
    ?>
    <details class="baykat-farm-image-update">
        <summary><?php echo esc_html( $label ); ?></summary>
        <form class="baykat-farm-image-update-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( $action_name, '_wpnonce' ); ?>
            <input type="hidden" name="<?php echo esc_attr( $action_name ); ?>" value="<?php echo esc_attr( $record_id ); ?>">
            <?php baykat_farm_render_image_field( $field_name, 'Photo', '-' . (int) $record_id ); ?>
            <button class="baykat-finance-button" type="submit">Enregistrer la photo</button>
        </form>
    </details>
    <?php
}

function baykat_farm_render_image( $attachment_id, $size = 'medium', $class = '' ) {
    if ( $attachment_id ) {
        $image = wp_get_attachment_image(
            (int) $attachment_id,
            $size,
            false,
            array(
                'class'   => trim( 'baykat-farm-card-image ' . $class ),
                'loading' => 'lazy',
            )
        );

        if ( $image ) {
            return $image;
        }
    }

    return '<div class="baykat-farm-card-image baykat-farm-card-image-placeholder ' . esc_attr( $class ) . '" aria-hidden="true"><span>Baykat</span></div>';
}
