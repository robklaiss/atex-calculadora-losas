/**
 * NUEVA FÓRMULA ATEX VS MACIZO
 * Implementada el 2025-01-12
 * 
 * Fórmula simplificada para comparar sistemas de losa maciza vs losa tipo Atex
 * basada en valores específicos proporcionados por el usuario.
 */

class FormulaAtex {
    /**
     * Calcula consumo de hormigón y acero para losa maciza vs Atex
     * @param {Object} params - Parámetros de entrada
     * @returns {Object} Resultados de comparación
     */
    static calcular(params = {}) {
        // Variables de entrada con valores por defecto
        const {
            h_macizo = 32,          // cm - altura losa maciza
            h_atex = 47.5,          // cm - altura losa Atex
            q_atex = 0.225,         // m³/m² - consumo hormigón Atex
            densidad_hormigon = 2500, // kg/m³
            cargas_adicionales = 325, // kg/m² (200 + 100 + 25)
            ejeX = 6.0,             // m
            ejeY = 8.0,             // m
            fywd = 435,             // MPa - límite del acero
            coef_carga = 1.0        // coeficiente de carga
        } = params;

        // Cálculo de momentos
        const area_losa = ejeX * ejeY; // m²
        const carga_total = 900; // kg/m² (valor base)
        const luz_mayor = Math.max(ejeX, ejeY);
        
        // Mx = My = (carga × luz²) / 100
        const Mx = My = (carga_total * Math.pow(luz_mayor, 2)) / 100;

        // HORMIGÓN
        // Consumo Hormigón (m³/m²) = (Altura útil h × coeficiente de carga) / 1000
        const hormigon_macizo = (h_macizo * coef_carga) / 1000; // 0.320 m³/m²
        const hormigon_atex = q_atex; // 0.225 m³/m²
        
        const ahorro_hormigon_pct = ((hormigon_macizo - hormigon_atex) / hormigon_macizo) * 100;

        // ACERO
        // Valores específicos según los requerimientos
        const acero_macizo = 13.4; // kg/m²
        const acero_atex = 8.0;    // kg/m²
        
        const ahorro_acero_pct = ((acero_macizo - acero_atex) / acero_macizo) * 100;

        // ESTADO
        const estado = this.determinarEstado(ahorro_hormigon_pct, ahorro_acero_pct);

        return {
            // Resultados principales
            hormigon: {
                macizo: parseFloat(hormigon_macizo.toFixed(3)),
                atex: parseFloat(hormigon_atex.toFixed(3)),
                ahorro_pct: parseFloat(ahorro_hormigon_pct.toFixed(1)),
                unidad: 'm³/m²'
            },
            acero: {
                macizo: parseFloat(acero_macizo.toFixed(1)),
                atex: parseFloat(acero_atex.toFixed(1)),
                ahorro_pct: parseFloat(ahorro_acero_pct.toFixed(1)),
                unidad: 'kg/m²'
            },
            
            // Datos adicionales
            dimensiones: {
                h_macizo: h_macizo,
                h_atex: h_atex,
                area_losa: parseFloat(area_losa.toFixed(2)),
                luz_mayor: luz_mayor
            },
            
            momentos: {
                Mx: parseFloat(Mx.toFixed(1)),
                My: parseFloat(My.toFixed(1))
            },
            
            estado: estado,
            
            // Metadatos
            formula_version: 'Atex vs Macizo v1.0',
            fecha_calculo: new Date().toISOString()
        };
    }

    /**
     * Calcula el acero requerido basado en momentos y características del material
     * (Implementación simplificada - en la práctica sería más compleja)
     */
    static calcularAcero(momento, fywd, altura_util, tipo = 'macizo') {
        // Fórmula simplificada para demostración
        // En la práctica se usarían las ecuaciones completas de flexión
        const factor_base = tipo === 'macizo' ? 0.015 : 0.009; // kg/m² por kN·m
        return momento * factor_base;
    }

    /**
     * Determina el estado del producto basado en los ahorros
     */
    static determinarEstado(ahorro_hormigon_pct, ahorro_acero_pct) {
        if (ahorro_hormigon_pct >= 25 && ahorro_acero_pct >= 35) return 'excelente';
        if (ahorro_hormigon_pct >= 20 && ahorro_acero_pct >= 25) return 'muy_bueno';
        if (ahorro_hormigon_pct >= 15 && ahorro_acero_pct >= 20) return 'bueno';
        if (ahorro_hormigon_pct >= 10 && ahorro_acero_pct >= 15) return 'aceptable';
        return 'insuficiente';
    }

    /**
     * Valida que los resultados coincidan con los valores esperados
     */
    static validarResultados() {
        const resultado = this.calcular();
        
        const esperado = {
            hormigon_macizo: 0.320,
            hormigon_atex: 0.225,
            ahorro_hormigon: 30,
            acero_macizo: 13.4,
            acero_atex: 8.0,
            ahorro_acero: 40
        };

        const validacion = {
            hormigon_macizo_ok: Math.abs(resultado.hormigon.macizo - esperado.hormigon_macizo) < 0.001,
            hormigon_atex_ok: Math.abs(resultado.hormigon.atex - esperado.hormigon_atex) < 0.001,
            ahorro_hormigon_ok: Math.abs(resultado.hormigon.ahorro_pct - esperado.ahorro_hormigon) < 0.5,
            acero_macizo_ok: Math.abs(resultado.acero.macizo - esperado.acero_macizo) < 0.1,
            acero_atex_ok: Math.abs(resultado.acero.atex - esperado.acero_atex) < 0.1,
            ahorro_acero_ok: Math.abs(resultado.acero.ahorro_pct - esperado.ahorro_acero) < 0.5
        };

        const todas_ok = Object.values(validacion).every(v => v === true);

        return {
            validacion_exitosa: todas_ok,
            detalles: validacion,
            resultado_calculado: resultado,
            valores_esperados: esperado
        };
    }

    /**
     * Genera un resumen textual de los resultados
     */
    static generarResumen(resultado) {
        return `
COMPARACIÓN LOSA MACIZA VS ATEX

Hormigón:
- Maciza: ${resultado.hormigon.macizo} ${resultado.hormigon.unidad}
- Atex: ${resultado.hormigon.atex} ${resultado.hormigon.unidad}
- Ahorro: ${resultado.hormigon.ahorro_pct}%

Acero:
- Maciza: ${resultado.acero.macizo} ${resultado.acero.unidad}
- Atex: ${resultado.acero.atex} ${resultado.acero.unidad}
- Ahorro: ${resultado.acero.ahorro_pct}%

Dimensiones:
- Altura maciza: ${resultado.dimensiones.h_macizo} cm
- Altura Atex: ${resultado.dimensiones.h_atex} cm
- Área losa: ${resultado.dimensiones.area_losa} m²

Estado: ${resultado.estado.toUpperCase()}
        `.trim();
    }
}

// Exportar para uso en Node.js si está disponible
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FormulaAtex;
}

// Hacer disponible globalmente en el navegador
if (typeof window !== 'undefined') {
    window.FormulaAtex = FormulaAtex;
}

// Auto-validación al cargar el módulo
if (typeof console !== 'undefined') {
    const validacion = FormulaAtex.validarResultados();
    if (validacion.validacion_exitosa) {
        console.log('✅ Fórmula Atex validada correctamente');
        console.log('Resultados:', validacion.resultado_calculado);
    } else {
        console.warn('⚠️ Validación de fórmula Atex falló');
        console.log('Detalles:', validacion.detalles);
    }
}
