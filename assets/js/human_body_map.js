/**
 * Human Body Map Component
 * Handles front/back view SVG interaction for pain/issue mapping with high detail.
 */

const HumanBodyMap = {
    selectedRegions: new Set(),
    
    // Front View Path Data (Anatomically Detailed)
    frontPaths: {
        'Cabeza': 'M100,5 C112,5 118,15 118,28 C118,40 110,48 100,48 C90,48 82,40 82,28 C82,15 88,5 100,5 Z',
        'Cuello': 'M94,48 C94,58 106,58 106,48 L112,65 C112,65 88,65 88,65 Z',
        'Hombro Izquierdo': 'M88,65 C75,65 58,72 52,85 C50,92 58,98 68,98 L85,92 Z',
        'Hombro Derecho': 'M112,65 C125,65 142,72 148,85 C150,92 142,98 132,98 L115,92 Z',
        'Pecho / Tórax': 'M85,92 L115,92 C130,115 125,140 115,155 L85,155 C75,140 70,115 85,92 Z',
        'Abdomen Superior': 'M85,155 L115,155 C118,175 115,190 100,200 C85,190 82,175 85,155 Z',
        'Abdomen Inferior / Pelvis': 'M85,195 L115,195 C118,215 112,230 100,240 C88,230 82,215 85,195 Z',
        
        'Brazo Superior Izq.': 'M52,85 C48,105 45,130 45,145 L62,150 L68,98 Z',
        'Brazo Superior Der.': 'M148,85 C152,105 155,130 155,145 L138,150 L132,98 Z',
        'Codo Izquierdo': 'M45,145 C43,155 43,165 45,175 L62,175 L62,150 Z',
        'Codo Derecho': 'M155,145 C157,155 157,165 155,175 L138,175 L138,150 Z',
        'Antebrazo Izquierdo': 'M45,175 C48,205 52,230 58,245 L78,235 L62,175 Z',
        'Antebrazo Derecho': 'M155,175 C152,205 148,230 142,245 L122,235 L138,175 Z',
        'Muñeca / Mano Izq.': 'M58,245 C62,265 52,275 45,275 C38,275 42,255 45,245 Z',
        'Muñeca / Mano Der.': 'M142,245 C138,265 148,275 155,275 C162,275 158,255 155,245 Z',

        'Muslo Izquierdo': 'M85,240 C75,270 70,310 75,340 L95,340 L100,240 Z',
        'Muslo Derecho': 'M115,240 C125,270 130,310 125,340 L105,340 L100,240 Z',
        'Rodilla Izquierda': 'M75,340 C75,355 95,355 95,340 L95,360 L75,360 Z',
        'Rodilla Derecha': 'M125,340 C125,355 105,355 105,340 L105,360 L125,360 Z',
        'Pierna / Pantorrilla Izq.': 'M75,360 C78,390 82,420 75,440 L95,440 L95,360 Z',
        'Pierna / Pantorrilla Der.': 'M125,360 C122,390 118,420 125,440 L105,440 L105,360 Z',
        'Pie / Tobillo Izquierdo': 'M75,440 C70,460 62,470 55,470 C48,470 65,440 75,440 Z',
        'Pie / Tobillo Derecho': 'M125,440 C130,460 138,470 145,470 C152,470 135,440 125,440 Z'
    },
    
    // Back View Path Data (Anatomically Detailed)
    backPaths: {
        'Cabeza (Nuca)': 'M100,5 C112,5 118,15 118,28 C118,40 110,48 100,48 C90,48 82,40 82,28 C82,15 88,5 100,5 Z', 
        'Cuello Posterior': 'M94,48 C94,58 106,58 106,48 L112,65 C112,65 88,65 88,65 Z',
        'Hombro Izq. Post.': 'M88,65 C75,65 58,72 52,85 C50,92 58,98 68,98 L85,92 Z',
        'Hombro Der. Post.': 'M112,65 C125,65 142,72 148,85 C150,92 142,98 132,98 L115,92 Z',
        'Espalda Superior (Escápulas)': 'M85,92 L115,92 C130,115 125,140 115,155 L85,155 C75,140 70,115 85,92 Z',
        'Espalda Media (Lumbar Alta)': 'M85,155 L115,155 C118,175 115,190 100,200 C85,190 82,175 85,155 Z',
        'Espalda Baja (Lumbar)': 'M90,200 L110,200 L115,225 L85,225 Z',
        'Glúteo Izquierdo': 'M85,225 L100,225 L100,260 L78,255 Z',
        'Glúteo Derecho': 'M115,225 L100,225 L100,260 L122,255 Z',
        
        'Brazo Sup. Izq. Post.': 'M52,85 C48,105 45,130 45,145 L62,150 L68,98 Z',
        'Brazo Sup. Der. Post.': 'M148,85 C152,105 155,130 155,145 L138,150 L132,98 Z',
        'Codo Izq. Posterior': 'M45,145 C43,155 43,165 45,175 L62,175 L62,150 Z',
        'Codo Der. Posterior': 'M155,145 C157,155 157,165 155,175 L138,175 L138,150 Z',
        'Antebrazo Izq. Post.': 'M45,175 C48,205 52,230 58,245 L78,235 L62,175 Z',
        'Antebrazo Der. Post.': 'M155,175 C152,205 148,230 142,245 L122,235 L138,175 Z',
        'Muñeca / Mano Izq. Post.': 'M58,245 C62,265 52,275 45,275 C38,275 42,255 45,245 Z',
        'Muñeca / Mano Der. Post.': 'M142,245 C138,265 148,275 155,275 C162,275 158,255 155,245 Z',

        'Muslo Izq. Posterior': 'M78,255 C72,275 68,310 72,340 L90,340 L100,260 Z',
        'Muslo Der. Posterior': 'M122,255 C128,275 132,310 128,340 L110,340 L100,260 Z',
        'Corva Izquierda': 'M72,340 C72,355 90,355 90,340 L90,360 L72,360 Z',
        'Corva Derecha': 'M128,340 C128,355 110,355 110,340 L110,360 L128,360 Z',
        'Pantorrilla Izq. Post.': 'M72,360 C75,390 78,420 72,440 L90,440 L90,360 Z',
        'Pantorrilla Der. Post.': 'M128,360 C125,390 122,420 128,440 L110,440 L110,360 Z',
        'Talón / Pie Izq.': 'M72,440 C68,460 62,470 55,470 C48,470 65,440 72,440 Z',
        'Talón / Pie Der.': 'M128,440 C132,460 138,470 145,470 C152,470 135,440 128,440 Z'
    },

    render: function(containerId, inputId) {
        const container = document.getElementById(containerId);
        const input = document.getElementById(inputId);
        if (!container) return;

        // Custom Styles for Professional Look
        const styles = `
            <style>
                .human-body-svg {
                    width: 100%;
                    max-width: 380px;
                    height: auto;
                    filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.1));
                    margin: 0 auto;
                    display: block;
                }
                .body-part {
                    fill: #e2e8f0;
                    stroke: #94a3b8;
                    stroke-width: 1.5;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    cursor: pointer;
                }
                .body-part:hover {
                    fill: #93c5fd;
                    stroke: #3b82f6;
                    stroke-width: 2;
                }
                .body-part.active {
                    fill: #ef4444;
                    stroke: #b91c1c;
                    stroke-width: 2;
                }
                .view-controls .btn {
                    font-weight: 500;
                    letter-spacing: 0.5px;
                }
                .selected-areas {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                }
                .area-badge {
                    background-color: #ef4444;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 20px;
                    font-size: 0.8rem;
                    display: inline-block;
                    margin: 2px;
                    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
                    animation: fadeIn 0.3s ease-in;
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(5px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .svg-tooltip {
                    position: absolute;
                    background: rgba(15, 23, 42, 0.9);
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    pointer-events: none;
                    opacity: 0;
                    transition: opacity 0.2s;
                    z-index: 10;
                    white-space: nowrap;
                }
            </style>
        `;

        container.innerHTML = styles + `
            <div class="body-map-wrapper position-relative">
                <div class="view-controls mb-3 text-center">
                    <div class="btn-group btn-group-sm w-100 shadow-sm" role="group">
                        <button type="button" class="btn btn-outline-primary active" id="btn-front">
                            <i class="bi bi-person me-1"></i> Frontal
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btn-back">
                            <i class="bi bi-person-fill me-1"></i> Posterior
                        </button>
                    </div>
                </div>
                <div class="svg-container text-center position-relative">
                    <div id="svg-tooltip" class="svg-tooltip"></div>
                    <svg viewBox="20 0 160 400" class="human-body-svg" id="body-svg">
                        <g id="body-paths"></g>
                    </svg>
                </div>
                <div class="selected-areas mt-3 p-3 rounded-3 shadow-sm">
                    <small class="text-secondary fw-bold d-block mb-2 text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Zonas Seleccionadas:
                    </small>
                    <div id="areas-list" class="d-flex flex-wrap gap-1">
                        <span class="text-muted small fst-italic">Ninguna zona seleccionada</span>
                    </div>
                </div>
            </div>
        `;

        this.updatePaths('front');

        // Events
        document.getElementById('btn-front').addEventListener('click', (e) => {
            this.switchView('front', e.currentTarget);
        });
        document.getElementById('btn-back').addEventListener('click', (e) => {
            this.switchView('back', e.currentTarget);
        });

        const tooltip = document.getElementById('svg-tooltip');

        container.addEventListener('mousemove', (e) => {
            if (e.target.tagName === 'path') {
                tooltip.style.opacity = '1';
                tooltip.style.left = (e.offsetX + 15) + 'px';
                tooltip.style.top = (e.offsetY - 15) + 'px';
                tooltip.textContent = e.target.getAttribute('data-name');
            } else {
                tooltip.style.opacity = '0';
            }
        });

        container.addEventListener('mouseout', (e) => {
            if (e.target.tagName === 'path') {
                tooltip.style.opacity = '0';
            }
        });

        container.addEventListener('click', (e) => {
            if (e.target.tagName === 'path') {
                this.toggleRegion(e.target.id, e.target.getAttribute('data-name'), input);
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
        
        for (const [name, d] of Object.entries(paths)) {
            const id = name.replace(/\s+/g, '-').toLowerCase();
            const activeClass = this.selectedRegions.has(name) ? 'active' : '';
            html += `<path d="${d}" id="path-${id}" data-name="${name}" class="body-part ${activeClass}"></path>`;
        }
        
        group.innerHTML = html;
    },

    toggleRegion: function(pathId, regionName, input) {
        const pathEl = document.getElementById(pathId);
        if (!pathEl) return;

        if (this.selectedRegions.has(regionName)) {
            this.selectedRegions.delete(regionName);
            pathEl.classList.remove('active');
        } else {
            this.selectedRegions.add(regionName);
            pathEl.classList.add('active');
        }

        this.updateInputAndList(input);
    },

    updateInputAndList: function(input) {
        const listContainer = document.getElementById('areas-list');
        if (this.selectedRegions.size === 0) {
            listContainer.innerHTML = '<span class="text-muted small fst-italic">Ninguna zona seleccionada</span>';
            input.value = '';
            return;
        }

        let listHtml = '';
        const regionsArray = Array.from(this.selectedRegions);
        regionsArray.forEach(region => {
            listHtml += `<span class="area-badge"><i class="bi bi-check2 me-1"></i>${region}</span>`;
        });
        listContainer.innerHTML = listHtml;
        input.value = regionsArray.join(', ');
    }
};
