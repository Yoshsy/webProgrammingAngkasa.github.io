<?php
session_start();

include '../connect/conn.php';
if (!isset($_SESSION['email'])) {
    header("Location: ../index.php");
}
$email = $_SESSION['email'];
$queryHistory = [
    $dbConnect->query("SELECT fk_users AS historyVisitor FROM tb_pengunjung visitors INNER JOIN users ON visitors.fk_users = users.userId WHERE users.email = '$email'"),
    $dbConnect->query("SELECT fk_users AS historyOrder FROM tb_pemesanan orders INNER JOIN users ON orders.fk_users = users.userId WHERE users.email = '$email'")
];

if ($queryHistory[0] && $queryHistory[1] -> num_rows === 0) {
    header('Location: ../index.php');
}

$arrHistoryOrder = [];
while ($resultHistory = $queryHistory[0]->fetch_assoc()) {
    $arrHistoryOrder[] = $resultHistory;
}
while ($resultHistory = $queryHistory[1]->fetch_assoc()) {
    $arrHistoryOrder[] = $resultHistory;
}
$visitor = $arrHistoryOrder[0]['historyVisitor'];
$order = $arrHistoryOrder[1]['historyOrder'];

$queryResult = [
    'pengunjung' => "SELECT * FROM tb_pengunjung WHERE fk_users = $visitor",
    'pesanan' => "SELECT id_pemesanan, list_no_kamar.no_kamar, mv_type.type_kamar, check_in, check_out, total_harga
                            FROM tb_pemesanan
                            INNER JOIN list_no_kamar
                            ON list_no_kamar.id = tb_pemesanan.no_kamar
                            INNER JOIN mv_type
                            on mv_type.id_type = list_no_kamar.fk_type
                            WHERE fk_users = $order
                            GROUP BY no_kamar",
    'rangeTimeOut' => "SELECT TIMESTAMPDIFF(DAY, NOW(), tb_pemesanan.check_out) AS hari,
                            HOUR(TIMEDIFF(tb_pemesanan.check_out, NOW())) % 24 AS jam,
                            MINUTE(TIMEDIFF(tb_pemesanan.check_out, NOW())) AS menit,
                            SECOND(TIMEDIFF(tb_pemesanan.check_out, NOW())) AS detik
                            FROM tb_pemesanan
                            WHERE tb_pemesanan.fk_users = $order",
];


$results = [
    $dbConnect->query($queryResult['pengunjung']),      //* 0
    $dbConnect->query($queryResult['pesanan']),       //* 1
    $dbConnect->query($queryResult['rangeTimeOut']),    //* 2
];


$rows = [
    mysqli_fetch_assoc($results[0]),
    $results[1]->fetch_assoc(),
    mysqli_fetch_assoc($results[2])
];

$parseInt = [
    'hari' => (int)($rows[2]['hari']),
    'jam' => (int)($rows[2]['jam']),
    'menit' => (int)($rows[2]['menit']),
    'detik' => (int)($rows[2]['detik'])
];

$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$dateArray = ['date' => $now->format('Y-m-d H:i:s')];
$date = json_encode($dateArray['date']);
?>

<html lang="en">
<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Anda</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f0f4f8;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #333;
        }

        h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-align: center;
        }

        a {
            text-decoration: none;
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            margin-bottom: 20px;
            text-align: center;
        }

        a:hover {
            background-color: #2980b9;
        }

        p {
            background: white;
            width: 80%;
            max-width: 600px;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            word-wrap: break-word;
            text-align: left;
        }

        p span:first-child {
            font-weight: bold;
            color: #2c3e50;
            flex: 1;
        }

        p span:last-child {
            flex: 2;
            text-align: left;
        }

        #endPointCheckOut {
            font-size: 1.2em;
            color: #e74c3c;
            font-weight: bold;
            text-align: left;
        }

        @media (max-width: 768px) {
            p {
                width: 95%;
                font-size: 1em;
                flex-direction: column;
                align-items: flex-start;
            }

            p span:last-child {
                text-align: left;
                margin-top: 5px;
            }

            h1 {
                font-size: 2em;
            }

            a {
                padding: 8px 16px;
                font-size: 1em;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.8em;
            }

            a {
                padding: 6px 12px;
                font-size: 0.9em;
            }

            p {
                font-size: 0.9em;
            }
        }
    </style>
</head>

<body>
    <a href="form_pesanan.php">Isi Form Pemesanan</a>

    <h1>Your Payment Result</h1>
    <p><span>nama:</span> <?= $rows[0]['nama'] ?></p>
    <p><span>alamat:</span> <?= $rows[0]['alamat'] ?></p>

    <p><span>no kamar:</span> <?= $rows[1]['no_kamar'] . ' ➡️ ' . $rows[1]['type_kamar'] ?></p>

    <p><span>check in:</span> <?= $rows[1]['check_in'] ?></p>
    <p><span>check out:</span> <?= $rows[1]['check_out'] ?></p>

    <p><span>waktu check out:</span> <span id="endPointCheckOut"><?= $rows[2]['hari'] ?> hari | <?= $rows[2]['jam'] ?> jam • <?= $rows[2]['menit'] ?> menit • <?= $rows[2]['detik'] ?> detik</span></p>

    <p><span>total harga:</span> <?= 'Rp. ' . number_format((int) $rows[1]['total_harga'], 0, '.', ','); ?></p>

    <script>
        function updateCountdown(targetTime) {
            let countdownElement = document.getElementById('endPointCheckOut');

            let interval = setInterval(function() {
                let now = Math.floor(Date.now() / 1000);
                let remainingTime = targetTime - now;

                if (remainingTime <= 0) {
                    clearInterval(interval);
                    alert('Sekarang Anda harus Check Out!');
                    countdownElement.innerHTML = '-- : -- : --';
                    localStorage.removeItem("checkoutTimestamp");
                } else {
                    let hari = Math.floor(remainingTime / 86400);
                    let jam = Math.floor((remainingTime % 86400) / 3600);
                    let menit = Math.floor((remainingTime % 3600) / 60);
                    let detik = remainingTime % 60;

                    countdownElement.innerHTML = hari + " hari | " + jam + " jam • " + menit + " menit • " + detik + " detik...";
                }
            }, 1000);
        }

        window.onload = function() {
            let phpCheckoutTimestamp = Math.floor(new Date("<?= $rows[1]['check_out'] ?>").getTime() / 1000);
            let storedTimestamp = localStorage.getItem("checkoutTimestamp");

            if (!storedTimestamp || parseInt(storedTimestamp) !== phpCheckoutTimestamp) {
                localStorage.setItem("checkoutTimestamp", phpCheckoutTimestamp);
                storedTimestamp = phpCheckoutTimestamp;
            } else {
                storedTimestamp = parseInt(storedTimestamp);
            }

            updateCountdown(storedTimestamp);
        };
    </script>
</body>

</html>