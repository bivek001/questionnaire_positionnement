param([string]$PhpPath = 'php')
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$failures=@()
$files=Get-ChildItem -LiteralPath $projectRoot -Recurse -File -Filter '*.php' | Where-Object {
    $relative=$_.FullName.Substring($projectRoot.Length+1).Replace('\','/')
    !$relative.StartsWith('vendor/') -and !$relative.StartsWith('database/backups/')
}
foreach($file in $files){
    $result=& $PhpPath -l $file.FullName 2>&1
    if($LASTEXITCODE -ne 0){$failures+=$result}
}
$links=Get-ChildItem -LiteralPath $projectRoot -Recurse -Force | Where-Object {($_.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0}
$required=@('config/database.php','vendor/autoload.php','admin/login.php','professor/login.php','trainee_login.php','database/exports/questionnaire_db.sql')
foreach($path in $required){if(!(Test-Path -LiteralPath (Join-Path $projectRoot $path))){$failures+="Missing: $path"}}
if($links){$failures+='External/symbolic filesystem links found. Review them.'}
if($failures){$failures;exit 1}
Write-Output "PASS: $($files.Count) application PHP syntax checks, bundled dependencies, required files and absence of filesystem links."
Write-Output 'These checks do not replace role-based browser tests or database compatibility checks.'
