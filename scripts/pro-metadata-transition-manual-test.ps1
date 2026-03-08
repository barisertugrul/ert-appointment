param(
    [string]$OutputFile = ".\test-results\pro-metadata-transition-$(Get-Date -Format 'yyyyMMdd-HHmmss').json",
    [string]$Tester = "",
    [string]$Environment = ""
)

$ErrorActionPreference = 'Stop'

function Ask-Result {
    param(
        [string]$Id,
        [string]$Title,
        [string]$Instruction,
        [string]$Expected
    )

    Write-Host "`n[$Id] $Title" -ForegroundColor Cyan
    Write-Host "Adım: $Instruction"
    Write-Host "Beklenen: $Expected" -ForegroundColor DarkGreen

    do {
        $value = Read-Host "Sonuç (p=pass, f=fail, s=skip)"
        $value = ($value ?? '').Trim().ToLowerInvariant()
    } while ($value -notin @('p','f','s'))

    $note = Read-Host "Not (opsiyonel)"

    [pscustomobject]@{
        id = $Id
        title = $Title
        result = switch ($value) {
            'p' { 'pass' }
            'f' { 'fail' }
            's' { 'skip' }
        }
        note = $note
        timestamp = (Get-Date).ToString('s')
    }
}

Write-Host "ERT Appointment Manual Pro-Metadata Transition Script" -ForegroundColor Yellow
Write-Host "Bu script 4 geçiş senaryosunu sorar ve JSON raporu üretir.`n"

if ([string]::IsNullOrWhiteSpace($Tester)) {
    $Tester = Read-Host "Tester adı"
}

if ([string]::IsNullOrWhiteSpace($Environment)) {
    $Environment = Read-Host "Ortam (local/stage/prod vb.)"
}

$cases = @()

# Scenario A: Lite only
$cases += Ask-Result -Id 'LITE-001' -Title 'Lite only form save/update' `
    -Instruction 'Pro kapalıyken global formu kaydet/güncelle.' `
    -Expected 'Form save/update sorunsuz olmalı.'

$cases += Ask-Result -Id 'LITE-002' -Title 'Lite only booking flow' `
    -Instruction 'Pro kapalıyken booking wizard ile randevu oluştur.' `
    -Expected 'Adımlar ve submit çalışmalı.'

$cases += Ask-Result -Id 'LITE-003' -Title 'Lite API response shape' `
    -Instruction 'Form endpoint çıktısını kontrol et.' `
    -Expected 'department_label/provider_label/ui_styles alanları response shape içinde kalmalı.'

# Scenario B: Pro active
$cases += Ask-Result -Id 'PRO-001' -Title 'Pro active label/style save' `
    -Instruction 'Pro açık ve lisans valid durumda label/style kaydet.' `
    -Expected 'Kayıt başarılı olmalı.'

$cases += Ask-Result -Id 'PRO-002' -Title 'Pro active frontend apply' `
    -Instruction 'Kaydedilen label/style ile booking frontendi aç.' `
    -Expected 'Label override + style apply görünmeli.'

$cases += Ask-Result -Id 'PRO-003' -Title 'Pro active flow safety' `
    -Instruction 'Booking + (varsa) payment/notification tetikle.' `
    -Expected 'Akışta regresyon olmamalı.'

# Scenario C: Pro off -> on
$cases += Ask-Result -Id 'TRON-001' -Title 'Transition OFF->ON old values read' `
    -Instruction 'Pro kapalıyken oluşmuş verilerle Proyu aç ve formu yükle.' `
    -Expected 'Eski metadata değerleri okunmalı.'

$cases += Ask-Result -Id 'TRON-002' -Title 'Transition OFF->ON persistence' `
    -Instruction 'Pro açıkken yeni değer kaydet, yenile.' `
    -Expected 'Yeni değerler korunmalı.'

$cases += Ask-Result -Id 'TRON-003' -Title 'Transition OFF->ON API/shortcode' `
    -Instruction 'API ve shortcode çıktısını kontrol et.' `
    -Expected 'Kontrat bozulmamalı.'

# Scenario D: Pro on -> off
$cases += Ask-Result -Id 'TROFF-001' -Title 'Transition ON->OFF UI gate' `
    -Instruction 'Proyu kapat, Form ekranında Pro alanlarını kontrol et.' `
    -Expected 'Pro alanları disabled/korumalı olmalı.'

$cases += Ask-Result -Id 'TROFF-002' -Title 'Transition ON->OFF fallback read' `
    -Instruction 'Pro kapalı durumda form yükleme + booking akışını test et.' `
    -Expected 'Lite fallback ile temel akış çalışmalı.'

$cases += Ask-Result -Id 'TROFF-003' -Title 'Transition ON->OFF booking safety' `
    -Instruction 'Randevu submit et ve sonucu kontrol et.' `
    -Expected 'Submit/appointment create sorunsuz olmalı.'

$summary = [pscustomobject]@{
    generatedAt = (Get-Date).ToString('s')
    tester = $Tester
    environment = $Environment
    total = $cases.Count
    pass = ($cases | Where-Object { $_.result -eq 'pass' }).Count
    fail = ($cases | Where-Object { $_.result -eq 'fail' }).Count
    skip = ($cases | Where-Object { $_.result -eq 'skip' }).Count
}

$report = [pscustomobject]@{
    summary = $summary
    cases = $cases
}

$outputDir = Split-Path -Parent $OutputFile
if (-not [string]::IsNullOrWhiteSpace($outputDir) -and -not (Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir | Out-Null
}

$report | ConvertTo-Json -Depth 5 | Set-Content -Path $OutputFile -Encoding UTF8

Write-Host "`nTest tamamlandı." -ForegroundColor Green
Write-Host "Rapor: $OutputFile"
Write-Host "Özet: Pass=$($summary.pass), Fail=$($summary.fail), Skip=$($summary.skip), Total=$($summary.total)"
