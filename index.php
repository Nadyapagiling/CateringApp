<?php
session_start();
require __DIR__ . '/koneksi.php';

// ---------- CSRF token ----------
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- Proses aksi (semua lewat POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Token tidak valid.');
    }

    $aksi = $_POST['aksi'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_menu'] ?? '');
    $kat  = trim($_POST['kategori'] ?? '');
    $harga = (int)($_POST['harga'] ?? 0);
    $stok  = (int)($_POST['stok'] ?? 0);

    if ($aksi === 'tambah' || $aksi === 'update') {
        if ($nama === '' || $kat === '' || $harga <= 0 || $stok < 0) {
            header('Location: index.php?pesan=invalid'); exit;
        }
    }

    if ($aksi === 'tambah') {
        $st = $conn->prepare('INSERT INTO menu (nama_menu, kategori, harga, stok) VALUES (?,?,?,?)');
        $st->bind_param('ssii', $nama, $kat, $harga, $stok);
        $st->execute();
        header('Location: index.php?pesan=tambah'); exit;
    }
    if ($aksi === 'update' && $id > 0) {
        $st = $conn->prepare('UPDATE menu SET nama_menu=?, kategori=?, harga=?, stok=? WHERE id=?');
        $st->bind_param('ssiii', $nama, $kat, $harga, $stok, $id);
        $st->execute();
        header('Location: index.php?pesan=update'); exit;
    }
    if ($aksi === 'hapus' && $id > 0) {
        $st = $conn->prepare('DELETE FROM menu WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        header('Location: index.php?pesan=hapus'); exit;
    }
    header('Location: index.php'); exit;
}

// ---------- Mode edit ----------
$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $st = $conn->prepare('SELECT * FROM menu WHERE id=?');
    $st->bind_param('i', $eid);
    $st->execute();
    $edit = $st->get_result()->fetch_assoc();
}

// ---------- Daftar + pencarian ----------
$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';
$st = $conn->prepare('SELECT * FROM menu WHERE nama_menu LIKE ? OR kategori LIKE ? ORDER BY id DESC');
$st->bind_param('ss', $like, $like);
$st->execute();
$rows = $st->get_result();

$pesan = [
    'tambah'  => 'Menu berhasil ditambahkan.',
    'update'  => 'Menu berhasil diperbarui.',
    'hapus'   => 'Menu berhasil dihapus.',
    'invalid' => 'Data tidak valid, periksa kembali isian.',
];
$info = $pesan[$_GET['pesan'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Catering App</title>
<style>
  body{font-family:system-ui,sans-serif;max-width:900px;margin:30px auto;padding:0 16px;color:#222}
  h1{margin-bottom:4px} .sub{color:#666;margin-top:0}
  form.row{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}
  input{padding:8px;border:1px solid #ccc;border-radius:6px}
  button,.btn{padding:8px 14px;border:0;border-radius:6px;background:#2e7d32;color:#fff;cursor:pointer;text-decoration:none;font-size:14px}
  .btn-edit{background:#f9a825;color:#000} .btn-del{background:#c62828}
  table{width:100%;border-collapse:collapse;margin-top:12px}
  th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
  th{background:#f1f8e9} .info{padding:10px;background:#e8f5e9;border-radius:6px}
  form.inline{display:inline}
</style>
</head>
<body>
<h1>Catering App</h1>
<p class="sub">Kelola menu catering (Versi 2.1 - CRUD via Jenkins)</p>

<?php if ($info): ?><div class="info"><?= e($info) ?></div><?php endif; ?>

<h3><?= $edit ? 'Edit Menu' : 'Tambah Menu' ?></h3>
<form method="post" class="row">
  <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
  <input type="hidden" name="aksi" value="<?= $edit ? 'update' : 'tambah' ?>">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
  <input name="nama_menu" placeholder="Nama menu" required maxlength="100" value="<?= e($edit['nama_menu'] ?? '') ?>">
  <input name="kategori" placeholder="Kategori" required maxlength="50" value="<?= e($edit['kategori'] ?? '') ?>">
  <input name="harga" type="number" min="1" placeholder="Harga" required value="<?= e($edit['harga'] ?? '') ?>">
  <input name="stok" type="number" min="0" placeholder="Stok" required value="<?= e($edit['stok'] ?? '') ?>">
  <button type="submit"><?= $edit ? 'Simpan' : 'Tambah' ?></button>
  <?php if ($edit): ?><a class="btn btn-del" href="index.php">Batal</a><?php endif; ?>
</form>

<form method="get" class="row">
  <input name="q" placeholder="Cari nama atau kategori..." value="<?= e($q) ?>">
  <button type="submit">Cari</button>
</form>

<table>
  <tr><th>#</th><th>Nama Menu</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr>
  <?php $no = 1; while ($r = $rows->fetch_assoc()): ?>
  <tr>
    <td><?= $no++ ?></td>
    <td><?= e($r['nama_menu']) ?></td>
    <td><?= e($r['kategori']) ?></td>
    <td>Rp <?= number_format((int)$r['harga'], 0, ',', '.') ?></td>
    <td><?= (int)$r['stok'] ?></td>
    <td>
      <a class="btn btn-edit" href="index.php?edit=<?= (int)$r['id'] ?>">Edit</a>
      <form method="post" class="inline" onsubmit="return confirm('Hapus menu ini?')">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="aksi" value="hapus">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button type="submit" class="btn-del">Hapus</button>
      </form>
    </td>
  </tr>
  <?php endwhile; ?>
  <?php if ($no === 1): ?><tr><td colspan="6">Belum ada data.</td></tr><?php endif; ?>
</table>
</body>
</html>
