
<?php
// =========================================================
// SIDEBAR REI CMS
// =========================================================
// Session dan autentikasi ditangani oleh halaman pemanggil.

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');

// Ambil halaman dari controller admin.php
$currentPage = $page ?? ($_GET['page'] ?? 'dashboard');

// Halaman yang termasuk bagian Post
$postScripts = [
    'add.php',
    'edit.php',
    'delete_post.php',
];

// Jika halaman berdiri sendiri, tandai menu Post aktif
if (in_array($currentScript, $postScripts, true)) {
    $currentPage = 'posts';
}

// Jika halaman sedang berada di bagian artikel
$isPostSection = in_array(
    $currentPage,
    ['posts', 'categories'],
    true
);

$navBase = 'flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl transition';
$navActive = 'bg-cyan-600 text-white shadow-md shadow-cyan-200';
$navInactive = 'text-gray-600 hover:bg-gray-50';

$subBase = 'flex items-center gap-2 pl-8 pr-4 py-2.5 text-xs font-medium rounded-xl transition';
$subActive = 'bg-gray-200 text-gray-900 font-semibold shadow-sm';
$subInactive = 'text-gray-600 hover:bg-gray-100 hover:text-gray-900';
?>

<aside
    id="reiSidebar"
    class="hidden md:flex flex-col w-72 shrink-0 bg-white rounded-3xl shadow-xl border border-gray-100/80 z-20 overflow-hidden transition-all duration-300"
>
    <!-- LOGO -->
    <div class="flex items-center justify-center h-20 px-4 bg-gradient-to-br from-purple-600 to-black shadow-inner m-3 rounded-2xl shrink-0">
        <a
            href="admin.php?page=dashboard"
            class="flex items-center justify-center w-full h-full"
        >
            <img
                src="logo-rei.png"
                alt="Logo Raja Emas Indonesia"
                class="max-h-14 max-w-full w-auto object-contain filter brightness-150 contrast-125 drop-shadow-md"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
            >

            <span class="hidden text-white text-sm font-bold text-center">
                RAJA EMAS INDONESIA
            </span>
        </a>
    </div>

    <!-- MENU NAVIGASI -->
    <nav class="flex-1 px-3 py-2 space-y-1.5 overflow-y-auto sidebar-scroll">

        <!-- DASHBOARD -->
        <a
            href="admin.php?page=dashboard"
            class="<?= $navBase ?> <?= $currentPage === 'dashboard' ? $navActive : $navInactive ?>"
            <?= $currentPage === 'dashboard' ? 'aria-current="page"' : '' ?>
        >
            <span class="material-icons text-lg">dashboard</span>
            <span>Dashboard</span>
        </a>

        <!-- POST -->
        <div class="space-y-1">

            <button
                type="button"
                onclick="toggleSubmenu('post-submenu', 'post-arrow')"
                aria-controls="post-submenu"
                aria-expanded="<?= $isPostSection ? 'true' : 'false' ?>"
                class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-2xl transition <?= $isPostSection ? $navActive : $navInactive ?>"
            >
                <span class="flex items-center gap-3">
                    <span class="material-icons text-lg">article</span>
                    <span>Post</span>
                </span>

                <span
                    id="post-arrow"
                    class="material-icons text-sm transform transition-transform duration-200 <?= $isPostSection ? 'rotate-180' : '' ?>"
                >
                    expand_more
                </span>
            </button>

            <!-- SUBMENU POST -->
            <div
                id="post-submenu"
                class="<?= $isPostSection ? 'block' : 'hidden' ?> pl-3 space-y-1"
            >
                <a
                    href="admin.php?page=posts"
                    class="<?= $subBase ?> <?= $currentPage === 'posts' ? $subActive : $subInactive ?>"
                    <?= $currentPage === 'posts' ? 'aria-current="page"' : '' ?>
                >
                    <span class="material-icons text-xs">list</span>
                    <span>Semua Artikel</span>
                </a>

                <a
                    href="admin.php?page=categories"
                    class="<?= $subBase ?> <?= $currentPage === 'categories' ? $subActive : $subInactive ?>"
                    <?= $currentPage === 'categories' ? 'aria-current="page"' : '' ?>
                >
                    <span class="material-icons text-xs">category</span>
                    <span>Kategori Post</span>
                </a>
            </div>
        </div>

        <!-- HALAMAN -->
        <a
            href="admin.php?page=pages"
            class="<?= $navBase ?> <?= $currentPage === 'pages' ? $navActive : $navInactive ?>"
        >
            <span class="material-icons text-lg">description</span>
            <span>Halaman</span>
        </a>

        <!-- MENU -->
        <a
            href="admin.php?page=menus"
            class="<?= $navBase ?> <?= $currentPage === 'menus' ? $navActive : $navInactive ?>"
        >
            <span class="material-icons text-lg">menu</span>
            <span>Menu</span>
        </a>

        <!-- SLIDER -->
        <a
            href="admin.php?page=slider"
            class="<?= $navBase ?> <?= $currentPage === 'slider' ? $navActive : $navInactive ?>"
        >
            <span class="material-icons text-lg">collections</span>
            <span>Slider</span>
        </a>

        <!-- CABANG TOKO -->
        <a
            href="admin.php?page=branches"
            class="<?= $navBase ?> <?= $currentPage === 'branches' ? $navActive : $navInactive ?>"
        >
            <span class="material-icons text-lg">storefront</span>
            <span>Cabang Toko</span>
        </a>

        <!-- TEMA -->
        <a
            href="admin.php?page=theme"
            class="<?= $navBase ?> <?= $currentPage === 'theme' ? $navActive : $navInactive ?>"
        >
            <span class="material-icons text-lg">palette</span>
            <span>Tema</span>
        </a>

        <!-- WEBSITE -->
        <a
            href="index.php"
            target="_blank"
            rel="noopener noreferrer"
            class="<?= $navBase ?> <?= $navInactive ?>"
        >
            <span class="material-icons text-lg">open_in_new</span>
            <span>Website</span>
        </a>

    </nav>

    <!-- LOGOUT -->
    <div class="p-3 border-t border-gray-100 bg-gray-50/50 shrink-0">
        <a
            href="logout.php"
            class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-black rounded-xl hover:opacity-95 transition shadow-sm"
        >
            <span class="material-icons text-lg">logout</span>
            <span>Logout</span>
        </a>
    </div>
</aside>

<script>
(function () {
    if (typeof window.toggleSubmenu !== 'function') {
        window.toggleSubmenu = function (submenuId, arrowId) {
            const submenu = document.getElementById(submenuId);
            const arrow = document.getElementById(arrowId);

            if (!submenu || !arrow) {
                return;
            }

            const isHidden = submenu.classList.contains('hidden');

            submenu.classList.toggle('hidden', !isHidden);
            arrow.classList.toggle('rotate-180', isHidden);

            const button = arrow.closest('button');

            if (button) {
                button.setAttribute(
                    'aria-expanded',
                    isHidden ? 'true' : 'false'
                );
            }
        };
    }
})();
</script>
