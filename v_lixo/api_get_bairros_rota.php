<?php
// get_bairros_rota.php
include '../conexao.php';
header('Content-Type: application/json');
$rota = $_GET['rota'] ?? '';
$bairros = [];
if ($rota) {
    $stmt = $conn->prepare("SELECT bairros_json FROM rotas_lixo WHERE rota_nome = :rota LIMIT 1");
    $stmt->bindParam(':rota', $rota, PDO::PARAM_STR);
    $stmt->execute();
    $json = $stmt->fetchColumn();
    if ($json) {
        $bairros = json_decode($json, true) ?: [];
    }
}
echo json_encode(['bairros' => $bairros]);