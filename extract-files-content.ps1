$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition
$desktopPath = [Environment]::GetFolderPath("Desktop")
$randomFolder = "extracao_" + ([guid]::NewGuid().ToString().Substring(0, 8))
$outputDir = Join-Path $desktopPath $randomFolder
New-Item -ItemType Directory -Path $outputDir | Out-Null

$outputFile = Join-Path $outputDir "conteudoExtraido.md"
$validExtensions = @(".php", ".json", ".md", ".env", ".example", ".gitignore", ".prettierrc", ".Dockerfile", ".txt", "")

Get-ChildItem -Path $projectRoot -Recurse -File | ForEach-Object {
    $ext = $_.Extension
    if ($validExtensions -contains $ext -or $_.Name -eq "Dockerfile") {
        $fileName = $_.Name

        try {
            $fileContent = Get-Content -Path $_.FullName -Raw
        } catch {
            $fileContent = "[ERRO AO LER O ARQUIVO]"
        }

        Add-Content -Path $outputFile -Value "### $fileName"
        Add-Content -Path $outputFile -Value '```'
        Add-Content -Path $outputFile -Value $fileContent
        Add-Content -Path $outputFile -Value '```'
        Add-Content -Path $outputFile -Value ''
    }
}

Write-Host "`n✅ Arquivo Markdown gerado com sucesso em:"
Write-Host $outputFile
