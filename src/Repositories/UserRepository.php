<?php
namespace Src\Repositories;

use PDO;
use Src\Config\Database;

class UserRepository {
    private PDO $db;

    public function __construct(array $cfg) {
        $this->db = Database::conn($cfg);
    }

    // 🔹 Ambil semua data user dengan pagination
    public function paginate($page, $per) {
        $off = ($page - 1) * $per;

        $total = (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();

        $stmt = $this->db->prepare('
            SELECT id, username, email
            FROM users
            ORDER BY id DESC
            LIMIT :per OFFSET :off
        ');

        $stmt->bindValue(':per', (int) $per, PDO::PARAM_INT);
        $stmt->bindValue(':off', (int) $off, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $per,
                'last_page' => max(1, (int) ceil($total / $per))
            ]
        ];
    }

    // 🔹 Ambil satu user berdasarkan ID
    public function find($id) {
        $s = $this->db->prepare('SELECT id, username, email FROM users WHERE id=?');
        $s->execute([$id]);
        return $s->fetch();
    }

    // 🔹 Tambah user baru
    public function create($username, $email, $password) {
        $this->db->beginTransaction();
        try {
            $s = $this->db->prepare('INSERT INTO users (username, email, password) VALUES (?,?,?)');
            $s->execute([$username, $email, $password]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $this->find($id);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // 🔹 Update data user
    public function update($id, $username, $email, $password) {
        $s = $this->db->prepare('UPDATE users SET username=?, email=?, password=? WHERE id=?');
        $s->execute([$username, $email, $password, $id]);
        return $this->find($id);
    }

    // 🔹 Hapus user
    public function delete($id) {
        $s = $this->db->prepare('DELETE FROM users WHERE id=?');
        return $s->execute([$id]);
    }
}
