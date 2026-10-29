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

require_once __DIR__ . '/site-pages.php';

$requested_page = $_GET['page'] ?? 'home';
// Slug lama (misalnya "beranda" atau "branch") diarahkan ke halaman bawaannya.
$requested_page = rei_site_page_key($requested_page) ?? $requested_page;

// Halaman bawaan website yang isinya diatur dari menu Page di dashboard.
$site_page_rows = rei_load_site_pages($pdo);
$site_pages = [];
foreach ($site_page_rows as $site_key => $site_row) {
    $site_pages[$site_key] = rei_site_page_title($site_key, $site_row);
}
$site_page_data = $site_page_rows[$requested_page] ?? null;
$site_page_intro = isset($site_pages[$requested_page]) ? rei_site_page_intro($requested_page, $site_page_data) : '';
$site_page_custom_html = trim((string) ($site_page_data['custom_html'] ?? ''));
$site_page_banner = !empty($site_page_data['featured_image'])
    ? '<figure class="page-banner"><img src="' . htmlspecialchars((string) $site_page_data['featured_image'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($site_pages[$requested_page] ?? '', ENT_QUOTES, 'UTF-8') . '"></figure>'
    : '';
$current_page_data = null;

if ($requested_page && !isset($site_pages[$requested_page]) && $requested_page !== 'admin') {
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

$escape = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$office_address = trim((string) (getenv('REI_OFFICE_ADDRESS') ?: 'Ruko Sedayu Square, Jalan Kamal Raya Outer Ring Road No.H08, RT.2/RW.12, Cengkareng Barat, Kecamatan Cengkareng, Kota Jakarta Barat, Daerah Khusus Ibukota Jakarta 11730'));
$render_branch_card = static function (array $branch) use ($escape): void {
    $phone = trim((string) ($branch['phone'] ?? ''));
    $phone_digits = preg_replace('/\D+/', '', $phone) ?? '';
    $map_link = trim((string) ($branch['map_link'] ?? ''));
    $map_scheme = strtolower((string) parse_url($map_link, PHP_URL_SCHEME));
    $map_is_safe = in_array($map_scheme, ['http', 'https'], true);
    ?>
    <article class="branch-card glass">
        <div class="branch-card-heading">
            <span class="branch-number"><?= $escape($branch['branch_number'] ?? '') ?: 'REI' ?></span>
            <span class="eyebrow">RAJA EMAS INDONESIA</span>
        </div>
        <h3><?= $escape($branch['title'] ?? 'Cabang Raja Emas') ?></h3>
        <?php if (!empty($branch['address'])): ?>
            <p class="branch-detail"><span class="material-icons">place</span><?= nl2br($escape($branch['address'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($branch['hours'])): ?>
            <p class="branch-detail"><span class="material-icons">schedule</span><?= $escape($branch['hours']) ?></p>
        <?php endif; ?>
        <?php if (!empty($branch['notes'])): ?>
            <p class="branch-note"><?= $escape($branch['notes']) ?></p>
        <?php endif; ?>
        <?php
        $map_embed_url = trim((string) ($branch['map_embed_url'] ?? ''));
        $embed_scheme = strtolower((string) parse_url($map_embed_url, PHP_URL_SCHEME));
        ?>
        <?php if ($map_embed_url !== '' && in_array($embed_scheme, ['http', 'https'], true)): ?>
            <div class="branch-map">
                <iframe src="<?= $escape($map_embed_url) ?>" title="Peta <?= $escape($branch['title'] ?? 'cabang') ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
        <?php endif; ?>
        <div class="branch-actions">
            <?php if ($phone_digits !== ''): ?>
                <a class="button button-secondary" href="https://wa.me/<?= $escape($phone_digits) ?>" target="_blank" rel="noopener noreferrer"><span class="material-icons">chat</span>Hubungi cabang</a>
            <?php endif; ?>
            <?php if ($map_is_safe): ?>
                <a class="button button-primary" href="<?= $escape($map_link) ?>" target="_blank" rel="noopener noreferrer">Petunjuk arah <span class="material-icons">north_east</span></a>
            <?php endif; ?>
        </div>
    </article>
    <?php
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <script>
        // Deteksi jenis perangkat: menandai <html data-device="mobile|tablet|laptop|desktop" data-input="touch|mouse">
        // supaya tata letak menyesuaikan HP, tablet, laptop, dan desktop.
        (function () {
            var root = document.documentElement;
            function detect() {
                var width = window.innerWidth;
                var touch = window.matchMedia('(pointer: coarse) and (hover: none)').matches;
                var device = width < 640 ? 'mobile'
                    : width < 1024 ? 'tablet'
                    : (touch && width < 1366) ? 'tablet'
                    : width < 1440 ? 'laptop' : 'desktop';
                root.dataset.device = device;
                root.dataset.input = touch ? 'touch' : 'mouse';
            }
            detect();
            window.addEventListener('resize', detect, { passive: true });
            window.addEventListener('orientationchange', detect);
        })();
    </script>
    <title><?= $escape($current_page_data['title'] ?? $site_pages[$requested_page ?? 'home'] ?? 'Raja Emas Indonesia') ?> - Raja Emas Indonesia</title>
    
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
            font-family: Helvetica, Arial, sans-serif; 
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
        .home-slider { margin-top: 16px; }

        :root { --gold: #a87922; --gold-light: #8a641f; --ink: #2b2419; --text: #302a22; }
        html { scroll-behavior: smooth; }
        body {
            min-height: 100vh;
            color: var(--text);
            background-color: #fff;
            background-image: radial-gradient(circle at 1px 1px, rgba(142, 103, 31, .22) 1.5px, transparent 1.9px);
            background-size: 24px 24px;
            background-attachment: fixed;
        }
        .glass {
            background: rgba(255,255,255,.94);
            border-color: rgba(141,108,48,.24);
            box-shadow: 0 16px 40px rgba(58,43,20,.11);
        }
        #top-nav { color: var(--ink); }
        .nav-link { color: var(--ink); }
        .nav-link:hover { background: rgba(166,126,48,.1); }
        .site-nav > a:last-child { background: rgba(166,126,48,.1) !important; }
        .eyebrow { color: #87621d; }
        .hero h1, .page-heading h1, .section-heading h2, .content-panel h2,
        .feature-card h3, .service-card h3, .value-card h3, .post-card h3, .branch-card h3 { color: var(--ink); }
        .hero h1 span, .gold-text { color: #9a6d1e; }
        .hero-copy > p, .page-heading > p,
        .feature-card p, .service-card p, .value-card p, .content-panel p, .post-card p { color: #625b50; }
        .feature-card .material-icons, .service-card .material-icons, .value-card .material-icons { color: #a87922; }
        a { color: inherit; }
        .site-nav { width: min(var(--site-max-width), calc(100% - var(--site-gutter))) !important; max-width: var(--site-max-width) !important; padding: 10px 24px !important; gap: 22px; }
        .site-nav { min-height: 88px; }
        .site-nav.glass, #bottom-nav.glass {
            background: linear-gradient(120deg, rgba(55, 24, 82, .94), rgba(112, 52, 143, .9) 52%, rgba(45, 20, 70, .94));
            border-color: rgba(255, 255, 255, .3);
            box-shadow: 0 14px 38px rgba(41, 18, 58, .24), inset 0 1px 0 rgba(255, 255, 255, .18);
            backdrop-filter: blur(22px) saturate(155%);
            -webkit-backdrop-filter: blur(22px) saturate(155%);
        }
        .site-nav.glass, #bottom-nav.glass .nav-link { color: #fff; }
        .site-nav .nav-link, #bottom-nav .nav-link {
            color: rgba(255, 255, 255, .94);
            text-shadow: 0 2px 4px rgba(31, 8, 44, .58), 0 0 1px rgba(31, 8, 44, .8);
        }
        .site-nav .nav-link:hover, #bottom-nav .nav-link:hover { background: rgba(255, 255, 255, .16); }
        .site-logo { width: 155px; height: auto; display: block; filter: brightness(1.2) contrast(1.25) drop-shadow(0 2px 4px rgba(22, 8, 32, .55)); }
        .site-nav .site-logo { width: 230px; height: auto; }
        .site-nav .nav-links-container { gap: 8px; }
        #top-nav .nav-link .material-icons { display: none; }
        #top-nav .nav-link { gap: 0; }
        .nav-links-container { scrollbar-width: none; }
        .nav-links-container::-webkit-scrollbar { display: none; }
        .site-nav .nav-link { position: relative; min-height: 44px; gap: 8px; padding: 10px 14px; border: 1px solid transparent; border-radius: 14px; font-size: 16px; font-weight: 800; white-space: nowrap; transition: color .2s ease, background .2s ease, border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
        .site-nav .nav-link::after { content: ''; position: absolute; right: 14px; bottom: 5px; left: 14px; height: 2px; border-radius: 999px; background: linear-gradient(90deg, #f4d06f, #fff1ad, #f4d06f); transform: scaleX(0); transform-origin: center; transition: transform .2s ease; }
        .site-nav .nav-link:hover, .site-nav .nav-link:focus-visible { color: #fff; background: rgba(255, 255, 255, .14); border-color: rgba(255, 231, 145, .42); box-shadow: 0 8px 18px rgba(27, 10, 42, .2), inset 0 1px 0 rgba(255, 255, 255, .18); transform: translateY(-2px); outline: none; }
        .site-nav .nav-link:hover::after, .site-nav .nav-link:focus-visible::after { transform: scaleX(1); }
        .site-nav .nav-link .material-icons { font-size: 26px; }
        .mobile-menu-toggle { display: none; }
        .language-switch { display: flex; flex: 0 0 auto; align-items: center; gap: 3px; padding: 0; border: 0; border-radius: 0; background: transparent; }
        .language-switch button { min-width: 36px; padding: 7px 4px; border: 0; border-radius: 0; background: transparent; color: rgba(255,255,255,.76); font: inherit; font-size: 16px; font-weight: 800; cursor: pointer; }
        .language-switch button,
        .language-switch button:hover,
        .language-switch button:focus,
        .language-switch button[aria-pressed="true"],
        .language-switch button[aria-pressed="true"]:hover,
        .language-switch button[aria-pressed="true"]:focus {
            background: transparent !important;
            box-shadow: none !important;
            outline: none !important;
            color: #e53935;
        }
        .language-switch button:not([aria-pressed="true"]) { color: #fff; }
        .page-shell { width: min(1440px, calc(100% - 64px)); margin: 0 auto; padding: 130px 0 70px; }
        .hero { min-height: auto; display: flex; flex-direction: column; }
        .hero-slider { order: -1; width: 100%; max-width: 1680px; margin: 24px auto 0; }
        .hero-intro { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(390px, .95fr); gap: 56px; align-items: center; width: 100%; max-width: 1680px; margin: 0 auto 34px; padding: 52px 24px 24px; color: var(--ink); background: transparent; }
        .hero-intro-copy { max-width: 660px; }
        .hero-intro .eyebrow { color: var(--gold-light); }
        .hero-intro h1 { margin: 16px 0 22px; color: var(--ink); font: 500 clamp(2.7rem, 5.2vw, 5rem)/1.05 Helvetica, Arial, sans-serif; letter-spacing: -.045em; }
        .hero-intro h1 span { color: var(--gold-light); }
        .hero-intro-copy > p { max-width: 620px; margin: 0; color: #625b50; font-size: 1.08rem; line-height: 1.7; }
        .hero-intro-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
        .hero-intro-actions .button-secondary { color: var(--ink); border-color: rgba(141,108,48,.3); background: rgba(255,255,255,.8); }
        .hero-benefit-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .hero-benefit-card { min-height: 122px; padding: 19px; border: 1px solid rgba(141,108,48,.2); border-radius: 16px; background: rgba(255,255,255,.9); box-shadow: 0 12px 28px rgba(58,43,20,.08); }
        .hero-benefit-card .material-icons { display: grid; width: 38px; height: 38px; place-items: center; margin-bottom: 19px; border-radius: 11px; color: #a87922; background: rgba(166,126,48,.1); font-size: 21px; }
        .hero-benefit-card strong { display: block; color: var(--ink); font-size: 1rem; }
        .hero-benefit-card span:last-child { display: block; margin-top: 5px; color: #625b50; font-size: .83rem; }
        .hero-copy { max-width: 690px; }
        .eyebrow { color: var(--gold-light); font-size: .75rem; font-weight: 700; letter-spacing: .22em; text-transform: uppercase; }
        .hero h1, .page-heading h1 { margin: 15px 0 22px; font-family: Helvetica, Arial, sans-serif; font-size: clamp(2.8rem, 6vw, 5.3rem); line-height: 1.04; font-weight: 500; }
        .hero h1 span, .gold-text { color: var(--gold-light); }
        .hero-copy > p, .page-heading > p { max-width: 640px; color: rgba(255,255,255,.84); font-size: 1.07rem; line-height: 1.8; }
        .hero-slider { overflow: hidden; border-radius: 30px; background: #3e2144; box-shadow: 0 24px 60px rgba(48,28,42,.2); }
        .hero-slide { display: none; }
        .hero-slide.is-active { display: block; animation: hero-fade-in .55s ease both; }
        .hero-slide img { display: block; width: 100%; height: 700px; aspect-ratio: auto; object-fit: fill; background: #3e2144; }
        .hero-slide-caption { min-height: 112px; display: flex; justify-content: space-between; align-items: center; gap: 24px; padding: 20px 28px; color: #fff; background: linear-gradient(110deg, #38203e, #5c2b5b); }
        .hero-slider-controls-bar { min-height: 74px; }
        .hero-slide-caption .eyebrow { color: #f6dc91; }
        .hero-slide-caption h2 { margin: 5px 0 0; color: #fff; font: 500 clamp(1.2rem, 2.6vw, 2rem)/1.2 Helvetica, Arial, sans-serif; }
        .hero-slide-controls { display: flex; align-items: center; gap: 10px; flex: 0 0 auto; }
        .hero-slide-arrow { width: 42px; height: 42px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.45); border-radius: 50%; background: rgba(255,255,255,.1); color: #fff; cursor: pointer; }
        .hero-slide-arrow { display: none; }
        .hero-slide-arrow:hover { background: rgba(255,255,255,.22); }
        .hero-slide-dots { display: flex; align-items: center; gap: 7px; padding: 0 4px; }
        .hero-slide-dot { width: 9px; height: 9px; padding: 0; border: 0; border-radius: 50%; background: rgba(255,255,255,.46); cursor: pointer; }
        .hero-slide-dot[aria-current="true"] { width: 24px; border-radius: 99px; background: #f6dc91; }
        @keyframes hero-fade-in { from { opacity: .45; } to { opacity: 1; } }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .hero-slide.is-active { animation: none; }
        }
        .button-row, .branch-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
        .button { min-height: 46px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 18px; border-radius: 12px; text-decoration: none; font-weight: 700; transition: transform .2s ease, background .2s ease; }
        .button:hover { transform: translateY(-2px); opacity: .95; }
        .button-primary { background: linear-gradient(135deg, #f7e4b5, #dfbd72); color: var(--ink); }
        .button-secondary { color: var(--ink); border: 1px solid rgba(141,108,48,.3); background: rgba(255,255,255,.8); }
        .button .material-icons { font-size: 19px; }
        .section { margin-top: 52px; }
        .benefit-strip, .stat-strip { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .benefit-item, .stat-item { padding: 18px 16px; border-radius: 18px; background: rgba(255,255,255,.9); border: 1px solid rgba(141,108,48,.18); }
        .benefit-item { display: flex; align-items: flex-start; gap: 11px; }
        .benefit-item .material-icons { color: #a87922; font-size: 27px; }
        .benefit-item strong, .stat-item strong { display: block; color: var(--ink); font-size: .98rem; }
        .benefit-item span:last-child { display: block; margin-top: 3px; color: #625b50; font-size: .82rem; line-height: 1.45; }
        .stat-strip { margin-top: 18px; padding: 18px; border: 1px solid rgba(80,51,95,.12); border-radius: 24px; background: #fff; color: var(--ink); box-shadow: 0 18px 42px rgba(58,43,20,.1); }
        .stat-item { background: linear-gradient(145deg, #45234f, #7a4786); border-color: rgba(255,255,255,.2); color: #fff; text-align: center; box-shadow: inset 0 1px 0 rgba(255,255,255,.12); }
        .stat-item strong { color: #f6dc91; font: 500 clamp(1.45rem, 3vw, 2.2rem) Helvetica, Arial, sans-serif; }
        .stat-item span { display: block; margin-top: 5px; color: rgba(255,255,255,.82); font-size: .84rem; }
        .stat-strip { display: block; }
        .stat-strip-layout { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(300px, .75fr); gap: 18px; align-items: stretch; }
        .stat-items { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .xrf-illustration { position: relative; min-height: 205px; overflow: hidden; margin: 0; border: 1px solid rgba(255,255,255,.18); border-radius: 18px; background: #3c2048; }
        .xrf-illustration::after { position: absolute; inset: 0; content: ''; background: linear-gradient(180deg, rgba(42,18,53,.02) 25%, rgba(42,18,53,.8)); pointer-events: none; }
        .xrf-illustration > img:first-child { width: 100%; height: 100%; min-height: 205px; object-fit: cover; object-position: 82% center; display: block; opacity: .9; }
        .xrf-illustration figcaption { position: absolute; right: 18px; bottom: 16px; left: 18px; z-index: 2; color: #fff; font-weight: 700; line-height: 1.4; }
        .xrf-illustration figcaption span { display: block; margin-bottom: 3px; color: #f6dc91; font-size: .7rem; letter-spacing: .12em; text-transform: uppercase; }
        .service-card.service-card-highlight { background: linear-gradient(135deg, #4a2850, #74436f); color: #fff; }
        .service-card.service-card-highlight h3, .service-card.service-card-highlight p { color: #fff; }
        .service-card.service-card-highlight p { color: rgba(255,255,255,.8); }
        .service-card.service-card-highlight .material-icons { color: #f6dc91; }
        .service-card.service-card-highlight {
            background: #fff;
            border: 1px solid rgba(80, 51, 95, .11);
            color: var(--ink);
        }
        .service-card.service-card-highlight h3 { color: var(--ink); }
        .service-card.service-card-highlight p { color: #625b50; }
        .service-card.service-card-highlight .material-icons { color: #a87922; }
        .faq-list { display: grid; gap: 10px; }
        .faq-list details { padding: 18px 20px; border: 1px solid rgba(141,108,48,.2); border-radius: 16px; background: rgba(255,255,255,.9); }
        .faq-list summary { color: var(--ink); font-weight: 700; cursor: pointer; }
        .faq-list details p { margin: 13px 0 0; color: #625b50; line-height: 1.65; }
        .testimonial-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .testimonial-card { padding: 24px; border-radius: 22px; background: #fff; border: 1px solid rgba(141,108,48,.18); }
        .testimonial-card blockquote { margin: 0 0 16px; color: #4f493f; line-height: 1.7; }
        .testimonial-card cite { color: #87621d; font-style: normal; font-weight: 700; }
        .quick-contact { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 24px 28px; border-radius: 24px; background: linear-gradient(115deg, #f5e4bd, #e6c77d); }
        .quick-contact h2 { margin: 0 0 7px; color: var(--ink); font: 500 clamp(1.45rem, 3vw, 2.1rem) Helvetica, Arial, sans-serif; }
        .quick-contact p { margin: 0; color: #62502b; }
        .quick-contact .button { background: #3d2343; color: #fff; }
        .section-heading { display: flex; justify-content: space-between; align-items: end; gap: 20px; margin-bottom: 20px; }
        .section-heading h2, .content-panel h2 { margin: 8px 0 0; font: 500 clamp(1.8rem, 4vw, 2.7rem)/1.15 Helvetica, Arial, sans-serif; }
        .feature-grid, .service-grid, .value-grid, .post-grid, .branch-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .feature-card, .service-card, .value-card, .content-panel, .post-card, .branch-card { border-radius: 24px; padding: 28px; }
        .feature-card .material-icons, .service-card .material-icons, .value-card .material-icons { color: var(--gold-light); font-size: 30px; }
        .feature-card h3, .service-card h3, .value-card h3, .post-card h3, .branch-card h3 { margin: 14px 0 9px; font-size: 1.13rem; font-weight: 700; }
        .feature-card p, .service-card p, .value-card p, .content-panel p, .post-card p { color: #625b50; line-height: 1.75; margin-bottom: 0; }
        .page-heading { max-width: 800px; margin-bottom: 32px; }
        .page-banner { margin: 0 0 34px; overflow: hidden; border-radius: 24px; }
        .page-banner img { display: block; width: 100%; max-height: 460px; object-fit: cover; }
        .page-heading h1 { font-size: clamp(2.6rem, 5vw, 4.6rem); }
        .content-panel { margin-bottom: 20px; }
        .content-panel p + p { margin-top: 14px; }
        .split-panel { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; align-items: center; }
        .content-panel .split-panel { align-items: start; }
        .content-panel .quote-panel { margin: 0 0 14px; }
        .about-profile { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(340px, .85fr); gap: 24px; align-items: stretch; }
        .about-profile-copy { padding: 34px; border-radius: 24px; background: rgba(255,255,255,.92); border: 1px solid rgba(80,51,95,.12); }
        .about-profile-copy h2, .about-section-heading h2 { margin: 8px 0 12px; color: var(--ink); font: 500 clamp(1.8rem, 3vw, 2.7rem)/1.15 Helvetica, Arial, sans-serif; }
        .about-profile-copy p { color: var(--muted-text); line-height: 1.8; }
        .about-profile-copy p + p { margin-top: 14px; }
        .about-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 24px; border-radius: 24px; background: linear-gradient(145deg, #39203f, #70417b); color: #fff; box-shadow: 0 18px 42px rgba(48,28,42,.16); }
        .about-metric { display: flex; min-height: 118px; flex-direction: column; justify-content: space-between; padding: 17px; border: 1px solid rgba(255,255,255,.16); border-radius: 16px; background: rgba(255,255,255,.1); }
        .about-metric strong { color: #f6dc91; font: 500 clamp(1.55rem, 3vw, 2.25rem) Helvetica, Arial, sans-serif; }
        .about-metric span { color: rgba(255,255,255,.78); font-size: .84rem; }
        .about-section-heading { display: flex; justify-content: space-between; align-items: end; gap: 20px; margin-bottom: 20px; }
        .about-section-heading p { max-width: 520px; margin: 0; color: var(--muted-text); line-height: 1.7; }
        .process-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .process-card { position: relative; padding: 24px; border: 1px solid rgba(80,51,95,.12); border-radius: 20px; background: rgba(255,255,255,.9); }
        .process-card .process-number { display: grid; width: 38px; height: 38px; place-items: center; margin-bottom: 18px; border-radius: 50%; background: #4b2854; color: #f6dc91; font-weight: 800; }
        .process-card h3 { margin: 0 0 8px; color: var(--ink); font-size: 1.05rem; }
        .process-card p { margin: 0; color: var(--muted-text); line-height: 1.65; font-size: .92rem; }
        .about-source { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-top: 22px; padding: 18px 22px; border-radius: 18px; background: linear-gradient(115deg, #f5e4bd, #e6c77d); }
        .about-source p { margin: 0; color: #62502b; line-height: 1.55; }
        .about-source strong { color: var(--ink); }
        .value-showcase { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(300px, .75fr); gap: 18px; align-items: stretch; }
        .value-showcase .value-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .about-illustration { position: relative; min-height: 395px; overflow: hidden; margin: 0; border-radius: 24px; background: linear-gradient(145deg, #3c1f48, #7a3e86); box-shadow: 0 18px 42px rgba(48,28,42,.16); }
        .about-illustration::after { position: absolute; inset: 0; content: ''; background: linear-gradient(180deg, transparent 34%, rgba(34,15,43,.76)); pointer-events: none; }
        .about-illustration img { width: 100%; height: 100%; min-height: 395px; object-fit: cover; object-position: 76% center; display: block; }
        .about-illustration figcaption { position: absolute; right: 22px; bottom: 20px; left: 22px; z-index: 1; color: #fff; font-weight: 700; line-height: 1.45; }
        .about-illustration figcaption span { display: block; margin-bottom: 5px; color: #f6dc91; font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; }
        .service-card .button { margin-top: 22px; }
        .content-panel .button-primary { margin: 22px 0 0 10px; }
        .gold-rule { width: 72px; height: 2px; background: var(--gold); margin: 20px 0; }
        .quote-panel { border-left: 2px solid var(--gold); padding-left: 22px; color: #69501f; font: italic 1.35rem/1.6 Helvetica, Arial, sans-serif; }
        .post-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .post-card { display: flex; flex-direction: column; }
        .post-card img { width: 100%; height: 220px; object-fit: cover; border-radius: 16px; margin-bottom: 18px; }
        .post-card .post-date { border: 0; padding: 0; margin-top: auto; padding-top: 18px; }
        .post-card .button { align-self: flex-start; margin-top: 18px; }
        .branch-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .branch-card { display: flex; flex-direction: column; background: rgba(255,255,255,.9); }
        .branch-card-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .branch-number { color: var(--gold-light); font: 1.3rem Helvetica, Arial, sans-serif; }
        .branch-card .eyebrow { font-size: .62rem; letter-spacing: .13em; }
        .branch-detail { display: flex; align-items: flex-start; gap: 9px; color: #625b50; line-height: 1.6; margin: 7px 0; }
        .branch-detail .material-icons { font-size: 19px; color: #a87922; }
        .branch-note { color: #625b50; line-height: 1.6; }
        .branch-map { height: 190px; overflow: hidden; border-radius: 15px; margin-top: 12px; }
        .branch-map iframe { width: 100%; height: 100%; border: 0; }
        .branch-actions { margin-top: auto; padding-top: 18px; }
        .empty-state { padding: 28px; border: 1px dashed rgba(141,108,48,.35); border-radius: 20px; color: #625b50; background: rgba(255,255,255,.8); line-height: 1.7; }
        .contact-list { display: grid; gap: 12px; margin-top: 18px; }
        .contact-item { display: flex; gap: 12px; align-items: flex-start; padding: 16px; border-radius: 16px; background: rgba(166,126,48,.07); }
        .contact-item .material-icons { color: #a87922; }
        .contact-layout { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(320px, .85fr); gap: 20px; align-items: stretch; }
        .contact-form-panel, .contact-info-panel, .contact-process-panel { padding: 30px; border-radius: 24px; }
        .contact-form-panel { background: linear-gradient(145deg, rgba(255,255,255,.97), rgba(249,244,235,.9)); border: 1px solid rgba(141,108,48,.2); box-shadow: 0 18px 42px rgba(58,43,20,.1); }
        .contact-form-panel h2, .contact-info-panel h2, .contact-process-panel h2 { margin: 8px 0 8px; color: var(--ink); font: 500 clamp(1.65rem, 3vw, 2.35rem)/1.15 Helvetica, Arial, sans-serif; }
        .contact-form-panel > p, .contact-info-panel > p { color: #625b50; line-height: 1.7; }
        .contact-form { display: grid; gap: 16px; margin-top: 24px; }
        .contact-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .contact-field { display: grid; gap: 7px; }
        .contact-field label { color: var(--ink); font-size: .88rem; font-weight: 800; }
        .contact-field input, .contact-field select, .contact-field textarea { width: 100%; padding: 13px 15px; border: 1px solid rgba(80,51,95,.2); border-radius: 13px; background: rgba(255,255,255,.85); color: var(--text); font: inherit; outline: none; transition: border-color .2s ease, box-shadow .2s ease, background .2s ease; }
        .contact-field textarea { min-height: 138px; resize: vertical; }
        .contact-field input::placeholder, .contact-field textarea::placeholder { color: #9b9288; }
        .contact-field input:focus, .contact-field select:focus, .contact-field textarea:focus { border-color: #9c6ab0; background: #fff; box-shadow: 0 0 0 4px rgba(112,52,143,.12); }
        .contact-form .button { justify-self: start; border: 0; cursor: pointer; }
        .contact-form-note { margin: 0; color: #81776d; font-size: .82rem; line-height: 1.55; }
        .contact-side { display: grid; gap: 20px; }
        .contact-info-panel { background: linear-gradient(145deg, #42234d, #6e3c80); color: #fff; box-shadow: 0 18px 42px rgba(48,28,42,.18); }
        .contact-info-panel h2, .contact-info-panel > p { color: #fff; }
        .contact-info-panel > p { color: rgba(255,255,255,.78); }
        .contact-info-panel .contact-list { margin-top: 20px; }
        .contact-info-panel .contact-item { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.12); }
        .contact-info-panel .contact-item .material-icons { color: #f6dc91; }
        .contact-info-panel .contact-item strong { color: #f6dc91; }
        .contact-info-panel .contact-item p { margin: 4px 0 0; color: rgba(255,255,255,.75); line-height: 1.55; }
        .contact-info-panel > .button { margin-top: 22px; border-radius: 12px; }
        .contact-process-panel { background: linear-gradient(115deg, #f5e4bd, #e6c77d); }
        .contact-process-panel h2 { font-size: 1.55rem; }
        .contact-process-list { display: grid; gap: 13px; margin: 18px 0 0; padding: 0; list-style: none; }
        .contact-process-list li { display: grid; grid-template-columns: 32px 1fr; gap: 11px; align-items: start; color: #62502b; line-height: 1.5; }
        .contact-process-list b { display: grid; width: 32px; height: 32px; place-items: center; border-radius: 50%; background: #3d2343; color: #f6dc91; font-size: .85rem; }
        .contact-process-list strong { display: block; color: var(--ink); }
        .post-card > img { width: 100%; height: 220px; object-fit: cover; border-radius: 16px; margin: 0 0 18px; }
        .post-content { color: #4f493f; font-size: 1.05rem; line-height: 1.85; }
        .post-content p { margin: 0 0 18px; }
        .post-detail-image { width: 100%; max-height: 460px; object-fit: cover; border-radius: 20px; margin: 22px 0; }
        .site-footer { width: min(1440px, calc(100% - 64px)); margin: 0 auto 34px; padding: 28px; border-radius: 24px; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 24px; color: #625b50; }
        .site-footer img { width: 140px; height: auto; filter: brightness(.76) contrast(1.2); }
        .footer-address { display: flex; align-items: flex-start; gap: 12px; }
        .footer-address .material-icons { color: #a87922; }
        .footer-address strong { color: var(--ink); }
        .footer-address p { margin: 4px 0 0; line-height: 1.6; }
        .site-footer small { white-space: nowrap; color: #625b50; }
        .branch-search { width: 100%; max-width: 560px; margin: 0 0 26px; padding: 15px 18px; border: 1px solid rgba(141,108,48,.3); border-radius: 14px; background: #fff; color: var(--ink); font: inherit; box-shadow: 0 8px 24px rgba(58,43,20,.06); }
        .branch-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .branch-result { min-width: 0; }
        .branch-result-count { margin: 12px 0 18px; color: #625b50; font-size: .9rem; }
        .branch-result-count a { color: #765415; font-weight: 700; }
        .branch-no-results[hidden] { display: none; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .chatbot-launcher { position: fixed; right: 24px; bottom: 24px; z-index: 1200; width: 62px; height: 62px; display: grid; place-items: center; border: 0; border-radius: 50%; color: #fff; background: linear-gradient(145deg, #623366, #39213f); box-shadow: 0 12px 32px rgba(45,27,45,.3); cursor: pointer; }
        .chatbot-launcher .material-icons { font-size: 28px; }
        .chatbot-panel { position: fixed; right: 24px; bottom: 98px; z-index: 1200; width: min(390px, calc(100vw - 32px)); max-height: min(580px, calc(100vh - 130px)); display: none; flex-direction: column; overflow: hidden; border: 1px solid rgba(141,108,48,.24); border-radius: 22px; background: #fff; box-shadow: 0 20px 70px rgba(36,25,37,.24); }
        .chatbot-panel.is-open { display: flex; }
        .chatbot-header { display: flex; justify-content: space-between; align-items: center; padding: 17px 19px; color: #fff; background: linear-gradient(110deg, #38203e, #5c2b5b); }
        .chatbot-header strong { display: block;         font-size: 20px; }
        .chatbot-header small { color: #f6dc91; }
        .chatbot-close { width: 36px; height: 36px; display: grid; place-items: center; border: 0; border-radius: 50%; background: rgba(255,255,255,.13); color: white; cursor: pointer; }
        .chatbot-messages { display: flex; flex: 1; flex-direction: column; gap: 12px; overflow-y: auto; padding: 18px; color: var(--ink); }
        .chat-message { max-width: 90%; padding: 12px 14px; border-radius: 15px; background: #f4f0e8; line-height: 1.55; }
        .chat-message.is-user { align-self: flex-end; color: #fff; background: #563158; }
        .chat-message a { color: #765415; font-weight: 700; }
        .chat-quick-replies { display: flex; flex-wrap: wrap; gap: 8px; }
        .chat-quick-replies button { padding: 8px 11px; border: 1px solid rgba(141,108,48,.28); border-radius: 99px; background: #fff; color: var(--ink); font: inherit; font-size: .83rem; cursor: pointer; }
        .chatbot-form { display: flex; gap: 8px; padding: 12px; border-top: 1px solid #eee8dd; }
        .chatbot-form input { min-width: 0; flex: 1; padding: 11px 13px; border: 1px solid #e2d9c8; border-radius: 99px; color: var(--ink); font: inherit; }
        .chatbot-form button { width: 44px; height: 44px; display: grid; place-items: center; border: 0; border-radius: 50%; background: #57315a; color: #fff; cursor: pointer; }
        @media (max-width: 900px) {
            .site-nav { justify-content: center !important; flex-wrap: wrap; border-radius: 26px !important; }
            .site-nav > a:first-child { margin-right: auto; }
            .nav-links-container { order: 3; width: 100%; justify-content: center; overflow-x: auto; }
            .nav-link { font-size: .77rem; }
            .hero { padding-top: 20px; }
            .feature-grid, .service-grid, .value-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .benefit-strip, .stat-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .testimonial-grid { grid-template-columns: 1fr; }
            .quick-contact { align-items: flex-start; flex-direction: column; }
            .hero-intro { grid-template-columns: 1fr; gap: 30px; padding-top: 62px; padding-bottom: 58px; }
            .contact-layout { grid-template-columns: 1fr; }
            .about-profile { grid-template-columns: 1fr; }
            .process-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .value-showcase { grid-template-columns: 1fr; }
            .stat-strip-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .page-shell { width: min(100% - 28px, 1440px); padding-top: 155px; }
            .site-nav { top: 10px !important; padding: 9px 12px !important; }
            .site-logo { width: 125px; }
            .site-nav > a:last-child { font-size: .75rem; }
            .nav-links-container { justify-content: flex-start; }
            .nav-link { padding: 7px 8px; }
            .hero-slider { border-radius: 20px; }
            .hero-slide img { height: 700px; aspect-ratio: auto; object-fit: fill; }
            .hero-slide-caption { min-height: 0; align-items: flex-start; padding: 17px; gap: 12px; }
            .hero-slider-controls-bar { align-items: center; flex-direction: row; }
            .hero-slide-caption h2 { font-size: 1.1rem; }
            .hero-slide-controls { gap: 6px; }
            .hero-slide-arrow { width: 36px; height: 36px; }
            .hero-slide-dot { width: 7px; height: 7px; }
            .hero-slide-dot[aria-current="true"] { width: 17px; }
            .feature-grid, .service-grid, .value-grid, .post-grid, .branch-grid, .split-panel { grid-template-columns: 1fr; }
            .section-heading { align-items: flex-start; flex-direction: column; }
            .feature-card, .service-card, .value-card, .content-panel, .post-card, .branch-card { padding: 21px; }
            .contact-form-panel, .contact-info-panel, .contact-process-panel { padding: 21px; }
            .contact-form-grid { grid-template-columns: 1fr; }
            .about-profile-copy { padding: 21px; }
            .about-source { align-items: flex-start; flex-direction: column; }
            .process-grid { grid-template-columns: 1fr; }
            .about-illustration, .about-illustration img { min-height: 280px; }
            .stat-items { grid-template-columns: 1fr; }
            .site-footer { width: calc(100% - 28px); grid-template-columns: 1fr; align-items: flex-start; }
            .branch-grid { grid-template-columns: 1fr; }
            #bottom-nav { max-width: calc(100% - 24px); gap: 8px; padding: 10px 12px; }
            #bottom-nav .nav-link { font-size: .75rem; padding: 6px; }
            .chatbot-launcher { right: 16px; bottom: 16px; width: 56px; height: 56px; }
            .chatbot-panel { right: 16px; bottom: 84px; }
            .benefit-strip, .stat-strip { grid-template-columns: 1fr; }
        }

        :root {
            --gold: #bc9551;
            --gold-light: #a77b35;
            --ink: #26172f;
            --text: #342a38;
            --deep-purple: #2c1b36;
            --mid-purple: #50335f;
            --muted-text: #6f6672;
            --site-max-width: 1680px;
            --site-gutter: 64px;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: var(--text);
            background-color: #f8f6f3;
            background-image:
                radial-gradient(ellipse at 12% 8%, rgba(173, 137, 76, .08), transparent 28%),
                radial-gradient(circle at 1px 1px, rgba(80, 51, 95, .09) 1px, transparent 1.7px);
            background-size: auto, 30px 30px;
        }
        .site-nav, .page-shell, .content-container, .slider-wrapper, .site-footer {
            width: min(var(--site-max-width), calc(100% - var(--site-gutter)));
            max-width: var(--site-max-width);
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box;
        }
        .site-nav { left: 50%; transform: translateX(-50%); }
        .site-nav { width: min(1680px, calc(100% - var(--site-gutter))) !important; }
        .page-shell { flex: 1 0 auto; padding: 136px 0 70px; }
        .content-container { flex: 1 0 auto; padding: 0; }
        .page-custom-content { flex: 1 0 auto; width: min(var(--site-max-width), calc(100% - var(--site-gutter))); max-width: var(--site-max-width); margin: 100px auto 0; }
        .slider-wrapper { padding: 0; }
        .page-heading {
            position: relative;
            isolation: isolate;
            max-width: none;
            overflow: hidden;
            margin: 0 0 46px;
            padding: clamp(34px, 5vw, 72px);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 30px;
            background: linear-gradient(118deg, #2a1934 0%, #40264f 62%, #573664 100%);
            box-shadow: 0 24px 60px rgba(43, 26, 52, .16);
        }
        .page-heading::after {
            position: absolute;
            z-index: -1;
            top: -40%;
            right: -5%;
            width: min(42vw, 560px);
            aspect-ratio: 1;
            border: 1px solid rgba(231, 199, 136, .2);
            border-radius: 50%;
            box-shadow: 0 0 0 36px rgba(231, 199, 136, .035), 0 0 0 84px rgba(231, 199, 136, .025);
            content: "";
        }
        .page-heading .eyebrow, .content-panel .eyebrow { color: #e5c98f; }
        .page-heading h1 { max-width: 980px; color: #fff; }
        .page-heading .gold-text { color: #e9ca88; }
        .page-heading > p { max-width: 760px; color: rgba(255, 255, 255, .78); }
        .hero-copy > p { color: var(--muted-text); }
        .hero h1 { color: var(--ink); }
        .hero h1 span, .gold-text { color: var(--gold-light); }
        .eyebrow { color: var(--gold-light); }
        .section { margin-top: 64px; }
        .section-heading { margin-bottom: 24px; }
        .section-heading h2, .feature-card h3, .service-card h3, .value-card h3,
        .post-card h3, .branch-card h3 { letter-spacing: -.02em; }
        .glass { border-color: rgba(80, 51, 95, .11); }
        .feature-card, .service-card, .value-card, .post-card, .branch-card {
            border-radius: 22px;
            box-shadow: 0 12px 34px rgba(48, 36, 53, .07);
            transition: transform .22s ease, box-shadow .22s ease;
        }
        .feature-card:hover, .service-card:hover, .value-card:hover, .post-card:hover, .branch-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 42px rgba(48, 36, 53, .12);
        }
        .feature-card, .service-card, .value-card, .post-card { background: rgba(255, 255, 255, .94); }
        .feature-card:nth-child(2), .service-card:nth-child(2), .value-card:nth-child(2) {
            border-color: rgba(255, 255, 255, .12);
            background: linear-gradient(145deg, #30203a, #493057);
        }
        .feature-card:nth-child(2) h3, .service-card:nth-child(2) h3, .value-card:nth-child(2) h3 { color: #fff; }
        .feature-card:nth-child(2) p, .service-card:nth-child(2) p, .value-card:nth-child(2) p { color: rgba(255, 255, 255, .76); }
        .feature-card:nth-child(2) .material-icons, .service-card:nth-child(2) .material-icons, .value-card:nth-child(2) .material-icons { color: #e5c98f; }
        .content-panel.glass {
            border-color: rgba(255, 255, 255, .12);
            background: linear-gradient(120deg, #2b1a35, #493057);
            box-shadow: 0 20px 48px rgba(43, 26, 52, .16);
        }
        .content-panel h2 { color: #fff; }
        .content-panel p { color: rgba(255, 255, 255, .76); }
        .content-panel .quote-panel { color: #f0dcae; border-color: #d2ad68; }
        .content-panel .button-secondary { color: #fff; border-color: rgba(255, 255, 255, .3); background: rgba(255, 255, 255, .08); }
        .button-primary { background: linear-gradient(135deg, #ead6a8, #c9a45f); }
        .button-secondary { border-color: rgba(80, 51, 95, .18); background: #fff; color: var(--deep-purple); }
        .service-card:nth-child(2) {
            border-color: rgba(80, 51, 95, .11);
            background: #fff;
        }
        .service-card:nth-child(2) h3 { color: var(--ink); }
        .service-card:nth-child(2) p { color: #625b50; }
        .service-card:nth-child(2) .material-icons { color: #a87922; }
        .service-card .button-primary {
            background: #fff;
            border: 1px solid rgba(141, 108, 48, .3);
            color: var(--ink);
        }
        .post-card img, .post-card > img { border-radius: 14px; }
        .post-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .branch-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .branch-result { display: flex; min-width: 0; }
        .branch-card { width: 100%; height: 100%; border: 1px solid rgba(80, 51, 95, .12); background: #fff; }
        .branch-card-heading .eyebrow, .branch-number { color: var(--gold-light); }
        .branch-detail, .branch-note, .branch-result-count { color: var(--muted-text); }
        .branch-detail .material-icons, .contact-item .material-icons { color: var(--mid-purple); }
        .branch-search { max-width: 760px; border-color: rgba(80, 51, 95, .2); }
        #branch-search { box-sizing: border-box; height: 54px; margin: 20px 0 26px !important; padding: 15px 18px; border: 1px solid rgba(80, 51, 95, .2) !important; border-radius: 14px !important; background: #fff !important; color: var(--ink); box-shadow: 0 8px 24px rgba(58,43,20,.08) !important; }
        #branch-search:focus { border-color: #9c6ab0 !important; box-shadow: 0 0 0 4px rgba(112,52,143,.12), 0 8px 24px rgba(58,43,20,.08) !important; }
        .contact-item { background: rgba(80, 51, 95, .07); }
        .empty-state { border-color: rgba(80, 51, 95, .22); color: var(--muted-text); }
        .site-footer {
            flex: 0 0 auto;
            margin-top: auto;
            margin-bottom: 34px;
            border-color: rgba(255, 255, 255, .12);
            background: linear-gradient(115deg, #2b1a35, #493057);
            color: rgba(255, 255, 255, .75);
            box-shadow: 0 20px 48px rgba(43, 26, 52, .15);
        }
        .site-footer img { filter: brightness(0) invert(1); }
        .footer-address .material-icons { color: #e5c98f; }
        .footer-credit { display: flex; align-items: center; justify-content: center; gap: 6px; color: #fff; font-weight: 700; letter-spacing: .02em; text-align: center; }
        .footer-credit .footer-heart { color: #ff4d67; font-size: 20px; }
        .footer-address strong, .site-footer small { color: #fff; }
        .chatbot-launcher { background: linear-gradient(145deg, #60416e, #2c1b36); }
        .chatbot-header { background: linear-gradient(110deg, #2b1a35, #573664); }
        .chat-message.is-user, .chatbot-form button { background: #493057; }
        .chat-message a { color: #68477b; }
        @media (min-width: 1500px) {
            .branch-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (min-width: 901px) and (max-width: 1500px) {
            .site-nav { justify-content: space-between !important; flex-wrap: nowrap; min-height: 78px; border-radius: 26px !important; }
            .site-nav > a:first-child { margin-right: 0; }
            .site-nav .site-logo { width: 178px; }
            .mobile-menu-toggle {
                width: 48px;
                height: 48px;
                display: grid;
                place-items: center;
                flex: 0 0 auto;
                border: 1px solid rgba(255, 255, 255, .32);
                border-radius: 15px;
                background: rgba(255, 255, 255, .12);
                color: #fff;
                cursor: pointer;
            }
            .mobile-menu-toggle .material-icons { font-size: 25px; }
            .site-nav .nav-links-container {
                position: absolute;
                top: calc(100% + 10px);
                left: 0;
                right: 0;
                z-index: 1001;
                display: flex;
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 4px;
                padding: 10px;
                overflow: hidden;
                border: 1px solid rgba(255, 255, 255, .28);
                border-radius: 22px;
                background: linear-gradient(135deg, rgba(50, 29, 64, .97), rgba(89, 52, 108, .97));
                box-shadow: 0 18px 42px rgba(34, 19, 43, .28);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transform: translateY(-8px);
                transition: opacity .18s ease, transform .18s ease, visibility .18s ease;
            }
            .site-nav .nav-links-container.is-open {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transform: translateY(0);
            }
            .site-nav .nav-links-container .nav-link {
                min-height: 60px;
                justify-content: flex-start;
                padding: 10px 14px;
                border-radius: 13px;
                color: rgba(255, 255, 255, .96);
                font-size: 16px;
                font-weight: 800;
            }
            .site-nav .nav-links-container .nav-link:hover { background: rgba(255, 255, 255, .15); }
        }
        @media (max-width: 900px) {
            :root { --site-gutter: 48px; }
            .site-nav { justify-content: space-between !important; flex-wrap: nowrap; min-height: 78px; border-radius: 26px !important; }
            .site-nav > a:first-child { margin-right: 0; }
            .mobile-menu-toggle {
                width: 48px;
                height: 48px;
                display: grid;
                place-items: center;
                flex: 0 0 auto;
                border: 1px solid rgba(255, 255, 255, .32);
                border-radius: 15px;
                background: rgba(255, 255, 255, .12);
                color: #fff;
                cursor: pointer;
            }
            .mobile-menu-toggle .material-icons { font-size: 25px; }
            .site-nav .site-logo { width: 178px; }
            .language-switch { margin-left: auto; }
            .language-switch button { min-width: 38px; padding: 7px 8px; }
            .site-nav .nav-links-container {
                position: absolute;
                top: calc(100% + 10px);
                left: 0;
                right: 0;
                z-index: 1001;
                display: flex;
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 4px;
                padding: 10px;
                overflow: hidden;
                border: 1px solid rgba(255, 255, 255, .28);
                border-radius: 22px;
                background: linear-gradient(135deg, rgba(50, 29, 64, .97), rgba(89, 52, 108, .97));
                box-shadow: 0 18px 42px rgba(34, 19, 43, .28);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transform: translateY(-8px);
                transition: opacity .18s ease, transform .18s ease, visibility .18s ease;
            }
            .site-nav .nav-links-container.is-open {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transform: translateY(0);
            }
            .site-nav .nav-links-container .nav-link {
                min-height: 60px;
                justify-content: flex-start;
                padding: 10px 14px;
                border-radius: 13px;
                color: rgba(255, 255, 255, .96);
                font-size: 16px;
                font-weight: 800;
            }
            .site-nav .nav-links-container .nav-link:hover { background: rgba(255, 255, 255, .15); }
            .post-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .branch-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 600px) {
            :root { --site-gutter: 28px; }
            .page-shell { padding-top: 155px; }
            .site-nav { top: 10px !important; min-height: 68px; padding: 8px 12px !important; }
            .site-nav .site-logo { width: 140px; }
            .mobile-menu-toggle { width: 44px; height: 44px; }
            .language-switch { gap: 1px; padding: 0; }
            .language-switch button { min-width: 36px; padding: 6px 3px; font-size: 16px; }
            .page-heading { padding: 30px 24px; border-radius: 22px; margin-bottom: 34px; }
            .page-heading::after { width: 68vw; right: -24%; top: -20%; }
            .post-grid, .branch-grid { grid-template-columns: 1fr; }
            .section { margin-top: 46px; }
        }
        @media (max-width: 360px) {
            .site-nav .site-logo { width: 112px; }
            .language-switch button { min-width: 34px; font-size: 16px; }
            .mobile-menu-toggle { width: 40px; height: 40px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .feature-card, .service-card, .value-card, .post-card, .branch-card { transition: none; }
        }

        /* ===== Lapisan responsif =====
           Kelas perangkat diatur oleh skrip di <head> lewat html[data-device]:
           mobile (<640px), tablet (640-1023px atau layar sentuh <1366px), laptop (1024-1439px), desktop (>=1440px).
           Ukuran teks dan jarak ikut lebar layar agar tidak terlalu besar atau kecil di perangkat mana pun. */
        :root {
            --nav-top: 20px;
            --nav-h: 78px;
            --shell-top: calc(var(--nav-top) + var(--nav-h) + 22px);
            --hero-bars: 200px;
        }
        html { font-size: clamp(15px, 14px + .2vw, 17px); -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }
        body { overflow-x: hidden; }
        img, video, iframe { max-width: 100%; }

        /* Jarak konten dari navbar mengikuti tinggi navbar, bukan angka tetap. */
        .site-nav { min-height: var(--nav-h) !important; }
        .page-shell { padding-top: var(--shell-top); }
        .content-container, .slider-wrapper { margin-top: var(--shell-top); }
        .page-custom-content { margin-top: var(--shell-top); }
        .hero-slider { margin-top: 0; }

        /* Semua halaman memakai lebar yang sama dengan navbar. */
        .site-nav { width: min(var(--site-max-width), calc(100% - var(--site-gutter))) !important; max-width: var(--site-max-width) !important; }

        /* Banner: selebar navbar, rasio asli 2:1 tanpa ditarik. Di layar pendek tingginya dibatasi
           (bagian atas/bawah gambar sedikit terpotong) agar banner tetap muat di layar. */
        .hero-slide img,
        .hero-slide img[loading] { width: 100%; height: auto !important; aspect-ratio: 2 / 1 !important; max-height: max(300px, calc(100svh - var(--shell-top) - 40px)); object-fit: cover !important; object-position: center; }
        .hero-slider { width: 100%; max-width: none; margin-left: 0; margin-right: 0; }
        .hero-slide-caption { flex-wrap: wrap; }
        .hero-slide-caption > div { flex: 1 1 260px; min-width: 0; }
        .hero-slide-caption .button { flex: 0 0 auto; white-space: nowrap; }

        /* Judul dan paragraf menyesuaikan lebar layar. */
        .hero-intro h1 { font-size: clamp(2.2rem, 1.2rem + 3.6vw, 4.6rem); }
        .hero h1, .page-heading h1 { font-size: clamp(2.1rem, 1.1rem + 3.4vw, 4.4rem); }
        .section-heading h2 { font-size: clamp(1.5rem, 1.1rem + 1.4vw, 2.4rem); }
        .hero-intro-copy > p, .page-heading > p { font-size: clamp(.98rem, .92rem + .25vw, 1.1rem); }
        .section { margin-top: clamp(40px, 3vw + 24px, 72px); }
        .hero-intro { gap: clamp(24px, 4vw, 56px); padding: clamp(28px, 4vw, 52px) clamp(4px, 2vw, 24px) 24px; }
        .page-heading { padding: clamp(26px, 4.5vw, 64px); }
        .page-banner img { max-height: min(460px, 55svh); }
        .content-panel h2 { margin: .25em 0 .5em; line-height: 1.15; }
        .hero-intro { max-width: none; margin-bottom: clamp(8px, 2vw, 34px); }

        /* Navbar atas disembunyikan dan menu bawah dimunculkan saat halaman di-scroll ke bawah. */
        #top-nav, .chatbot-launcher { transition: top .4s ease-in-out, bottom .3s ease; }
        body.nav-scrolled #bottom-nav { bottom: 30px; }
        @media (min-width: 601px) {
            body.nav-scrolled #top-nav { top: -140px !important; }
        }

        /* Kartu yang tersisa sendirian di baris terakhir (grid 2 kolom) dibuat selebar baris. */
        @media (min-width: 601px) and (max-width: 900px) {
            .service-grid > :last-child:nth-child(odd),
            .feature-grid > :last-child:nth-child(odd),
            .value-grid > :last-child:nth-child(odd),
            .post-grid > :last-child:nth-child(odd) { grid-column: 1 / -1; }
        }

        /* Laptop & desktop (mouse): menu tampil penuh, tanpa tombol menu. */
        @media (min-width: 1100px) {
            html:not([data-device="tablet"]) .mobile-menu-toggle { display: none !important; }
            html:not([data-device="tablet"]) .site-nav { justify-content: space-between !important; flex-wrap: nowrap; gap: clamp(10px, 1.4vw, 22px); }
            html:not([data-device="tablet"]) .site-nav .site-logo { width: clamp(160px, 12vw, 220px); }
            html:not([data-device="tablet"]) .site-nav .nav-links-container {
                position: static; display: flex; flex: 1 1 auto; flex-direction: row; justify-content: center; align-items: center;
                width: auto; min-width: 0; gap: clamp(2px, .4vw, 8px); padding: 0; overflow: visible;
                border: 0; border-radius: 0; background: none; box-shadow: none;
                opacity: 1; visibility: visible; pointer-events: auto; transform: none;
            }
            html:not([data-device="tablet"]) .site-nav .nav-links-container .nav-link {
                min-height: 44px; padding: 8px clamp(8px, .8vw, 14px); font-size: clamp(14px, .55vw + 8px, 16px);
            }
            html:not([data-device="tablet"]) .language-switch { margin-left: 0; }
        }

        /* Desktop lebar: konten tidak melebar berlebihan. */
        @media (min-width: 1920px) {
            :root { --site-max-width: 1760px; }
        }

        /* Tablet */
        @media (max-width: 1023px) {
            :root { --nav-top: 16px; --nav-h: 72px; --hero-bars: 168px; }
            .hero-slide-caption { min-height: 92px; padding: 16px 22px; }
            .hero-slider-controls-bar { min-height: 60px; }
        }

        /* Mobile */
        @media (max-width: 600px) {
            :root { --nav-top: 10px; --nav-h: 64px; --site-gutter: 24px; }
            .page-shell { padding-top: calc(var(--shell-top) - 8px); }
            .site-nav { min-height: 64px !important; }
            .hero-slider { max-width: 100%; }
            .hero-slide-caption { min-height: 0; padding: 14px 16px; gap: 10px; }
            .hero-slide-caption h2 { font-size: 1.02rem; }
            .hero-slider-controls-bar { min-height: 48px; padding-top: 10px; padding-bottom: 10px; }
            .hero-slider-controls-bar > .eyebrow { font-size: .62rem; letter-spacing: .14em; }
            .hero-intro { padding: 30px 2px 16px; }
            .hero-intro-actions .button { flex: 1 1 auto; justify-content: center; }
            .hero-benefit-card { min-height: 0; padding: 15px; }
            .hero-benefit-card .material-icons { margin-bottom: 12px; }
            .post-card img { height: auto; aspect-ratio: 5 / 3; }
            .site-footer { margin-bottom: 96px; }

            /* Menu bawah: ikon di atas label agar muat di layar kecil. */
            #bottom-nav { width: calc(100% - 20px) !important; max-width: 480px; justify-content: space-between; gap: 2px; padding: 8px 10px; border-radius: 22px; }
            #bottom-nav .nav-link { flex: 1 1 0; min-width: 0; flex-direction: column; justify-content: center; gap: 2px; padding: 4px 2px; font-size: .68rem; line-height: 1.1; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            #bottom-nav { height: auto; line-height: 1.1; }
            #bottom-nav .nav-link .material-icons { height: auto; line-height: 1; font-size: 20px !important; }
            .chatbot-panel { max-height: calc(100svh - 120px); }
            body.nav-scrolled #bottom-nav { bottom: max(12px, env(safe-area-inset-bottom)); }
            body.nav-scrolled .chatbot-launcher { bottom: 92px; }
        }

        /* HP layar kecil */
        @media (max-width: 380px) {
            :root { --site-gutter: 20px; }
            .hero-benefit-grid { gap: 10px; }
            .hero-benefit-card strong { font-size: .92rem; }
        }

        /* HP/tablet posisi landscape dengan layar pendek: banner tidak memenuhi layar. */
        @media (orientation: landscape) and (max-height: 520px) {
            :root { --nav-top: 8px; --nav-h: 60px; --hero-bars: 120px; }
        }

        /* Layar sentuh: tanpa efek hover yang membuat kartu "melompat". */
        @media (hover: none) {
            .feature-card:hover, .service-card:hover, .value-card:hover, .post-card:hover, .branch-card:hover,
            .site-nav .nav-link:hover { transform: none; }
        }

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

    <nav id="top-nav" class="site-nav glass">
        <a href="?page=home" aria-label="Raja Emas Indonesia - Home" style="text-decoration:none; display: flex; align-items: center;">
            <img class="site-logo" src="logo-rei.png" alt="Logo Raja Emas Indonesia">
        </a>
        <div class="nav-links-container" id="primary-navigation" aria-label="Navigasi utama">
            <?php foreach ($site_pages as $nav_key => $nav_title): ?>
                <a href="?page=<?= $escape($nav_key) ?>" class="nav-link"><?php if ($nav_key !== 'home'): ?><span class="material-icons" aria-hidden="true"><?= $escape(REI_SITE_PAGES[$nav_key]['icon']) ?></span><?php endif; ?><?= $escape($nav_title) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="language-switch" role="group" aria-label="Pilih bahasa">
            <button type="button" data-language="id" aria-pressed="true">ID</button>
            <button type="button" data-language="en" aria-pressed="false">EN</button>
        </div>
        <button class="mobile-menu-toggle" type="button" aria-label="Buka menu navigasi" aria-expanded="false" aria-controls="primary-navigation">
            <span class="material-icons" aria-hidden="true">menu</span>
        </button>
    </nav>

    <?php if (isset($site_pages[$requested_page ?? ''])): ?>
        <?php if (!empty($site_page_data['custom_css'])): ?><style><?= $site_page_data['custom_css'] ?></style><?php endif; ?>
        <main class="page-shell">
            <?php if ($site_page_custom_html !== ''): ?>
                <div class="page-custom-content"><?= $site_page_data['custom_html'] ?></div>
            <?php elseif ($requested_page === 'home'): ?>
                <section class="hero">
                    <div class="hero-intro">
                        <div class="hero-intro-copy">
                            <span class="eyebrow">Outlet tersedia di berbagai kota</span>
                            <h1>Jual Emas Tanpa<br><span>Surat Lebih Mudah</span></h1>
                            <?= $site_page_intro ?>
                            <div class="hero-intro-actions">
                                <a class="button button-primary" href="?page=cabang"><span class="material-icons">location_on</span>Cari Outlet Terdekat</a>
                                <a class="button button-secondary" href="?page=cabang">Lihat Outlet Kami</a>
                            </div>
                        </div>
                        <div class="hero-benefit-grid" aria-label="Keunggulan layanan">
                            <div class="hero-benefit-card"><span class="material-icons">biotech</span><strong>Teknologi XRF</strong><span>Akurat hingga 0,01%</span></div>
                            <div class="hero-benefit-card"><span class="material-icons">link_off</span><strong>Tanpa Surat</strong><span>Tetap dapat dijual</span></div>
                            <div class="hero-benefit-card"><span class="material-icons">schedule</span><strong>Buka Setiap Hari</strong><span>09.00 – 20.00</span></div>
                            <div class="hero-benefit-card"><span class="material-icons">verified_user</span><strong>Proses Transparan</strong><span>Di hadapan Anda</span></div>
                        </div>
                    </div>
                    <div class="hero-slider" data-hero-slider role="region" aria-label="Sorotan Raja Emas Indonesia" aria-roledescription="carousel">
                        <article class="hero-slide is-active" data-hero-slide aria-hidden="false">
                            <img src="uploads/hero/raja-emas-artboard.png" alt="Promosi Raja Emas Indonesia bersama Igun tentang harga emas dan perhiasan" fetchpriority="high">
                            <div class="hero-slide-caption">
                                <div><span class="eyebrow">Pilihan berharga, lebih dekat</span><h2>Temukan pengalaman Raja Emas Indonesia</h2></div>
                                <a class="button button-primary" href="?page=cabang">Temukan cabang <span class="material-icons">north_east</span></a>
                            </div>
                        </article>
                        <article class="hero-slide" data-hero-slide aria-hidden="true">
                            <img src="uploads/hero/raja-emas-slider-2026.png" alt="Promosi layanan jual emas transparan dari Raja Emas Indonesia" loading="lazy">
                            <div class="hero-slide-caption">
                                <div><span class="eyebrow">Layanan Raja Emas</span><h2>Informasi jelas untuk setiap pilihan emas</h2></div>
                                <a class="button button-primary" href="?page=service">Jelajahi layanan <span class="material-icons">north_east</span></a>
                            </div>
                        </article>
                        <article class="hero-slide" data-hero-slide aria-hidden="true">
                            <img src="uploads/hero/raja-emas-elvi.png" alt="Promosi Raja Emas Indonesia bersama Elvi tentang layanan jual emas" loading="lazy">
                            <div class="hero-slide-caption">
                                <div><span class="eyebrow">Raja Emas Indonesia</span><h2>Kenali layanan kami dan kunjungi cabang terdekat</h2></div>
                                <a class="button button-primary" href="?page=contact">Hubungi kami <span class="material-icons">north_east</span></a>
                            </div>
                        </article>
                        <div class="hero-slide-caption hero-slider-controls-bar" aria-label="Kontrol slider">
                            <span class="eyebrow">Cerita dan layanan Raja Emas</span>
                            <div class="hero-slide-controls">
                                <button class="hero-slide-arrow" type="button" data-hero-previous aria-label="Slide sebelumnya"><span class="material-icons">arrow_back</span></button>
                                <div class="hero-slide-dots">
                                    <button class="hero-slide-dot" type="button" data-hero-dot="0" aria-label="Tampilkan slide 1 dari 3" aria-current="true"></button>
                                    <button class="hero-slide-dot" type="button" data-hero-dot="1" aria-label="Tampilkan slide 2 dari 3" aria-current="false"></button>
                                    <button class="hero-slide-dot" type="button" data-hero-dot="2" aria-label="Tampilkan slide 3 dari 3" aria-current="false"></button>
                                </div>
                                <button class="hero-slide-arrow" type="button" data-hero-next aria-label="Slide berikutnya"><span class="material-icons">arrow_forward</span></button>
                            </div>
                        </div>
                    </div>
                </section>
                <?= $site_page_banner ?>

                <section class="section">
                    <div class="section-heading"><div><span class="eyebrow">Layanan utama</span><h2>Jual beli emas dalam satu tempat</h2></div><a class="button button-secondary" href="?page=service">Semua layanan <span class="material-icons">arrow_forward</span></a></div>
                    <div class="service-grid">
                        <article class="service-card service-card-highlight"><span class="material-icons">sell</span><h3>Jual emas tanpa surat</h3><p>Ajukan penilaian untuk emas perhiasan, batangan, atau emas tanpa surat dengan proses yang dijelaskan di cabang.</p><a class="button button-primary" href="?page=contact">Tanya sekarang <span class="material-icons">arrow_forward</span></a></article>
                        <article class="service-card glass"><span class="material-icons">biotech</span><h3>Cek keaslian emas</h3><p>Dapatkan panduan pemeriksaan kadar dan tanyakan ketersediaan layanan uji di cabang pilihan Anda.</p><a class="button button-secondary" href="?page=cabang">Cari cabang <span class="material-icons">arrow_forward</span></a></article>
                        <article class="service-card glass"><span class="material-icons">compare_arrows</span><h3>Informasi harga & produk</h3><p>Hubungi tim cabang untuk informasi terbaru karena harga, koleksi, dan layanan dapat berbeda di setiap lokasi.</p><a class="button button-secondary" href="?page=contact">Hubungi kami <span class="material-icons">arrow_forward</span></a></article>
                    </div>
                </section>

                <section class="section">
                    <div class="section-heading">
                        <div><span class="eyebrow">Lebih dekat dengan REI</span><h2>Berharga dalam setiap detail</h2></div>
                        <a class="button button-secondary" href="?page=about-us">Tentang kami <span class="material-icons">arrow_forward</span></a>
                    </div>
                    <div class="feature-grid">
                        <article class="feature-card glass"><span class="material-icons">auto_awesome</span><h3>Keindahan yang personal</h3><p>Pilih perhiasan yang terasa selaras dengan gaya, momen, dan cerita istimewa Anda.</p></article>
                        <article class="feature-card glass"><span class="material-icons">support_agent</span><h3>Layanan lebih dekat</h3><p>Tim di cabang siap membantu Anda mendapatkan informasi produk dan layanan secara langsung.</p></article>
                        <article class="feature-card glass"><span class="material-icons">storefront</span><h3>Kunjungi cabang kami</h3><p>Lihat alamat, jam operasional, dan petunjuk arah cabang dalam satu halaman.</p></article>
                    </div>
                </section>

                <section class="section">
                    <div class="section-heading"><div><span class="eyebrow">Kunjungi kami</span><h2>Cabang Raja Emas</h2></div><a class="button button-secondary" href="?page=cabang">Semua cabang <span class="material-icons">arrow_forward</span></a></div>
                    <?php if (empty($branches_list)): ?>
                        <div class="empty-state">Informasi cabang belum tersedia saat ini. Silakan kembali lagi nanti.</div>
                    <?php else: ?>
                        <div class="branch-grid">
                            <?php foreach (array_slice($branches_list, 0, 4) as $branch): ?>
                                <?php $render_branch_card($branch); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="section">
                    <div class="section-heading"><div><span class="eyebrow">Ruang inspirasi</span><h2>Artikel terbaru</h2></div><a class="button button-secondary" href="?page=blog">Semua artikel <span class="material-icons">arrow_forward</span></a></div>
                    <?php if (empty($posts)): ?>
                        <div class="empty-state">Cerita terbaru akan hadir di sini. Sementara itu, jelajahi layanan dan cabang Raja Emas Indonesia.</div>
                    <?php else: ?>
                        <div class="post-grid">
                            <?php foreach (array_slice($posts, 0, 3) as $post): ?>
                                <?php
                                $home_excerpt = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post['content'] ?? ''))) ?? '');
                                $home_words = preg_split('/\s+/', $home_excerpt) ?: [];
                                ?>
                                <article class="post-card glass" data-article-slug="<?= $escape($post['slug'] ?? '') ?>">
                                    <?php if (!empty($post['featured_image'])): ?><img src="<?= $escape($post['featured_image']) ?>" alt="<?= $escape($post['title'] ?? 'Artikel tentang emas') ?>" loading="lazy"><?php endif; ?>
                                    <span class="eyebrow">Cerita REI</span>
                                    <h3><?= $escape($post['title'] ?? '') ?></h3>
                                    <p><?= $escape(implode(' ', array_slice($home_words, 0, 24))) ?><?= count($home_words) > 24 ? '…' : '' ?></p>
                                    <?php if (!empty($post['slug'])): ?><a class="button button-secondary" href="?page=<?= rawurlencode((string) $post['slug']) ?>">Baca artikel <span class="material-icons">arrow_forward</span></a><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="section">
                    <div class="section-heading"><div><span class="eyebrow">Mengapa Raja Emas</span><h2>Pengalaman yang jelas dan aman</h2></div></div>
                    <div class="testimonial-grid">
                        <article class="testimonial-card"><blockquote>“Saya bisa bertanya dulu tentang proses dan dokumen yang dibutuhkan sebelum datang ke cabang.”</blockquote><cite>Pengunjung website · Jakarta</cite></article>
                        <article class="testimonial-card"><blockquote>“Informasi cabang dan petunjuk arah membantu saya merencanakan kunjungan dengan lebih nyaman.”</blockquote><cite>Pelanggan Raja Emas · Tangerang</cite></article>
                        <article class="testimonial-card"><blockquote>“Penjelasan kadar emas yang sederhana membuat saya lebih percaya diri saat memilih.”</blockquote><cite>Pembaca blog · Bandung</cite></article>
                    </div>
                </section>

                <section class="section content-panel glass">
                    <div class="section-heading"><div><span class="eyebrow">FAQ</span><h2>Pertanyaan yang sering ditanyakan</h2></div></div>
                    <div class="faq-list">
                        <details><summary>Apakah bisa menjual emas tanpa surat?</summary><p>Ketersediaan dan persyaratan dapat berbeda di setiap cabang. Hubungi cabang pilihan Anda terlebih dahulu agar tim dapat menjelaskan prosesnya.</p></details>
                        <details><summary>Apakah pemeriksaan keaslian emas berbayar?</summary><p>Tanyakan ketersediaan pemeriksaan dan ketentuannya kepada cabang terdekat. Tim akan menjelaskan proses sebelum pemeriksaan dilakukan.</p></details>
                        <details><summary>Berapa lama proses pencairannya?</summary><p>Durasi bergantung pada jenis emas, hasil pemeriksaan, dan kelengkapan informasi. Cabang akan memberikan estimasi setelah penilaian.</p></details>
                    </div>
                </section>

                <section class="section quick-contact">
                    <div><h2>Butuh jawaban yang lebih cepat?</h2><p>Temukan cabang terdekat atau kirim pertanyaan kepada tim Raja Emas Indonesia.</p></div>
                    <a class="button" href="?page=contact"><span class="material-icons">chat</span>Hubungi kami</a>
                </section>

            <?php elseif ($requested_page === 'about-us'): ?>
                <header class="page-heading"><span class="eyebrow">About us · Profil perusahaan</span><h1>Raja Emas Indonesia,<br><span class="gold-text">transparan di setiap nilai.</span></h1><?= $site_page_intro ?></header><?= $site_page_banner ?>
                <section class="about-profile">
                    <article class="about-profile-copy">
                        <span class="eyebrow">Siapa kami</span>
                        <h2>Lebih dari sekadar transaksi emas</h2>
                        <div class="gold-rule"></div>
                        <p>Raja Emas Indonesia adalah brand dari PT Emas Murni Asli yang bergerak di bidang perdagangan emas, perak, dan logam mulia lainnya. Kami menerima emas dalam berbagai kondisi, termasuk emas tanpa surat, dengan proses penilaian yang mudah dipahami.</p>
                        <p>Setiap pengecekan kadar dan komposisi dilakukan menggunakan teknologi XRF. Hasilnya dapat dilihat langsung sehingga pelanggan memiliki informasi yang cukup sebelum mengambil keputusan.</p>
                        <div class="button-row"><a class="button button-primary" href="?page=service">Lihat layanan <span class="material-icons">arrow_forward</span></a><a class="button button-secondary" href="?page=cabang">Temukan outlet</a></div>
                    </article>
                    <div class="about-metrics" aria-label="Ringkasan Raja Emas Indonesia">
                        <div class="about-metric"><strong>152</strong><span>Outlet resmi</span></div>
                        <div class="about-metric"><strong>31</strong><span>Kota di Indonesia</span></div>
                        <div class="about-metric"><strong>100K+</strong><span>Pelanggan</span></div>
                        <div class="about-metric"><strong>15+</strong><span>Tahun beroperasi</span></div>
                    </div>
                </section>

                <section class="section">
                    <div class="about-section-heading"><div><span class="eyebrow">Mengapa Raja Emas</span><h2>Standar layanan yang kami jaga</h2></div><p>Kami membangun pengalaman yang mengutamakan akurasi, harga yang kompetitif, dan komunikasi yang terbuka.</p></div>
                    <div class="value-showcase">
                    <div class="value-grid">
                        <article class="value-card glass"><span class="material-icons">biotech</span><h3>Akurasi XRF</h3><p>Kadar dan komposisi emas diperiksa dengan teknologi XRF secara cepat, presisi, dan tidak merusak.</p></article>
                        <article class="value-card glass"><span class="material-icons">sell</span><h3>Harga kompetitif</h3><p>Emas perhiasan, batangan, lama, patah, maupun tanpa surat tetap dapat dikonsultasikan di outlet.</p></article>
                        <article class="value-card glass"><span class="material-icons">visibility</span><h3>Proses transparan</h3><p>Pengecekan dilakukan di hadapan pelanggan agar setiap tahap mudah dipahami dan dipercaya.</p></article>
                        <article class="value-card glass"><span class="material-icons">storefront</span><h3>Jaringan luas</h3><p>Outlet resmi hadir di berbagai kota agar layanan emas lebih mudah dijangkau oleh masyarakat.</p></article>
                    </div>
                    <figure class="about-illustration">
                        <img src="uploads/hero/raja-emas-artboard.png" alt="Ilustrasi layanan Raja Emas Indonesia dengan latar ungu dan emas" loading="lazy">
                        <figcaption><span>Raja Emas Indonesia</span>Nilai emas yang jelas, layanan yang terpercaya.</figcaption>
                    </figure>
                    </div>
                </section>

                <section class="section">
                    <div class="about-section-heading"><div><span class="eyebrow">Cara kami melayani</span><h2>Proses mudah dan profesional</h2></div><p>Dari kunjungan hingga pembayaran, setiap langkah dirancang agar Anda merasa aman dan mendapatkan informasi yang jelas.</p></div>
                    <div class="process-grid">
                        <article class="process-card"><span class="process-number">01</span><h3>Datang ke outlet</h3><p>Kunjungi outlet terdekat tanpa perlu proses yang rumit.</p></article>
                        <article class="process-card"><span class="process-number">02</span><h3>Pengecekan XRF</h3><p>Kadar emas diperiksa dengan hasil yang dapat dilihat langsung.</p></article>
                        <article class="process-card"><span class="process-number">03</span><h3>Penawaran harga</h3><p>Tim menjelaskan hasil pemeriksaan dan penawaran secara terbuka.</p></article>
                        <article class="process-card"><span class="process-number">04</span><h3>Pembayaran</h3><p>Setelah disepakati, proses pembayaran dilakukan langsung di tempat.</p></article>
                    </div>
                </section>

                <section class="about-source"><p><strong>Ingin mengenal Raja Emas lebih jauh?</strong><br>Pelajari profil, layanan, dan jaringan outlet melalui situs resmi kami.</p><a class="button button-secondary" href="https://rajaemas.id/tentang" target="_blank" rel="noopener noreferrer">Profil resmi <span class="material-icons">open_in_new</span></a></section>

            <?php elseif ($requested_page === 'service'): ?>
                <header class="page-heading"><span class="eyebrow">Service · Layanan resmi</span><h1>Transaksi emas yang<br><span class="gold-text">jelas dan terpercaya.</span></h1><?= $site_page_intro ?></header><?= $site_page_banner ?>
                <section class="service-grid">
                    <article class="service-card service-card-highlight"><span class="material-icons">sell</span><h3>Jual emas</h3><p>Jual emas perhiasan, batangan, emas lama, patah, rusak, maupun tanpa surat setelah melalui proses pemeriksaan dan verifikasi.</p><a class="button button-primary" href="?page=cabang">Cari outlet <span class="material-icons">arrow_forward</span></a></article>
                    <article class="service-card glass"><span class="material-icons">biotech</span><h3>Cek keaslian dengan XRF</h3><p>Verifikasi keaslian dan kadar emas menggunakan teknologi XRF secara non-destruktif. Hasil pemeriksaan dapat dilihat langsung.</p><a class="button button-secondary" href="?page=contact">Tanya layanan <span class="material-icons">arrow_forward</span></a></article>
                    <article class="service-card glass"><span class="material-icons">diamond</span><h3>Beli logam mulia</h3><p>Dapatkan informasi tentang ketersediaan logam mulia dan produk emas dari tim outlet Raja Emas Indonesia.</p><a class="button button-secondary" href="?page=cabang">Hubungi outlet <span class="material-icons">arrow_forward</span></a></article>
                </section>
                <section class="section stat-strip" aria-label="Ringkasan layanan Raja Emas Indonesia">
                    <div class="stat-strip-layout">
                        <div class="stat-items">
                            <div class="stat-item"><strong>XRF</strong><span>Teknologi pemeriksaan non-destruktif</span></div>
                            <div class="stat-item"><strong>Gratis</strong><span>Cek keaslian di outlet</span></div>
                            <div class="stat-item"><strong>31</strong><span>Kota dengan jaringan outlet</span></div>
                            <div class="stat-item"><strong>Terbuka</strong><span>Proses dilakukan di hadapan pelanggan</span></div>
                        </div>
                        <figure class="xrf-illustration">
                            <img src="uploads/hero/raja-emas-slider-2026.png" alt="Ilustrasi pelanggan dan staf Raja Emas Indonesia dalam proses pemeriksaan emas" loading="lazy">
                            <figcaption><span>Pemeriksaan di outlet</span>Keaslian dan kadar emas diperiksa dengan XRF.</figcaption>
                        </figure>
                    </div>
                </section>

                <section class="section">
                    <div class="about-section-heading"><div><span class="eyebrow">Proses jual emas</span><h2>Langkah yang mudah dipahami</h2></div><p>Hasil akhir transaksi mengikuti kadar, berat, kondisi emas, harga yang berlaku, dan ketentuan verifikasi di outlet.</p></div>
                    <div class="process-grid">
                        <article class="process-card"><span class="process-number">01</span><h3>Datang ke outlet</h3><p>Bawa emas yang ingin diperiksa dan siapkan identitas resmi untuk verifikasi transaksi.</p></article>
                        <article class="process-card"><span class="process-number">02</span><h3>Pemeriksaan XRF</h3><p>Tim memeriksa keaslian dan kadar emas menggunakan alat XRF tanpa merusak bentuknya.</p></article>
                        <article class="process-card"><span class="process-number">03</span><h3>Penimbangan dan penawaran</h3><p>Berat, kadar, kondisi, dan harga yang berlaku menjadi dasar penawaran kepada pelanggan.</p></article>
                        <article class="process-card"><span class="process-number">04</span><h3>Setujui dan selesaikan</h3><p>Jika penawaran disepakati, administrasi dan pembayaran dilakukan sesuai ketentuan yang berlaku.</p></article>
                    </div>
                </section>

                <section class="section content-panel glass split-panel">
                    <div><span class="eyebrow">Informasi penting</span><h2>Transparan sejak pengecekan pertama</h2><p>Raja Emas melakukan pengecekan keaslian dan kadar sebelum menentukan nilai transaksi. Harga final dan tata cara pembayaran disampaikan serta disepakati di outlet.</p><a class="button button-primary" href="?page=cabang">Temukan outlet terdekat <span class="material-icons">north_east</span></a></div>
                    <div class="quote-panel">Bawa emas Anda, lihat proses pemeriksaannya, pahami hasilnya, lalu tentukan keputusan dengan tenang.</div>
                </section>

                <section class="section content-panel glass">
                    <div class="section-heading"><div><span class="eyebrow">FAQ layanan</span><h2>Yang perlu Anda ketahui</h2></div></div>
                    <div class="faq-list">
                        <details><summary>Apakah emas tanpa surat dapat diperiksa?</summary><p>Emas tanpa surat tetap dapat diperiksa, tetapi penerimaan transaksi mengikuti hasil verifikasi dan ketentuan yang berlaku di outlet.</p></details>
                        <details><summary>Apakah pengecekan XRF merusak emas?</summary><p>Teknologi XRF digunakan untuk membantu mengukur keaslian dan kadar emas secara non-destruktif.</p></details>
                        <details><summary>Apakah harga emas selalu sama?</summary><p>Harga dapat berubah mengikuti kondisi pasar. Penawaran akhir ditentukan setelah kadar, berat, kondisi emas, dan harga yang berlaku diperiksa.</p></details>
                    </div>
                </section>

                <section class="section quick-contact"><div><h2>Siap berkonsultasi?</h2><p>Hubungi tim atau kunjungi outlet resmi Raja Emas Indonesia untuk informasi sesuai kebutuhan Anda.</p></div><a class="button" href="?page=contact">Hubungi kami <span class="material-icons">chat</span></a></section>

            <?php elseif ($requested_page === 'cabang'): ?>
                <header class="page-heading"><span class="eyebrow">Branch · Lokasi kami</span><h1>Temukan Raja Emas<br><span class="gold-text">di dekat Anda.</span></h1><?= $site_page_intro ?></header><?= $site_page_banner ?>
                <?php if (empty($branches_list)): ?>
                    <div class="empty-state">Informasi cabang belum tersedia saat ini. Silakan kembali lagi nanti untuk menemukan lokasi Raja Emas Indonesia.</div>
                <?php else: ?>
                    <label class="sr-only" for="branch-search">Cari nama cabang atau alamat</label>
                    <input class="branch-search" id="branch-search" type="search" placeholder="Cari kota, nama cabang, atau alamat..." autocomplete="off">
                    <section class="branch-grid" aria-label="Daftar cabang Raja Emas Indonesia">
                        <?php foreach ($branches_list as $branch): ?>
                            <?php
                            ob_start();
                            $render_branch_card($branch);
                            $branch_card_html = ob_get_clean();
                            ?>
                            <div class="branch-result" data-branch-searchable="<?= $escape(($branch['title'] ?? '') . ' ' . ($branch['address'] ?? '')) ?>"><?= $branch_card_html ?></div>
                        <?php endforeach; ?>
                    </section>
                    <p class="empty-state branch-no-results" hidden>Tidak ada cabang yang cocok dengan pencarian Anda.</p>
                <?php endif; ?>

            <?php elseif ($requested_page === 'blog'): ?>
                <header class="page-heading"><span class="eyebrow">Blog · Cerita dan inspirasi</span><h1>Wawasan untuk<br><span class="gold-text">pilihan yang berharga.</span></h1><?= $site_page_intro ?></header><?= $site_page_banner ?>
                <?php if (empty($posts)): ?>
                    <div class="empty-state">Artikel terbaru sedang disiapkan. Kunjungi kembali halaman ini untuk membaca cerita dan informasi dari Raja Emas Indonesia.</div>
                <?php else: ?>
                    <section class="post-grid" aria-label="Artikel Raja Emas Indonesia">
                        <?php foreach ($posts as $post): ?>
                            <?php
                            $excerpt_text = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post['content'] ?? ''))) ?? '');
                            $excerpt_words = preg_split('/\s+/', $excerpt_text, 34);
                            $excerpt = implode(' ', array_slice($excerpt_words ?: [], 0, 32));
                            ?>
                            <article class="post-card glass" data-article-slug="<?= $escape($post['slug'] ?? '') ?>">
                                <?php if (!empty($post['featured_image'])): ?><img src="<?= $escape($post['featured_image']) ?>" alt="<?= $escape($post['title']) ?>" loading="lazy"><?php endif; ?>
                                <span class="eyebrow">Cerita REI</span>
                                <h3><?= $escape($post['title']) ?></h3>
                                <p><?= $escape($excerpt) ?><?= count($excerpt_words ?: []) > 32 ? '…' : '' ?></p>
                                <div class="post-date">Dipublikasikan <?= !empty($post['created_at']) ? $escape(date('d M Y', strtotime($post['created_at']))) : '' ?></div>
                                <?php if (!empty($post['slug'])): ?><a class="button button-secondary" href="?page=<?= rawurlencode((string) $post['slug']) ?>">Baca selengkapnya <span class="material-icons">arrow_forward</span></a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

            <?php elseif ($requested_page === 'contact'): ?>
                <header class="page-heading"><span class="eyebrow">Contact · Hubungi kami</span><h1>Kami siap membantu<br><span class="gold-text">perjalanan Anda.</span></h1><?= $site_page_intro ?></header><?= $site_page_banner ?>
                <section class="contact-layout" aria-label="Formulir dan informasi kontak">
                    <div class="contact-form-panel">
                        <span class="eyebrow">Kirim pesan</span>
                        <h2>Ceritakan kebutuhan Anda</h2>
                        <p>Isi formulir berikut agar tim kami dapat memahami pertanyaan Anda dengan lebih baik.</p>
                        <form class="contact-form" id="contact-form" action="#contact-form" method="post">
                            <div class="contact-form-grid">
                                <div class="contact-field">
                                    <label for="contact-name">Nama lengkap</label>
                                    <input id="contact-name" name="name" type="text" placeholder="Nama Anda" autocomplete="name" required>
                                </div>
                                <div class="contact-field">
                                    <label for="contact-phone">Nomor WhatsApp</label>
                                    <input id="contact-phone" name="phone" type="tel" placeholder="08xx-xxxx-xxxx" autocomplete="tel" required>
                                </div>
                            </div>
                            <div class="contact-form-grid">
                                <div class="contact-field">
                                    <label for="contact-email">Email</label>
                                    <input id="contact-email" name="email" type="email" placeholder="nama@email.com" autocomplete="email">
                                </div>
                                <div class="contact-field">
                                    <label for="contact-topic">Topik pertanyaan</label>
                                    <select id="contact-topic" name="topic" required>
                                        <option value="" selected disabled>Pilih topik</option>
                                        <option value="jual-emas">Jual emas</option>
                                        <option value="perhiasan">Koleksi perhiasan</option>
                                        <option value="layanan">Informasi layanan</option>
                                        <option value="cabang">Lokasi dan cabang</option>
                                        <option value="lainnya">Pertanyaan lainnya</option>
                                    </select>
                                </div>
                            </div>
                            <div class="contact-field">
                                <label for="contact-message">Pesan</label>
                                <textarea id="contact-message" name="message" placeholder="Tuliskan pertanyaan atau kebutuhan Anda..." required></textarea>
                            </div>
                            <button class="button button-primary" type="submit">Kirim pertanyaan <span class="material-icons">arrow_forward</span></button>
                            <p class="contact-form-note">Dengan mengirim formulir, Anda memberikan informasi agar tim Raja Emas Indonesia dapat menghubungi Anda.</p>
                        </form>
                    </div>
                    <aside class="contact-side">
                        <div class="contact-info-panel">
                            <span class="eyebrow">Hubungi kami</span>
                            <h2>Mulai dari lokasi terdekat</h2>
                            <p>Tim cabang siap membantu menjawab pertanyaan dan memberikan informasi sesuai kondisi setempat.</p>
                            <div class="contact-list">
                                <div class="contact-item"><span class="material-icons">chat</span><div><strong>WhatsApp cabang</strong><p>Pilih cabang untuk menghubungi nomor yang tercantum pada informasi lokasi.</p></div></div>
                                <div class="contact-item"><span class="material-icons">schedule</span><div><strong>Jam operasional</strong><p>Jam buka dapat berbeda. Periksa informasi cabang sebelum berkunjung.</p></div></div>
                                <div class="contact-item"><span class="material-icons">place</span><div><strong>Alamat kantor</strong><p><?= $escape($office_address) ?></p></div></div>
                            </div>
                            <a class="button button-primary" href="?page=cabang">Lihat semua cabang <span class="material-icons">north_east</span></a>
                        </div>
                        <div class="contact-process-panel">
                            <span class="eyebrow">Cara kami membantu</span>
                            <h2>Jelas sejak awal</h2>
                            <ol class="contact-process-list">
                                <li><b>1</b><div><strong>Kami membaca pertanyaan Anda</strong>Informasi yang lengkap membantu kami memberi arahan yang lebih tepat.</div></li>
                                <li><b>2</b><div><strong>Tim menghubungi Anda</strong>Kami akan mengarahkan Anda ke cabang atau layanan yang sesuai.</div></li>
                                <li><b>3</b><div><strong>Anda datang dengan nyaman</strong>Siapkan pertanyaan dan temukan lokasi yang paling dekat.</div></li>
                            </ol>
                        </div>
                    </aside>
                </section>
            <?php endif; ?>
        </main>
        <?php if (!empty($site_page_data['custom_js'])): ?><script><?= $site_page_data['custom_js'] ?></script><?php endif; ?>

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
            <div class="page-custom-content"><?= $current_page_data['custom_html'] ?></div>
        <?php else: ?>
            <div class="content-container">
                <div class="post-card glass">
                    <h1 style="margin-top: 0; font-size: 2rem;"><?= htmlspecialchars($current_page_data['title']) ?></h1>
                    <div class="post-date">Dipublikasikan: <?= date('d M Y', strtotime($current_page_data['created_at'])) ?></div>
                    
                    <?php if (!empty($current_page_data['featured_image'])): ?>
                        <img class="post-detail-image" src="<?= $escape($current_page_data['featured_image']) ?>" alt="<?= $escape($current_page_data['title']) ?>">
                    <?php endif; ?>

                    <div class="post-content" data-article-slug="<?= $escape($current_page_data['slug'] ?? '') ?>" style="margin-top: 20px; line-height: 1.6;"><?= $current_page_data['content'] ?></div>
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

    <footer class="site-footer glass">
        <a href="?page=home" aria-label="Raja Emas Indonesia - Home"><img src="logo-rei.png" alt="Raja Emas Indonesia"></a>
        <div class="footer-credit">made by rei-dev with <span class="material-icons footer-heart" aria-hidden="true">favorite</span></div>
        <small>&copy; <?= date('Y') ?> Raja Emas Indonesia</small>
    </footer>

    <section class="chatbot-panel" id="chatbot-panel" aria-label="Chatbot Raja Emas" aria-hidden="true">
        <header class="chatbot-header">
            <div><strong>Asisten Raja Emas</strong><small>Siap membantu menemukan informasi</small></div>
            <button class="chatbot-close" type="button" data-chat-close aria-label="Tutup chatbot"><span class="material-icons">close</span></button>
        </header>
        <div class="chatbot-messages" data-chat-messages aria-live="polite">
            <div class="chat-message">Halo! Saya bisa membantu mencari cabang, menjelaskan layanan, atau menemukan artikel tentang emas.</div>
            <div class="chat-quick-replies">
                <button type="button" data-chat-prompt="Saya ingin mencari cabang">Cari cabang</button>
                <button type="button" data-chat-prompt="Informasi layanan jual emas">Info layanan</button>
                <button type="button" data-chat-prompt="Artikel tentang emas">Artikel emas</button>
            </div>
        </div>
        <form class="chatbot-form" data-chat-form>
            <label class="sr-only" for="chat-message">Tulis pertanyaan</label>
            <input id="chat-message" name="message" type="text" placeholder="Tulis pertanyaan..." autocomplete="off" required>
            <button type="submit" aria-label="Kirim pesan"><span class="material-icons">send</span></button>
        </form>
    </section>
    <button class="chatbot-launcher" type="button" data-chat-open aria-label="Buka chatbot Raja Emas" aria-expanded="false" aria-controls="chatbot-panel"><span class="material-icons">chat</span></button>

    <nav id="bottom-nav" class="glass">
        <a href="?page=home" class="nav-link"><i class="material-icons" style="font-size: 18px;">home</i> <?= $escape($site_pages['home']) ?></a>
        <a href="?page=cabang" class="nav-link"><i class="material-icons" style="font-size: 18px;">storefront</i> <?= $escape($site_pages['cabang']) ?></a>
        <a href="?page=blog" class="nav-link"><i class="material-icons" style="font-size: 18px;">auto_stories</i> <?= $escape($site_pages['blog']) ?></a>
        <a href="?page=contact" class="nav-link"><i class="material-icons" style="font-size: 18px;">chat</i> <?= $escape($site_pages['contact']) ?></a>
        <a href="#" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;" class="nav-link"><i class="material-icons" style="font-size: 18px;">arrow_upward</i> Atas</a>
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

            const translations = {
                'Home': 'Home',
                'Tentang Kami': 'About Us',
                'Layanan': 'Services',
                'Cabang': 'Branches',
                'Kontak': 'Contact',
                'Buka menu navigasi': 'Open navigation menu',
                'Tutup menu navigasi': 'Close navigation menu',
                'Pilih bahasa': 'Choose language',
                'Navigasi utama': 'Main navigation',
                'Sorotan Raja Emas Indonesia': 'Raja Emas Indonesia highlights',
                'Pilihan berharga, lebih dekat': 'A valuable choice, closer to you',
                'Temukan pengalaman Raja Emas Indonesia': 'Discover the Raja Emas Indonesia experience',
                'Temukan cabang': 'Find a branch',
                'Layanan Raja Emas': 'Raja Emas services',
                'Informasi jelas untuk setiap pilihan emas': 'Clear information for every gold choice',
                'Jelajahi layanan': 'Explore our services',
                'Kenali layanan kami dan kunjungi cabang terdekat': 'Discover our services and visit your nearest branch',
                'Hubungi kami': 'Contact us',
                'Cerita dan layanan Raja Emas': 'Raja Emas stories and services',
                'Slide sebelumnya': 'Previous slide',
                'Slide berikutnya': 'Next slide',
                'Tampilkan slide 1 dari 3': 'Show slide 1 of 3',
                'Tampilkan slide 2 dari 3': 'Show slide 2 of 3',
                'Tampilkan slide 3 dari 3': 'Show slide 3 of 3',
                'Lebih dekat dengan REI': 'Closer to REI',
                'Berharga dalam setiap detail': 'Meaningful in every detail',
                'Tentang kami': 'About us',
                'Keindahan yang personal': 'Beauty with a personal touch',
                'Pilih perhiasan yang terasa selaras dengan gaya, momen, dan cerita istimewa Anda.': 'Choose jewellery that reflects your style, your moments, and your special story.',
                'Layanan lebih dekat': 'Service, closer to you',
                'Tim di cabang siap membantu Anda mendapatkan informasi produk dan layanan secara langsung.': 'Our branch teams are ready to provide information about products and services in person.',
                'Kunjungi cabang kami': 'Visit our branches',
                'Lihat alamat, jam operasional, dan petunjuk arah cabang dalam satu halaman.': 'Find branch addresses, opening hours, and directions on one page.',
                'Temukan inspirasi': 'Find inspiration',
                'Cerita dan kabar terbaru': 'Stories and latest news',
                'Ikuti artikel dan informasi terbaru dari Raja Emas Indonesia.': 'Explore the latest articles and updates from Raja Emas Indonesia.',
                'Setiap pilihan berharga dimulai dari informasi yang tepat dan pengalaman yang nyaman.': 'Every valuable choice begins with clear information and a comfortable experience.',
                'Baca blog': 'Read our blog',
                'Kunjungi kami': 'Visit us',
                'Cabang Raja Emas': 'Raja Emas branches',
                'Semua cabang': 'All branches',
                'Informasi cabang belum tersedia saat ini. Silakan kembali lagi nanti.': 'Branch information is currently unavailable. Please check back later.',
                'Ruang inspirasi': 'Inspiration',
                'Artikel terbaru': 'Latest articles',
                'Cerita terbaru akan hadir di sini. Sementara itu, jelajahi layanan dan cabang Raja Emas Indonesia.': 'Our latest stories will appear here. In the meantime, explore Raja Emas Indonesia services and branches.',
                'Semua artikel': 'All articles',
                'Cerita REI': 'REI stories',
                'Baca artikel': 'Read article',
                'About us · Tentang kami': 'About us',
                'Raja Emas Indonesia,': 'Raja Emas Indonesia,',
                'lebih dekat dengan Anda.': 'closer to you.',
                'Raja Emas Indonesia hadir untuk menjadi bagian dari perjalanan Anda menemukan keindahan dan makna dalam setiap pilihan emas.': 'Raja Emas Indonesia is here to be part of your journey to discover beauty and meaning in every gold choice.',
                'Siapa kami': 'Who we are',
                'Berangkat dari nilai yang berharga': 'Rooted in what matters',
                'Emas bukan hanya tentang kilau. Ia bisa menjadi ungkapan kasih, penanda pencapaian, hadiah penuh arti, atau pilihan personal yang disimpan untuk waktu mendatang.': 'Gold is about more than its shine. It can express affection, mark an achievement, become a meaningful gift, or be a personal choice treasured for the future.',
                'Kami ingin setiap kunjungan terasa ramah dan informatif—mulai dari mengenal koleksi, bertanya tentang produk, hingga menemukan cabang yang mudah dijangkau.': 'We want every visit to feel welcoming and informative—from exploring our collections and asking about products to finding a conveniently located branch.',
                '“Pilihan yang bermakna layak ditemani pengalaman yang hangat, jelas, dan personal.”': '“A meaningful choice deserves a warm, clear, and personal experience.”',
                'Cara kami melayani': 'How we serve',
                'Nilai yang kami bawa': 'The values we stand for',
                'Kami menghargai cerita dan kebutuhan yang berbeda di setiap kunjungan.': 'We value the unique stories and needs behind every visit.',
                'Informatif': 'Informative',
                'Kami mendorong percakapan terbuka agar Anda dapat bertanya sebelum menentukan pilihan.': 'We encourage open conversations so you can ask questions before making a choice.',
                'Hadir lebih dekat': 'Here for you',
                'Informasi cabang membantu Anda merencanakan kunjungan dengan lebih nyaman.': 'Our branch information helps you plan a more convenient visit.',
                'Service · Layanan': 'Services',
                'Temukan layanan': 'Discover services',
                'yang sesuai kebutuhan.': 'that suit your needs.',
                'Jelajahi pilihan dan dapatkan informasi langsung dari tim Raja Emas Indonesia. Ketersediaan produk dan layanan dapat berbeda di setiap cabang.': 'Explore your options and get information directly from the Raja Emas Indonesia team. Product and service availability may vary by branch.',
                'Koleksi perhiasan': 'Jewellery collection',
                'Jelajahi pilihan perhiasan emas untuk melengkapi gaya sehari-hari maupun momen spesial.': 'Explore gold jewellery for everyday style and special occasions.',
                'Lihat cabang': 'View branches',
                'Informasi produk': 'Product information',
                'Butuh detail tentang koleksi atau ketersediaan? Sampaikan pertanyaan Anda langsung kepada tim cabang.': 'Need details about a collection or availability? Ask our branch team directly.',
                'Pilihan untuk hadiah': 'Gift choices',
                'Temukan inspirasi hadiah yang berkesan untuk merayakan momen berharga bersama orang terdekat.': 'Find thoughtful gift ideas to celebrate meaningful moments with those closest to you.',
                'Rencanakan kunjungan': 'Plan a visit',
                'Perlu bantuan memilih?': 'Need help choosing?',
                'Mulai percakapan dengan cabang': 'Start a conversation with a branch',
                'Hubungi atau kunjungi cabang untuk memperoleh informasi yang sesuai dengan kebutuhan dan ketersediaan setempat.': 'Contact or visit a branch for information tailored to your needs and local availability.',
                'Branch · Lokasi kami': 'Our branches',
                'Temukan Raja Emas': 'Find Raja Emas',
                'di dekat Anda.': 'near you.',
                'Pilih cabang untuk melihat informasi alamat, jam operasional, kontak, dan petunjuk arah. Silakan hubungi cabang terlebih dahulu untuk memastikan informasi kunjungan.': 'Choose a branch to view its address, opening hours, contact details, and directions. Please contact the branch first to confirm visit information.',
                'Informasi cabang belum tersedia saat ini. Silakan kembali lagi nanti untuk menemukan lokasi Raja Emas Indonesia.': 'Branch information is currently unavailable. Please check back later to find a Raja Emas Indonesia location.',
                'Cari nama cabang atau alamat': 'Search branch name or address',
                'Cari kota, nama cabang, atau alamat...': 'Search by city, branch name, or address...',
                'lokasi resmi': 'official locations',
                'Sumber: situs Raja Emas Indonesia': 'Source: Raja Emas Indonesia website',
                'Daftar cabang Raja Emas Indonesia': 'Raja Emas Indonesia branch directory',
                'Tidak ada cabang yang cocok dengan pencarian Anda.': 'No branches match your search.',
                'Hubungi cabang': 'Contact branch',
                'Petunjuk arah': 'Get directions',
                'Blog · Cerita dan inspirasi': 'Blog · Stories and inspiration',
                'Wawasan untuk': 'Insights for',
                'pilihan yang berharga.': 'a valuable choice.',
                'Temukan artikel, informasi, dan inspirasi terbaru dari Raja Emas Indonesia.': 'Discover the latest articles, information, and inspiration from Raja Emas Indonesia.',
                'Artikel terbaru sedang disiapkan. Kunjungi kembali halaman ini untuk membaca cerita dan informasi dari Raja Emas Indonesia.': 'New articles are being prepared. Please visit again to read stories and updates from Raja Emas Indonesia.',
                'Artikel Raja Emas Indonesia': 'Raja Emas Indonesia articles',
                'Dipublikasikan': 'Published',
                'Baca selengkapnya': 'Read more',
                'Contact · Hubungi kami': 'Contact · Get in touch',
                'Kami siap membantu': 'We are here to help',
                'perjalanan Anda.': 'you on your journey.',
                'Punya pertanyaan tentang koleksi, layanan, atau rencana berkunjung? Hubungi cabang Raja Emas Indonesia yang paling sesuai untuk Anda.': 'Have questions about our collections, services, or planning a visit? Contact the Raja Emas Indonesia branch that works best for you.',
                'Hubungi cabang': 'Contact a branch',
                'Mulai dari lokasi terdekat': 'Start with your nearest location',
                'Tim cabang dapat membantu menjawab pertanyaan dan memberikan informasi sesuai kondisi setempat.': 'Our branch team can answer your questions and provide information relevant to the local area.',
                'Lihat semua cabang': 'View all branches',
                'WhatsApp cabang': 'Branch WhatsApp',
                'Pilih cabang untuk menghubungi nomor yang tercantum pada informasi lokasi.': 'Choose a branch to contact the number listed in its location details.',
                'Jam operasional': 'Opening hours',
                'Jam buka dapat berbeda. Periksa informasi cabang sebelum melakukan kunjungan.': 'Opening hours may vary. Check branch information before your visit.',
                'Alamat dan petunjuk arah': 'Address and directions',
                'Temukan alamat serta tautan peta pada halaman cabang.': 'Find the address and map link on the branches page.',
                'Kantor Raja Emas Indonesia': 'Raja Emas Indonesia Head Office',
                'Alamat kantor akan segera diperbarui.': 'The office address will be updated soon.',
                'Raja Emas Indonesia - Home': 'Raja Emas Indonesia - Home',
                'Dipublikasikan:': 'Published:',
                'Kembali ke Beranda': 'Back to Home',
                '← Kembali ke Beranda': '← Back to Home',
                'Kirim pesan': 'Send message',
                'Tulis pertanyaan': 'Write your question',
                'Tulis pertanyaan...': 'Type your question...',
                'Buka chatbot Raja Emas': 'Open Raja Emas chatbot',
                'Tutup chatbot': 'Close chatbot',
                'Asisten Raja Emas': 'Raja Emas Assistant',
                'Logo Raja Emas Indonesia': 'Raja Emas Indonesia logo',
                'Promosi Raja Emas Indonesia bersama Igun tentang harga emas dan perhiasan': 'Raja Emas Indonesia promotion with Igun about gold prices and jewellery',
                'Promosi layanan jual emas transparan dari Raja Emas Indonesia': 'Raja Emas Indonesia promotion for transparent gold-selling services',
                'Promosi Raja Emas Indonesia bersama Elvi tentang layanan jual emas': 'Raja Emas Indonesia promotion with Elvi about gold-selling services',
                'Siap membantu menemukan informasi': 'Here to help you find information',
                'Halo! Saya bisa membantu mencari cabang, menjelaskan layanan, atau menemukan artikel tentang emas.': 'Hello! I can help you find a branch, learn about our services, or discover articles about gold.',
                'Cari cabang': 'Find a branch',
                'Info layanan': 'Service information',
                'Artikel emas': 'Gold articles',
                'Beranda': 'Home',
                'Cabang': 'Branches',
                'Kontak': 'Contact',
                'Atas': 'Top',
                'Artikel tentang emas': 'Articles about gold',
                'Buka setiap hari': 'Open daily',
                'BUKA SETIAP HARI': 'OPEN DAILY',
                'Home - Raja Emas Indonesia': 'Home - Raja Emas Indonesia',
                'Tentang Kami - Raja Emas Indonesia': 'About Us - Raja Emas Indonesia',
                'Layanan - Raja Emas Indonesia': 'Services - Raja Emas Indonesia',
                'Cabang - Raja Emas Indonesia': 'Branches - Raja Emas Indonesia',
                'Blog - Raja Emas Indonesia': 'Blog - Raja Emas Indonesia',
                'Kontak - Raja Emas Indonesia': 'Contact - Raja Emas Indonesia',
                'Mengenal Kadar Emas: Karat, Kemurnian, dan Cara Membacanya': 'Understanding Gold Purity: Karats, Fineness, and How to Read Them',
                'Mengenal Kadar Emas: Karat, Kemurnian, dan Cara Membacanya - Raja Emas Indonesia': 'Understanding Gold Purity: Karats, Fineness, and How to Read Them - Raja Emas Indonesia',
                'Cara Merawat Perhiasan Emas agar Tetap Indah': 'How to Care for Gold Jewellery and Keep It Beautiful',
                'Cara Merawat Perhiasan Emas agar Tetap Indah - Raja Emas Indonesia': 'How to Care for Gold Jewellery and Keep It Beautiful - Raja Emas Indonesia',
                'Memilih Perhiasan Emas untuk Menemani Aktivitas Sehari-hari': 'Choosing Gold Jewellery for Everyday Wear',
                'Memilih Perhiasan Emas untuk Menemani Aktivitas Sehari-hari - Raja Emas Indonesia': 'Choosing Gold Jewellery for Everyday Wear - Raja Emas Indonesia',
                'Emas sebagai Hadiah: Menyimpan Makna dalam Momen Istimewa': 'Gold as a Gift: Meaningful Memories for Special Moments',
                'Emas sebagai Hadiah: Menyimpan Makna dalam Momen Istimewa - Raja Emas Indonesia': 'Gold as a Gift: Meaningful Memories for Special Moments - Raja Emas Indonesia',
                'Panduan Belanja Emas dengan Tenang dan Bijak': 'A Thoughtful Guide to Buying Gold with Confidence',
                'Panduan Belanja Emas dengan Tenang dan Bijak - Raja Emas Indonesia': 'A Thoughtful Guide to Buying Gold with Confidence - Raja Emas Indonesia',
                'Memahami Peran Emas dalam Perencanaan Keuangan': 'Understanding Gold’s Role in Financial Planning',
                'Memahami Peran Emas dalam Perencanaan Keuangan - Raja Emas Indonesia': 'Understanding Gold’s Role in Financial Planning - Raja Emas Indonesia'
            };
            const articleTranslations = {
                'mengenal-kadar-emas': {
                    title: 'Understanding Gold Purity: Karats, Fineness, and How to Read Them',
                    summary: 'Karat indicates the proportion of gold in an alloy. 24-karat gold generally refers to a very high level of purity, while lower karat numbers mean other metals are mixed in to create a particular colour, strength, or character.',
                    paragraphs: [
                        'Karat indicates the proportion of gold in an alloy. 24-karat gold generally refers to a very high level of purity, while lower karat numbers mean other metals are mixed in to create a particular colour, strength, or character.',
                        'Because jewellery is made to be worn, each product may have a different composition and character. Colour alone is not enough to determine purity. Check the product details and any accompanying marks or documents, and ask the seller directly about the information that applies to the item.',
                        'When comparing jewellery, consider purity, weight, design, condition, and the full price breakdown. Ask for a clear explanation and keep your transaction receipt so the details of your purchase are properly recorded.'
                    ]
                },
                'cara-merawat-perhiasan-emas': {
                    title: 'How to Care for Gold Jewellery and Keep It Beautiful',
                    summary: 'Simple care can help preserve the appearance of your jewellery. Remove it before exercising, swimming, cleaning, or applying personal care products. Chemicals, impacts, and friction may affect its shine or certain components.',
                    paragraphs: [
                        'Simple care can help preserve the appearance of your jewellery. Remove it before exercising, swimming, cleaning, or applying personal care products. Chemicals, impacts, and friction may affect its shine or certain components.',
                        'For light care, wipe it with a soft, dry cloth. If suitable for your jewellery, use warm water and mild soap, then rinse and dry it completely. Avoid bleach, abrasive cleaners, and stiff brushes that may scratch the surface.',
                        'Store each piece separately in a fabric pouch or a soft-lined compartment to prevent friction. For jewellery with gemstones or special components, ask the store about recommended care before cleaning it.'
                    ]
                },
                'memilih-perhiasan-emas-sehari-hari': {
                    title: 'Choosing Gold Jewellery for Everyday Wear',
                    summary: 'Everyday jewellery should feel comfortable, be easy to style, and suit your routine. Start with simple shapes and sizes that do not restrict movement. A delicate necklace, small earrings, or a bracelet with a comfortable clasp can be practical choices.',
                    paragraphs: [
                        'Everyday jewellery should feel comfortable, be easy to style, and suit your routine. Start with simple shapes and sizes that do not restrict movement. A delicate necklace, small earrings, or a bracelet with a comfortable clasp can be practical choices.',
                        'Consider your daily activities. If you often use your hands, choose a style that is less likely to catch on things. Check the clasp, joints, and areas that touch your skin so it remains comfortable for extended wear.',
                        'There is no single style that suits everyone. Try on different designs, consider their weight and size, and choose the one that makes you feel confident. Our branch team can explain the products available.'
                    ]
                },
                'emas-sebagai-hadiah-istimewa': {
                    title: 'Gold as a Gift: Meaningful Memories for Special Moments',
                    summary: 'A gold gift can commemorate a birthday, family celebration, achievement, or another important occasion. Its emotional value often comes from the story behind the gift and the care taken in choosing it.',
                    paragraphs: [
                        'A gold gift can commemorate a birthday, family celebration, achievement, or another important occasion. Its emotional value often comes from the story behind the gift and the care taken in choosing it.',
                        'Choose a style that suits the recipient’s habits and taste. Notice the jewellery colours they usually wear, choose a comfortable size, and consider a design that is easy to pair with other pieces. If you are unsure, choose a classic style or ask whether exchanges are available under the store policy.',
                        'Before purchasing, ask for the product and price details, and keep the receipt and any documents provided. Thoughtful packaging and a personal note can complete the gift while keeping the focus on a positive experience.'
                    ]
                },
                'panduan-belanja-emas-bijak': {
                    title: 'A Thoughtful Guide to Buying Gold with Confidence',
                    summary: 'Before shopping, decide on your goal and a comfortable budget. Are you looking for jewellery to wear, a gift, or a particular type of gold? A clear purpose helps narrow your options without rushing.',
                    paragraphs: [
                        'Before shopping, decide on your goal and a comfortable budget. Are you looking for jewellery to wear, a gift, or a particular type of gold? A clear purpose helps narrow your options without rushing.',
                        'Ask for the relevant details: product purity or specifications, weight, price breakdown, applicable fees, and transaction documents. Make sure you understand the service terms and store policies before completing your purchase.',
                        'Compare products using equivalent information, not just appearance or a single price figure. Choose a seller willing to explain products clearly, read documents carefully, and keep your proof of purchase in a safe place.'
                    ]
                },
                'emas-dalam-perencanaan-keuangan': {
                    title: 'Understanding Gold’s Role in Financial Planning',
                    summary: 'Some people consider gold as one part of a long-term financial plan. However, gold prices can change and future results cannot be guaranteed. It is important to understand your goals, time horizon, and funding needs before making a decision.',
                    paragraphs: [
                        'Some people consider gold as one part of a long-term financial plan. However, gold prices can change and future results cannot be guaranteed. It is important to understand your goals, time horizon, and funding needs before making a decision.',
                        'Jewellery and gold products intended for saving have different characteristics. Jewellery offers design and wearability value, while other products may have different cost structures and resale mechanisms. Learn the transaction details and terms that apply to each product.',
                        'Use information from reliable sources, avoid decisions based on promises of guaranteed returns, and consider your personal financial circumstances. This article is for educational purposes only and is not financial advice or a guarantee of investment value.'
                    ]
                }
            };
            const originalTextNodes = new WeakMap();
            const originalElementText = new WeakMap();
            const originalAttributes = new WeakMap();
            const attributeTranslations = {
                'Cari nama cabang atau alamat': 'Search branch name or address',
                'Cari kota, nama cabang, atau alamat...': 'Search by city, branch name, or address...',
                'Tulis pertanyaan...': 'Type your question...',
                'Buka menu navigasi': 'Open navigation menu',
                'Tutup menu navigasi': 'Close navigation menu',
                'Navigasi utama': 'Main navigation',
                'Pilih bahasa': 'Choose language'
            };
            const translatePage = (language) => {
                const isEnglish = language === 'en';
                document.querySelectorAll('.post-card[data-article-slug]').forEach((card) => {
                    const title = card.querySelector('h3, h1, h2');
                    const excerpt = card.querySelector(':scope > p');
                    if (title && !originalElementText.has(title)) originalElementText.set(title, title.textContent);
                    if (excerpt && !originalElementText.has(excerpt)) originalElementText.set(excerpt, excerpt.textContent);
                });
                const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
                    acceptNode(node) {
                        return node.parentElement?.closest('script, style, .material-icons') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
                    }
                });
                while (walker.nextNode()) {
                    const node = walker.currentNode;
                    if (!originalTextNodes.has(node)) {
                        originalTextNodes.set(node, node.nodeValue);
                    }
                    const original = originalTextNodes.get(node);
                    const trimmed = original.trim();
                    let translated = isEnglish ? translations[trimmed] : trimmed;
                    if (isEnglish && /^\d+ lokasi resmi/.test(trimmed)) {
                        translated = trimmed.replace(' lokasi resmi', ' official locations');
                    } else if (isEnglish && /^Dipublikasikan:?(\s|$)/.test(trimmed)) {
                        translated = trimmed.replace(/^Dipublikasikan:?/, 'Published');
                    }
                    if (translated) {
                        node.nodeValue = original.replace(trimmed, translated);
                    } else if (!isEnglish) {
                        node.nodeValue = original;
                    }
                }

                document.querySelectorAll('.post-content[data-article-slug]').forEach((content) => {
                    const article = articleTranslations[content.dataset.articleSlug];
                    const paragraphs = Array.from(content.querySelectorAll('p'));
                    if (!article || paragraphs.length !== article.paragraphs.length) {
                        return;
                    }
                    paragraphs.forEach((paragraph, index) => {
                        if (!originalElementText.has(paragraph)) originalElementText.set(paragraph, paragraph.textContent);
                        paragraph.textContent = isEnglish ? article.paragraphs[index] : originalElementText.get(paragraph);
                    });
                });

                document.querySelectorAll('[placeholder], [aria-label], [title], img[alt]').forEach((element) => {
                    ['placeholder', 'aria-label', 'title', 'alt'].forEach((attribute) => {
                        if (!element.hasAttribute(attribute)) {
                            return;
                        }
                        if (!originalAttributes.has(element)) originalAttributes.set(element, new Map());
                        const attributes = originalAttributes.get(element);
                        if (!attributes.has(attribute)) attributes.set(attribute, element.getAttribute(attribute));
                        const original = attributes.get(attribute);
                        const value = isEnglish ? (attributeTranslations[original] ?? translations[original] ?? original) : original;
                        element.setAttribute(attribute, value);
                    });
                });

                document.querySelectorAll('.post-card[data-article-slug]').forEach((card) => {
                    const article = articleTranslations[card.dataset.articleSlug];
                    if (!article) {
                        return;
                    }
                    const title = card.querySelector('h3, h1, h2');
                    const excerpt = card.querySelector(':scope > p');
                    if (title) {
                        if (!originalElementText.has(title)) originalElementText.set(title, title.textContent);
                        title.textContent = isEnglish ? article.title : originalElementText.get(title);
                    }
                    if (excerpt) {
                        if (!originalElementText.has(excerpt)) originalElementText.set(excerpt, excerpt.textContent);
                        excerpt.textContent = isEnglish ? article.summary : originalElementText.get(excerpt);
                    }
                });

                document.documentElement.lang = isEnglish ? 'en' : 'id';
                document.title = isEnglish ? (translations[document.body.dataset.indonesianTitle] ?? document.body.dataset.indonesianTitle) : document.body.dataset.indonesianTitle;
                document.querySelectorAll('[data-language]').forEach((button) => {
                    button.setAttribute('aria-pressed', String(button.dataset.language === language));
                });
                const search = document.querySelector('#branch-search');
                if (search) {
                    search.setAttribute('placeholder', isEnglish ? 'Search by city, branch name, or address...' : 'Cari kota, nama cabang, atau alamat...');
                    search.setAttribute('aria-label', isEnglish ? 'Search branch name or address' : 'Cari nama cabang atau alamat');
                }
            };
            document.body.dataset.indonesianTitle = document.title;
            document.querySelectorAll('[data-language]').forEach((button) => {
                button.addEventListener('click', () => {
                    const language = button.dataset.language;
                    localStorage.setItem('rei-language', language);
                    translatePage(language);
                });
            });
            translatePage(localStorage.getItem('rei-language') === 'en' ? 'en' : 'id');

            const siteNav = document.querySelector('#top-nav');
            const menuToggle = siteNav?.querySelector('.mobile-menu-toggle');
            const primaryNavigation = siteNav?.querySelector('#primary-navigation');
            const mobileNavigation = window.matchMedia('(max-width: 1500px)');
            if (siteNav && menuToggle && primaryNavigation) {
                const setMenuOpen = (isOpen) => {
                    primaryNavigation.classList.toggle('is-open', isOpen);
                    menuToggle.setAttribute('aria-expanded', String(isOpen));
                    menuToggle.setAttribute('aria-label', isOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi');
                    menuToggle.querySelector('.material-icons').textContent = isOpen ? 'close' : 'menu';
                };

                menuToggle.addEventListener('click', () => {
                    setMenuOpen(menuToggle.getAttribute('aria-expanded') !== 'true');
                });
                primaryNavigation.querySelectorAll('a').forEach((link) => {
                    link.addEventListener('click', () => {
                        if (mobileNavigation.matches) {
                            setMenuOpen(false);
                        }
                    });
                });
                document.addEventListener('click', (event) => {
                    if (mobileNavigation.matches && !siteNav.contains(event.target)) {
                        setMenuOpen(false);
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && menuToggle.getAttribute('aria-expanded') === 'true') {
                        setMenuOpen(false);
                        menuToggle.focus();
                    }
                });
                mobileNavigation.addEventListener('change', () => setMenuOpen(false));
            }

            const heroSlider = document.querySelector('[data-hero-slider]');
            if (heroSlider) {
                const slides = Array.from(heroSlider.querySelectorAll('[data-hero-slide]'));
                const dots = Array.from(heroSlider.querySelectorAll('[data-hero-dot]'));
                const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                let activeIndex = 0;
                let timer;

                const showSlide = (index) => {
                    activeIndex = (index + slides.length) % slides.length;
                    slides.forEach((slide, slideIndex) => {
                        const active = slideIndex === activeIndex;
                        slide.classList.toggle('is-active', active);
                        slide.setAttribute('aria-hidden', String(!active));
                        dots[slideIndex].setAttribute('aria-current', String(active));
                    });
                };
                const stopAutoplay = () => window.clearInterval(timer);
                const startAutoplay = () => {
                    stopAutoplay();
                    if (!reducedMotion) {
                        timer = window.setInterval(() => showSlide(activeIndex + 1), 6500);
                    }
                };

                heroSlider.querySelector('[data-hero-previous]').addEventListener('click', () => {
                    showSlide(activeIndex - 1);
                    startAutoplay();
                });
                heroSlider.querySelector('[data-hero-next]').addEventListener('click', () => {
                    showSlide(activeIndex + 1);
                    startAutoplay();
                });
                dots.forEach((dot, index) => dot.addEventListener('click', () => {
                    showSlide(index);
                    startAutoplay();
                }));
                heroSlider.addEventListener('mouseenter', stopAutoplay);
                heroSlider.addEventListener('mouseleave', startAutoplay);
                heroSlider.addEventListener('focusin', stopAutoplay);
                heroSlider.addEventListener('focusout', (event) => {
                    if (!heroSlider.contains(event.relatedTarget)) {
                        startAutoplay();
                    }
                });
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        stopAutoplay();
                    } else {
                        startAutoplay();
                    }
                });
                startAutoplay();
            }

            const branchSearch = document.querySelector('#branch-search');
            if (branchSearch) {
                const branchCards = Array.from(document.querySelectorAll('[data-branch-searchable]'));
                const noBranchResults = document.querySelector('.branch-no-results');
                branchSearch.addEventListener('input', () => {
                    const query = branchSearch.value.trim().toLocaleLowerCase('id');
                    let visibleCount = 0;

                    branchCards.forEach((card) => {
                        const matches = card.dataset.branchSearchable.toLocaleLowerCase('id').includes(query);
                        card.hidden = !matches;
                        visibleCount += Number(matches);
                    });

                    noBranchResults.hidden = visibleCount !== 0;
                });
            }

            const chatbot = document.querySelector('#chatbot-panel');
            const chatbotLauncher = document.querySelector('[data-chat-open]');
            const chatbotMessages = chatbot?.querySelector('[data-chat-messages]');
            const chatbotForm = chatbot?.querySelector('[data-chat-form]');
            if (chatbot && chatbotLauncher && chatbotMessages && chatbotForm) {
                const chatbotInput = chatbotForm.querySelector('input[name="message"]');
                const closeChatbot = () => {
                    chatbot.classList.remove('is-open');
                    chatbot.setAttribute('aria-hidden', 'true');
                    chatbotLauncher.setAttribute('aria-expanded', 'false');
                };
                const addChatMessage = (text, isUser = false, link = null) => {
                    const message = document.createElement('div');
                    message.className = `chat-message${isUser ? ' is-user' : ''}`;
                    message.textContent = text;

                    if (link) {
                        const anchor = document.createElement('a');
                        anchor.href = link.href;
                        anchor.textContent = link.label;
                        if (link.external) {
                            anchor.target = '_blank';
                            anchor.rel = 'noopener noreferrer';
                        }
                        message.append(document.createElement('br'), anchor);
                    }

                    chatbotMessages.append(message);
                    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
                };
                const answerChatMessage = (question) => {
                    const text = question.toLocaleLowerCase('id');
                    if (/cabang|lokasi|alamat|kota|outlet|branches?|locations?/.test(text)) {
                        addChatMessage(document.documentElement.lang === 'en' ? 'Browse our branch directory by city or name. Select a location to view its address and contact details.' : 'Kami menyediakan daftar lokasi cabang yang dapat dicari berdasarkan kota atau nama. Pilih lokasi untuk melihat alamat dan kontak.', false, { href: '?page=cabang', label: document.documentElement.lang === 'en' ? 'View all branches' : 'Lihat daftar cabang' });
                    } else if (/artikel|blog|rawat|karat|perhiasan|investasi|articles?|jewellery|care|investment/.test(text)) {
                        addChatMessage(document.documentElement.lang === 'en' ? 'Explore our educational articles about gold purity, jewellery care, and choosing gold.' : 'Kami punya artikel edukasi tentang kadar emas, perawatan perhiasan, dan panduan memilih emas.', false, { href: '?page=blog', label: document.documentElement.lang === 'en' ? 'Read gold articles' : 'Baca artikel emas' });
                    } else if (/layan|jual|beli|surat|harga|emas|services?|sell|buy|price|gold/.test(text)) {
                        addChatMessage(document.documentElement.lang === 'en' ? 'Service information and helpful details are available on our services page. For current prices and availability, please confirm directly with a branch.' : 'Informasi layanan dan hal yang perlu diperhatikan tersedia di halaman layanan. Untuk harga dan ketersediaan terkini, silakan konfirmasi langsung ke cabang.', false, { href: '?page=service', label: document.documentElement.lang === 'en' ? 'Explore services' : 'Lihat informasi layanan' });
                    } else if (/kontak|hubungi|whatsapp|wa|contact|reach/.test(text)) {
                        addChatMessage(document.documentElement.lang === 'en' ? 'Contact details and opening hours are listed on each branch page.' : 'Nomor kontak dan jam operasional tersedia pada masing-masing halaman cabang.', false, { href: '?page=cabang', label: document.documentElement.lang === 'en' ? 'Choose a branch' : 'Pilih cabang untuk kontak' });
                    } else {
                        addChatMessage(document.documentElement.lang === 'en' ? 'I can help you find a branch, learn about our services, or discover educational articles. Choose a topic and I will point you in the right direction.' : 'Saya bisa membantu menemukan cabang, informasi layanan, atau artikel edukasi. Pilih salah satu topik agar saya arahkan ke informasi yang tepat.');
                    }
                };

                chatbotLauncher.addEventListener('click', () => {
                    const isOpen = chatbot.classList.toggle('is-open');
                    chatbot.setAttribute('aria-hidden', String(!isOpen));
                    chatbotLauncher.setAttribute('aria-expanded', String(isOpen));
                    if (isOpen) {
                        chatbotInput.focus();
                    }
                });
                chatbot.querySelector('[data-chat-close]').addEventListener('click', closeChatbot);
                chatbot.querySelectorAll('[data-chat-prompt]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const question = button.textContent.trim();
                        addChatMessage(question, true);
                        answerChatMessage(button.dataset.chatPrompt);
                    });
                });
                chatbotForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const question = chatbotInput.value.trim();
                    if (!question) {
                        return;
                    }

                    addChatMessage(question, true);
                    answerChatMessage(question);
                    chatbotForm.reset();
                    chatbotInput.focus();
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && chatbot.classList.contains('is-open')) {
                        closeChatbot();
                        chatbotLauncher.focus();
                    }
                });
            }
        });

        let lastScroll = 0;
        const topNav = document.getElementById("top-nav");
        const bottomNav = document.getElementById("bottom-nav");
        
        window.addEventListener("scroll", () => {
            let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            // Posisi navbar atas/bawah diatur CSS lewat kelas ini (lihat "Lapisan responsif").
            document.body.classList.toggle("nav-scrolled", currentScroll > lastScroll && currentScroll > 50);
            lastScroll = currentScroll <= 0 ? 0 : currentScroll;
        });
    </script>
</body>
</html>
