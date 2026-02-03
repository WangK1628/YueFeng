<?php
// 儿童成长相册 - 单文件版
// 部署说明: 将此文件放在网站根目录，确保/photo目录存在并包含照片
// 配置设置
define('PHOTO_DIR', __DIR__ . '/photo');  // 照片目录路径
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB
define('SITE_NAME', '宝贝成长相册');
define('CHILD_NAME', '来来');  // 修改为孩子的名字
define('TIMEZONE', 'Asia/Shanghai');
date_default_timezone_set(TIMEZONE);
 
// 安全函数：防止目录遍历攻击
function sanitize_path($path) {
    $path = realpath($path);
    $base = realpath(PHOTO_DIR);
     
    // 确保路径在照片目录内
    if ($path === false || $base === false || strpos($path, $base) !== 0) {
        return false;
    }
     
    return $path;
}
 
// 获取请求参数
$action = $_GET['action'] ?? 'index';
$photo_name = $_GET['photo'] ?? '';
$size = $_GET['size'] ?? 'medium';
$search = $_GET['search'] ?? '';
$year_filter = $_GET['year'] ?? '';
 
// 路由处理
switch ($action) {
    case 'view':
        display_photo($photo_name);
        break;
    case 'thumbnail':
        generate_thumbnail($photo_name, $size);
        break;
    case 'full':
        display_full_image($photo_name);
        break;
    default:
        display_gallery($search, $year_filter);
        break;
}
 
// 显示相册主页
function display_gallery($search = '', $year_filter = '') {
    // 获取所有照片
    $all_photos = get_all_photos();
     
    // 如果有搜索关键词，过滤照片
    $photos = $all_photos;
    if (!empty($search)) {
        $photos = filter_photos_by_search($all_photos, $search);
    }
     
    // 如果有年份筛选，只显示该年份的照片
    if (!empty($year_filter) && isset($photos[$year_filter])) {
        $filtered_photos = [$year_filter => $photos[$year_filter]];
        $photos = $filtered_photos;
    }
     
    // 计算统计数据
    $total_photos = 0;
    $earliest_date = null;
    $latest_date = null;
     
    foreach ($photos as $year => $year_photos) {
        $total_photos += count($year_photos);
         
        foreach ($year_photos as $photo) {
            if (!$earliest_date || $photo['timestamp'] < $earliest_date) {
                $earliest_date = $photo['timestamp'];
            }
            if (!$latest_date || $photo['timestamp'] > $latest_date) {
                $latest_date = $photo['timestamp'];
            }
        }
    }
     
    // 按年份排序（最新的年份在前面）
    krsort($photos);
     
    // 获取所有可用年份
    $all_years = array_keys($all_photos);
    rsort($all_years);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo CHILD_NAME; ?>的成长记录</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
         
        :root {
            --primary-orange: #FF9800;
            --light-orange: #FFB74D;
            --dark-orange: #F57C00;
            --background: #FFF5E6;
            --card-bg: #FFFFFF;
            --text-dark: #5A4A42;
            --text-light: #7A6A62;
            --success: #41B3A3;
            --warning: #FFA726;
            --error: #F44336;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 15px 40px rgba(255, 152, 0, 0.2);
        }
         
        body {
            font-family: 'Noto Sans SC', sans-serif;
            background: linear-gradient(135deg, #fdfcfb 0%, #f5e8d0 100%);
            color: var(--text-dark);
            min-height: 100vh;
            line-height: 1.6;
            overflow-x: hidden;
        }
         
        /* 背景装饰 */
        .bg-decoration {
            position: fixed;
            z-index: 0;
            opacity: 0.08;
            pointer-events: none;
            font-size: 80px;
        }
         
        .decoration-1 {
            top: 10%;
            left: 5%;
            animation: float 25s infinite linear;
            color: var(--primary-orange);
        }
         
        .decoration-2 {
            bottom: 15%;
            right: 8%;
            animation: float 30s infinite linear reverse;
            color: var(--light-orange);
            transform: rotate(30deg);
        }
         
        .decoration-3 {
            top: 20%;
            right: 12%;
            animation: float 35s infinite linear;
            color: var(--success);
            transform: rotate(-15deg);
        }
         
        .decoration-4 {
            bottom: 25%;
            left: 10%;
            animation: float 20s infinite linear reverse;
            color: var(--warning);
        }
         
        /* 顶部导航 */
        .top-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 15px 0;
            border-bottom: 2px solid rgba(255, 152, 0, 0.2);
        }
         
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
         
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
         
        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
        }
         
        .logo-text {
            display: flex;
            flex-direction: column;
        }
         
        .logo-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-orange);
            line-height: 1;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
        }
         
        .logo-subtitle {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 2px;
        }
         
        /* 搜索框 */
        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }
         
        .search-input {
            width: 100%;
            padding: 14px 20px 14px 50px;
            border: 2px solid rgba(255, 152, 0, 0.3);
            border-radius: 50px;
            font-size: 1rem;
            background: white;
            color: var(--text-dark);
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
         
        .search-input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
        }
         
        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-orange);
            font-size: 1.1rem;
        }
         
        .search-btn {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
            color: white;
            border: none;
            border-radius: 50px;
            padding: 8px 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
         
        .search-btn:hover {
            transform: translateY(-50%) scale(1.05);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.4);
        }
         
        /* 主容器 */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
            position: relative;
            z-index: 1;
        }
         
        /* 页面标题 */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 2px solid rgba(255, 152, 0, 0.15);
            position: relative;
            overflow: hidden;
        }
         
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, var(--primary-orange), var(--light-orange));
        }
         
        .page-title {
            font-size: 2.8rem;
            color: var(--primary-orange);
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
         
        .baby-emoji {
            font-size: 3rem;
            animation: bounce 2s infinite;
        }
         
        .page-description {
            font-size: 1.2rem;
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.8;
        }
         
        /* 控制面板 */
        .control-panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 40px;
            box-shadow: var(--shadow);
            border: 2px solid rgba(255, 152, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
         
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            flex: 1;
        }
         
        .stat-card {
            background: linear-gradient(135deg, rgba(255, 152, 0, 0.1), rgba(255, 183, 77, 0.1));
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 152, 0, 0.2);
            transition: all 0.3s ease;
        }
         
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255, 152, 0, 0.2);
        }
         
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-orange);
            margin-bottom: 8px;
            line-height: 1;
        }
         
        .stat-label {
            color: var(--text-light);
            font-size: 0.95rem;
            font-weight: 500;
        }
         
        .filter-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
         
        .year-select {
            padding: 12px 25px 12px 15px;
            border: 2px solid rgba(255, 152, 0, 0.3);
            border-radius: 50px;
            background: white;
            color: var(--text-dark);
            font-size: 1rem;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23FF9800' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
            min-width: 180px;
        }
         
        .year-select:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
        }
         
        .reset-btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--success), #5cd6c6);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
         
        .reset-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(65, 179, 163, 0.3);
        }
         
        /* 年份分组 */
        .year-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 2px solid rgba(255, 152, 0, 0.15);
            animation: fadeIn 0.5s ease-out;
        }
         
        .year-header {
            background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
         
        .year-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }
         
        .year-header:hover::before {
            left: 100%;
        }
         
        .year-title {
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }
         
        .year-icon {
            font-size: 1.5rem;
        }
         
        .year-count {
            background: rgba(255, 255, 255, 0.25);
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
         
        .year-content {
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            transition: all 0.3s ease;
        }
         
        .year-content.hidden {
            display: none;
        }
         
        /* 照片卡片 */
        .photo-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid rgba(255, 152, 0, 0.1);
        }
         
        .photo-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary-orange);
        }
         
        .photo-link {
            display: block;
            text-decoration: none;
            color: inherit;
            position: relative;
        }
         
        .photo-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
            transition: transform 0.5s;
        }
         
        .photo-card:hover .photo-image {
            transform: scale(1.08);
        }
         
        .photo-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.6), transparent 50%);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: flex-end;
            padding: 20px;
        }
         
        .photo-card:hover .photo-overlay {
            opacity: 1;
        }
         
        .view-button {
            background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
            color: white;
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }
         
        .photo-card:hover .view-button {
            opacity: 1;
            transform: translateY(0);
        }
         
        .photo-info {
            padding: 20px;
            background: white;
        }
         
        .photo-date {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
         
        .photo-age {
            font-size: 0.95rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 5px;
        }
         
        .age-icon {
            color: var(--primary-orange);
        }
         
        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 2px dashed rgba(255, 152, 0, 0.3);
            margin: 40px 0;
        }
         
        .empty-icon {
            font-size: 80px;
            color: var(--light-orange);
            margin-bottom: 25px;
            display: block;
        }
         
        .empty-title {
            font-size: 2rem;
            color: var(--text-dark);
            margin-bottom: 15px;
        }
         
        .empty-description {
            max-width: 500px;
            margin: 0 auto 30px;
            line-height: 1.8;
            color: var(--text-light);
        }
         
        /* 提示卡片 */
        .tips-card {
            background: linear-gradient(135deg, rgba(255, 152, 0, 0.1), rgba(255, 183, 77, 0.1));
            border-radius: 15px;
            padding: 25px;
            margin-top: 40px;
            border-left: 5px solid var(--primary-orange);
            box-shadow: var(--shadow);
        }
         
        .tips-title {
            color: var(--primary-orange);
            margin-bottom: 15px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
         
        .tips-list {
            list-style: none;
            padding: 0;
        }
         
        .tips-list li {
            margin-bottom: 10px;
            padding-left: 30px;
            position: relative;
            line-height: 1.6;
        }
         
        .tips-list li:before {
            content: '&#10003;';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
        }
         
        /* 页脚 */
        .footer {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
            font-size: 0.95rem;
            border-top: 1px solid rgba(255, 152, 0, 0.2);
            margin-top: 60px;
            background: rgba(255, 255, 255, 0.9);
        }
         
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
         
        .footer-link {
            color: var(--primary-orange);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
         
        .footer-link:hover {
            color: var(--dark-orange);
            transform: translateY(-2px);
        }
         
        /* 动画 */
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, 30px) rotate(120deg); }
            66% { transform: translate(-20px, 40px) rotate(240deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }
         
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
         
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
         
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
         
        /* 响应式设计 */
        @media (max-width: 1200px) {
            .container {
                max-width: 95%;
            }
             
            .year-content {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
         
        @media (max-width: 992px) {
            .nav-container {
                flex-direction: column;
                align-items: stretch;
            }
             
            .search-box {
                max-width: 100%;
            }
             
            .control-panel {
                flex-direction: column;
                align-items: stretch;
            }
             
            .filter-group {
                justify-content: center;
            }
             
            .page-title {
                font-size: 2.2rem;
            }
             
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
         
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }
             
            .page-header {
                padding: 20px;
            }
             
            .page-title {
                font-size: 1.8rem;
                flex-direction: column;
                gap: 10px;
            }
             
            .baby-emoji {
                font-size: 2.3rem;
            }
             
            .stats-grid {
                grid-template-columns: 1fr;
            }
             
            .year-content {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                padding: 20px;
                gap: 20px;
            }
             
            .year-header {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
             
            .year-count {
                align-self: flex-start;
            }
             
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
             
            .year-select, .reset-btn {
                width: 100%;
            }
        }
         
        @media (max-width: 576px) {
            .year-content {
                grid-template-columns: 1fr;
            }
             
            .photo-image {
                height: 200px;
            }
             
            .page-title {
                font-size: 1.6rem;
            }
             
            .page-description {
                font-size: 1rem;
            }
             
            .stat-number {
                font-size: 2rem;
            }
             
            .bg-decoration {
                display: none;
            }
        }
         
        /* 打印样式 */
        @media print {
            .top-nav, .control-panel, .footer, .view-button {
                display: none;
            }
             
            .year-content {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                padding: 10px;
            }
             
            .photo-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
             
            .photo-image {
                height: 150px;
            }
             
            body {
                background: white;
            }
        }
    </style>
</head>
<body>
    <!-- 背景装饰 -->
    <div class="bg-decoration decoration-1">
        <i class="fas fa-heart"></i>
    </div>
    <div class="bg-decoration decoration-2">
        <i class="fas fa-baby"></i>
    </div>
    <div class="bg-decoration decoration-3">
        <i class="fas fa-camera"></i>
    </div>
    <div class="bg-decoration decoration-4">
        <i class="fas fa-star"></i>
    </div>
     
    <!-- 顶部导航 -->
    <div class="top-nav">
        <div class="nav-container">
            <a href="?" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-baby-carriage"></i>
                </div>
                <div class="logo-text">
                    <span class="logo-title"><?php echo SITE_NAME; ?></span>
                    <span class="logo-subtitle"><?php echo CHILD_NAME; ?>的成长记录</span>
                </div>
            </a>
             
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <form method="GET" action="" style="display: inline;">
                    <input type="text" name="search" class="search-input" placeholder="搜索照片..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">搜索</button>
                </form>
            </div>
             
            <div style="display: flex; gap: 10px;">
                <a href="?" class="btn" style="padding: 10px 20px; background: linear-gradient(135deg, var(--primary-orange), var(--light-orange)); color: white; border-radius: 50px; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-home"></i> 首页
                </a>
            </div>
        </div>
    </div>
     
    <!-- 主容器 -->
    <div class="container">
        <!-- 页面标题 -->
        <div class="page-header">
            <h1 class="page-title">
                <span class="baby-emoji">&#128118;
                <?php echo CHILD_NAME; ?>的相册
                &#127775;</span>
            </h1>
            <p class="page-description">
                记录<?php echo CHILD_NAME; ?>成长的美好瞬间，每一张照片都是珍贵的记忆
            </p>
        </div>
         
        <?php if (empty($photos)): ?>
            <!-- 空状态 -->
            <div class="empty-state">
                <span class="empty-icon">&#128247;</span>
                <h2 class="empty-title">相册空空如也</h2>
                <p class="empty-description">
                    还没有<?php echo CHILD_NAME; ?>的照片哦，请将照片按"YYYY-MM-DD.jpg"格式命名并放入photo目录
                </p>
                 
                <div class="tips-card">
                    <h3 class="tips-title"><i class="fas fa-lightbulb"></i> 使用提示：</h3>
                    <ul class="tips-list">
                        <li>照片文件名格式：<strong>YYYY-MM-DD.jpg</strong>（例如：2026-01-01.jpg）</li>
                        <li>支持的图片格式：JPG、PNG、GIF、WebP</li>
                        <li>将照片放入网站目录下的 <strong>/photo</strong> 文件夹中</li>
                        <li>系统会自动按时间线整理照片</li>
                        <li>您可以使用搜索框按日期查找照片</li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <!-- 控制面板 -->
            <div class="control-panel">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_photos; ?></div>
                        <div class="stat-label">成长瞬间</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($photos); ?></div>
                        <div class="stat-label">成长年份</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php 
                            if ($earliest_date) {
                                echo date('Y', $earliest_date);
                            }
                            ?>
                        </div>
                        <div class="stat-label">记录开始</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php 
                            if ($latest_date) {
                                echo date('Y', $latest_date);
                            }
                            ?>
                        </div>
                        <div class="stat-label">最新记录</div>
                    </div>
                </div>
                 
                <div class="filter-group">
                    <select class="year-select">
                        <option value="">所有年份</option>
                        <?php foreach ($all_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($year_filter == $year) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>年
                            </option>
                        <?php endforeach; ?>
                    </select>
                     
                    <?php if ($search || $year_filter): ?>
                        <a href="?" class="reset-btn">
                            <i class="fas fa-redo"></i> 重置筛选
                        </a>
                    <?php endif; ?>
                </div>
            </div>
             
            <!-- 年份分组 -->
            <?php foreach ($photos as $year => $year_photos): ?>
                <?php 
                // 计算该年份的照片数量
                $year_count = count($year_photos);
                 
                // 计算该年份的平均照片数（按月份）
                $months_with_photos = [];
                foreach ($year_photos as $photo) {
                    $month = date('Y-m', $photo['timestamp']);
                    if (!in_array($month, $months_with_photos)) {
                        $months_with_photos[] = $month;
                    }
                }
                $months_count = count($months_with_photos);
                ?>
                 
                <div class="year-section" id="year-<?php echo $year; ?>">
                    <div class="year-header">
                        <h2 class="year-title">
                            <span class="year-icon">&#128197;</span>
                            <?php echo $year; ?>年 · <?php echo CHILD_NAME; ?>的成长记录
                        </h2>
                        <div class="year-count">
                            <i class="fas fa-camera"></i>
                            <?php echo $year_count; ?> 张照片 · <?php echo $months_count; ?> 个月份
                        </div>
                    </div>
                    <div class="year-content" id="content-<?php echo $year; ?>">
                        <?php foreach ($year_photos as $photo): ?>
                            <div class="photo-card">
                                <a href="?action=view&photo=<?php echo urlencode($photo['filename']); ?>" class="photo-link">
                                    <img src="?action=thumbnail&photo=<?php echo urlencode($photo['filename']); ?>&size=medium"
                                         alt="<?php echo CHILD_NAME; ?>在<?php echo $photo['date_display']; ?>的照片"
                                         class="photo-image"
                                         loading="lazy">
                                    <div class="photo-overlay">
                                        <button class="view-button">
                                            <i class="fas fa-eye"></i> 查看详情
                                        </button>
                                    </div>
                                </a>
                                <div class="photo-info">
                                    <div class="photo-date">
                                        <i class="far fa-calendar-alt"></i> <?php echo $photo['date_display']; ?>
                                    </div>
                                    <div class="photo-age">
                                        <span class="age-icon">&#128118;</span>
                                        <?php
                                        // 计算孩子的年龄（假设生日为最早的照片日期）
                                        if ($earliest_date) {
                                            $diff = $photo['timestamp'] - $earliest_date;
                                            $years = floor($diff / (365 * 24 * 60 * 60));
                                            $months = floor(($diff % (365 * 24 * 60 * 60)) / (30 * 24 * 60 * 60));
                                            $days = floor(($diff % (30 * 24 * 60 * 60)) / (24 * 60 * 60));
                                             
                                            if ($years > 0) {
                                                echo "约 {$years}岁{$months}个月";
                                            } elseif ($months > 0) {
                                                echo "约 {$months}个月{$days}天";
                                            } else {
                                                echo "约 {$days}天";
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
             
            <!-- 提示卡片 -->
            <div class="tips-card">
                <h3 class="tips-title"><i class="fas fa-info-circle"></i> 相册使用小贴士</h3>
                <ul class="tips-list">
                    <li>点击照片可以查看高清大图</li>
                    <li>使用年份筛选可以快速定位到特定年份的照片</li>
                    <li>在搜索框中输入日期（如"2026-01"）可以查找特定月份的照片</li>
                    <li>鼠标悬停在照片上可以预览查看按钮</li>
                    <li>支持键盘导航：在查看页面可以使用左右箭头键切换照片</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
     
    <!-- 页脚 -->
    <div class="footer">
        <div class="footer-links">
            <a href="#" class="footer-link">
                <i class="fas fa-share-alt"></i> 分享相册
            </a>
            <a href="#" class="footer-link">
                <i class="fas fa-print"></i> 打印相册
            </a>
            <a href="#" class="footer-link">
                <i class="fas fa-download"></i> 备份相册
            </a>
        </div>
        <p><?php echo SITE_NAME; ?> · 记录<?php echo CHILD_NAME; ?>的成长点滴</p>
        <p>&#169; <?php echo date('Y'); ?> 家庭相册 · 由爱而生</p>
        <p style="margin-top: 10px; font-size: 0.85rem; color: #aaa;">
            <i class="fas fa-heart" style="color: #ff6b6b;"></i> 已记录 <?php echo $total_photos ?? 0; ?> 个美好瞬间
        </p>
    </div>
     
    <script>
        // 切换年份显示/隐藏
        function toggleYear(year) {
            const yearContent = document.getElementById('content-' + year);
            const yearHeader = document.querySelector('#year-' + year + ' .year-header');
             
            if (yearContent.classList.contains('hidden')) {
                yearContent.classList.remove('hidden');
                yearHeader.style.borderBottom = 'none';
            } else {
                yearContent.classList.add('hidden');
                yearHeader.style.borderBottom = '2px solid rgba(255, 152, 0, 0.1)';
            }
        }
         
        // 页面加载后初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 默认展开最新年份
            const yearSections = document.querySelectorAll('.year-section');
            if (yearSections.length > 0) {
                // 展开第一个年份（最新的）
                const firstYear = yearSections[0];
                const yearId = firstYear.id.replace('year-', '');
                const yearContent = document.getElementById('content-' + yearId);
                if (yearContent) {
                    yearContent.classList.remove('hidden');
                }
                 
                // 折叠其他年份
                for (let i = 1; i < yearSections.length; i++) {
                    const yearId = yearSections[i].id.replace('year-', '');
                    const yearContent = document.getElementById('content-' + yearId);
                    if (yearContent) {
                        yearContent.classList.add('hidden');
                        const yearHeader = yearSections[i].querySelector('.year-header');
                        yearHeader.style.borderBottom = '2px solid rgba(255, 152, 0, 0.1)';
                    }
                }
            }
             
            // 添加滚动动画
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
             
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = 1;
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
             
            // 观察所有年份区域
            document.querySelectorAll('.year-section').forEach(section => {
                section.style.opacity = 0;
                section.style.transform = 'translateY(30px)';
                section.style.transition = 'opacity 0.6s, transform 0.6s';
                observer.observe(section);
            });
             
            // 观察所有照片卡片
            document.querySelectorAll('.photo-card').forEach((card, index) => {
                card.style.opacity = 0;
                card.style.transform = 'translateY(20px)';
                card.style.transition = `opacity 0.5s, transform 0.5s ${index * 0.05}s`;
                 
                const cardObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.opacity = 1;
                            entry.target.style.transform = 'translateY(0)';
                        }
                    });
                }, observerOptions);
                 
                cardObserver.observe(card);
            });
             
            // 更新当前时间
            function updateCurrentTime() {
                const now = new Date();
                const timeString = now.toLocaleDateString('zh-CN', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    weekday: 'long'
                }) + ' ' + now.toLocaleTimeString('zh-CN', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                 
                const timeElement = document.getElementById('current-time');
                if (timeElement) {
                    timeElement.textContent = timeString;
                }
            }
             
            // 添加当前时间显示
            const footer = document.querySelector('.footer p:nth-child(2)');
            if (footer) {
                const timeSpan = document.createElement('span');
                timeSpan.id = 'current-time';
                timeSpan.style.display = 'block';
                timeSpan.style.marginTop = '5px';
                timeSpan.style.fontSize = '0.9rem';
                footer.appendChild(document.createElement('br'));
                footer.appendChild(timeSpan);
                updateCurrentTime();
                setInterval(updateCurrentTime, 60000);
            }
             
            // 添加相册统计
            const totalPhotos = <?php echo $total_photos ?? 0; ?>;
            if (totalPhotos > 0) {
                const earliestDate = <?php echo $earliest_date ?? 0; ?>;
                if (earliestDate) {
                    const earliest = new Date(earliestDate * 1000);
                    const daysDiff = Math.floor((new Date() - earliest) / (1000 * 60 * 60 * 24));
                     
                    const statsElement = document.createElement('div');
                    statsElement.style.marginTop = '10px';
                    statsElement.style.fontSize = '0.85rem';
                    statsElement.style.color = '#888';
                    statsElement.innerHTML = `
                        <i class="fas fa-history"></i> 已记录 ${daysDiff} 天 · 
                        <i class="fas fa-images"></i> 平均 ${(totalPhotos / daysDiff).toFixed(2)} 张/天
                    `;
                     
                    const footerParagraphs = document.querySelectorAll('.footer p');
                    if (footerParagraphs.length > 2) {
                        footerParagraphs[2].appendChild(document.createElement('br'));
                        footerParagraphs[2].appendChild(statsElement);
                    }
                }
            }
        });
         
        // 键盘快捷键
        document.addEventListener('keydown', function(e) {
            // 如果当前在图片查看页面，使用左右箭头导航
            if (window.location.search.includes('action=view')) {
                const prevBtn = document.querySelector('.nav-button.prev');
                const nextBtn = document.querySelector('.nav-button.next');
                 
                if (e.key === 'ArrowLeft' && prevBtn) {
                    prevBtn.click();
                } else if (e.key === 'ArrowRight' && nextBtn) {
                    nextBtn.click();
                } else if (e.key === 'Escape') {
                    window.location.href = '?';
                }
            }
        });
         
        // 搜索功能增强
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
             
            searchInput.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
             
            // 添加搜索提示
            searchInput.addEventListener('input', function() {
                const searchBtn = this.nextElementSibling;
                if (this.value.trim()) {
                    searchBtn.style.background = 'linear-gradient(135deg, var(--dark-orange), var(--primary-orange))';
                } else {
                    searchBtn.style.background = 'linear-gradient(135deg, var(--primary-orange), var(--light-orange))';
                }
            });
        }
         
        // 添加返回顶部按钮
        const backToTopBtn = document.createElement('button');
        backToTopBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
        backToTopBtn.style.position = 'fixed';
        backToTopBtn.style.bottom = '30px';
        backToTopBtn.style.right = '30px';
        backToTopBtn.style.width = '50px';
        backToTopBtn.style.height = '50px';
        backToTopBtn.style.background = 'linear-gradient(135deg, var(--primary-orange), var(--light-orange))';
        backToTopBtn.style.color = 'white';
        backToTopBtn.style.border = 'none';
        backToTopBtn.style.borderRadius = '50%';
        backToTopBtn.style.fontSize = '1.2rem';
        backToTopBtn.style.cursor = 'pointer';
        backToTopBtn.style.boxShadow = '0 5px 20px rgba(255, 152, 0, 0.3)';
        backToTopBtn.style.zIndex = '99';
        backToTopBtn.style.opacity = '0';
        backToTopBtn.style.transition = 'all 0.3s ease';
        backToTopBtn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
         
        document.body.appendChild(backToTopBtn);
         
        // 显示/隐藏返回顶部按钮
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTopBtn.style.opacity = '1';
                backToTopBtn.style.transform = 'translateY(0)';
            } else {
                backToTopBtn.style.opacity = '0';
                backToTopBtn.style.transform = 'translateY(20px)';
            }
        });
         
        // 添加加载动画
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('load', function() {
                this.style.animation = 'pulse 0.5s ease';
                setTimeout(() => {
                    this.style.animation = '';
                }, 500);
            });
        });
    </script>
</body>
</html>
<?php
}
 
// 搜索照片
function filter_photos_by_search($photos, $search) {
    $filtered = [];
     
    foreach ($photos as $year => $year_photos) {
        foreach ($year_photos as $photo) {
            if (stripos($photo['date'], $search) !== false || 
                stripos($photo['date_display'], $search) !== false) {
                $filtered[$year][] = $photo;
            }
        }
    }
     
    return $filtered;
}
 
// 显示单张照片 - 添加旋转功能
function display_photo($photo_name) {
    // 验证照片文件名
    if (!validate_photo_name($photo_name)) {
        header('HTTP/1.0 400 Bad Request');
        die('无效的照片文件名');
    }
     
    // 获取照片路径
    $photo_path = PHOTO_DIR . '/' . $photo_name;
    $photo_path = sanitize_path($photo_path);
     
    if (!$photo_path || !file_exists($photo_path)) {
        header('HTTP/1.0 404 Not Found');
        die('照片不存在');
    }
     
    // 从文件名提取日期
    $date_str = pathinfo($photo_name, PATHINFO_FILENAME);
    $timestamp = strtotime($date_str);
    $date_display = date('Y年m月d日', $timestamp);
     
    // 获取相邻照片
    $adjacent_photos = get_adjacent_photos($photo_name);
     
    // 计算年龄信息
    $photos = get_all_photos();
    $earliest_date = null;
    foreach ($photos as $year_photos) {
        foreach ($year_photos as $photo) {
            if (!$earliest_date || $photo['timestamp'] < $earliest_date) {
                $earliest_date = $photo['timestamp'];
            }
        }
    }
     
    $age_info = '';
    if ($earliest_date) {
        $diff = $timestamp - $earliest_date;
        $years = floor($diff / (365 * 24 * 60 * 60));
        $months = floor(($diff % (365 * 24 * 60 * 60)) / (30 * 24 * 60 * 60));
        $days = floor(($diff % (30 * 24 * 60 * 60)) / (24 * 60 * 60));
         
        if ($years > 0) {
            $age_info = "约 {$years}岁{$months}个月";
        } elseif ($months > 0) {
            $age_info = "约 {$months}个月{$days}天";
        } else {
            $age_info = "约 {$days}天";
        }
    }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $date_display; ?> - <?php echo CHILD_NAME; ?>的照片 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
         
        :root {
            --primary-orange: #FF9800;
            --light-orange: #FFB74D;
            --dark-orange: #F57C00;
            --background: #FFF5E6;
            --card-bg: #FFFFFF;
            --text-dark: #5A4A42;
            --text-light: #7A6A62;
            --success: #41B3A3;
            --warning: #FFA726;
            --error: #F44336;
        }
         
        body {
            font-family: 'Noto Sans SC', sans-serif;
            background: linear-gradient(135deg, #111 0%, #222 100%);
            color: white;
            line-height: 1.6;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }
         
        /* 背景装饰 */
        .bg-decoration {
            position: absolute;
            z-index: 0;
            opacity: 0.05;
            pointer-events: none;
            font-size: 80px;
        }
         
        .decoration-1 {
            top: 10%;
            left: 5%;
            animation: float 25s infinite linear;
            color: var(--primary-orange);
        }
         
        .decoration-2 {
            bottom: 15%;
            right: 8%;
            animation: float 30s infinite linear reverse;
            color: var(--light-orange);
            transform: rotate(30deg);
        }
         
        /* 查看器容器 */
        .viewer-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: relative;
            z-index: 1;
        }
         
        /* 顶部导航 */
        .viewer-header {
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            border-bottom: 2px solid rgba(255, 152, 0, 0.3);
            flex-wrap: wrap;
            gap: 15px;
        }
         
        .viewer-header h1 {
            font-size: 1.4rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
         
        .viewer-header .child-name {
            color: var(--light-orange);
            font-weight: 700;
        }
         
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            color: #aaa;
        }
         
        .breadcrumb a {
            color: var(--light-orange);
            text-decoration: none;
            transition: color 0.3s;
        }
         
        .breadcrumb a:hover {
            color: white;
            text-decoration: underline;
        }
         
        .nav-links {
            display: flex;
            gap: 15px;
            align-items: center;
        }
         
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 50px;
            background: rgba(255, 152, 0, 0.2);
            transition: all 0.3s;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }
         
        .nav-links a:hover {
            background: rgba(255, 152, 0, 0.4);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.2);
        }
         
        /* 照片查看区域 */
        .photo-viewer {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
            background: #000;
            padding: 20px;
        }
         
        .photo-container {
            max-width: 95%;
            max-height: 80vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }
         
        .photo-image-full {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 10px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.8s ease;
            transition: transform 0.5s ease;
        }
         
        /* 旋转控制栏 */
        .rotate-controls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            background: rgba(0, 0, 0, 0.7);
            padding: 12px 20px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 152, 0, 0.3);
            z-index: 10;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            opacity: 0.7;
            transition: opacity 0.3s;
        }
         
        .rotate-controls:hover {
            opacity: 1;
        }
         
        .rotate-btn {
            background: rgba(255, 152, 0, 0.3);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(255, 152, 0, 0.5);
        }
         
        .rotate-btn:hover {
            background: var(--primary-orange);
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
        }
         
        .rotate-value {
            color: white;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            font-weight: 600;
        }
         
        .reset-rotate-btn {
            background: rgba(65, 179, 163, 0.3);
            border-color: rgba(65, 179, 163, 0.5);
        }
         
        .reset-rotate-btn:hover {
            background: var(--success);
        }
         
        /* 旋转角度指示器 */
        .rotation-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: none;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 152, 0, 0.3);
            z-index: 10;
        }
         
        /* 导航箭头 */
        .arrow-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 152, 0, 0.8);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 10;
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
         
        .arrow-nav:hover {
            background: var(--primary-orange);
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 8px 25px rgba(255, 152, 0, 0.4);
        }
         
        .arrow-left {
            left: 30px;
        }
         
        .arrow-right {
            right: 30px;
        }
         
        /* 信息面板 */
        .photo-info-panel {
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            padding: 25px 30px;
            text-align: center;
            z-index: 100;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.3);
            border-top: 2px solid rgba(255, 152, 0, 0.3);
        }
         
        .photo-info-content {
            max-width: 800px;
            margin: 0 auto;
        }
         
        .photo-date-large {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
         
        .photo-age-info {
            color: var(--light-orange);
            font-size: 1.2rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
         
        .photo-filename {
            color: #aaa;
            font-size: 0.95rem;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 10px;
            display: inline-block;
        }
         
        .navigation-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
         
        .nav-button {
            padding: 14px 30px;
            background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            min-width: 150px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
        }
         
        .nav-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255, 152, 0, 0.4);
        }
         
        .nav-button.disabled {
            background: rgba(100, 100, 100, 0.5);
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.6;
        }
         
        .back-button {
            background: linear-gradient(135deg, var(--success), #5cd6c6);
        }
         
        .back-button:hover {
            box-shadow: 0 10px 25px rgba(65, 179, 163, 0.4);
        }
         
        .download-button {
            background: linear-gradient(135deg, #2196F3, #64B5F6);
        }
         
        .download-button:hover {
            box-shadow: 0 10px 25px rgba(33, 150, 243, 0.4);
        }
         
        /* 照片元数据 */
        .photo-meta {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
         
        .meta-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
         
        .meta-icon {
            font-size: 1.5rem;
            color: var(--light-orange);
        }
         
        .meta-label {
            font-size: 0.9rem;
            color: #aaa;
        }
         
        .meta-value {
            font-size: 1rem;
            font-weight: 600;
            color: white;
        }
         
        /* 动画 */
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
         
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, 30px) rotate(120deg); }
            66% { transform: translate(-20px, 40px) rotate(240deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }
         
        /* 响应式调整 */
        @media (max-width: 992px) {
            .viewer-header {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }
             
            .breadcrumb {
                justify-content: center;
            }
             
            .nav-links {
                justify-content: center;
            }
             
            .arrow-nav {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
             
            .arrow-left {
                left: 15px;
            }
             
            .arrow-right {
                right: 15px;
            }
             
            .photo-image-full {
                max-height: 70vh;
            }
             
            .rotate-controls {
                bottom: 10px;
                padding: 10px 15px;
            }
             
            .rotate-btn {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }
         
        @media (max-width: 768px) {
            .photo-date-large {
                font-size: 1.4rem;
            }
             
            .photo-age-info {
                font-size: 1rem;
            }
             
            .nav-button {
                min-width: 120px;
                padding: 12px 20px;
                font-size: 0.9rem;
            }
             
            .navigation-buttons {
                gap: 10px;
            }
             
            .photo-meta {
                gap: 15px;
            }
             
            .rotate-controls {
                gap: 10px;
                padding: 8px 12px;
            }
             
            .rotate-value {
                min-width: 50px;
                font-size: 0.9rem;
            }
        }
         
        @media (max-width: 576px) {
            .photo-image-full {
                max-height: 60vh;
            }
             
            .arrow-nav {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
             
            .arrow-left {
                left: 10px;
            }
             
            .arrow-right {
                right: 10px;
            }
             
            .viewer-header h1 {
                font-size: 1.1rem;
            }
             
            .nav-links a {
                padding: 8px 15px;
                font-size: 0.85rem;
            }
             
            .photo-info-panel {
                padding: 15px;
            }
             
            .photo-date-large {
                flex-direction: column;
                gap: 5px;
            }
             
            .rotate-controls {
                flex-wrap: wrap;
                justify-content: center;
                width: 90%;
                border-radius: 25px;
            }
        }
    </style>
</head>
<body>
    <!-- 背景装饰 -->
    <div class="bg-decoration decoration-1">
        <i class="fas fa-heart"></i>
    </div>
    <div class="bg-decoration decoration-2">
        <i class="fas fa-star"></i>
    </div>
     
    <div class="viewer-container">
        <div class="viewer-header">
            <h1>
                <span class="child-name"><?php echo CHILD_NAME; ?></span>的成长记录 · 
                <span class="photo-date"><?php echo $date_display; ?></span>
            </h1>
             
            <div class="breadcrumb">
                <a href="?"><i class="fas fa-home"></i> 首页</a>
                <span><i class="fas fa-chevron-right"></i></span>
                <a href="?year=<?php echo date('Y', $timestamp); ?>"><?php echo date('Y', $timestamp); ?>年</a>
                <span><i class="fas fa-chevron-right"></i></span>
                <span><?php echo $date_display; ?></span>
            </div>
             
            <div class="nav-links">
                <a href="?"><i class="fas fa-th"></i> 返回相册</a>
                <a href="#"><i class="fas fa-print"></i> 打印照片</a>
            </div>
        </div>
         
        <div class="photo-viewer">
            <?php if ($adjacent_photos['prev']): ?>
                <a href="?action=view&photo=<?php echo urlencode($adjacent_photos['prev']); ?>" class="arrow-nav arrow-left">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
             
            <div class="photo-container">
                <img src="?action=full&photo=<?php echo urlencode($photo_name); ?>"
                     alt="<?php echo CHILD_NAME; ?>在<?php echo $date_display; ?>的照片"
                     class="photo-image-full"
                     id="main-photo">
                 
                <!-- 旋转角度指示器 -->
                <div class="rotation-indicator" id="rotation-indicator">
                    <i class="fas fa-sync-alt"></i>
                    <span id="rotation-angle">0°</span>
                </div>
                 
                <!-- 旋转控制栏 -->
                <div class="rotate-controls">
                    <button class="rotate-btn" id="rotate-left" title="向左旋转90° (快捷键: [)">
                        <i class="fas fa-undo"></i>
                    </button>
                     
                    <div class="rotate-value" id="rotate-value">0°</div>
                     
                    <button class="rotate-btn" id="rotate-right" title="向右旋转90° (快捷键: ])">
                        <i class="fas fa-redo"></i>
                    </button>
                     
                    <button class="rotate-btn reset-rotate-btn" id="reset-rotate" title="重置旋转 (快捷键: 0)">
                        <i class="fas fa-history"></i>
                    </button>
                </div>
            </div>
             
            <?php if ($adjacent_photos['next']): ?>
                <a href="?action=view&photo=<?php echo urlencode($adjacent_photos['next']); ?>" class="arrow-nav arrow-right">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
         
        <div class="photo-info-panel">
            <div class="photo-info-content">
               
                 
                <div class="photo-age-info">
                    <i class="fas fa-baby"></i>
                    <?php echo $age_info; ?>
                </div>
                 
           
                 
                <div class="navigation-buttons">
                    <?php if ($adjacent_photos['prev']): ?>
                        <a href="?action=view&photo=<?php echo urlencode($adjacent_photos['prev']); ?>" class="nav-button prev">
                            <i class="fas fa-arrow-left"></i> 上一张
                        </a>
                    <?php else: ?>
                        <span class="nav-button disabled">
                            <i class="fas fa-arrow-left"></i> 上一张
                        </span>
                    <?php endif; ?>
                     
                    <a href="?" class="nav-button back-button">
                        <i class="fas fa-th"></i> 返回相册
                    </a>
                     
                    <a href="?action=full&photo=<?php echo urlencode($photo_name); ?>"
                       download="<?php echo $photo_name; ?>"
                       class="nav-button download-button">
                        <i class="fas fa-download"></i> 下载原图
                    </a>
                     
                    <?php if ($adjacent_photos['next']): ?>
                        <a href="?action=view&photo=<?php echo urlencode($adjacent_photos['next']); ?>" class="nav-button next">
                            下一张 <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="nav-button disabled">
                            下一张 <i class="fas fa-arrow-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
     
    <script>
        // 旋转功能
        let rotationAngle = 0;
        const mainPhoto = document.getElementById('main-photo');
        const rotateValue = document.getElementById('rotate-value');
        const rotationIndicator = document.getElementById('rotation-indicator');
        const rotationAngleText = document.getElementById('rotation-angle');
         
        // 初始化旋转状态
        function initRotation() {
            // 从sessionStorage中读取旋转角度（如果有）
            const photoId = '<?php echo $photo_name; ?>';
            const savedAngle = sessionStorage.getItem(`rotation_${photoId}`);
             
            if (savedAngle !== null) {
                rotationAngle = parseInt(savedAngle);
                applyRotation();
            }
        }
         
        // 应用旋转
        function applyRotation() {
            // 限制角度在0-360度之间
            rotationAngle = ((rotationAngle % 360) + 360) % 360;
             
            // 应用CSS变换
            mainPhoto.style.transform = `rotate(${rotationAngle}deg)`;
            rotateValue.textContent = `${rotationAngle}°`;
             
            // 显示或隐藏旋转指示器
            if (rotationAngle !== 0) {
                rotationIndicator.style.display = 'flex';
                rotationAngleText.textContent = `${rotationAngle}°`;
            } else {
                rotationIndicator.style.display = 'none';
            }
             
            // 保存到sessionStorage
            const photoId = '<?php echo $photo_name; ?>';
            sessionStorage.setItem(`rotation_${photoId}`, rotationAngle.toString());
        }
         
        // 旋转按钮事件
        document.getElementById('rotate-left').addEventListener('click', function() {
            rotationAngle -= 90;
            applyRotation();
            showRotationNotification('向左旋转90°');
        });
         
        document.getElementById('rotate-right').addEventListener('click', function() {
            rotationAngle += 90;
            applyRotation();
            showRotationNotification('向右旋转90°');
        });
         
        document.getElementById('reset-rotate').addEventListener('click', function() {
            rotationAngle = 0;
            applyRotation();
            showRotationNotification('重置旋转');
        });
         
        // 显示旋转通知
        function showRotationNotification(message) {
            // 创建通知元素
            const notification = document.createElement('div');
            notification.className = 'rotation-notification';
            notification.innerHTML = `
                <i class="fas fa-sync-alt"></i>
                <span>${message}</span>
            `;
             
            // 添加到页面
            document.body.appendChild(notification);
             
            // 添加样式
            notification.style.cssText = `
                position: fixed;
                top: 100px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(0, 0, 0, 0.8);
                color: white;
                padding: 12px 25px;
                border-radius: 50px;
                display: flex;
                align-items: center;
                gap: 12px;
                z-index: 10000;
                font-weight: 500;
                font-size: 1rem;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 152, 0, 0.5);
                animation: slideInDown 0.3s ease, fadeOut 0.5s ease 2s forwards;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            `;
             
            // 2秒后移除
            setTimeout(() => {
                notification.remove();
            }, 2000);
        }
         
        // 键盘导航
        document.addEventListener('keydown', function(e) {
            const prevBtn = document.querySelector('.nav-button.prev');
            const nextBtn = document.querySelector('.nav-button.next');
            const backBtn = document.querySelector('.back-button');
             
            // 照片导航
            if (e.key === 'ArrowLeft' && prevBtn) {
                prevBtn.click();
            } else if (e.key === 'ArrowRight' && nextBtn) {
                nextBtn.click();
            } else if (e.key === 'Escape' && backBtn) {
                backBtn.click();
            }
            // 旋转控制
            else if (e.key === '[' || e.key === '【') { // 向左旋转
                document.getElementById('rotate-left').click();
                e.preventDefault();
            } else if (e.key === ']' || e.key === '】') { // 向右旋转
                document.getElementById('rotate-right').click();
                e.preventDefault();
            } else if (e.key === '0' || e.key === '零') { // 重置旋转
                document.getElementById('reset-rotate').click();
                e.preventDefault();
            } else if (e.key === 'f' || e.key === 'F') {
                // 全屏切换
                if (!document.fullscreenElement) {
                    mainPhoto.requestFullscreen().catch(err => {
                        console.log(`全屏错误: ${err.message}`);
                    });
                } else {
                    document.exitFullscreen();
                }
            }
        });
         
        // 图片加载错误处理
        mainPhoto.addEventListener('error', function() {
            this.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect width="400" height="300" fill="%23333"/><text x="200" y="150" font-family="Arial" font-size="20" fill="white" text-anchor="middle">图片加载失败</text></svg>';
        });
         
        // 双击全屏
        mainPhoto.addEventListener('dblclick', function() {
            if (!document.fullscreenElement) {
                this.requestFullscreen().catch(err => {
                    console.log(`全屏错误: ${err.message}`);
                });
            } else {
                document.exitFullscreen();
            }
        });
         
        // 鼠标滚轮切换图片
        document.addEventListener('wheel', function(e) {
            const prevBtn = document.querySelector('.nav-button.prev');
            const nextBtn = document.querySelector('.nav-button.next');
             
            if (e.deltaY > 0 && nextBtn) {
                nextBtn.click();
            } else if (e.deltaY < 0 && prevBtn) {
                prevBtn.click();
            }
        });
         
        // 触摸滑动切换（移动端）
        let touchStartX = 0;
        let touchEndX = 0;
        let touchStartY = 0;
        let touchEndY = 0;
         
        document.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        });
         
        document.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            handleSwipe();
        });
         
        function handleSwipe() {
            const swipeThreshold = 50;
            const prevBtn = document.querySelector('.nav-button.prev');
            const nextBtn = document.querySelector('.nav-button.next');
             
            // 水平滑动
            if (Math.abs(touchEndX - touchStartX) > Math.abs(touchEndY - touchStartY)) {
                if (touchEndX < touchStartX - swipeThreshold && nextBtn) {
                    nextBtn.click();
                } else if (touchEndX > touchStartX + swipeThreshold && prevBtn) {
                    prevBtn.click();
                }
            }
        }
         
        // 触摸旋转（双指旋转）
        let initialAngle = 0;
        let initialDistance = 0;
        let isRotating = false;
         
        document.addEventListener('touchstart', function(e) {
            if (e.touches.length === 2) {
                isRotating = true;
                initialAngle = rotationAngle;
                 
                // 计算初始距离和角度
                const touch1 = e.touches[0];
                const touch2 = e.touches[1];
                initialDistance = Math.hypot(
                    touch2.clientX - touch1.clientX,
                    touch2.clientY - touch1.clientY
                );
            }
        });
         
        document.addEventListener('touchmove', function(e) {
            if (e.touches.length === 2 && isRotating) {
                e.preventDefault();
                 
                const touch1 = e.touches[0];
                const touch2 = e.touches[1];
                 
                // 计算当前角度
                const currentAngle = Math.atan2(
                    touch2.clientY - touch1.clientY,
                    touch2.clientX - touch1.clientX
                ) * 180 / Math.PI;
                 
                // 计算旋转变化
                if (e.target === mainPhoto || e.target.closest('.photo-container')) {
                    const angleDiff = currentAngle - initialAngle;
                    rotationAngle = Math.round(initialAngle + angleDiff / 5) * 5;
                    applyRotation();
                }
            }
        });
         
        document.addEventListener('touchend', function(e) {
            if (e.touches.length < 2) {
                isRotating = false;
            }
        });
         
        // 添加CSS动画
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInDown {
                from { transform: translate(-50%, -20px); opacity: 0; }
                to { transform: translate(-50%, 0); opacity: 1; }
            }
             
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
             
            @keyframes pulse {
                0% { transform: scale(1) rotate(var(--rotation-angle)); }
                50% { transform: scale(1.05) rotate(var(--rotation-angle)); }
                100% { transform: scale(1) rotate(var(--rotation-angle)); }
            }
        `;
        document.head.appendChild(style);
         
        // 页面加载完成后初始化旋转功能
        document.addEventListener('DOMContentLoaded', function() {
            initRotation();
             
            // 添加旋转说明
            const photoMeta = document.querySelector('.photo-meta');
            if (photoMeta) {
                const rotationTip = document.createElement('div');
                rotationTip.className = 'meta-item';
                rotationTip.innerHTML = `
                    <div class="meta-icon"><i class="fas fa-sync-alt"></i></div>
                    <div class="meta-label">旋转控制</div>
                    <div class="meta-value">[ ← ] → 0</div>
                `;
                photoMeta.appendChild(rotationTip);
            }
        });
         
        // 切换照片时清除旋转状态（如果需要的话）
        const navButtons = document.querySelectorAll('.nav-button:not(.disabled)');
        navButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                // 如果切换到另一张照片，清除当前照片的旋转状态
                const currentPhotoId = '<?php echo $photo_name; ?>';
                sessionStorage.removeItem(`rotation_${currentPhotoId}`);
            });
        });
    </script>
</body>
</html>
<?php
}
 
// 文件大小格式化函数
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' 字节';
    }
}
 
// 生成缩略图
function generate_thumbnail($photo_name, $size) {
    // 验证照片文件名
    if (!validate_photo_name($photo_name)) {
        header('HTTP/1.0 400 Bad Request');
        die('无效的照片文件名');
    }
     
    // 获取照片路径
    $photo_path = PHOTO_DIR . '/' . $photo_name;
    $photo_path = sanitize_path($photo_path);
     
    if (!$photo_path || !file_exists($photo_path)) {
        header('HTTP/1.0 404 Not Found');
        die('照片不存在');
    }
     
    // 定义缩略图尺寸
    $sizes = [
        'small' => [200, 200],
        'medium' => [400, 400],
        'large' => [800, 800]
    ];
     
    if (!isset($sizes[$size])) {
        $size = 'medium';
    }
     
    list($width, $height) = $sizes[$size];
     
    // 获取文件扩展名
    $ext = strtolower(pathinfo($photo_path, PATHINFO_EXTENSION));
     
    // 检查GD库是否可用
    if (!extension_loaded('gd')) {
        // 如果GD库不可用，直接输出原图
        header('Content-Type: ' . mime_content_type($photo_path));
        readfile($photo_path);
        exit;
    }
     
    // 根据扩展名创建图像资源
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $source = @imagecreatefromjpeg($photo_path);
            break;
        case 'png':
            $source = @imagecreatefrompng($photo_path);
            break;
        case 'gif':
            $source = @imagecreatefromgif($photo_path);
            break;
        case 'webp':
            $source = @imagecreatefromwebp($photo_path);
            break;
        default:
            // 不支持的文件类型
            header('Content-Type: image/svg+xml');
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '"><rect width="' . $width . '" height="' . $height . '" fill="#f5e8d0"/><text x="' . ($width/2) . '" y="' . ($height/2) . '" font-family="Arial" font-size="14" fill="#FF9800" text-anchor="middle">不支持的文件格式</text></svg>';
            exit;
    }
     
    if (!$source) {
        // 如果无法创建图像资源，返回占位符
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '"><rect width="' . $width . '" height="' . $height . '" fill="#f5e8d0"/><text x="' . ($width/2) . '" y="' . ($height/2) . '" font-family="Arial" font-size="14" fill="#FF9800" text-anchor="middle">无法加载图片</text></svg>';
        exit;
    }
     
    // 获取原始图片尺寸
    $orig_width = imagesx($source);
    $orig_height = imagesy($source);
     
    // 计算缩略图尺寸（保持比例）
    $ratio = $orig_width / $orig_height;
    if ($width / $height > $ratio) {
        $width = $height * $ratio;
    } else {
        $height = $width / $ratio;
    }
     
    // 创建缩略图
    $thumb = imagecreatetruecolor($width, $height);
     
    // 对于PNG和GIF，保留透明度
    if ($ext === 'png' || $ext === 'gif' || $ext === 'webp') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $width, $height, $transparent);
    }
     
    // 重新采样图像
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, $orig_width, $orig_height);
     
    // 设置缓存头（缓存1小时）
    header('Cache-Control: public, max-age=3600');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
     
    // 输出图像
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            imagejpeg($thumb, null, 85);
            break;
        case 'png':
            header('Content-Type: image/png');
            imagepng($thumb);
            break;
        case 'gif':
            header('Content-Type: image/gif');
            imagegif($thumb);
            break;
        case 'webp':
            header('Content-Type: image/webp');
            imagewebp($thumb);
            break;
    }
     
    // 释放内存
    imagedestroy($source);
    imagedestroy($thumb);
    exit;
}
 
// 显示完整图片
function display_full_image($photo_name) {
    // 验证照片文件名
    if (!validate_photo_name($photo_name)) {
        header('HTTP/1.0 400 Bad Request');
        die('无效的照片文件名');
    }
     
    // 获取照片路径
    $photo_path = PHOTO_DIR . '/' . $photo_name;
    $photo_path = sanitize_path($photo_path);
     
    if (!$photo_path || !file_exists($photo_path)) {
        header('HTTP/1.0 404 Not Found');
        die('照片不存在');
    }
     
    // 获取文件信息
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $photo_path);
    finfo_close($file_info);
     
    // 设置缓存头（缓存1天）
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
     
    // 输出原始图片
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($photo_path));
    header('Content-Disposition: inline; filename="' . basename($photo_path) . '"');
    readfile($photo_path);
    exit;
}
 
// 辅助函数：验证照片文件名
function validate_photo_name($filename) {
    if (empty($filename)) return false;
     
    // 验证文件名格式：YYYY-MM-DD.扩展名
    $pattern = '/^(\d{4})-(\d{2})-(\d{2})\.(jpg|jpeg|png|gif|webp)$/i';
     
    if (!preg_match($pattern, $filename, $matches)) {
        return false;
    }
     
    // 验证日期是否有效
    $year = intval($matches[1]);
    $month = intval($matches[2]);
    $day = intval($matches[3]);
     
    if (!checkdate($month, $day, $year)) {
        return false;
    }
     
    return true;
}
 
// 辅助函数：获取所有照片
function get_all_photos() {
    $photos = [];
     
    // 检查目录是否存在
    if (!is_dir(PHOTO_DIR)) {
        // 尝试创建目录
        if (!mkdir(PHOTO_DIR, 0755, true)) {
            return $photos;
        }
    }
     
    // 扫描目录获取照片
    $files = @scandir(PHOTO_DIR);
    if ($files === false) {
        return $photos;
    }
     
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
         
        $file_path = PHOTO_DIR . '/' . $file;
         
        // 检查是否为文件
        if (!is_file($file_path)) continue;
         
        // 验证文件名格式
        if (!validate_photo_name($file)) continue;
         
        // 从文件名提取日期
        $date_str = pathinfo($file, PATHINFO_FILENAME);
        $timestamp = strtotime($date_str);
        $date_display = date('Y年m月d日', $timestamp);
        $year = date('Y', $timestamp);
        $year_month = date('Y年m月', $timestamp);
         
        // 添加到照片数组
        $photos[$year][] = [
            'filename' => $file,
            'date' => $date_str,
            'date_display' => $date_display,
            'timestamp' => $timestamp,
            'month_group' => $year_month,
            'url' => '?action=view&photo=' . urlencode($file)
        ];
    }
     
    // 在每个年份内按日期排序（最新的在前面）
    foreach ($photos as $year => &$year_photos) {
        usort($year_photos, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
    }
     
    return $photos;
}
 
// 辅助函数：获取相邻照片
function get_adjacent_photos($current_photo) {
    $files = @scandir(PHOTO_DIR);
    if ($files === false) {
        return ['prev' => null, 'next' => null];
    }
     
    $photo_files = [];
     
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
         
        $file_path = PHOTO_DIR . '/' . $file;
         
        if (!is_file($file_path)) continue;
         
        if (!validate_photo_name($file)) continue;
         
        $photo_files[] = $file;
    }
     
    // 按日期排序
    sort($photo_files);
     
    // 查找当前照片的位置
    $current_index = array_search($current_photo, $photo_files);
    $prev_photo = ($current_index > 0) ? $photo_files[$current_index - 1] : null;
    $next_photo = ($current_index < count($photo_files) - 1) ? $photo_files[$current_index + 1] : null;
     
    return [
        'prev' => $prev_photo,
        'next' => $next_photo
    ];
}
?>
