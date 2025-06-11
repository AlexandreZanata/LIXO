<?php
session_start();
include '../conexao.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_name'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID não especificado.']);
    exit();
}

$id = $_GET['id'];

try {
    $query = "SELECT * FROM rotas_lixo WHERE id = :id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $rota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'rota' => $rota]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Rota não encontrada.']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar detalhes da rota: ' . $e->getMessage()]);
}
?>