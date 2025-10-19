<?php
// ----------------------------------------------------------------------
// md_viewer.php - 渲染 Markdown 文件并支持 LaTeX 公式
// ----------------------------------------------------------------------

// 1) 引入 Parsedown (若使用 composer, 改为 require 'vendor/autoload.php';)
require_once __DIR__ . '/libs/Parsedown.php';

// 2) 获取并校验文件名
$baseDir = '/var/www/jamesband.asia/'; // Markdown 文件所在目录
$fileParam = $_GET['file'] ?? '';
$file      = basename($fileParam); // 去掉诸如 ../../ 之类的路径

// 支持的文件类型
$allowedExts = ['md', 'markdown', 'txt', 'm4a', 'mp3', 'wav', 'ogg', 'pdf'];
if (!preg_match('/\.(' . implode('|', $allowedExts) . ')$/i', $file)) {
    http_response_code(400);
    exit('非法文件请求');
}

$path = $baseDir . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit('文件不存在');
}

// 3) 根据文件类型处理
$title = htmlspecialchars(pathinfo($file, PATHINFO_FILENAME));
$betterTitle = $title;
$description = '';
$fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$fileSize = filesize($path);
$fileSizeMB = round($fileSize / 1024 / 1024, 2);

$htmlBody = '';
$isAudio = false;
$isPdf = false;
$isText = false;

switch ($fileExt) {
    case 'm4a':
    case 'mp3':
    case 'wav':
    case 'ogg':
        $isAudio = true;
        $betterTitle = $title;
        $description = "音频文件：" . $title . " (大小: " . $fileSizeMB . " MB)";
        break;

    case 'pdf':
        $isPdf = true;
        $betterTitle = $title;
        $description = "PDF文档：" . $title . " (大小: " . $fileSizeMB . " MB)";
        break;

    case 'md':
    case 'markdown':
    case 'txt':
        $isText = true;
        $markdown = file_get_contents($path);
        $parser = new Parsedown();
        $parser->setSafeMode(true); // 防止 XSS
        $htmlBody = $parser->text($markdown);

        $betterTitle = $title;
        $description = 'Markdown 文档：' . $title;

        if ($markdown !== false) {
            $lines = preg_split("/\r\n|\r|\n/", $markdown);
            $firstLine = $lines[0] ?? '';

            // 如果第一行是标题（# 开头），用作标题
            if (preg_match('/^\s*#\s+(.+)/', $firstLine, $matches)) {
                $betterTitle = trim($matches[1]);
            }

            // 生成描述：取前几行非标题内容
            $descLines = [];
            foreach (array_slice($lines, 0, 5) as $line) {
                $line = trim($line);
                if ($line !== '' && !preg_match('/^\s*#/', $line)) {
                    $descLines[] = $line;
                }
                if (count($descLines) >= 3) break;
            }
            if (!empty($descLines)) {
                $description = mb_strimwidth(implode(' ', $descLines), 0, 160, '...', 'UTF-8');
            }
        }
        break;

    default:
        $betterTitle = $title;
        $description = "文件：" . $title . " (大小: " . $fileSizeMB . " MB)";
        break;
}

?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($betterTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    
    <!-- Open Graph 分享标签 -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?= htmlspecialchars($betterTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="/1.png">
    
    <!-- Twitter 分享标签 -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($betterTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="/1.png">

    <!-- GitHub 风格 Markdown 样式 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/5.2.0/github-markdown-light.min.css" integrity="sha512-HRJU0ocXfiegP6nBI3EkkOvE5H7BnPWm0t/2XyNgNs9A5oVho+6b3mL6u2S6fGc9VFDLuHMzdeVI2MvlkIY2uA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- —— KaTeX + autorender —— -->
    <!-- 样式 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <!-- 核心脚本 -->
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <!-- KaTeX autorender 脚本（不自动执行，稍后手动触发） -->
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>

    <!-- 全局预处理：去除 $$ 片段里的 <em>/<i>/<strong> 等标签，恢复下划线; 并修复同一行多对 $$ 定界符 -->
    <script>
    function restoreMath(root){
        root.innerHTML = root.innerHTML.replace(/\$\$([\s\S]*?)\$\$/g, (all, expr)=>{
            // 1) 去掉 Markdown 插入的强调标签，恢复 _
            let clean = expr.replace(/<\/?(em|i|strong|b)>/gi,'')
                           .replace(/&nbsp;/gi,' ');

            // 2) 若同一行出现多对 $$，除前两处外改为单 $
            //   把临时占位符替换后再还原
            let parts = clean.split('$$');
            if(parts.length>3){
                let rebuilt='';
                for(let i=0;i<parts.length;i++){
                    rebuilt+=parts[i];
                    if(i!==parts.length-1){
                        rebuilt += (i<2?'$$':'$');
                    }
                }
                clean = rebuilt;
            }
            return `$$${clean}$$`;
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('.markdown-body');
        if(root) restoreMath(root);
        if (typeof renderMathInElement === 'function') {
            renderMathInElement(document.body, {
                delimiters:[
                  {left:'$$', right:'$$', display:false},
                  {left:'$' , right:'$',  display:false},
                  {left:'\\[', right:'\\]', display:true}
                ],
                throwOnError:false,
                strict:'ignore',
                errorCallback:(msg,err)=>{ console.warn('KaTeX error', err); },
                processEscapes:true
            });
        }
    });
    </script>

    <!-- 不再做块级转换，全部 $$..$$ 当行内公式 -->

    <style>
        body { background:#0A0F1A; color:#e0e8f0; margin:0; }
        .markdown-body {
            box-sizing: border-box;
            max-width: 860px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .markdown-body a { color:#5895f1; }

        /* 让超宽 KaTeX 块级公式可横向滚动 */
        .katex-display {
            overflow-x: auto;
            overflow-y: hidden;
            max-width: 100%;
        }
    </style>
</head>
<body>
    <?php if ($isAudio): ?>
        <div style="max-width: 800px; margin: 50px auto; padding: 20px; background: #0A0F1A; color: #e0e8f0; text-align: center; border-radius: 10px;">
            <h1><?= $betterTitle ?></h1>
            <p style="color: #888; margin-bottom: 30px;"><?= $description ?></p>
            <div style="background: #1a2332; padding: 30px; border-radius: 8px; margin: 20px 0;">
                <audio controls style="width: 100%; max-width: 600px; outline: none;">
                    <source src="/jamesband.asia/<?= urlencode($file) ?>" type="audio/mp4">
                    您的浏览器不支持音频播放。
                </audio>
            </div>
            <div style="margin-top: 30px;">
                <a href="/jamesband.asia/<?= urlencode($file) ?>" download style="display: inline-block; background: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 5px;">
                    下载音频文件
                </a>
                <a href="/audio_player.php" style="display: inline-block; background: #2196F3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 5px;">
                    返回音频播放器
                </a>
            </div>
        </div>

    <?php elseif ($isPdf): ?>
        <div style="max-width: 900px; margin: 20px auto; padding: 20px; background: #0A0F1A; color: #e0e8f0;">
            <h1><?= $betterTitle ?></h1>
            <p style="color: #888; margin-bottom: 20px;"><?= $description ?></p>
            <div style="margin-top: 20px;">
                <a href="/jamesband.asia/<?= urlencode($file) ?>" download style="display: inline-block; background: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 5px;">
                    下载PDF文件
                </a>
                <a href="/preview.php?f=<?= urlencode($file) ?>" style="display: inline-block; background: #FF9800; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 5px;">
                    预览PDF
                </a>
                <a href="/" style="display: inline-block; background: #2196F3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 5px;">
                    返回主页
                </a>
            </div>
        </div>

    <?php elseif ($isText): ?>
        <article class="markdown-body">
            <?= $htmlBody ?>
        </article>

    <?php else: ?>
        <div style="max-width: 800px; margin: 50px auto; padding: 20px; background: #0A0F1A; color: #e0e8f0; text-align: center; border-radius: 10px;">
            <h1><?= $betterTitle ?></h1>
            <p style="color: #888; margin-bottom: 30px;"><?= $description ?></p>
            <div style="margin-top: 30px;">
                <a href="/jamesband.asia/<?= urlencode($file) ?>" download style="display: inline-block; background: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 5px;">
                    下载文件
                </a>
                <a href="/" style="display: inline-block; background: #2196F3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 5px;">
                    返回主页
                </a>
            </div>
        </div>
    <?php endif; ?>
</body>
</html> 