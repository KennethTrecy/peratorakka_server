<?php

namespace App\Database\Migrations;

use App\Models\FrozenPeriodModel;
use App\Models\SummaryCalculationModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\Migration;

class ExpandSummaryCalculations extends Migration
{
    public function up()
    {
        try {
            $new_fields = [
                "opened_debit_amount" => [
                    "type" => "TEXT",
                    "null" => false,
                    "default" => "0"
                ],
                "opened_credit_amount" => [
                    "type" => "TEXT",
                    "null" => false,
                    "default" => "0"
                ]
            ];
            $this->forge->addColumn("summary_calculations", $new_fields);

            $frozen_periods = model(FrozenPeriodModel::class)->findAll();
            $previous_keyed_summary_calculations = [];
            foreach ($frozen_periods as $index => $frozen_period) {
                $summary_calculations = model(SummaryCalculationModel::class)
                    ->where("frozen_period_id", $frozen_period->id)
                    ->findAll();

                $keyed_summary_calculations = [];
                foreach ($summary_calculations as $summary_calculation) {
                    $account_id = $summary_calculation->account_id;

                    $previous_calculation = $previous_keyed_summary_calculations[$account_id] ?? [];

                    $summary_calculation->opened_debit_amount
                        = $previous_calculation["closed_debit_amount"] ?? "0";
                    $summary_calculation->opened_credit_amount
                        = $previous_calculation["closed_credit_amount"] ?? "0";
                    $keyed_summary_calculations[$account_id] = $summary_calculation->toArray();
                }

                model(SummaryCalculationModel::class)
                    ->updateBatch(array_values($keyed_summary_calculations), "id");

                $previous_keyed_summary_calculations = $keyed_summary_calculations;
            }
        } catch (DatabaseException $error) {
            $this->down();
            throw $error;
        } catch (\TypeError $error) {
            $this->down();
            throw $error;
        } catch (\ErrorException $error) {
            $this->down();
            throw $error;
        }
    }

    public function down()
    {
        $this->forge->dropColumn("summary_calculations", "opened_debit_amount");
        $this->forge->dropColumn("summary_calculations", "opened_credit_amount");
    }
}
