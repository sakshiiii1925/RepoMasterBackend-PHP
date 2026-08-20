<?php
require_once __DIR__ . '/../helpers/mappers.php';
class YardService {
    public function __construct(private PDO $pdo) {}
    public function list(string $agencyId): array {$s=$this->pdo->prepare('SELECT * FROM yard WHERE agency_id=? ORDER BY id DESC');$s->execute([$agencyId]);return array_map(fn($r)=>yardRow($r),$s->fetchAll());}
    public function get(int $id,string $agencyId): array {$s=$this->pdo->prepare('SELECT * FROM yard WHERE id=? AND agency_id=?');$s->execute([$id,$agencyId]);$r=$s->fetch();if(!$r)throw new RuntimeException('Yard not found');return yardRow($r);}
    public function add(array $d): array {$s=$this->pdo->prepare('INSERT INTO yard (yard_name,yard_address,yard_manager_name,yard_contact_no,agency_id) VALUES (?,?,?,?,?)');$s->execute([$d['yardName']??null,$d['yardAddress']??null,$d['yardManagerName']??null,$d['yardContactNo']??null,$d['agencyId']??null]);return $this->get((int)$this->pdo->lastInsertId(),(string)$d['agencyId']);}
    public function update(int $id,array $d,string $agencyId): array {$this->get($id,$agencyId);$s=$this->pdo->prepare('UPDATE yard SET yard_name=?,yard_address=?,yard_manager_name=?,yard_contact_no=? WHERE id=? AND agency_id=?');$s->execute([$d['yardName']??null,$d['yardAddress']??null,$d['yardManagerName']??null,$d['yardContactNo']??null,$id,$agencyId]);return $this->get($id,$agencyId);}
    public function delete(int $id,string $agencyId): void {$this->get($id,$agencyId);$s=$this->pdo->prepare('DELETE FROM yard WHERE id=? AND agency_id=?');$s->execute([$id,$agencyId]);}
}
