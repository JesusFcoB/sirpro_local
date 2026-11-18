param(
    [int]$Php = 0,
    [int]$Puerto = 0,
    [string]$MysqlAdmin = '',
    [int]$MysqlIniciado = 0
)

# El servidor PHP se inicia dentro de la consola del lanzador, así que su
# proceso padre es el propio iniciar_sirpro.bat. Cuando ese proceso termina
# (tecla o X de la ventana), el vigilante apaga el PHP de Sirpro.
$procesoPhp = Get-CimInstance Win32_Process -Filter "ProcessId=$Php" -ErrorAction SilentlyContinue
if ($null -ne $procesoPhp) {
    $lanzador = [int]$procesoPhp.ParentProcessId
    if ($lanzador -gt 0) {
        try {
            $proceso = Get-Process -Id $lanzador -ErrorAction SilentlyContinue
            if ($null -ne $proceso) {
                $proceso.WaitForExit(86400000) > $null
            }
        } catch {
        }
    }
}

# Apaga solo el servidor PHP levantado por Sirpro en este puerto.
$ids = @()
if ($Php -gt 0) {
    $ids += $Php
}
try {
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object {
        $_.CommandLine -like "*-S 127.0.0.1:$Puerto*"
    } | ForEach-Object {
        $ids += [int]$_.ProcessId
    }
} catch {
}

foreach ($id in ($ids | Sort-Object -Unique)) {
    Stop-Process -Id $id -Force -ErrorAction SilentlyContinue
}

# MySQL se apaga con mysqladmin, nunca con una señal forzada.
if ($MysqlIniciado -eq 1 -and $MysqlAdmin -ne '' -and (Test-Path $MysqlAdmin)) {
    & $MysqlAdmin -u root shutdown > $null 2>&1
}

exit 0
