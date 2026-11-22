param(
    [Parameter(Mandatory)]
    [string] $Runtime,
    [int] $WebPort = 8087,
    [int] $DbPort = 3318,
    [string] $Xampp = 'C:\xampp'
)
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path -LiteralPath $Runtime).Path
$source = Split-Path -Parent $PSScriptRoot
$wordpress = Join-Path $root 'wordpress'
$data = Join-Path $root 'mariadb'
$mysql = Join-Path $Xampp 'mysql\bin\mysqld.exe'
$php = Join-Path $Xampp 'php\php.exe'

foreach ($item in @($mysql, $php, "$wordpress/wp-config.php", "$data/mysql")) {
    if (-not (Test-Path -LiteralPath $item)) { throw "Missing prerequisite: $item" }
}
function Start-LocalProcess($Program, $Arguments, $Port, $Log, $Marker) {
    $active = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if ($active) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($active.OwningProcess)"
        if ($process.ExecutablePath -ne $Program -or -not $process.CommandLine.Replace('\','/').Contains($Marker.Replace('\','/'))) {
            throw "Unexpected process is listening on port $Port"
        }
        return
    }
    $procArgs = @{
        FilePath = $Program
        ArgumentList = $Arguments
        WindowStyle = 'Hidden'
        WorkingDirectory = $wordpress
        RedirectStandardOutput = "$root/$Log.out.log"
        RedirectStandardError = "$root/$Log.err.log"
    }
    Start-Process @procArgs | Out-Null
}

Start-LocalProcess $mysql @(
    '--no-defaults', ('"--datadir=' + $data + '"'), "--port=$DbPort", '--bind-address=127.0.0.1',
    '--character-set-server=utf8mb4', '--console'
) $DbPort 'mariadb' $data
$phpOptions = @('-d', 'extension=gd', '-d', 'memory_limit=512M')
if (Test-Path -LiteralPath (Join-Path $Xampp 'php\ext\php_opcache.dll')) {
    $phpOptions += @('-d', 'zend_extension=opcache', '-d', 'opcache.enable_cli=1')
}
Start-LocalProcess $php ($phpOptions + @(
    '-S', "127.0.0.1:$WebPort",
    '-t', ('"' + $wordpress + '"'), ('"' + (Join-Path $source 'scripts\router.php') + '"')
)) $WebPort 'php' $wordpress
Write-Host "Storefront  http://127.0.0.1:$WebPort/"
Write-Host "WordPress   http://127.0.0.1:$WebPort/wp-admin/"
