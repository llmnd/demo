document.addEventListener('DOMContentLoaded', function () {
    function initializeFarmMaps(root) {
        if (!root) {
            return;
        }

        if (!window.L) {
            root.querySelectorAll('[data-farm-overview-map-error]').forEach(function (message) {
                message.hidden = false;
            });
            root.querySelectorAll('[data-location-status]').forEach(function (message) {
                message.textContent = 'La carte OpenStreetMap n’a pas pu se charger. Rechargez la page avant de choisir un emplacement.';
            });
            root.querySelectorAll('[data-geocode-search]').forEach(function (button) {
                button.disabled = true;
            });
            return;
        }

        root.querySelectorAll('[data-farm-overview-map]').forEach(function (container) {
            if (container.dataset.mapReady) {
                return;
            }

            const farms = JSON.parse(container.dataset.farms || '[]');
            const canvas = container.querySelector('[data-farm-overview-map-canvas]');
            const emptyState = container.querySelector('[data-farm-overview-map-empty]');
            if (!canvas || !farms.length) {
                if (emptyState) {
                    emptyState.hidden = false;
                }
                return;
            }

            container.dataset.mapReady = 'true';
            const map = L.map(canvas, { scrollWheelZoom: false }).setView(
                [farms[0].latitude, farms[0].longitude],
                13
            );
            map.attributionControl.setPrefix(false);
            const overviewTiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            });
            overviewTiles.on('tileerror', function () {
                const mapError = container.querySelector('[data-farm-overview-map-error]');
                if (mapError) {
                    mapError.hidden = false;
                }
            });
            overviewTiles.addTo(map);

            const bounds = [];
            const farmMarkersLayer = L.layerGroup().addTo(map);
            farms.forEach(function (farm) {
                const position = [Number(farm.latitude), Number(farm.longitude)];
                const farmIcon = L.divIcon({
                    className: 'baykat-farm-map-marker',
                    html: '<span class="baykat-farm-map-marker-badge"><svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path class="baykat-farm-marker-roof" d="M6 21 24 7l18 14"/><path class="baykat-farm-marker-building" d="M11 19v22h26V19L24 9z"/><path class="baykat-farm-marker-trim" d="M7 22 24 9l17 13M16 26h6v6h-6zM26 26h6v6h-6z"/><path class="baykat-farm-marker-door" d="M20 41V32h8v9"/><path class="baykat-farm-marker-grain" d="M37 17v-7m0 4-3-3m3 5 3-3"/></svg></span>',
                    iconSize: [48, 56],
                    iconAnchor: [24, 51],
                    popupAnchor: [0, -48],
                    tooltipAnchor: [0, -48],
                    tooltipDirection: 'top'
                });
                const marker = L.marker(position, {
                    icon: farmIcon,
                    title: farm.name,
                    alt: 'Ferme : ' + farm.name,
                    riseOnHover: true
                }).addTo(farmMarkersLayer);
                const popup = document.createElement('div');
                const name = document.createElement('strong');
                const location = document.createElement('span');
                const tooltip = document.createElement('span');
                const openButton = document.createElement('button');
                const parcelsButton = document.createElement('button');

                name.textContent = farm.name;
                location.textContent = farm.location || 'Localisation non renseignée';
                openButton.type = 'button';
                openButton.className = 'baykat-farm-map-popup-link';
                openButton.dataset.openFarmModal = String(farm.id);
                openButton.textContent = 'Voir la ferme';
                parcelsButton.type = 'button';
                parcelsButton.className = 'baykat-farm-map-popup-link';
                parcelsButton.dataset.exploreFarmParcels = String(farm.id);
                parcelsButton.textContent = 'Explorer les parcelles';
                tooltip.textContent = farm.name;
                popup.append(name, location, parcelsButton, openButton);
                marker.bindPopup(popup);
                marker.bindTooltip(tooltip, {
                    permanent: true,
                    direction: 'top',
                    offset: [0, -3],
                    opacity: 1,
                    className: 'baykat-farm-map-tooltip'
                });
                bounds.push(position);
            });

            function fitFarmBounds() {
                if (bounds.length > 1) {
                    map.fitBounds(bounds, { padding: [48, 48], maxZoom: 14 });
                } else {
                    map.setView(bounds[0], 14);
                }
            }

            fitFarmBounds();
            const resetButton = container.querySelector('[data-reset-farm-map]');
            const parcelTools = container.querySelector('[data-parcel-map-tools]');
            const parcelSelect = container.querySelector('[data-parcel-map-select]');
            const farmName = container.querySelector('[data-parcel-map-farm-name]');
            const parcelStatus = container.querySelector('[data-parcel-map-status]');
            const drawButton = container.querySelector('[data-parcel-map-draw]');
            const finishButton = container.querySelector('[data-parcel-map-finish]');
            const cancelButton = container.querySelector('[data-parcel-map-cancel]');
            const clearButton = container.querySelector('[data-parcel-map-clear]');
            const saveButton = container.querySelector('[data-parcel-map-save]');
            const backButton = container.querySelector('[data-parcel-map-back]');
            const parcelLayers = L.featureGroup().addTo(map);
            let activeFarm = null;
            let activeParcel = null;
            let currentBoundary = [];
            let isDrawing = false;
            let isDirty = false;
            let isSaving = false;
            let parcelPolygon = null;
            const parcelColors = {
                growing: '#56834e',
                planned: '#b39a58',
                harvested: '#b47b45',
                paused: '#78806a'
            };

            function setParcelStatus(message, isError) {
                if (parcelStatus) {
                    parcelStatus.textContent = message;
                    parcelStatus.classList.toggle('is-error', Boolean(isError));
                }
            }

            function selectedCampaignColor(parcel) {
                const growingCampaign = parcel.campaigns.find(function (campaign) { return campaign.status === 'growing'; });
                if (growingCampaign) {
                    return parcelColors.growing;
                }
                const currentCampaign = parcel.campaigns.find(function (campaign) { return parcelColors[campaign.status]; });
                return currentCampaign ? parcelColors[currentCampaign.status] : parcelColors[parcel.status] || '#829071';
            }

            function makeParcelPopup(parcel) {
                const popup = document.createElement('div');
                const title = document.createElement('strong');
                const area = document.createElement('span');
                title.textContent = parcel.name;
                area.textContent = parcel.area_m2 === null
                    ? 'Superficie non renseignée'
                    : Number(parcel.area_m2).toLocaleString('fr-FR') + ' m²';
                popup.append(title, area);

                const campaignNames = parcel.campaigns.map(function (campaign) { return campaign.name; });
                if (campaignNames.length) {
                    const campaigns = document.createElement('span');
                    campaigns.textContent = 'Campagnes : ' + campaignNames.join(', ');
                    popup.appendChild(campaigns);
                } else {
                    const emptyCampaigns = document.createElement('span');
                    emptyCampaigns.textContent = 'Aucune campagne enregistrée';
                    popup.appendChild(emptyCampaigns);
                }

                const openButton = document.createElement('button');
                openButton.type = 'button';
                openButton.className = 'baykat-farm-map-popup-link';
                openButton.dataset.openParcelModal = String(parcel.id);
                openButton.textContent = 'Voir la parcelle';
                popup.appendChild(openButton);
                return popup;
            }

            function parcelTooltip(parcel) {
                const tooltip = document.createElement('span');
                const parcelName = parcel.name.trim();
                const parcelNameKey = parcelName.toLocaleLowerCase('fr-FR');
                const campaignNames = parcel.campaigns
                    .map(function (campaign) { return campaign.name.trim(); })
                    .filter(function (name, index, names) {
                        return name
                            && name.toLocaleLowerCase('fr-FR') !== parcelNameKey
                            && names.findIndex(function (candidate) {
                                return candidate.toLocaleLowerCase('fr-FR') === name.toLocaleLowerCase('fr-FR');
                            }) === index;
                    });
                tooltip.textContent = parcelName + (campaignNames.length ? ' · ' + campaignNames.join(', ') : '');
                return tooltip;
            }

            function updateParcelTools() {
                if (!parcelTools) {
                    return;
                }
                const canEdit = Boolean(activeFarm && activeFarm.can_edit && activeParcel);
                if (parcelSelect) {
                    parcelSelect.disabled = isSaving;
                }
                if (backButton) {
                    backButton.disabled = isSaving;
                }
                if (cancelButton) {
                    cancelButton.disabled = isSaving;
                }
                if (drawButton) {
                    drawButton.hidden = !canEdit || isDrawing;
                    drawButton.disabled = !canEdit;
                }
                if (finishButton) {
                    finishButton.hidden = !canEdit || !isDrawing;
                    finishButton.disabled = currentBoundary.length < 3;
                }
                if (cancelButton) {
                    cancelButton.hidden = !canEdit || ( !isDirty && !isDrawing );
                }
                if (clearButton) {
                    clearButton.hidden = !canEdit || !currentBoundary.length || isDrawing;
                }
                if (saveButton) {
                    saveButton.hidden = !canEdit || !isDirty || isDrawing;
                    saveButton.disabled = !canEdit || !isDirty || isSaving;
                }
            }

            function renderParcelBoundary() {
                parcelLayers.clearLayers();
                parcelPolygon = null;
                if (!activeFarm) {
                    updateParcelTools();
                    return;
                }

                activeFarm.parcels.forEach(function (parcel) {
                    const isSelected = activeParcel && String(parcel.id) === String(activeParcel.id);
                    const boundary = isSelected ? currentBoundary : parcel.boundary;
                    const coordinates = boundary.map(function (point) {
                        return [Number(point[0]), Number(point[1])];
                    });
                    const color = selectedCampaignColor(parcel);

                    if (isSelected && isDrawing) {
                        if (coordinates.length > 1) {
                            L.polyline(coordinates, {
                                color: color,
                                weight: 3,
                                dashArray: '6 7',
                                lineCap: 'round'
                            }).addTo(parcelLayers);
                        }
                        coordinates.forEach(function (position) {
                            L.circleMarker(position, {
                                radius: 5,
                                color: '#fff',
                                weight: 2,
                                fillColor: color,
                                fillOpacity: 1
                            }).addTo(parcelLayers);
                        });
                        return;
                    }

                    if (coordinates.length < 3) {
                        return;
                    }

                    const polygon = L.polygon(coordinates, {
                        color: color,
                        weight: isSelected ? 3 : 2,
                        opacity: .95,
                        fillColor: color,
                        fillOpacity: isSelected ? .5 : .3
                    }).addTo(parcelLayers);
                    polygon.bindPopup(makeParcelPopup(parcel));
                    polygon.bindTooltip(parcelTooltip(parcel), {
                        permanent: true,
                        direction: 'center',
                        className: 'baykat-farm-parcel-tooltip'
                    });

                    if (isSelected) {
                        parcelPolygon = polygon;
                    }

                    if (isSelected && activeFarm.can_edit) {
                        coordinates.forEach(function (position, index) {
                            const vertexIcon = L.divIcon({
                                className: 'baykat-farm-parcel-vertex',
                                html: '<span></span>',
                                iconSize: [16, 16],
                                iconAnchor: [8, 8]
                            });
                            const vertex = L.marker(position, {
                                icon: vertexIcon,
                                draggable: true,
                                bubblingMouseEvents: false,
                                keyboard: true,
                                title: 'Déplacer un sommet du contour'
                            }).addTo(parcelLayers);
                            vertex.on('drag', function (event) {
                                currentBoundary[index] = [
                                    event.target.getLatLng().lat,
                                    event.target.getLatLng().lng
                                ];
                                if (parcelPolygon) {
                                    parcelPolygon.setLatLngs(currentBoundary);
                                }
                            });
                            vertex.on('dragend', function () {
                                isDirty = true;
                                setParcelStatus('Contour modifié. Enregistrez pour conserver les changements.');
                                updateParcelTools();
                            });
                        });
                    }
                });
                updateParcelTools();
            }

            function chooseParcel(parcelId) {
                activeParcel = activeFarm.parcels.find(function (parcel) {
                    return String(parcel.id) === String(parcelId);
                }) || null;
                currentBoundary = activeParcel ? activeParcel.boundary.map(function (point) {
                    return [Number(point[0]), Number(point[1])];
                }) : [];
                isDrawing = false;
                isDirty = false;
                renderParcelBoundary();

                if (!activeParcel) {
                    setParcelStatus('Choisissez une parcelle pour afficher son contour et ses campagnes.');
                } else if (!activeParcel.boundary.length) {
                    setParcelStatus(activeFarm.can_edit
                        ? 'Aucun contour enregistré. Cliquez sur « Tracer » puis placez les points sur la carte.'
                        : 'Le contour de cette parcelle n’a pas encore été dessiné.');
                } else {
                    const campaigns = activeParcel.campaigns.map(function (campaign) { return campaign.name; });
                    setParcelStatus(
                        activeParcel.name
                        + (activeParcel.area_m2 === null ? '' : ' · ' + Number(activeParcel.area_m2).toLocaleString('fr-FR') + ' m²')
                        + (campaigns.length ? ' · Campagnes : ' + campaigns.join(', ') : ' · Aucune campagne enregistrée')
                        + (activeFarm.can_edit ? ' · Déplacez les points pour ajuster le contour.' : '')
                    );
                }
                if (activeParcel && currentBoundary.length >= 3) {
                    const selectedBounds = L.latLngBounds(currentBoundary);
                    if (selectedBounds.isValid()) {
                        map.fitBounds(selectedBounds, { padding: [42, 42], maxZoom: 18 });
                    }
                }
            }

            function returnToFarms() {
                if (isDirty || isDrawing) {
                    if (!window.confirm('Les modifications du contour ne sont pas enregistrées. Quitter sans les conserver ?')) {
                        return;
                    }
                }
                activeFarm = null;
                activeParcel = null;
                currentBoundary = [];
                isDrawing = false;
                isDirty = false;
                parcelLayers.clearLayers();
                map.doubleClickZoom.enable();
                farmMarkersLayer.addTo(map);
                parcelTools.hidden = true;
                if (resetButton) {
                    resetButton.hidden = false;
                }
                map.closePopup();
                fitFarmBounds();
            }

            function exploreFarm(farmId) {
                const farm = farms.find(function (item) { return String(item.id) === String(farmId); });
                if (!farm || !parcelTools || !parcelSelect) {
                    return;
                }
                if (isDirty || isDrawing) {
                    if (!window.confirm('Les modifications du contour ne sont pas enregistrées. Changer de ferme ?')) {
                        return;
                    }
                }

                activeFarm = farm;
                activeParcel = null;
                currentBoundary = [];
                isDrawing = false;
                isDirty = false;
                parcelLayers.clearLayers();
                farmMarkersLayer.remove();
                map.doubleClickZoom.enable();
                parcelTools.hidden = false;
                if (resetButton) {
                    resetButton.hidden = true;
                }
                farmName.textContent = farm.name;
                parcelSelect.replaceChildren();
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Choisir une parcelle';
                parcelSelect.appendChild(placeholder);
                farm.parcels.forEach(function (parcel) {
                    const option = document.createElement('option');
                    option.value = String(parcel.id);
                    option.textContent = parcel.name;
                    parcelSelect.appendChild(option);
                });
                parcelSelect.value = '';
                renderParcelBoundary();
                updateParcelTools();

                if (farm.parcels.length) {
                    const mappedCount = farm.parcels.filter(function (parcel) { return parcel.boundary.length >= 3; }).length;
                    setParcelStatus(
                        mappedCount
                            ? mappedCount + ' parcelle' + (mappedCount > 1 ? 's' : '') + ' affichée' + (mappedCount > 1 ? 's' : '') + '. Choisissez une parcelle pour voir ses détails ou ajuster son contour.'
                            : (farm.can_edit
                                ? 'Aucun contour n’est encore tracé. Choisissez une parcelle pour dessiner son contour et visualiser ses cultures.'
                                : 'Aucun contour n’est encore disponible pour les parcelles de cette ferme.')
                    );
                    if (parcelLayers.getBounds().isValid()) {
                        map.fitBounds(parcelLayers.getBounds(), { padding: [42, 42], maxZoom: 17 });
                    }
                } else {
                    setParcelStatus('Cette ferme ne contient pas encore de parcelles.');
                }
                map.setView([Number(farm.latitude), Number(farm.longitude)], 17);
                window.requestAnimationFrame(function () {
                    map.invalidateSize();
                });
            }

            if (parcelTools) {
                parcelSelect.addEventListener('change', function () {
                    if (isDirty || isDrawing) {
                        parcelSelect.value = activeParcel ? String(activeParcel.id) : '';
                        setParcelStatus('Enregistrez ou annulez le contour avant de changer de parcelle.', true);
                        return;
                    }
                    chooseParcel(parcelSelect.value);
                });

                drawButton.addEventListener('click', function () {
                    if (!activeParcel || !activeFarm.can_edit) {
                        return;
                    }
                    if (currentBoundary.length && !window.confirm('Le nouveau tracé remplacera le contour actuel. Continuer ?')) {
                        return;
                    }
                    currentBoundary = [];
                    isDrawing = true;
                    isDirty = false;
                    map.doubleClickZoom.disable();
                    renderParcelBoundary();
                    setParcelStatus('Cliquez sur la carte pour placer les sommets du contour, puis terminez le tracé.');
                });

                finishButton.addEventListener('click', function () {
                    if (currentBoundary.length < 3) {
                        setParcelStatus('Ajoutez au moins 3 points pour fermer un contour.', true);
                        return;
                    }
                    isDrawing = false;
                    isDirty = true;
                    map.doubleClickZoom.enable();
                    renderParcelBoundary();
                    setParcelStatus('Contour prêt. Enregistrez-le pour le conserver.');
                });

                cancelButton.addEventListener('click', function () {
                    if (isDirty && !window.confirm('Annuler les modifications non enregistrées ?')) {
                        return;
                    }
                    chooseParcel(activeParcel ? activeParcel.id : '');
                });

                clearButton.addEventListener('click', function () {
                    if (!activeParcel || !currentBoundary.length) {
                        return;
                    }
                    if (!window.confirm('Effacer le contour de cette parcelle ? Enregistrez ensuite pour confirmer.')) {
                        return;
                    }
                    currentBoundary = [];
                    isDrawing = false;
                    isDirty = true;
                    renderParcelBoundary();
                    setParcelStatus('Contour effacé. Enregistrez pour confirmer la suppression.');
                });

                saveButton.addEventListener('click', async function () {
                    if (!activeParcel || !isDirty || isDrawing || isSaving) {
                        return;
                    }
                    if (!window.baykatFarmMapConfig || !window.baykatFarmMapConfig.ajaxUrl || !window.baykatFarmMapConfig.nonce) {
                        setParcelStatus('La configuration de sauvegarde est indisponible. Rechargez la page avant de réessayer.', true);
                        return;
                    }
                    isSaving = true;
                    updateParcelTools();
                    saveButton.disabled = true;
                    saveButton.textContent = 'Enregistrement…';
                    const request = new URLSearchParams({
                        action: 'baykat_farm_save_parcel_boundary',
                        nonce: window.baykatFarmMapConfig.nonce,
                        parcel_id: String(activeParcel.id),
                        boundary: JSON.stringify(currentBoundary)
                    });

                    try {
                        const response = await fetch(window.baykatFarmMapConfig.ajaxUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                            body: request.toString()
                        });
                        const payload = await response.json();
                        if (!response.ok || !payload.success) {
                            throw new Error(payload.data && payload.data.message
                                ? payload.data.message
                                : 'Le contour n’a pas pu être enregistré.');
                        }

                        activeParcel.boundary = payload.data.boundary;
                        currentBoundary = payload.data.boundary.map(function (point) {
                            return [Number(point[0]), Number(point[1])];
                        });
                        isDirty = false;
                        renderParcelBoundary();
                        setParcelStatus(payload.data.message);
                    } catch (error) {
                        setParcelStatus(error.message || 'Une erreur est survenue pendant l’enregistrement du contour.', true);
                    } finally {
                        isSaving = false;
                        saveButton.textContent = 'Enregistrer';
                        updateParcelTools();
                    }
                });

                backButton.addEventListener('click', returnToFarms);
                L.DomEvent.disableClickPropagation(parcelTools);
                L.DomEvent.disableScrollPropagation(parcelTools);
                map.on('click', function (event) {
                    if (!isDrawing || !activeFarm || !activeFarm.can_edit) {
                        return;
                    }
                    if (currentBoundary.length >= 200) {
                        setParcelStatus('Le contour est limité à 200 points.', true);
                        return;
                    }
                    currentBoundary.push([event.latlng.lat, event.latlng.lng]);
                    renderParcelBoundary();
                    setParcelStatus(currentBoundary.length + ' point' + (currentBoundary.length > 1 ? 's' : '') + ' placé' + (currentBoundary.length > 1 ? 's' : '') + '. Continuez ou terminez le tracé.');
                });

                container.addEventListener('click', function (event) {
                    const exploreButton = event.target.closest('[data-explore-farm-parcels]');
                    if (exploreButton && container.contains(exploreButton)) {
                        exploreFarm(exploreButton.dataset.exploreFarmParcels);
                    }
                });
            }

            if (resetButton) {
                resetButton.addEventListener('click', function () {
                    if (activeFarm) {
                        returnToFarms();
                        return;
                    }
                    map.closePopup();
                    fitFarmBounds();
                });
            }
        });

        root.querySelectorAll('[data-farm-location-picker]').forEach(function (canvas) {
            if (canvas.dataset.mapReady) {
                return;
            }

            const form = canvas.closest('form');
            const latitudeField = form && form.querySelector('[data-farm-latitude]');
            const longitudeField = form && form.querySelector('[data-farm-longitude]');
            const status = form && form.querySelector('[data-location-status]');
            const locationField = form && form.querySelector('[name="farm_location"]');
            if (!form || !latitudeField || !longitudeField) {
                return;
            }

            canvas.dataset.mapReady = 'true';
            const initialLatitude = Number.parseFloat(latitudeField.value);
            const initialLongitude = Number.parseFloat(longitudeField.value);
            const hasPosition = Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude);
            const map = L.map(canvas, { scrollWheelZoom: false }).setView(
                hasPosition ? [initialLatitude, initialLongitude] : [0, 0],
                hasPosition ? 15 : 2
            );
            map.attributionControl.setPrefix(false);
            const pickerTiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            });
            pickerTiles.on('tileerror', function () {
                if (status) {
                    status.textContent = 'Le fond de carte OpenStreetMap ne s’est pas chargé. Vérifiez votre connexion ; vous pouvez encore choisir l’épingle.';
                }
            });
            pickerTiles.addTo(map);

            let marker = null;

            function updateCoordinates(position) {
                latitudeField.value = position.lat.toFixed(7);
                longitudeField.value = position.lng.toFixed(7);
                if (status) {
                    status.textContent = 'Emplacement sélectionné. Déplacez l’épingle ou cliquez sur la carte pour l’ajuster.';
                }
            }

            function setMarker(latitude, longitude) {
                const position = L.latLng(latitude, longitude);
                if (marker) {
                    marker.setLatLng(position);
                } else {
                    marker = L.marker(position, { draggable: true }).addTo(map);
                    marker.on('dragend', function (event) {
                        updateCoordinates(event.target.getLatLng());
                    });
                }
                updateCoordinates(position);
            }

            if (hasPosition) {
                setMarker(initialLatitude, initialLongitude);
            }

            map.on('click', function (event) {
                setMarker(event.latlng.lat, event.latlng.lng);
            });

            const clearButton = form.querySelector('[data-clear-farm-location]');
            if (clearButton) {
                clearButton.addEventListener('click', function () {
                    if (marker) {
                        map.removeLayer(marker);
                        marker = null;
                    }
                    latitudeField.value = '';
                    longitudeField.value = '';
                    if (status) {
                        status.textContent = 'Aucune épingle sélectionnée. Recherchez un lieu ou cliquez sur la carte.';
                    }
                });
            }

            const searchButton = form.querySelector('[data-geocode-search]');
            const resultsContainer = form.querySelector('[data-geocode-results]');
            let selectedLocation = locationField ? locationField.value : '';

            if (locationField) {
                locationField.addEventListener('input', function () {
                    if (locationField.value === selectedLocation) {
                        return;
                    }
                    selectedLocation = locationField.value;
                    if (marker) {
                        map.removeLayer(marker);
                        marker = null;
                        latitudeField.value = '';
                        longitudeField.value = '';
                        if (status) {
                            status.textContent = 'La localisation a changé. Recherchez-la à nouveau ou replacez l’épingle sur la carte.';
                        }
                    }
                });
            }

            async function searchLocation() {
                const query = locationField ? locationField.value.trim() : '';
                if (query.length < 3) {
                    if (status) {
                        status.textContent = 'Saisissez au moins 3 caractères pour rechercher une adresse.';
                    }
                    return;
                }
                if (!window.baykatFarmMapConfig || !window.baykatFarmMapConfig.ajaxUrl) {
                    console.error('Baykat Farm map configuration is missing.');
                    if (status) {
                        status.textContent = 'La recherche de lieu n’est pas configurée. Vous pouvez toujours placer l’épingle sur la carte.';
                    }
                    return;
                }

                if (searchButton) {
                    searchButton.disabled = true;
                    searchButton.textContent = 'Recherche…';
                }
                if (resultsContainer) {
                    resultsContainer.replaceChildren();
                    resultsContainer.hidden = true;
                }
                if (status) {
                    status.textContent = 'Recherche du lieu sur OpenStreetMap…';
                }

                const request = new URLSearchParams({
                    action: 'baykat_farm_geocode',
                    nonce: window.baykatFarmMapConfig.nonce,
                    query: query
                });

                try {
                    const response = await fetch(window.baykatFarmMapConfig.ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: request.toString()
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.data && payload.data.message
                            ? payload.data.message
                            : 'La recherche du lieu a échoué.');
                    }

                    if (!payload.data.length) {
                        if (status) {
                            status.textContent = 'Aucun lieu trouvé. Essayez une adresse plus précise ou placez l’épingle sur la carte.';
                        }
                        return;
                    }

                    resultsContainer.hidden = false;
                    payload.data.forEach(function (result) {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'baykat-farm-geocode-result';
                        option.textContent = result.label;
                        option.addEventListener('click', function () {
                            const label = result.label.slice(0, 190);
                            if (locationField) {
                                locationField.value = label;
                                selectedLocation = label;
                            }
                            setMarker(Number(result.latitude), Number(result.longitude));
                            map.setView([Number(result.latitude), Number(result.longitude)], 16);
                            resultsContainer.hidden = true;
                        });
                        resultsContainer.appendChild(option);
                    });

                    if (status) {
                        status.textContent = 'Choisissez le bon résultat pour placer l’épingle.';
                    }
                } catch (error) {
                    if (status) {
                        status.textContent = error.message;
                    }
                } finally {
                    if (searchButton) {
                        searchButton.disabled = false;
                        searchButton.textContent = 'Rechercher';
                    }
                }
            }

            if (searchButton) {
                searchButton.addEventListener('click', searchLocation);
            }
            if (locationField) {
                locationField.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        searchLocation();
                    }
                });
            }

            let parent = canvas.parentElement;
            while (parent && parent !== form) {
                if (parent.matches('details')) {
                    const disclosure = parent;
                    disclosure.addEventListener('toggle', function () {
                        if (disclosure.open) {
                            window.requestAnimationFrame(function () {
                                map.invalidateSize();
                            });
                        }
                    });
                }
                parent = parent.parentElement;
            }
        });
    }

    document.querySelectorAll('.baykat-farm-app').forEach(function (app) {
        function normalizeSearchText(value) {
            return value
                .toLocaleLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        }

        function initializeCampaignQuotePreview() {
            if (app.dataset.campaignQuoteReady) {
                return;
            }

            app.dataset.campaignQuoteReady = 'true';
            const numberFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 });
            const currencyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

            function formatCurrency(value) {
                return currencyFormatter.format(value) + ' FCFA';
            }

            function listLines(value) {
                return value.split(/\r\n|\r|\n/)
                    .map(function (line) { return line.trim(); })
                    .filter(Boolean);
            }

            function normalizeItem(value) {
                return normalizeSearchText(value).replace(/\s+/g, ' ').trim();
            }

            function updatePreview() {
                const form = app.querySelector('#baykat_campaign_type')?.form;
                const preview = form && form.querySelector('[data-simulation-quote-preview]');
                const typeSelect = form && form.querySelector('#baykat_campaign_type');
                if (!form || !preview || !typeSelect) {
                    return;
                }

                if (typeSelect.value !== 'simulation') {
                    preview.hidden = true;
                    return;
                }

                const area = Number(form.querySelector('[name="campaign_area_ha"]')?.value);
                const yieldPerHectare = Number(form.querySelector('[name="expected_yield_kg_ha"]')?.value);
                const cost = Number(form.querySelector('[name="expected_cost"]')?.value);
                const price = Number(form.querySelector('[name="expected_price_per_kg"]')?.value);
                const availableFunds = Number(form.querySelector('[name="expected_available_funds"]')?.value);
                const validInputs = Number.isFinite(area) && area > 0
                    && Number.isFinite(yieldPerHectare) && yieldPerHectare > 0
                    && Number.isFinite(cost) && cost >= 0
                    && Number.isFinite(price) && price > 0
                    && Number.isFinite(availableFunds) && availableFunds >= 0;
                if (!validInputs) {
                    preview.hidden = true;
                    return;
                }

                const harvest = area * yieldPerHectare;
                const revenue = harvest * price;
                const result = revenue - cost;
                const fundingGap = Math.max(0, cost - availableFunds);
                const breakEvenPrice = cost / harvest;
                const breakEvenYield = cost / (area * price);
                preview.hidden = false;

                const values = {
                    harvest: numberFormatter.format(harvest) + ' kg',
                    revenue: formatCurrency(revenue),
                    result: formatCurrency(result),
                    fundingGap: formatCurrency(fundingGap),
                    breakEvenPrice: numberFormatter.format(breakEvenPrice) + ' FCFA/kg',
                    breakEvenYield: numberFormatter.format(breakEvenYield) + ' kg/ha'
                };
                Object.keys(values).forEach(function (key) {
                    const target = preview.querySelector('[data-quote-' + key.replace(/[A-Z]/g, function (letter) {
                        return '-' + letter.toLowerCase();
                    }) + ']');
                    if (target) {
                        target.textContent = values[key];
                    }
                });

                const analysis = preview.querySelector('[data-quote-analysis]');
                if (analysis) {
                    const financialMessage = fundingGap > 0
                        ? 'Il manque ' + formatCurrency(fundingGap) + ' pour couvrir le budget déclaré.'
                        : 'Les fonds déclarés couvrent le budget estimé.';
                    const profitabilityMessage = result >= 0
                        ? 'Selon les hypothèses saisies, les recettes dépasseraient les coûts déclarés de ' + formatCurrency(result) + '.'
                        : 'Selon les hypothèses saisies, les coûts dépasseraient les recettes de ' + formatCurrency(Math.abs(result)) + '. Pour atteindre le seuil d’équilibre sans changer les autres paramètres, le prix ou le rendement devrait atteindre l’un des seuils indiqués ci-dessous.';
                    analysis.textContent = profitabilityMessage + ' ' + financialMessage + ' Les résultats ne comprennent que les coûts que vous avez saisis.';
                }

                const neededItems = listLines(form.querySelector('[name="expected_tools_needed"]')?.value || '');
                const availableItems = new Set(
                    listLines(form.querySelector('[name="expected_tools_available"]')?.value || '')
                        .map(normalizeItem)
                );
                const missingItems = neededItems.filter(function (item) {
                    return !availableItems.has(normalizeItem(item));
                });
                const toolsSummary = preview.querySelector('[data-quote-tools-summary]');
                const missingList = preview.querySelector('[data-quote-tools-missing]');
                if (toolsSummary) {
                    toolsSummary.textContent = neededItems.length
                        ? missingItems.length
                            ? missingItems.length + ' élément(s) déclaré(s) comme nécessaire(s) ne figurent pas dans votre liste des ressources disponibles.'
                            : 'Tous les éléments déclarés comme nécessaires figurent dans votre liste des ressources disponibles.'
                        : 'Aucun besoin matériel n’a été renseigné. La simulation ne peut pas déduire les outils nécessaires de la culture seule.';
                }
                if (missingList) {
                    missingList.replaceChildren();
                    missingItems.forEach(function (item) {
                        const listItem = document.createElement('li');
                        listItem.textContent = item;
                        missingList.appendChild(listItem);
                    });
                }
            }

            app.addEventListener('input', function (event) {
                if (event.target.closest('#baykat_campaign_type')) {
                    return;
                }
                if (event.target.closest('.baykat-farm-add-form form')) {
                    updatePreview();
                }
            });
            app.addEventListener('change', function (event) {
                if (event.target.matches('#baykat_campaign_type')) {
                    updatePreview();
                }
            });
            document.addEventListener('baykat:farm-module-loaded', updatePreview);
            updatePreview();
        }

        function initializeCampaignComparison() {
            if (app.dataset.campaignComparisonReady) {
                return;
            }

            app.dataset.campaignComparisonReady = 'true';
            let comparisonTrigger = null;
            const numberFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 });
            const currencyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

            function formatArea(value) {
                return value ? numberFormatter.format(Number(value)) + ' ha' : 'Non disponible';
            }

            function formatQuantity(value) {
                return value !== '' && Number.isFinite(Number(value))
                    ? numberFormatter.format(Number(value)) + ' kg'
                    : 'Non disponible';
            }

            function formatCurrency(value) {
                return value !== '' && Number.isFinite(Number(value))
                    ? currencyFormatter.format(Number(value)) + ' FCFA'
                    : 'Non disponible';
            }

            function updateComparison(dialog) {
                const trigger = comparisonTrigger;
                const simulationSelect = dialog.querySelector('[data-comparison-simulation-select]');
                const simulation = simulationSelect && simulationSelect.selectedOptions[0];
                if (!trigger || !simulation) {
                    return;
                }

                const campaignTitle = dialog.querySelector('[data-comparison-campaign-title]');
                if (campaignTitle) {
                    campaignTitle.textContent = trigger.dataset.campaignName || '';
                }

                const actualValues = {
                    area: formatArea(trigger.dataset.campaignArea),
                    harvest: trigger.dataset.campaignHarvest || 'Non disponible',
                    yield: trigger.dataset.campaignYield !== ''
                        ? formatQuantity(trigger.dataset.campaignYield) + '/ha'
                        : 'Non disponible',
                    expenses: formatCurrency(trigger.dataset.campaignExpenses),
                    income: formatCurrency(trigger.dataset.campaignIncome),
                    result: formatCurrency(trigger.dataset.campaignResult),
                    funding: 'Non renseigné'
                };
                const availableFunds = simulation.dataset.availableFunds;
                const fundingGap = availableFunds !== ''
                    ? Math.max(0, Number(simulation.dataset.expenses) - Number(availableFunds))
                    : null;
                const forecastValues = {
                    area: formatArea(simulation.dataset.area),
                    harvest: formatQuantity(simulation.dataset.harvest),
                    yield: formatQuantity(simulation.dataset.yield) + '/ha',
                    expenses: formatCurrency(simulation.dataset.expenses),
                    income: formatCurrency(simulation.dataset.income),
                    result: formatCurrency(simulation.dataset.result),
                    funding: null === fundingGap ? 'Non renseigné' : formatCurrency(fundingGap)
                };

                Object.keys(actualValues).forEach(function (key) {
                    const actualCell = dialog.querySelector('[data-comparison-real-' + key + ']');
                    const forecastCell = dialog.querySelector('[data-comparison-forecast-' + key + ']');
                    if (actualCell) {
                        actualCell.textContent = actualValues[key];
                    }
                    if (forecastCell) {
                        forecastCell.textContent = forecastValues[key];
                    }
                });

                const sourceSimulation = dialog.querySelector('[data-comparison-source-simulation]');
                if (sourceSimulation) {
                    sourceSimulation.value = simulation.value;
                }

                const toolsNeeded = (simulation.dataset.toolsNeeded || '').split(/\r\n|\r|\n/).map(function (item) {
                    return item.trim();
                }).filter(Boolean);
                const toolsAvailable = (simulation.dataset.toolsAvailable || '').split(/\r\n|\r|\n/).map(function (item) {
                    return item.trim();
                }).filter(Boolean);
                const normalizedAvailable = new Set(toolsAvailable.map(function (item) {
                    return normalizeSearchText(item).replace(/\s+/g, ' ').trim();
                }));
                const toolsMissing = toolsNeeded.filter(function (item) {
                    return !normalizedAvailable.has(normalizeSearchText(item).replace(/\s+/g, ' ').trim());
                });
                [
                    ['needed', toolsNeeded],
                    ['available', toolsAvailable],
                    ['missing', toolsMissing]
                ].forEach(function (listData) {
                    const list = dialog.querySelector('[data-comparison-tools-' + listData[0] + ']');
                    if (!list) {
                        return;
                    }
                    list.replaceChildren();
                    if (!listData[1].length) {
                        const item = document.createElement('li');
                        item.textContent = 'missing' === listData[0] && toolsNeeded.length
                            ? 'Aucun élément manquant selon les listes saisies.'
                            : 'Non renseigné';
                        list.appendChild(item);
                        return;
                    }
                    listData[1].forEach(function (value) {
                        const item = document.createElement('li');
                        item.textContent = value;
                        list.appendChild(item);
                    });
                });
            }

            app.addEventListener('click', function (event) {
                const openButton = event.target.closest('[data-open-campaign-comparison]');
                if (openButton) {
                    const dialog = app.querySelector('[data-campaign-comparison-dialog]');
                    const simulationSelect = dialog && dialog.querySelector('[data-comparison-simulation-select]');
                    if (!dialog || !simulationSelect) {
                        return;
                    }

                    comparisonTrigger = openButton;
                    const preferredId = openButton.dataset.sourceSimulationId;
                    const cropName = normalizeSearchText(openButton.dataset.campaignCropName || '');
                    const availableOptions = Array.from(simulationSelect.options).filter(function (option) {
                        const linkedSimulation = option.value === preferredId;
                        const sameReference = openButton.dataset.campaignCropReference
                            && option.dataset.cropReference === openButton.dataset.campaignCropReference;
                        const sameName = normalizeSearchText(option.dataset.cropName || '') === cropName;
                        option.hidden = !linkedSimulation && !sameReference && !sameName;
                        return !option.hidden;
                    });
                    let preferredOption = Array.from(simulationSelect.options).find(function (option) {
                        return !option.hidden && option.value === preferredId;
                    });
                    if (!preferredOption) {
                        preferredOption = availableOptions.find(function (option) {
                            return openButton.dataset.campaignCropReference
                                && option.dataset.cropReference === openButton.dataset.campaignCropReference;
                        });
                    }
                    if (!preferredOption) {
                        preferredOption = availableOptions.find(function (option) {
                            return normalizeSearchText(option.dataset.cropName || '') === cropName;
                        });
                    }
                    if (!preferredOption) {
                        return;
                    }
                    simulationSelect.value = preferredOption.value;
                    updateComparison(dialog);
                    if (!dialog.open) {
                        dialog.showModal();
                    }
                    const closeButton = dialog.querySelector('[data-close-campaign-comparison]');
                    if (closeButton) {
                        closeButton.focus();
                    }
                    return;
                }

                const createSimulationButton = event.target.closest('[data-create-comparison-simulation]');
                if (createSimulationButton) {
                    const typeSelect = app.querySelector('#baykat_campaign_type');
                    const formDetails = typeSelect && typeSelect.closest('details');
                    if (formDetails) {
                        formDetails.open = true;
                    }
                    if (typeSelect) {
                        typeSelect.value = 'simulation';
                        typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        typeSelect.focus();
                        typeSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }

                const closeButton = event.target.closest('[data-close-campaign-comparison]');
                const dialog = event.target.closest('[data-campaign-comparison-dialog]');
                if (closeButton || (dialog && event.target === dialog)) {
                    if (dialog && dialog.open) {
                        dialog.close();
                        if (comparisonTrigger && comparisonTrigger.isConnected) {
                            comparisonTrigger.focus();
                        }
                    }
                }
            });

            app.addEventListener('change', function (event) {
                const dialog = event.target.closest('[data-campaign-comparison-dialog]');
                if (!dialog) {
                    return;
                }
                if (event.target.matches('[data-comparison-simulation-select]')) {
                    updateComparison(dialog);
                    return;
                }
                if (event.target.matches('[data-comparison-farm]')) {
                    const parcelSelect = dialog.querySelector('[data-comparison-parcel]');
                    if (!parcelSelect) {
                        return;
                    }
                    Array.from(parcelSelect.options).forEach(function (option) {
                        if (option.value) {
                            option.hidden = option.dataset.farmId !== event.target.value;
                        }
                    });
                    if (parcelSelect.selectedOptions[0] && parcelSelect.selectedOptions[0].hidden) {
                        parcelSelect.value = '';
                    }
                }
            });
        }

        function initializeStockMovementForm(root) {
            const form = root.querySelector('form input[name="baykat_add_stock_movement"]');
            if (!form) {
                return;
            }
            const formElement = form.closest('form');
            if (formElement.dataset.stockMovementReady) {
                return;
            }
            formElement.dataset.stockMovementReady = 'true';
            const sourceFarm = formElement.querySelector('[data-stock-farm-select="source"]');
            const destinationFarm = formElement.querySelector('[data-stock-farm-select="destination"]');
            const sourceParcel = formElement.querySelector('[name="parcel_id"]');
            const destinationParcel = formElement.querySelector('[name="destination_parcel_id"]');
            const movementType = formElement.querySelector('[name="movement_type"]');
            const entryCost = formElement.querySelector('[data-stock-entry-cost]');
            const amount = entryCost ? entryCost.querySelector('input') : null;
            const itemName = formElement.querySelector('[name="item_name"]');
            const unit = formElement.querySelector('[name="unit"]');
            const quantity = formElement.querySelector('[name="quantity"]');
            const activity = formElement.querySelector('[name="activity_id"]');
            const prefill = formElement.querySelector('[data-stock-prefill]');
            const prefillLabel = formElement.querySelector('[data-stock-prefill-label]');
            const prefillHelp = formElement.querySelector('[data-stock-prefill-help]');
            const guidance = formElement.querySelector('[data-stock-movement-guidance]');
            const balanceMessage = formElement.querySelector('[data-stock-balance-message]');
            const destinationHeading = formElement.querySelector('[data-stock-destination-heading]');
            const entryHeading = formElement.querySelector('[data-stock-entry-heading]');
            const sourceFarmLabel = formElement.querySelector('[data-stock-source-farm-label]');
            const sourceParcelLabel = formElement.querySelector('[data-stock-source-parcel-label]');
            const activityHelp = formElement.querySelector('[data-stock-activity-help]');
            const submitButton = formElement.querySelector('button[type="submit"]');
            const reducingTypes = ['exit', 'transfer', 'adjustment_negative'];
            const descriptions = {
                entry: 'Entrée : ajoute une quantité au stock et enregistre aussi son coût comme dépense d’intrant.',
                exit: 'Sortie : retire du stock la quantité utilisée. Associez une activité pour expliquer l’utilisation.',
                transfer: 'Transfert : retire la quantité du lieu de départ et l’ajoute au lieu d’arrivée.',
                adjustment_positive: 'Ajustement positif : ajoute au stock une quantité trouvée lors d’un inventaire. Ce n’est pas un achat.',
                adjustment_negative: 'Ajustement négatif : retire du stock une quantité manquante ou détériorée.',
                return: 'Retour : remet en stock une quantité non utilisée. Choisissez l’activité de sortie d’origine; le serveur vérifiera la quantité retournable.'
            };
            const prefillOptions = prefill
                ? Array.from(prefill.options).filter(function (option) { return option.value !== ''; })
                : [];
            const stockOptions = prefillOptions.filter(function (option) { return !option.dataset.returnable; });
            const returnOptions = prefillOptions.filter(function (option) { return option.dataset.returnable; });
            let selectedReturnOption = null;

            function filterLocationOptions(farmSelect, parcelSelect) {
                if (!farmSelect || !parcelSelect) {
                    return;
                }
                Array.from(parcelSelect.options).forEach(function (option) {
                    if (!option.value) {
                        return;
                    }
                    option.hidden = option.dataset.farmId !== farmSelect.value;
                });
                if (parcelSelect.selectedOptions.length && parcelSelect.selectedOptions[0].hidden) {
                    parcelSelect.value = '';
                }
            }

            function filterRelatedOptions() {
                const farmId = sourceFarm.value;
                const parcelId = sourceParcel.value;

                formElement.querySelectorAll('[data-stock-filter-select]').forEach(function (select) {
                    Array.from(select.options).forEach(function (option) {
                        if (!option.value || !option.dataset.farmId) {
                            return;
                        }
                        option.hidden = !farmId
                            || option.dataset.farmId !== farmId
                            || (parcelId && option.dataset.parcelId !== parcelId);
                    });
                    if (select.selectedOptions.length && select.selectedOptions[0].hidden) {
                        select.value = '';
                    }
                });

                prefillOptions.forEach(function (option) {
                    option.hidden = option.dataset.farmId !== farmId
                        || option.dataset.parcelId !== parcelId;
                });

                if (prefill && prefill.selectedOptions.length) {
                    const selectedOption = prefill.selectedOptions[0];
                    const isReturnOption = Boolean(selectedOption.dataset.returnable);
                    if (selectedOption.value && isReturnOption !== (movementType.value === 'return')) {
                        prefill.value = '';
                        selectedReturnOption = null;
                    }
                }
                if (prefill && prefill.selectedOptions.length && prefill.selectedOptions[0].hidden) {
                    prefill.value = '';
                }
                const prefillField = prefill && prefill.closest('.baykat-farm-field');
                if (prefillField) {
                    const relevantOptions = movementType.value === 'return' ? returnOptions : stockOptions;
                    prefillField.hidden = !relevantOptions.some(function (option) { return !option.hidden; });
                }
            }

            function currentBalance() {
                const farmId = sourceFarm.value;
                const parcelId = sourceParcel.value || '0';
                const name = itemName.value.trim().toLocaleLowerCase();
                const selectedUnit = unit.value.trim().toLocaleLowerCase();
                if (!farmId || !name || !selectedUnit) {
                    return null;
                }
                const option = stockOptions.find(function (stock) {
                    return stock.dataset.farmId === farmId
                        && (stock.dataset.parcelId || '0') === parcelId
                        && stock.dataset.itemName.trim().toLocaleLowerCase() === name
                        && stock.dataset.unit.trim().toLocaleLowerCase() === selectedUnit;
                });
                return option ? Number(option.dataset.quantity) : 0;
            }

            function validateMovement() {
                const isTransfer = movementType.value === 'transfer';
                const isEntry = movementType.value === 'entry';
                const isReturn = movementType.value === 'return';
                const isReduction = reducingTypes.includes(movementType.value);
                const destinationFarmField = destinationFarm.closest('.baykat-farm-field');
                const destinationParcelField = destinationParcel.closest('.baykat-farm-field');

                destinationFarmField.hidden = !isTransfer;
                destinationParcelField.hidden = !isTransfer;
                destinationFarm.disabled = !isTransfer;
                destinationParcel.disabled = !isTransfer;
                destinationFarm.required = isTransfer;
                entryCost.hidden = !isEntry;
                entryHeading.hidden = !isEntry;
                amount.required = isEntry;
                amount.disabled = !isEntry;
                activity.required = isReturn;
                activityHelp.textContent = isReturn
                    ? 'Obligatoire pour vérifier que la quantité retournée a bien été sortie pour cette activité.'
                    : 'Associez une activité pour garder le contexte de l’utilisation.';
                prefillLabel.textContent = isReturn
                    ? 'Choisir la sortie d’origine (facultatif)'
                    : 'Partir d’un intrant déjà en stock (facultatif)';
                prefillHelp.textContent = isReturn
                    ? 'Choisissez un retour proposé pour remplir automatiquement le lieu, l’intrant, l’unité, l’activité et la quantité maximale.'
                    : 'Choisissez un stock connu pour éviter les erreurs de nom et d’unité.';
                destinationHeading.hidden = !isTransfer;
                sourceFarmLabel.textContent = isReduction ? 'Ferme de départ' : 'Ferme concernée';
                sourceParcelLabel.textContent = isReduction ? 'Parcelle de départ (facultatif)' : 'Parcelle concernée (facultatif)';
                guidance.textContent = descriptions[movementType.value] || '';
                let stepNumber = 0;
                formElement.querySelectorAll('[data-stock-step-label]').forEach(function (heading) {
                    if (!heading.hidden) {
                        stepNumber += 1;
                        heading.textContent = stepNumber + '. ' + heading.dataset.stockStepLabel;
                    }
                });
                balanceMessage.textContent = '';
                balanceMessage.classList.remove('is-error', 'is-success');
                quantity.setCustomValidity('');
                quantity.removeAttribute('max');
                destinationFarm.setCustomValidity('');

                if (!isTransfer) {
                    destinationFarm.value = '';
                    destinationParcel.value = '';
                }
                if (!isEntry) {
                    amount.value = '';
                }

                if (isTransfer && destinationFarm.value && sourceFarm.value === destinationFarm.value
                    && (sourceParcel.value || '0') === (destinationParcel.value || '0')) {
                    destinationFarm.setCustomValidity('La destination doit être différente de l’emplacement de départ.');
                    balanceMessage.textContent = 'Choisissez un autre emplacement : le départ et la destination sont identiques.';
                    balanceMessage.classList.add('is-error');
                }

                if (isReduction) {
                    const balance = currentBalance();
                    if (null !== balance) {
                        const formattedBalance = balance.toLocaleString('fr-FR', { maximumFractionDigits: 3 });
                        balanceMessage.textContent = 'Stock disponible à cet emplacement : ' + formattedBalance + ' ' + unit.value.trim() + '.';
                        if (balance > 0) {
                            balanceMessage.classList.add('is-success');
                            quantity.max = balance.toFixed(3);
                        } else if (itemName.value.trim() && unit.value.trim() && sourceFarm.value) {
                            balanceMessage.textContent = 'Cet intrant n’est pas enregistré à cet emplacement avec cette unité. Vérifiez le nom, l’unité ou l’emplacement.';
                            balanceMessage.classList.add('is-error');
                        }

                        const requestedQuantity = Number(quantity.value);
                        if (quantity.value && balance <= 0) {
                            quantity.setCustomValidity('Aucun stock correspondant n’est disponible à cet emplacement.');
                            balanceMessage.textContent = 'Aucun stock correspondant n’est disponible à cet emplacement. Vérifiez le nom de l’intrant, son unité et la parcelle.';
                            balanceMessage.classList.remove('is-success');
                            balanceMessage.classList.add('is-error');
                        } else if (quantity.value && requestedQuantity > balance + 0.0005) {
                            quantity.setCustomValidity('La quantité demandée dépasse le stock disponible (' + formattedBalance + ' ' + unit.value.trim() + ').');
                            balanceMessage.textContent = 'Quantité demandée supérieure au stock disponible (' + formattedBalance + ' ' + unit.value.trim() + '). Réduisez la quantité ou vérifiez l’emplacement.';
                            balanceMessage.classList.remove('is-success');
                            balanceMessage.classList.add('is-error');
                        }
                    }
                } else {
                    if (isReturn) {
                        const validReturnOption = selectedReturnOption
                            && !selectedReturnOption.hidden
                            && selectedReturnOption.dataset.farmId === sourceFarm.value
                            && selectedReturnOption.dataset.parcelId === (sourceParcel.value || '0')
                            && selectedReturnOption.dataset.activityId === activity.value
                            && selectedReturnOption.dataset.itemName.trim().toLocaleLowerCase() === itemName.value.trim().toLocaleLowerCase()
                            && selectedReturnOption.dataset.unit.trim().toLocaleLowerCase() === unit.value.trim().toLocaleLowerCase();
                        if (validReturnOption) {
                            const returnableQuantity = Number(selectedReturnOption.dataset.quantity);
                            const formattedReturnable = returnableQuantity.toLocaleString('fr-FR', { maximumFractionDigits: 3 });
                            quantity.max = returnableQuantity.toFixed(3);
                            balanceMessage.textContent = 'Quantité maximale retournable pour cette activité : ' + formattedReturnable + ' ' + unit.value.trim() + '.';
                            balanceMessage.classList.add('is-success');
                            if (quantity.value && Number(quantity.value) > returnableQuantity + 0.0005) {
                                quantity.setCustomValidity('La quantité dépasse ce qui reste à retourner pour cette activité (' + formattedReturnable + ' ' + unit.value.trim() + ').');
                                balanceMessage.textContent = 'La quantité dépasse ce qui reste à retourner pour cette activité (' + formattedReturnable + ' ' + unit.value.trim() + ').';
                                balanceMessage.classList.remove('is-success');
                                balanceMessage.classList.add('is-error');
                            }
                        } else if (!activity.value) {
                            balanceMessage.textContent = 'Choisissez l’activité d’origine du retour. Utilisez la liste de stocks pour préremplir l’intrant, l’unité et l’activité.';
                            balanceMessage.classList.add('is-error');
                        } else if (itemName.value.trim() && unit.value.trim()) {
                            balanceMessage.textContent = 'Vérifiez que l’intrant, l’unité et l’activité correspondent à la sortie d’origine. Choisir un élément « Retour » dans la liste affiche la quantité maximale.';
                            balanceMessage.classList.add('is-error');
                        }
                    }
                }

                if (submitButton) {
                    submitButton.disabled = Boolean(destinationFarm.validationMessage);
                }
            }

            sourceFarm.addEventListener('change', function () {
                filterLocationOptions(sourceFarm, sourceParcel);
                filterRelatedOptions();
                validateMovement();
            });
            destinationFarm.addEventListener('change', function () {
                filterLocationOptions(destinationFarm, destinationParcel);
                validateMovement();
            });
            sourceParcel.addEventListener('change', function () {
                filterRelatedOptions();
                validateMovement();
            });
            destinationParcel.addEventListener('change', validateMovement);
            movementType.addEventListener('change', function () {
                selectedReturnOption = null;
                filterRelatedOptions();
                validateMovement();
            });
            [itemName, unit, activity].forEach(function (field) {
                field.addEventListener('input', function () {
                    selectedReturnOption = null;
                    validateMovement();
                });
                field.addEventListener('change', function () {
                    selectedReturnOption = null;
                    validateMovement();
                });
            });
            quantity.addEventListener('input', validateMovement);
            quantity.addEventListener('change', validateMovement);
            if (prefill) {
                prefill.addEventListener('change', function () {
                    const option = prefill.selectedOptions[0];
                    if (option && option.value) {
                        itemName.value = option.dataset.itemName || '';
                        unit.value = option.dataset.unit || '';
                        selectedReturnOption = option.dataset.returnable ? option : null;
                        if (option.dataset.returnable) {
                            activity.value = option.dataset.activityId || '';
                            quantity.value = option.dataset.quantity || '';
                        } else {
                            selectedReturnOption = null;
                        }
                    } else {
                        selectedReturnOption = null;
                    }
                    validateMovement();
                });
            }
            filterLocationOptions(sourceFarm, sourceParcel);
            filterLocationOptions(destinationFarm, destinationParcel);
            filterRelatedOptions();
            validateMovement();
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
            initializeStockMovementForm(root);
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
                        if (filterTool && !filterTool.hasAttribute('data-record-filter-persistent')) {
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

                const amount = rows.filter(function (row) {
                    return row.dataset.cancelled !== 'true';
                }).reduce(function (sum, row) {
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

                if (section.id === 'baykat-workspace-campaigns') {
                    const emptyMessage = section.querySelector('[data-parcel-campaign-empty]');
                    if (emptyMessage) {
                        emptyMessage.hidden = visibleRecords.length > 0;
                    }
                }
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

                const amount = rows.filter(function (row) {
                    return row.dataset.cancelled !== 'true';
                }).reduce(function (sum, row) {
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
                if (attribute === 'data-farm-id') {
                    const farmRecordId = 'baykat-farm-record-' + String(value);
                    const liveFarmRecord = Array.from(app.querySelectorAll('[id]')).find(function (record) {
                        return record.id === farmRecordId;
                    });

                    if (liveFarmRecord) {
                        return liveFarmRecord;
                    }

                    const workspace = app.querySelector('[data-dashboard-workspace-template]');
                    if (!workspace) {
                        return null;
                    }

                    return Array.from(workspace.content.querySelectorAll('[id]')).find(function (record) {
                        return record.id === farmRecordId;
                    }) || null;
                }

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
                        'baykat-workspace-campaigns',
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
                        workspaceHeading.textContent = 'Campagnes, activités, finances et stocks';
                    }
                    if (workspaceDescription) {
                        workspaceDescription.textContent = 'Retrouvez les campagnes rattachées à ' + parcelName + ', puis consultez ses activités, finances et stocks.';
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

            if (!form.matches('.baykat-farm-form, .baykat-farm-image-update-form, .baykat-farm-record-form, .baykat-farm-delete-form, .baykat-finance-cancel-form, .baykat-finance-form-content, .baykat-farm-activity-finance-form')) {
                return;
            }

            if (form.matches('.baykat-farm-delete-form, .baykat-finance-cancel-form') && !window.confirm(form.dataset.confirmMessage || 'Confirmer la suppression ? Cette action ne peut pas être annulée.')) {
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

        function filterActivityCampaignOptions(root) {
            root.querySelectorAll('form').forEach(function (form) {
                const parcelSelect = form.querySelector('select[name="parcel_id"]');
                const campaignSelect = form.querySelector('[data-activity-campaign-select]');
                if (!parcelSelect || !campaignSelect) {
                    return;
                }

                Array.from(campaignSelect.options).forEach(function (option) {
                    option.hidden = Boolean(option.value) && (
                        !parcelSelect.value || option.dataset.parcelId !== parcelSelect.value
                    );
                });
                if (campaignSelect.selectedOptions.length && campaignSelect.selectedOptions[0].hidden) {
                    campaignSelect.value = '';
                }
            });
        }

        filterActivityCampaignOptions(app);
        document.addEventListener('baykat:farm-module-loaded', function () {
            filterActivityCampaignOptions(app);
        });

        app.addEventListener('change', function (event) {
            const activityParcelSelect = event.target.closest('form select[name="parcel_id"]');
            if (activityParcelSelect) {
                filterActivityCampaignOptions(activityParcelSelect.form);
            }

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
        initializeCampaignQuotePreview();
        initializeCampaignComparison();
        initializeStockMovementForm(app);
        initializeRecordFilters(app);
        initializeDashboardModals();
        initializeMobileNavigation();
        initializeFarmMaps(app);
        document.addEventListener('baykat:farm-module-loaded', function () {
            initializeFarmMaps(app);
        });
    });
});
