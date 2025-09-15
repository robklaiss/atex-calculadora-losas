<?php
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Atex Calculadora</title>
<link rel="stylesheet" href="assets/styles.css" />
<script defer src="assets/app.js"></script>
<link href="/atex-latam-favicon.png?1" rel="icon" type="image/png" />
<link href="/atex-latam-webclip.png?1" rel="apple-touch-icon" type="image/png" />
</head>
<body>
<header class="topbar">
  <img src="images/atex_latam_logo.png" alt="Atex" class="logo" />
  <div class="contact-section">
    <a href="contacto-proyecto.html" class="contact-btn"><span>¡Hablemos sobre su proyecto!</span></a>
  </div>
</header>
<main>
  <section class="wizard" id="wizard">
    <h1>Calculadora de losas</h1>
    
    <!-- Step Progress Indicator -->
    <div class="step-progress">
      <div class="step-indicator">
        <span class="step-number active" data-step="1">1</span>
        <span class="step-number" data-step="2">2</span>
        <span class="step-number" data-step="3">3</span>
        <span class="step-number" data-step="4">4</span>
        <span class="step-number" data-step="5">5</span>
        <span class="step-number" data-step="6">6</span>
      </div>
      <div class="step-title">Paso <span id="currentStep">1</span> de 6</div>
    </div>

    <!-- Step Cards -->
    <div class="wizard-steps">
      <!-- Step 1: Zona -->
      <div class="step-card active" data-step="1">
        <h3>Zona</h3>
        <p>País donde se ejecutará la obra</p>
        <select id="zona">
          <option value="">Selecciona un país</option>
        </select>
        <div id="zonaWarning" class="warning-message" style="display: none;">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="#dc3545">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
          </svg>
          <span>Por favor selecciona un país antes de continuar.</span>
        </div>
        <div class="step-actions">
          <button class="btn-next" onclick="nextStep()">Siguiente</button>
        </div>
        <script>
        // Initialize the application when the DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Check for URL parameters to restore search
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.toString()) {
                // If we have URL parameters, restore the form state
                const formElements = document.querySelectorAll('#wizard-form input, #wizard-form select');
                formElements.forEach(element => {
                    const paramValue = urlParams.get(element.name);
                    if (paramValue !== null) {
                        if (element.type === 'radio' || element.type === 'checkbox') {
                            element.checked = element.value === paramValue;
                        } else {
                            element.value = paramValue;
                        }
                    }
                });
                
                // Trigger form submission to perform the search
                const form = document.getElementById('wizard-form');
                if (form) {
                    const event = new Event('submit');
                    form.dispatchEvent(event);
                }
            }
        });
        </script>
      </div>

      <!-- Step 2: Direccionalidad -->
      <div class="step-card" data-step="2">
        <h3>Direccionalidad</h3>
        <p>Selecciona el tipo de direccionalidad de la losa</p>
        <select id="direccionalidad">
          <option value="bi">Bidireccional</option>
          <option value="uni">Unidireccional</option>
        </select>
        <div class="step-actions">
          <button class="btn-prev" onclick="prevStep()">Anterior</button>
          <button class="btn-next" onclick="nextStep()">Siguiente</button>
        </div>
      </div>

      <!-- Step 3: Tipo -->
      <div class="step-card" data-step="3">
        <h3>Tipo de losa</h3>
        <p>Elige entre losa convencional o post-tensada</p>
        <select id="tipo">
          <option value="convencional">Convencional</option>
          <option value="post">Post-tensado</option>
        </select>
        <div class="step-actions">
          <button class="btn-prev" onclick="prevStep()">Anterior</button>
          <button class="btn-next" onclick="nextStep()">Siguiente</button>
        </div>
      </div>

      <!-- Step 4: Uso -->
      <div class="step-card" data-step="4">
        <h3>Uso de la losa</h3>
        <p>Selecciona el uso predefinido o ingresa una carga viva personalizada</p>
        <div class="grid-2">
          <label>Predefinido
            <select id="uso">
              <option value="">Cargando opciones...</option>
            </select>
          </label>
          <label>Carga viva personalizada (kN/m²)
            <input type="number" id="cargaViva" step="0.1" min="0" placeholder="ej: 2.5" />
          </label>
        </div>
        <div class="step-actions">
          <button class="btn-prev" onclick="prevStep()">Anterior</button>
          <button class="btn-next" onclick="nextStep()">Siguiente</button>
        </div>
      </div>

      <!-- Step 5: Geometría -->
      <div class="step-card" data-step="5">
        <h3>Geometría</h3>
        <p>Ingresa las dimensiones de la losa en metros</p>
        <div class="grid-2">
          <label>Eje X (m) <input type="number" id="ejeX" step="0.01" min="0" placeholder="ej: 1.5"></label>
          <label>Eje Y (m) <input type="number" id="ejeY" step="0.01" min="0" placeholder="ej: 2.5"></label>
        </div>
        <div id="dimensionWarning" class="warning-message" style="display: none;">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="#dc3545">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
          </svg>
          <span>Por favor completa ambos campos de dimensión antes de continuar.</span>
        </div>
        <div class="step-actions">
          <button class="btn-prev" onclick="prevStep()">Anterior</button>
          <button class="btn-next" onclick="nextStep()">Siguiente</button>
        </div>
      </div>

      <!-- Step 6: Carga de losa -->
      <div class="step-card" data-step="6">
        <h3>Carga de losa</h3>
        <p>Especifica el porcentaje de carga de la losa (normalmente 100%)</p>
        <div class="pct-container">
          <div class="pct-row">
            <button type="button" class="slider-arrow" id="losa_dec" aria-label="Disminuir">&#8249;</button>
            <div class="pct-input">
              <input type="number" id="losa_pct" min="0" max="100" step="1" value="100" inputmode="numeric" />
              <span class="pct-suffix">%</span>
            </div>
            <button type="button" class="slider-arrow" id="losa_inc" aria-label="Aumentar">&#8250;</button>
          </div>
        </div>
        <div class="step-actions">
          <button class="btn-prev" onclick="prevStep()">Anterior</button>
          <button class="btn-calculate" onclick="calculateResults()">Calcular</button>
        </div>
      </div>
    </div>

    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen" style="display: none;">
      <div class="loader"></div>
      <p id="loadingText">Cargando...</p>
    </div>
  </section>

  <section class="resultados" id="resultados" hidden>
    <div class="actions">
      <div class="filter-group">
        <span id="count" style="color:#555"></span>
      </div>
      <div class="filter-group">
        <select id="filtroEstado" class="filter-select">
          <option value="todas">Estado: Todos</option>
          <option value="ok" selected>Estado: OK</option>
          <option value="ajustada">Estado: Ajustada</option>
          <option value="insuficiente">Estado: Insuficiente</option>
        </select>
        <select id="filtroAltura" class="filter-select">
          <option value="todas" selected>Altura: Todas</option>
          <!-- Options will be populated by JavaScript -->
        </select>
      </div>
    </div>
    <div id="cards" class="cards"></div>
    <table id="tabla" class="tabla" hidden>
      <thead>
        <tr>
          <th>ID</th><th>Nombre</th><th>Altura (mm)</th><th>Familia</th><th>Heq (mm)</th><th>Ahorro Hormigón</th><th>Ahorro Acero</th><th>Estado</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </section>
</main>
<footer class="foot">
  <div class="copyright">
    © 2025 Atex Paraguay S.A. Todos los derechos reservados. Queda prohibida la reproducción total o parcial de este sitio web, incluyendo textos, imágenes y diseños, sin la autorización previa y por escrito de Atex Paraguay S.A.
  </div>
</footer>
</body>
</html>
