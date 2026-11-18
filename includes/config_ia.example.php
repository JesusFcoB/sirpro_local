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

    // Modelo a usar. Podés cambiarlo por otro modelo Gemini disponible en tu cuenta.
    'modelo' => 'gemini-2.5-flash',

    // Segundos de espera máxima por respuesta.
    'timeout' => 45,

    // Si es false, el modo "Analizar negocio" no envía cifras de ventas/inventario.
    'incluir_datos' => true,
];
