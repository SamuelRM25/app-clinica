/**
 * Human Body Map Component
 * Handles front/back view SVG interaction for pain/issue mapping.
 */

const HumanBodyMap = {
    selectedRegions: new Set(),
    
    // Front View Path Data (Simplified)
    frontPaths: {
        'head': 'M100,20 c15,0 20,10 20,25 c0,15 -5,25 -20,25 s-20,-10 -20,-25 c0,-15 5,-25 20,-25',
        'torso': 'M80,70 L120,70 L125,140 L75,140 Z',
        'right-arm': 'M120,75 L150,110 L140,120 L120,95 Z',
        'left-arm': 'M80,75 L50,110 L60,120 L80,95 Z',
        'right-leg': 'M102,140 L120,140 L115,220 L102,220 Z',
        'left-leg': 'M80,140 L98,140 L98,220 L85,220 Z'
    },
    
    // Back View Path Data (Simplified)
    backPaths: {
        'back-head': 'M100,20 c15,0 20,10 20,25 c0,15 -5,25 -20,25 s-20,-10 -20,-25 c0,-15 5,-25 20,-25',
        'upper-back': 'M80,70 L120,70 L120,105 L80,105 Z',
        'lower-back': 'M80,105 L120,105 L125,140 L75,140 Z',
        'right-arm-back': 'M120,75 L150,110 L140,120 L120,95 Z',
        'left-arm-back': 'M80,75 L50,110 L60,120 L80,95 Z',
        'right-leg-back': 'M102,140 L120,140 L115,220 L102,220 Z',
        'left-leg-back': 'M80,140 L98,140 L98,220 L85,220 Z'
    },

    render: function(containerId, inputId) {
        const container = document.getElementById(containerId);
        const input = document.getElementById(inputId);
        if (!container) return;

        container.innerHTML = `
            <div class="body-map-wrapper">
                <div class="view-controls mb-3 text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary active" id="btn-front">Vista Frontal</button>
                        <button type="button" class="btn btn-outline-primary" id="btn-back">Vista Posterior</button>
                    </div>
                </div>
                <div class="svg-container text-center">
                    <svg viewBox="0 0 200 250" class="human-body-svg" id="body-svg">
                        <g id="body-paths"></g>
                    </svg>
                </div>
                <div class="selected-areas mt-3 p-2 border rounded bg-light">
                    <small class="text-muted d-block mb-1">Zonas Marcadas:</small>
                    <div id="areas-list" class="d-flex flex-wrap gap-1">
                        <span class="text-muted small">Ninguna zona seleccionada</span>
                    </div>
                </div>
            </div>
        `;

        this.updatePaths('front');

        // Events
        document.getElementById('btn-front').addEventListener('click', (e) => {
            this.switchView('front', e.target);
        });
        document.getElementById('btn-back').addEventListener('click', (e) => {
            this.switchView('back', e.target);
        });

        container.addEventListener('click', (e) => {
            if (e.target.tagName === 'path') {
                this.toggleRegion(e.target.id, input);
            }
        });
    },

    switchView: function(view, btn) {
        document.querySelectorAll('.view-controls .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        this.updatePaths(view);
    },

    updatePaths: function(view) {
        const group = document.getElementById('body-paths');
        const paths = view === 'front' ? this.frontPaths : this.backPaths;
        let html = '';
        
        for (const [id, d] of Object.entries(paths)) {
            const activeClass = this.selectedRegions.has(id) ? 'active' : '';
            html += `<path d="${d}" id="${id}" class="body-part ${activeClass}" title="${id}"></path>`;
        }
        
        group.innerHTML = html;
    },

    toggleRegion: function(regionId, input) {
        if (this.selectedRegions.has(regionId)) {
            this.selectedRegions.delete(regionId);
            document.getElementById(regionId).classList.remove('active');
        } else {
            this.selectedRegions.add(regionId);
            document.getElementById(regionId).classList.add('active');
        }

        this.updateInputAndList(input);
    },

    updateInputAndList: function(input) {
        const listContainer = document.getElementById('areas-list');
        if (this.selectedRegions.size === 0) {
            listContainer.innerHTML = '<span class="text-muted small">Ninguna zona seleccionada</span>';
            input.value = '';
            return;
        }

        let listHtml = '';
        const regionsArray = Array.from(this.selectedRegions);
        regionsArray.forEach(region => {
            listHtml += `<span class="badge bg-danger p-2">${this.formatLabel(region)}</span>`;
        });
        listContainer.innerHTML = listHtml;
        input.value = regionsArray.join(', ');
    },

    formatLabel: function(id) {
        return id.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
};
