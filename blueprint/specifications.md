# Specifications
These are set of sections containing the specific details derived from the general requirements.

## Database Specifications
This section contains details of every entity in the system. Each entity is assumed to have
timestamps and can be soft-deleted.

If there are numerical values denoting the financial amount, they should be string to retain the
accuracy of the values.

Only the email is the minimum required to distinguish the users from each other. The authentication
mechanism is dependent to the developer's preference.

1. A user has the following information:
   - Primary ID
   - Email
2. A currency has the following information:
   - Primary ID
   - User ID
   - Code
   - Name
3. An account has the following information:
   - Primary ID
   - Currency ID
   - Name
   - Description (optional)
   - Kind (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Asset
     - Liability
     - Equity
     - Expense
     - Income
4. An event has the following information:
   - Primary ID
   - User ID
   - Name
   - Description (optional)
   - Kind (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Timeout
     - Interval
     - Manual
5. A timeout event has the following information:
   - Primary ID
   - Event ID
   - Expiration date and time
6. An interval event has the following information:
   - Primary ID
   - Event ID
   - Schedule as CRON expression
7. An event started time has the following information:
   - Primary ID
   - Event ID
   - Started time
8. A modifier has the following information:
   - Primary ID
   - Debit Account ID
   - Credit account ID
   - Name
   - Description (optional)
   - Action (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Record
     - Close
   - Kind (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Reactive
     - Dependent
     - Manual Input
9. A reactive modifier has the following special information:
   - Primary ID
   - Modifier ID
   - Event ID
   - Operation
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Adder
     - Multiplier
     - Value
10. A dependent modifier should not loop back to themselves to prevent infinite loop. It has the
    following special information:
    - Primary ID
    - Modifier ID
    - Parent modifier ID
    - Operation
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Adder
      - Multiplier
    - Value
11. A financial entry has the following information:
    - Primary ID
    - Modifier ID
    - Debit Amount
    - Credit Amount
    - Remarks
12. A frozen period has the following information:
    - Primary ID
    - User ID
    - Started date and time
    - Finished date and time
13. A summary calculation has the following information:
    - Primary ID
    - Frozen Period ID
    - Unadjusted Debit Amount
    - Adjusted Debit Amount
    - Unadjusted Credit Amount
    - Adjusted Credit Amount
