function initializeBaykatFinancePages(root) {
    const financePages = root.querySelectorAll('.baykat-finance-page');
    financePages.forEach(function (financePage) {
        if (financePage.dataset.financeReady) {
            return;
        }
        financePage.dataset.financeReady = 'true';

        const form = financePage.querySelector('.baykat-finance-form-content');
        const amountInput = financePage.querySelector('[data-finance-amount]');
        const quantityInput = financePage.querySelector('[data-finance-quantity]');
        const transactionType = form ? form.querySelector('#baykat_type') : null;
        const expenseCategoryField = form ? form.querySelector('[data-expense-category-field]') : null;
        const expenseCategorySelect = expenseCategoryField ? expenseCategoryField.querySelector('select') : null;
        const farmSelect = form ? form.querySelector('[data-finance-farm-select]') : null;
        const parcelSelect = form ? form.querySelector('[data-finance-parcel-select]') : null;
        const activitySelect = form ? form.querySelector('[data-finance-activity-select]') : null;
        const cropSelect = form ? form.querySelector('[data-finance-crop-select]') : null;
        const campaignSelect = form ? form.querySelector('[data-finance-campaign-select]') : null;
        const saleCropSelect = form ? form.querySelector('[data-finance-sale-crop]') : null;
        const saleProductInput = form ? form.querySelector('[data-sale-product]') : null;
        const saleUnitInput = form ? form.querySelector('[data-sale-unit]') : null;
        const saleQuantityInput = form ? form.querySelector('[data-sale-quantity]') : null;
        const saleAvailability = form ? form.querySelector('[data-sale-availability]') : null;
        const saleParcelLabel = form ? form.querySelector('[data-sale-parcel-label]') : null;
        const submitButton = form ? form.querySelector('button[type="submit"]') : null;
        const searchInput = financePage.querySelector('[data-finance-search]');
        const typeFilter = financePage.querySelector('#baykat-finance-filter');
        const transactionRows = Array.from(
            financePage.querySelectorAll('.baykat-finance-transaction-row')
        );
        const noResultsRow = financePage.querySelector('.baykat-finance-no-results');

        if (form && amountInput && submitButton) {
            function updateExpenseCategoryVisibility() {
                const isExpense = !transactionType || transactionType.value === 'expense';
                if (expenseCategoryField) {
                    expenseCategoryField.hidden = !isExpense;
                }
                if (expenseCategorySelect) {
                    expenseCategorySelect.disabled = !isExpense;
                    if (!isExpense) {
                        expenseCategorySelect.value = 'general';
                    }
                }
                if (parcelSelect && parcelSelect.tagName === 'SELECT') {
                    parcelSelect.required = !isExpense;
                    if (!isExpense && !parcelSelect.value) {
                        parcelSelect.setCustomValidity('Choisissez une parcelle pour rattacher ce revenu.');
                    } else {
                        parcelSelect.setCustomValidity('');
                    }
                }
            }

            function filterActivityOptions() {
                if (!parcelSelect || parcelSelect.tagName !== 'SELECT') {
                    return;
                }

                const parcelId = parcelSelect.value;
                const farmId = farmSelect ? farmSelect.value : '';
                Array.from(parcelSelect.options).forEach(function (option) {
                    if (option.value) {
                        option.hidden = !farmId || option.dataset.farmId !== farmId;
                    }
                });
                [activitySelect, cropSelect, campaignSelect].filter(Boolean).forEach(function (select) {
                    Array.from(select.options).forEach(function (option) {
                        if (!option.value) {
                            option.hidden = false;
                            return;
                        }
                        option.hidden = !parcelId || option.dataset.parcelId !== parcelId;
                    });
                    if (select.selectedOptions.length && select.selectedOptions[0].hidden) {
                        select.value = '';
                    }
                });
            }

            if (transactionType) {
                transactionType.addEventListener('change', updateExpenseCategoryVisibility);
                updateExpenseCategoryVisibility();
                updateCampaignSelection();
            }

            function updateCampaignSelection() {
                if (!campaignSelect) {
                    return;
                }
                const activity = activitySelect && activitySelect.selectedOptions[0];
                const selectedSaleCrop = saleCropSelect && saleCropSelect.selectedOptions[0];
                const activityCampaignId = activity && activity.value ? activity.dataset.campaignId : '';
                const cropCampaignId = selectedSaleCrop && selectedSaleCrop.value
                    ? selectedSaleCrop.dataset.campaignId
                    : '';
                const fixedCampaignId = activityCampaignId || cropCampaignId || '';
                if (fixedCampaignId) {
                    campaignSelect.value = fixedCampaignId;
                    campaignSelect.disabled = true;
                } else {
                    campaignSelect.disabled = false;
                }
            }

            if (parcelSelect && (activitySelect || cropSelect || campaignSelect)) {
                parcelSelect.addEventListener('change', filterActivityOptions);
                filterActivityOptions();
            }
            if (farmSelect) {
                farmSelect.addEventListener('change', function () {
                    if (parcelSelect && parcelSelect.tagName === 'SELECT') {
                        parcelSelect.value = '';
                    }
                    filterActivityOptions();
                });
            }
            if (activitySelect) {
                activitySelect.addEventListener('change', updateCampaignSelection);
            }
            if (campaignSelect) {
                updateCampaignSelection();
            }

            function updateSaleCrop() {
                if (!saleCropSelect) {
                    return;
                }

                const selectedCrop = saleCropSelect.selectedOptions[0];
                const hasCrop = selectedCrop && selectedCrop.value !== '';
                const available = hasCrop ? Number(selectedCrop.dataset.available) : 0;

                if (saleProductInput) {
                    saleProductInput.value = hasCrop ? selectedCrop.dataset.product : '';
                }
                if (saleUnitInput) {
                    saleUnitInput.value = hasCrop ? selectedCrop.dataset.unit : '';
                }
                if (parcelSelect) {
                    parcelSelect.value = hasCrop ? selectedCrop.dataset.parcelId : '';
                    if (farmSelect && hasCrop) {
                        const selectedParcel = Array.from(parcelSelect.options).find(function (option) {
                            return option.value === selectedCrop.dataset.parcelId;
                        });
                        farmSelect.value = selectedParcel ? selectedParcel.dataset.farmId : '';
                    }
                    if (activitySelect) {
                        filterActivityOptions();
                    }
                    if (campaignSelect) {
                        updateCampaignSelection();
                    }
                }
                if (saleParcelLabel) {
                    saleParcelLabel.textContent = hasCrop
                        ? selectedCrop.dataset.parcelLabel
                        : 'La parcelle sera renseignée automatiquement.';
                }
                if (saleQuantityInput) {
                    saleQuantityInput.max = hasCrop ? String(available) : '';
                    saleQuantityInput.value = '';
                    saleQuantityInput.setCustomValidity('');
                }
                if (saleAvailability) {
                    saleAvailability.textContent = hasCrop
                        ? 'Quantité encore disponible : ' + available.toLocaleString('fr-FR') + ' ' + selectedCrop.dataset.unit + '. La quantité vendue ne peut pas dépasser ce restant.'
                        : 'Choisissez la culture pour afficher la quantité récoltée encore disponible.';
                }
            }

            if (saleCropSelect) {
                saleCropSelect.addEventListener('change', updateSaleCrop);
                updateSaleCrop();
            }

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
                    const exceedsAvailable = saleQuantityInput === quantityInput
                        && quantityInput.value !== ''
                        && quantity > Number(quantityInput.max);
                    const isInvalid = quantityInput.value !== ''
                        && (!Number.isFinite(quantity) || quantity < 0.001);

                    quantityInput.setCustomValidity(
                        isInvalid
                            ? 'La quantité doit être d’au moins 0,001.'
                            : (exceedsAvailable ? 'La quantité dépasse la récolte encore disponible.' : '')
                    );

                    if (isInvalid || exceedsAvailable) {
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
                    if (saleQuantityInput === quantityInput && quantity > Number(quantityInput.max)) {
                        event.preventDefault();
                        quantityInput.setCustomValidity('La quantité dépasse la récolte encore disponible.');
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
                const isVisible = matchesSearch
                    && matchesType
                    && row.dataset.farmScopeHidden !== 'true';

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
