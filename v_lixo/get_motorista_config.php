<?php
session_start();
include '../conexao.php';

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'lixo_adm') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit();
}

// Verificar se o ID do usuário foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID do usuário não fornecido']);
    exit();
}

$usuario_id = $_GET['id'];

try {
    // Buscar configuração do motorista
    $query = "SELECT * FROM config_motoristas_lixo WHERE usuario_id = :usuario_id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'config' => $config]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Nenhuma configuração encontrada para este motorista']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar configuração: ' . $e->getMessage()]);
}
?>