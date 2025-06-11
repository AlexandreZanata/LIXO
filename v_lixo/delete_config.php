<?php
session_start();
include '../conexao.php';

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'lixo_adm') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit();
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID da configuração não especificado.']);
    exit();
}

$config_id = $_POST['id'];

try {
    // Buscar nome do motorista antes de excluir (para mensagem de confirmação)
    $nome_query = "SELECT nome_usuario FROM config_motoristas_lixo WHERE id = :id LIMIT 1";
    $nome_stmt = $conn->prepare($nome_query);
    $nome_stmt->bindParam(':id', $config_id, PDO::PARAM_INT);
    $nome_stmt->execute();
    $nome_motorista = $nome_stmt->fetchColumn();
    
    if (!$nome_motorista) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Configuração não encontrada.']);
        exit();
    }
    
    // Excluir configuração
    $delete_query = "DELETE FROM config_motoristas_lixo WHERE id = :id";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bindParam(':id', $config_id, PDO::PARAM_INT);
    $delete_stmt->execute();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => "Configuração excluída com sucesso para o motorista: $nome_motorista"
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir configuração: ' . $e->getMessage()]);
}
?>