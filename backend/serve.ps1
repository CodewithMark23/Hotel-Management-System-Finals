param([string]$PhpPath = 'C:\xampp\php\php.exe')

if (-not (Test-Path -LiteralPath $PhpPath)) {
    throw "PHP executable not found: $PhpPath"
}

$hotelPreviousPhp = $env:PHP_BINARY
$env:PHP_BINARY = (Resolve-Path -LiteralPath $PhpPath).Path

try {
    & $env:PHP_BINARY "$PSScriptRoot\artisan" serve --host=127.0.0.1 --port=8000 --no-reload --tries=1
}
finally {
    $env:PHP_BINARY = $hotelPreviousPhp
}
