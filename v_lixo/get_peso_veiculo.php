<?php
session_start();
include '../conexao.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_name'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit();
}

if (!isset($_GET['prefixo']) || empty($_GET['prefixo'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Prefixo não especificado.']);
    exit();
}

$prefixo = $_GET['prefixo'];

try {
    $query = "SELECT veiculo_peso FROM veiculos_lixo WHERE prefixo = :prefixo LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':prefixo', $prefixo, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $peso = $stmt->fetchColumn();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'peso' => $peso]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Veículo não encontrado.']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar peso do veículo: ' . $e->getMessage()]);
}
?>