function initializeBaykatFinancePages(root) {
    const financePages = root.querySelectorAll('.baykat-finance-page');
    financePages.forEach(function (financePage) {
        if (financePage.dataset.financeReady) {
            return;
        }
        financePage.dataset.financeReady = 'true';

        const form = financePage.querySelector('.baykat-finance-form-content');
        const amountInput = financePage.querySelector('#baykat_amount');
        const quantityInput = financePage.querySelector('#baykat_quantity');
        const submitButton = form ? form.querySelector('button[type="submit"]') : null;
        const searchInput = financePage.querySelector('#baykat-finance-search');
        const typeFilter = financePage.querySelector('#baykat-finance-filter');
        const transactionRows = Array.from(
            financePage.querySelectorAll('.baykat-finance-transaction-row')
        );
        const noResultsRow = financePage.querySelector('.baykat-finance-no-results');

        if (form && amountInput && submitButton) {
            amountInput.addEventListener('input', function () {
                const amount = Number(amountInput.value);
                const isInvalid = amountInput.value !== '' && (!Number.isFinite(amount) || amount < 0.01);

                amountInput.setCustomValidity(
                    isInvalid ? 'Le montant doit être d’au moins 0,01 FCFA.' : ''
                );

                if (isInvalid) {
                    amountInput.setAttribute('aria-invalid', 'true');
                } else {
                    amountInput.removeAttribute('aria-invalid');
                }
            });

            if (quantityInput) {
                quantityInput.addEventListener('input', function () {
                    const quantity = Number(quantityInput.value);
                    const isInvalid = quantityInput.value !== ''
                        && (!Number.isFinite(quantity) || quantity < 0.001);

                    quantityInput.setCustomValidity(
                        isInvalid ? 'La quantité doit être d’au moins 0,001.' : ''
                    );

                    if (isInvalid) {
                        quantityInput.setAttribute('aria-invalid', 'true');
                    } else {
                        quantityInput.removeAttribute('aria-invalid');
                    }
                });
            }

            form.addEventListener('submit', function (event) {
                const amount = Number(amountInput.value);

                if (!Number.isFinite(amount) || amount < 0.01) {
                    event.preventDefault();
                    amountInput.setCustomValidity('Le montant doit être d’au moins 0,01 FCFA.');
                    amountInput.setAttribute('aria-invalid', 'true');
                    amountInput.reportValidity();
                    amountInput.focus();
                    return;
                }

                if (quantityInput) {
                    const quantity = Number(quantityInput.value);

                    if (!Number.isFinite(quantity) || quantity < 0.001) {
                        event.preventDefault();
                        quantityInput.setCustomValidity('La quantité doit être d’au moins 0,001.');
                        quantityInput.setAttribute('aria-invalid', 'true');
                        quantityInput.reportValidity();
                        quantityInput.focus();
                        return;
                    }
                }

                if (submitButton.disabled) {
                    event.preventDefault();
                    return;
                }

                submitButton.disabled = true;
                submitButton.setAttribute('aria-busy', 'true');
                submitButton.textContent = 'Enregistrement...';
            });
        }

        function filterTransactions() {
            if (!transactionRows.length) {
                return;
            }

            const searchTerm = searchInput ? searchInput.value.trim().toLocaleLowerCase() : '';
            const selectedType = typeFilter ? typeFilter.value : 'all';
            let visibleCount = 0;

            transactionRows.forEach(function (row) {
                const rowText = (row.dataset.searchText || row.textContent).toLocaleLowerCase();
                const matchesSearch = rowText.includes(searchTerm);
                const matchesType = selectedType === 'all' || row.dataset.transactionType === selectedType;
                const isVisible = matchesSearch && matchesType;

                row.hidden = !isVisible;

                if (isVisible) {
                    visibleCount += 1;
                }
            });

            if (noResultsRow) {
                noResultsRow.hidden = visibleCount > 0;
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterTransactions);
        }

        if (typeFilter) {
            typeFilter.addEventListener('change', filterTransactions);
        }

        filterTransactions();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initializeBaykatFinancePages(document);
});

document.addEventListener('baykat:farm-module-loaded', function () {
    initializeBaykatFinancePages(document);
});
