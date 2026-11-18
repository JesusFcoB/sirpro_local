<?php
declare(strict_types=1);

/** Celda de Excel: "texto" se escapa, "numero"/"moneda" se exportan como valores numericos reales. */
function excelCelda(mixed $valor, string $tipo = 'texto'): array
{
    return ['v' => $valor, 't' => $tipo];
}

/**
 * Exporta un informe como HTML con extension .xls (lo abre Excel con formato).
 * Incluye banda de titulo, metadatos, anchos de columna, filas alternadas,
 * formatos numericos y un resumen final.
 */
function exportarExcel(
    string $archivo,
    string $titulo,
    array $meta,
    array $columnas,
    array $filas,
    array $anchos = [],
    array $resumen = [],
    ?string $pie = null
): never {
    $nombre = preg_replace('/[^A-Za-z0-9_-]/', '_', $archivo) ?: 'reporte';

    // Logo incrustado para que se vea tambien al abrir el .xls fuera del navegador
    $logoArchivo = dirname(__DIR__) . '/assets/logo.png';
    $logo = is_file($logoArchivo)
        ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoArchivo))
        : '';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $css = <<<'CSS'
    <style>
      body{font-family:'Segoe UI',Arial,sans-serif;font-size:10pt;color:#1e293b;margin:0;}
      .cabecera{border-bottom:3px solid #1d4ed8;padding-bottom:10px;margin-bottom:12px;}
      .cabecera table{width:100%;}
      .cabecera td{border:0;padding:0;vertical-align:middle;}
      .cabecera img{height:52px;}
      .cabecera h1{margin:0;font-size:16pt;color:#0f1f4d;letter-spacing:-.3px;}
      .cabecera p{margin:3px 0 0;color:#64748b;font-size:9pt;}
      .meta{margin:14px 0 10px;color:#475569;font-size:9pt;}
      .meta span{margin-right:26px;white-space:nowrap;}
      .meta strong{color:#0f1f4d;}
      table{border-collapse:collapse;width:100%;}
      th{background:#1d4ed8;color:#ffffff;border:1px solid #1d4ed8;padding:8px;text-align:left;font-size:9pt;}
      td{border:1px solid #d6dde8;padding:6px 8px;vertical-align:top;}
      tr:nth-child(even) td{background:#f8fafc;}
      td.num{text-align:right;mso-number-format:"#,##0";}
      td.mon{text-align:right;mso-number-format:"\$#,##0.00";}
      .resumen{margin-top:14px;display:flex;justify-content:flex-end;gap:16px;flex-wrap:wrap;}
      .resumen div{background:#eff6ff;border:2px solid #1d4ed8;border-radius:6px;padding:8px 14px;color:#0f1f4d;font-weight:bold;font-size:10pt;}
      .pie{margin-top:18px;padding-top:6px;border-top:1px solid #cbd5e1;color:#64748b;font-size:8pt;}
    </style>
    CSS;

    echo "\xEF\xBB\xBF";
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    echo '<x:Name>' . e($titulo) . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/>';
    echo '</x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo $css . '</head><body>';

    echo '<div class="cabecera"><table><tr>';
    if ($logo !== '') {
        echo '<td style="width:230px;"><img src="' . $logo . '" width="230" height="50" alt="Sirpro"></td>';
    }
    echo '<td><h1>' . e($titulo) . '</h1><p>Sirpro · Soluciones Profesionales</p></td>';
    echo '</tr></table></div>';

    if ($meta !== []) {
        echo '<div class="meta">';
        foreach ($meta as $etiqueta => $valor) {
            echo '<span><strong>' . e($etiqueta) . ':</strong> ' . e($valor) . '</span>';
        }
        echo '</div>';
    }

    echo '<table>';
    if ($anchos !== []) {
        echo '<colgroup>';
        foreach ($anchos as $ancho) {
            echo '<col style="width:' . (int) $ancho . 'px">';
        }
        echo '</colgroup>';
    }
    echo '<thead><tr>';
    foreach ($columnas as $columna) {
        echo '<th>' . e($columna) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($filas as $fila) {
        echo '<tr>';
        foreach ($fila as $celda) {
            $valor = $celda['v'] ?? '';
            $tipo = $celda['t'] ?? 'texto';
            if ($tipo === 'moneda') {
                echo '<td class="mon">' . number_format((float) $valor, 2, '.', '') . '</td>';
            } elseif ($tipo === 'numero') {
                echo '<td class="num">' . (int) $valor . '</td>';
            } else {
                echo '<td>' . e($valor) . '</td>';
            }
        }
        echo '</tr>';
    }
    echo '</tbody></table>';

    if ($resumen !== []) {
        echo '<div class="resumen">';
        foreach ($resumen as $item) {
            echo '<div>' . e($item[0]) . ': ' . e($item[1]) . '</div>';
        }
        echo '</div>';
    }

    echo '<div class="pie">' . e($pie ?? 'Documento generado por Sirpro · Punto de venta') . '</div>';
    echo '</body></html>';
    exit;
}
