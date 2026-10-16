
<?php
session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';

$msg = '';
$categories = [];

$title = '';
$content = '';
$categoryId = '';

/*
|--------------------------------------------------------------------------
| Ambil kategori artikel
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query(
        "SELECT id, name FROM post_categories ORDER BY name ASC"
    );

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Gagal mengambil kategori: ' . $e->getMessage());
    $msg = 'Kategori gagal dimuat. Periksa tabel post_categories.';
}

/*
|--------------------------------------------------------------------------
| Proses simpan artikel
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';

    if ($title === '') {
        $msg = 'Judul artikel wajib diisi.';
    } elseif ($content === '') {
        $msg = 'Konten artikel wajib diisi.';
    } else {
        // Membuat slug dari judul
        $slug = strtolower(
            trim(
                preg_replace('/[^a-zA-Z0-9]+/', '-', $title),
                '-'
            )
        );

        $categoryId = $categoryId !== '' ? (int) $categoryId : null;
        $featuredImage = null;

        /*
        |--------------------------------------------------------------------------
        | Upload gambar utama
        |--------------------------------------------------------------------------
        */
        if (
            isset($_FILES['featured_image']) &&
            $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES['featured_image'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $msg = 'Upload gambar gagal. Silakan coba kembali.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $msg = 'Ukuran gambar maksimal 5 MB.';
            } else {
                $uploadDir = __DIR__ . '/uploads/';

                if (
                    !is_dir($uploadDir) &&
                    !mkdir($uploadDir, 0755, true) &&
                    !is_dir($uploadDir)
                ) {
                    $msg = 'Folder uploads tidak dapat dibuat.';
                } else {
                    // Validasi berdasarkan MIME file
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($file['tmp_name']);

                    $allowedTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                    ];

                    if (!isset($allowedTypes[$mimeType])) {
                        $msg = 'Format gambar harus JPG, PNG, atau WEBP.';
                    } else {
                        $extension = $allowedTypes[$mimeType];

                        $fileName = bin2hex(random_bytes(16))
                            . '.' . $extension;

                        $destination = $uploadDir . $fileName;

                        if (
                            move_uploaded_file(
                                $file['tmp_name'],
                                $destination
                            )
                        ) {
                            // Path yang disimpan di database
                            $featuredImage = 'uploads/' . $fileName;
                        } else {
                            $msg = 'Gambar gagal disimpan ke folder uploads.';
                        }
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan ke database
        |--------------------------------------------------------------------------
        */
        if ($msg === '') {
            try {
                // Pastikan kategori yang dipilih memang tersedia
                if ($categoryId !== null) {
                    $checkCategory = $pdo->prepare(
                        'SELECT id FROM post_categories WHERE id = ? LIMIT 1'
                    );
                    $checkCategory->execute([$categoryId]);

                    if (!$checkCategory->fetchColumn()) {
                        throw new RuntimeException(
                            'Kategori yang dipilih tidak ditemukan.'
                        );
                    }
                }

                // Pastikan slug unik
                $baseSlug = $slug;
                $counter = 1;

                $checkSlug = $pdo->prepare(
                    'SELECT id FROM posts WHERE slug = ? LIMIT 1'
                );

                while (true) {
                    $checkSlug->execute([$slug]);

                    if (!$checkSlug->fetchColumn()) {
                        break;
                    }

                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO posts
                    (title, slug, content, featured_image, category_id, type)
                    VALUES (?, ?, ?, ?, ?, 'post')"
                );

                $stmt->execute([
                    $title,
                    $slug,
                    $content,
                    $featuredImage,
                    $categoryId
                ]);

                header('Location: admin.php?page=posts');
                exit;
            } catch (Throwable $e) {
                error_log('Gagal menyimpan artikel: ' . $e->getMessage());

                $msg = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Artikel gagal disimpan. Periksa struktur tabel posts.';

                // Hapus file jika upload berhasil tetapi penyimpanan DB gagal
                if ($featuredImage !== null) {
                    $uploadedPath = __DIR__ . '/' . $featuredImage;

                    if (is_file($uploadedPath)) {
                        unlink($uploadedPath);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Artikel Baru - REI-CMS</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/icon?family=Material+Icons"
        rel="stylesheet"
    >

    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        .tox-tinymce {
            border-radius: 12px !important;
            overflow: hidden;
        }

        @media (max-width: 767px) {
            .page-shell {
                padding: 10px;
                gap: 10px;
            }

            .main-panel {
                border-radius: 20px;
            }

            .page-header {
                padding-left: 16px;
                padding-right: 16px;
            }

            .page-content {
                padding: 16px;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            tinymce.init({
                selector: 'textarea.editor',
                height: 350,
                menubar: false,
                branding: false,
                plugins: 'lists link code image',
                toolbar:
                    'undo redo | blocks | bold italic | ' +
                    'alignleft aligncenter alignright | ' +
                    'bullist numlist | link image | code',
                content_style:
                    'body { font-family: Inter, sans-serif; font-size: 14px; }',
                promotion: false
            });
        });
    </script>
</head>

<body class="text-gray-800 antialiased">

    <div class="page-shell flex min-h-screen overflow-hidden p-3 md:p-4 gap-4">

        <!-- SIDEBAR DARI FILE TERPISAH -->
        <?php require __DIR__ . '/sidebar.php'; ?>

        <!-- KONTEN UTAMA -->
        <div class="main-panel flex flex-col flex-1 min-w-0 min-h-[calc(100vh-2rem)] bg-white rounded-3xl shadow-xl border border-gray-100/80 overflow-hidden">

            <!-- HEADER -->
            <header class="page-header flex items-center justify-between min-h-20 px-8 py-4 bg-white border-b border-gray-100 shrink-0">

                <div class="flex items-center gap-3 min-w-0">
                    <a
                        href="admin.php?page=posts"
                        class="text-gray-400 hover:text-gray-700 flex items-center shrink-0"
                        aria-label="Kembali ke daftar artikel"
                    >
                        <span class="material-icons">arrow_back</span>
                    </a>

                    <div class="min-w-0">
                        <h1 class="text-lg md:text-xl font-bold text-gray-800">
                            Tambah Artikel Baru
                        </h1>

                        <p class="text-xs text-gray-500 mt-1">
                            Buat dan publikasikan artikel website REI
                        </p>
                    </div>
                </div>

                <a
                    href="admin.php?page=posts"
                    class="hidden sm:inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition"
                >
                    <span class="material-icons text-lg">close</span>
                    Batal
                </a>
            </header>

            <!-- FORM ARTIKEL -->
            <main class="page-content flex-1 overflow-y-auto p-6 md:p-8 bg-gray-50/60">

                <div class="max-w-5xl mx-auto">

                    <?php if ($msg !== ''): ?>
                        <div
                            role="alert"
                            class="mb-6 p-4 bg-red-50 text-red-700 rounded-2xl text-sm border border-red-100"
                        >
                            <div class="flex items-start gap-2">
                                <span class="material-icons text-lg">error_outline</span>

                                <span>
                                    <?= htmlspecialchars(
                                        $msg,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form
                        id="articleForm"
                        method="POST"
                        enctype="multipart/form-data"
                        class="space-y-6"
                    >

                        <div class="bg-white p-5 md:p-7 rounded-2xl border border-gray-200 shadow-sm space-y-6">

                            <!-- JUDUL -->
                            <div>
                                <label
                                    for="title"
                                    class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2"
                                >
                                    Judul Artikel
                                </label>

                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    value="<?= htmlspecialchars(
                                        $title,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    placeholder="Masukkan judul artikel..."
                                    required
                                    maxlength="255"
                                    class="w-full p-3.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-100 focus:border-teal-700"
                                >
                            </div>

                            <!-- KATEGORI DAN GAMBAR -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                                <div>
                                    <label
                                        for="category_id"
                                        class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2"
                                    >
                                        Kategori Artikel
                                    </label>

                                    <select
                                        id="category_id"
                                        name="category_id"
                                        class="w-full p-3.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-100 focus:border-teal-700"
                                    >
                                        <option value="">-- Pilih Kategori --</option>

                                        <?php foreach ($categories as $cat): ?>
                                            <option
                                                value="<?= (int) $cat['id'] ?>"
                                                <?= (string) $categoryId === (string) $cat['id'] ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars(
                                                    $cat['name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <?php if (empty($categories)): ?>
                                        <p class="text-xs text-amber-600 mt-2">
                                            Belum ada kategori atau kategori gagal dimuat.
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label
                                        for="featured_image"
                                        class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2"
                                    >
                                        Gambar Utama / Banner
                                    </label>

                                    <input
                                        type="file"
                                        id="featured_image"
                                        name="featured_image"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        class="w-full text-sm file:mr-3 file:py-2.5 file:px-3 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 border border-gray-200 rounded-xl"
                                    >

                                    <p class="text-xs text-gray-400 mt-2">
                                        JPG, PNG, WEBP. Maksimal 5 MB.
                                    </p>

                                    <div id="imagePreviewContainer" class="hidden mt-3">
                                        <img
                                            id="imagePreview"
                                            src=""
                                            alt="Pratinjau gambar artikel"
                                            class="max-h-48 rounded-xl border border-gray-200 object-contain"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- KONTEN -->
                            <div>
                                <label
                                    for="content"
                                    class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2"
                                >
                                    Konten Artikel
                                </label>

                                <textarea
                                    id="content"
                                    name="content"
                                    class="editor"
                                    required
                                ><?= htmlspecialchars(
                                    $content,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                                <p class="text-xs text-gray-400 mt-2">
                                    Gunakan editor untuk mengatur paragraf, judul,
                                    daftar, tautan, dan gambar dalam artikel.
                                </p>
                            </div>

                            <!-- TOMBOL -->
                            <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-5 border-t border-gray-100">

                                <a
                                    href="admin.php?page=posts"
                                    class="inline-flex justify-center items-center px-5 py-3 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition"
                                >
                                    Batal
                                </a>

                                <button
                                    id="submitButton"
                                    type="submit"
                                    class="inline-flex justify-center items-center gap-2 px-6 py-3 bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold rounded-xl shadow-md shadow-teal-100 transition"
                                >
                                    <span class="material-icons text-lg">publish</span>
                                    <span>Publikasikan Artikel</span>
                                </button>
                            </div>

                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Pratinjau gambar sebelum diunggah
        const imageInput = document.getElementById('featured_image');
        const imagePreview = document.getElementById('imagePreview');
        const previewContainer = document.getElementById('imagePreviewContainer');

        imageInput.addEventListener('change', function () {
            const file = this.files && this.files[0];

            if (!file) {
                imagePreview.src = '';
                previewContainer.classList.add('hidden');
                return;
            }

            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!allowedTypes.includes(file.type)) {
                alert('Format gambar harus JPG, PNG, atau WEBP.');
                this.value = '';
                previewContainer.classList.add('hidden');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran gambar maksimal 5 MB.');
                this.value = '';
                previewContainer.classList.add('hidden');
                return;
            }

            imagePreview.src = URL.createObjectURL(file);
            previewContainer.classList.remove('hidden');
        });

        // Mencegah klik berulang saat form dikirim
        document.getElementById('articleForm').addEventListener('submit', function () {
            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }

            const button = document.getElementById('submitButton');
            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-not-allowed');
            button.querySelector('span:last-child').textContent = 'Menyimpan...';
        });
    </script>

</body>
</html>
