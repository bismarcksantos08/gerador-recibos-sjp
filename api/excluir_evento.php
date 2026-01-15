<?php
require 'conexao.php';
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];

/**
 * Este endpoint EXCLUI um evento específico do histórico (tabela eventos_recibos),
 * sem mexer na tabela principal de recibos.
 *
 * Espera receber:
 *  - id (obrigatório) => id (PK) da tabela eventos_recibos (ex: "Evento ID: 65" no modal)
 *
 * Aceita também: evento_id, eid (apelidos)
 */

$id = $body['id'] ?? ($body['evento_id'] ?? ($body['eid'] ?? null));

if ($id === null || $id === '') {
  http_response_code(400);
  echo json_encode(['status'=>'erro','erro'=>'id obrigatório (Evento ID da tabela eventos_recibos)']);
  exit;
}

if (!ctype_digit((string)$id)) {
  http_response_code(400);
  echo json_encode(['status'=>'erro','erro'=>'id inválido (deve ser numérico)']);
  exit;
}

try {
  // Confere se existe (para retornar 404 quando já não existe)
  $sel = $pdo->prepare("SELECT id, tipo, recibo_id FROM eventos_recibos WHERE id = :id LIMIT 1");
  $sel->execute([':id' => (int)$id]);
  $row = $sel->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    http_response_code(404);
    echo json_encode(['status'=>'erro','erro'=>'Evento não encontrado (talvez já tenha sido removido)','id'=>(int)$id,'db'=>$db ?? null]);
    exit;
  }

  $del = $pdo->prepare("DELETE FROM eventos_recibos WHERE id = :id LIMIT 1");
  $del->execute([':id' => (int)$id]);

  $aff = $del->rowCount();

  echo json_encode([
    'status' => 'ok',
    'deleted' => $aff,
    'id' => (int)$id,
    'tipo' => $row['tipo'],
    'recibo_id' => $row['recibo_id'],
    'db' => $db ?? null
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'erro','erro'=>$e->getMessage(),'db'=>$db ?? null]);
}
