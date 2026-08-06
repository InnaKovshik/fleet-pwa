<?php
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fleet PWA</title>


<!-- Manifest + Theme color -->
<link rel="manifest" href="<?php echo FLEET_PWA_URL; ?>manifest.json">
<meta name="theme-color" content="#1e88e5">

<!-- CSS -->
<style>
body{font-family:sans-serif;margin:0;padding:0;background:#f2f2f2;color:#333;}
#scannerView,#carView{padding:20px;}
#video{border:1px solid #ccc;width:100%;max-width:400px;height:auto;}
button{padding:10px 15px;margin:5px;background:#1e88e5;color:#fff;border:none;border-radius:5px;cursor:pointer;}
button:disabled{background:#ccc;cursor:not-allowed;}
#carView{display:none;}
#status{margin-top:10px;font-weight:bold;}
</style>

</head>
<body>

<!-- Scanner View -->
<div id="scannerView">
    <p id="scannerStatus">Klicken Sie auf die Schaltfläche, um den QR-Scanner zu starten.</p>
    <button id="startCameraBtn">Scanner starten</button>
    <video id="video" autoplay playsinline></video>
    <canvas id="canvas" hidden></canvas>
</div>

<!-- Car / Trip View -->
<div id="carView">
    <h3>Hallo <span id="userName">Fahrer</span>,</h3>
    <h2>Auto: <span id="carIdDisplay"></span> (<span id="kennzeichenDisplay"></span>)</h2>
    <button id="startTrip">Start Trip</button>
    <button id="endTrip" disabled>End Trip</button>
    <p id="status">Keine aktive Fahrt</p>
</div>


<!-- JS Libraries -->
<script src="/wp-content/plugins/fleet-pwa/public/jsQR.js"></script>

<!-- IndexedDB Offline Handling -->
<script src="<?php echo FLEET_PWA_URL; ?>public/db.js"></script>

<!-- Fleet App JS -->
<script src="<?php echo FLEET_PWA_URL; ?>public/app.js"></script>


</body>
</html>
