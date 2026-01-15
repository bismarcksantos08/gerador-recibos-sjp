<?php
require 'conexao.php';
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = $body['id'] ?? $body['recibo_id'] ?? '';

if (!$id) {
  http_response_code(400);
  echo json_encode(['erro' => 'ID obrigatório']);
  exit;
}

try {
  $pdo->beginTransaction();

  // Busca antes de excluir (para log completo)
  $sel = $pdo->prepare("SELECT * FROM recibos WHERE id = :id");
  $sel->execute([':id' => $id]);
  $r = $sel->fetch(PDO::FETCH_ASSOC);

  if (!$r) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(['erro' => 'Recibo não encontrado']);
    exit;
  }

  // Exclui
  $del = $pdo->prepare("DELETE FROM recibos WHERE id = :id");
  $del->execute([':id' => $id]);

  // Log SAÍDA completo
  $ins = $pdo->prepare("
    INSERT INTO eventos_recibos
      (tipo, recibo_id, valor, vencimento, ts, usuario,
       nome, endereco, bairro, setor, telefone, telefonec, numero, pagamento)
    VALUES
      ('saida', :recibo_id, :valor, :vencimento, NOW(), 'local',
       :nome, :endereco, :bairro, :setor, :telefone, :telefonec, :numero, :pagamento)
  ");

  $ins->execute([
    ':recibo_id'  => $r['id'],
    ':valor'      => $r['valor'] ?? 0,
    ':vencimento' => $r['vencimento'] ?? null,
    ':nome'       => $r['nome'] ?? '',
    ':endereco'   => $r['endereco'] ?? '',
    ':bairro'     => $r['bairro'] ?? '',
    ':setor'      => $r['setor'] ?? '',
    ':telefone'   => $r['telefone'] ?? '',
    ':telefonec'  => $r['telefonec'] ?? '',
    ':numero'     => $r['numero'] ?? 'S/N',
    ':pagamento'  => $r['pagamento'] ?? 'Dinheiro'
  ]);

  $pdo->commit();
  echo json_encode(['status' => 'ok']);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['erro' => $e->getMessage()]);
}
