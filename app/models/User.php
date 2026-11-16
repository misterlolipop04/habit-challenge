<?php

class User
{
    private $conn;
    
    public function __construct($conn)
    {
        $this->conn = $conn;
    }
    
    public function getById($id)
    {
        $stmt = $this->conn->prepare(
            'SELECT * FROM users WHERE id = ?'
            );
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function updateChallengeName($id, $name)
    {
        $stmt = $this->conn->prepare(
            'UPDATE users SET challenge_name = ? WHERE id = ?'
            );
        
        $stmt->bind_param('si', $name, $id);
        return $stmt->execute();
    }
}