
<?php
session_start();

/*
|--------------------------------------------------------------------------
| AUTENTIKASI ADMIN
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| KONFIGURASI
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/config.php';

// Agar sidebar menandai menu Post sebagai aktif.
$page = 'posts';

$error = '';
$success = '';
$post = null;

/*
|--------------------------------------------------------------------------
| VALIDASI ID ARTIKEL
|--------------------------------------------------------------------------
*/
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: admin.php?page=posts');
    exit;
}

/*
|--------------------------------------------------------------------------
| PASTIKAN KONEKSI PDO TERSEDIA
|--------------------------------------------------------------------------
| Kode ini mengasumsikan config.php membuat variabel $pdo.
*/
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit(
        'Koneksi database PDO tidak ditemukan. ' .
        'Periksa variabel koneksi di config.php.'
    );
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/*
|--------------------------------------------------------------------------
| AMBIL DATA ARTIKEL
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->prepare(
        'SELECT * FROM posts WHERE id = :id LIMIT 1'
    );

    $stmt->execute(['id' => $id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        header('Location: admin.php?page=posts&not_found=1');
        exit;
    }
} catch (PDOException $e) {
    error_log('Edit artikel - SELECT: ' . $e->getMessage());
    http_response_code(500);
    exit('Gagal mengambil data artikel. Periksa log aplikasi.');
}

/*
|--------------------------------------------------------------------------
| NILAI FORM
|--------------------------------------------------------------------------
*/
$title = (string) ($post['title'] ?? '');
$content = (string) ($post['content'] ?? '');

/*
|--------------------------------------------------------------------------
| PROSES SIMPAN PERUBAHAN
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $content = (string) ($_POST['content'] ?? '');

    if ($title === '') {
        $error = 'Judul artikel wajib diisi.';
    } elseif (trim(strip_tags($content)) === '') {
        $error = 'Isi artikel tidak boleh kosong.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'UPDATE posts
                 SET title = :title, content = :content
                 WHERE id = :id'
            );

            $stmt->execute([
                'title' => $title,
                'content' => $content,
                'id' => $id,
            ]);

            header(
                'Location: admin.php?page=posts&updated=1'
            );
            exit;
        } catch (PDOException $e) {
            error_log('Edit artikel - UPDATE: ' . $e->getMessage());
            $error = 'Artikel gagal disimpan. Silakan coba kembali.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/
function editEscape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Artikel - REI CMS</title>

    <!-- Font sama dengan admin.php -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Material Icons -->
    <link
        href="https://fonts.googleapis.com/icon?family=Material+Icons"
        rel="stylesheet"
    >

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    colors: {
                        rei: {
                            cyan: '#0891b2',
                            purple: '#421a40'
                        }
                    }
                }
            }
        };
    </script>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f8fafc;
        }

        .page-scroll {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }

        .page-scroll::-webkit-scrollbar {
            width: 7px;
        }

        .page-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .page-scroll::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 10px;
        }

        .page-scroll::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .material-icons {
            vertical-align: middle;
            line-height: 1;
        }

        input:focus {
            outline: none;
        }

        .tox-tinymce {
            border-radius: 12px !important;
            border-color: #e5e7eb !important;
            overflow: hidden;
        }

        .tox .tox-edit-area::before {
            border-color: #0891b2 !important;
        }

        @media (max-width: 767px) {
            .editor-shell {
                padding: 16px !important;
            }

            .page-header {
                align-items: flex-start;
                gap: 12px;
            }

            .page-header h1 {
                font-size: 18px;
            }

            .back-label {
                display: none;
            }
        }
    </style>
</head>

<body class="text-gray-800 antialiased">

    <div class="flex h-screen overflow-hidden p-3 md:p-4 gap-4">

        <!-- SIDEBAR YANG SAMA DENGAN ADMIN.PHP -->
        <?php require __DIR__ . '/sidebar.php'; ?>

        <!-- PANEL UTAMA -->
        <div class="flex flex-col flex-1 min-w-0 h-full
                    bg-white rounded-3xl shadow-xl
                    border border-gray-100/80 overflow-hidden">

            <!-- HEADER -->
            <header class="page-header flex items-center justify-between
                           min-h-20 px-5 md:px-8 py-4 bg-white
                           border-b border-gray-100 shrink-0">

                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-xs text-gray-400 mb-2">
                        <span>REI CMS</span>
                        <span class="material-icons" style="font-size:14px">
                            chevron_right
                        </span>
                        <a
                            href="admin.php?page=posts"
                            class="hover:text-cyan-600 transition"
                        >
                            Post
                        </a>
                        <span class="material-icons" style="font-size:14px">
                            chevron_right
                        </span>
                        <span class="text-cyan-700 font-medium">Edit</span>
                    </div>

                    <h1 class="text-xl font-bold text-gray-800">
                        Edit Artikel
                    </h1>

                    <p class="text-sm text-gray-500 mt-1">
                        Perbarui informasi artikel website REI.
                    </p>
                </div>

                <a
                    href="admin.php?page=posts"
                    class="inline-flex items-center justify-center gap-2
                           px-4 py-2.5 rounded-xl border border-gray-200
                           bg-white text-gray-600 text-sm font-semibold
                           hover:bg-gray-50 hover:text-gray-800
                           transition shrink-0"
                >
                    <span class="material-icons text-lg">arrow_back</span>
                    <span class="back-label">Kembali</span>
                </a>

            </header>

            <!-- KONTEN -->
            <main class="page-scroll flex-1 overflow-y-auto
                         p-5 md:p-8 bg-gray-50/40">

                <div class="max-w-5xl mx-auto space-y-6">

                    <!-- INFORMASI HALAMAN -->
                    <div class="flex items-start gap-4">
                        <div class="flex items-center justify-center
                                    w-12 h-12 rounded-2xl
                                    bg-cyan-50 text-cyan-700 shrink-0">
                            <span class="material-icons text-2xl">
                                edit_document
                            </span>
                        </div>

                        <div>
                            <h2 class="text-base font-bold text-gray-800">
                                Informasi Artikel
                            </h2>
                            <p class="text-sm text-gray-500 mt-1">
                                Edit judul dan konten artikel di bawah ini.
                                Perubahan akan disimpan ke database.
                            </p>
                        </div>
                    </div>

                    <!-- PESAN ERROR -->
                    <?php if ($error !== ''): ?>
                        <div class="flex items-start gap-3 p-4 rounded-xl
                                    border border-red-200 bg-red-50 text-red-700">
                            <span class="material-icons">error_outline</span>
                            <div class="text-sm font-medium">
                                <?= editEscape($error) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- FORM EDIT -->
                    <form
                        id="editPostForm"
                        method="POST"
                        action="edit.php?id=<?= (int) $id ?>"
                        class="bg-white rounded-2xl border border-gray-200
                               shadow-sm overflow-hidden"
                    >

                        <!-- CARD HEADER -->
                        <div class="px-5 md:px-7 py-5
                                    border-b border-gray-100
                                    flex items-center justify-between gap-3">

                            <div>
                                <h3 class="font-bold text-gray-800">
                                    Edit Konten
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">
                                    ID artikel: #<?= (int) $id ?>
                                </p>
                            </div>

                            <span class="inline-flex items-center gap-1.5
                                         px-3 py-1.5 rounded-lg
                                         bg-cyan-50 text-cyan-700
                                         text-xs font-semibold">
                                <span class="material-icons" style="font-size:16px">
                                    edit
                                </span>
                                Mode Edit
                            </span>

                        </div>

                        <!-- FIELD FORM -->
                        <div class="editor-shell p-5 md:p-7 space-y-6">

                            <!-- JUDUL -->
                            <div>
                                <label
                                    for="title"
                                    class="block text-sm font-semibold
                                           text-gray-700 mb-2"
                                >
                                    Judul Artikel
                                    <span class="text-red-500">*</span>
                                </label>

                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    value="<?= editEscape($title) ?>"
                                    maxlength="255"
                                    required
                                    placeholder="Masukkan judul artikel"
                                    class="w-full px-4 py-3 rounded-xl
                                           border border-gray-200 bg-white
                                           text-sm text-gray-800
                                           placeholder:text-gray-400
                                           focus:border-cyan-500
                                           focus:ring-4 focus:ring-cyan-500/10
                                           transition"
                                >

                                <p class="text-xs text-gray-400 mt-2">
                                    Gunakan judul yang jelas dan sesuai isi artikel.
                                </p>
                            </div>

                            <!-- ISI ARTIKEL -->
                            <div>
                                <div class="flex items-center justify-between
                                            gap-3 mb-2">
                                    <label
                                        for="content"
                                        class="block text-sm font-semibold text-gray-700"
                                    >
                                        Isi Artikel
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <span class="text-xs text-gray-400">
                                        Editor teks
                                    </span>
                                </div>

                                <textarea
                                    id="content"
                                    name="content"
                                    required
                                ><?= editEscape($content) ?></textarea>

                                <p class="text-xs text-gray-400 mt-2">
                                    Pastikan isi artikel sudah benar sebelum disimpan.
                                </p>
                            </div>

                        </div>

                        <!-- FOOTER FORM -->
                        <div class="px-5 md:px-7 py-5
                                    bg-gray-50/70 border-t border-gray-100
                                    flex flex-col-reverse sm:flex-row
                                    items-stretch sm:items-center
                                    justify-between gap-3">

                            <a
                                href="admin.php?page=posts"
                                class="inline-flex items-center justify-center
                                       gap-2 px-5 py-3 rounded-xl
                                       border border-gray-200 bg-white
                                       text-gray-600 text-sm font-semibold
                                       hover:bg-gray-100 transition"
                            >
                                <span class="material-icons text-lg">close</span>
                                Batal
                            </a>

                            <button
                                type="submit"
                                id="saveButton"
                                class="inline-flex items-center justify-center
                                       gap-2 px-6 py-3 rounded-xl
                                       bg-cyan-600 text-white
                                       text-sm font-semibold
                                       shadow-lg shadow-cyan-600/20
                                       hover:bg-cyan-700
                                       active:scale-[0.98]
                                       transition"
                            >
                                <span class="material-icons text-lg">
                                    save
                                </span>
                                <span>Simpan Perubahan</span>
                            </button>

                        </div>

                    </form>

                    <!-- CATATAN -->
                    <div class="flex items-start gap-3 px-1 py-2">
                        <span class="material-icons text-gray-400 text-lg">
                            info
                        </span>
                        <p class="text-xs leading-5 text-gray-500">
                            Periksa kembali judul dan isi artikel sebelum menyimpan.
                            Setelah berhasil, Anda akan diarahkan kembali ke halaman Post.
                        </p>
                    </div>

                </div>
            </main>
        </div>
    </div>

  
<script>
document.addEventListener('DOMContentLoaded', function () {
    const contentField = document.querySelector('#content');
    const form = document.querySelector('#editPostForm');
    const saveButton = document.querySelector('#saveButton');

    if (!contentField || !form) return;

    ClassicEditor
        .create(contentField, {
            toolbar: [
                'undo', 'redo', '|',
                'heading', '|',
                'bold', 'italic', 'underline', '|',
                'bulletedList', 'numberedList', '|',
                'link', 'insertTable', '|',
                'blockQuote', 'removeFormat'
            ]
        })
        .then(editor => {
            form.addEventListener('submit', function (event) {
                const title = document.querySelector('#title');

                // Sinkronkan konten editor ke textarea sebelum dikirim.
                contentField.value = editor.getData();

                if (!title.value.trim()) {
                    event.preventDefault();
                    title.focus();
                    alert('Judul artikel wajib diisi.');
                    return;
                }

                const plainText = editor.getData()
                    .replace(/<[^>]*>/g, '')
                    .trim();

                if (!plainText) {
                    event.preventDefault();
                    editor.editing.view.focus();
                    alert('Isi artikel tidak boleh kosong.');
                    return;
                }

                if (saveButton) {
                    saveButton.disabled = true;
                    saveButton.classList.add(
                        'opacity-70',
                        'cursor-not-allowed'
                    );

                    const buttonText =
                        saveButton.querySelector('span:last-child');

                    if (buttonText) {
                        buttonText.textContent = 'Menyimpan...';
                    }
                }
            });
        })
        .catch(error => {
            console.error('Editor gagal dimuat:', error);
            alert('Editor gagal dimuat. Periksa koneksi internet.');
        });
});
</script>

</body>
</html>
