<?php
// Helper that exports the MySQL strains table to CSV/JSON and packages a
// static, search-only HTML frontend for download.

require_once __DIR__ . '/../db.php';

function render_intro($error = null) {
    http_response_code(200);
    echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"UTF-8\">\n<title>Offline bundle builder</title>\n<style>body{font-family:Arial,Helvetica,sans-serif;margin:2rem;max-width:880px;}form{margin-top:1rem;padding:1rem;border:1px solid #ccc;background:#f9f9f9;}button{padding:0.6rem 1.4rem;font-size:1rem;}code{background:#f1f1f1;padding:2px 4px;border-radius:3px;}\n.alert{border:1px solid #d33;background:#ffecec;color:#900;padding:0.75rem;margin-bottom:1rem;}\n</style>\n</head><body>";
    echo "<h1>Offline search bundle</h1>";
    echo "<p>This helper exports the <code>strains</code> table to CSV and JSON, builds a static search-only HTML page (no PHP or SQLite required for offline use), and packages everything as a <code>.tar.gz</code> download.</p>";
    echo "<ol><li>Click <strong>Build &amp; download</strong>.</li><li>Your browser will ask where to save <code>strainlove_offline_bundle.tar.gz</code>.</li><li>Extract it and open <code>offline/index.html</code> directly in your browser to search offline.</li></ol>";
    if ($error) {
        echo '<div class="alert">' . htmlspecialchars($error) . '</div>';
    }
    echo '<form method="POST"><p>This will read the current MySQL data and generate a downloadable ZIP.</p><button type="submit">Build &amp; download</button></form>';
    echo "</body></html>";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render_intro();
    exit;
}

if (!class_exists('PharData')) {
    render_intro('Phar extension is required to build the downloadable archive.');
    exit;
}

set_time_limit(0);

$bundleRoot = sys_get_temp_dir() . '/strainlove_bundle_' . bin2hex(random_bytes(4));
$offlineDir = $bundleRoot . '/offline';
if (!is_dir($offlineDir) && !mkdir($offlineDir, 0700, true)) {
    render_intro('Could not create temporary working directory.');
    exit;
}

$csvPath = $offlineDir . '/strains.csv';
$jsonPath = $offlineDir . '/strains.json';
$jsPath = $offlineDir . '/strains.js';
$indexPath = $offlineDir . '/index.html';
$notePath = $offlineDir . '/README.txt';

function clean_up_temp($path) {
    if (!is_dir($path)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($path);
}

// Fetch data from MySQL and write CSV

// Fetch data from MySQL and write CSV + JSON
$csvHandle = fopen($csvPath, 'w');
fputcsv($csvHandle, ['Strain', 'Genotype', 'Recipient', 'Donor', 'Comment', 'Signature', 'Created']);

$stmt = $dbh->query('SELECT Strain, Genotype, Recipient, Donor, Comment, Signature, Created FROM strains ORDER BY Strain ASC');
$rows = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = $row;
    fputcsv($csvHandle, [
        $row['Strain'],
        $row['Genotype'],
        $row['Recipient'],
        $row['Donor'],
        $row['Comment'],
        $row['Signature'],
        $row['Created'],
    ]);
}
fclose($csvHandle);
file_put_contents($jsonPath, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents($jsPath, 'window.STRAINS_DATA = ' . json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';');

// Offline index file (search-only frontend, static HTML + JS)
$offlineIndex = <<<'HTMLINDEX'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Strainlove offline search</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;margin:1.5rem;}
        .container{display:flex;gap:2rem;align-items:flex-start;}
        form{min-width:320px;max-width:420px;padding:1rem;border:1px solid #ccc;background:#f9f9f9;}
        label{display:block;margin-bottom:0.35rem;}
        input[type=text]{width:100%;padding:0.35rem;margin:0.15rem 0;}
        input[type=checkbox]{margin-right:0.35rem;}
        button,input[type=submit]{padding:0.5rem 1rem;}
        table{border-collapse:collapse;width:100%;margin-top:1rem;}
        th,td{border:1px solid #ddd;padding:0.5rem;vertical-align:top;}
        th{background:#f0f0f0;}
        .pager{margin-top:0.75rem;display:flex;gap:0.5rem;align-items:center;}
        .notice{background:#eef6ff;border:1px solid #b5d2ff;padding:0.65rem;margin-bottom:0.75rem;}
        mark{background:#fff7a8;padding:0 2px;}
    </style>
</head>
<body>
<h1>Strainlove offline search</h1>
<div class="notice">Search runs fully in your browser using bundled data (<code>strains.js</code>/<code>strains.json</code>). No PHP or database server is needed.</div>
<div class="container">
<form id="search-form">
    <h3>Search filters</h3>
    <label><input type="checkbox" name="check_genotype" checked>Genotype</label>
    <label><input type="checkbox" name="check_comment">Comment</label>

    <p><strong>Include keywords</strong></p>
    <div id="include-terms"></div>

    <p><strong>Exclude keywords</strong></p>
    <div id="exclude-terms"></div>

    <label>Strain number between</label>
    <div style="display:flex;gap:0.5rem;">
        <input type="text" name="minNum" placeholder="Min">
        <input type="text" name="maxNum" placeholder="Max">
    </div>

    <label>Signature
        <input type="text" name="sign1">
    </label>
    <label>Limit results
        <input type="text" name="limit" value="100">
    </label>

    <input type="hidden" name="page" value="1">
    <div style="margin-top:0.75rem; display:flex; gap:0.5rem;">
        <input type="submit" value="Search">
        <button type="button" id="reset-btn">Reset</button>
    </div>
</form>

<div style="flex:1;" id="results-pane">
    <p id="summary">Loading data...</p>
    <table>
        <thead>
            <tr>
                <th>Strain</th>
                <th>Genotype</th>
                <th>Recipient</th>
                <th>Donor</th>
                <th>Comment</th>
                <th>Signature</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody id="results-body">
            <tr><td colspan="7">Loading...</td></tr>
        </tbody>
    </table>
    <div class="pager" id="pager" style="display:none;"></div>
</div>
</div>

<script src="strains.js"></script>
<script>
(function() {
    const includeContainer = document.getElementById('include-terms');
    const excludeContainer = document.getElementById('exclude-terms');
    for (let i = 1; i <= 4; i++) {
        includeContainer.insertAdjacentHTML('beforeend', `<input type="text" name="term${i}" placeholder="Include term ${i}">`);
        excludeContainer.insertAdjacentHTML('beforeend', `<input type="text" name="notterm${i}" placeholder="Exclude term ${i}">`);
    }

    const form = document.getElementById('search-form');
    const resetBtn = document.getElementById('reset-btn');
    const summaryEl = document.getElementById('summary');
    const tbody = document.getElementById('results-body');
    const pager = document.getElementById('pager');
    let data = Array.isArray(window.STRAINS_DATA) ? window.STRAINS_DATA : [];

    function loadData() {
        if (data.length) {
            summaryEl.textContent = `${data.length} rows loaded.`;
            render();
            return;
        }

        fetch('strains.json', { cache: 'no-store' })
            .then(resp => resp.json())
            .then(rows => {
                data = rows;
                summaryEl.textContent = `${data.length} rows loaded.`;
                render();
            })
            .catch(err => {
                summaryEl.textContent = 'Failed to load strains.json: ' + err;
                tbody.innerHTML = '<tr><td colspan="7">Unable to load data.</td></tr>';
            });
    }

    function normalize(val) {
        return (val || '').toString().toLowerCase();
    }

    function escapeHtml(text) {
        const safe = (text === null || text === undefined) ? '' : String(text);
        return safe
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function highlight(text, terms) {
        if (!terms.length || !text) return escapeHtml(text).replace(/\n/g, '<br>');
        const escaped = escapeHtml(text);
        try {
            const pattern = terms.map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|');
            const regex = new RegExp(`(${pattern})`, 'gi');
            return escaped.replace(regex, '<mark>$1</mark>').replace(/\n/g, '<br>');
        } catch (e) {
            return escaped.replace(/\n/g, '<br>');
        }
    }

    function matchesTerms(text, terms) {
        const hay = normalize(text);
        return terms.every(term => hay.includes(term));
    }

    function matchesExclude(text, terms) {
        const hay = normalize(text);
        return !terms.some(term => hay.includes(term));
    }

    function parseFilters() {
        const formData = new FormData(form);
        const includeTerms = [];
        const excludeTerms = [];
        for (let i = 1; i <= 4; i++) {
            const inc = normalize(formData.get(`term${i}`));
            const exc = normalize(formData.get(`notterm${i}`));
            if (inc) includeTerms.push(inc);
            if (exc) excludeTerms.push(exc);
        }

        const searchGenotype = formData.get('check_genotype') !== null;
        const searchComment = formData.get('check_comment') !== null;
        const fields = [];
        if (searchGenotype) fields.push('Genotype');
        if (searchComment) fields.push('Comment');
        if (fields.length === 0) fields.push('Genotype');

        const minNum = parseInt(formData.get('minNum'), 10);
        const maxNum = parseInt(formData.get('maxNum'), 10);
        const signature = normalize(formData.get('sign1'));
        let limit = parseInt(formData.get('limit'), 10);
        if (!Number.isFinite(limit) || limit <= 0 || limit > 1000) limit = 100;
        let page = parseInt(formData.get('page'), 10);
        if (!Number.isFinite(page) || page <= 0) page = 1;

        return { includeTerms, excludeTerms, fields, minNum, maxNum, signature, limit, page };
    }

    function rowMatches(row, filters) {
        const targets = filters.fields.map(f => normalize(row[f] || ''));

        if (filters.includeTerms.length && !filters.includeTerms.every(term => targets.some(t => t.includes(term)))) {
            return false;
        }
        if (filters.excludeTerms.length && filters.excludeTerms.some(term => targets.some(t => t.includes(term)))) {
            return false;
        }

        const strainNum = parseInt(row.Strain, 10);
        if (Number.isFinite(filters.minNum) && strainNum < filters.minNum) return false;
        if (Number.isFinite(filters.maxNum) && strainNum > filters.maxNum) return false;

        if (filters.signature && !normalize(row.Signature).includes(filters.signature)) return false;

        return true;
    }

    function render(pageReset=false) {
        if (!data.length) {
            tbody.innerHTML = '<tr><td colspan="7">No data loaded.</td></tr>';
            pager.style.display = 'none';
            return;
        }

        const filters = parseFilters();
        const filtered = data.filter(row => rowMatches(row, filters));

        const total = filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / filters.limit));
        const page = pageReset ? 1 : Math.min(filters.page, totalPages);

        const offset = (page - 1) * filters.limit;
        const pageRows = filtered.slice(offset, offset + filters.limit);

        const includeHighlight = filters.includeTerms;

        summaryEl.textContent = total
            ? `Showing ${offset + 1}-${Math.min(offset + filters.limit, total)} of ${total} matches.`
            : 'No results found.';

        tbody.innerHTML = pageRows.map(r => `
            <tr>
                <td>${escapeHtml(r.Strain)}</td>
                <td>${highlight(r.Genotype, includeHighlight)}</td>
                <td>${escapeHtml(r.Recipient ?? '')}</td>
                <td>${escapeHtml(r.Donor ?? '')}</td>
                <td>${highlight(r.Comment, includeHighlight)}</td>
                <td>${escapeHtml(r.Signature || '')}</td>
                <td>${escapeHtml(r.Created || '')}</td>
            </tr>
        `).join('') || '<tr><td colspan="7">No results found.</td></tr>';

        if (total > filters.limit) {
            pager.style.display = 'flex';
            pager.innerHTML = '';
            if (page > 1) {
                const prev = document.createElement('a');
                prev.href = '#';
                prev.textContent = '« Prev';
                prev.onclick = (e) => { e.preventDefault(); form.querySelector('input[name="page"]').value = page - 1; render(); };
                pager.appendChild(prev);
            }
            const span = document.createElement('span');
            span.textContent = `Page ${page} of ${totalPages}`;
            pager.appendChild(span);
            if (offset + filters.limit < total) {
                const next = document.createElement('a');
                next.href = '#';
                next.textContent = 'Next »';
                next.onclick = (e) => { e.preventDefault(); form.querySelector('input[name="page"]').value = page + 1; render(); };
                pager.appendChild(next);
            }
        } else {
            pager.style.display = 'none';
        }
    }

    form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        form.querySelector('input[name="page"]').value = 1;
        render(true);
    });

    resetBtn.addEventListener('click', function() {
        form.reset();
        form.querySelector('input[name="page"]').value = 1;
        render(true);
    });

    loadData();
})();
</script>
</body>
</html>
HTMLINDEX;

file_put_contents($indexPath, $offlineIndex);

$noteText = "Strainlove offline bundle\n\nContents:\n- index.html: search-only frontend (static HTML + JavaScript)\n- strains.json: exported data for the offline search UI\n- strains.csv: raw export of the same rows\n\nUsage:\n1) Extract the archive.\n2) Open offline/index.html in your browser (no PHP, SQLite, or MySQL needed).\n\nSearch supports keyword include/exclude, genotype/comment toggles, strain number range, signature filter, limits, and pagination.\n\nData exported on: " . date('c') . "\n";
file_put_contents($notePath, $noteText);

$tarPath = $bundleRoot . '/strainlove_offline_bundle.tar';
$phar = new PharData($tarPath);
$phar->addFile($csvPath, 'offline/strains.csv');
$phar->addFile($jsonPath, 'offline/strains.json');
$phar->addFile($jsPath, 'offline/strains.js');
$phar->addFile($indexPath, 'offline/index.html');
$phar->addFile($notePath, 'offline/README.txt');
$phar->compress(Phar::GZ);
$gzPath = $tarPath . '.gz';
unset($phar);
@unlink($tarPath);

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="strainlove_offline_bundle.tar.gz"');
header('Content-Length: ' . filesize($gzPath));
readfile($gzPath);

clean_up_temp($bundleRoot);
unlink($gzPath);
exit;
