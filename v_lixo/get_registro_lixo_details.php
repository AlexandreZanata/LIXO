<?php
session_start();
include '../conexao.php';

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_name']) || !in_array($_SESSION['role'], ['lixo_adm', 'admin', 'geraladm'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit();
}

// Verificar se o ID do registro foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID do registro não fornecido']);
    exit();
}

$registro_id = $_GET['id'];

try {
    // Buscar detalhes do registro
    $query = "SELECT 
                r.*,
                v.veiculo,
                v.placa
              FROM registros_lixo r
              LEFT JOIN veiculos v ON r.veiculo_id = v.veiculo
              WHERE r.id = :id LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $registro_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'registro' => $registro]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar registro: ' . $e->getMessage()]);
}
?>