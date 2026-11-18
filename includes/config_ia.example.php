<?php
declare(strict_types=1);

/**
 * EJEMPLO DE CONFIGURACIÓN DEL ASISTENTE IA
 * Copiá este archivo como config_ia.php y rellená tu clave de Google AI Studio.
 * Nunca subas config_ia.php al repositorio (ya está en .gitignore).
 */
return [
    // Proveedor de IA. Por ahora soporta 'gemini'.
    'proveedor' => 'gemini',

    // API key de Google AI Studio (https://aistudio.google.com/apikey)
    'clave' => 'TU_API_KEY_AQUI',

    // Modelo principal. 3.5 Flash-Lite es más ligero y estable que 3.8 Flash.
    'modelo' => 'gemini-3.5-flash-lite',

    // Si el principal está saturado o falla, se prueba con estos modelos en orden.
    'modelos_alternativos' => ['gemini-3.8-flash'],

    // Segundos de espera máxima por respuesta, por modelo.
    'timeout' => 35,

    // Si es false, el modo "Analizar negocio" no envía cifras de ventas/inventario.
    'incluir_datos' => true,

    // Límites por usuario y sesión (protegen tu clave de Gemini).
    'limite_por_minuto' => 8,
    'limite_por_hora'   => 40,
];
