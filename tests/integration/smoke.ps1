param(
    [string]$BaseUrl = "http://localhost:8080"
)

$ErrorActionPreference = "Stop"

function Get-CsrfToken {
    param([string]$Html)

    $match = [regex]::Match($Html, 'name="_csrf_token" value="([^"]+)"')
    if (-not $match.Success) {
        throw "CSRF token not found"
    }

    return $match.Groups[1].Value
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$login = Invoke-WebRequest -Uri "$BaseUrl/login" -WebSession $session -UseBasicParsing
$token = Get-CsrfToken $login.Content

Invoke-WebRequest `
    -Uri "$BaseUrl/login" `
    -Method Post `
    -WebSession $session `
    -Body @{ email = "user@wdpai.com"; password = "wdpai123"; _csrf_token = $token } `
    -UseBasicParsing | Out-Null

foreach ($path in @("/dashboard", "/planer", "/session", "/atlas", "/history")) {
    $response = Invoke-WebRequest -Uri "$BaseUrl$path" -WebSession $session -UseBasicParsing
    if ($response.StatusCode -ne 200) {
        throw "$path returned $($response.StatusCode)"
    }
}

try {
    Invoke-WebRequest -Uri "$BaseUrl/admin/users" -WebSession $session -UseBasicParsing | Out-Null
    throw "User should not access admin panel"
} catch {
    if ([int]$_.Exception.Response.StatusCode -ne 403) {
        throw
    }
}

Write-Host "Smoke tests passed"
