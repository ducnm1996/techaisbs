$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$php = Join-Path $projectRoot '.runtime\php\php.exe'
$mysqlBase = Join-Path $projectRoot '.runtime\mysql\PFiles64\MySQL\MySQL Server 8.4'
$mysqlData = Join-Path $projectRoot '.runtime\mysql-data'
if (!(Test-Path -LiteralPath $php)) { throw 'Cần cài PHP trước. Xem README.md.' }
if (!(Get-NetTCPConnection -State Listen -LocalPort 3307 -ErrorAction SilentlyContinue)) {
    $mysqlArguments = '--no-defaults --basedir="' + $mysqlBase + '" --datadir="' + $mysqlData + '" --bind-address=127.0.0.1 --port=3307 --mysqlx=0'
    Start-Process -FilePath (Join-Path $mysqlBase 'bin\mysqld.exe') -ArgumentList $mysqlArguments -WindowStyle Hidden -WorkingDirectory $projectRoot | Out-Null
    $ready = $false
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        if (Get-NetTCPConnection -State Listen -LocalPort 3307 -ErrorAction SilentlyContinue) {
            $ready = $true
            break
        }
        Start-Sleep -Milliseconds 500
    }
    if (!$ready) { throw 'MySQL chưa sẵn sàng. Kiểm tra log trong .runtime/mysql-data.' }
}
$env:Path = (Split-Path $php) + ';' + $env:Path
Push-Location (Join-Path $projectRoot 'app')
try { & $php artisan serve --env=local --host=127.0.0.1 --port=8000 --no-reload }
finally { Pop-Location }
