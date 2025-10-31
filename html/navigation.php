<?php
// ----------------------------------------------------------------------
// navigation.php - 动态生成导航链接的 PHP 脚本 (支持图标和日期)
// ----------------------------------------------------------------------

// 【重要】存放 HTML 文件的实际文件夹路径
$dir_path = "/var/www/jamesband.asia/"; // 需要链接的HTML文件存放目录

// 【重要】您的域名：动态推断协议与主机，避免 http/https 不一致
$scheme = 'http';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') { $scheme = 'https'; }
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) { $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO']; }
$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$base_url = $scheme . '://' . $host . '/';

$html_files = array(); 

if (is_dir($dir_path)) {
    if ($dh = opendir($dir_path)) {
        while (($file_entry = readdir($dh)) !== false) {
            $file_path = $dir_path . $file_entry;
            $file_info = pathinfo($file_path);

            // 处理多种文件类型：html, md, markdown, pdf, 音频文件
            if (is_file($file_path) && isset($file_info['extension'])) {
                $ext = strtolower($file_info['extension']);
                if (in_array($ext, ['html', 'md', 'markdown', 'pdf', 'm4a', 'mp3', 'wav', 'ogg'])) {
                    // 排除脚本文件自身
                    if (!in_array($file_entry, ['navigation.php', 'index.php', 'md_viewer.php'])) {
                        $html_files[] = $file_entry; 
                    }
                }
            }
        }
        closedir($dh); 
    } else {
        echo "<ul><li class='no-files'>错误：无法打开目录 '{$dir_path}'。</li></ul>";
        exit; 
    }
} else {
    echo "<ul><li class='no-files'>错误：目录 '{$dir_path}' 不存在或不是一个有效的目录。</li></ul>";
    exit;
}

// 按文件名自然排序（降序：从高序号到低序号）
natsort($html_files); 
$html_files = array_reverse($html_files, true); // 反转数组顺序，实现降序排列
// 如果需要区分大小写的字母排序，可以使用 sort($html_files, SORT_STRING | SORT_FLAG_CASE);
// 如果需要不区分大小写的字母排序，可以使用 sort($html_files, SORT_NATURAL | SORT_FLAG_CASE);

echo "<ul>\n";

if (empty($html_files)) {
    echo "    <li class='no-files'>在目标目录 ({$dir_path}) 下没有找到 HTML 文件。</li>\n";
} else {
    foreach ($html_files as $file) { // 现在 $html_files 已经排序好了
        $link_text = htmlspecialchars(pathinfo($file, PATHINFO_FILENAME));
        // 根据扩展名决定链接地址：HTML 直接访问；Markdown 通过 md_viewer.php 渲染；PDF 通过 preview.php 查看；音频通过 md_viewer.php 播放
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'html') {
            $link_href = htmlspecialchars($base_url . "jamesband.asia/" . $file);
        } elseif ($ext === 'pdf') {
            // 使用带 META 的壳页，防止微信直接抓取 PDF 无卡片
            $link_href = htmlspecialchars($base_url . 'preview.php?f=' . rawurlencode($file));
        } elseif (in_array($ext, ['m4a', 'mp3', 'wav', 'ogg'])) {
            // 音频文件通过 md_viewer.php 播放
            $link_href = htmlspecialchars($base_url . 'md_viewer.php?file=' . rawurlencode($file));
        } else { // md / markdown
            $link_href = htmlspecialchars($base_url . 'md_viewer.php?file=' . rawurlencode($file));
        }
        
        // 获取文件修改时间用于显示日期 (这部分逻辑可以保留，因为日期显示与排序无关)
        $file_path_for_time = $dir_path . $file;
        $file_timestamp = 0;
        if(is_file($file_path_for_time)){
            $file_timestamp = filemtime($file_path_for_time);
        }
        $display_date = date("m-d", $file_timestamp); // 格式化日期为 月-日

        // 图标选择逻辑
        $icon_class = "fas fa-file-alt";
        if ($ext === 'md' || $ext === 'markdown') {
            $icon_class = "fas fa-file-lines"; // Markdown 专用图标
        } elseif ($ext === 'pdf') {
            $icon_class = "fas fa-file-pdf"; // PDF 专用图标
        } elseif (in_array($ext, ['m4a', 'mp3', 'wav', 'ogg'])) {
            $icon_class = "fas fa-music"; // 音频文件专用图标
        } elseif (stripos($link_text, '报告') !== false || stripos($link_text, 'report') !== false) {
            $icon_class = "fas fa-chart-line";
        } elseif (stripos($link_text, '代码') !== false || stripos($link_text, 'code') !== false) {
            $icon_class = "fas fa-code";
        } elseif (stripos($link_text, '笔记') !== false || stripos($link_text, 'note') !== false) {
            $icon_class = "fas fa-book-open";
        } elseif (stripos($link_text, '学习') !== false || stripos($link_text, 'study') !== false) {
            $icon_class = "fas fa-graduation-cap";
        } elseif (stripos($link_text, '项目') !== false || stripos($link_text, 'project') !== false) {
            $icon_class = "fas fa-folder-open";
        }

        // 输出包含图标、日期和文本的链接（添加悬停 title 显示完整文件名）
        $title_attr = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
        echo "    <li>\n";
        echo "        <a href=\"{$link_href}\" target=\"_blank\" title=\"{$title_attr}\">\n";
        echo "            <div class=\"tile-icon-area\">\n";
        echo "                <span class=\"tile-date\">{$display_date}</span>\n"; 
        echo "                <span class=\"link-icon\"><i class=\"{$icon_class}\"></i></span>\n"; 
        echo "            </div>\n";
        echo "            <span class=\"link-text\">{$link_text}</span>\n"; 
        echo "        </a>\n";
        echo "    </li>\n";
    }
}

echo "</ul>\n";
?>
