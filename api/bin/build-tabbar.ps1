$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$iconDir = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../miniprogram/assets/tabbar'))
[IO.Directory]::CreateDirectory($iconDir) | Out-Null
foreach ($name in @('home', 'products', 'news', 'contact')) {
    foreach ($selected in @($false, $true)) {
        $hex = if ($selected) { '#ee642d' } else { '#858d99' }
        $large = [Drawing.Bitmap]::new(324, 324, [Drawing.Imaging.PixelFormat]::Format32bppArgb)
        $g = [Drawing.Graphics]::FromImage($large)
        $g.Clear([Drawing.Color]::Transparent)
        $g.SmoothingMode = [Drawing.Drawing2D.SmoothingMode]::AntiAlias
        $g.ScaleTransform(4, 4)
        $pen = [Drawing.Pen]::new([Drawing.ColorTranslator]::FromHtml($hex), [single]4.2)
        $pen.StartCap = $pen.EndCap = [Drawing.Drawing2D.LineCap]::Round
        $pen.LineJoin = [Drawing.Drawing2D.LineJoin]::Round
        $path = [Drawing.Drawing2D.GraphicsPath]::new()
        switch ($name) {
            'home' {
                $path.AddLines([Drawing.PointF[]]@([Drawing.PointF]::new(12,37),[Drawing.PointF]::new(40.5,13),[Drawing.PointF]::new(69,37)))
                $path.StartFigure()
                $path.AddLines([Drawing.PointF[]]@([Drawing.PointF]::new(20,37),[Drawing.PointF]::new(20,67),[Drawing.PointF]::new(61,67),[Drawing.PointF]::new(61,37)))
                $path.StartFigure()
                $path.AddLines([Drawing.PointF[]]@([Drawing.PointF]::new(34,67),[Drawing.PointF]::new(34,49),[Drawing.PointF]::new(47,49),[Drawing.PointF]::new(47,67)))
            }
            'products' {
                $path.AddLines([Drawing.PointF[]]@([Drawing.PointF]::new(40.5,13),[Drawing.PointF]::new(66,27),[Drawing.PointF]::new(40.5,41),[Drawing.PointF]::new(15,27),[Drawing.PointF]::new(40.5,13)))
                $path.StartFigure()
                $path.AddLines([Drawing.PointF[]]@([Drawing.PointF]::new(15,27),[Drawing.PointF]::new(15,55),[Drawing.PointF]::new(40.5,69),[Drawing.PointF]::new(66,55),[Drawing.PointF]::new(66,27)))
                $path.StartFigure(); $path.AddLine([single]40.5,[single]41,[single]40.5,[single]69)
            }
            'news' {
                $path.AddRectangle([Drawing.RectangleF]::new(18,13,45,55))
                $path.AddRectangle([Drawing.RectangleF]::new(27,25,12,13))
                $path.StartFigure(); $path.AddLine(47,26,54,26)
                $path.StartFigure(); $path.AddLine(47,37,54,37)
                $path.StartFigure(); $path.AddLine(27,49,54,49)
                $path.StartFigure(); $path.AddLine(27,58,49,58)
            }
            'contact' {
                $path.AddEllipse([Drawing.RectangleF]::new(28.5,13,24,24))
                $path.StartFigure(); $path.AddBezier(15,67,15,40,66,40,66,67)
                $path.AddLine(66,67,15,67)
            }
        }
        $g.DrawPath($pen,$path)
        $small = [Drawing.Bitmap]::new(81,81,[Drawing.Imaging.PixelFormat]::Format32bppArgb)
        $sg = [Drawing.Graphics]::FromImage($small)
        $sg.Clear([Drawing.Color]::Transparent)
        $sg.InterpolationMode = [Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $sg.PixelOffsetMode = [Drawing.Drawing2D.PixelOffsetMode]::HighQuality
        $sg.DrawImage($large,[Drawing.Rectangle]::new(0,0,81,81))
        $suffix = if ($selected) { '-active' } else { '' }
        $small.Save((Join-Path $iconDir ($name + $suffix + '.png')),[Drawing.Imaging.ImageFormat]::Png)
        $sg.Dispose(); $small.Dispose(); $path.Dispose(); $pen.Dispose(); $g.Dispose(); $large.Dispose()
    }
}
Write-Output 'Generated 8 tab bar icons.'
