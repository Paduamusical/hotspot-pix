<?php
declare(strict_types=1); require_once __DIR__ . '/auth.php'; requireAdmin(); $db=db();
if ($_SERVER['REQUEST_METHOD']==='GET') { $rows=$db->query('SELECT * FROM plans ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC); jsonResponse(['plans'=>$rows,'csrf'=>csrf()]); }
verifyCsrf(); $d=input();
try { $id=filter_var($d['id']??null,FILTER_VALIDATE_INT); if ($_SERVER['REQUEST_METHOD']==='DELETE') { if(!$id) jsonResponse(['error'=>'ID inválido'],422); $db->prepare('DELETE FROM plans WHERE id=?')->execute([$id]); jsonResponse(['ok'=>true]); }
  $name=trim((string)($d['name']??''));$price=filter_var($d['price_cents']??null,FILTER_VALIDATE_INT);$minutes=filter_var($d['duration_minutes']??null,FILTER_VALIDATE_INT);$profile=trim((string)($d['mikrotik_profile']??'default'));$active=!empty($d['active'])?1:0;
  if($name===''||$price===false||$price<1||$minutes===false||$minutes<1) jsonResponse(['error'=>'Preencha os dados do plano corretamente.'],422);
  if($id){$db->prepare('UPDATE plans SET name=?,price_cents=?,duration_minutes=?,mikrotik_profile=?,active=? WHERE id=?')->execute([$name,$price,$minutes,$profile,$active,$id]);}else{$db->prepare('INSERT INTO plans(name,price_cents,duration_minutes,mikrotik_profile,active) VALUES(?,?,?,?,?)')->execute([$name,$price,$minutes,$profile,$active]);} jsonResponse(['ok'=>true]);
} catch(Throwable $e){jsonResponse(['error'=>'Não foi possível gravar o plano.'],500);}
