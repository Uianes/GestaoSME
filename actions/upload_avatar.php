<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
require_login();
$user = $_SESSION['user'] ?? null;
$matricula = (int)($user['matricula'] ?? 0);
if ($matricula <= 0) {
  header('Location: ../app.php?page=config&err=1');
  exit;
}
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
  header('Location: ../app.php?page=config&err=2');
  exit;
}
$file = $_FILES['avatar'];
if ($file['size'] > 3 * 1024 * 1024) {
  header('Location: ../app.php?page=config&err=3');
  exit;
}
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($ext, $allowed, true)) {
  header('Location: ../app.php?page=config&err=4');
  exit;
}
$dir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($dir)) {
  mkdir($dir, 0775, true);
}
$filename = 'm' . $matricula . '_' . time() . '.' . $ext;
$dest = $dir . $filename;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
  header('Location: ../app.php?page=config&err=5');
  exit;
}
$conn = db();
$stmt = $conn->prepare("UPDATE usuarios SET avatar = ? WHERE matricula = ?");
$stmt->bind_param("si", $filename, $matricula);
$stmt->execute();
$stmt->close();
$_SESSION['user']['avatar'] = $filename;
header('Location: ../app.php?page=config&ok=1');
exit;