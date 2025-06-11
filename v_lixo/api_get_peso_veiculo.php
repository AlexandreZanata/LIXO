<?php
// get_peso_veiculo.php
include '../conexao.php';
header('Content-Type: application/json');
$prefixo = $_GET['prefixo'] ?? '';
$peso = null;
if ($prefixo) {
    $stmt = $conn->prepare("SELECT veiculo_peso FROM veiculos_lixo WHERE prefixo = :prefixo LIMIT 1");
    $stmt->bindParam(':prefixo', $prefixo, PDO::PARAM_STR);
    $stmt->execute();
    $peso = $stmt->fetchColumn();
}
echo json_encode(['peso' => $peso]);