<?php
include 'cek_login.php';

define('SAFE_OPENAI', true);         
include 'config_openai.php';         // berisi $OPENAI_API_KEY dari OpenRouter

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Koneksi Database
$host = "sql312.infinityfree.com";
$user = "if0_39315912";
$pass = "tvP6aiEVifFs";
$db   = "if0_39315912_Kelinci";
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
  die("❌ Gagal koneksi ke database: " . mysqli_connect_error());
}

// Ambil pesan dari form/chat
$message = strtolower(trim($_POST['message'] ?? ''));
if (!$message) {
  echo "❌ Tidak ada pesan dikirim.";
  exit;
}

// ==== Jawaban Otomatis Berdasarkan Pola ====
if (strpos($message, 'jantan') !== false) {
  $sql = "SELECT SUM(jantan) AS total FROM histori WHERE status = 'aktif'";
  $res = $conn->query($sql);
  $row = $res->fetch_assoc();
  echo "🐰 Jumlah jantan aktif saat ini: " . ($row['total'] ?? 0) . " ekor.";

} elseif (strpos($message, 'betina') !== false) {
  $sql = "SELECT SUM(betina) AS total FROM histori WHERE status = 'aktif'";
  $res = $conn->query($sql);
  $row = $res->fetch_assoc();
  echo "🐰 Jumlah betina aktif saat ini: " . ($row['total'] ?? 0) . " ekor.";

} elseif (strpos($message, 'anak') !== false) {
  $sql = "SELECT SUM(jumlah_anak) AS total FROM histori WHERE status = 'aktif'";
  $res = $conn->query($sql);
  $row = $res->fetch_assoc();
  echo "🐰 Total anak kelinci saat ini: " . ($row['total'] ?? 0) . " ekor.";

} elseif (strpos($message, 'sakit') !== false || strpos($message, 'riwayat') !== false) {
  $sql = "SELECT kandang, riwayat_kesehatan FROM histori WHERE status = 'aktif' AND riwayat_kesehatan IS NOT NULL AND riwayat_kesehatan != ''";
  $res = $conn->query($sql);
  if ($res->num_rows > 0) {
    $list = [];
    while ($row = $res->fetch_assoc()) {
      $list[] = $row['kandang'] . " (" . $row['riwayat_kesehatan'] . ")";
    }
    echo "⚠️ Ada " . count($list) . " kandang yang sedang sakit:\n- " . implode("\n- ", $list);
  } else {
    echo "✅ Saat ini semua kandang dalam kondisi sehat.";
  }

} elseif (strpos($message, 'hamil') !== false) {
  $sql = "SELECT kandang, tgl_kawin FROM histori WHERE status = 'aktif' AND tgl_kawin != '0000-00-00' AND jumlah_anak = 0";
  $res = $conn->query($sql);
  $list = [];
  while ($row = $res->fetch_assoc()) {
    $list[] = $row['kandang'] . " (kawin: " . $row['tgl_kawin'] . ")";
  }
  echo count($list) > 0
    ? "🤰 Ada " . count($list) . " kandang kemungkinan sedang hamil:\n- " . implode("\n- ", $list)
    : "✅ Tidak ada kandang hamil saat ini.";

} elseif (strpos($message, 'melahirkan') !== false && strpos($message, 'mau') !== false) {
  $today = date('Y-m-d');
  $sql = "SELECT kandang, tgl_kawin FROM histori WHERE status = 'aktif' AND tgl_kawin != '0000-00-00' AND jumlah_anak = 0";
  $res = $conn->query($sql);
  $list = [];
  while ($row = $res->fetch_assoc()) {
    $days = (strtotime($today) - strtotime($row['tgl_kawin'])) / (60 * 60 * 24);
    if ($days >= 27) {
      $list[] = $row['kandang'] . " (kawin: " . $row['tgl_kawin'] . ", hamil " . floor($days) . " hari)";
    }
  }
  echo count($list) > 0
    ? "🍼 Ada " . count($list) . " kandang yang kemungkinan akan segera melahirkan:\n- " . implode("\n- ", $list)
    : "⏳ Belum ada kandang yang akan melahirkan.";

} elseif (strpos($message, 'melahirkan') !== false) {
  $sql = "SELECT kandang, jumlah_anak, tgl_lahir_anak FROM histori WHERE status = 'aktif' AND jumlah_anak > 0";
  $res = $conn->query($sql);
  $list = [];
  while ($row = $res->fetch_assoc()) {
    $list[] = $row['kandang'] . " (" . $row['jumlah_anak'] . " anak, lahir: " . $row['tgl_lahir_anak'] . ")";
  }
  echo count($list) > 0
    ? "👶 Ada " . count($list) . " kandang yang sudah melahirkan:\n- " . implode("\n- ", $list)
    : "❌ Belum ada kelahiran yang tercatat.";

} elseif (preg_match('/ka\.\d+|kb\.\d+|kc\.\d+/i', $message, $match)) {
  $kandang = strtoupper($match[0]);
  $stmt = $conn->prepare("SELECT jantan, betina, jumlah_anak, riwayat_kesehatan FROM histori WHERE status = 'aktif' AND kandang = ? ORDER BY waktu_input DESC LIMIT 1");
  $stmt->bind_param("s", $kandang);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($data = $result->fetch_assoc()) {
    $j = $data['jantan'];
    $b = $data['betina'];
    $a = $data['jumlah_anak'];
    $r = $data['riwayat_kesehatan'] ?: 'sehat';
    echo "📦 Kandang $kandang: $j jantan, $b betina, $a anak, riwayat: $r.";
  } else {
    echo "⚠️ Data kandang $kandang tidak ditemukan.";
  }

// ==== Kirim ke OpenRouter ====
} else {
  $chat = [
    "model" => "openai/gpt-3.5-turbo",
    "messages" => [
      ["role" => "system", "content" => "Kamu adalah asisten pribadi bernama Asisten, ramah, lucu, dan membantu Boss (pengguna) dalam hal apapun."],
      ["role" => "user", "content" => $message]
    ]
  ];

  $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $OPENAI_API_KEY",
    "HTTP-Referer: https://ninerabbit.com",  // atau isi domain Boss
    "X-Title: Ninerabbit Assistant"
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($chat));
  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($status === 200) {
    $data = json_decode($response, true);
    $reply = $data['choices'][0]['message']['content'] ?? "🤖 Maaf, tidak ada respon.";
    echo nl2br(htmlspecialchars($reply));
  } else {
    echo "❌ Gagal menghubungi Asisten. Status: $status";
  }
}