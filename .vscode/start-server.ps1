$ErrorActionPreference = 'Stop'

$phpExecutable = 'C:\wamp64\bin\php\php8.3.28\php.exe'
$publicDirectory = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\public'))
$routerFile = Join-Path $publicDirectory 'router.php'

if (!(Test-Path -LiteralPath $phpExecutable -PathType Leaf)) {
    Write-Error "WAMP PHP 8.3.28 was not found at $phpExecutable."
    exit 1
}

$listener = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1

if ($null -ne $listener) {
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($listener.OwningProcess)"
    $expectedCommand = $process.ExecutablePath -eq $phpExecutable `
        -and $process.CommandLine -match '(?i)-S\s+127\.0\.0\.1:8000'

    if ($expectedCommand) {
        Write-Output 'Vision Studio AI server already running on http://127.0.0.1:8000.'
        exit 0
    }

    Write-Error "Port 8000 is occupied by PID $($listener.OwningProcess); no duplicate server was started."
    exit 1
}

Write-Output 'Launching Vision Studio AI development server on http://127.0.0.1:8000 with WAMP PHP 8.3.28.'
& $phpExecutable -S 127.0.0.1:8000 -t $publicDirectory $routerFile
exit $LASTEXITCODE
