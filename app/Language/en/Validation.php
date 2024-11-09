<?php

// override core en language system validation or define your own en language validation message
return [
    'must_be_same_for_modifier' => 'The "{field}" modifier only allows same values in debit side'
        .' and credit side.',
    'must_be_same_for_financial_entry' => 'The "{field}" modifier only allows same values in'
        .' debit side and credit side.',
    'must_be_same_as_password_of_current_user' => 'The "{field}" must be same to the current'
        .' password of the current user.',

    'valid_numerical_tool_configuration' => 'The "{field}" is not a valid numerical tool'
        .' configuration.',
    'may_allow_exchange_action' => 'The "{field}" modifier only allows same values in debit side'
        .' and credit side.',

    'must_be_on_or_before_current_time' => 'The {field} must be on or before the current time.',
    'must_be_before_incoming_midnight' => 'The {field} must be before the incoming midnight.',

    'ensure_ownership' => 'The {field} must be owned by the current user and present.',
    'has_column_value_in_list' => 'The {field} does not match the acceptable values.',
    'is_unique_compositely' => 'The {field} must be a unique value in the database.',

    'is_valid_currency_amount' => 'The amount in {field} must be a fractional form or decimal form.'
];
