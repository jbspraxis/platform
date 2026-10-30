<#
  Builds the self-review pack (runbook §9): a still every 2 seconds of each video on 3×3 sheets
  (each still 640 px wide, the phone-preview size the checklist asks about), and the measured
  facts (lengths, sizes, loudness, true peaks) in measurements.txt.
#>
$ErrorActionPreference = 'Continue'   # ffmpeg reports on stderr; that is not a failure
$demo = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$out = Join-Path $demo 'work\review\pack'
New-Item -ItemType Directory -Force -Path $out | Out-Null
Get-ChildItem $out -Filter *.png | Remove-Item

foreach ($v in 'hero-60s-loop.mp4', 'demo-60s-en.mp4', 'demo-3min-en.mp4') {
  $name = [IO.Path]::GetFileNameWithoutExtension($v)
  ffmpeg -v error -y -i (Join-Path $demo $v) -vf "fps=0.5,scale=640:360:flags=lanczos,drawtext=fontfile='C\:/Windows/Fonts/arial.ttf':text='%{pts\:hms}':x=8:y=8:fontsize=20:fontcolor=white:box=1:boxcolor=black@0.6,tile=3x3" (Join-Path $out "$name-%02d.png")
}

$lines = @()
foreach ($v in 'hero-60s-loop.mp4', 'demo-60s-en.mp4', 'demo-60s-fr.mp4', 'demo-3min-en.mp4', 'demo-3min-fr.mp4', 'youtube\upload\demo-60s-en.mp4', 'youtube\upload\demo-60s-fr.mp4', 'youtube\upload\demo-3min-en.mp4', 'youtube\upload\demo-3min-fr.mp4') {
  $f = Join-Path $demo $v
  $dur = [math]::Round([double](ffprobe -v error -show_entries format=duration -of csv=p=0 $f), 2)
  $mb = [math]::Round((Get-Item $f).Length / 1MB, 2)
  $vs = ffprobe -v error -select_streams v:0 -show_entries stream=width,height,r_frame_rate -of csv=p=0 $f
  $aud = ''
  if ((ffprobe -v error -select_streams a -show_entries stream=index -of csv=p=0 $f)) {
    $m = ffmpeg -hide_banner -nostats -i $f -af "ebur128=peak=true:framelog=quiet" -f null NUL 2>&1 | Select-String -Pattern '^\s+(I|Peak):' | ForEach-Object { $_.Line.Trim() -replace '\s+', ' ' }
    $aud = ($m -join ', ')
  } else { $aud = 'no audio track' }
  $lines += "$v | $dur s | $mb MB | $vs | $aud"
}
$poster = Join-Path $demo 'hero-60s-poster.jpg'
$lines += "hero-60s-poster.jpg | $([math]::Round((Get-Item $poster).Length / 1KB)) KB | $(ffprobe -v error -show_entries stream=width,height -of csv=p=0 $poster)"
foreach ($t in Get-ChildItem (Join-Path $demo 'youtube') -Filter 'thumbnail-*.png') {
  $lines += "youtube\$($t.Name) | $([math]::Round($t.Length / 1KB)) KB | $(ffprobe -v error -show_entries stream=width,height -of csv=p=0 $t.FullName)"
}
$lines | Set-Content -Encoding utf8 (Join-Path $out 'measurements.txt')
$lines
"sheets: " + (Get-ChildItem $out -Filter *.png).Count
