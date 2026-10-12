document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.baykat-farm-app').forEach(function (app) {
        function normalizeSearchText(value) {
            return value
                .toLocaleLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        }

        function initializeCompactAddForms(root) {
            root.querySelectorAll('.baykat-farm-add-panel').forEach(function (panel) {
                const nextPanel = panel.nextElementSibling;
                const details = panel.querySelector(':scope > .baykat-farm-add-form');
                const heading = nextPanel && nextPanel.matches('.baykat-farm-panel')
                    ? nextPanel.querySelector(':scope > .baykat-farm-panel-heading')
                    : null;

                if (!details || !heading) {
                    return;
                }

                heading.classList.add('has-add-action');
                heading.appendChild(details);
                panel.remove();
            });
        }

        function initializeRecordFilters(root) {
            initializeCompactAddForms(root);
            root.querySelectorAll('.baykat-farm-record-tools').forEach(function (tools) {
                if (tools.dataset.filterReady) {
                    return;
                }
                tools.dataset.filterReady = 'true';

                const searchInput = tools.querySelector('[data-farm-record-search]');
                const searchToggle = tools.querySelector('[data-toggle-record-search]');
                const searchTool = tools.querySelector('[data-record-search-tool]');
                const filterTool = tools.querySelector('[data-record-filter-tool]');
                const filterInput = tools.querySelector('[data-farm-record-filter]');
                const listId = searchInput
                    ? searchInput.getAttribute('aria-controls')
                    : filterInput.getAttribute('aria-controls');
                const panel = tools.closest('.baykat-farm-panel');
                const list = panel && listId
                    ? panel.querySelector('#' + listId)
                    : null;
                const resultCount = tools.querySelector('[data-farm-record-count]');
                const emptyMessage = panel
                    ? panel.querySelector('[data-farm-record-empty]')
                    : null;

                if (!list || !panel || !resultCount) {
                    console.error('Baykat Farm record filters could not find their list.');
                    return;
                }

                const cards = Array.from(list.querySelectorAll('[data-farm-record-card]'));

                function filterRecords() {
                    const searchTerm = normalizeSearchText(searchInput ? searchInput.value.trim() : '');
                    const selectedFilter = filterInput ? filterInput.value : 'all';
                    let visibleCount = 0;

                    cards.forEach(function (card) {
                        const cardText = normalizeSearchText(card.dataset.searchText || card.textContent);
                        const matchesSearch = cardText.includes(searchTerm);
                        const matchesFilter = selectedFilter === 'all'
                            || card.dataset.farmRecordFilterValue === selectedFilter;
                        const isVisible = matchesSearch
                            && matchesFilter
                            && card.dataset.farmScopeHidden !== 'true';

                        card.hidden = !isVisible;

                        if (isVisible) {
                            visibleCount += 1;
                        }
                    });

                    resultCount.textContent = visibleCount + (visibleCount === 1 ? ' résultat' : ' résultats');

                    if (emptyMessage) {
                        emptyMessage.hidden = visibleCount > 0;
                    }
                }

                if (searchInput) {
                    searchInput.addEventListener('input', filterRecords);
                }

                if (searchToggle && searchTool) {
                    searchToggle.addEventListener('click', function () {
                        const isExpanded = searchToggle.getAttribute('aria-expanded') === 'true';
                        searchToggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
                        searchToggle.setAttribute('aria-label', isExpanded ? 'Afficher la recherche' : 'Masquer la recherche');
                        searchTool.hidden = isExpanded;
                        if (filterTool) {
                            filterTool.hidden = isExpanded;
                        }

                        if (!isExpanded && searchInput) {
                            searchInput.focus();
                        }
                    });
                }

                if (filterInput) {
                    filterInput.addEventListener('change', filterRecords);
                }

                filterRecords();
            });
        }

        function filterWorkspaceToFarm(workspaceTarget, farmId) {
            const farmIdString = String(farmId);
            const farmParcels = Array.from(workspaceTarget.querySelectorAll('[data-parcel-record-card]'))
                .filter(function (card) {
                    return card.dataset.parcelFarmId === farmIdString;
                });
            const parcelIds = new Set(farmParcels.map(function (card) {
                return card.dataset.parcelId;
            }));

            workspaceTarget.querySelectorAll('[data-parcel-record-card]').forEach(function (card) {
                const isOutOfScope = card.dataset.parcelFarmId !== farmIdString;
                card.dataset.farmScopeHidden = isOutOfScope ? 'true' : 'false';
                card.hidden = isOutOfScope;
            });
            workspaceTarget.querySelectorAll('[data-farm-id]').forEach(function (record) {
                const isOutOfScope = record.dataset.farmId !== farmIdString;
                record.dataset.farmScopeHidden = isOutOfScope ? 'true' : 'false';
                record.hidden = isOutOfScope;
            });
            workspaceTarget.querySelectorAll('select[name="parcel_id"] option').forEach(function (option) {
                if (option.value) {
                    option.hidden = !parcelIds.has(option.value);
                }
            });
            workspaceTarget.querySelectorAll('select[name="farm_id"] option').forEach(function (option) {
                if (option.value) {
                    option.hidden = option.value !== farmIdString;
                }
            });
            workspaceTarget.querySelectorAll('select[name="farm_id"]').forEach(function (select) {
                select.value = farmIdString;
            });

            workspaceTarget.querySelectorAll('.baykat-farm-workspace-section').forEach(function (section) {
                const count = section.querySelector(':scope > summary .baykat-farm-workspace-count');
                if (!count) {
                    return;
                }

                const records = Array.from(section.querySelectorAll('[data-farm-id], [data-parcel-record-card]'));
                const visibleRecords = records.filter(function (record) {
                    return !record.hidden
                        && (record.dataset.farmId === farmIdString || record.dataset.parcelFarmId === farmIdString);
                });
                const transactionIds = new Set(visibleRecords.map(function (record) {
                    return record.dataset.transactionId;
                }).filter(Boolean));
                count.textContent = String(transactionIds.size + visibleRecords.filter(function (record) {
                    return !record.dataset.transactionId;
                }).length);
            });

            workspaceTarget.querySelectorAll('[data-farm-finance-total]').forEach(function (total) {
                const type = total.dataset.farmFinanceTotal;
                const financePage = total.closest('.baykat-finance-page');
                const rows = Array.from((financePage || workspaceTarget).querySelectorAll('.baykat-finance-transaction-row'))
                    .filter(function (row) {
                        return row.dataset.farmId === farmIdString
                                && (type === 'balance' || type === 'count' || type === row.dataset.transactionType)
                                && row.dataset.farmScopeHidden !== 'true';
                    });

                if (type === 'count') {
                    total.textContent = String(rows.length);
                    return;
                }

                const amount = rows.reduce(function (sum, row) {
                    const rowAmount = Number(row.dataset.amount || 0);
                    return sum + (type === 'balance' && row.dataset.transactionType === 'expense' ? -rowAmount : rowAmount);
                }, 0);
                total.textContent = Math.round(amount).toLocaleString('fr-FR') + ' FCFA';
            });
        }

        function filterWorkspaceToParcel(workspaceTarget, parcelId) {
            const parcelIdString = String(parcelId);

            workspaceTarget.querySelectorAll('[data-farm-record-card], [data-stock-record], .baykat-finance-transaction-row').forEach(function (record) {
                const isOutOfScope = record.dataset.parcelId !== parcelIdString;
                record.dataset.farmScopeHidden = isOutOfScope ? 'true' : 'false';
                record.hidden = isOutOfScope;
            });
            workspaceTarget.querySelectorAll('select[name="parcel_id"] option').forEach(function (option) {
                if (option.value) {
                    option.hidden = option.value !== parcelIdString;
                }
            });
            workspaceTarget.querySelectorAll('select[name="parcel_id"]').forEach(function (select) {
                select.value = parcelIdString;
            });

            workspaceTarget.querySelectorAll('.baykat-farm-workspace-section').forEach(function (section) {
                const count = section.querySelector(':scope > summary .baykat-farm-workspace-count');
                if (!count) {
                    return;
                }

                const records = Array.from(section.querySelectorAll('[data-farm-record-card], [data-stock-record], .baykat-finance-transaction-row'));
                const visibleRecords = records.filter(function (record) {
                    return record.dataset.parcelId === parcelIdString && record.dataset.farmScopeHidden !== 'true';
                });
                const transactionIds = new Set(visibleRecords.map(function (record) {
                    return record.dataset.transactionId;
                }).filter(Boolean));
                count.textContent = String(transactionIds.size + visibleRecords.filter(function (record) {
                    return !record.dataset.transactionId;
                }).length);
            });

            workspaceTarget.querySelectorAll('[data-farm-finance-total]').forEach(function (total) {
                const type = total.dataset.farmFinanceTotal;
                const financePage = total.closest('.baykat-finance-page');
                const rows = Array.from((financePage || workspaceTarget).querySelectorAll('.baykat-finance-transaction-row'))
                    .filter(function (row) {
                        return row.dataset.parcelId === parcelIdString
                            && (type === 'balance' || type === 'count' || type === row.dataset.transactionType)
                            && row.dataset.farmScopeHidden !== 'true';
                    });

                if (type === 'count') {
                    total.textContent = String(rows.length);
                    return;
                }

                const amount = rows.reduce(function (sum, row) {
                    const rowAmount = Number(row.dataset.amount || 0);
                    return sum + (type === 'balance' && row.dataset.transactionType === 'expense' ? -rowAmount : rowAmount);
                }, 0);
                total.textContent = Math.round(amount).toLocaleString('fr-FR') + ' FCFA';
            });
        }

        function initializeWorkspaceTabs(root, selectedSectionId) {
            root.querySelectorAll('.baykat-farm-modal-workspace [data-dashboard-workspace-target]').forEach(function (target) {
                if (target.dataset.tabsReady) {
                    return;
                }

                const sections = Array.from(target.children).filter(function (child) {
                    return child.matches('.baykat-farm-workspace-section');
                });

                if (sections.length < 2) {
                    return;
                }

                target.dataset.tabsReady = 'true';
                const tablist = document.createElement('div');
                const tabs = [];
                tablist.className = 'baykat-farm-workspace-tabs';
                tablist.setAttribute('role', 'tablist');
                tablist.setAttribute('aria-label', 'Catégories de gestion');

                sections.forEach(function (section, index) {
                    const summary = section.querySelector(':scope > summary strong');
                    const count = section.querySelector(':scope > summary .baykat-farm-workspace-count');
                    const tab = document.createElement('button');
                    const tabId = 'baykat-workspace-tab-' + section.id;
                    const label = document.createElement('span');
                    const countBadge = document.createElement('span');
                    const sectionLabel = summary ? summary.textContent.trim() : 'Section ' + (index + 1);
                    const itemCount = count ? count.textContent.trim() : '';

                    tab.className = 'baykat-farm-workspace-tab';
                    tab.type = 'button';
                    tab.id = tabId;
                    tab.setAttribute('role', 'tab');
                    tab.setAttribute('aria-controls', section.id);
                    tab.setAttribute('aria-label', itemCount ? sectionLabel + ', ' + itemCount + ' éléments' : sectionLabel);
                    label.textContent = sectionLabel;
                    tab.appendChild(label);
                    if (itemCount) {
                        countBadge.className = 'baykat-farm-workspace-tab-count';
                        countBadge.setAttribute('aria-hidden', 'true');
                        countBadge.textContent = itemCount;
                        tab.appendChild(countBadge);
                    }
                    section.setAttribute('role', 'tabpanel');
                    section.setAttribute('aria-labelledby', tabId);
                    section.tabIndex = 0;
                    tabs.push({ button: tab, section: section });
                    tablist.appendChild(tab);
                });

                function activateTab(selectedTab, moveFocus) {
                    tabs.forEach(function (tab) {
                        const isActive = tab === selectedTab;
                        tab.button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        tab.button.tabIndex = isActive ? 0 : -1;
                        tab.section.hidden = !isActive;
                        tab.section.open = isActive;
                    });

                    if (moveFocus) {
                        selectedTab.button.focus();
                    }
                }

                tablist.addEventListener('click', function (event) {
                    const button = event.target.closest('[role="tab"]');
                    const selectedTab = tabs.find(function (tab) {
                        return tab.button === button;
                    });

                    if (selectedTab) {
                        activateTab(selectedTab, false);
                    }
                });
                tablist.addEventListener('keydown', function (event) {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                        return;
                    }

                    event.preventDefault();
                    const currentIndex = tabs.findIndex(function (tab) {
                        return tab.button.getAttribute('aria-selected') === 'true';
                    });
                    let nextIndex = currentIndex;

                    if (event.key === 'ArrowLeft') {
                        nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                    } else if (event.key === 'ArrowRight') {
                        nextIndex = (currentIndex + 1) % tabs.length;
                    } else if (event.key === 'Home') {
                        nextIndex = 0;
                    } else {
                        nextIndex = tabs.length - 1;
                    }

                    activateTab(tabs[nextIndex], true);
                });
                target.insertBefore(tablist, target.firstChild);

                const targetSection = selectedSectionId
                    ? sections.find(function (section) {
                        return section.id === selectedSectionId
                            || section.contains(document.getElementById(selectedSectionId));
                    })
                    : null;
                const initialTab = tabs.find(function (tab) {
                    return tab.section === targetSection;
                }) || tabs[0];
                activateTab(initialTab, false);
            });
        }

        function showRequestError(message) {
            let notice = app.querySelector('.baykat-farm-request-error');

            if (!notice) {
                notice = document.createElement('div');
                notice.className = 'baykat-farm-notice baykat-farm-notice-error baykat-farm-request-error';
                notice.setAttribute('role', 'alert');
                app.insertBefore(notice, app.querySelector('.baykat-farm-navigation'));
            }

            notice.textContent = message;
            app.setAttribute('aria-busy', 'false');
        }

        function initializeImagePreviews(root) {
            root.querySelectorAll('.baykat-farm-image-input').forEach(function (input) {
                if (input.dataset.previewReady) {
                    return;
                }
                input.dataset.previewReady = 'true';

                const picker = input.closest('.baykat-farm-image-picker');
                const preview = picker.querySelector('.baykat-farm-image-preview');
                const previewImage = preview.querySelector('img');
                const placeholder = preview.querySelector('.baykat-farm-image-placeholder');
                const errorMessage = document.createElement('span');
                let previewUrl = null;

                errorMessage.className = 'baykat-farm-image-error';
                errorMessage.setAttribute('role', 'alert');
                errorMessage.hidden = true;
                picker.after(errorMessage);

                input.addEventListener('change', function () {
                    const file = input.files && input.files[0];
                    errorMessage.hidden = true;

                    if (previewUrl) {
                        URL.revokeObjectURL(previewUrl);
                        previewUrl = null;
                    }

                    if (!file) {
                        previewImage.hidden = true;
                        placeholder.hidden = false;
                        return;
                    }

                    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                        errorMessage.textContent = 'Choisissez une image JPG, PNG ou WebP.';
                    } else if (file.size > 5 * 1024 * 1024) {
                        errorMessage.textContent = 'La photo doit faire 5 Mo maximum.';
                    } else {
                        previewUrl = URL.createObjectURL(file);
                        previewImage.src = previewUrl;
                        previewImage.hidden = false;
                        placeholder.hidden = true;
                        return;
                    }

                    input.value = '';
                    previewImage.hidden = true;
                    placeholder.hidden = false;
                    errorMessage.hidden = false;
                });
            });
        }

        function initializeMobileNavigation() {
            const toggle = app.querySelector('[data-mobile-menu-toggle]');

            if (!toggle || toggle.dataset.mobileMenuReady) {
                return;
            }

            toggle.dataset.mobileMenuReady = 'true';

            function setMenuOpen(isOpen) {
                app.classList.toggle('is-mobile-nav-open', isOpen);
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                toggle.setAttribute('aria-label', isOpen ? 'Fermer le menu de navigation' : 'Ouvrir le menu de navigation');
            }

            toggle.addEventListener('click', function () {
                setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
            });

            app.addEventListener('click', function (event) {
                if (event.target.closest('.baykat-farm-site-navigation a, .baykat-farm-account-navigation a, .baykat-farm-navigation a')) {
                    setMenuOpen(false);
                }
            });

            app.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && app.classList.contains('is-mobile-nav-open')) {
                    setMenuOpen(false);
                    toggle.focus();
                }
            });

            window.addEventListener('resize', function () {
                if (window.matchMedia('(min-width: 641px)').matches) {
                    setMenuOpen(false);
                }
            });
        }

        function initializeDashboardModals() {
            if (app.dataset.dashboardModalsReady) {
                return;
            }

            app.dataset.dashboardModalsReady = 'true';
            const modalHistory = [];

            function findDashboardRecord(attribute, value) {
                const liveRecord = Array.from(app.querySelectorAll('[' + attribute + ']')).find(function (record) {
                    return record.getAttribute(attribute) === String(value);
                });

                if (liveRecord) {
                    return liveRecord;
                }

                const workspace = app.querySelector('[data-dashboard-workspace-template]');
                if (!workspace) {
                    return null;
                }

                return Array.from(workspace.content.querySelectorAll('[' + attribute + ']')).find(function (record) {
                    return record.getAttribute(attribute) === String(value);
                }) || null;
            }

            function showModal(dialog, title, content) {
                const modalTitle = dialog.querySelector('[data-dashboard-modal-title]');
                const modalBody = dialog.querySelector('[data-dashboard-modal-body]');
                modalTitle.textContent = title;
                modalBody.replaceChildren(content);

                if (!dialog.open) {
                    dialog.showModal();
                    const closeButton = dialog.querySelector('[data-close-dashboard-modal]');
                    if (closeButton) {
                        closeButton.focus({ preventScroll: true });
                    }
                }
            }

            function cloneRecord(record) {
                const clone = record.cloneNode(true);
                const idMap = new Map();

                clone.hidden = false;
                clone.classList.add('baykat-farm-modal-record');
                clone.removeAttribute('data-farm-record-card');
                clone.removeAttribute('id');
                clone.querySelectorAll('[data-open-farm-modal], [data-open-parcel-modal]').forEach(function (trigger) {
                    trigger.remove();
                });

                clone.querySelectorAll('[id]').forEach(function (element) {
                    const oldId = element.id;
                    const newId = oldId + '-modal';
                    idMap.set(oldId, newId);
                    element.id = newId;
                });
                clone.querySelectorAll('[for]').forEach(function (element) {
                    if (idMap.has(element.htmlFor)) {
                        element.htmlFor = idMap.get(element.htmlFor);
                    }
                });
                clone.querySelectorAll('[aria-labelledby], [aria-describedby]').forEach(function (element) {
                    ['aria-labelledby', 'aria-describedby'].forEach(function (attribute) {
                        const value = element.getAttribute(attribute);

                        if (value) {
                            element.setAttribute(attribute, value.split(/\s+/).map(function (id) {
                                return idMap.get(id) || id;
                            }).join(' '));
                        }
                    });
                });

                return clone;
            }

            function openParcelModal(dialog, parcelId, addBackButton, selectedSectionId, revealActivityId, notice) {
                const record = findDashboardRecord('data-parcel-id', parcelId);

                if (!record) {
                    console.error('Baykat Farm could not find parcel details for modal:', parcelId);
                    return;
                }

                if (addBackButton) {
                    const modalTitle = dialog.querySelector('[data-dashboard-modal-title]');
                    const modalBody = dialog.querySelector('[data-dashboard-modal-body]');
                    modalHistory.push({
                        title: modalTitle.textContent,
                        nodes: Array.from(modalBody.childNodes)
                    });
                }

                const content = document.createElement('div');
                const clonedRecord = cloneRecord(record);
                const name = record.querySelector('h4');
                content.className = 'baykat-farm-modal-detail';

                if (modalHistory.length) {
                    const backButton = document.createElement('button');
                    backButton.className = 'baykat-farm-modal-back';
                    backButton.type = 'button';
                    backButton.dataset.modalBack = 'true';
                    backButton.textContent = '← Retour à ' + modalHistory[modalHistory.length - 1].title;
                    content.appendChild(backButton);
                }

                content.appendChild(clonedRecord);

                const workspace = app.querySelector('[data-dashboard-workspace-template]');
                const modalWorkspaceTemplate = dialog.querySelector('[data-dashboard-workspace-template-modal]');

                if (workspace && modalWorkspaceTemplate) {
                    const management = modalWorkspaceTemplate.content.firstElementChild.cloneNode(true);
                    const managementTarget = management.querySelector('[data-dashboard-workspace-target]');
                    const workspaceSections = workspace.content.cloneNode(true);
                    const relevantSections = [
                        'baykat-workspace-crops',
                        'baykat-workspace-activities',
                        'baykat-workspace-finances',
                        'baykat-workspace-stocks'
                    ];

                    managementTarget.appendChild(workspaceSections);
                    managementTarget.querySelectorAll('.baykat-farm-workspace-section').forEach(function (section) {
                        if (!relevantSections.includes(section.id)) {
                            section.remove();
                        }
                    });
                    filterWorkspaceToParcel(managementTarget, parcelId);

                    const workspaceEyebrow = management.querySelector('.baykat-farm-modal-workspace-heading .baykat-farm-eyebrow');
                    const workspaceHeading = management.querySelector('.baykat-farm-modal-workspace-heading h3');
                    const workspaceDescription = management.querySelector('.baykat-farm-modal-workspace-heading p');
                    const parcelName = name ? name.textContent : 'cette parcelle';

                    if (workspaceEyebrow) {
                        workspaceEyebrow.textContent = 'Gestion de la parcelle';
                    }
                    if (workspaceHeading) {
                        workspaceHeading.textContent = 'Cultures, activités, finances et stocks';
                    }
                    if (workspaceDescription) {
                        workspaceDescription.textContent = 'Choisissez une rubrique pour consulter les informations de ' + parcelName + '. Cultures, activités, finances et stocks restent liés à cette parcelle.';
                    }

                    content.appendChild(management);
                }

                showModal(dialog, name ? name.textContent : 'Détail de la parcelle', content);
                initializeWorkspaceTabs(content, selectedSectionId);
                initializeImagePreviews(content);
                initializeRecordFilters(content);
                if (revealActivityId) {
                    const activityCard = content.querySelector('[data-activity-id="' + revealActivityId + '"]');
                    const financeDetails = activityCard && activityCard.querySelector('.baykat-farm-activity-finances');
                    if (financeDetails) {
                        financeDetails.open = true;
                    }
                }
                if (notice) {
                    const noticeElement = document.createElement('div');
                    const backButton = content.querySelector('[data-modal-back]');
                    noticeElement.className = notice.className;
                    noticeElement.setAttribute('role', notice.role);
                    noticeElement.textContent = notice.text;
                    content.insertBefore(noticeElement, backButton ? backButton.nextSibling : content.firstChild);
                }
                document.dispatchEvent(new Event('baykat:farm-module-loaded'));
            }

            function openFarmModal(dialog, farmId, selectedSectionId) {
                const record = findDashboardRecord('data-farm-id', farmId);

                if (!record) {
                    console.error('Baykat Farm could not find farm details for modal:', farmId);
                    return;
                }

                if (dialog.open) {
                    const modalTitle = dialog.querySelector('[data-dashboard-modal-title]');
                    const modalBody = dialog.querySelector('[data-dashboard-modal-body]');
                    modalHistory.push({
                        title: modalTitle.textContent,
                        nodes: Array.from(modalBody.childNodes)
                    });
                } else {
                    modalHistory.length = 0;
                }

                const content = document.createElement('div');
                const farmDetail = document.createElement('div');
                const clonedFarm = cloneRecord(record);
                const farmName = record.querySelector('h4');
                const workspace = app.querySelector('[data-dashboard-workspace-template]');
                const modalWorkspaceTemplate = dialog.querySelector('[data-dashboard-workspace-template-modal]');

                content.className = 'baykat-farm-modal-detail baykat-farm-modal-farm-page';

                if (modalHistory.length) {
                    const backButton = document.createElement('button');
                    backButton.className = 'baykat-farm-modal-back';
                    backButton.type = 'button';
                    backButton.dataset.modalBack = 'true';
                    backButton.textContent = '← Retour à ' + modalHistory[modalHistory.length - 1].title;
                    content.appendChild(backButton);
                }

                farmDetail.className = 'baykat-farm-modal-farm-detail';
                farmDetail.appendChild(clonedFarm);
                content.appendChild(farmDetail);

                if (workspace && modalWorkspaceTemplate) {
                    const management = modalWorkspaceTemplate.content.firstElementChild.cloneNode(true);
                    const managementTarget = management.querySelector('[data-dashboard-workspace-target]');
                    managementTarget.appendChild(workspace.content.cloneNode(true));
                    const farmsSection = managementTarget.querySelector('#baykat-workspace-farms');

                    if (farmsSection) {
                        farmsSection.remove();
                    }
                    managementTarget.querySelectorAll('.baykat-farm-workspace-section').forEach(function (section) {
                        if (section.id !== 'baykat-workspace-parcels') {
                            section.remove();
                        } else {
                            section.open = true;
                        }
                    });

                    filterWorkspaceToFarm(managementTarget, farmId);

                    if (selectedSectionId) {
                        managementTarget.querySelectorAll('[data-open-parcel-modal]').forEach(function (trigger) {
                            trigger.dataset.openParcelModalSection = selectedSectionId;
                        });
                    }

                    const workspaceEyebrow = management.querySelector('.baykat-farm-modal-workspace-heading .baykat-farm-eyebrow');
                    const workspaceHeading = management.querySelector('.baykat-farm-modal-workspace-heading h3');
                    const workspaceDescription = management.querySelector('.baykat-farm-modal-workspace-heading p');

                    if (workspaceEyebrow) {
                        workspaceEyebrow.textContent = 'Votre exploitation';
                    }
                    if (workspaceHeading) {
                        workspaceHeading.textContent = 'Parcelles de ' + (farmName ? farmName.textContent : 'la ferme');
                    }
                    if (workspaceDescription) {
                        workspaceDescription.textContent = 'Ouvrez une parcelle pour retrouver au même endroit ses cultures, ses activités, ses finances et ses stocks.';
                    }

                    content.appendChild(management);
                }

                showModal(dialog, farmName ? farmName.textContent : 'Détail de la ferme', content);
                initializeWorkspaceTabs(content, selectedSectionId);
                initializeImagePreviews(content);
                initializeRecordFilters(content);
                document.dispatchEvent(new Event('baykat:farm-module-loaded'));
            }

            function openManagementModal(dialog, targetId) {
                const statsTemplate = dialog.querySelector('[data-dashboard-stats-template]');
                const workspaceTemplate = app.querySelector('[data-dashboard-workspace-template]');
                const modalWorkspaceTemplate = dialog.querySelector('[data-dashboard-workspace-template-modal]');
                const statsContent = statsTemplate && statsTemplate.content.firstElementChild;
                const workspaceContent = modalWorkspaceTemplate && modalWorkspaceTemplate.content.firstElementChild;

                if (!statsContent || !workspaceContent || !workspaceTemplate) {
                    console.error('Baykat Farm could not find dashboard statistics or management content.');
                    return;
                }

                modalHistory.length = 0;
                const content = document.createElement('div');
                const stats = statsContent.cloneNode(true);
                const workspaceView = workspaceContent.cloneNode(true);
                const workspaceTarget = workspaceView.querySelector('[data-dashboard-workspace-target]');
                const workspaceSections = workspaceTemplate.content.cloneNode(true);
                const farmsSection = workspaceSections.querySelector('#baykat-workspace-farms');
                const workspaceEyebrow = workspaceView.querySelector('.baykat-farm-modal-workspace-heading .baykat-farm-eyebrow');
                const workspaceHeading = workspaceView.querySelector('.baykat-farm-modal-workspace-heading h3');
                const workspaceDescription = workspaceView.querySelector('.baykat-farm-modal-workspace-heading p');
                const farmSections = [
                    'baykat-workspace-parcels',
                    'baykat-workspace-crops',
                    'baykat-workspace-activities',
                    'baykat-workspace-finances',
                    'baykat-workspace-stocks'
                ];
                content.className = 'baykat-farm-dashboard-content';
                const farmSummary = stats.querySelector('.baykat-farm-stats-farms');
                const statsDisclosure = document.createElement('details');
                const statsSummary = document.createElement('summary');
                statsDisclosure.className = 'baykat-farm-stats-disclosure';
                statsSummary.textContent = 'Afficher les statistiques';
                statsDisclosure.appendChild(statsSummary);
                statsDisclosure.appendChild(stats);

                if (farmSummary) {
                    farmSummary.remove();
                }

                if (workspaceEyebrow) {
                    workspaceEyebrow.textContent = 'Mes exploitations';
                }
                if (workspaceHeading) {
                    workspaceHeading.textContent = 'Choisissez une ferme';
                }
                if (workspaceDescription) {
                    workspaceDescription.textContent = 'Ouvrez une exploitation pour consulter ses parcelles, cultures, activités et finances.';
                }

                content.appendChild(statsDisclosure);
                content.appendChild(workspaceView);

                if (!farmsSection) {
                    console.error('Baykat Farm could not find the farm list for management modal.');
                    return;
                }

                farmsSection.open = true;
                workspaceTarget.appendChild(farmsSection);

                if (farmSections.includes(targetId)) {
                    workspaceTarget.querySelectorAll('[data-open-farm-modal]').forEach(function (trigger) {
                        trigger.dataset.openFarmModalSection = targetId;
                    });
                }

                showModal(dialog, 'Statistiques & gestion', content);
                initializeImagePreviews(content);
                initializeRecordFilters(content);
                document.dispatchEvent(new Event('baykat:farm-module-loaded'));

                if (targetId && !farmSections.includes(targetId)) {
                    const target = content.querySelector('#' + targetId);

                    if (target) {
                        let disclosure = target.closest('details');

                        while (disclosure) {
                            disclosure.open = true;
                            disclosure = disclosure.parentElement.closest('details');
                        }

                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            }

            function openDailyModal(dialog) {
                const dailyTemplate = app.querySelector('[data-dashboard-daily-template]');
                const dailyContent = dailyTemplate && dailyTemplate.content.firstElementChild;

                if (!dailyContent) {
                    console.error('Baykat Farm could not find daily dashboard content.');
                    return;
                }

                modalHistory.length = 0;
                showModal(dialog, 'Le quotidien de vos exploitations', dailyContent.cloneNode(true));
            }

            app.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-open-daily-modal], [data-open-stats-modal], [data-open-management-target], [data-open-farm-modal], [data-open-parcel-modal], [data-modal-back], [data-close-dashboard-modal]');

                if (trigger && trigger.matches('a[data-open-farm-modal], a[data-open-management-target]')) {
                    if (
                        event.button !== 0
                        || event.metaKey
                        || event.ctrlKey
                        || event.shiftKey
                        || event.altKey
                        || trigger.target === '_blank'
                    ) {
                        return;
                    }
                    event.preventDefault();
                }

                if (!trigger) {
                    const dialog = app.querySelector('[data-farm-dashboard-dialog]');
                    const parcelCard = event.target.closest('[data-parcel-record-card]');
                    const clickedControl = event.target.closest('a, button, input, select, textarea, summary, form');

                    if (dialog && parcelCard && !clickedControl) {
                        openParcelModal(dialog, parcelCard.dataset.parcelId, false);
                    }
                    return;
                }

                const dialog = app.querySelector('[data-farm-dashboard-dialog]');

                if (!dialog) {
                    const card = trigger.closest('.baykat-farm-record-card');
                    const actions = card && card.querySelector('.baykat-farm-record-actions');

                    if (actions) {
                        actions.open = true;
                        actions.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }

                const modalTitle = dialog.querySelector('[data-dashboard-modal-title]');
                const modalBody = dialog.querySelector('[data-dashboard-modal-body]');

                if (trigger.matches('[data-close-dashboard-modal]')) {
                    dialog.close();
                    modalHistory.length = 0;
                    return;
                }

                if (trigger.matches('[data-modal-back]')) {
                    const previous = modalHistory.pop();

                    if (previous) {
                        modalTitle.textContent = previous.title;
                        modalBody.replaceChildren.apply(modalBody, previous.nodes);
                        initializeImagePreviews(modalBody);
                    }
                    return;
                }

                if (trigger.matches('[data-open-daily-modal]')) {
                    openDailyModal(dialog);
                } else if (trigger.matches('[data-open-stats-modal]')) {
                    openManagementModal(dialog, '');
                } else if (trigger.matches('[data-open-management-target]')) {
                    openManagementModal(dialog, trigger.dataset.openManagementTarget);
                } else if (trigger.matches('[data-open-farm-modal]')) {
                    openFarmModal(dialog, trigger.dataset.openFarmModal, trigger.dataset.openFarmModalSection);
                } else if (trigger.matches('[data-open-parcel-modal]')) {
                    openParcelModal(dialog, trigger.dataset.openParcelModal, dialog.open, trigger.dataset.openParcelModalSection);
                }
            });

            app.addEventListener('click', function (event) {
                const dialog = app.querySelector('[data-farm-dashboard-dialog]');

                if (dialog && event.target === dialog) {
                    dialog.close();
                    modalHistory.length = 0;
                }
            });
            app.addEventListener('baykat:farm-refresh-parcel-modal', function (event) {
                const detail = event.detail || {};
                const dialog = app.querySelector('[data-farm-dashboard-dialog]');
                if (!dialog || !detail.parcelId) {
                    return;
                }
                openParcelModal(
                    dialog,
                    detail.parcelId,
                    false,
                    detail.sectionId,
                    detail.activityId,
                    detail.notice
                );
            });
            app.addEventListener('cancel', function (event) {
                if (event.target.matches('[data-farm-dashboard-dialog]')) {
                    modalHistory.length = 0;
                }
            }, true);
            app.addEventListener('close', function (event) {
                if (event.target.matches('[data-farm-dashboard-dialog]')) {
                    modalHistory.length = 0;
                }
            });
        }

        async function loadApplication(url, options) {
            const requestOptions = options || {};
            const targetUrl = new URL(url, window.location.href);
            const previousScroll = window.scrollY;
            const isSubmission = Boolean(requestOptions.formData);
            const openDetails = Array.from(app.querySelectorAll('details[open]'))
                .map(function (details) {
                    return details.id;
                })
                .filter(Boolean);
            const filterValues = Array.from(
                app.querySelectorAll('[data-farm-record-search], [data-farm-record-filter]')
            ).map(function (filter) {
                return { id: filter.id, value: filter.value };
            }).filter(function (filter) {
                return filter.id;
            });

            app.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(targetUrl.href, {
                    method: isSubmission ? 'POST' : 'GET',
                    body: requestOptions.formData || null,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    throw new Error('La requête a échoué (HTTP ' + response.status + ').');
                }

                const html = await response.text();
                const page = new DOMParser().parseFromString(html, 'text/html');
                const incomingApp = page.querySelector('.baykat-farm-app');
                const incomingNavigation = incomingApp
                    ? incomingApp.querySelector('.baykat-farm-navigation')
                    : null;
                const incomingModule = incomingApp
                    ? incomingApp.querySelector('.baykat-farm-module')
                    : null;
                const currentNavigation = app.querySelector('.baykat-farm-navigation');
                const currentModule = app.querySelector('.baykat-farm-module');

                if (!incomingNavigation || !incomingModule || !currentNavigation || !currentModule) {
                    throw new Error('La réponse ne contient pas le module attendu.');
                }

                const incomingNotice = isSubmission
                    ? incomingModule.querySelector('.baykat-farm-notice')
                    : null;
                const submissionNotice = incomingNotice
                    ? {
                        className: incomingNotice.className,
                        role: incomingNotice.getAttribute('role') || 'status',
                        text: incomingNotice.textContent.trim()
                    }
                    : null;
                currentNavigation.replaceWith(incomingNavigation);
                currentModule.replaceWith(incomingModule);
                const errorNotice = app.querySelector('.baykat-farm-request-error');

                if (errorNotice) {
                    errorNotice.remove();
                }

                if (requestOptions.pushHistory) {
                    history.replaceState(
                        Object.assign({}, history.state, { scrollY: previousScroll }),
                        '',
                        window.location.href
                    );
                    history.pushState({ scrollY: previousScroll }, '', targetUrl.href);
                } else if (requestOptions.replaceHistory) {
                    history.replaceState({ scrollY: previousScroll }, '', targetUrl.href);
                }

                app.setAttribute('aria-busy', 'false');
                initializeImagePreviews(app);
                initializeRecordFilters(app);
                openDetails.forEach(function (detailsId) {
                    const details = app.querySelector('#' + detailsId);

                    if (details) {
                        details.open = true;
                    }
                });
                filterValues.forEach(function (savedFilter) {
                    const filter = app.querySelector('#' + savedFilter.id);

                    if (filter) {
                        filter.value = savedFilter.value;

                        if (filter.matches('[data-farm-record-search]') && savedFilter.value) {
                            const tools = filter.closest('.baykat-farm-record-tools');
                            const searchToggle = tools && tools.querySelector('[data-toggle-record-search]');
                            const searchTool = tools && tools.querySelector('[data-record-search-tool]');

                            if (searchToggle && searchTool) {
                                searchTool.hidden = false;
                                searchToggle.setAttribute('aria-expanded', 'true');
                                searchToggle.setAttribute('aria-label', 'Masquer la recherche');
                            }
                        }

                        filter.dispatchEvent(new Event(filter.matches('select') ? 'change' : 'input', { bubbles: true }));
                    }
                });
                document.dispatchEvent(new CustomEvent('baykat:farm-module-loaded'));

                if (targetUrl.hash) {
                    const anchor = document.getElementById(targetUrl.hash.slice(1));

                    if (anchor) {
                        let disclosure = anchor.closest('details');

                        while (disclosure) {
                            disclosure.open = true;
                            disclosure = disclosure.parentElement.closest('details');
                        }

                        anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                } else {
                    window.scrollTo(0, requestOptions.scrollY ?? previousScroll);
                }
                return { success: true, notice: submissionNotice };
            } catch (error) {
                if (!isSubmission && requestOptions.pushHistory) {
                    window.location.assign(targetUrl.href);
                    return { success: false };
                }

                showRequestError(
                    isSubmission
                        ? 'La réponse du serveur n’a pas pu être confirmée. Vérifiez les enregistrements avant de réessayer.'
                        : 'Le module n’a pas pu être chargé. Vérifiez votre connexion puis réessayez.'
                );
                console.error('Baykat Farm request failed:', error);
                return { success: false };
            }
        }

        app.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');

            if (
                !link
                || link.hasAttribute('data-open-farm-modal')
                || link.hasAttribute('data-open-management-target')
                || event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
                || link.target === '_blank'
            ) {
                return;
            }

            const targetUrl = new URL(link.href, window.location.href);
            const isSamePage = targetUrl.origin === window.location.origin
                && targetUrl.pathname === window.location.pathname
                && targetUrl.search === window.location.search;

            if (isSamePage && targetUrl.hash) {
                const anchor = document.getElementById(targetUrl.hash.slice(1));

                if (anchor) {
                    event.preventDefault();
                    let disclosure = anchor.closest('details');

                    while (disclosure) {
                        disclosure.open = true;
                        disclosure = disclosure.parentElement.closest('details');
                    }

                    if (window.location.href !== targetUrl.href) {
                        history.pushState({ scrollY: window.scrollY }, '', targetUrl.href);
                    }

                    anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    return;
                }
            }

            if (
                targetUrl.origin !== window.location.origin
                || targetUrl.pathname !== window.location.pathname
                || (targetUrl.search === window.location.search && !targetUrl.hash)
            ) {
                return;
            }

            event.preventDefault();
            loadApplication(targetUrl.href, { pushHistory: true });
        });

        app.addEventListener('submit', function (event) {
            if (event.defaultPrevented) {
                return;
            }

            const form = event.target;

            if (!form.matches('.baykat-farm-form, .baykat-farm-image-update-form, .baykat-farm-record-form, .baykat-farm-delete-form, .baykat-finance-form-content, .baykat-farm-activity-finance-form')) {
                return;
            }

            if (form.matches('.baykat-farm-delete-form') && !window.confirm('Confirmer la suppression ? Cette action ne peut pas être annulée.')) {
                event.preventDefault();
                return;
            }

            event.preventDefault();
            const formData = new FormData(form);
            const submitter = event.submitter
                || (document.activeElement && form.contains(document.activeElement) && document.activeElement.matches('button[type="submit"], input[type="submit"]')
                    ? document.activeElement
                    : form.querySelector('button[type="submit"][name], input[type="submit"][name]'));

            if (submitter && submitter.name && !formData.has(submitter.name)) {
                formData.set(submitter.name, submitter.value);
            }

            if (form.matches('.baykat-farm-form, .baykat-farm-image-update-form, .baykat-farm-record-form, .baykat-farm-delete-form, .baykat-farm-activity-finance-form')) {
                form.setAttribute('aria-busy', 'true');

                if (submitter) {
                    submitter.disabled = true;
                    submitter.textContent = 'Enregistrement...';
                }
            }

            const dialog = app.querySelector('[data-farm-dashboard-dialog]');
            const parcelCard = dialog && dialog.open
                ? dialog.querySelector('[data-dashboard-modal-body] [data-parcel-record-card]')
                : null;
            const selectedTab = dialog && dialog.open
                ? dialog.querySelector('[data-dashboard-modal-body] [role="tab"][aria-selected="true"]')
                : null;
            const activityField = form.querySelector('[name="activity_id"]');
            const modalContext = parcelCard
                ? {
                    parcelId: parcelCard.dataset.parcelId,
                    sectionId: selectedTab ? selectedTab.getAttribute('aria-controls') : '',
                    activityId: activityField ? activityField.value : ''
                }
                : null;

            loadApplication(form.action || window.location.href, { formData: formData }).then(function (result) {
                if (modalContext && result && result.success) {
                    app.dispatchEvent(new CustomEvent('baykat:farm-refresh-parcel-modal', {
                        detail: Object.assign({}, modalContext, { notice: result.notice })
                    }));
                }
            });
        });

        app.addEventListener('change', function (event) {
            const activityFinanceType = event.target.closest('.baykat-farm-activity-form select[name="activity_finance_type"]');
            if (activityFinanceType) {
                const categorySelect = activityFinanceType.form.querySelector('select[name="activity_finance_category"]');
                const categoryField = categorySelect && categorySelect.closest('[data-activity-finance-category-field]');
                if (categoryField && categorySelect) {
                    const isExpense = activityFinanceType.value === 'expense';
                    categoryField.hidden = !isExpense;
                    categorySelect.disabled = !isExpense;
                }
                return;
            }

            const typeSelect = event.target.closest('.baykat-farm-activity-finance-form select[name="type"]');
            if (!typeSelect) {
                return;
            }

            const categorySelect = typeSelect.form.querySelector('select[name="expense_category"]');
            const categoryField = categorySelect && categorySelect.closest('.baykat-farm-field');
            if (categoryField && categorySelect) {
                const isExpense = typeSelect.value === 'expense';
                categoryField.hidden = !isExpense;
                categorySelect.disabled = !isExpense;
            }
        });

        window.addEventListener('popstate', function (event) {
            loadApplication(window.location.href, {
                scrollY: event.state && Number.isFinite(event.state.scrollY)
                    ? event.state.scrollY
                    : 0
            });
        });

        initializeImagePreviews(app);
        initializeRecordFilters(app);
        initializeDashboardModals();
        initializeMobileNavigation();
    });
});
