<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(900);

$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

// =======================
// ⛔ DETENER
// =======================
if ($accion === 'detener') {
    file_put_contents(__DIR__ . '/estado.txt', 'STOP');
    echo json_encode(array("ok" => true));
    exit;
}

// =======================
// ⚙️ MODO NORMAL (HTML)
// =======================
if ($accion !== 'ejecutar') {
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Scraper OLT</title>

<style>
body {
    font-family: Arial;
    background: #f3f3f3;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.card {
    background: #fff;
    padding: 2rem;
    border-radius: 8px;
    width: 350px;
}
.btn {
    width: 100%;
    padding: 10px;
    border: none;
    color: white;
    cursor: pointer;
    margin-top: 10px;
}
.iniciar { background: #4a90e2; }
.detener { background: #ef4444; }
#log {
    margin-top: 10px;
    background: black;
    color: #aaa;
    padding: 10px;
    height: 200px;
    overflow-y: auto;
    font-size: 12px;
}
.ok { color: #22c55e; }
.err { color: #ef4444; }
.info { color: #6366f1; }
</style>
</head>

<body>
<div class="card">
    <h2>📡 Scraper OLT</h2>

    <button class="btn iniciar" onclick="iniciar()">▶ Iniciar lecturas</button>
    <button class="btn detener" onclick="detener()">⛔ Detener</button>

    <div id="log"></div>
</div>

<script>
let corriendo = false;

function log(msg, tipo) {
    tipo = tipo || '';
    let div = document.getElementById('log');
    div.innerHTML += '<div class="'+tipo+'">'+msg+'</div>';
    div.scrollTop = div.scrollHeight;
}

function iniciar() {
    corriendo = true;
    log('🚀 Iniciando loop...', 'info');
    ejecutarLoop();
}

function detener() {
    corriendo = false;
    fetch('?accion=detener');
    log('⛔ Detenido por usuario', 'err');
}

function ejecutarLoop() {
    if (!corriendo) return;

    let xhr = new XMLHttpRequest();
    xhr.open('GET', '?accion=ejecutar', true);
    xhr.timeout = 180000;

    xhr.onload = function() {
        try {
            let data = JSON.parse(xhr.responseText);

            if (data.ok) {
                log('✅ ONUs: ' + data.onus, 'ok');
            } else {
                log('❌ ' + data.error, 'err');
                corriendo = false;
            }

        } catch(e) {
            log('❌ Error parseando respuesta', 'err');
            corriendo = false;
        }

        if (corriendo) {
            setTimeout(ejecutarLoop, 1000);
        }
    };

    xhr.onerror = function() {
        log('❌ Error conexión', 'err');
    };

    xhr.ontimeout = function() {
        log('❌ Timeout', 'err');
    };

    xhr.send();
}
</script>
</body>
</html>
<?php
exit;
}

// =======================
// 🚀 MODO EJECUCIÓN
// =======================
ini_set('display_errors', 0);
header('Content-Type: application/json');

file_put_contents(__DIR__ . '/estado.txt', 'RUN');

$baseUrl  = getenv('OLT_URL');
$username = getenv('OLT_USER');
$password = getenv('OLT_PASSWORD');
$cookieFile = __DIR__ . "/cookies.txt";

$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $cookieFile,
    CURLOPT_COOKIEFILE     => $cookieFile,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 15,
));

// LOGIN
curl_setopt_array($ch, array(
    CURLOPT_URL        => $baseUrl . "/login",
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query(array(
        "username" => $username,
        "password" => $password
    ))
));
curl_exec($ch);

// =======================
// 🔁 CURL CON REINTENTOS
// =======================
function curlConReintentos($ch, $url, $intentos = 3) {
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);

    for ($i = 0; $i < $intentos; $i++) {
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($html !== false && $httpCode === 200) {
            return $html;
        }

        // Pequeña pausa antes de reintentar
        usleep(500000); // 0.5 seg
    }

    return false;
}

// =======================
// 🧠 PARSEO SN + ESTADO
// =======================
function getOnuSnList($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $rows  = $xpath->query("//table//tr");

    $onus = array();

    foreach ($rows as $row) {
        $cols = $row->getElementsByTagName("td");
        if ($cols->length < 7) continue;

        preg_match('/:(\d+)/', $cols->item(0)->nodeValue, $idMatch);
        $onuid = isset($idMatch[1]) ? (int)$idMatch[1] : null;

        $estado = trim($cols->item(1)->nodeValue);
        $sn     = trim($cols->item(6)->nodeValue);

        if (!$onuid || !$sn) continue;

        $onus[] = array(
            "onuid"  => $onuid,
            "sn"     => $sn,
            "estado" => $estado
        );
    }

    return $onus;
}

// =======================
// 🧠 PARSEO OPTICO (robusto)
// =======================
function parseOptical($html) {

    // Regex tolerante a espacios, saltos de línea y atributos extra en los tags
    preg_match('/Rx\s+optical\s+level\s*<\/td>\s*<td[^>]*>\s*([-\d\.]+)/i', $html, $rx);
    preg_match('/Tx\s+optical\s+level\s*<\/td>\s*<td[^>]*>\s*([-\d\.]+)/i', $html, $tx);
    preg_match('/Temperature\s*<\/td>\s*<td[^>]*>\s*([\d\.]+)/i',            $html, $temp);
    preg_match('/Distance\s*<\/td>\s*<td[^>]*>\s*([\d]+)/i',                 $html, $dist);

    return array(
        "rx_power"    => isset($rx[1])   ? (float)$rx[1]   : null,
        "tx_power"    => isset($tx[1])   ? (float)$tx[1]   : null,
        "temperature" => isset($temp[1]) ? (float)$temp[1] : null,
        "distance"    => isset($dist[1]) ? (int)$dist[1]   : null
    );
}

$ponCounts = array(
    1 => 91,  2 => 116, 3 => 110, 4 => 89,
    5 => 71,  6 => 116, 7 => 32,  8 => 78
);

$snMap   = array();
$allOnus = array();

// =======================
// 🔎 SACAR SN
// =======================
foreach ($ponCounts as $pon => $maxOnu) {

    if (trim(@file_get_contents(__DIR__.'/estado.txt')) === 'STOP') {
        echo json_encode(array("ok"=>false,"error"=>"Proceso detenido"));
        exit;
    }

    $url  = $baseUrl . "/action/onuauthinfo.html?select=".$pon."&authmode=0";
    $html = curlConReintentos($ch, $url);

    if (!$html) continue; // Si falla los 3 intentos, skip este PON

    $snMap[$pon] = getOnuSnList($html);
}

// =======================
// 🧠 FILTRAR SN ÚNICOS
// Prioriza Online, si no hay Online queda el primero que apareció
// =======================
$snUnicos = array();

foreach ($snMap as $pon => $onus) {
    foreach ($onus as $onu) {

        $sn = $onu["sn"];

        if (!isset($snUnicos[$sn])) {
            // Primera vez que vemos este SN, lo guardamos
            $snUnicos[$sn] = array(
                "pon"    => $pon,
                "onuid"  => $onu["onuid"],
                "estado" => $onu["estado"]
            );
        } elseif ($onu["estado"] === "Online") {
            // Si aparece de nuevo y está Online, lo pisamos (tiene prioridad)
            $snUnicos[$sn] = array(
                "pon"    => $pon,
                "onuid"  => $onu["onuid"],
                "estado" => $onu["estado"]
            );
        }
        // Si ya existe y el nuevo NO es Online, lo ignoramos (no lo descartamos)
    }
}

// =======================
// 🔁 OPTICO SOLO ÚNICOS
// =======================
foreach ($snUnicos as $sn => $data) {

    if (trim(@file_get_contents(__DIR__.'/estado.txt')) === 'STOP') {
        echo json_encode(array("ok"=>false,"error"=>"Proceso detenido"));
        exit;
    }

    $pon   = $data["pon"];
    $onuid = $data["onuid"];

    $url  = $baseUrl . "/action/onuoptical.html?ponid=".$pon."&onuid=".$onuid;
    $html = curlConReintentos($ch, $url);

    if (!$html) continue;

    $optical = parseOptical($html);

    // ✅ Incluimos la ONU aunque no tenga datos ópticos
    // Solo descartamos si rx_power es exactamente null Y tx_power también (página vacía/error real)
    if ($optical["rx_power"] === null && $optical["tx_power"] === null &&
        $optical["temperature"] === null && $optical["distance"] === null) {
        continue; // HTML llegó pero no tenía ningún dato óptico — skip
    }

    $allOnus[] = array(
        "pon"         => $pon,
        "onuid"       => $onuid,
        "sn"          => $sn,
        "estado"      => $data["estado"],
        "rx_power"    => $optical["rx_power"],
        "tx_power"    => $optical["tx_power"],
        "temperature" => $optical["temperature"],
        "distance"    => $optical["distance"]
    );
}

curl_close($ch);

// =======================
// 📤 ENVIAR
// =======================
$json = json_encode($allOnus);

$respuesta = file_get_contents("", false, stream_context_create(array(
    'http'=>array(
        'method'=>'POST',
        'header'=>"Content-Type: application/json\r\n",
        'content'=>$json
    )
)));

echo json_encode(array(
    "ok"      => true,
    "onus"    => count($allOnus),
    "respuesta" => $respuesta
));