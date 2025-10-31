<?php
    // 动态分享信息：当存在 ?view=... 时，从目标页面提取标题/描述/图片，覆盖默认分享信息
    $defaultTitle = '初二英语学习';
    $defaultDesc  = '星星英语角';
    $defaultImage = '/1.png';
    $shareTitle   = $defaultTitle;
    $shareDesc    = $defaultDesc;
    $shareImage   = $defaultImage;

    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') { $scheme = 'https'; }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) { $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO']; }
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    function abs_url($url, $scheme, $host) {
        if ($url === '') return $url;
        if (preg_match('#^https?://#i', $url)) return $url;
        if ($url[0] === '/') return rtrim($scheme . '://' . $host, '/') . $url;
        return rtrim($scheme . '://' . $host, '/') . '/' . ltrim($url, '/');
    }

    function pick_text_summary($html){
        // 优先 h1，其次第一个段落，最后裁剪纯文本
        if (preg_match('/<h1[^>]*>(.*?)<\\/h1>/is', $html, $m)) {
            $t = trim(strip_tags($m[1])); if ($t !== '') return $t;
        }
        if (preg_match('/<p[^>]*>(.*?)<\\/p>/is', $html, $m2)) {
            $t = trim(strip_tags($m2[1])); if ($t !== '') return mb_strimwidth($t, 0, 160, '...', 'UTF-8');
        }
        $plain = trim(strip_tags($html));
        return $plain !== '' ? mb_strimwidth($plain, 0, 160, '...', 'UTF-8') : '';
    }

    $view = isset($_GET['view']) ? trim($_GET['view']) : '';
    if ($view !== '') {
        $baseDir = '/var/www/jamesband.asia/';
        $baseUrl = 'http://jamesband.asia/';
        $parsed  = @parse_url($view);
        $path    = $parsed['path']  ?? '';
        $query   = $parsed['query'] ?? '';

        // 简化：仅处理顶层文件名，防止目录遍历
        $safeName = basename($path);
        $ext      = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));

        if ($ext === 'html' || $ext === 'htm') {
            $filePath = $baseDir . $safeName;
            if (is_file($filePath)) {
                $html = @file_get_contents($filePath);
                if ($html !== false) {
                    if (preg_match('/<meta\\s+property=["\']og:title["\']\\s+content=["\']([^"\']+)["\']/i', $html, $mm)) {
                        $t = trim($mm[1]); if ($t !== '') $shareTitle = $t;
                    } elseif (preg_match('/<title[^>]*>(.*?)<\\/title>/is', $html, $m)) {
                        $t = trim(strip_tags($m[1])); if ($t !== '') { $shareTitle = $t; }
                    } else {
                        $shareTitle = pathinfo($safeName, PATHINFO_FILENAME);
                    }

                    if (preg_match('/<meta\\s+(?:name|property)=["\'](?:description|og:description)["\']\\s+content=["\']([^"\']*)["\']/i', $html, $m2)) {
                        $d = trim($m2[1]); if ($d !== '') { $shareDesc = $d; }
                    } else {
                        $guess = pick_text_summary($html);
                        if ($guess !== '') $shareDesc = $guess; else $shareDesc = $defaultDesc;
                    }

                    if (preg_match('/<meta\\s+property=["\']og:image["\']\\s+content=["\']([^"\']+)["\']/i', $html, $m3)) {
                        $img = trim($m3[1]); if ($img !== '') { $shareImage = $img; }
                    } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m4)) {
                        $img = trim($m4[1]); if ($img !== '') { $shareImage = $img; }
                    }
                } else {
                    $shareTitle = pathinfo($safeName, PATHINFO_FILENAME);
                    $shareDesc  = $defaultDesc;
                }
            } else {
                $shareTitle = pathinfo($safeName, PATHINFO_FILENAME);
                $shareDesc  = $defaultDesc;
            }
        } else if (preg_match('/md_viewer\\.php$/i', $path)) {
            parse_str($query, $q);
            $f = basename($q['file'] ?? '');
            $name = $f ? pathinfo($f, PATHINFO_FILENAME) : '';
            if ($name !== '') {
                $shareTitle = $name;
                $mdPath = $baseDir . $f;
                if (is_file($mdPath)) {
                    $md = @file_get_contents($mdPath);
                    if ($md !== false) {
                        // 取第一行标题或正文摘要
                        $lines = preg_split("/\r\n|\r|\n/", $md);
                        $first = $lines[0] ?? '';
                        if (preg_match('/^\s*#\s+(.+)/', $first, $hm)) {
                            $shareTitle = trim($hm[1]);
                            $shareDesc  = 'Markdown：' . $shareTitle;
                        } else {
                            $plain = trim(preg_replace('/[#>*`\-\[\]_]/', '', implode(' ', array_slice($lines, 0, 5))));
                            if ($plain !== '') {
                                $shareDesc = mb_strimwidth($plain, 0, 160, '...', 'UTF-8');
                            } else {
                                $shareDesc = 'Markdown：' . $name;
                            }
                        }
                    }
                }
            }
        } else if (preg_match('/preview\\.php$/i', $path)) {
            parse_str($query, $q);
            $f = basename($q['f'] ?? '');
            $name = $f ? pathinfo($f, PATHINFO_FILENAME) : '';
            if ($name !== '') {
                $shareTitle = $name;
                $shareDesc  = $name . ' - PDF 下载';
                $coverCandidate = '/cover/' . $name . '.jpg';
                if (is_file($baseDir . 'cover/' . $name . '.jpg')) {
                    $shareImage = $coverCandidate;
                }
            }
        } else if (preg_match('/pdf_viewer\\.php$/i', $path)) {
            parse_str($query, $q);
            $f = basename($q['file'] ?? '');
            $name = $f ? pathinfo($f, PATHINFO_FILENAME) : '';
            if ($name !== '') {
                $shareTitle = $name;
                $shareDesc  = 'PDF：' . $name;
                $coverCandidate = '/cover/' . $name . '.jpg';
                if (is_file($baseDir . 'cover/' . $name . '.jpg')) {
                    $shareImage = $coverCandidate;
                }
            }
        } else if ($ext === 'pdf') {
            $name = pathinfo($safeName, PATHINFO_FILENAME);
            if ($name !== '') {
                $shareTitle = $name;
                $shareDesc  = 'PDF：' . $name;
                $coverCandidate = '/cover/' . $name . '.jpg';
                if (is_file($baseDir . 'cover/' . $name . '.jpg')) {
                    $shareImage = $coverCandidate;
                }
            }
        } else {
            $base = pathinfo($safeName, PATHINFO_FILENAME);
            if ($base !== '') {
                $shareTitle = $base;
                $shareDesc  = $defaultDesc;
            }
        }
    }
    $shareImage = abs_url($shareImage, $scheme, $host);

    function getRecentFiles($limit = 20) {
        $dir_path = "/var/www/jamesband.asia/";
        $files = array();
        if (is_dir($dir_path)) {
            if ($dh = opendir($dir_path)) {
                while (($file_entry = readdir($dh)) !== false) {
                    $file_path = $dir_path . $file_entry;
                    $file_info = pathinfo($file_path);
                    if (is_file($file_path) && isset($file_info['extension'])) {
                        $ext = strtolower($file_info['extension']);
                        if (in_array($ext, ['html', 'md', 'markdown', 'pdf', 'm4a'])) {
                            if (!in_array($file_entry, ['navigation.php', 'index.php', 'md_viewer.php'])) {
                                $files[] = array(
                                    'file' => $file_entry,
                                    'mtime' => filemtime($file_path)
                                );
                            }
                        }
                    }
                }
                closedir($dh);
            }
        }
        usort($files, function($a, $b) { return $b['mtime'] - $a['mtime']; });
        return array_slice($files, 0, $limit);
    }
    
    $recentFiles = getRecentFiles(100); // 获取更多文件以确保能找到足够多的编号文件

    // 按文件名前的数字编号从高到低排序
    usort($recentFiles, function($a, $b) {
        $numA = 0; $numB = 0;
        preg_match('/^(\d+)/', $a['file'], $matchesA);
        if (isset($matchesA[1])) $numA = (int)$matchesA[1];
        preg_match('/^(\d+)/', $b['file'], $matchesB);
        if (isset($matchesB[1])) $numB = (int)$matchesB[1];
        return $numB - $numA;
    });

    $recentFiles = array_slice($recentFiles, 0, 25); // 取排序后的前25个

    $hcItems = [];
    foreach ($recentFiles as $file) {
        $ext = strtolower(pathinfo($file['file'], PATHINFO_EXTENSION));
        if ($ext === 'html') {
            $href = "jamesband.asia/" . $file["file"];
        } elseif ($ext === 'pdf') {
            $href = 'preview.php?f=' . rawurlencode($file['file']);
        } elseif (in_array($ext, ['m4a', 'mp3', 'wav', 'ogg'])) {
            // 音频文件直接链接到音频播放器或md_viewer
            $href = 'md_viewer.php?file=' . rawurlencode($file['file']);
        } else {
            $href = 'md_viewer.php?file=' . rawurlencode($file['file']);
        }
        $hcItems[] = [
            'label' => $file['file'],
            'url'   => $href,
        ];
    }
    
    // 调试信息
    error_log("找到的文件数量: " . count($recentFiles));
    error_log("六边形项目数量: " . count($hcItems));
    foreach ($hcItems as $index => $item) {
        error_log("项目 {$index}: {$item['label']} -> {$item['url']}");
    }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初二英语学习 星星英语角 - 祝你天天进步，考高分！</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cdefs%3E%3CradialGradient id='spacetime' cx='50%25' cy='50%25' r='60%25'%3E%3Cstop offset='0%25' style='stop-color:%23002244;stop-opacity:1' /%3E%3Cstop offset='70%25' style='stop-color:%23001122;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23000000;stop-opacity:1' /%3E%3C/radialGradient%3E%3ClinearGradient id='grid' x1='0%25' y1='0%25' x2='100%25' y2='0%25'%3E%3Cstop offset='0%25' style='stop-color:%2393c5fd;stop-opacity:0.8' /%3E%3Cstop offset='100%25' style='stop-color:%2393c5fd;stop-opacity:0.3' /%3E%3C/defs%3E%3Ccircle cx='16' cy='16' r='15' fill='url(%23spacetime)'/%3E%3Cpath d='M 4 16 Q 16 8 28 16' stroke='url(%23grid)' stroke-width='1' fill='none' opacity='0.7'/%3E%3Cpath d='M 4 20 Q 16 12 28 20' stroke='url(%23grid)' stroke-width='0.8' fill='none' opacity='0.5'/%3E%3Cpath d='M 4 12 Q 16 4 28 12' stroke='url(%23grid)' stroke-width='0.8' fill='none' opacity='0.5'/%3E%3Cpath d='M 4 24 Q 16 16 28 24' stroke='url(%23grid)' stroke-width='0.6' fill='none' opacity='0.4'/%3E%3Ccircle cx='16' cy='16' r='2' fill='%23ffffff' opacity='0.9'/%3E%3Ccircle cx='16' cy='16' r='1' fill='%23ffaa00' opacity='0.8'/%3E%3C/svg%3E">
    <?php $ogUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''); ?>
    <meta property="og:title" content="<?= htmlspecialchars($shareTitle, ENT_QUOTES, 'UTF-8') ?>" />
    <meta property="og:url" content="<?= htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8') ?>" />
    <meta property="og:description" content="<?= htmlspecialchars($shareDesc, ENT_QUOTES, 'UTF-8') ?>" />
    <meta property="og:type" content="article" />
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($shareTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($shareDesc, ENT_QUOTES, 'UTF-8') ?>" />
    <meta name="twitter:image" content="<?= htmlspecialchars($shareImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="description" content="<?= htmlspecialchars($shareDesc, ENT_QUOTES, 'UTF-8') ?>" />
    <meta property="og:image" content="<?= htmlspecialchars($shareImage, ENT_QUOTES, 'UTF-8') ?>" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500;700&display=swap');
        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css');
        :root {
            --star-bg: #000000;
            --text-color: #e0e8f0;
            --header-color: #8A2BE2;
            --border-color: rgba(138, 43, 226, 0.1);
            --tile-border-color: rgba(138, 43, 226, 0.2);
            --vscode-titlebar: rgba(20, 20, 28, 0.7);
            --vscode-sidebar: rgba(22, 22, 34, 0.75);
            --vscode-sidebar-hover: rgba(40, 40, 58, 0.9);
            --vscode-editor: rgba(10, 12, 20, 0.35);
            --vscode-active: #2d2f3a;
            --vscode-accent: #4FC1FF;
            --vscode-accent-2: #8A2BE2;
        }
        body {
            font-family: 'Noto Sans SC', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--star-bg);
            color: var(--text-color);
            line-height: 1.7;
            min-height: 100vh;
            box-sizing: border-box;
            overflow: hidden;
        }
        #flowfield-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0; 
            pointer-events: none;
            background-color: #000000; /* 确保容器背景也是纯黑色 */
        }
        .titlebar, .layout {
            position: relative;
            z-index: 1;
        }
        .titlebar {
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 14px;
            background: var(--vscode-titlebar);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            backdrop-filter: blur(10px);
        }
        .titlebar-left { display: flex; align-items: center; gap: 10px; }
        .titlebar-title { font-weight: 600; letter-spacing: 0.3px; color: #cfd8e3; }
        .titlebar-right { display: flex; align-items: center; gap: 10px; color: #9aa4b2; font-size: 0.9em; }
        .home-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px; color: #d6e2f1; cursor: pointer; font-size: 0.9em;
        }
        .home-btn:hover { background: rgba(255,255,255,0.1); }
        .layout { display: flex; height: calc(100vh - 44px); width: 100vw; }
        .sidebar {
            width: 300px;
            min-width: 240px;
            max-width: 560px;
            background: var(--vscode-sidebar);
            border-right: 1px solid rgba(255,255,255,0.06);
            backdrop-filter: blur(12px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header { height: 10px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .sidebar-search { padding: 8px 10px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .sidebar-search input {
            width: 100%; padding: 8px 10px; border-radius: 8px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
            color: #d6e2f1; outline: none;
        }
        .sidebar-search input::placeholder { color: #8aa0c3; }
        #auto-navigation { flex: 1; overflow: auto; padding: 6px 0; }
        #auto-navigation h2 { display: none; }
        #auto-navigation ul { list-style: none; margin: 0; padding: 6px 6px 80px 6px; }
        #auto-navigation ul li { margin: 2px 4px; border-radius: 8px; border: 1px solid transparent; transition: background 0.2s ease, border-color 0.2s ease; }
        #auto-navigation ul li:hover { background: var(--vscode-sidebar-hover); border-color: rgba(255,255,255,0.05); }
        #auto-navigation ul li.active { background: rgba(138, 43, 226, 0.18); border-color: rgba(138, 43, 226, 0.35); box-shadow: inset 0 0 0 1px rgba(138,43,226,0.25); }
        #auto-navigation ul li a {
            text-decoration: none; color: #d6e2f1; display: flex; align-items: center;
            gap: 10px; padding: 10px 12px; font-size: 0.95em; font-weight: 500;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .link-icon { font-size: 1.1em; color: #9cc9ff; margin: 0; }
        #auto-navigation ul li a:hover .link-icon { color: #d0eaff; transform: none; }
        .link-text { display: block; }
        #auto-navigation ul li.no-files { text-align: center; padding: 10px; border: 1px dashed rgba(255,255,255,0.12); background: transparent; }
        #auto-navigation ul li.no-files:hover { background: transparent; }
        .editor { flex: 1; background: transparent; position: relative; display: flex; flex-direction: column; }
        .resizer { width: 6px; cursor: col-resize; background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.12), transparent); position: relative; overflow: visible; z-index: 1; }
        .toggle-arrow {
            position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
            width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            background: rgba(22,22,34,0.75); border: 1px solid rgba(255,255,255,0.25); color: #d6e2f1; cursor: pointer; z-index: 10;
            pointer-events: auto;
        }
        .toggle-arrow:hover { background: rgba(22,22,34,0.95); }
        #toggleSidebarFloat {
            position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
            width: 22px; height: 22px; display: none; background: rgba(22,22,34,0.75);
            border: 1px solid rgba(255,255,255,0.25); color: #d6e2f1; cursor: pointer; z-index: 10; border-radius: 50%;
        }
        #toggleSidebarFloat:hover { background: rgba(22,22,34,0.95); }
        .sidebar-max #toggleSidebarFloat { display: inline-flex; }
        .sidebar-max #toggleSidebarBtn { display: none !important; }
        .sidebar-max .editor { display: none !important; }
        .sidebar-max .sidebar { width: 100vw !important; min-width: 100vw !important; max-width: 100vw !important; }
        .sidebar-max .resizer { width: 40px; cursor: pointer; background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.12), transparent); position: fixed; right: 0; top: 50%; height: 100px; z-index: 10; }
        .editor-tabs {
            height: 40px; display: flex; align-items: center; gap: 8px; padding: 0 10px;
            border-bottom: 1px solid rgba(255,255,255,0.06); background: rgba(22,22,34,0.5); backdrop-filter: blur(8px);
        }
        .tab { padding: 6px 12px; border-radius: 6px; background: rgba(255,255,255,0.05); color: #cfd8e3; font-size: 0.9em; }
        .editor-tab-title { color: #cfd8e3; font-weight: 600; letter-spacing: 0.3px; margin-right: auto; }
        .tabs-spacer { flex: 1; }
        .icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06); color: #d6e2f1; cursor: pointer;
        }
        .icon-btn:hover { background: rgba(255,255,255,0.1); }
        .editor-body { flex: 1; position: relative; }
        .preview-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; display: none; background: transparent; }
        .preview-frame iframe { width: 100%; height: 100%; border: none; }
        .welcome {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            padding: 20px; text-align: center; margin-top: -30px;
            overflow: hidden;
        }
        #hcWorld { position: absolute; inset: 0; width: 100%; height: 100%; display: block; z-index: 1; }
        .hexagon-container {
            position: relative; 
            width: 100%; 
            height: 100%;
            display: flex; 
            align-items: center; 
            justify-content: center;
            min-width: 200px;
            min-height: 200px;
            overflow: hidden;
            box-sizing: border-box;
        }
        .hexagon-item {
            position: absolute; 
            width: 140px; 
            height: 120px; 
            cursor: pointer; 
            transition: all 0.4s ease;
            transform-origin: center center; 
            left: 50%; 
            top: 50%; 
            transform: translate(-50%, -50%);
        }
        .hexagon-shape {
            width: 100%; height: 100%; position: relative;
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.1), rgba(76, 193, 255, 0.05), rgba(138, 43, 226, 0.1));
            border: 2px solid rgba(138, 43, 226, 0.4); backdrop-filter: blur(12px);
            display: flex; align-items: center; justify-content: center; text-align: center;
            color: #d6e2f1; font-size: 0.7rem; font-weight: 500; padding: 8px;
            line-height: 1.2; box-sizing: border-box; transition: all 0.4s ease;
            word-wrap: break-word; word-break: break-word; overflow-wrap: break-word;
            overflow: hidden;
        }
        .hex-label { 
            width: 100%; 
            display: block;
            text-align: center;
            font-size: inherit;
            line-height: inherit;
            word-break: break-word;
            hyphens: auto;
            max-height: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        .hexagon-item:hover .hexagon-shape {
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.25), rgba(76, 193, 255, 0.15), rgba(138, 43, 226, 0.25));
            border-color: rgba(138, 43, 226, 0.8); transform: scale(1.03);
            box-shadow: 0 0 30px rgba(138, 43, 226, 0.5), inset 0 0 30px rgba(76, 193, 255, 0.2);
        }
        
        .hexagon-item:hover .hex-label {
            -webkit-line-clamp: unset;
            overflow: visible;
        }
        .statusbar {
            height: 28px; background: rgba(22,22,34,0.7); border-top: 1px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: space-between; padding: 0 10px; font-size: 12px; color: #a9b4c7;
        }
        .max-preview .titlebar, .max-preview .sidebar, .max-preview .editor-tabs, .max-preview .statusbar, .max-preview .resizer { display: none !important; }
        .max-preview .layout { height: 100vh; }
        .max-preview .preview-frame { display: block; position: fixed; inset: 0; z-index: 5; }
        .restore-btn {
            position: fixed; right: 16px; bottom: 16px; z-index: 10;
            width: 44px; height: 44px; border-radius: 50%; display: none; align-items: center; justify-content: center;
            background: rgba(0,0,0,0.45); border: 1px solid rgba(255,255,255,0.2); color: #fff; cursor: pointer;
            box-shadow: 0 6px 24px rgba(0,0,0,0.4);
        }
        .max-preview .restore-btn { display: inline-flex; }
        * { scrollbar-width: thin; scrollbar-color: rgba(156,201,255,0.6) rgba(22,22,34,0.35); }
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: rgba(22,22,34,0.35); }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, rgba(156,201,255,0.9), rgba(138,43,226,0.75));
            border-radius: 8px; border: 2px solid rgba(22,22,34,0.6);
        }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, rgba(208,234,255,1), rgba(157,52,209,0.9)); }
        @media (max-width: 900px) { 
            .sidebar { width: 220px; } 
        }
        
        @media (max-width: 768px) {
            .hexagon-container {
                min-width: 280px;
                min-height: 280px;
            }
            .hexagon-item {
                width: 120px;
                height: 100px;
            }
            .hexagon-shape {
                font-size: 0.65rem !important;
                padding: 6px !important;
            }
        }
        
        @media (max-width: 480px) {
            .hexagon-container {
                min-width: 240px;
                min-height: 240px;
            }
            .hexagon-item {
                width: 100px;
                height: 85px;
            }
            .hexagon-shape {
                font-size: 0.6rem !important;
                padding: 4px !important;
            }
        }
    </style>
</head>
<body>
    <div id="flowfield-background"></div>
    <div class="titlebar">
        <div class="titlebar-left">
            <span class="titlebar-title">初二英语学习 · 星星英语角 - 祝你天天进步，考高分！</span>
        </div>
        <div class="titlebar-right">
            <span id="datetime"></span>
        </div>
    </div>

    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-header"></div>
            <div class="sidebar-search"><input id="navSearch" type="text" placeholder="搜索…"></div>
            <nav id="auto-navigation">
                <?php
                    if (file_exists('navigation.php')) {
                        include 'navigation.php';
                    } else {
                        echo "<ul><li class='no-files'>导航文件 (navigation.php) 未找到。请检查文件路径。</li></ul>";
                    }
                ?>
            </nav>
            <div class="statusbar">
                <span><i class="fas fa-circle" style="color:#8A2BE2;margin-right:6px"></i>就绪</span>
                <span>© <?php echo date("Y"); ?> jamesband</span>
            </div>
        </aside>
        <div class="resizer" id="resizer" role="separator" aria-orientation="vertical" aria-label="调整侧栏宽度">
            <button class="toggle-arrow" id="toggleSidebarBtn" title="文件列表全宽" aria-label="文件列表全宽"><i class="fas fa-chevron-right"></i></button>
            <button class="toggle-arrow" id="toggleSidebarFloat" title="恢复侧栏" aria-label="恢复侧栏"><i class="fas fa-chevron-left"></i></button>
        </div>
        <section class="editor">
            <div class="editor-tabs">
                <span class="editor-tab-title">英语学习园地</span>
                <button class="icon-btn" id="btnHome" title="回主页"><i class="fas fa-home"></i></button>
                <button class="icon-btn" id="btnMax" title="最大化预览"><i class="fas fa-expand"></i></button>
            </div>
            <div class="editor-body">
                <div class="welcome" id="welcome">
                    <canvas id="hcWorld"></canvas>
                    <div class="hexagon-container" style="z-index:1;">
                        <div class="hexagon-ring">
                            <?php
                                echo "<!-- 调试: 找到 " . count($hcItems) . " 个项目 -->";
                                foreach ($hcItems as $index => $it) {
                                    $label = htmlspecialchars($it['label'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $url   = htmlspecialchars($it['url'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $fileExt = strtolower(pathinfo($it['label'], PATHINFO_EXTENSION));
                                    $isAudio = in_array($fileExt, ['m4a', 'mp3', 'wav', 'ogg']);
                                    echo "<!-- 项目 {$index}: {$label} -->";
                            ?>
                            <div class="hexagon-item" data-href="<?= $url ?>" title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="hexagon-shape" style="<?= $isAudio ? 'background: linear-gradient(135deg, rgba(76, 175, 80, 0.2), rgba(139, 195, 74, 0.1), rgba(76, 175, 80, 0.2)); border-color: rgba(76, 175, 80, 0.6);' : '' ?>">
                                    <span class="hex-label" style="<?= $isAudio ? 'color: #81C784;' : '' ?>"><?= $label ?></span>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <iframe id="previewFrame" class="preview-frame" src="" allowfullscreen allow="autoplay; fullscreen; display-capture; microphone; camera; geolocation; gyroscope; accelerometer; magnetometer; encrypted-media; picture-in-picture"></iframe>
            </div>
        </section>
    </div>
    <button class="restore-btn" id="btnRestore" title="退出全屏"><i class="fas fa-compress"></i></button>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nav = document.getElementById('auto-navigation');
            const frame = document.getElementById('previewFrame');
            const welcome = document.getElementById('welcome');
            const resizer = document.getElementById('resizer');
            const sidebar = document.querySelector('.sidebar');
            const layout = document.querySelector('body');
            const btnMax = document.getElementById('btnMax');
            const btnRestore = document.getElementById('btnRestore');
            const btnHome = document.getElementById('btnHome');
            const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');
            const toggleSidebarFloat = document.getElementById('toggleSidebarFloat');
            const datetime = document.getElementById('datetime');
            const hexContainer = document.querySelector('.hexagon-container');
            const hexItems = Array.from(document.querySelectorAll('.hexagon-item'));
            
            if (!nav) return;

            const searchInput = document.getElementById('navSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const q = this.value.trim().toLowerCase();
                    nav.querySelectorAll('li').forEach(li => {
                        const text = (li.textContent || '').toLowerCase();
                        li.style.display = text.includes(q) ? '' : 'none';
                    });
                });
            }

            nav.addEventListener('click', function(e) {
                const anchor = e.target.closest('a');
                if (!anchor) return;
                e.preventDefault();
                const url = anchor.getAttribute('href');
                if (!url) return;
                nav.querySelectorAll('li.active').forEach(li => li.classList.remove('active'));
                const li = anchor.closest('li');
                if (li) li.classList.add('active');
                if (welcome) welcome.style.display = 'none';
                frame.style.display = 'block';
                frame.src = url;
            }, false);

            try {
                const params = new URLSearchParams(window.location.search);
                if (!params.get('view') && params.get('auto') === '1') {
                    const firstLink = nav.querySelector('a[href]');
                    if (firstLink) firstLink.click();
                }
            } catch(_) {}

            document.addEventListener('click', function(e) {
                const hexItem = e.target.closest('.hexagon-item');
                if (!hexItem) return;
                const href = hexItem.getAttribute('data-href');
                if (!href) return;
                if (welcome) welcome.style.display = 'none';
                frame.style.display = 'block';
                frame.src = href;
                nav.querySelectorAll('li.active').forEach(li => li.classList.remove('active'));
            });

            let startX = 0; let startWidth = 0; let dragging = false;
            if (resizer) {
                resizer.addEventListener('pointerdown', function(ev){
                    startX = ev.clientX; startWidth = sidebar.getBoundingClientRect().width; dragging = true;
                    document.body.style.cursor = 'col-resize';
                    document.addEventListener('pointermove', onPointerMove);
                    document.addEventListener('pointerup', onPointerUp);
                });
                resizer.addEventListener('dblclick', function(){
                    const current = sidebar.getBoundingClientRect().width;
                    const target = current < 420 ? Math.min(window.innerWidth * 0.6, 560) : 300;
                    sidebar.style.width = target + 'px';
                    sidebar.style.minWidth = target + 'px';
                });
            }
            function onPointerMove(ev){
                if(!dragging) return;
                const dx = ev.clientX - startX;
                let newWidth = Math.min(Math.max(startWidth + dx, 200), Math.min(window.innerWidth * 0.6, 560));
                sidebar.style.width = newWidth + 'px';
                sidebar.style.minWidth = newWidth + 'px';
            }
            function onPointerUp(){
                dragging = false;
                document.body.style.cursor = '';
                document.removeEventListener('pointermove', onPointerMove);
                document.removeEventListener('pointerup', onPointerUp);
            }

            function enterSidebarMax() {
                document.body.classList.add('sidebar-max');
            }
            function exitSidebarMax() {
                document.body.classList.remove('sidebar-max');
            }
            function toggleSidebarMax() {
                if (document.body.classList.contains('sidebar-max')) {
                    exitSidebarMax();
                } else {
                    enterSidebarMax();
                }
            }
            toggleSidebarBtn && toggleSidebarBtn.addEventListener('click', toggleSidebarMax);
            toggleSidebarFloat && toggleSidebarFloat.addEventListener('click', exitSidebarMax);

            function enterMax(){ if (frame && frame.src) window.open(frame.src, '_blank'); }
            function exitMax(){ layout.classList.remove('max-preview'); }
            btnMax && btnMax.addEventListener('click', enterMax);
            btnRestore && btnRestore.addEventListener('click', exitMax);
            document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ exitMax(); document.body.classList.remove('sidebar-max'); } });
            
            (function initFromUrl(){
                try {
                    const params = new URLSearchParams(window.location.search);
                    const view = params.get('view');
                    if (view) {
                        if (welcome) welcome.style.display = 'none';
                        frame.style.display = 'block';
                        const target = new URL(view, window.location.origin);
                        if (target.origin === window.location.origin) {
                            frame.src = target.href;
                            layout.classList.add('max-preview');
                        }
                    }
                } catch(err) {}
            })();

            window.addEventListener('popstate', function(){
                try {
                    const params = new URLSearchParams(window.location.search);
                    const view = params.get('view');
                    if (view) {
                        if (welcome) welcome.style.display = 'none';
                        frame.style.display = 'block';
                        const target = new URL(view, window.location.origin);
                        if (target.origin === window.location.origin) {
                            layout.classList.add('max-preview');
                            if (frame.src !== target.href) frame.src = target.href;
                        }
                    } else {
                        layout.classList.remove('max-preview');
                    }
                } catch(err) {}
            });

            btnHome && btnHome.addEventListener('click', function(){
                nav.querySelectorAll('li.active').forEach(li => li.classList.remove('active'));
                if (welcome) welcome.style.display = 'flex';
                frame.style.display = 'none';
                frame.src = '';
                exitMax();
                document.body.classList.remove('sidebar-max');
                nav.scrollTo({ top: 0, behavior: 'smooth' });
                window.history.pushState({}, '', '/index.php');
            });

            function pad(n){ return String(n).padStart(2,'0'); }
            function tick(){
                const d = new Date();
                const s = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
                if (datetime) datetime.textContent = s;
            }
            tick();
            setInterval(tick, 1000);

            // 响应式六边形布局逻辑
            const hexRing = document.querySelector('.hexagon-ring');
            
            function createResponsiveLayout() {
                if (!hexContainer || !hexItems.length) return;
                const rect = hexContainer.getBoundingClientRect();
                const containerWidth = rect.width;
                const containerHeight = rect.height;
                if (containerWidth <= 0 || containerHeight <= 0) {
                    setTimeout(createResponsiveLayout, 100);
                    return;
                }

                // 预留边距，保证 hover 放大(1.03)也不溢出
                const safetyPadding = 24;
                const usableWidth = Math.max(0, containerWidth - safetyPadding * 2);
                const usableHeight = Math.max(0, containerHeight - safetyPadding * 2);

                const itemCount = hexItems.length;
                const aspect = 0.875; // 高 = 宽 * 0.875
                const kx = 1.2;       // 水平间距系数
                const ky = 1.25;      // 垂直间距系数
                const maxItemWidth = 160;
                const minItemWidth = 70;

                // 估算最多列数（基于最小尺寸）
                const maxColsByWidth = Math.max(1, Math.floor(usableWidth / (minItemWidth * kx)));
                const tryMaxCols = Math.max(1, Math.min(itemCount, maxColsByWidth));

                let best = { width: 0, height: 0, cols: 1, rows: itemCount };
                for (let cols = tryMaxCols; cols >= 1; cols--) {
                    const rows = Math.ceil(itemCount / cols);
                    const wByWidth = usableWidth / (cols * kx);
                    const wByHeight = usableHeight / (rows * aspect * ky);
                    const candidateWidth = Math.min(maxItemWidth, wByWidth, wByHeight);
                    if (candidateWidth >= minItemWidth && candidateWidth > best.width) {
                        best = {
                            width: candidateWidth,
                            height: candidateWidth * aspect,
                            cols,
                            rows
                        };
                        // 已经足够大，提前结束
                        if (candidateWidth >= 150) break;
                    }
                }

                // 如果仍然过小，至少保证一个合理最小值
                if (best.width <= 0) {
                    const cols = Math.max(1, Math.floor(Math.sqrt(itemCount)));
                    const rows = Math.ceil(itemCount / cols);
                    const wByWidth = usableWidth / (cols * kx);
                    const wByHeight = usableHeight / (rows * aspect * ky);
                    const candidateWidth = Math.max(50, Math.min(maxItemWidth, wByWidth, wByHeight));
                    best = {
                        width: candidateWidth,
                        height: candidateWidth * aspect,
                        cols,
                        rows
                    };
                }

                const spacingX = best.width * kx;
                const spacingY = best.height * ky;
                const totalWidth = best.cols * spacingX;
                const totalHeight = best.rows * spacingY;

                const startX = (containerWidth - totalWidth) / 2 + best.width / 2;
                const startY = (containerHeight - totalHeight) / 2 + best.height / 2;

                hexItems.forEach((item, index) => {
                    const row = Math.floor(index / best.cols);
                    const col = index % best.cols;
                    const x = startX + col * spacingX;
                    const y = startY + row * spacingY;
                    item.style.left = '0';
                    item.style.top = '0';
                    item.style.transform = `translate(${x - best.width / 2}px, ${y - best.height / 2}px)`;
                    const hexShape = item.querySelector('.hexagon-shape');
                    if (hexShape) {
                        hexShape.style.width = best.width + 'px';
                        hexShape.style.height = best.height + 'px';
                    }
                });
            }
            
            // 初始布局 - 延迟执行确保DOM完全渲染
            console.log('准备初始化布局...');
            console.log('找到的六边形项目:', hexItems);
            
            if (hexItems.length > 0) {
                console.log('开始延迟布局...');
                setTimeout(() => {
                    console.log('延迟布局开始...');
                    createResponsiveLayout();
                }, 100);
            } else {
                console.log('没有找到六边形项目！');
            }
            
            // 窗口大小改变时重新布局
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    createResponsiveLayout();
                }, 100);
            });
            
            // 使用 ResizeObserver 监听容器尺寸变化
            if (window.ResizeObserver && hexContainer) {
                const resizeObserver = new ResizeObserver(() => {
                    clearTimeout(resizeTimeout);
                    resizeTimeout = setTimeout(() => {
                        createResponsiveLayout();
                    }, 50);
                });
                resizeObserver.observe(hexContainer);
            }
            
            // 调试样式已移除
        });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof p5 === 'undefined') { return; }

        const flowSketch = (p) => {
            let particles = [];
            const numParticles = 200; // 减少粒子数量，从500减少到200
            const noiseScale = 0.01;
            let container;

            p.setup = () => {
                container = document.getElementById('flowfield-background');
                if (!container) { return; }
                const canvas = p.createCanvas(container.offsetWidth, container.offsetHeight);
                canvas.parent(container);
                p.colorMode(p.HSB, 360, 100, 100, 100);
                p.strokeWeight(2.5); // 增加线条粗细，让粒子更粗壮清晰
                resetFlow();
                p.drawingContext.shadowBlur = 0; // 移除阴影模糊效果
                p.drawingContext.shadowColor = 'transparent'; // 移除阴影
                let resizeTimeout;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimeout);
                    resizeTimeout = setTimeout(() => {
                        if (container) {
                            p.resizeCanvas(container.offsetWidth, container.offsetHeight);
                            resetFlow();
                        }
                    }, 200);
                });
            };

            function resetFlow() {
                particles = [];
                for (let i = 0; i < numParticles; i++) {
                    particles[i] = new Particle(p.random(p.width), p.random(p.height), p);
                }
                p.background(0);
            }

            p.draw = () => {
                p.background(0); // 完全纯黑色背景，不留下轨迹
                particles.forEach(particle => {
                    let angle = p.noise(particle.pos.x * noiseScale, particle.pos.y * noiseScale) * p.TWO_PI * 4;
                    let force = p5.Vector.fromAngle(angle);
                    force.mult(0.1);
                    particle.applyForce(force);
                    particle.update();
                    particle.edges();
                    particle.show();
                });
            };

            class Particle {
                constructor(x, y, p_instance) {
                    this.p = p_instance;
                    this.pos = this.p.createVector(x, y);
                    this.vel = this.p.createVector(0, 0);
                    this.acc = this.p.createVector(0, 0);
                    this.maxSpeed = 1.2; // 降低移动速度，减少虚线感
                    this.hue = p.random(240, 300);
                }
                applyForce(force) { this.acc.add(force); }
                update() {
                    this.vel.add(this.acc);
                    this.vel.limit(this.maxSpeed);
                    this.pos.add(this.vel);
                    this.acc.mult(0);
                }
                edges() {
                    if (this.pos.x > this.p.width) { this.pos.x = 0; }
                    if (this.pos.x < 0) { this.pos.x = this.p.width; }
                    if (this.pos.y > this.p.height) { this.pos.y = 0; }
                    if (this.pos.y < 0) { this.pos.y = this.p.height; }
                }
                show() {
                    this.p.stroke(0, 0, 45, 100); // 降低亮度到45，让粒子更柔和
                    this.p.point(this.pos.x, this.pos.y);
                }
            }
        };
        new p5(flowSketch);
    });
    </script>
</body>
</html>


<script>
// 注册全站 Service Worker，作用于站点根路径
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/sw.js').catch(()=>{});
  });
}
</script>
