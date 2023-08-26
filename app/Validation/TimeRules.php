<?php

namespace App\Validation;

use CodeIgniter\I18n\Time;

class TimeRules {
    public function must_be_on_or_before_current_time(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $does_not_exceed = $this->isOnOrBeforeOtherTime($value, Time::now()->toDateTimeString());

        if (!$does_not_exceed) {
            $error = "{field} must be on or before the current time.";
        }

        return $does_not_exceed;
    }

    public function must_not_exceed_other_time_field(
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
            $error = '"{0}" needs a valid to be a valid date to check the {field}.';
            return false;
        }

        $other_time = dot_array_search($parameters[0], $data);
        if (!isValidDate($other_time)) {
            return false;
        }

        $does_not_exceed = $this->isOnOrBeforeOtherTime($value, $other_time);

        if (!$does_not_exceed) {
            $error = "{field} must be on or before $other_time.";
        }

        return $does_not_exceed;
    }

    private function isValidDate($value): bool {
        try {
            Time::createFromFormat(DATE_TIME_STRING_FORMAT, $value);
            return true;
        } finally {
        }

        return false;
    }

    private function isOnOrBeforeOtherTime(string $raw_subject_time, string $raw_other_time): bool {
        $subject_time = Time::createFromFormat(DATE_TIME_STRING_FORMAT, $raw_subject_time);
        $other_time = Time::createFromFormat(DATE_TIME_STRING_FORMAT, $raw_other_time);
        return $subject_time->isBefore($other_time) || $subject_time->equals($other_time);
    }
}
