<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title>Tester GET Branch API | REI</title>
    <style>
        :root{font-family:Inter,Segoe UI,Arial,sans-serif;color:#201b29;background:#f5f3f7;font-synthesis:none}
        *{box-sizing:border-box}body{margin:0}.wrap{max-width:1180px;margin:0 auto;padding:32px 18px 64px}
        header{margin-bottom:24px}.eyebrow{color:#8b6c28;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
        h1{margin:8px 0;font-size:clamp(26px,4vw,38px);color:#24152b}.lead{color:#6e6875;margin:0;line-height:1.6}
        .panel{background:#fff;border:1px solid #e6e1e9;border-radius:18px;padding:22px;margin:16px 0;box-shadow:0 8px 30px #291a2d0a}
        .grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px}.field{grid-column:span 4}.field.wide{grid-column:span 8}.field.full{grid-column:1/-1}
        label{display:block;margin-bottom:6px;font-weight:700;font-size:13px;color:#4a4351}.input,select{width:100%;min-height:44px;padding:10px 12px;border:1px solid #d8d2dc;border-radius:10px;background:#fff;color:#201b29;font:inherit}
        .input:focus,select:focus{outline:3px solid #dcebf1;border-color:#18829a}.help{color:#77717d;font-size:12px;margin:6px 0 0;line-height:1.45}
        .row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.btn{border:0;border-radius:10px;padding:11px 16px;background:#167d91;color:#fff;font-weight:800;cursor:pointer;min-height:42px}.btn:hover{background:#116a7b}.btn:disabled{opacity:.55;cursor:wait}.btn.alt{background:#eeeaf1;color:#392c40}.btn.alt:hover{background:#e2dce7}
        .coords{display:grid;grid-template-columns:1fr 1fr;gap:12px}.status{display:none;border-radius:10px;padding:12px 14px;margin-top:16px;font-size:13px;line-height:1.5}.status.show{display:block}.status.info{background:#eef8fb;color:#15596b}.status.error{background:#fff0f0;color:#912d36}.status.ok{background:#eff9f2;color:#22613a}
        .stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.stat{padding:14px;border-radius:12px;background:#f7f5f8}.stat small{display:block;color:#746d7b;font-size:12px}.stat strong{display:block;margin-top:5px;font-size:20px;color:#27192f}
        .results-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.results-head h2{margin:0;font-size:20px}.branch-list{display:grid;gap:12px;margin-top:16px}.branch{border:1px solid #e8e3eb;border-radius:14px;padding:16px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px}.branch h3{margin:0 0 7px;font-size:16px}.branch p{margin:4px 0;color:#696371;font-size:13px;line-height:1.5;overflow-wrap:anywhere}.branch-meta{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}.pill{display:inline-flex;align-items:center;padding:4px 9px;border-radius:999px;background:#f0edf3;color:#544b5b;font-size:11px;font-weight:700}.pill.active{background:#e8f6ec;color:#27643a}.pill.soon{background:#fff5dd;color:#765414}.pill.temp{background:#fff0e5;color:#89511f}.pill.closed{background:#ffebed;color:#8f2833}.distance{font-weight:800;color:#167d91;white-space:nowrap}.empty{padding:26px;text-align:center;color:#716b77;border:1px dashed #d8d2dc;border-radius:12px;margin-top:16px}
        details{margin-top:14px}summary{cursor:pointer;font-weight:700;color:#51465a}pre{white-space:pre-wrap;overflow:auto;max-height:440px;background:#211b29;color:#eee9f1;border-radius:12px;padding:14px;font:12px/1.5 Consolas,monospace}
        a{color:#167d91}.foot{font-size:12px;color:#77717d;margin-top:20px}
        @media(max-width:760px){.field,.field.wide{grid-column:1/-1}.wrap{padding:22px 12px 48px}.panel{padding:17px}.stats{grid-template-columns:1fr}.branch{grid-template-columns:1fr}.distance{white-space:normal}.coords{grid-template-columns:1fr 1fr}}
    </style>
</head>
<body>
<main class="wrap">
    <header>
        <div class="eyebrow">Raja Emas Indonesia · API Tools</div>
        <h1>Tester GET Branch API</h1>
        <p class="lead">Ambil daftar cabang, filter status, dan urutkan dari lokasi terdekat. Jarak dihitung API dalam kilometer.</p>
    </header>

    <section class="panel" aria-labelledby="requestTitle">
        <h2 id="requestTitle" style="margin:0 0 18px;font-size:19px">Pengaturan request</h2>
        <form id="requestForm">
            <div class="grid">
                <div class="field wide">
                    <label for="endpoint">URL endpoint</label>
                    <input class="input" id="endpoint" name="endpoint" value="branches.php" autocomplete="url" required>
                </div>
                <div class="field">
                    <label for="statusFilter">Filter status</label>
                    <select id="statusFilter" name="status">
                        <option value="">Semua status</option>
                        <option value="active">Active</option>
                        <option value="coming_soon">Coming soon</option>
                        <option value="temp_closed">Tutup sementara</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="field full">
                    <label for="token">Bearer token</label>
                    <input class="input" id="token" name="token" type="password" autocomplete="off" spellcheck="false" placeholder="Tempel token API untuk request ini" required>
                    <p class="help">Token hanya dipakai di request halaman ini dan tidak disimpan ke browser.</p>
                </div>
                <div class="field full">
                    <label>Koordinat pengguna (opsional)</label>
                    <div class="coords">
                        <input class="input" id="latitude" name="latitude" type="number" inputmode="decimal" step="any" min="-90" max="90" placeholder="Latitude, contoh -6.2000000">
                        <input class="input" id="longitude" name="longitude" type="number" inputmode="decimal" step="any" min="-180" max="180" placeholder="Longitude, contoh 106.8166667">
                    </div>
                    <p class="help">Isi keduanya atau kosongkan keduanya. Bisa juga pakai tombol lokasi perangkat.</p>
                </div>
                <div class="field full row">
                    <button class="btn alt" id="locationButton" type="button">Gunakan lokasi saya</button>
                    <button class="btn" id="submitButton" type="submit">Ambil data cabang</button>
                </div>
            </div>
        </form>
        <div id="requestStatus" class="status" role="status" aria-live="polite"></div>
    </section>

    <section id="resultsPanel" class="panel" hidden aria-labelledby="resultsTitle">
        <div class="results-head">
            <h2 id="resultsTitle">Hasil GET</h2>
            <div class="row">
                <input class="input" id="search" type="search" placeholder="Cari nama, alamat, kota..." aria-label="Cari cabang" style="min-width:250px">
                <button class="btn alt" id="clearButton" type="button">Bersihkan</button>
            </div>
        </div>
        <div class="stats" style="margin-top:16px">
            <div class="stat"><small>Total dari API</small><strong id="totalCount">0</strong></div>
            <div class="stat"><small>Ditampilkan</small><strong id="shownCount">0</strong></div>
            <div class="stat"><small>Cabang terdekat</small><strong id="nearestBranch">—</strong></div>
        </div>
        <div id="branchList" class="branch-list"></div>
        <details>
            <summary>Lihat JSON respons gabungan</summary>
            <pre id="jsonOutput"></pre>
        </details>
    </section>
    <p class="foot">Endpoint ini memakai autentikasi Bearer API. Tanpa koordinat, daftar mengikuti urutan API; dengan koordinat, cabang terdekat muncul lebih dulu. <a href="api-test.php">Buka dokumentasi API</a>.</p>
</main>

<script>
(() => {
    const $ = (id) => document.getElementById(id);
    const form = $('requestForm');
    const requestStatus = $('requestStatus');
    const resultsPanel = $('resultsPanel');
    const branchList = $('branchList');
    let allBranches = [];

    function showStatus(message, type = 'info') {
        requestStatus.textContent = message;
        requestStatus.className = `status show ${type}`;
    }

    function clearStatus() {
        requestStatus.textContent = '';
        requestStatus.className = 'status';
    }

    function makePill(text, className = '') {
        const pill = document.createElement('span');
        pill.className = `pill ${className}`.trim();
        pill.textContent = text;
        return pill;
    }

    function renderBranches() {
        const query = $('search').value.trim().toLocaleLowerCase('id-ID');
        const visible = allBranches.filter(branch => {
            if (!query) return true;
            return ['branch_number', 'title', 'address', 'city', 'province', 'district', 'phone']
                .some(key => String(branch[key] ?? '').toLocaleLowerCase('id-ID').includes(query));
        });

        branchList.replaceChildren();
        $('shownCount').textContent = String(visible.length);
        const closest = allBranches.find(branch => Number.isFinite(Number(branch.distance_km))
            && branch.distance_km !== null && branch.distance_km !== '');
        $('nearestBranch').textContent = closest
            ? `${closest.title || closest.branch_number} · ${Number(closest.distance_km).toFixed(2)} km`
            : 'Tidak tersedia';

        if (!visible.length) {
            const empty = document.createElement('div');
            empty.className = 'empty';
            empty.textContent = allBranches.length ? 'Tidak ada cabang yang cocok dengan pencarian.' : 'API tidak mengembalikan data cabang.';
            branchList.append(empty);
            return;
        }

        visible.forEach((branch, index) => {
            const card = document.createElement('article');
            card.className = 'branch';
            const main = document.createElement('div');
            const title = document.createElement('h3');
            title.textContent = `${branch.branch_number ?? '—'} · ${branch.title || 'Cabang tanpa nama'}`;
            main.append(title);

            const address = document.createElement('p');
            address.textContent = branch.address || 'Alamat belum tersedia';
            main.append(address);

            const region = [branch.district, branch.city, branch.province].filter(Boolean).join(', ');
            if (region) {
                const regionLine = document.createElement('p');
                regionLine.textContent = region;
                main.append(regionLine);
            }

            const phone = document.createElement('p');
            phone.textContent = `Telepon: ${branch.phone || '—'} · Jam: ${branch.hours || '—'}`;
            main.append(phone);

            const meta = document.createElement('div');
            meta.className = 'branch-meta';
            const statusLabels = {
                active: ['Active', 'active'],
                coming_soon: ['Coming soon', 'soon'],
                temp_closed: ['Tutup sementara', 'temp'],
                closed: ['Closed', 'closed']
            };
            const status = statusLabels[branch.status] || ['Status belum diketahui', ''];
            meta.append(makePill(status[0], status[1]));
            meta.append(makePill(`Pembukaan: ${branch.opening_date || '—'}`));
            if (branch.latitude !== null && branch.longitude !== null) {
                meta.append(makePill(`${branch.latitude}, ${branch.longitude}`));
            } else {
                meta.append(makePill('Koordinat belum tersedia'));
            }
            card.append(main, meta);

            const distance = document.createElement('div');
            distance.className = 'distance';
            distance.textContent = branch.distance_km !== null && branch.distance_km !== ''
                && Number.isFinite(Number(branch.distance_km))
                    ? `${Number(branch.distance_km).toFixed(2)} km`
                    : (index === 0 && $('latitude').value && $('longitude').value ? 'Jarak tidak tersedia' : '');
            card.append(distance);
            branchList.append(card);
        });
    }

    async function getPage(url, token) {
        const response = await fetch(url, {
            method: 'GET',
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
            cache: 'no-store',
            credentials: 'same-origin'
        });
        const text = await response.text();
        let json;
        try { json = JSON.parse(text); }
        catch { throw new Error(`Respons bukan JSON (HTTP ${response.status}). ${text.slice(0, 180)}`); }
        if (!response.ok || json.success !== true || !Array.isArray(json.data)) {
            throw new Error(json.message || `GET gagal dengan HTTP ${response.status}.`);
        }
        return json;
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        clearStatus();
        const token = $('token').value.trim();
        const endpoint = $('endpoint').value.trim() || 'branches.php';
        const latText = $('latitude').value.trim();
        const lngText = $('longitude').value.trim();
        if (!token) { showStatus('Bearer token wajib diisi.', 'error'); return; }
        if ((latText === '') !== (lngText === '')) {
            showStatus('Isi latitude dan longitude bersama-sama, atau kosongkan keduanya.', 'error');
            return;
        }
        if (latText !== '' && (!Number.isFinite(Number(latText)) || Number(latText) < -90 || Number(latText) > 90
            || !Number.isFinite(Number(lngText)) || Number(lngText) < -180 || Number(lngText) > 180)) {
            showStatus('Koordinat di luar rentang latitude -90..90 atau longitude -180..180.', 'error');
            return;
        }

        const button = $('submitButton');
        button.disabled = true;
        resultsPanel.hidden = true;
        allBranches = [];
        branchList.replaceChildren();
        try {
            const base = new URL(endpoint, location.href);
            const limit = 100;
            let offset = 0;
            let total = null;
            const gathered = [];
            while (total === null || offset < total) {
                const url = new URL(base);
                url.searchParams.set('limit', String(limit));
                url.searchParams.set('offset', String(offset));
                if (latText !== '') {
                    url.searchParams.set('latitude', latText);
                    url.searchParams.set('longitude', lngText);
                } else {
                    url.searchParams.delete('latitude');
                    url.searchParams.delete('longitude');
                }
                const status = $('statusFilter').value;
                if (status) url.searchParams.set('status', status);
                else url.searchParams.delete('status');

                showStatus(`Mengambil data cabang... (${gathered.length}${total !== null ? `/${total}` : ''})`, 'info');
                const json = await getPage(url, token);
                gathered.push(...json.data);
                total = Number(json.pagination?.total ?? gathered.length);
                if (!json.data.length) break;
                offset += json.data.length;
            }

            allBranches = gathered;
            $('totalCount').textContent = String(total ?? gathered.length);
            $('jsonOutput').textContent = JSON.stringify({ success: true, data: gathered, pagination: { total, count: gathered.length } }, null, 2);
            resultsPanel.hidden = false;
            renderBranches();
            showStatus(`GET berhasil: ${gathered.length} cabang diterima${latText ? ', diurutkan berdasarkan jarak terdekat' : ''}.`, 'ok');
        } catch (error) {
            showStatus(error.message || 'Request gagal. Periksa endpoint dan token.', 'error');
        } finally {
            button.disabled = false;
        }
    });

    $('search').addEventListener('input', renderBranches);
    $('clearButton').addEventListener('click', () => {
        $('search').value = '';
        renderBranches();
    });
    $('locationButton').addEventListener('click', () => {
        if (!navigator.geolocation) {
            showStatus('Browser ini tidak mendukung geolokasi.', 'error');
            return;
        }
        $('locationButton').disabled = true;
        showStatus('Meminta izin lokasi dari browser...', 'info');
        navigator.geolocation.getCurrentPosition(position => {
            $('latitude').value = position.coords.latitude.toFixed(7);
            $('longitude').value = position.coords.longitude.toFixed(7);
            $('locationButton').disabled = false;
            showStatus('Koordinat perangkat terisi. Klik “Ambil data cabang” untuk mencari yang terdekat.', 'ok');
        }, error => {
            $('locationButton').disabled = false;
            showStatus(error.message || 'Lokasi tidak bisa diambil. Isi koordinat secara manual.', 'error');
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
    });
})();
</script>
</body>
</html>
