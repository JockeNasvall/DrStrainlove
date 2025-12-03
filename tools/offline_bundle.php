<?php
// Helper that exports the MySQL strains table to CSV, converts it to SQLite,
// and packages an offline search-only frontend for download.

require_once __DIR__ . '/../db.php';

function render_intro($error = null) {
    http_response_code(200);
    echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"UTF-8\">\n<title>Offline bundle builder</title>\n<style>body{font-family:Arial,Helvetica,sans-serif;margin:2rem;max-width:880px;}form{margin-top:1rem;padding:1rem;border:1px solid #ccc;background:#f9f9f9;}button{padding:0.6rem 1.4rem;font-size:1rem;}code{background:#f1f1f1;padding:2px 4px;border-radius:3px;}\n.alert{border:1px solid #d33;background:#ffecec;color:#900;padding:0.75rem;margin-bottom:1rem;}\n</style>\n</head><body>";
    echo "<h1>Offline search bundle</h1>";
    echo "<p>This helper exports the <code>strains</code> table to CSV, converts it to a bundled SQLite database, and builds a small search-only frontend that you can download as a ZIP file.</p>";
    echo "<ol><li>Click <strong>Build &amp; download</strong>.</li><li>Your browser will ask where to save <code>strainlove_offline_bundle.zip</code>.</li><li>Unzip it and run <code>php -S localhost:8000</code> inside the extracted folder to use the offline search page.</li></ol>";
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

if (!class_exists('ZipArchive')) {
    render_intro('ZipArchive extension is not available on this server.');
    exit;
}

if (!class_exists('SQLite3')) {
    render_intro('SQLite3 extension is required to build the offline database.');
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
$sqlitePath = $offlineDir . '/strains.sqlite';
$indexPath = $offlineDir . '/index.php';
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

// Build SQLite database
$sqlite = new SQLite3($sqlitePath);
$sqlite->exec('PRAGMA journal_mode = OFF');
$sqlite->exec('PRAGMA synchronous = OFF');
$sqlite->exec('CREATE TABLE strains (Strain INTEGER PRIMARY KEY, Genotype TEXT, Recipient INTEGER, Donor INTEGER, Comment TEXT, Signature TEXT, Created TEXT)');
$sqlite->exec('CREATE INDEX idx_strains_recipient ON strains (Recipient)');
$sqlite->exec('CREATE INDEX idx_strains_donor ON strains (Donor)');
$sqlite->exec('BEGIN');
$insert = $sqlite->prepare('INSERT INTO strains (Strain, Genotype, Recipient, Donor, Comment, Signature, Created) VALUES (:Strain, :Genotype, :Recipient, :Donor, :Comment, :Signature, :Created)');
foreach ($rows as $row) {
    $insert->bindValue(':Strain', (int)$row['Strain'], SQLITE3_INTEGER);
    $insert->bindValue(':Genotype', $row['Genotype'], SQLITE3_TEXT);
    $insert->bindValue(':Recipient', $row['Recipient'] === null ? null : (int)$row['Recipient'], SQLITE3_INTEGER);
    $insert->bindValue(':Donor', $row['Donor'] === null ? null : (int)$row['Donor'], SQLITE3_INTEGER);
    $insert->bindValue(':Comment', $row['Comment'], SQLITE3_TEXT);
    $insert->bindValue(':Signature', $row['Signature'], SQLITE3_TEXT);
    $insert->bindValue(':Created', $row['Created'], SQLITE3_TEXT);
    $insert->execute();
}
$sqlite->exec('COMMIT');
$sqlite->close();

// Offline index file (search-only frontend)
$offlineIndex = <<<'PHPINDEX'
<?php
// Offline search-only frontend using the exported SQLite database.
$dsn = 'sqlite:' . __DIR__ . '/strains.sqlite';
$pdo = new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function read_value($key, $default = '') {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

$limit = (int)(read_value('limit', 100));
if ($limit <= 0 || $limit > 1000) {
    $limit = 100;
}
$page = (int)(read_value('page', 1));
if ($page <= 0) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

$searchgenotype = isset($_GET['check']) ? (isset($_GET['check']['genotype']) ? 1 : 0) : 1; // default on
$searchcomment = isset($_GET['check']['comment']) ? 1 : 0;
$includeFields = [];
if ($searchgenotype) $includeFields[] = 'Genotype';
if ($searchcomment) $includeFields[] = 'Comment';
if (!$includeFields) {
    $includeFields = ['Genotype'];
}

$terms = [];
for ($i = 1; $i <= 4; $i++) {
    $val = read_value('term' . $i);
    if ($val !== '') {
        $terms[] = $val;
    }
}

$notTerms = [];
for ($i = 1; $i <= 4; $i++) {
    $val = read_value('notterm' . $i);
    if ($val !== '') {
        $notTerms[] = $val;
    }
}

$minNum = read_value('minNum');
$maxNum = read_value('maxNum');
$signature = read_value('sign1');

$conditions = [];
$params = [];

foreach ($terms as $idx => $term) {
    $likes = [];
    foreach ($includeFields as $field) {
        $param = ':term' . $idx . '_' . $field;
        $likes[] = "$field LIKE $param";
        $params[$param] = '%' . $term . '%';
    }
    $conditions[] = '(' . implode(' OR ', $likes) . ')';
}

foreach ($notTerms as $idx => $term) {
    $likes = [];
    foreach ($includeFields as $field) {
        $param = ':notterm' . $idx . '_' . $field;
        $likes[] = "$field LIKE $param";
        $params[$param] = '%' . $term . '%';
    }
    $conditions[] = 'NOT (' . implode(' OR ', $likes) . ')';
}

if ($minNum !== '' && ctype_digit($minNum)) {
    $conditions[] = 'Strain >= :minNum';
    $params[':minNum'] = (int)$minNum;
}
if ($maxNum !== '' && ctype_digit($maxNum)) {
    $conditions[] = 'Strain <= :maxNum';
    $params[':maxNum'] = (int)$maxNum;
}
if ($signature !== '') {
    $conditions[] = 'Signature LIKE :signature';
    $params[':signature'] = '%' . $signature . '%';
}

$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$countSql = "SELECT COUNT(*) FROM strains $where";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $name => $value) {
    $countStmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT Strain, Genotype, Recipient, Donor, Comment, Signature, Created FROM strains $where ORDER BY Strain ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

?>
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
</style>
</head>
<body>
<h1>Strainlove offline search</h1>
<div class="container">
<form method="get">
    <h3>Search filters</h3>
    <label><input type="checkbox" name="check[genotype]" <?php echo $searchgenotype ? 'checked' : ''; ?>>Genotype</label>
    <label><input type="checkbox" name="check[comment]" <?php echo $searchcomment ? 'checked' : ''; ?>>Comment</label>

    <p><strong>Include keywords</strong></p>
    <?php for ($i = 1; $i <= 4; $i++): ?>
        <input type="text" name="term<?php echo $i; ?>" value="<?php echo h(read_value('term' . $i)); ?>">
    <?php endfor; ?>

    <p><strong>Exclude keywords</strong></p>
    <?php for ($i = 1; $i <= 4; $i++): ?>
        <input type="text" name="notterm<?php echo $i; ?>" value="<?php echo h(read_value('notterm' . $i)); ?>">
    <?php endfor; ?>

    <label>Strain number between</label>
    <div style="display:flex;gap:0.5rem;">
        <input type="text" name="minNum" value="<?php echo h($minNum); ?>" placeholder="Min">
        <input type="text" name="maxNum" value="<?php echo h($maxNum); ?>" placeholder="Max">
    </div>

    <label>Signature
        <input type="text" name="sign1" value="<?php echo h($signature); ?>">
    </label>
    <label>Limit results
        <input type="text" name="limit" value="<?php echo h($limit); ?>">
    </label>

    <input type="hidden" name="page" value="1">
    <div style="margin-top:0.75rem; display:flex; gap:0.5rem;">
        <input type="submit" value="Search">
        <a href="index.php" style="display:inline-block;padding:0.55rem 1rem;border:1px solid #ccc;background:#eee;text-decoration:none;">Reset</a>
    </div>
</form>

<div style="flex:1;">
    <p><strong><?php echo count($results); ?></strong> results shown (<?php echo $total; ?> total).</p>
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
        <tbody>
            <?php if (!$results): ?>
                <tr><td colspan="7">No results found.</td></tr>
            <?php else: ?>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?php echo h($row['Strain']); ?></td>
                        <td><?php echo nl2br(h($row['Genotype'])); ?></td>
                        <td><?php echo h($row['Recipient']); ?></td>
                        <td><?php echo h($row['Donor']); ?></td>
                        <td><?php echo nl2br(h($row['Comment'])); ?></td>
                        <td><?php echo h($row['Signature']); ?></td>
                        <td><?php echo h($row['Created']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ($total > $limit): ?>
        <div class="pager">
            <?php if ($page > 1): ?>
                <a href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">&laquo; Prev</a>
            <?php endif; ?>
            <span>Page <?php echo $page; ?> of <?php echo max(1, ceil($total / $limit)); ?></span>
            <?php if ($offset + $limit < $total): ?>
                <a href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</div>
</body>
</html>
PHPINDEX;

file_put_contents($indexPath, $offlineIndex);

$noteText = "Strainlove offline bundle\n\nContents:\n- index.php: search-only frontend using PDO SQLite\n- strains.sqlite: exported data\n- strains.csv: raw export of the same rows\n\nUsage:\n1) Install PHP locally (no MySQL needed).\n2) In this folder, run: php -S localhost:8000\n3) Open http://localhost:8000 in your browser.\n\nSearch supports keyword include/exclude, genotype/comment toggles, strain number range, signature filter, limits, and pagination.\n\nData exported on: " . date('c') . "\n";
file_put_contents($notePath, $noteText);

$zipPath = tempnam(sys_get_temp_dir(), 'strainlove_bundle_');
$zipArchive = new ZipArchive();
$zipArchive->open($zipPath, ZipArchive::OVERWRITE);
$zipArchive->addFile($csvPath, 'offline/strains.csv');
$zipArchive->addFile($sqlitePath, 'offline/strains.sqlite');
$zipArchive->addFile($indexPath, 'offline/index.php');
$zipArchive->addFile($notePath, 'offline/README.txt');
$zipArchive->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="strainlove_offline_bundle.zip"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);

clean_up_temp($bundleRoot);
unlink($zipPath);
exit;
