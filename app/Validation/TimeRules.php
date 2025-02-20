<?php

namespace App\Validation;

use App\Models\FrozenPeriodModel;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;

class TimeRules
{
    public function must_be_on_or_before_current_time(
        $value,
        ?string &$error = null
    ): bool {
        $does_not_exceed = $this->isOnOrBeforeOtherTime(
            $value,
            Time::now("Asia/Manila")->toDateTimeString()
        );

        return $does_not_exceed;
    }

    public function must_be_before_incoming_midnight(
        $value,
        ?string &$error = null
    ): bool {
        $does_not_exceed = $this->isOnOrBeforeOtherTime(
            $value,
            Time::now("Asia/Manila")
                ->setHour(23)
                ->setMinute(59)
                ->setSecond(59)
                ->toDateTimeString()
        );

        return $does_not_exceed;
    }

    public function must_be_on_before_time_of_other_field(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        helper("array");

        $parameters = explode(",", $parameters);

        if (
            count($parameters) < 1
            || is_null(dot_array_search($parameters[0], $data))
        ) {
            throw new InvalidArgumentException(
                '"'.$parameters[0].'" needs a valid date to check the field.'
            );
        }

        $other_time = dot_array_search($parameters[0], $data);
        if (!$this->isValidDate($other_time)) {
            return false;
        }

        $does_not_exceed = $this->isOnOrBeforeOtherTime($value, $other_time);

        if (!$does_not_exceed) {
            $error = "This must be on or before $other_time.";
        }

        return $does_not_exceed;
    }

    public function must_be_thawed(
        $value,
        ?string &$error = null
    ): bool {
        $frozen_period_model = model(FrozenPeriodModel::class);
        $matched_frozen_entry_count = $frozen_period_model
            ->where("started_at <=", Time::createFromFormat(DATE_TIME_STRING_FORMAT, $value))
            ->where("finished_at >=", Time::createFromFormat(DATE_TIME_STRING_FORMAT, $value))
            ->countAllResults();
        $is_not_frozen = $matched_frozen_entry_count === 0;

        if ($is_not_frozen) {
            $error = "{field} must not be within a frozen period.";
        }

        return $is_not_frozen;
    }

    private function isValidDate($value): bool
    {
        try {
            Time::createFromFormat(DATE_TIME_STRING_FORMAT, $value, "Asia/Manila");
            return true;
        } finally {
        }

        return false;
    }

    private function isOnOrBeforeOtherTime(string $raw_subject_time, string $raw_other_time): bool
    {
        $subject_time = Time::createFromFormat(DATE_TIME_STRING_FORMAT, $raw_subject_time);
        $other_time = Time::createFromFormat(DATE_TIME_STRING_FORMAT, $raw_other_time);
        return $subject_time->isBefore($other_time) || $subject_time->equals($other_time);
    }
}
