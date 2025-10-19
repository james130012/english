<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>音频播放器</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .audio-list {
            padding: 20px;
        }
        .audio-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .audio-item:hover {
            background: #e9ecef;
            border-color: #667eea;
            transform: translateX(5px);
        }
        .audio-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .audio-info {
            flex-grow: 1;
        }
        .audio-name {
            font-weight: bold;
            font-size: 1.1em;
            color: #333;
            margin-bottom: 5px;
        }
        .audio-size {
            color: #666;
            font-size: 0.9em;
        }
        .audio-controls {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-play {
            background: #667eea;
            color: white;
        }
        .btn-play:hover {
            background: #5568d3;
        }
        .btn-download {
            background: #28a745;
            color: white;
        }
        .btn-download:hover {
            background: #218838;
        }
        .player-container {
            padding: 20px;
            background: #f8f9fa;
            border-top: 2px solid #e9ecef;
            display: none;
        }
        .player-container.active {
            display: block;
        }
        .now-playing {
            font-weight: bold;
            margin-bottom: 15px;
            color: #667eea;
            font-size: 1.1em;
        }
        audio {
            width: 100%;
            outline: none;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state svg {
            width: 100px;
            height: 100px;
            opacity: 0.3;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎵 音频播放器</h1>
            <p>大宇之形 方寸之间</p>
        </div>
        
        <div class="audio-list">
            <?php
            $audioDir = '/var/www/jamesband.asia/';
            $audioExts = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'];
            $audioFiles = [];
            
            if (is_dir($audioDir)) {
                $files = scandir($audioDir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $filePath = $audioDir . $file;
                    if (is_file($filePath)) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, $audioExts)) {
                            $audioFiles[] = [
                                'name' => $file,
                                'size' => filesize($filePath),
                                'path' => '/jamesband.asia/' . urlencode($file)
                            ];
                        }
                    }
                }
            }
            
            if (empty($audioFiles)) {
                echo '<div class="empty-state">';
                echo '<div style="font-size: 80px;">🎵</div>';
                echo '<h3>暂无音频文件</h3>';
                echo '<p>请将音频文件放入 /var/www/jamesband.asia/ 目录</p>';
                echo '</div>';
            } else {
                foreach ($audioFiles as $index => $audio) {
                    $sizeKB = round($audio['size'] / 1024, 2);
                    $sizeMB = round($audio['size'] / 1024 / 1024, 2);
                    $displaySize = $sizeMB > 1 ? $sizeMB . ' MB' : $sizeKB . ' KB';
                    
                    echo '<div class="audio-item">';
                    echo '<div class="audio-icon">🎵</div>';
                    echo '<div class="audio-info">';
                    echo '<div class="audio-name">' . htmlspecialchars($audio['name']) . '</div>';
                    echo '<div class="audio-size">' . $displaySize . '</div>';
                    echo '</div>';
                    echo '<div class="audio-controls">';
                    echo '<button class="btn btn-play" onclick="playAudio(\'' . htmlspecialchars($audio['path'], ENT_QUOTES) . '\', \'' . htmlspecialchars($audio['name'], ENT_QUOTES) . '\')">播放</button>';
                    echo '<a href="' . htmlspecialchars($audio['path']) . '" download class="btn btn-download">下载</a>';
                    echo '</div>';
                    echo '</div>';
                }
            }
            ?>
        </div>
        
        <div class="player-container" id="playerContainer">
            <div class="now-playing" id="nowPlaying">正在播放：</div>
            <audio id="audioPlayer" controls controlsList="nodownload">
                您的浏览器不支持音频播放。
            </audio>
        </div>
    </div>
    
    <script>
        function playAudio(path, name) {
            const player = document.getElementById('audioPlayer');
            const container = document.getElementById('playerContainer');
            const nowPlaying = document.getElementById('nowPlaying');
            
            player.src = path;
            player.play();
            nowPlaying.textContent = '正在播放：' + name;
            container.classList.add('active');
            
            container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    </script>
</body>
</html>
