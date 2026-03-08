param(
    [string]$OutputFile = ".\test-results\scope-override-$(Get-Date -Format 'yyyyMMdd-HHmmss').json",
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

Write-Host "ERT Appointment Manual Scope/Override Test Script" -ForegroundColor Yellow
Write-Host "Bu script manuel adımları tek tek sorar ve JSON raporu üretir.`n"

if ([string]::IsNullOrWhiteSpace($Tester)) {
    $Tester = Read-Host "Tester adı"
}

if ([string]::IsNullOrWhiteSpace($Environment)) {
    $Environment = Read-Host "Ortam (local/stage/prod vb.)"
}

$cases = @()

# Persist checks
$cases += Ask-Result -Id 'PERSIST-001' -Title 'Global auto_confirm persist' `
    -Instruction 'Admin > Settings > Scope=Global. Auto-confirm ON yap, Kaydet, sayfayı yenile.' `
    -Expected 'Auto-confirm toggle ON kalmalı.'

$cases += Ask-Result -Id 'PERSIST-002' -Title 'Global allow_general_booking persist' `
    -Instruction 'Allow General Booking ON yap, Kaydet, sayfayı yenile.' `
    -Expected 'Allow General Booking ON kalmalı.'

$cases += Ask-Result -Id 'PERSIST-003' -Title 'Global arrival_reminder persist' `
    -Instruction 'Arrival Reminder ON yap, Kaydet, sayfayı yenile.' `
    -Expected 'Arrival Reminder ON kalmalı.'

# Override checks
$cases += Ask-Result -Id 'OVR-001' -Title 'Provider date range override' `
    -Instruction 'Provider P1 için booking start/end ayarla. Frontendde P1 seç.' `
    -Expected 'Takvim, provider tarih aralığına göre sınırlandırılmalı.'

$cases += Ask-Result -Id 'OVR-002' -Title 'Provider slot duration override' `
    -Instruction 'Provider P1 için slot duration globalden farklı ayarla, frontendde P1 seç.' `
    -Expected 'Slot üretimi provider duration değerini kullanmalı.'

$cases += Ask-Result -Id 'OVR-003' -Title 'Provider buffer_after override' `
    -Instruction 'Provider P1 için buffer_after farklı ayarla ve slot başlangıçlarını kontrol et.' `
    -Expected 'Ardışık slotlar duration+buffer_after kuralına göre ilerlemeli.'

$cases += Ask-Result -Id 'OVR-004' -Title 'Department fallback' `
    -Instruction 'Providerda olmayan ayarı departmentta set et, provider seçip frontend kontrol et.' `
    -Expected 'Providerda yoksa department değeri uygulanmalı.'

$cases += Ask-Result -Id 'OVR-005' -Title 'Global fallback' `
    -Instruction 'Provider ve departmentta olmayan ayarı globalde set et, frontend kontrol et.' `
    -Expected 'Sadece globalde varsa global değeri uygulanmalı.'

# Arrival note and break math
$cases += Ask-Result -Id 'ARR-001' -Title 'Arrival note visibility' `
    -Instruction 'arrival_buffer > 0 iken booking tamamla (location dolu/boş iki senaryo).' `
    -Expected 'Success ekranında arrival notu her iki senaryoda doğru metinle görünmeli.'

$cases += Ask-Result -Id 'SLOT-001' -Title 'Break overlap suppression' `
    -Instruction 'Break aralığı tanımla ve slot listesinde çakışan saatleri kontrol et.' `
    -Expected 'Break ile çakışan slotlar listelenmemeli.'

# Gutenberg regression
$cases += Ask-Result -Id 'GBLK-001' -Title 'Gutenberg block mount' `
    -Instruction 'Sayfaya Gutenberg booking block ekle, frontend aç ve widget yüklenmesini izle.' `
    -Expected '"Rezervasyon formu yükleniyor..." placeholder kaybolup widget açılmalı.'

$cases += Ask-Result -Id 'GBLK-002' -Title 'Multiple booking hosts in page' `
    -Instruction 'Aynı sayfada birden fazla booking host (blok/shortcode) ile test et.' `
    -Expected 'Her host kendi içinde mount olmalı, sonsuz loading olmamalı.'

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
