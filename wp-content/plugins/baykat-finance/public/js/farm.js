document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.baykat-farm-app').forEach(function (app) {
        function normalizeSearchText(value) {
            return value
                .toLocaleLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        }

        function initializeRecordFilters(root) {
            root.querySelectorAll('.baykat-farm-record-tools').forEach(function (tools) {
                if (tools.dataset.filterReady) {
                    return;
                }
                tools.dataset.filterReady = 'true';

                const searchInput = tools.querySelector('[data-farm-record-search]');
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
                        const isVisible = matchesSearch && matchesFilter;

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

                if (filterInput) {
                    filterInput.addEventListener('change', filterRecords);
                }

                filterRecords();
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
            } catch (error) {
                if (!isSubmission && requestOptions.pushHistory) {
                    window.location.assign(targetUrl.href);
                    return;
                }

                showRequestError(
                    isSubmission
                        ? 'La réponse du serveur n’a pas pu être confirmée. Vérifiez les enregistrements avant de réessayer.'
                        : 'Le module n’a pas pu être chargé. Vérifiez votre connexion puis réessayez.'
                );
                console.error('Baykat Farm request failed:', error);
            }
        }

        app.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');

            if (
                !link
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

            if (!form.matches('.baykat-farm-form, .baykat-farm-image-update-form, .baykat-farm-record-form, .baykat-farm-delete-form, .baykat-finance-form-content')) {
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

            if (form.matches('.baykat-farm-form, .baykat-farm-image-update-form, .baykat-farm-record-form, .baykat-farm-delete-form')) {
                form.setAttribute('aria-busy', 'true');

                if (submitter) {
                    submitter.disabled = true;
                    submitter.textContent = 'Enregistrement...';
                }
            }

            loadApplication(form.action || window.location.href, { formData: formData });
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
    });
});
