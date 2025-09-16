const apiBase = '../api';

const state = {
  zona: '',
  pais: '',
  tipoLosa: 'bidireccional',
  uso: '',
  cargaViva: '',
  ejeX: '',
  ejeY: '',
  largoTotal: '',
  anchoTotal: '',
  losa_pct: 90,
  productos: [],
  candidatos: [],
  candidatosAll: [],
  currentStep: 1
};

function saveState(){
  Object.entries(state).forEach(([k,v]) => {
    if (['productos','candidatos','currentStep'].includes(k)) return; // do not persist currentStep
    sessionStorage.setItem(k, v);
  });
}

function updateHeightFilterOptions() {
  const heightSelect = document.querySelector('#filtroAltura');
  if (!heightSelect) return;
  
  // Preserve current selection
  const prevValue = heightSelect.value || 'todas';
  
  // Get unique heights from all candidates
  const heights = [...new Set(state.candidatosAll.map(item => item.altura_mm))].sort((a, b) => a - b);
  
  // Clear existing options except the first one ("todas")
  while (heightSelect.options.length > 1) {
    heightSelect.remove(1);
  }
  
  // Rebuild height options
  heights.forEach(height => {
    const option = document.createElement('option');
    option.value = String(height);
    option.textContent = `Altura: ${height}mm`;
    heightSelect.appendChild(option);
  });
  
  // Restore previous selection if still valid, otherwise fall back to 'todas'
  if (prevValue !== 'todas' && heights.includes(parseInt(prevValue, 10))) {
    heightSelect.value = prevValue;
  } else {
    heightSelect.value = 'todas';
  }
}

function applyFilterAndRender(){
  console.log('applyFilterAndRender called');
  console.log('candidatosAll length:', state.candidatosAll.length);
  
  // Ensure height options are in sync and selection preserved BEFORE reading filters
  updateHeightFilterOptions();
  
  const estadoFilter = document.querySelector('#filtroEstado')?.value || 'todas';
  const alturaFilter = document.querySelector('#filtroAltura')?.value || 'todas';
  
  console.log('Filters - Estado:', estadoFilter, 'Altura:', alturaFilter);
  
  const cards = document.querySelector('#cards');
  if (!cards) {
    console.error('Cards container not found');
    return;
  }
  
  // Clear existing cards
  cards.innerHTML = '';
  
  // Filter candidates based on selected filters
  const filteredCandidates = state.candidatosAll.filter(candidate => {
    // Apply estado filter
    if (estadoFilter !== 'todas' && candidate.estado !== estadoFilter) {
      return false;
    }
    
    // Apply altura filter
    if (alturaFilter !== 'todas' && candidate.altura_mm !== parseInt(alturaFilter, 10)) {
      return false;
    }
    
    return true;
  });
  
  console.log('Filtered candidates:', filteredCandidates.length);
  
  // Render each candidate
  filteredCandidates.forEach(candidate => {
    renderCard(candidate, cards);
  });
  
  // Update count display
  updateCountDisplay(filteredCandidates.length, state.candidatosAll.length);
}

function updateCountDisplay(filteredCount, totalCount) {
  const countElement = document.querySelector('#count');
  if (countElement) {
    const estadoFilter = document.querySelector('#filtroEstado')?.value || 'todas';
    const alturaFilter = document.querySelector('#filtroAltura')?.value || 'todas';
    
    let filterText = '';
    
    // Add estado filter info if not 'todas'
    if (estadoFilter !== 'todas') {
      const estadoText = {
        'ok': 'OK',
        'ajustada': 'Ajustada',
        'insuficiente': 'Insuficiente'
      }[estadoFilter] || estadoFilter;
      filterText += `Estado: ${estadoText}`;
    }
    
    // Add altura filter info if not 'todas'
    if (alturaFilter !== 'todas') {
      if (filterText) filterText += ' | ';
      filterText += `Altura: ${alturaFilter}mm`;
    }
    
    // Build the full count text
    let countText = `${filteredCount} de ${totalCount} resultados`;
    if (filterText) {
      countText += ` (Filtro: ${filterText})`;
    }
    
    countElement.textContent = countText;
    
    // Show/hide no results message
    const noResults = document.querySelector('#no-results');
    if (noResults) {
      noResults.style.display = filteredCount === 0 ? 'block' : 'none';
    }
  }
}

function showNoResultsPage() {
  const cards = document.querySelector('#cards');
  const noResultsDiv = document.createElement('div');
  noResultsDiv.className = 'no-results-container';
  
  // Analyze why there are no results
  const reasons = [];
  const totalProducts = state.candidatosAll.length;
  
  if (totalProducts === 0) {
    reasons.push('No se encontraron productos disponibles para el país y configuración seleccionados.');
  } else {
    const filter = document.querySelector('#filtroEstado')?.value || 'todas';
    const okProducts = state.candidatosAll.filter(r => r.estado === 'ok').length;
    const ajustadaProducts = state.candidatosAll.filter(r => r.estado === 'ajustada').length;
    const insuficienteProducts = state.candidatosAll.filter(r => r.estado === 'insuficiente').length;
    
    if (filter === 'ok' && okProducts === 0) {
      reasons.push(`Se calcularon ${totalProducts} productos, pero ninguno cumple con los criterios óptimos (estado OK).`);
      if (ajustadaProducts > 0) {
        reasons.push(`Hay ${ajustadaProducts} productos con estado "Ajustada" que podrían funcionar con algunas consideraciones.`);
      }
      if (insuficienteProducts > 0) {
        reasons.push(`${insuficienteProducts} productos tienen estado "Insuficiente" y no son recomendados para estas dimensiones.`);
      }
    } else if (filter === 'ajustada' && ajustadaProducts === 0) {
      reasons.push(`No hay productos con estado "Ajustada" para los parámetros ingresados.`);
      if (okProducts > 0) {
        reasons.push(`Hay ${okProducts} productos con estado "OK" disponibles.`);
      }
    }
  }
  
  // Get current parameters for display
  const ejeX = document.querySelector('#ejeX').value || 'No especificado';
  const ejeY = document.querySelector('#ejeY').value || 'No especificado';
  const largoTotal = document.querySelector('#largoTotal').value || 'No especificado';
  const anchoTotal = document.querySelector('#anchoTotal').value || 'No especificado';
  const uso = document.querySelector('#uso').value;
  const cargaViva = document.querySelector('#cargaViva').value;
  const tipoLosa = document.querySelector('#tipoLosa').value;
  
  noResultsDiv.innerHTML = `
    <div class="no-results-card">
      <div class="no-results-header">
        <h2>🔍 No se encontraron resultados</h2>
      </div>
      
      <div class="no-results-content">
        <div class="parameters-section">
          <h3>📋 Parámetros utilizados:</h3>
          <ul class="parameters-list">
            <li><strong>Distancias de apoyo:</strong> ${ejeX} × ${ejeY} metros</li>
            <li><strong>Dimensiones totales:</strong> ${largoTotal} × ${anchoTotal} metros</li>
            <li><strong>Tipo de losa:</strong> ${tipoLosa}</li>
            <li><strong>Uso:</strong> ${uso || (cargaViva ? `Carga personalizada: ${cargaViva} kN/m²` : 'No especificado')}</li>
            <li><strong>País:</strong> ${state.pais}</li>
          </ul>
        </div>
        
        <div class="reasons-section">
          <h3>❓ Posibles causas:</h3>
          <ul class="reasons-list">
            ${reasons.map(reason => `<li>${reason}</li>`).join('')}
          </ul>
        </div>
        
        <div class="suggestions-section">
          <h3>💡 Sugerencias:</h3>
          <ul class="suggestions-list">
            <li>Intenta cambiar el filtro a "Todas" para ver todos los productos calculados</li>
            <li>Verifica que las dimensiones sean correctas y estén en metros</li>
            <li>Considera ajustar las dimensiones de la losa</li>
            <li>Prueba con diferentes configuraciones (bidireccional vs unidireccional)</li>
            <li>Revisa si hay productos disponibles para tu país</li>
          </ul>
        </div>
        
        <div class="actions-section">
          <button class="btn-retry" onclick="goBackToStep(4)">📝 Modificar parámetros</button>
          <button class="btn-filter" onclick="changeFilterToAll()">👁️ Ver todos los resultados</button>
        </div>
      </div>
    </div>
  `;
  
  cards.appendChild(noResultsDiv);
}

function changeFilterToAll() {
  const filtro = document.querySelector('#filtroEstado');
  if (filtro) {
    filtro.value = 'todas';
    applyFilterAndRender();
  }
}

function goBackToStep(stepNumber) {
  // Hide results section
  document.querySelector('#resultados').hidden = true;
  
  // Show wizard
  document.querySelector('#wizard').style.display = 'block';
  
  // Set current step
  state.currentStep = stepNumber;
  
  // Hide all step cards
  document.querySelectorAll('.step-card').forEach(card => {
    card.classList.remove('active');
  });
  
  // Show target step
  const targetCard = document.querySelector(`.step-card[data-step="${stepNumber}"]`);
  if (targetCard) {
    targetCard.classList.add('active');
  }
  
  // Update step indicator
  updateStepIndicator();
}

// Wizard Functions
function initWizard() {
  updateStepIndicator();
}

function nextStep() {
  console.log('nextStep called, current step:', state.currentStep);
  
  // Validate current step before proceeding
  if (state.currentStep === 1) {
    const zona = document.querySelector('#zona').value;
    if (!zona) {
      const warning = document.querySelector('#zonaWarning');
      if (warning) {
        warning.style.display = 'flex';
        setTimeout(() => {
          warning.style.display = 'none';
        }, 3000);
      }
      return;
    }
    // Update pais based on zona selection
    state.zona = zona;
    state.pais = zona;
  }
  
  if (state.currentStep === 3) {
    // Validate custom load warning if custom load is being used
    const cargaViva = document.querySelector('#cargaViva').value;
    const confirmCustomLoad = document.querySelector('#confirmCustomLoad');
    if (cargaViva && !confirmCustomLoad.checked) {
      const warning = document.querySelector('#customLoadWarning');
      if (warning) {
        warning.style.display = 'flex';
        setTimeout(() => {
          warning.style.display = 'none';
        }, 5000);
      }
      return;
    }
  }
  
  if (state.currentStep === 4) {
    const ejeX = document.querySelector('#ejeX').value;
    const ejeY = document.querySelector('#ejeY').value;
    
    if (!ejeX || !ejeY || parseFloat(ejeX) <= 0 || parseFloat(ejeY) <= 0) {
      const warning = document.querySelector('#dimensionWarning');
      if (warning) {
        warning.style.display = 'flex';
        setTimeout(() => {
          warning.style.display = 'none';
        }, 3000);
      }
      return;
    }
  }
  
  if (state.currentStep === 5) {
    const largoTotal = document.querySelector('#largoTotal').value;
    const anchoTotal = document.querySelector('#anchoTotal').value;
    
    if (!largoTotal || !anchoTotal || parseFloat(largoTotal) <= 0 || parseFloat(anchoTotal) <= 0) {
      const warning = document.querySelector('#totalDimensionWarning');
      if (warning) {
        warning.style.display = 'flex';
        setTimeout(() => {
          warning.style.display = 'none';
        }, 3000);
      }
      return;
    }
  }
  
  if (state.currentStep < 6) {
    hideCurrentStep();
    state.currentStep++;
    showCurrentStep();
    updateStepIndicator();
    saveState();
  }
}

function prevStep() {
  if (state.currentStep > 1) {
    showLoading();
    setTimeout(() => {
      hideCurrentStep();
      state.currentStep--;
      showCurrentStep();
      updateStepIndicator();
      hideLoading();
    }, 1000); // 1 second loading
  }
}

function hideCurrentStep() {
  const currentCard = document.querySelector(`.step-card[data-step="${state.currentStep}"]`);
  if (currentCard) {
    currentCard.classList.remove('active');
  }
}

function showCurrentStep() {
  const nextCard = document.querySelector(`.step-card[data-step="${state.currentStep}"]`);
  if (nextCard) {
    nextCard.classList.add('active');
  }
}

function updateStepIndicator() {
  // Update step numbers
  document.querySelectorAll('.step-number').forEach(step => {
    const stepNum = parseInt(step.dataset.step);
    step.classList.remove('active', 'completed');
    
    if (stepNum === state.currentStep) {
      step.classList.add('active');
    } else if (stepNum < state.currentStep) {
      step.classList.add('completed');
    }
  });
  
  // Update step title
  const currentStepSpan = document.querySelector('#currentStep');
  if (currentStepSpan) {
    currentStepSpan.textContent = state.currentStep;
  }
}

function showLoading() {
  const loadingScreen = document.querySelector('#loadingScreen');
  const loadingText = document.querySelector('#loadingText');
  
  if (loadingScreen && loadingText) {
    const messages = [
      'Procesando información...',
      'Validando datos...',
      'Preparando siguiente paso...',
      'Cargando...'
    ];
    
    loadingText.textContent = messages[Math.floor(Math.random() * messages.length)];
    loadingScreen.style.display = 'flex';
  }
}

function hideLoading() {
  const loadingScreen = document.querySelector('#loadingScreen');
  if (loadingScreen) {
    loadingScreen.style.display = 'none';
  }
}

function calculateResults() {
  console.log('calculateResults called');
  console.log('Current state before calculation:', state);
  
  // Update state with current form values
  const zonaEl = document.querySelector('#zona');
  const tipoLosaEl = document.querySelector('#tipoLosa');
  const usoEl = document.querySelector('#uso');
  const cargaVivaEl = document.querySelector('#cargaViva');
  const ejeXEl = document.querySelector('#ejeX');
  const ejeYEl = document.querySelector('#ejeY');
  const largoTotalEl = document.querySelector('#largoTotal');
  const anchoTotalEl = document.querySelector('#anchoTotal');
  const losaPctEl = document.querySelector('#losa_pct');
  
  if (zonaEl) {
    state.zona = zonaEl.value;
    state.pais = zonaEl.value;
  }
  if (tipoLosaEl) state.tipoLosa = tipoLosaEl.value;
  if (usoEl) state.uso = usoEl.value;
  if (cargaVivaEl) state.cargaViva = cargaVivaEl.value.replace(',', '.');
  if (ejeXEl) state.ejeX = ejeXEl.value.replace(',', '.');
  if (ejeYEl) state.ejeY = ejeYEl.value.replace(',', '.');
  if (largoTotalEl) state.largoTotal = largoTotalEl.value.replace(',', '.');
  if (anchoTotalEl) state.anchoTotal = anchoTotalEl.value.replace(',', '.');
  if (losaPctEl) state.losa_pct = losaPctEl.value.replace(',', '.');
  
  console.log('Updated state:', state);
  
  // Validate required fields before proceeding
  if (!state.ejeX || !state.ejeY || parseFloat(state.ejeX) <= 0 || parseFloat(state.ejeY) <= 0) {
    alert('Por favor ingresa valores válidos para Eje X y Eje Y');
    return;
  }
  
  if (!state.uso && !state.cargaViva) {
    alert('Por favor selecciona un uso predefinido o ingresa una carga viva personalizada');
    return;
  }
  
  showLoading();
  const loadingText = document.querySelector('#loadingText');
  if (loadingText) {
    loadingText.textContent = 'Calculando productos...';
  }
  
  setTimeout(async () => {
    try {
      console.log('Starting final calculation...');
      await buscarProductos();
      console.log('Calculation completed, hiding loading...');
      hideLoading();
      
      // Show results section after calculation
      console.log('Showing results section...');
      document.querySelector('#wizard').style.display = 'none';
      document.querySelector('#resultados').hidden = false;
      console.log('Results section should now be visible');
    } catch (error) {
      console.error('Error in final calculation:', error);
      hideLoading();
      alert('Error al realizar el cálculo. Por favor intenta nuevamente.');
    }
  }, 1000); // 1 second for final calculation
}

function restoreState(){
  console.log('Restoring state from sessionStorage');
  Object.keys(state).forEach(k => {
    if (['productos','candidatos','candidatosAll','currentStep'].includes(k)) return;
    const val = sessionStorage.getItem(k);
    if (val !== null) {
      state[k] = val;
      console.log(`Restored ${k}:`, val);
    }
  });
  
  // Apply restored values to form elements
  const zonaEl = document.querySelector('#zona');
  if (zonaEl) {
    if (state.zona) {
      zonaEl.value = state.zona;
    } else {
      // Ensure default option is selected if no zona in state
      zonaEl.selectedIndex = 0;
    }
  }
  
  const tipoLosaEl = document.querySelector('#tipoLosa');
  if (tipoLosaEl && state.tipoLosa) tipoLosaEl.value = state.tipoLosa;
  
  const usoEl = document.querySelector('#uso');
  if (usoEl && state.uso) usoEl.value = state.uso;
  
  const cargaVivaEl = document.querySelector('#cargaViva');
  if (cargaVivaEl && state.cargaViva) cargaVivaEl.value = state.cargaViva;
  
  const ejeXEl = document.querySelector('#ejeX');
  if (ejeXEl && state.ejeX) ejeXEl.value = state.ejeX;
  
  const ejeYEl = document.querySelector('#ejeY');
  if (ejeYEl && state.ejeY) ejeYEl.value = state.ejeY;
  
  const largoTotalEl = document.querySelector('#largoTotal');
  if (largoTotalEl && state.largoTotal) largoTotalEl.value = state.largoTotal;
  
  const anchoTotalEl = document.querySelector('#anchoTotal');
  if (anchoTotalEl && state.anchoTotal) anchoTotalEl.value = state.anchoTotal;
  
  const losaPctEl = document.querySelector('#losa_pct');
  if (losaPctEl && state.losa_pct) losaPctEl.value = state.losa_pct;
  
  console.log('State restoration completed');
}

async function loadConfig(){
  try {
    const res = await fetch(`${apiBase}/config.php`);
    const data = await res.json();
    if (!data.ok) throw new Error('No config');
    
    // Usos
    const uso = document.querySelector('#uso');
    if (uso) {
      uso.innerHTML = data.usos ? 
        data.usos.map(u => 
          `<option value="${u.nombre}" ${u.nombre === state.uso ? 'selected' : ''}>
            ${u.nombre}
          </option>`
        ).join('') : 
        '<option value="">No hay opciones disponibles</option>';
      
      if (data.usos && data.usos.length && !state.uso) {
        state.uso = data.usos[0].nombre;
      }
      
      // Update state when selection changes and show predefined value
      uso.addEventListener('change', e => { 
        state.uso = e.target.value;
        
        // Show predefined live load value
        const cargaVivaDisplay = document.querySelector('#cargaVivaDisplay');
        const cargaVivaValor = document.querySelector('#cargaVivaValor');
        
        if (cargaVivaDisplay && cargaVivaValor && state.uso) {
          // Find the selected uso in the data to get its carga_viva_kN_m2 value
          const selectedUso = data.usos.find(u => u.nombre === state.uso);
          if (selectedUso && selectedUso.carga_viva_kN_m2) {
            cargaVivaValor.textContent = selectedUso.carga_viva_kN_m2;
            cargaVivaDisplay.style.display = 'block';
          } else {
            cargaVivaDisplay.style.display = 'none';
          }
        } else if (cargaVivaDisplay) {
          cargaVivaDisplay.style.display = 'none';
        }
        
        saveState(); 
      });
    }

    // Paises -> populate Step 1 (#zona)
    const zona = document.querySelector('#zona');
    if (zona) {
      // Build options
      const list = Array.isArray(data.paises) ? data.paises : [];
      // Preserve selection if valid; otherwise keep placeholder and require user choice
      const desired = (state.pais && list.includes(state.pais)) ? state.pais : '';
      zona.innerHTML = '<option value="">Selecciona un país</option>' +
        list.map(n => `<option value="${n}">${n}</option>`).join('');
      if (desired) {
        zona.value = desired;
        // Sync state
        state.zona = desired; state.pais = desired; saveState();
      }
      zona.addEventListener('change', e => {
        state.zona = e.target.value;
        state.pais = e.target.value;
        saveState();
      });
    }
  } catch (error) {
    console.error('Error loading config:', error);
    const uso = document.querySelector('#uso');
    if (uso) {
      uso.innerHTML = '<option value="">Error cargando opciones</option>';
    }
  }
}

function bindEvents(){
  // Add event listeners for filters
  const filtroEstado = document.querySelector('#filtroEstado');
  if (filtroEstado) filtroEstado.addEventListener('change', applyFilterAndRender);
  
  const filtroAltura = document.querySelector('#filtroAltura');
  if (filtroAltura) filtroAltura.addEventListener('change', applyFilterAndRender);
  
  const exportar = document.querySelector('#exportar');
  if (exportar) exportar.addEventListener('click', exportarCSV);
  
  const descargar = document.querySelector('#descargar');
  if (descargar) descargar.addEventListener('click', descargarPDF);
  
  // Bind input events to save state
  // Exclude 'losa_pct' here; it's handled in initSlider() for real-time updates
  ['tipoLosa', 'uso', 'cargaViva', 'ejeX', 'ejeY', 'largoTotal', 'anchoTotal'].forEach(id => {
    const el = document.querySelector(`#${id}`);
    if (el) {
      el.addEventListener('change', () => {
        console.log(`${id} changed to:`, el.value);
        state[id] = el.value;
        saveState();
      });
    }
  });
  document.querySelector('#ejeX').addEventListener('input', e=>{ 
    const value = e.target.value.replace(',', '.'); // Convert comma to dot
    state.ejeX=parseFloat(value||'0'); 
    saveState(); 
  });
  document.querySelector('#ejeY').addEventListener('input', e=>{ 
    const value = e.target.value.replace(',', '.'); // Convert comma to dot
    state.ejeY=parseFloat(value||'0'); 
    saveState(); 
  });
  
  // Add event listeners for custom load warning
  const cargaVivaEl = document.querySelector('#cargaViva');
  const customLoadWarning = document.querySelector('#customLoadWarning');
  const confirmCustomLoad = document.querySelector('#confirmCustomLoad');
  const cargaVivaDisplay = document.querySelector('#cargaVivaDisplay');
  
  if (cargaVivaEl && customLoadWarning) {
    cargaVivaEl.addEventListener('input', () => {
      if (cargaVivaEl.value.trim()) {
        customLoadWarning.style.display = 'flex';
        // Hide the predefined value display when custom value is entered
        if (cargaVivaDisplay) {
          cargaVivaDisplay.style.display = 'none';
        }
      } else {
        customLoadWarning.style.display = 'none';
        if (confirmCustomLoad) confirmCustomLoad.checked = false;
        // Show the predefined value display again when custom value is cleared
        if (cargaVivaDisplay && state.uso) {
          cargaVivaDisplay.style.display = 'block';
        }
      }
    });
  }
  
  // Add event listener for custom load confirmation checkbox
  if (confirmCustomLoad && cargaVivaDisplay) {
    confirmCustomLoad.addEventListener('change', () => {
      if (confirmCustomLoad.checked && cargaVivaEl && cargaVivaEl.value.trim()) {
        // Hide predefined value display when custom load is confirmed
        cargaVivaDisplay.style.display = 'none';
      } else if (!cargaVivaEl || !cargaVivaEl.value.trim()) {
        // Show predefined value display if no custom value
        if (state.uso) {
          cargaVivaDisplay.style.display = 'block';
        }
      }
    });
  }
  
  // Add event listeners for new total dimension fields
  const largoTotalEl = document.querySelector('#largoTotal');
  const anchoTotalEl = document.querySelector('#anchoTotal');
  
  if (largoTotalEl) {
    largoTotalEl.addEventListener('input', e => {
      const value = e.target.value.replace(',', '.');
      state.largoTotal = parseFloat(value || '0');
      saveState();
    });
  }
  
  if (anchoTotalEl) {
    anchoTotalEl.addEventListener('input', e => {
      const value = e.target.value.replace(',', '.');
      state.anchoTotal = parseFloat(value || '0');
      saveState();
    });
  }
}

async function buscarProductos(){
  console.log('buscarProductos called');
  console.log('Current state:', state);
  
  try {
    // First load products - map new tipoLosa to old API format
    let direccionalidad, tipo;
    switch(state.tipoLosa) {
      case 'bidireccional':
        direccionalidad = 'bi';
        tipo = 'convencional';
        break;
      case 'unidireccional':
        direccionalidad = 'uni';
        tipo = 'convencional';
        break;
      case 'casetonada':
        direccionalidad = 'bi';
        tipo = 'convencional'; // All products are tipo=convencional, filter by familia later
        break;
      case 'casetonada_postensada':
        direccionalidad = 'bi';
        tipo = 'convencional'; // All products are tipo=convencional, filter by familia later
        break;
      default:
        direccionalidad = 'bi';
        tipo = 'convencional';
    }
    
    const params = new URLSearchParams({ pais: state.pais, direccionalidad: direccionalidad, tipo: tipo });
    console.log('API params:', params.toString());
    
    const res = await fetch(`${apiBase}/productos.php?${params}`);
    console.log('API response status:', res.status);
    
    if (!res.ok) {
      throw new Error(`HTTP error! status: ${res.status}`);
    }
    
    const data = await res.json();
    console.log('API response data:', data);
    
    if (!data.ok) {
      console.error('API returned error:', data);
      throw new Error(data.error || 'No productos disponibles');
    }
    
    if (!data.items || data.items.length === 0) {
      console.warn('No products found for current parameters');
      state.productos = [];
      state.candidatosAll = [];
      applyFilterAndRender();
      return;
    }
    
    // Filter products based on tipoLosa selection
    let filteredProducts = data.items;
    switch(state.tipoLosa) {
      case 'bidireccional':
        // familia=casetonada, direccionalidad=bi
        filteredProducts = data.items.filter(p => p.familia === 'casetonada' && p.direccionalidad === 'bi');
        break;
      case 'unidireccional':
        // familia=casetonada, direccionalidad=uni
        filteredProducts = data.items.filter(p => p.familia === 'casetonada' && p.direccionalidad === 'uni');
        break;
      case 'casetonada':
        // familia=casetonada, direccionalidad=bi (same as bidireccional for now)
        filteredProducts = data.items.filter(p => p.familia === 'casetonada' && p.direccionalidad === 'bi');
        break;
      case 'casetonada_postensada':
        // familia=casetonada, direccionalidad=bi, but we'll handle post-tensado in calculation
        filteredProducts = data.items.filter(p => p.familia === 'casetonada' && p.direccionalidad === 'bi');
        break;
      default:
        filteredProducts = data.items.filter(p => p.familia === 'casetonada' && p.direccionalidad === 'bi');
    }
    
    state.productos = filteredProducts;
    console.log('Products loaded and filtered:', state.productos.length, 'from', data.items.length, 'total');
    
    // Then calculate candidates
    await calcularCandidatos();
    
    // Finally render results
    applyFilterAndRender();
    
  } catch (error) {
    console.error('Error in buscarProductos:', error);
    state.productos = [];
    state.candidatosAll = [];
    applyFilterAndRender();
  }
}

async function calcularCandidatos(){
  console.log('calcularCandidatos called');
  console.log('Using state values:', {
    ejeX: state.ejeX,
    ejeY: state.ejeY,
    uso: state.uso,
    cargaViva: state.cargaViva,
    losa_pct: state.losa_pct
  });
  
  // Use state values instead of reading from DOM
  const ejeX = parseFloat(state.ejeX);
  const ejeY = parseFloat(state.ejeY);
  const cargaViva = state.cargaViva ? parseFloat(state.cargaViva) : null;
  const losa_pct = parseFloat(state.losa_pct) || 100;
  
  console.log('Parsed values:', { ejeX, ejeY, cargaViva, losa_pct });
  
  // Validar que los valores de geometría sean válidos
  if (!ejeX || !ejeY || ejeX <= 0 || ejeY <= 0) {
    console.error('Invalid geometry values:', { ejeX, ejeY });
    alert('Por favor ingresa valores válidos para Eje X y Eje Y (mayores a 0)');
    return;
  }
  
  if (!state.uso && !cargaViva) {
    console.error('No uso or cargaViva specified');
    alert('Por favor selecciona un uso predefinido o ingresa una carga viva personalizada');
    return;
  }

  // Primero calcular la comparación Atex vs Macizo general
  await calcularComparacionAtexMacizo();
  
  console.log('Starting product calculation...');
  console.log('Available products:', state.productos.length);
  
  const resultados = [];
  for (const p of state.productos) {
    console.log('Calculating for product:', p.id);
    // Add tipo_opcional for post-tensado calculations
    const params = new URLSearchParams({
      producto_id: p.id,
      ejeX: ejeX,
      ejeY: ejeY,
      uso: state.uso || '',
      cargaViva: cargaViva || '',
      losa_pct: losa_pct
    });
    
    // Add post-tensado flag if selected
    if (state.tipoLosa === 'casetonada_postensada') {
      params.append('tipo_opcional', 'post');
    }
    
    console.log('API call params:', params.toString());
    
    try {
      const res = await fetch(`${apiBase}/calcular.php?${params}`);
      const c = await res.json();
      
      console.log('API response for product', p.id, ':', c);
      
      if (!c.ok) {
        console.warn('Calculation failed for product', p.id, ':', c);
        continue;
      }
      const item = { ...p, ...c };
      resultados.push(item);
    } catch (error) {
      console.error('Error calculating product', p.id, ':', error);
    }
  }
  
  console.log('Total calculation results:', resultados.length);
  
  // Ordenar todos los resultados por ahorro total (concreto + acero)
  resultados.sort((a, b) => {
    const ahorroTotalA = (a.ahorro_concreto_pct || 0) + (a.ahorro_acero_pct || 0);
    const ahorroTotalB = (b.ahorro_concreto_pct || 0) + (b.ahorro_acero_pct || 0);
    return ahorroTotalB - ahorroTotalA; // Orden descendente (mayor ahorro primero)
  });
  
  console.log('All results sorted:', resultados.length);
  state.candidatosAll = resultados;
}

async function calcularComparacionAtexMacizo() {
  console.log('Calculando comparación Atex vs Macizo...');
  
  const ejeX = parseFloat(state.ejeX);
  const ejeY = parseFloat(state.ejeY);
  
  const params = new URLSearchParams({
    ejeX: ejeX,
    ejeY: ejeY,
    h_macizo: 32,
    h_atex: 47.5,
    q_atex: 0.225,
    coef_carga: 1.0
  });
  
  try {
    const res = await fetch(`${apiBase}/calcular-atex.php?${params}`);
    const resultado = await res.json();
    
    console.log('Comparación Atex vs Macizo:', resultado);
    
    if (resultado.ok) {
      // Guardar la comparación en el estado global
      state.comparacionAtexMacizo = resultado;
      
      // Mostrar la comparación en la interfaz
      mostrarComparacionAtexMacizo(resultado);
    } else {
      console.error('Error en comparación Atex vs Macizo:', resultado);
    }
  } catch (error) {
    console.error('Error calculando comparación Atex vs Macizo:', error);
  }
}

function mostrarComparacionAtexMacizo(resultado) {
  console.log('Mostrando comparación Atex vs Macizo');
  
  // Buscar o crear el contenedor de comparación
  let comparacionContainer = document.querySelector('#comparacion-atex-macizo');
  if (!comparacionContainer) {
    comparacionContainer = document.createElement('div');
    comparacionContainer.id = 'comparacion-atex-macizo';
    comparacionContainer.className = 'comparacion-container';
    
    // Insertar antes del contenedor de resultados
    const resultsContainer = document.querySelector('#cards');
    if (resultsContainer && resultsContainer.parentNode) {
      resultsContainer.parentNode.insertBefore(comparacionContainer, resultsContainer);
    }
  }
  
  comparacionContainer.innerHTML = `
    <div class="comparacion-header">
      <h2>Comparación: Losa Maciza vs Sistema Atex</h2>
      <p class="comparacion-subtitle">Análisis para losa de ${resultado.dimensiones.area_losa} m² (${resultado.ejeX_m}m × ${resultado.ejeY_m}m)</p>
    </div>
    
    <div class="comparacion-grid">
      <div class="sistema-card macizo">
        <h3>Losa Maciza</h3>
        <div class="valores">
          <div class="valor-item">
            <span class="label">Hormigón:</span>
            <span class="valor">${resultado.hormigon.macizo} ${resultado.hormigon.unidad}</span>
          </div>
          <div class="valor-item">
            <span class="label">Acero:</span>
            <span class="valor">${resultado.acero.macizo} ${resultado.acero.unidad}</span>
          </div>
          <div class="valor-item">
            <span class="label">Altura:</span>
            <span class="valor">${resultado.dimensiones.h_macizo} cm</span>
          </div>
        </div>
      </div>
      
      <div class="vs-separator">
        <span>VS</span>
      </div>
      
      <div class="sistema-card atex">
        <h3>Sistema Atex</h3>
        <div class="valores">
          <div class="valor-item">
            <span class="label">Hormigón:</span>
            <span class="valor">${resultado.hormigon.atex} ${resultado.hormigon.unidad}</span>
          </div>
          <div class="valor-item">
            <span class="label">Acero:</span>
            <span class="valor">${resultado.acero.atex} ${resultado.acero.unidad}</span>
          </div>
          <div class="valor-item">
            <span class="label">Altura:</span>
            <span class="valor">${resultado.dimensiones.h_atex} cm</span>
          </div>
        </div>
      </div>
    </div>
    
    <div class="ahorros-section">
      <h3>Ahorros con Sistema Atex</h3>
      <div class="ahorros-grid">
        <div class="ahorro-item hormigon">
          <div class="ahorro-porcentaje">${resultado.hormigon.ahorro_pct}%</div>
          <div class="ahorro-label">Ahorro Hormigón</div>
          <div class="ahorro-detalle">${(resultado.hormigon.macizo - resultado.hormigon.atex).toFixed(3)} ${resultado.hormigon.unidad} menos</div>
        </div>
        <div class="ahorro-item acero">
          <div class="ahorro-porcentaje">${resultado.acero.ahorro_pct}%</div>
          <div class="ahorro-label">Ahorro Acero</div>
          <div class="ahorro-detalle">${(resultado.acero.macizo - resultado.acero.atex).toFixed(1)} ${resultado.acero.unidad} menos</div>
        </div>
      </div>
    </div>
  `;
}

function renderCard(item, container){
  const div = document.createElement('div');
  div.className = 'card';
  
  // Calcular la altura equivalente en cm para mostrar en el título
  const alturaEquivalenteCm = Math.round(item.heq_mm / 10);
  const alturaTotalCm = Math.round(item.altura_mm / 10);
  
  // Extract mold height from JSON metadata if available
  let alturaMoldeCm = null;
  let espessuraLaminaCm = 5; // Default slab thickness
  let alturaRealTotalCm = alturaTotalCm;
  
  if (item.metadata_json) {
    try {
      const metadata = JSON.parse(item.metadata_json);
      const productKey = Object.keys(metadata)[0];
      if (metadata[productKey] && metadata[productKey].length > 2) {
        const dataRows = metadata[productKey].slice(2); // Skip header rows
        for (const row of dataRows) {
          if (row["Altura do \nMolde"] && row["Espessura \nda Lâmina"] && row["Altura \nTotal"]) {
            const moldeStr = row["Altura do \nMolde"].replace(',', '.');
            const espessuraStr = row["Espessura \nda Lâmina"].replace(',', '.');
            const totalStr = row["Altura \nTotal"].replace(',', '.');
            const molde = parseFloat(moldeStr);
            const espessura = parseFloat(espessuraStr);
            const total = parseFloat(totalStr);
            
            if (!isNaN(molde) && !isNaN(espessura) && !isNaN(total) && Math.abs(total - alturaTotalCm) < 1) {
              alturaMoldeCm = molde;
              espessuraLaminaCm = espessura;
              alturaRealTotalCm = total;
              break;
            }
          }
        }
      }
    } catch (e) {
      // Fallback to altura_mm if JSON parsing fails
    }
  }
  
  // Determine the product type image based on item direccionalidad
  const productImage = item.direccionalidad === 'bi' 
    ? 'assets/atex-forma-bidirecional.webp' 
    : 'assets/atex-forma-unidirecional.webp';
  
  div.innerHTML = `
    <div class="card-header">
      <h3>Molde ${item.nombre} /${alturaMoldeCm || (item.altura_mm/10)}+${espessuraLaminaCm} = ${(alturaMoldeCm || (item.altura_mm/10)) + espessuraLaminaCm} cm</h3>
    </div>
    
    <div class="calculation-box">
      <div class="product-image-container">
        <img src="${productImage}" alt="${item.direccionalidad === 'bi' ? 'Bidireccional' : 'Unidireccional'}" class="product-type-image">
      </div>
      <div class="inertia-section">
        <p><strong>Inercia / nervadura: ${item.inercia_cm4 || 0} cm⁴</strong></p>
        <p>Losa maciza equivalente en inercia</p>
        <div class="heq-formula">
          <span>Heq = </span>
          <span class="formula-root">³√</span>
          <span class="formula-fraction">
            <span class="numerator">${item.inercia_cm4 || 0} x 12</span>
            <span class="denominator">80</span>
          </span>
          <span> = ${(item.heq_mm/10).toFixed(1)} cm</span>
        </div>
      </div>
      
      <div class="comparison-table">
        <table>
          <thead>
            <tr>
              <th></th>
              <th class="concrete-header">Concreto</th>
              <th class="steel-header">Acero</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Maciza</strong> h = ${(item.heq_mm/10).toFixed(1)} cm</td>
              <td>${item.volumen_maciza_m3_m2.toFixed(3)} m³/m²</td>
              <td>${item.acero_maciza_kg_m2.toFixed(1)} kg/m²</td>
            </tr>
            <tr>
              <td><strong>Atex</strong> h = ${(alturaMoldeCm || (item.altura_mm/10)) + espessuraLaminaCm} cm</td>
              <td>${item.volumen_atex_m3_m2.toFixed(3)} m³/m²</td>
              <td>${item.acero_atex_kg_m2.toFixed(1)} kg/m²</td>
            </tr>
            <tr class="savings-row">
              <td class="savings-label-cell">Economía</td>
              <td class="savings-concrete">${item.ahorro_concreto_pct !== undefined ? Math.round(item.ahorro_concreto_pct * 10) / 10 : '0.0'}%</td>
              <td class="savings-steel">${item.ahorro_acero_pct !== undefined ? Math.round(item.ahorro_acero_pct * 10) / 10 : '0.0'}%</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <div class="choose-mold-container">
        <a href="contacto.html?nombre=${encodeURIComponent(item.nombre)}&altura=${item.altura_mm/10}cm&tipo=${state.direccionalidad === 'bi' ? 'Bidireccional' : 'Unidireccional'}&direccional=${state.direccionalidad === 'bi' ? 'bidireccional' : 'unidireccional'}&ejeX=${state.ejeX}&ejeY=${state.ejeY}&alturaLoseta=${state.alturaLoseta}&sobrecarga=${state.sobrecarga}&pais=${state.pais}&uso=${state.uso}" 
           class="contact-btn">
          Elegir este molde
        </a>
      </div>
    </div>
    
    <div class="badges">
      ${item.requiere_anulador_nervio ? '<span class="badge anulador">Anulador de nervio</span>' : ''}
      ${item.tipo==='post' ? '<span class="badge post">Post-tensado</span>' : ''}
    </div>
    <div class="estado-section">
      <p class="estado ${item.estado}">Estado: ${item.estado.toUpperCase()}</p>
      <div class="estado-details">
        ${getEstadoDetails(item)}
      </div>
    </div>
  `;
  container.appendChild(div);

  // Persist full wizard + selected product payload for the contact form
  const contactLink = div.querySelector('a.contact-btn');
  if (contactLink) {
    contactLink.addEventListener('click', () => {
      try {
        const wizardSnapshot = {
          pais: state.pais,
          direccionalidad: state.direccionalidad,
          tipo: state.tipo,
          uso: state.uso,
          cargaViva: state.cargaViva,
          ejeX: state.ejeX,
          ejeY: state.ejeY,
          losa_pct: state.losa_pct
        };

        const productoData = {
          id: item.id,
          nombre: item.nombre,
          altura_mm: item.altura_mm,
          heq_mm: item.heq_mm,
          heq_requerido_mm: item.heq_requerido_mm,
          inercia_cm4: item.inercia_cm4,
          volumen_maciza_m3_m2: item.volumen_maciza_m3_m2,
          acero_maciza_kg_m2: item.acero_maciza_kg_m2,
          volumen_atex_m3_m2: item.volumen_atex_m3_m2,
          acero_atex_kg_m2: item.acero_atex_kg_m2,
          ahorro_concreto_pct: item.ahorro_concreto_pct,
          ahorro_acero_pct: item.ahorro_acero_pct,
          estado: item.estado,
          requiere_anulador_nervio: !!item.requiere_anulador_nervio,
          tipo: item.tipo
        };

        const payload = {
          wizard: wizardSnapshot,
          producto: productoData,
          comparacion: state.comparacionAtexMacizo || null,
          timestamp: new Date().toISOString()
        };

        sessionStorage.setItem('contactPayload', JSON.stringify(payload));
      } catch (err) {
        console.error('Failed to store contact payload', err);
      }
    });
  }
}

function getEstadoDetails(item) {
  const heqReq = item.heq_requerido_mm;
  const heqActual = item.heq_mm;
  const ahorroConcreto = item.ahorro_concreto_pct;
  const ahorroAcero = item.ahorro_acero_pct;
  
  let details = [];
  
  if (item.estado === 'insuficiente') {
    if (heqReq && heqActual < heqReq * 0.95) {
      details.push(`⚠️ Heq insuficiente: ${heqActual} mm < ${(heqReq * 0.95).toFixed(1)} mm requerido`);
    }
    if (ahorroConcreto < 8) {
      details.push(`📉 Ahorro concreto bajo: ${ahorroConcreto}% (mín. 8%)`);
    }
    if (ahorroAcero < 5) {
      details.push(`📉 Ahorro acero bajo: ${ahorroAcero}% (mín. 5%)`);
    }
  } else if (item.estado === 'ajustada') {
    details.push(`⚡ Ahorros moderados: Concreto ${ahorroConcreto}%, Acero ${ahorroAcero}%`);
    if (heqReq) {
      details.push(`✅ Heq suficiente: ${heqActual} mm ≥ ${(heqReq * 0.95).toFixed(1)} mm`);
    }
  } else if (item.estado === 'ok') {
    if (heqReq) {
      details.push(`✅ Heq suficiente: ${heqActual} mm ≥ ${(heqReq * 0.95).toFixed(1)} mm`);
    }
  }
  
  return details.map(d => `<small>${d}</small>`).join('<br>');
}

function appendRow(item, tbody){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>${item.id}</td>
    <td>${item.nombre}</td>
    <td>${item.altura_mm}</td>
    <td>${item.familia}</td>
    <td>${item.heq_mm}</td>
    <td>${item.ahorro_concreto_pct !== undefined ? Math.round(item.ahorro_concreto_pct * 10) / 10 : '0.0'}%</td>
    <td>${item.ahorro_acero_pct !== undefined ? Math.round(item.ahorro_acero_pct * 10) / 10 : '0.0'}%</td>
    <td>${item.estado}</td>
  `;
  tbody.appendChild(tr);
}

function exportCsv(){
  const headers = ['id','nombre','altura_mm','familia','heq_mm','ahorro_concreto_pct','ahorro_acero_pct','estado'];
  const rows = state.candidatos.map(c=> headers.map(h=> c[h] ?? '').join(','));
  const csv = [headers.join(','), ...rows].join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url; a.download = 'resultados.csv'; a.click();
  URL.revokeObjectURL(url);
}

async function descargarPdf(){
  const res = await fetch(`${apiBase}/resumen-pdf.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ candidatos: state.candidatos }) });
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url; a.download = 'resumen.pdf'; a.click();
  URL.revokeObjectURL(url);
}

// Initialize percentage number input with arrow buttons
function initPctInput() {
  const input = document.getElementById('losa_pct');
  const btnDec = document.getElementById('losa_dec');
  const btnInc = document.getElementById('losa_inc');
  if (!input) return;

  const clamp = (v) => Math.max(0, Math.min(100, v));
  const sync = () => {
    let v = parseInt(String(input.value).replace(',', '.'), 10);
    if (isNaN(v)) v = 100;
    v = clamp(v);
    input.value = String(v);
    state.losa_pct = v;
    saveState();
  };

  input.addEventListener('input', sync);
  input.addEventListener('change', sync);

  if (btnDec) btnDec.addEventListener('click', () => { input.value = String(clamp((parseInt(input.value||'100',10)||100) - 1)); sync(); });
  if (btnInc) btnInc.addEventListener('click', () => { input.value = String(clamp((parseInt(input.value||'100',10)||100) + 1)); sync(); });

  // Initialize with saved value or default 100
  if (state.losa_pct !== undefined && state.losa_pct !== null) {
    input.value = String(clamp(parseInt(state.losa_pct,10) || 100));
  } else {
    input.value = '100';
    state.losa_pct = 100;
  }
  saveState();
}

window.addEventListener('DOMContentLoaded', async () => {
  await loadConfig();
  
  // Check for URL parameters first
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.toString()) {
    // If we have URL parameters, update the form fields
    const form = document.getElementById('wizard-form');
    if (form) {
      const formElements = form.elements;
      for (let i = 0; i < formElements.length; i++) {
        const element = formElements[i];
        if (urlParams.has(element.name)) {
          if (element.type === 'radio' || element.type === 'checkbox') {
            element.checked = element.value === urlParams.get(element.name);
          } else {
            element.value = urlParams.get(element.name) || '';
          }
        }
      }
    }
    
    // Update state from URL params
    const stateKeys = ['ejeX', 'ejeY', 'alturaLoseta', 'sobrecarga', 'pais', 'uso', 'direccionalidad'];
    stateKeys.forEach(key => {
      if (urlParams.has(key)) {
        state[key] = urlParams.get(key);
      }
    });
    
    // Save the updated state
    saveState();
  }
  
  // Restore saved state as early as possible so UI reflects it
  restoreState();
  
  // Initialize percentage input after state restore
  initPctInput();
  bindEvents();
  initWizard();
  
  // If we have URL parameters, trigger the search
  if (urlParams.toString() && state.pais && state.uso) {
    // Small delay to ensure the UI is ready
    setTimeout(() => {
      buscarProductos();
    }, 100);
  }
});

// Remove duplicate event listeners that cause conflicts
const buscarBtn = document.querySelector('#buscar');
if (buscarBtn) buscarBtn.addEventListener('click', buscarProductos);

const exportBtn = document.querySelector('#exportCsv');
if (exportBtn) exportBtn.addEventListener('click', exportCsv);

const descargarBtn = document.querySelector('#descargarPdf');
if (descargarBtn) descargarBtn.addEventListener('click', descargarPdf);

const filtro = document.querySelector('#filtroEstado');
if (filtro) filtro.addEventListener('change', applyFilterAndRender);

// Initialize height filter when page loads
document.addEventListener('DOMContentLoaded', function() {
  if (state.candidatosAll && state.candidatosAll.length > 0) {
    updateHeightFilterOptions();
  }
});

// Expose functions globally for inline onclick attributes
window.nextStep = nextStep;
window.prevStep = prevStep;
window.calculateResults = calculateResults;
window.goBackToStep = goBackToStep;
window.changeFilterToAll = changeFilterToAll;
