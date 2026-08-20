<?php
require_once __DIR__ . '/../helpers/mappers.php';
class SearchHistoryService {
    public function __construct(private PDO $pdo) {}
    public function save(string $vehicle,string $email,string $name,string $agency): array {$s=$this->pdo->prepare('INSERT INTO search_history (vehicle_number,user_email,user_name,search_time,agency_id) VALUES (?,?,?,?,?)');$s->execute([$vehicle,$email,$name,date('Y-m-d H:i:s'),$agency]);$id=(int)$this->pdo->lastInsertId();$q=$this->pdo->prepare('SELECT * FROM search_history WHERE id=?');$q->execute([$id]);return searchHistoryRow($q->fetch());}
    public function list(string $agency): array {$s=$this->pdo->prepare('SELECT * FROM search_history WHERE agency_id=? ORDER BY search_time DESC');$s->execute([$agency]);return array_map(fn($r)=>searchHistoryRow($r),$s->fetchAll());}
    public function all(): array {$s=$this->pdo->query('SELECT * FROM search_history ORDER BY search_time DESC');return array_map(fn($r)=>searchHistoryRow($r),$s->fetchAll());}
    public function search(string $agency,string $vehicle): array {$s=$this->pdo->prepare('SELECT * FROM search_history WHERE agency_id=? AND LOWER(vehicle_number) LIKE LOWER(?) ORDER BY search_time DESC');$s->execute([$agency,'%'.$vehicle.'%']);return array_map(fn($r)=>searchHistoryRow($r),$s->fetchAll());}
    public function byUser(string $agency,string $name): array {$s=$this->pdo->prepare('SELECT * FROM search_history WHERE agency_id=? AND user_name=? ORDER BY search_time DESC');$s->execute([$agency,$name]);return array_map(fn($r)=>searchHistoryRow($r),$s->fetchAll());}
    public function byDate(string $agency,string $date): array {$s=$this->pdo->prepare('SELECT * FROM search_history WHERE agency_id=? AND search_time BETWEEN ? AND ? ORDER BY search_time DESC');$s->execute([$agency,$date.' 00:00:00',$date.' 23:59:59']);return array_map(fn($r)=>searchHistoryRow($r),$s->fetchAll());}
    public function sort(string $agency,string $order): array {$sql=$order==='oldest'?'SELECT * FROM search_history WHERE agency_id=? ORDER BY search_time ASC':'SELECT * FROM search_history WHERE agency_id=? ORDER BY search_time DESC';$s=$this->pdo->prepare($sql);$s->execute([$agency]);return array_map(fn($r)=>searchHistoryRow($r),$s->fetchAll());}
}
