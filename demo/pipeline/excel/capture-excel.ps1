<#
  Opens a workbook in the real Microsoft Excel and captures the window as a PNG, for the
  "the file opens briefly" moment in shot 3 (runbook §6.1). The window is placed off-screen and
  captured with PrintWindow (PW_RENDERFULLCONTENT), so nothing appears on the owner's screen
  beyond a taskbar button for a few seconds. The title bar (which shows the signed-in account
  name) is cropped away.

    powershell -File excel/capture-excel.ps1 -Xlsx ..\captures\downloads\projects-over-budget-2026-11-03.xlsx -Out ..\work\excel\projects.png
#>
param(
  [Parameter(Mandatory = $true)][string]$Xlsx,
  [Parameter(Mandatory = $true)][string]$Out,
  [int]$Width = 1920,
  [int]$Height = 1080,
  [int]$CropTop = 46,  # the title bar: it shows the signed-in account's name and photo
  [int]$Zoom = 130     # large enough to read in the video, small enough to fit columns A to F
)
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Drawing
Add-Type @"
using System;
using System.Runtime.InteropServices;
public static class Win {
  [DllImport("user32.dll")] public static extern bool PrintWindow(IntPtr hwnd, IntPtr hdc, uint flags);
  [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr hwnd, out RECT r);
  [DllImport("user32.dll")] public static extern bool SetWindowPos(IntPtr hwnd, IntPtr after, int x, int y, int w, int h, uint flags);
  [DllImport("user32.dll")] public static extern bool SetProcessDPIAware();
  [StructLayout(LayoutKind.Sequential)] public struct RECT { public int Left, Top, Right, Bottom; }
}
"@
[void][Win]::SetProcessDPIAware()

$xlsxPath = (Resolve-Path $Xlsx).Path
$outPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $Out))
New-Item -ItemType Directory -Force -Path (Split-Path $outPath) | Out-Null

$xl = New-Object -ComObject Excel.Application
try {
  $xl.DisplayAlerts = $false
  $xl.ScreenUpdating = $true
  $wb = $xl.Workbooks.Open($xlsxPath, 0, $true)   # read-only, links not updated
  $xl.Visible = $true
  $xl.WindowState = -4143                          # xlNormal
  $hwnd = [IntPtr]$xl.Hwnd
  # Off-screen, exact pixel size (SetWindowPos works in physical pixels once DPI-aware).
  [void][Win]::SetWindowPos($hwnd, [IntPtr]::Zero, -4000, 0, $Width, $Height + $CropTop, 0x0014)
  $wb.Worksheets.Item(1).Activate()
  $xl.ActiveWindow.Zoom = $Zoom
  $xl.ActiveWindow.ScrollRow = 1
  $xl.ActiveWindow.ScrollColumn = 1
  $wb.Worksheets.Item(1).Range("A1").Select() | Out-Null
  Start-Sleep -Milliseconds 2500                   # let Excel paint

  $r = New-Object Win+RECT
  [void][Win]::GetWindowRect($hwnd, [ref]$r)
  $w = $r.Right - $r.Left; $h = $r.Bottom - $r.Top
  $bmp = New-Object System.Drawing.Bitmap $w, $h
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $hdc = $g.GetHdc()
  $ok = [Win]::PrintWindow($hwnd, $hdc, 2)         # PW_RENDERFULLCONTENT
  $g.ReleaseHdc($hdc); $g.Dispose()
  if (-not $ok) { throw "PrintWindow failed" }

  $cropH = [Math]::Min($Height, $h - $CropTop)
  $crop = $bmp.Clone((New-Object System.Drawing.Rectangle 0, $CropTop, ([Math]::Min($Width, $w)), $cropH), $bmp.PixelFormat)
  $crop.Save($outPath, [System.Drawing.Imaging.ImageFormat]::Png)
  $crop.Dispose(); $bmp.Dispose()
  "captured {0}x{1} (window {2}x{3}) to {4}" -f $crop.Width, $cropH, $w, $h, $outPath
}
finally {
  if ($wb) { $wb.Close($false) }
  $xl.Quit()
  [void][Runtime.InteropServices.Marshal]::ReleaseComObject($xl)
  [GC]::Collect(); [GC]::WaitForPendingFinalizers()
}
