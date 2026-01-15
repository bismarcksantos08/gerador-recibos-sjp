<?php
require 'conexao.php';
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$recibo_id = $body['recibo_id'] ?? $body['id'] ?? '';

if (!$recibo_id) {
  http_response_code(400);
  echo json_encode(['erro' => 'recibo_id obrigatório']);
  exit;
}

try {
  $pdo->beginTransaction();

  // Último evento de saída (de onde vamos restaurar)
  $sel = $pdo->prepare("
    SELECT *
    FROM eventos_recibos
    WHERE recibo_id = :rid
      AND tipo IN ('saida','exclusao')
    ORDER BY ts DESC, id DESC
    LIMIT 1
  ");
  $sel->execute([':rid' => $recibo_id]);
  $e = $sel->fetch(PDO::FETCH_ASSOC);

  if (!$e) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(['erro' => 'Nenhum evento de saída encontrado para restaurar']);
    exit;
  }

  // Restaura recibo completo
  $ins = $pdo->prepare("
    INSERT INTO recibos
      (id, nome, endereco, bairro, setor, telefone, telefonec, vencimento, numero, pago, valor, pagamento)
    VALUES
      (:id, :nome, :endereco, :bairro, :setor, :telefone, :telefonec, :vencimento, :numero, 0, :valor, :pagamento)
    ON DUPLICATE KEY UPDATE
      nome=VALUES(nome),
      endereco=VALUES(endereco),
      bairro=VALUES(bairro),
      setor=VALUES(setor),
      telefone=VALUES(telefone),
      telefonec=VALUES(telefonec),
      vencimento=VALUES(vencimento),
      numero=VALUES(numero),
      pago=VALUES(pago),
      valor=VALUES(valor),
      pagamento=VALUES(pagamento)
  ");

  $ins->execute([
    ':id'        => $e['recibo_id'],
    ':nome'      => $e['nome'] ?? '',
    ':endereco'  => $e['endereco'] ?? '',
    ':bairro'    => $e['bairro'] ?? '',
    ':setor'     => $e['setor'] ?? '',
    ':telefone'  => $e['telefone'] ?? '',
    ':telefonec' => $e['telefonec'] ?? '',
    ':vencimento'=> $e['vencimento'] ?? null,
    ':numero'    => $e['numero'] ?? 'S/N',
    ':valor'     => $e['valor'] ?? 0,
    ':pagamento' => $e['pagamento'] ?? 'Dinheiro'
  ]);

  // Remove o evento de saída restaurado (para sumir da lista de saídas)
  $del = $pdo->prepare("DELETE FROM eventos_recibos WHERE id = :id");
  $del->execute([':id' => $e['id']]);

  $pdo->commit();
  echo json_encode(['status' => 'ok']);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['erro' => $e->getMessage()]);
}
