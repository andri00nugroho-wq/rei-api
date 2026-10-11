<?php
require 'config.php';

if (basename($_SERVER['PHP_SELF']) === 'admin.php') {
    return;
}

// Rekam statistik pengunjung
$pdo->query("UPDATE visitor_stats SET total_views = total_views + 1 WHERE id = 1");
$visitor_count = $pdo->query("SELECT total_views FROM visitor_stats WHERE id = 1")->fetchColumn();

$today = date('Y-m-d');
$pdo->query("INSERT INTO daily_visitors (visit_date, views) VALUES ('$today', 1) ON DUPLICATE KEY UPDATE views = views + 1");

$requested_page = $_GET['page'] ?? null;
$current_page_data = null;

if ($requested_page && $requested_page !== 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ? AND type = 'page'");
    $stmt->execute([$requested_page]);
    $current_page_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

$posts = $pdo->query("SELECT * FROM posts WHERE type='post' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$theme = $pdo->query("SELECT * FROM theme_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$s_set = $pdo->query("SELECT * FROM slider_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Ambil slider berdasarkan halaman aktif
$current_slug = $requested_page ?? 'home';
$stmt_slider = $pdo->prepare("SELECT * FROM sliders WHERE show_on_page = 'all' OR show_on_page = ? ORDER BY created_at DESC");
$stmt_slider->execute([$current_slug]);
$sliders = $stmt_slider->fetchAll(PDO::FETCH_ASSOC);

$s_height = $s_set['height'] ?? 450;
$s_radius = $s_set['border_radius'] ?? 20;
$s_trans = $s_set['transition_ms'] ?? 500;
$s_inter = $s_set['interval_ms'] ?? 4000;

$ind_mode = $s_set['indicator_mode'] ?? 'default';
$ind_size = $s_set['indicator_size'] ?? 12;
$ind_show = $s_set['indicator_show'] ?? 1;

$menu_parents = $pdo->query("SELECT * FROM menus WHERE parent_id = 0 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$menu_children = $pdo->query("SELECT * FROM menus WHERE parent_id > 0 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$grouped_submenus = [];
foreach ($menu_children as $child) {
    $grouped_submenus[$child['parent_id']][] = $child;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $current_page_data ? htmlspecialchars($current_page_data['title']) . ' - REI-CMS' : 'REI-CMS Glassmorphism' ?></title>
    
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    
    <style>
        :root { 
            --bg-1: <?= $theme['bg_color_1'] ?? '#4A00E0' ?>; 
            --bg-2: <?= $theme['bg_color_2'] ?? '#8E2DE2' ?>; 
            --glass-bg: rgba(255, 255, 255, <?= $theme['glass_opacity'] ?? 0.15 ?>); 
            --blur: <?= $theme['blur_px'] ?? 15 ?>px; 
            --text: <?= $theme['text_color'] ?? '#ffffff' ?>; 
        }
        
        body { 
            margin: 0; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, var(--bg-1), var(--bg-2)); 
            background-attachment: fixed; 
            color: var(--text); 
            min-height: 150vh; 
        }
        
        .glass { 
            background: var(--glass-bg); 
            backdrop-filter: blur(var(--blur)); 
            -webkit-backdrop-filter: blur(var(--blur)); 
            border: 1px solid rgba(255, 255, 255, 0.2); 
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2); 
        }
        
        #top-nav { 
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%); 
            width: 90%; max-width: 900px; padding: 12px 25px; border-radius: 50px; 
            display: flex; justify-content: space-between; align-items: center; 
            z-index: 1000; transition: top 0.4s ease-in-out; 
        }

        .nav-links-container { display: flex; align-items: center; gap: 20px; position: relative; }
        .nav-link { color: var(--text); text-decoration: none; font-weight: 500; font-size: 0.95rem; display: flex; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 20px; transition: background 0.2s; }
        .nav-link:hover { background: rgba(255,255,255,0.15); opacity: 1; }

        .dropdown-wrapper { position: relative; }
        .dropdown-content { display: none; position: absolute; top: 100%; left: 0; min-width: 160px; background: var(--glass-bg); backdrop-filter: blur(var(--blur)); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); padding: 8px 0; z-index: 1100; }
        .dropdown-wrapper:hover .dropdown-content { display: block; }
        .dropdown-content a { color: var(--text); padding: 8px 16px; text-decoration: none; display: block; font-size: 0.9rem; transition: background 0.2s; }
        .dropdown-content a:hover { background: rgba(255,255,255,0.2); }
        
        #bottom-nav { position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%); width: max-content; padding: 12px 30px; border-radius: 50px; display: flex; gap: 20px; z-index: 1000; transition: bottom 0.4s ease-in-out; }
        
        .content-container { max-width: 800px; margin: 120px auto 100px; padding: 0 20px; }
        .post-card { padding: 30px; border-radius: 20px; margin-bottom: 30px; }
        .post-date { font-size: 0.9em; opacity: 0.8; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 15px;}
        
        .slider-wrapper { margin-top: 100px; max-width: 1000px; margin-left: auto; margin-right: auto; padding: 0 20px;}
        .slider { border-radius: <?= $s_radius ?>px !important; box-shadow: 0 8px 32px 0 rgba(0,0,0,0.3); background-color: transparent !important; }
        .slider .slides { border-radius: <?= $s_radius ?>px !important; overflow: hidden !important; -webkit-transform: translateZ(0); transform: translateZ(0); -webkit-mask-image: -webkit-radial-gradient(white, black); }
        .slider .slides li img { border-radius: <?= $s_radius ?>px !important; }
        .slider .caption { background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(5px); padding: 20px !important; border-radius: 15px; display: inline-block; }

        <?php if ($ind_show == 0): ?>
            .slider .indicators { display: none !important; }
        <?php else: ?>
            .slider .indicators .indicator-item {
                width: <?= $ind_size ?>px !important;
                height: <?= $ind_size ?>px !important;
                margin: 0 6px !important;
                background-color: rgba(255, 255, 255, 0.5) !important;
                border: 2px solid rgba(255, 255, 255, 0.8) !important;
                box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                transition: all 0.3s ease;
            }
            .slider .indicators .indicator-item.active { background-color: #ffffff !important; }

            <?php if ($ind_mode === 'minimal'): ?>
                .slider .indicators .indicator-item { border: none !important; }
            <?php elseif ($ind_mode === 'pill'): ?>
                .slider .indicators .indicator-item {
                    width: <?= $ind_size * 2.5 ?>px !important;
                    height: <?= max(6, $ind_size * 0.4) ?>px !important;
                    border-radius: 4px !important;
                    border: none !important;
                }
            <?php elseif ($ind_mode === 'square'): ?>
                .slider .indicators .indicator-item { border-radius: 4px !important; }
            <?php endif; ?>
        <?php endif; ?>
    </style>
</head>
<body>

    <nav id="top-nav" class="glass">
        <a href="index.php" style="text-decoration:none; display: flex; align-items: center;">
            <img src="logo-rei.png" alt="Logo Raja Emas Indonesia" style="max-height: 32px; width: auto; filter: brightness(1.3) contrast(1.1); drop-shadow: 0 2px 4px rgba(0,0,0,0.3);">
        </a>
        
        <div class="nav-links-container">
            <a href="index.php" class="nav-link">Home</a>
            <?php foreach($menu_parents as $parent): ?>
                <?php $has_children = isset($grouped_submenus[$parent['id']]); ?>
                <?php if($has_children): ?>
                    <div class="dropdown-wrapper">
                        <a href="<?= htmlspecialchars($parent['url']) ?>" class="nav-link">
                            <?= htmlspecialchars($parent['title']) ?> <span class="material-icons" style="font-size: 16px;">arrow_drop_down</span>
                        </a>
                        <div class="dropdown-content">
                            <?php foreach($grouped_submenus[$parent['id']] as $child): ?>
                                <a href="<?= htmlspecialchars($child['url']) ?>"><?= htmlspecialchars($child['title']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($parent['url']) ?>" class="nav-link"><?= htmlspecialchars($parent['title']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <a href="admin.php" class="nav-link" style="background: rgba(255,255,255,0.2);"><i class="material-icons" style="font-size: 16px;">dashboard</i> Admin</a>
    </nav>

    <?php if ($current_page_data): ?>
        <?php if (!empty($current_page_data['custom_css'])): ?>
            <style><?= $current_page_data['custom_css'] ?></style>
        <?php endif; ?>

        <?php if(count($sliders) > 0): ?>
        <div class="slider-wrapper">
            <div class="slider">
                <ul class="slides">
                    <?php foreach($sliders as $slide): ?>
                    <li>
                        <img src="<?= $slide['image_path'] ?>" alt="Slider Image">
                        <div class="caption center-align">
                            <h3><?= htmlspecialchars($slide['title']) ?></h3>
                            <?php if(!empty($slide['caption'])): ?>
                                <h5 class="light grey-text text-lighten-3"><?= htmlspecialchars($slide['caption']) ?></h5>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($current_page_data['custom_html'])): ?>
            <div style="margin-top: 100px;">
                <?= $current_page_data['custom_html'] ?>
            </div>
        <?php else: ?>
            <div class="content-container">
                <div class="post-card glass">
                    <h1 style="margin-top: 0; font-size: 2rem;"><?= htmlspecialchars($current_page_data['title']) ?></h1>
                    <div class="post-date">Dipublikasikan: <?= date('d M Y', strtotime($current_page_data['created_at'])) ?></div>
                    
                    <?php if (!empty($current_page_data['featured_image'])): ?>
                        <div style="margin: 20px 0;">
                            <img src="<?= $current_page_data['featured_image'] ?>" alt="Featured Image" style="width: 100%; max-height: 400px; object-fit: cover; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        </div>
                    <?php endif; ?>

                    <div class="post-content" style="margin-top: 20px; line-height: 1.6;">
                        <?= $current_page_data['content'] ?>
                    </div>
                    <div style="margin-top: 30px;">
                        <a href="index.php" class="nav-link" style="display:inline-block; background:rgba(255,255,255,0.2); padding: 8px 20px; border-radius:10px;">&larr; Kembali ke Beranda</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($current_page_data['custom_js'])): ?>
            <script><?= $current_page_data['custom_js'] ?></script>
        <?php endif; ?>

    <?php else: ?>
        <?php if(count($sliders) > 0): ?>
        <div class="slider-wrapper">
            <div class="slider">
                <ul class="slides">
                    <?php foreach($sliders as $slide): ?>
                    <li>
                        <img src="<?= $slide['image_path'] ?>" alt="Slider Image">
                        <div class="caption center-align">
                            <h3><?= htmlspecialchars($slide['title']) ?></h3>
                            <?php if(!empty($slide['caption'])): ?>
                                <h5 class="light grey-text text-lighten-3"><?= htmlspecialchars($slide['caption']) ?></h5>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php else: ?>
            <div style="margin-top: 120px;"></div> 
        <?php endif; ?>

        <div class="content-container">
            <?php foreach ($posts as $post): ?>
                <div class="post-card glass">
                    <h2 style="margin-top: 0;"><?= htmlspecialchars($post['title']) ?></h2>
                    <div class="post-date">Dipublikasikan: <?= date('d M Y', strtotime($post['created_at'])) ?></div>
                    
                    <?php if (!empty($post['featured_image'])): ?>
                        <div style="margin: 15px 0;">
                            <img src="<?= $post['featured_image'] ?>" alt="Featured Image" style="width: 100%; max-height: 350px; object-fit: cover; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        </div>
                    <?php endif; ?>

                    <div class="post-content" style="margin-top: 15px;">
                        <?= $post['content'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (count($posts) === 0 && count($sliders) === 0): ?>
                <div class="post-card glass" style="text-align:center;">
                    <h3>Belum ada konten</h3>
                    <p>Silakan buat artikel atau halaman baru melalui panel admin.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <nav id="bottom-nav" class="glass">
        <a href="index.php" class="nav-link"><i class="material-icons" style="font-size: 18px;">home</i> Home</a>
        <a href="admin.php" class="nav-link"><i class="material-icons" style="font-size: 18px;">dashboard</i> Admin</a>
        <a href="#" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;" class="nav-link"><i class="material-icons" style="font-size: 18px;">arrow_upward</i> Top</a>
    </nav>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var elems = document.querySelectorAll('.slider');
            M.Slider.init(elems, {
                indicators: <?= $ind_show == 1 ? 'true' : 'false' ?>, 
                height: <?= $s_height ?>, 
                transition: <?= $s_trans ?>, 
                interval: <?= $s_inter ?> 
            });
        });

        let lastScroll = 0;
        const topNav = document.getElementById("top-nav");
        const bottomNav = document.getElementById("bottom-nav");
        
        window.addEventListener("scroll", () => {
            let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            if (currentScroll > lastScroll && currentScroll > 50) {
                topNav.style.top = "-100px";
                bottomNav.style.bottom = "30px";
            } else {
                topNav.style.top = "20px";
                bottomNav.style.bottom = "-100px";
            }
            lastScroll = currentScroll <= 0 ? 0 : currentScroll;
        });
    </script>
</body>
</html>