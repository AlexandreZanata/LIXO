<?php
session_start();
include '../conexao.php';

// Verificar se o usuário está logado e tem a role correta
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'lixo') {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit();
}

$response = ['success' => false, 'message' => 'Requisição inválida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'marcar_pesagem') {
    $user_name = $_SESSION['user_name'];
    $veiculo_id = $_POST['veiculo_id'] ?? '';
    
    try {
        // Buscar o último registro de lixo para este veículo
        $registro_query = "SELECT id FROM registros_lixo 
                           WHERE veiculo_id = :veiculo_id 
                           ORDER BY id DESC LIMIT 1";
        $registro_stmt = $conn->prepare($registro_query);
        $registro_stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_STR);
        $registro_stmt->execute();
        $registro = $registro_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registro) {
            $registro_id = $registro['id'];
            
            // Atualizar apenas o status de pesagem
            $update_query = "UPDATE registros_lixo 
                            SET status_pesagem = 'em_pesagem'
                            WHERE id = :registro_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':registro_id', $registro_id, PDO::PARAM_INT);
            
            if ($update_stmt->execute()) {
                // Buscar código do veículo do usuário
                $user_query = "SELECT codigo_veiculo FROM usuarios WHERE name = :name LIMIT 1";
                $user_stmt = $conn->prepare($user_query);
                $user_stmt->bindParam(':name', $user_name, PDO::PARAM_STR);
                $user_stmt->execute();
                $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user_data) {
                    $codigo_veiculo = $user_data['codigo_veiculo'];
                    
                    // Atualizar status do veículo
                    $update_veiculo = "UPDATE veiculos SET status = 'Em pesagem' WHERE id = :veiculo_id";
                    $update_veiculo_stmt = $conn->prepare($update_veiculo);
                    $update_veiculo_stmt->bindParam(':veiculo_id', $codigo_veiculo, PDO::PARAM_INT);
                    $update_veiculo_stmt->execute();
                }
                
                $response = ['success' => true, 'message' => 'Veículo marcado para pesagem com sucesso.'];
            } else {
                $response = ['success' => false, 'message' => 'Erro ao atualizar registro.'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Nenhum registro encontrado para este veículo.'];
        }
    } catch (PDOException $e) {
        $response = ['success' => false, 'message' => 'Erro ao processar: ' . $e->getMessage()];
    }
}

// Retornar resposta como JSON
header('Content-Type: application/json');
echo json_encode($response);
exit();