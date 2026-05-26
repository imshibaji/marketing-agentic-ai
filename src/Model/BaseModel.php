<?php
namespace MarketingAgent\Model;

use PDO;

abstract class BaseModel {
    protected PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
}
