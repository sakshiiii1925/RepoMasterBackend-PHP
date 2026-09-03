<?php

require_once __DIR__ . '/../helpers/mappers.php';

class UserService
{
    public function __construct(private PDO $pdo) {}

    private function findByEmail(string $email): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $s->execute([$email]);
        return $s->fetch() ?: null;
    }

    private function findById(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function registerAdmin(array $u): array
    {
        if ($this->findByEmail((string)($u['email'] ?? ''))) throw new RuntimeException('Email already exists');
        $agencyId = trim((string)($u['agencyId'] ?? ''));
        if ($agencyId === '') throw new RuntimeException('Agency ID is required');

        $s = $this->pdo->prepare('INSERT INTO users (full_name,email,mobile,password,address,role,reference_admin_email,status,failed_attempts,agency_id,lock_time) VALUES (?,?,?,?,?,\'ADMIN\',?,?,0,?,NULL)');
        $s->execute([
            $u['fullName'] ?? null, $u['email'] ?? null, $u['mobile'] ?? null,
            password_hash((string)($u['password'] ?? ''), PASSWORD_BCRYPT), $u['address'] ?? null,
            $u['referenceAdminEmail'] ?? null, 'ACTIVE', $agencyId
        ]);
        return userRow($this->findById((int)$this->pdo->lastInsertId()));
    }

    public function registerUser(array $u): array
    {
        $agencyId = trim((string)($u['agencyId'] ?? ''));
        $s = $this->pdo->prepare("SELECT id FROM users WHERE agency_id = ? AND role = 'ADMIN' LIMIT 1");
        $s->execute([$agencyId]);
        if (!$s->fetch()) throw new RuntimeException('Invalid Agency ID');
        if ($this->findByEmail((string)($u['email'] ?? ''))) throw new RuntimeException('Email already exists');

        $s = $this->pdo->prepare("INSERT INTO users (full_name,email,mobile,password,address,role,reference_admin_email,status,failed_attempts,agency_id,lock_time) VALUES (?,?,?,?,?,'USER',?,?,0,?,NULL)");
        $s->execute([
            $u['fullName'] ?? null, $u['email'] ?? null, $u['mobile'] ?? null,
            password_hash((string)($u['password'] ?? ''), PASSWORD_BCRYPT), $u['address'] ?? null,
            $u['referenceAdminEmail'] ?? null, 'PENDING', $agencyId
        ]);
        return userRow($this->findById((int)$this->pdo->lastInsertId()));
    }

   public function login(array $r): array
{
    $u = $this->findByEmail((string)($r['email'] ?? ''));

    if (!$u) {
        return [
            'success' => false,
            'message' => 'Invalid Email or Password',
            'id' => null,
            'role' => null,
            'status' => null,
            'fullName' => null,
            'email' => null,
            'agencyId' => null
        ];
    }

    $lockDuration = 60000;

    $failed = (int)$u['failed_attempts'];

    $lockTime =
        $u['lock_time'] !== null
            ? (int)$u['lock_time']
            : null;

    // Check account lock
    if ($failed >= 3 && $lockTime !== null) {

        $elapsed =
            (int)(microtime(true) * 1000) - $lockTime;

        if ($elapsed < $lockDuration) {

            $remaining =
                max(
                    0,
                    (int)(($lockDuration - $elapsed) / 1000)
                );

            return [
                'success' => false,
                'message' =>
                    'Account locked. Try again in '
                    . $remaining
                    . ' seconds.',
                'id' => null,
                'role' => null,
                'status' => null,
                'fullName' => null,
                'email' => null,
                'agencyId' => null
            ];
        }

        $s = $this->pdo->prepare(
            'UPDATE users
             SET failed_attempts = 0,
                 lock_time = NULL
             WHERE id = ?'
        );

        $s->execute([$u['id']]);

        $u['failed_attempts'] = 0;
        $u['lock_time'] = null;
    }

    // Check password
    if (
        !password_verify(
            (string)($r['password'] ?? ''),
            $u['password']
        )
    ) {

        $failed =
            (int)$u['failed_attempts'] + 1;

        // Lock after 3 failed attempts
        if ($failed >= 3) {

            $now =
                (int)(microtime(true) * 1000);

            $s = $this->pdo->prepare(
                'UPDATE users
                 SET failed_attempts = ?,
                     lock_time = ?
                 WHERE id = ?'
            );

            $s->execute([
                $failed,
                $now,
                $u['id']
            ]);

            return [
                'success' => false,
                'message' =>
                    'Account locked. Try again in 60 seconds.',
                'id' => null,
                'role' => null,
                'status' => null,
                'fullName' => null,
                'email' => null,
                'agencyId' => null
            ];
        }

        $s = $this->pdo->prepare(
            'UPDATE users
             SET failed_attempts = ?
             WHERE id = ?'
        );

        $s->execute([
            $failed,
            $u['id']
        ]);

        return [
            'success' => false,
            'message' =>
                'Invalid password. '
                . (3 - $failed)
                . ' attempt(s) remaining.',
            'id' => null,
            'role' => null,
            'status' => null,
            'fullName' => null,
            'email' => null,
            'agencyId' => null
        ];
    }

    // User waiting for admin approval
    if (
        $u['role'] === 'USER' &&
        $u['status'] === 'PENDING'
    ) {

        return [
            'success' => false,
            'message' => 'Waiting for Admin Approval',
            'id' => (int)$u['id'],
            'role' => $u['role'],
            'status' => $u['status'],
            'fullName' => $u['full_name'],
            'email' => $u['email'],
            'agencyId' => $u['agency_id']
        ];
    }

    // Reset failed login information
    $s = $this->pdo->prepare(
        'UPDATE users
         SET failed_attempts = 0,
             lock_time = NULL
         WHERE id = ?'
    );

    $s->execute([$u['id']]);

    // Successful login
    return [
        'success' => true,
        'message' => 'Login Successful',

        // IMPORTANT
        'id' => (int)$u['id'],

        'role' => $u['role'],
        'status' => $u['status'],
        'fullName' => $u['full_name'],
        'email' => $u['email'],
        'agencyId' => $u['agency_id']
    ];
}
       

    public function getPendingUsers(string $agencyId): array { return $this->listUsers('agency_id = ? AND status = ?', [$agencyId,'PENDING']); }
    public function approveUser(int $id): array { return $this->setStatus($id,'ACTIVE'); }
    public function rejectUser(int $id): array { return $this->setStatus($id,'REJECTED'); }
    private function setStatus(int $id,string $status): array {
        if (!$this->findById($id)) throw new RuntimeException('User Not Found');
        $s=$this->pdo->prepare('UPDATE users SET status=? WHERE id=?'); $s->execute([$status,$id]);
        return userRow($this->findById($id));
    }
    public function getProfile(string $email): array { $u=$this->findByEmail($email); if(!$u) throw new RuntimeException('User Not Found'); return userRow($u); }
    public function updateUser(int $id,array $u): array {
        if(!$this->findById($id)) throw new RuntimeException('User not found');
        $s=$this->pdo->prepare('UPDATE users SET full_name=?, email=?, mobile=?, address=? WHERE id=?');
        $s->execute([$u['fullName']??null,$u['email']??null,$u['mobile']??null,$u['address']??null,$id]);
        return userRow($this->findById($id));
    }
    public function forgotPassword(string $email): string { if(!$this->findByEmail($email)) throw new RuntimeException('Email not registered'); return 'Email verified. Reset password allowed'; }
    public function resetPassword(string $email,string $newPassword): array { $u=$this->findByEmail($email); if(!$u) throw new RuntimeException('User not found'); $s=$this->pdo->prepare('UPDATE users SET password=? WHERE id=?'); $s->execute([password_hash($newPassword,PASSWORD_BCRYPT),$u['id']]); return userRow($this->findById((int)$u['id'])); }
    public function verifyEmail(string $email): bool { return $this->findByEmail($email)!==null; }
    public function getUsersByAdmin(string $agencyId): array { return $this->listUsers("agency_id = ? AND role = 'USER' AND status = 'ACTIVE'",[$agencyId]); }
    public function searchUsers(string $agencyId,string $search): array { $q='%'.$search.'%'; return $this->listUsers("agency_id = ? AND role = 'USER' AND status = 'ACTIVE' AND (LOWER(full_name) LIKE LOWER(?) OR LOWER(email) LIKE LOWER(?))",[$agencyId,$q,$q]); }
    public function deleteUser(int $id): void { if(!$this->findById($id)) throw new RuntimeException('User not found'); $s=$this->pdo->prepare('DELETE FROM users WHERE id=?'); $s->execute([$id]); }
    public function getApprovedUsersByAgency(string $agencyId): array { return $this->getUsersByAdmin($agencyId); }
    public function getPendingUserCount(): int { return (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE status='PENDING'")->fetchColumn(); }
    private function listUsers(string $where,array $params): array { $s=$this->pdo->prepare('SELECT * FROM users WHERE '.$where.' ORDER BY id DESC'); $s->execute($params); return array_map(fn($r)=>userRow($r),$s->fetchAll()); }
public function updateUserStatus(
    int $id,
    string $status
): array {

    $user = $this->findById($id);

    if (!$user) {
        throw new RuntimeException(
            'User not found'
        );
    }

    if ($user['role'] !== 'USER') {
        throw new RuntimeException(
            'Only users can be activated or deactivated'
        );
    }

    $status = strtoupper(trim($status));

    if (!in_array(
        $status,
        ['ACTIVE', 'INACTIVE'],
        true
    )) {
        throw new RuntimeException(
            'Invalid status'
        );
    }

    $stmt = $this->pdo->prepare(
        'UPDATE users
         SET status = ?
         WHERE id = ?'
    );

    $stmt->execute([
        $status,
        $id
    ]);

    return userRow(
        $this->findById($id)
    );
}
public function getUserAgencyId(int $userId): string
{
    $user = $this->findById($userId);

    if (!$user) {
        throw new RuntimeException('User not found');
    }

    if ($user['role'] !== 'USER') {
        throw new RuntimeException('Only users can access user vehicle data');
    }

    if ($user['status'] !== 'ACTIVE') {
        throw new RuntimeException('User account is not active');
    }

    $agencyId = trim((string)($user['agency_id'] ?? ''));

    if ($agencyId === '') {
        throw new RuntimeException('User agency ID not found');
    }

    return $agencyId;
}
    }
