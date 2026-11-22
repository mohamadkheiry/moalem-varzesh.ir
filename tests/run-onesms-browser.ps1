param(
    [Parameter(Mandatory)]
    [string] $Runtime,
    [string] $Cases = '14,16'
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path -LiteralPath $Runtime).Path
$wordpress = Join-Path $root 'wordpress'
$wpCli = Join-Path $root 'cli.phar'
if (!(Test-Path -LiteralPath "$wordpress/wp-config.php") -or !(Test-Path -LiteralPath $wpCli)) {
    throw 'Runtime must contain wordpress and cli.phar.'
}
$fixture = Join-Path $root 'onesms-browser-fixture.json'
$loader = Join-Path $wordpress 'wp-content/mu-plugins/mv-onesms-browser-fixture.php'
if ((Test-Path -LiteralPath $fixture) -or (Test-Path -LiteralPath $loader)) {
    throw 'A OneSMS fixture already exists.'
}

$tag = [guid]::NewGuid().ToString('n')
$phone = '0900' + (Get-Random -Minimum 1000000 -Maximum 9999999)
$capture = Join-Path $root "onesms-qa-$tag.txt"
$orderCapture = "$capture.json"
$email = "checkout-$tag@example.test"
$settings = @{
    phone = $phone
    capture = $capture
    orderCapture = $orderCapture
    email = $email
    tag = $tag
    expires = [DateTimeOffset]::UtcNow.AddMinutes(12).ToUnixTimeSeconds()
}

$testEnv = @{
    MV_QA_SMS_FIXTURE = $fixture
    MV_TEST_CASES = $Cases
    MV_BROWSER_QA_PHONE = $phone
    MV_BROWSER_QA_EMAIL = $email
    MV_BROWSER_QA_TAG = $tag
}
$previous = @{}
foreach ($name in $testEnv.Keys) {
    $previous[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
    [Environment]::SetEnvironmentVariable($name, $testEnv[$name], 'Process')
}
$code = 1
try {
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $loader) | Out-Null
    [IO.File]::WriteAllText($fixture, ($settings | ConvertTo-Json -Compress), [Text.UTF8Encoding]::new($false))
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'onesms-browser-fixture.php') -Destination $loader
    node (Join-Path $PSScriptRoot 'storefront.cjs')
    $code = $LASTEXITCODE
} finally {
    foreach ($path in @($fixture, $loader, $capture, "$capture.failed", $orderCapture)) {
        if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path }
    }
    php $wpCli "--path=$wordpress" eval-file (Join-Path $PSScriptRoot 'cleanup-browser-commerce.php')
    $cleanup = $LASTEXITCODE
    foreach ($name in $previous.Keys) {
        [Environment]::SetEnvironmentVariable($name, $previous[$name], 'Process')
    }
    if ($cleanup -ne 0) { throw 'Could not clean browser test records.' }
}
exit $code
