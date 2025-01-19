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
   - Presentational Precision
3. An account has the following information:
   - Primary ID
   - Currency ID
   - Name
   - Description (optional)
   - Kind (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - General Asset
     - Liability
     - Equity
     - Expense
     - Income
     - Liquid Asset
     - Depreciative Asset
4. An cash flow activity has the following information:
   - Primary ID
   - User ID
   - Name
   - Description
5. An event has the following information:
   - Primary ID
   - User ID
   - Name
   - Description (optional)
   - Kind (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Timeout
     - Interval
     - Manual
6. A timeout event has the following information:
   - Primary ID
   - Event ID
   - Expiration date and time
7. An interval event has the following information:
   - Primary ID
   - Event ID
   - Schedule as CRON expression
8. An event started time has the following information:
   - Primary ID
   - Event ID
   - Started time
9.  A modifier has the following information:
   - Primary ID
   - Debit Cash Flow Activity ID
   - Debit Account ID
   - Credit Cash Flow Activity ID
   - Credit account ID
   - Name
   - Description (optional)
   - Action (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Record
     - Close
     - Exchange
   - Kind (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Reactive
     - Dependent
     - Manual Input
10. A reactive modifier has the following special information:
   - Primary ID
   - Modifier ID
   - Event ID
   - Operation
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Adder
     - Multiplier
     - Value
11. A dependent modifier should not loop back to themselves to prevent infinite loop. It has the
    following special information:
    - Primary ID
    - Modifier ID
    - Parent modifier ID
    - Operation
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Adder
      - Multiplier
    - Value
12. A financial entry has the following information:
    - Primary ID
    - Modifier ID
    - Transaction Date
    - Debit Amount
    - Credit Amount
    - Remarks
13. A frozen period has the following information:
    - Primary ID
    - User ID
    - Started date and time
    - Finished date and time
14. A summary calculation has the following information:
    - Primary ID
    - Frozen Period ID
    - Account ID
    - Opened Debit Amount
    - Unadjusted Debit Amount
    - Closed Debit Amount
    - Opened Credit Amount
    - Unadjusted Credit Amount
    - Closed Credit Amount
15. A flow calculation has the following information:
    - Primary ID
    - Frozen Period ID
    - Cash Flow Activity ID
    - Account ID
    - Net amount
16. A collection has the following information:
    - Primary ID
    - User ID
    - Name
    - Description
17. An account collection has the following information:
    - Primary ID
    - Collection ID
    - Account ID
18. A formula has the following information:
    - Primary ID
    - Currency ID
    - Name
    - Description
    - Output Format
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Raw
      - Percentage
      - Currency
    - Exchange Rate Basis
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Periodic
      - Latest
    - Presentational Precision
    - Formula
19. A numerical tool has the following information:
    - Primary ID
    - User ID
    - Name
    - Kind
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Number
      - Pie
      - Line
    - Recurrence
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Periodic
      - Yearly
    - Recency
    - Order
    - Notes
    - Configuration
