<?php
/*  /var/www/html/preview.php
 *  用法：https://jamesband.asia/preview.php?f=文件名.pdf
 *  功能：让微信/QQ 抓取 OpenGraph meta，页面展示标题和一个按钮；
 *  用户第二次点击后才通过 serve_pdf.php 真正打开/下载 PDF。
 */

$src = basename($_GET['f'] ?? '');  // 只取文件名，防止目录遍历
if ($src === '' || !preg_match('/\.pdf$/i', $src)) {
    http_response_code(400);
    exit('bad request');
}

$title = preg_replace('/\.pdf$/i', '', $src);
$desc  = $title . ' - PDF 下载';

// 封面图：可放在 /var/www/html/cover/同名.jpg
$coverCandidate = "cover/{$title}.jpg";
$coverWebPath   = "/{$coverCandidate}"; // 公开路径
$cover = file_exists(__DIR__ . "/{$coverCandidate}") ? $coverWebPath : '/1.png';

// 真实 PDF 通过 serve_pdf.php 输出，避免直接暴露物理路径
$pdf   = "/serve_pdf.php?file=" . rawurlencode($src);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($desc) ?>">
    <meta property="og:type"        content="article">
    <meta property="og:title"       content="<?= htmlspecialchars($title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($desc) ?>">
    <meta property="og:image"       content="<?= htmlspecialchars($cover) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;500;700&display=swap');
        body{
            margin:0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:linear-gradient(135deg,#101522 0%, #1a1e2e 50%, #101522 100%);
            font-family:'Noto Sans SC',-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
            color:#d1d5db;
        }
        .card{
            background:rgba(255,255,255,0.05);
            border:1px solid rgba(255,255,255,.1);
            border-radius:16px;
            padding:40px 60px;
            backdrop-filter:blur(10px);
            box-shadow:0 20px 40px rgba(0,0,0,0.6);
            text-align:center;
        }
        .card h1{
            font-size:2rem;
            margin-bottom:24px;
            color:#ffffff;
        }
        .btn{
            display:inline-block;
            padding:1rem 2.5rem;
            font-size:1.2rem;
            font-weight:600;
            color:#ffffff;
            background:linear-gradient(135deg, #8A2BE2 0%, #9370DB 25%, #7B68EE 50%, #6495ED 75%, #87CEEB 100%);
            background-size:300% 300%;
            border:none;
            border-radius:16px;
            text-decoration:none;
            box-shadow:0 8px 24px rgba(138, 43, 226, 0.3), 0 4px 12px rgba(138, 43, 226, 0.2);
            position:relative;
            overflow:hidden;
            transition:all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            animation:gradient-shift 4s ease infinite;
        }
        
        .btn::before{
            content:'';
            position:absolute;
            top:0;
            left:-100%;
            width:100%;
            height:100%;
            background:linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition:left 0.5s;
        }
        
        .btn:hover{
            transform:translateY(-4px) scale(1.05);
            box-shadow:0 16px 32px rgba(138, 43, 226, 0.4), 0 8px 16px rgba(138, 43, 226, 0.3), 0 0 40px rgba(138, 43, 226, 0.1);
            background-position:100% 100%;
        }
        
        .btn:hover::before{
            left:100%;
        }
        
        .btn:active{
            transform:translateY(-2px) scale(1.02);
            transition:all 0.1s;
        }
        
        @keyframes gradient-shift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Honeycomb overlay */
        .hex-overlay{
            position:fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            z-index:0;
            pointer-events:none;
        }
        .card{ position:relative; z-index:1; }
    </style>
</head>
<body>
    <canvas id="hexOverlay" class="hex-overlay" aria-hidden="true"></canvas>
    <div class="card">
        <h1><?= htmlspecialchars($title) ?></h1>
        <a class="btn" href="<?= htmlspecialchars($pdf) ?>" target="_blank" rel="noopener">
            <i class="fas fa-download" style="margin-right: 8px;"></i>
            查看 / 下载 PDF
        </a>
    </div>
    <script>
    (function(){
        const canvas = document.getElementById('hexOverlay');
        if(!canvas){ return; }
        const ctx = canvas.getContext('2d');

        // 可调参数：六边形半径与线条样式（单位：CSS像素）
        // 支持 URL 参数 ?h=40 调整半径
        const urlParams = new URLSearchParams(window.location.search);
        const hParam = parseFloat(urlParams.get('h'));
        let hexRadius = Number.isFinite(hParam) && hParam > 6 ? hParam : 38; // 单元大小，拖拽的六边形应与其匹配
        const strokeColor = 'rgba(255,255,255,0.10)';
        const lineWidth = 1;

        const card = document.querySelector('.card');
        let gapRadius = 200; // 中间留白半径，稍后根据卡片动态计算

        function computeGapRadius(){
            if(!card){ return; }
            const rect = card.getBoundingClientRect();
            // 以卡片对角线一半加余量，确保标题与按钮区域完全未被网格覆盖
            const halfDiagonal = Math.sqrt(rect.width*rect.width + rect.height*rect.height)/2;
            gapRadius = halfDiagonal + 28; // 余量
        }

        function draw(){
            const dpr = window.devicePixelRatio || 1;
            const width = Math.ceil(window.innerWidth);
            const height = Math.ceil(window.innerHeight);
            canvas.width = Math.floor(width * dpr);
            canvas.height = Math.floor(height * dpr);
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, width, height);

            // 六边形几何（平顶）
            const r = hexRadius;
            const hexWidth = 2 * r;
            const hexHeight = Math.sqrt(3) * r; // ≈ 1.732 * r
            const horizStep = 1.5 * r;          // 相邻列中心间距（平顶）
            const vertStep = hexHeight;         // 相邻行中心间距（平顶）

            // 以卡片中心作为留白圆心
            const cardRect = card ? card.getBoundingClientRect() : {left: width/2 - 1, top: height/2 - 1, width: 2, height: 2};
            const centerX = cardRect.left + cardRect.width / 2;
            const centerY = cardRect.top + cardRect.height / 2;

            ctx.strokeStyle = strokeColor;
            ctx.lineWidth = lineWidth;

            // 预绘制一个单位六边形路径，后续重复描边
            function traceHex(cx, cy){
                // 中心到各顶点（平顶）
                const halfH = hexHeight / 2;
                ctx.beginPath();
                ctx.moveTo(cx - r/2, cy - halfH);
                ctx.lineTo(cx + r/2, cy - halfH);
                ctx.lineTo(cx + r,   cy);
                ctx.lineTo(cx + r/2, cy + halfH);
                ctx.lineTo(cx - r/2, cy + halfH);
                ctx.lineTo(cx - r,   cy);
                ctx.closePath();
                ctx.stroke();
            }

            // 需要绘制的行列范围，给一定边界冗余
            const cols = Math.ceil(width / horizStep) + 3;
            const rows = Math.ceil(height / vertStep) + 3;

            for(let row = -2; row < rows; row++){
                const offsetX = (row % 2 === 0) ? 0 : (horizStep / 2); // 奇数行半步水平偏移
                const cy = row * vertStep;
                for(let col = -2; col < cols; col++){
                    const cx = col * horizStep + offsetX;
                    // 中心留白：圆形区域不绘制
                    const dx = cx - centerX;
                    const dy = cy - centerY;
                    if ((dx*dx + dy*dy) <= (gapRadius * gapRadius)) {
                        continue;
                    }
                    traceHex(cx, cy);
                }
            }
        }

        function scheduleDraw(){
            computeGapRadius();
            window.requestAnimationFrame(draw);
        }

        let resizeTimer = null;
        window.addEventListener('resize', function(){
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(scheduleDraw, 100);
        });

        // 初次绘制（等待字体和布局稳定后）
        if(document.readyState === 'complete' || document.readyState === 'interactive'){
            setTimeout(scheduleDraw, 50);
        }else{
            window.addEventListener('DOMContentLoaded', function(){ setTimeout(scheduleDraw, 50); });
        }

    })();
    </script>
</body>
</html>
