<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1>Gestion financière</h1>
    <p>Vue d'ensemble de votre activité financière.</p>

    <?php if ( $notice ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
            <p><?php echo esc_html( $notice['message'] ); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:20px;margin:30px 0;">
        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:25px;">
            <p style="margin:0;color:#666;">Solde</p>
            <h2 style="margin:10px 0 0;"><?php echo esc_html( number_format( $balance, 0, ',', ' ' ) ); ?> FCFA</h2>
        </div>
        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:25px;">
            <p style="margin:0;color:#666;">Revenus</p>
            <h2 style="margin:10px 0 0;"><?php echo esc_html( number_format( $total_income, 0, ',', ' ' ) ); ?> FCFA</h2>
        </div>
        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:25px;">
            <p style="margin:0;color:#666;">Dépenses</p>
            <h2 style="margin:10px 0 0;"><?php echo esc_html( number_format( $total_expense, 0, ',', ' ' ) ); ?> FCFA</h2>
        </div>
    </div>

    <h2>Ajouter une transaction</h2>
    <form method="post">
        <?php wp_nonce_field( 'baykat_add_transaction' ); ?>

        <table class="form-table">
            <tr>
                <th><label for="type">Type</label></th>
                <td>
                    <select name="type" id="type">
                        <option value="income">Revenu</option>
                        <option value="expense">Dépense</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="amount">Montant</label></th>
                <td><input type="number" name="amount" id="amount" step="0.01" min="0" required> FCFA</td>
            </tr>
            <tr>
                <th><label for="description">Description</label></th>
                <td><textarea name="description" id="description" rows="4" cols="50"></textarea></td>
            </tr>
        </table>

        <button type="submit" name="baykat_add_transaction" class="button button-primary">
            Enregistrer la transaction
        </button>
    </form>

    <hr>

    <h2>Mes transactions</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Description</th>
                <th>Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $transactions ) : ?>
                <?php foreach ( $transactions as $transaction ) : ?>
                    <tr>
                        <td><?php echo esc_html( $transaction->created_at ); ?></td>
                        <td><?php echo 'income' === $transaction->type ? 'Revenu' : 'Dépense'; ?></td>
                        <td><?php echo esc_html( $transaction->description ); ?></td>
                        <td><?php echo esc_html( number_format( $transaction->amount, 0, ',', ' ' ) ); ?> FCFA</td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4">Aucune transaction pour le moment.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
