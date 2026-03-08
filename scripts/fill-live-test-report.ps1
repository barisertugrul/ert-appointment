param(
    [Parameter(Mandatory = $true)]
    [string]$JsonReport,

    [string]$TemplateFile = ".\docs\live-booking-mode-test-report.md",
    [string]$OutputFile = ".\test-results\live-booking-mode-report-$(Get-Date -Format 'yyyyMMdd-HHmmss').md"
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $JsonReport)) {
    throw "Json report not found: $JsonReport"
}

if (-not (Test-Path -LiteralPath $TemplateFile)) {
    throw "Template file not found: $TemplateFile"
}

$raw = Get-Content -LiteralPath $JsonReport -Raw -Encoding UTF8
$report = $raw | ConvertFrom-Json

if ($null -eq $report.summary -or $null -eq $report.cases) {
    throw "Invalid report format. Expected summary and cases fields."
}

$casesById = @{}
foreach ($case in $report.cases) {
    if ($null -ne $case.id -and "$($case.id)" -ne '') {
        $casesById["$($case.id)"] = $case
    }
}

function Convert-ResultToMark {
    param([string]$Result)

    $normalized = ''
    if ($null -ne $Result) {
        $normalized = "$Result"
    }

    switch ($normalized.ToLowerInvariant()) {
        'pass' { return 'PASS' }
        'fail' { return 'FAIL' }
        'skip' { return 'SKIP' }
        default { return 'N/A' }
    }
}

function Escape-CellText {
    param([string]$Text)

    if ($null -eq $Text) {
        return ''
    }

    $clean = $Text.Replace("`r", ' ').Replace("`n", ' ').Trim()
    return $clean.Replace('|', '/')
}

$lines = Get-Content -LiteralPath $TemplateFile -Encoding UTF8
$outLines = New-Object System.Collections.Generic.List[string]

$generatedAt = ''
if ($report.summary.generatedAt) {
    $generatedAt = "$($report.summary.generatedAt)"
}

$tester = if ($report.summary.tester) { "$($report.summary.tester)" } else { '' }
$environment = if ($report.summary.environment) { "$($report.summary.environment)" } else { '' }
$passCount = if ($null -ne $report.summary.pass) { [int]$report.summary.pass } else { 0 }
$failCount = if ($null -ne $report.summary.fail) { [int]$report.summary.fail } else { 0 }
$skipCount = if ($null -ne $report.summary.skip) { [int]$report.summary.skip } else { 0 }
$totalCount = if ($null -ne $report.summary.total) { [int]$report.summary.total } else { 0 }
$overall = if ($failCount -eq 0) { 'PASS' } else { 'FAIL' }

foreach ($line in $lines) {
    $current = $line

    if ($current -match '^\|\s*([^|]+?)\s*\|') {
        $id = "$($Matches[1])".Trim()

        if ($id -ne 'ID' -and $casesById.ContainsKey($id)) {
            $case = $casesById[$id]
            $mark = Convert-ResultToMark -Result "$($case.result)"
            $note = Escape-CellText -Text "$($case.note)"

            $cells = $current.Split('|')
            if ($cells.Count -ge 7) {
                $cells[4] = " $mark "
                $cells[5] = " $note "
                $current = ($cells -join '|')
            }
        }
    }

    $current = $current -replace '^- \*\*Tarih:\*\* .*$', "- **Tarih:** $generatedAt"
    $current = $current -replace '^- \*\*Test Eden:\*\* .*$', "- **Test Eden:** $tester"
    $current = $current -replace '^- \*\*Ortam:\*\* .*$', "- **Ortam:** $environment"
    $current = $current -replace '^- \*\*Toplam Test:\*\* .*$', "- **Toplam Test:** $totalCount"
    $current = $current -replace '^- \*\*Pass:\*\* .*$', "- **Pass:** $passCount"
    $current = $current -replace '^- \*\*Fail:\*\* .*$', "- **Fail:** $failCount"
    $current = $current -replace '^- \*\*Skip:\*\* .*$', "- **Skip:** $skipCount"
    $current = $current -replace '^- \*\*Genel Durum:\*\* .*$', "- **Genel Durum:** $overall"

    $outLines.Add($current)
}

$outputDir = Split-Path -Parent $OutputFile
if (-not [string]::IsNullOrWhiteSpace($outputDir) -and -not (Test-Path -LiteralPath $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir | Out-Null
}

$outLines | Set-Content -LiteralPath $OutputFile -Encoding UTF8

Write-Host "Report generated: $OutputFile" -ForegroundColor Green
