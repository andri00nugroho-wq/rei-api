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

if ($requested_page && $requested_page !== 'admin' && $requested_page !== 'cabang') {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ?");
    $stmt->execute([$requested_page]);
    $current_page_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

$posts = $pdo->query("SELECT * FROM posts WHERE type='post' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$theme = $pdo->query("SELECT * FROM theme_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$s_set = $pdo->query("SELECT * FROM slider_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Ambil data cabang toko untuk ditampilkan di website
try {
    $branches_list = $pdo->query("SELECT * FROM branches ORDER BY branch_number ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $branches_list = [];
}

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
    <script src="https://cdn.tailwindcss.com"></script>
    
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
        
        .content-container { max-width: 1000px; margin: 120px auto 100px; padding: 0 20px; }
        .post-card { padding: 30px; border-radius: 20px; margin-bottom: 30px; }
        .post-date { font-size: 0.9em; opacity: 0.8; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 15px;}
        
        .slider-wrapper { margin-top: 100px; max-width: 1000px; margin-left: auto; margin-right: auto; padding: 0 20px;}
        .slider { border-radius: <?= $s_radius ?>px !important; box-shadow: 0 8px 32px 0 rgba(0,0,0,0.3); background-color: transparent !important; }
        .slider .slides { border-radius: <?= $s_radius ?>px !important; overflow: hidden !important; -webkit-transform: translateZ(0); transform: translateZ(0); }
        .slider .slides li img { border-radius: <?= $s_radius ?>px !important; }
        .slider .caption { background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(5px); padding: 20px !important; border-radius: 15px; display: inline-block; }

        <?php if ($ind_show == 0): ?>
            .slider .indicators { display: none !important; }
        <?php else: ?>
            .slider .indicators .indicator-item {
                width: <?= $ind_size ?>px !important; height: <?= $ind_size ?>px !important;
                margin: 0 6px !important; background-color: rgba(255, 255, 255, 0.5) !important;
                border: 2px solid rgba(255, 255, 255, 0.8) !important; box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                transition: all 0.3s ease;
            }
            .slider .indicators .indicator-item.active { background-color: #ffffff !important; }
        <?php endif; ?>
    </style>
</head>
<body>

    <nav id="top-nav" class="glass">
        <a href="./" style="text-decoration:none; display: flex; align-items: center;">
            <img src="logo-rei.png" alt="Logo Raja Emas Indonesia" style="max-height: 32px; width: auto; filter: brightness(1.3) contrast(1.1);">
        </a>
        
        <div class="nav-links-container">
            <a href="./" class="nav-link">Home</a>
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
            <a href="cabang" class="nav-link">Cabang Toko</a>
        </div>

        <a href="admin.php" class="nav-link" style="background: rgba(255,255,255,0.2);"><i class="material-icons" style="font-size: 16px;">dashboard</i> Admin</a>
    </nav>

    <!-- Jika halaman yang diminta adalah khusus 'cabang' -->
    <?php if ($requested_page === 'cabang'): ?>
        <div class="content-container">
            <div class="post-card glass">
                <h1 style="margin-top: 0; font-size: 2rem; margin-bottom: 20px;">Daftar Cabang Toko</h1>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if (empty($branches_list)): ?>
                        <p style="opacity: 0.8;">Belum ada cabang toko yang ditambahkan.</p>
                    <?php else: ?>
                        <?php foreach($branches_list as $b): ?>
                        <div style="background: white; color: #111; border: 2px solid #3b1c32; border-radius: 30px; padding: 24px; display: flex; flex-direction: column; justify-content: between; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <div>
                                        <span style="font-size: 1.5rem; font-weight: bold;"><?= htmlspecialchars($b['branch_number']) ?></span>
                                        <h3 style="font-size: 1.25rem; font-weight: bold; margin: 4px 0 0 0; line-height: 1.4;"><?= htmlspecialchars($b['title']) ?></h3>
                                    </div>
                                </div>
                                <p style="font-size: 0.9rem; color: #4b5563; margin-bottom: 16px; line-height: 1.5;"><?= nl2br(htmlspecialchars($b['address'])) ?></p>
                                <p style="font-size: 0.9rem; font-weight: 600; color: #3b1c32; margin-bottom: 12px; text-decoration: underline;">
                                    Hp & Whatsapp: <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $b['phone']) ?>" target="_blank" style="color: #3b1c32;"><?= htmlspecialchars($b['phone']) ?></a>
                                </p>
                                <p style="font-size: 0.9rem; color: #1f2937; margin: 4px 0;">Buka : <?= htmlspecialchars($b['hours']) ?></p>
                                <p style="font-size: 0.9rem; color: #1f2937; margin: 4px 0 16px 0;">Catatan: <?= htmlspecialchars($b['notes']) ?></p>

                                <?php if(!empty($b['map_embed_url'])): ?>
                                <div style="border-radius: 16px; overflow: hidden; height: 160px; border: 1px solid #e5e7eb; margin-bottom: 16px;">
                                    <iframe src="<?= htmlspecialchars($b['map_embed_url']) ?>" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                                </div>
                                <?php endif; ?>
                            </div>

                            <a href="<?= !empty($b['map_link']) ? htmlspecialchars($b['map_link']) : '#' ?>" target="_blank" style="background: #3b1c32; color: white; padding: 12px; border-radius: 16px; text-align: center; text-decoration: none; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                Menuju Lokasi <span class="material-icons" style="font-size: 16px;">north_east</span>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 30px;">
                    <a href="./" class="nav-link" style="display:inline-block; background:rgba(255,255,255,0.2); padding: 8px 20px; border-radius:10px;">&larr; Kembali ke Beranda</a>
                </div>
            </div>
        </div>

    <!-- Tampilan Standar Beranda / Halaman Lain -->
    <?php elseif ($current_page_data): ?>
        <?php if (!empty($current_page_data['custom_css'])): ?><style><?= $current_page_data['custom_css'] ?></style><?php endif; ?>

        <?php if(count($sliders) > 0): ?>
        <div class="slider-wrapper">
            <div class="slider">
                <ul class="slides">
                    <?php foreach($sliders as $slide): ?>
                    <li>
                        <img src="<?= $slide['image_path'] ?>" alt="Slider Image">
                        <div class="caption center-align">
                            <h3><?= htmlspecialchars($slide['title']) ?></h3>
                            <?php if(!empty($slide['caption'])): ?><h5 class="light grey-text text-lighten-3"><?= htmlspecialchars($slide['caption']) ?></h5><?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($current_page_data['custom_html'])): ?>
            <div style="margin-top: 100px;"><?= $current_page_data['custom_html'] ?></div>
        <?php else: ?>
            <div class="content-container">
                <div class="post-card glass">
                    <h1 style="margin-top: 0; font-size: 2rem;"><?= htmlspecialchars($current_page_data['title']) ?></h1>
                    <div class="post-date">Dipublikasikan: <?= date('d M Y', strtotime($current_page_data['created_at'])) ?></div>
                    
                    <?php if (!empty($current_page_data['featured_image'])): ?>
                        <div style="margin: 20px 0;"><img src="<?= $current_page_data['featured_image'] ?>" alt="Featured Image" style="width: 100%; max-height: 400px; object-fit: cover; border-radius: 12px;"></div>
                    <?php endif; ?>

                    <div class="post-content" style="margin-top: 20px; line-height: 1.6;"><?= $current_page_data['content'] ?></div>
                    <div style="margin-top: 30px;"><a href="./" class="nav-link" style="display:inline-block; background:rgba(255,255,255,0.2); padding: 8px 20px; border-radius:10px;">&larr; Kembali ke Beranda</a></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($current_page_data['custom_js'])): ?><script><?= $current_page_data['custom_js'] ?></script><?php endif; ?>

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
                            <?php if(!empty($slide['caption'])): ?><h5 class="light grey-text text-lighten-3"><?= htmlspecialchars($slide['caption']) ?></h5><?php endif; ?>
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
                        <div style="margin: 15px 0;"><img src="<?= $post['featured_image'] ?>" alt="Featured Image" style="width: 100%; max-height: 350px; object-fit: cover; border-radius: 12px;"></div>
                    <?php endif; ?>

                    <div class="post-content" style="margin-top: 15px;"><?= $post['content'] ?></div>
                </div>
            <?php endforeach; ?>

            <!-- Bagian Tampilan Daftar Cabang Toko di Beranda (Opsional / Bisa diakses via menu) -->
            <div class="post-card glass" style="margin-top: 40px;">
                <h2 style="margin-top: 0; font-size: 1.8rem; margin-bottom: 24px;">Daftar Cabang Toko Resmi</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if (empty($branches_list)): ?>
                        <p style="opacity: 0.8;">Belum ada cabang toko yang tersedia.</p>
                    <?php else: ?>
                        <?php foreach($branches_list as $b): ?>
                        <div style="background: white; color: #111; border: 2px solid #3b1c32; border-radius: 30px; padding: 24px; display: flex; flex-direction: column; justify-content: between; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <div>
                                        <span style="font-size: 1.5rem; font-weight: bold;"><?= htmlspecialchars($b['branch_number']) ?></span>
                                        <h3 style="font-size: 1.25rem; font-weight: bold; margin: 4px 0 0 0; line-height: 1.4;"><?= htmlspecialchars($b['title']) ?></h3>
                                    </div>
                                </div>
                                <p style="font-size: 0.9rem; color: #4b5563; margin-bottom: 16px; line-height: 1.5;"><?= nl2br(htmlspecialchars($b['address'])) ?></p>
                                <p style="font-size: 0.9rem; font-weight: 600; color: #3b1c32; margin-bottom: 12px; text-decoration: underline;">
                                    Hp & Whatsapp: <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $b['phone']) ?>" target="_blank" style="color: #3b1c32;"><?= htmlspecialchars($b['phone']) ?></a>
                                </p>
                                <p style="font-size: 0.9rem; color: #1f2937; margin: 4px 0;">Buka : <?= htmlspecialchars($b['hours']) ?></p>
                                <p style="font-size: 0.9rem; color: #1f2937; margin: 4px 0 16px 0;">Catatan: <?= htmlspecialchars($b['notes']) ?></p>

                                <?php if(!empty($b['map_embed_url'])): ?>
                                <div style="border-radius: 16px; overflow: hidden; height: 160px; border: 1px solid #e5e7eb; margin-bottom: 16px;">
                                    <iframe src="<?= htmlspecialchars($b['map_embed_url']) ?>" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                                </div>
                                <?php endif; ?>
                            </div>

                            <a href="<?= !empty($b['map_link']) ? htmlspecialchars($b['map_link']) : '#' ?>" target="_blank" style="background: #3b1c32; color: white; padding: 12px; border-radius: 16px; text-align: center; text-decoration: none; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                Menuju Lokasi <span class="material-icons" style="font-size: 16px;">north_east</span>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <nav id="bottom-nav" class="glass">
        <a href="./" class="nav-link"><i class="material-icons" style="font-size: 18px;">home</i> Home</a>
        <a href="cabang" class="nav-link"><i class="material-icons" style="font-size: 18px;">storefront</i> Cabang</a>
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