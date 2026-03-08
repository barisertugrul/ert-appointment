param(
    [string]$ChecklistPath = "docs/wporg-assets-master-checklist.md",
    [switch]$OpenFile,
    [switch]$OpenInCode,
    [string]$OpenHeading,
    [switch]$ListSections,
    [ValidateSet('progress','status','today','tomorrow','s1','s2','s3','s4','s5','s6','final')]
    [string]$OpenSection
)

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$targetPath = if ([System.IO.Path]::IsPathRooted($ChecklistPath)) {
    $ChecklistPath
} else {
    Join-Path $repoRoot $ChecklistPath
}

if (-not (Test-Path $targetPath)) {
    throw "Checklist file not found: $targetPath"
}

$sectionHeadingMap = [ordered]@{
    'progress' = '## Genel İlerleme'
    'status'   = '## Durum Özeti'
    'today'    = '## Bugün Hedefi'
    'tomorrow' = '## Yarın Hedefi'
    's1'       = '## Sprint 1'
    's2'       = '## Sprint 2'
    's3'       = '## Sprint 3'
    's4'       = '## Sprint 4'
    's5'       = '## Sprint 5'
    's6'       = '## Sprint 6'
    'final'    = '## Final Teslim Listesi'
}

if ($ListSections) {
    Write-Host 'OpenSection aliases:'
    foreach ($item in $sectionHeadingMap.GetEnumerator()) {
        Write-Host ("- {0} => {1}" -f $item.Key, $item.Value)
    }

    if (-not $OpenInCode -and -not $OpenFile) {
        return
    }
}

$content = Get-Content -Path $targetPath -Raw -Encoding UTF8

$statusSectionMatch = [regex]::Match($content, '(?sm)^## Durum.*?(?=^## |\z)')
if (-not $statusSectionMatch.Success) {
    throw "Status section starting with '## Durum' not found in $targetPath"
}

$statusSection = $statusSectionMatch.Value
$sprintMatches = [regex]::Matches($statusSection, '(?m)^- \[(?<mark> |x|X)\] Sprint (?<num>[1-6])\b')

if ($sprintMatches.Count -ne 6) {
    throw "Expected 6 exact sprint status lines, found $($sprintMatches.Count)."
}

$completed = 0
foreach ($match in $sprintMatches) {
    if ($match.Groups['mark'].Value -match 'x|X') {
        $completed++
    }
}

$percent = [int][math]::Round(($completed / 6) * 100, 0)
$today = Get-Date -Format 'yyyy-MM-dd'

$newStatusLine = '- Durum: `%' + $percent + ' tamamlandı`'
if ($content -notmatch '(?m)^- Durum: `%.+`\s*$') {
    throw "Status line not found or unexpected format in $targetPath"
}
if ($content -notmatch '(?m)^- Son g.ncelleme: `\d{4}-\d{2}-\d{2}`\s*$') {
    throw "Date line not found or unexpected format in $targetPath"
}

$content = [regex]::Replace($content, '(?m)^- Durum: `%.+`\s*$', [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $newStatusLine }, 1)
$content = [regex]::Replace(
    $content,
    '(?m)^- Son g.ncelleme: `\d{4}-\d{2}-\d{2}`\s*$',
    [System.Text.RegularExpressions.MatchEvaluator]{
        param($m)
        [regex]::Replace($m.Value, '`\d{4}-\d{2}-\d{2}`', '`' + $today + '`')
    },
    1
)

Set-Content -Path $targetPath -Value $content -Encoding UTF8

Write-Host "Updated progress: $completed/6 sprint(s) -> %$percent"
Write-Host "File: $targetPath"

if ($OpenInCode) {
    try {
        $codeCmd = Get-Command code -ErrorAction SilentlyContinue
        if ($null -ne $codeCmd) {
            $targetLine = 1

            $resolvedHeading = $OpenHeading
            if ([string]::IsNullOrWhiteSpace($resolvedHeading) -and -not [string]::IsNullOrWhiteSpace($OpenSection)) {
                $resolvedHeading = $sectionHeadingMap[$OpenSection]
            }

            if (-not [string]::IsNullOrWhiteSpace($resolvedHeading)) {
                $lines = $content -split "`r?`n"
                for ($index = 0; $index -lt $lines.Length; $index++) {
                    if ($lines[$index].IndexOf($resolvedHeading, [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
                        $targetLine = $index + 1
                        break
                    }
                }

                if ($targetLine -eq 1) {
                    Write-Warning "Heading not found: '$resolvedHeading'. Opened file at line 1."
                }
            }

            & code -r -g "$targetPath`:$targetLine"
            Write-Host "Opened in VS Code: $targetPath (line $targetLine)"
        }
        else {
            Write-Warning "'code' command not found. Install/enable VS Code shell command, or use -OpenFile."
        }
    }
    catch {
        Write-Warning "File updated but could not be opened in VS Code: $($_.Exception.Message)"
    }
}

if ($OpenFile) {
    try {
        Invoke-Item -Path $targetPath
        Write-Host "Opened file: $targetPath"
    }
    catch {
        Write-Warning "File updated but could not be opened automatically: $($_.Exception.Message)"
    }
}
