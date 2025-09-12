# Calculadora de Losas Atex - Fórmulas de Cálculo

Este documento describe todas las fórmulas matemáticas y métodos de cálculo utilizados en el sistema de calculadora de losas Atex.

## Resumen

La calculadora compara losas macizas de concreto con losas nervadas Atex para determinar los ahorros de concreto y acero. Todas las fórmulas están basadas en el **catálogo Atex 2025** y validadas contra datos reales de productos.

## 1. Cálculo de Altura Equivalente (Heq)

La altura equivalente representa el espesor de una losa maciza que tendría la misma capacidad estructural que la losa nervada Atex.

### 1.1 Desde Datos del Producto
```
Heq = heq_mm (desde base de datos del producto)
```
Cuando está disponible, utiliza el valor exacto de Heq de las especificaciones del producto Atex.

### 1.2 Fórmula de Estimación
```
Heq = D × factor

Donde:
- D = Altura total de la losa (mm)
- factor = Factor de eficiencia basado en direccionalidad y tipo

Factores:
- Bidireccional: 0.60
- Unidireccional: 0.55
- Post-tensado: +0.08 adicional
```

**Ejemplo:**
- Atex 600 D20 (altura 200mm, bidireccional): Heq = 200 × 0.60 = 120mm
- Post-tensado: Heq = 200 × (0.60 + 0.08) = 136mm

## 2. Heq Requerido por Dimensiones de Luz

Valida si el producto puede manejar las dimensiones de luz especificadas usando relaciones L/Heq.

### 2.1 Fórmula de Heq Requerido
```
Heq_requerido = L_max / relación

Donde:
- L_max = Dimensión máxima de luz (m)
- relación = Relación L/Heq de la base de datos (adimensional)

Convertir a mm: Heq_requerido_mm = Heq_requerido_m × 1000
```

### 2.2 Verificación de Suficiencia Geométrica
```
Suficiente = Heq_actual ≥ Heq_requerido × tolerancia

Donde:
- tolerancia = 0.95 (permite 5% de margen)
```

**Ejemplo:**
- Luz: 6m × 8m, L_max = 8m
- Relación para bidireccional convencional = 25
- Heq_requerido = 8000mm / 25 = 320mm
- Si Heq del producto = 300mm: 300 ≥ 320 × 0.95 = 304mm → INSUFICIENTE

## 3. Cálculos de Volumen de Concreto

### 3.1 Volumen de Losa Maciza
```
Volumen_maciza = Heq / 1000

Unidades: m³/m² (metros cúbicos por metro cuadrado)
```

### 3.2 Volumen de Losa Atex

#### Desde Metadatos JSON (Preferido)
```
Volumen_atex = volumen_desde_metadatos_json

Extraído de tablas JSON del producto usando coincidencia de altura
```

#### Fórmula de Estimación (Respaldo)
```
Volumen_atex = Volumen_maciza × factor_eficiencia

Donde factor_eficiencia depende de la altura:
- ≤ 150mm: 0.585 (~41.5% ahorro)
- ≤ 200mm: 0.685 (~31.5% ahorro)  
- ≤ 230mm: 0.679 (~32.1% ahorro)
- ≤ 300mm: 0.70  (~30% ahorro)
- > 300mm: 0.65  (~35% ahorro)

Ajustes:
- Post-tensado: -0.05
- Casetonada: -0.02
- Bidireccional: -0.01

Rango final: 0.55 a 0.75
```

### 3.3 Ahorro de Concreto
```
Ahorro_concreto_% = (1 - Volumen_atex/Volumen_maciza) × 100
```

**Ejemplo:**
- Heq = 127mm → Volumen_maciza = 0.127 m³/m²
- Atex D20 → Volumen_atex = 0.087 m³/m² (del catálogo)
- Ahorro = (1 - 0.087/0.127) × 100 = 31.5%

## 4. Cálculos de Acero

### 4.1 Acero de Losa Maciza
```
Acero_maciza = Heq_mm × factor_k

Donde:
- factor_k = 0.8 kg/m² por mm de espesor
```

### 4.2 Acero de Losa Atex
```
Acero_atex = Acero_maciza × factor_acero

Donde factor_acero depende del tipo de producto:

Factores base:
- Regular: 0.50 (50% del acero de losa maciza)
- Post-tensado: 0.35 (35% del acero de losa maciza)
- Casetonada: 0.48 (48% del acero de losa maciza)

Ajustes por Heq:
- Heq ≥ 300mm: -0.05 (mayor eficiencia)
- Heq ≥ 200mm: -0.02 (eficiencia moderada)
- Heq ≤ 100mm: +0.08 (menor eficiencia)

Rango final: 0.25 a 0.65 (25% a 65% del acero de losa maciza)
```

### 4.3 Ahorro de Acero
```
Ahorro_acero_% = (1 - Acero_atex/Acero_maciza) × 100
```

**Ejemplo:**
- Heq = 127mm → Acero_maciza = 127 × 0.8 = 101.6 kg/m²
- Producto regular → factor_acero = 0.50 - 0.02 = 0.48
- Acero_atex = 101.6 × 0.48 = 48.8 kg/m²
- Ahorro = (1 - 48.8/101.6) × 100 = 52%

## 5. Clasificación de Productos

Los productos se clasifican según su rendimiento:

### 5.1 Reglas de Clasificación
```
SI no es geométricamente_suficiente:
    estado = "insuficiente"
SI NO SI ahorro_concreto ≥ 15% Y ahorro_acero ≥ 10%:
    estado = "ok"
SI NO SI ahorro_concreto ≥ 8% Y ahorro_acero ≥ 5%:
    estado = "ajustada"
SI NO:
    estado = "insuficiente"
```

## 6. Cálculos de Carga

### 6.1 Fuentes de Carga Viva
```
Carga_viva = uso_predefinido O valor_personalizado

Unidades: kN/m²
```

### 6.2 Porcentaje de Carga de Losa
```
Carga_efectiva = Carga_viva × (porcentaje_losa / 100)

Donde:
- porcentaje_losa = Entrada del usuario (típicamente 100%)
```

## 7. Ejemplos de Validación del Catálogo Atex 2025

### Ejemplo 1: Atex 600 D20
- **Altura:** 200mm
- **Heq (catálogo):** 127mm (factor 0.635)
- **Volumen de concreto:** 0.087 m³/m² (Atex) vs 0.127 m³/m² (maciza)
- **Ahorro de concreto:** 31.5%
- **Factor de acero:** ~0.50 → Ahorro de acero: ~50%

### Ejemplo 2: Atex 600 D23  
- **Altura:** 230mm
- **Heq (catálogo):** 156mm (factor 0.678)
- **Volumen de concreto:** 0.106 m³/m² (Atex) vs 0.156 m³/m² (maciza)
- **Ahorro de concreto:** 32.1%
- **Factor de acero:** ~0.48 → Ahorro de acero: ~52%

### Ejemplo 3: Atex 610 D15
- **Altura:** 150mm  
- **Heq (catálogo):** 111mm (factor 0.740)
- **Volumen de concreto:** ~0.065 m³/m² (Atex) vs 0.111 m³/m² (maciza)
- **Ahorro de concreto:** 41.4%
- **Factor de acero:** ~0.45 → Ahorro de acero: ~55%

## 8. Notas de Implementación

### 8.1 Análisis de Metadatos JSON
El sistema intenta extraer datos exactos de volumen de los metadatos JSON del producto:
```php
// Buscar coincidencia de altura en tabla JSON
foreach ($table as $row) {
    if (abs($row["Altura \nTotal"] - $target_height) < 0.1) {
        return $row["Volume\nde \nConcreto"];
    }
}
```

### 8.2 Precisión y Redondeo
- **Heq:** Redondeado a 1 decimal
- **Volúmenes:** Redondeado a 4 decimales  
- **Porcentajes de ahorro:** Redondeado a 1 decimal
- **Cantidades de acero:** Redondeado a 1 decimal

### 8.3 Manejo de Errores
- Parámetros faltantes devuelven mensajes de error apropiados
- Productos inválidos devuelven estado 404
- Errores de cálculo se registran con mensajes detallados
- Fórmulas de respaldo se usan cuando datos JSON no están disponibles

## 9. Dependencias de Base de Datos

### Tablas Requeridas:
- **productos:** Especificaciones de productos (heq_mm, altura_mm, familia, etc.)
- **ratios:** Relaciones L/Heq por tipo de producto y direccionalidad  
- **usos:** Tipos de uso predefinidos con cargas vivas
- **paises:** Disponibilidad de productos específicos por país

### Campos Clave:
- `heq_mm`: Altura equivalente en milímetros
- `altura_mm`: Altura total del producto en milímetros
- `familia`: Familia del producto (convencional, post, casetonada)
- `direccionalidad`: Dirección estructural (bi, uni)
- `metadata_json`: Tablas de especificaciones del producto en formato JSON

---

*Esta documentación está basada en el catálogo Atex 2025 y validada contra 337 variantes de productos importados.*
