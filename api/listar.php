<?php
require 'conexao.php';
header('Content-Type: application/json; charset=utf-8');
try { $stmt = $pdo->query("SELECT * FROM recibos ORDER BY vencimento DESC, nome ASC"); echo json_encode($stmt->fetchAll()); }
catch (Throwable $e) { http_response_code(500); echo json_encode(['erro'=>$e->getMessage()]); }
