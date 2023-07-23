<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Test\Interfaces\FabricatorModel;
use Faker\Generator;

class CurrencyModel extends Model implements FabricatorModel
{
    protected $table            = "currencies";
    protected $primaryKey       = "id";
    protected $useAutoIncrement = true;
    protected $returnType       = "array";
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        "user_id",
        "code",
        "name"
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = "datetime";
    protected $createdField  = "created_at";
    protected $updatedField  = "updated_at";
    protected $deletedField  = "deleted_at";

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    public function fake(Generator &$faker)
    {
        return [
            "code"  => $faker->currencyCode(),
            "name"  => $faker->firstName,
        ];
    }
}
