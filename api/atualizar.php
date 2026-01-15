<?php
require 'conexao.php';
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];

function normalize_date($v){
  if ($v === null) return null;
  $s = trim((string)$v);
  if ($s === '') return null;
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
  if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $s, $m)){
    return $m[3].'-'.$m[2].'-'.$m[1];
  }
  return $s;
}

$id = $body['id'] ?? ($body['recibo_id'] ?? '');
if (!$id) { http_response_code(400); echo json_encode(['erro'=>'ID obrigatório']); exit; }

try{
  $pdo->beginTransaction();

  $venc = normalize_date($body['vencimento'] ?? null);

  $stmt = $pdo->prepare("
    UPDATE recibos SET
      nome=:nome,
      endereco=:endereco,
      bairro=:bairro,
      setor=:setor,
      telefone=:telefone,
      telefonec=:telefonec,
      vencimento=:vencimento,
      numero=:numero,
      pago=:pago,
      valor=:valor,
      pagamento=:pagamento
    WHERE id=:id
  ");

  $stmt->execute([
    ':id'        => $id,
    ':nome'      => $body['nome'] ?? '',
    ':endereco'  => $body['endereco'] ?? '',
    ':bairro'    => $body['bairro'] ?? '',
    ':setor'     => $body['setor'] ?? '',
    ':telefone'  => $body['telefone'] ?? '',
    ':telefonec' => $body['telefonec'] ?? ($body['telefone_cliente'] ?? ''),
    ':vencimento'=> $venc,
    ':numero'    => $body['numero'] ?? 'S/N',
    ':pago'      => isset($body['pago']) ? (int)!!$body['pago'] : 0,
    ':valor'     => $body['valor'] ?? 0,
    ':pagamento' => $body['pagamento'] ?? 'Dinheiro'
  ]);

  // Confirma se o registro existe (caso id não exista, não atualiza)
  $check = $pdo->prepare("SELECT id FROM recibos WHERE id = :id LIMIT 1");
  $check->execute([':id'=>$id]);
  $exists = $check->fetch();
  if (!$exists){
    throw new Exception("Recibo não encontrado para atualizar (id=$id).");
  }

  $pdo->commit();
  echo json_encode(['status'=>'ok']);

} catch(Throwable $e){
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['erro'=>$e->getMessage()]);
}
