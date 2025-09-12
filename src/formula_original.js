/**
 * FÓRMULA ORIGINAL DEL CONFIGURADOR ATEX
 * Respaldo creado el 2025-01-12
 * 
 * Esta es la implementación original basada en el catálogo Atex 2025
 * que utiliza Heq (altura equivalente en inercia) para comparar
 * losas macizas vs losas nervadas Atex.
 */

class CalculoOriginal {
    /**
     * Calcula Heq (laje maciça equivalente em inércia).
     * Usa heq_mm del producto si existe, sino estima por inércia si disponible.
     */
    static calcularHeq(heq_mm_existente, altura_mm, familia, direccionalidad, mensajes) {
        if (heq_mm_existente && heq_mm_existente > 0) {
            return heq_mm_existente;
        }
        
        // Fórmula de estimación basada en geometría típica Atex
        // Para losas nervadas: heq ≈ 0.6 * D para bidireccional, 0.55 * D para unidireccional
        let factor = (direccionalidad === 'bi') ? 0.60 : 0.55;
        if (familia === 'post') factor += 0.08; // post-tensado aumenta eficiencia
        
        const heq = Math.max(0.0, altura_mm * factor);
        mensajes.push(`Heq calculado por fórmula Atex: heq = ${factor} × D`);
        return heq;
    }

    /**
     * Heq requerido segun luces y relación L/heq (ratios).
     * ratio_valor es L/heq (adimensional). Heq_req = Lmax_m / (ratio) en m -> mm.
     */
    static heqRequeridoPorLuces(ejeX_m, ejeY_m, ratio_valor, mensajes) {
        if (ratio_valor <= 0) return 0.0;
        const Lmax = Math.max(ejeX_m, ejeY_m);
        const heq_m = Lmax / ratio_valor;
        const heq_mm = Math.max(0.0, heq_m * 1000.0);
        mensajes.push(`Heq requerido por luces (L/heq=${ratio_valor}): ${heq_mm.toFixed(1)} mm`);
        return heq_mm;
    }

    /**
     * Volumen por m2: losa maciza vs losa nervada Atex.
     * Fórmulas del catálogo Atex 2025.
     */
    static volumenes(heq_mm, altura_mm, familia, direccionalidad, vol_atex_override = null) {
        // LOSA MACIZA: Volumen = heq (espesor equivalente en inercia)
        // Según catálogo: losa maciza tiene espesor = heq para misma capacidad estructural
        const vol_maciza_m3_m2 = heq_mm / 1000.0; // m³/m²
        
        // LOSA NERVADA ATEX: usar volumen real del producto si disponible
        let vol_atex_m3_m2;
        if (vol_atex_override !== null) {
            vol_atex_m3_m2 = Math.max(0.0, vol_atex_override);
        } else {
            // Estimación mejorada basada en datos reales del catálogo Atex 2025
            vol_atex_m3_m2 = this.estimarVolumenAtex(heq_mm, altura_mm, familia, direccionalidad);
        }

        return [vol_maciza_m3_m2, vol_atex_m3_m2];
    }
    
    /**
     * Estimación de volumen Atex basada en datos reales del catálogo
     */
    static estimarVolumenAtex(heq_mm, altura_mm, familia, direccionalidad) {
        // Datos reales del catálogo Atex para referencia:
        // Atex 600 D20 (200mm): heq=127mm, vol=0.087 m³/m² (31.5% ahorro vs 0.127)
        // Atex 600 D23 (230mm): heq=156mm, vol=0.106 m³/m² (32.1% ahorro vs 0.156)
        // Atex 610 D15 (150mm): heq=111mm, vol≈0.065 m³/m² (41.4% ahorro vs 0.111)
        
        const vol_maciza_equiv = heq_mm / 1000.0;
        
        // Factor base de eficiencia según altura (datos del catálogo)
        let factor_eficiencia;
        if (altura_mm <= 150) {
            factor_eficiencia = 0.585; // ~41.5% ahorro (como D15)
        } else if (altura_mm <= 200) {
            factor_eficiencia = 0.685; // ~31.5% ahorro (como D20)
        } else if (altura_mm <= 230) {
            factor_eficiencia = 0.679; // ~32.1% ahorro (como D23)
        } else if (altura_mm <= 300) {
            factor_eficiencia = 0.70;  // ~30% ahorro
        } else {
            factor_eficiencia = 0.65;  // ~35% ahorro (productos altos más eficientes)
        }
        
        // Ajustes por familia y direccionalidad
        if (familia === 'post') factor_eficiencia -= 0.05; // post-tensado más eficiente
        if (familia === 'casetonada') factor_eficiencia -= 0.02; // casetones optimizados
        if (direccionalidad === 'bi') factor_eficiencia -= 0.01; // bidireccional ligeramente mejor
        
        // Limitar dentro de rangos realistas del catálogo
        factor_eficiencia = Math.max(0.55, Math.min(0.75, factor_eficiencia));
        
        return Math.max(0.0, vol_maciza_equiv * factor_eficiencia);
    }

    /**
     * Acero por m2: basado en fórmulas del catálogo Atex para losa maciza vs nervada.
     */
    static acero(heq_mm, familia, tipo) {
        // LOSA MACIZA: Acero proporcional al espesor (heq)
        // Fórmula calibrada con datos del catálogo: As = k × heq
        const k_maciza = 0.8; // kg/m² por mm de espesor
        const kg_maciza = Math.max(0.0, heq_mm * k_maciza);
        
        // LOSA NERVADA ATEX: Optimización por concentración en nervuras
        // Datos del catálogo muestran ahorros de 45-65% según el producto
        const factor_acero_atex = this.calcularFactorAceroAtex(heq_mm, familia, tipo);
        
        const kg_atex = Math.max(0.0, kg_maciza * factor_acero_atex);
        return [kg_maciza, kg_atex];
    }
    
    /**
     * Calcula el factor de acero para losas Atex basado en datos del catálogo
     */
    static calcularFactorAceroAtex(heq_mm, familia, tipo) {
        // Factor base según datos del catálogo Atex 2025
        // Este factor representa la FRACCIÓN de acero que usa Atex vs maciza
        // Productos típicos usan 45-55% del acero de losa maciza (= 45-55% ahorro)
        let factor_base = 0.50; // Atex usa 50% del acero maciza (= 50% ahorro)
        
        // Ajustes por familia (basado en eficiencia estructural)
        if (familia === 'post' || tipo === 'post') {
            factor_base = 0.35; // post-tensado usa 35% del acero maciza (= 65% ahorro)
        } else if (familia === 'casetonada') {
            factor_base = 0.48; // casetones usan 48% del acero maciza (= 52% ahorro)
        }
        
        // Ajustes por heq (mayor heq permite mayor optimización)
        if (heq_mm >= 300) {
            factor_base -= 0.05; // productos con heq alto usan menos acero
        } else if (heq_mm >= 200) {
            factor_base -= 0.02; // productos medianos usan algo menos acero
        } else if (heq_mm <= 100) {
            factor_base += 0.08; // productos pequeños usan más acero relativo
        }
        
        // Limitar dentro de rangos realistas del catálogo
        // Factor 0.25 = Atex usa 25% del acero maciza = 75% ahorro
        // Factor 0.65 = Atex usa 65% del acero maciza = 35% ahorro
        return Math.max(0.25, Math.min(0.65, factor_base));
    }

    static estado(ahorro_concreto_pct, ahorro_acero_pct, suficiente_geometricamente) {
        if (!suficiente_geometricamente) return 'insuficiente';
        if (ahorro_concreto_pct >= 15 && ahorro_acero_pct >= 10) return 'ok';
        if (ahorro_concreto_pct >= 8 && ahorro_acero_pct >= 5) return 'ajustada';
        return 'insuficiente';
    }
}

/*
EJEMPLOS DE VALIDACIÓN CON CATÁLOGO ATEX 2025:

Ejemplo 1 - Atex 600 D20:
- Altura Total: 200 mm
- Heq catálogo: 127 mm (factor 0.635)
- Volume concreto: 0.087 m³/m² (nervada) vs 0.127 m³/m² (maciza) = 31.5% ahorro
- Acero estimado: ~55% del acero macizo equivalente

Ejemplo 2 - Atex 600 D23:
- Altura Total: 230 mm  
- Heq catálogo: 156 mm (factor 0.678)
- Volume concreto: 0.106 m³/m² (nervada) vs 0.156 m³/m² (maciza) = 32.1% ahorro
- Acero estimado: ~50% del acero macizo equivalente

Ejemplo 3 - Atex 610 D15:
- Altura Total: 150 mm
- Heq catálogo: 111 mm (factor 0.740)
- Volume concreto: estimado ~0.065 m³/m² (nervada) vs 0.111 m³/m² (maciza) = 41.4% ahorro
- Acero estimado: ~45% del acero macizo equivalente

Fórmulas implementadas basadas en estos datos reales del catálogo.
*/

// Exportar para uso en Node.js si está disponible
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CalculoOriginal;
}
