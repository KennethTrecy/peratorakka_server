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
     - General Equity
     - General Expense
     - General Income
     - Liquid Asset
     - Depreciative Asset
     - General Temporary
     - Itemized Asset
     - Direct Cost
     - Direct Sale
4.  An item info has the following information:
   - Primary ID
   - User ID
   - Name
   - Description
5.  An item configuration has the following information:
    - Primary ID
    - Account ID
    - Item Info ID
    - Valuation Method
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Weighted Average
      - FIFO
      - LIFO
6. An cash flow activity has the following information:
   - Primary ID
   - User ID
   - Name
   - Description
7. An event has the following information:
   - Primary ID
   - User ID
   - Name
   - Description (optional)
   - Kind (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Timeout
     - Interval
     - Manual
8. A timeout event has the following information:
   - Primary ID
   - Event ID
   - Expiration date and time
9. An interval event has the following information:
   - Primary ID
   - Event ID
   - Schedule as CRON expression
10. An event started time has the following information:
   - Primary ID
   - Event ID
   - Started time
11. A modifier has the following information:
   - Primary ID
   - Name
   - Description (optional)
   - Action (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Record
     - Close
     - Exchange
     - Paper Record
     - Bid
     - Ask
     - Transform
     - Throw
     - Catch
     - Condense
     - Dilute
   - Kind (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Reactive
     - Dependent
     - Manual Input
12. A reactive modifier has the following special information:
   - Primary ID
   - Modifier ID
   - Event ID
   - Operation
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Adder
     - Multiplier
     - Value
13. A dependent modifier should not loop back to themselves to prevent infinite loop. It has the
    following special information:
    - Primary ID
    - Modifier ID
    - Parent modifier ID
    - Operation
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Adder
      - Multiplier
    - Value
14. A modifier atom has the following information
    - Primary ID
    - Modifier ID
    - Account ID
    - Field Kind
      - Debit
      - Credit
      - Base
      - Paper
      - Item Count
      - Price
15. A modifier atom activity has the following information:
    - Primary ID
    - Modifier Atom ID
    - Cash Flow Activity ID
16. A financial entry has the following information
    - Primary ID
    - Modifier ID
    - Transaction Date
    - Remarks
17. A financial entry atom has the following information
    - Primary ID
    - Financial Entry ID
    - Modifier Atom ID
    - Numerical Value
18. A frozen period has the following information:
    - Primary ID
    - User ID
    - Started date and time
    - Finished date and time
19. A unadjusted summary calculation has the following information:
    - Primary Hash
    - Frozen Period ID
    - Account ID
    - Unadjusted Debit Amount
    - Unadjusted Credit Amount
20. A adjusted summary calculation has the following information
    - Unadjusted Summary Calculation Hash
    - Opened Debit Amount
    - Closed Debit Amount
    - Opened Credit Amount
    - Closed Credit Amount
21. A flow calculation has the following information:
    - Unadjusted Summary Calculation Hash
    - Cash Flow Activity ID
    - Net amount
22. An item calculation has the following information:
    - Unadjusted Summary Calculation Hash
    - Financial Entry ID
    - Remaining Count
23. A collection has the following information:
    - Primary ID
    - User ID
    - Name
    - Description
24. An account collection has the following information:
    - Primary ID
    - Collection ID
    - Account ID
25. A formula has the following information:
    - Primary ID
    - User ID
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
    - Expression
26. A numerical tool has the following information:
    - Primary ID
    - User ID
    - Name
    - Base Currency
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

### Migration Plan
Next version would have a major upgrade.

#### Migration Plan I
- Make another table for modifier atoms
- Make another table for modifier atom activity
- Convert some columns of modifier to modifier atoms
- Make another table for financial entry atoms
- Convert some columns of financial entry to financial entry atoms
- Make another table for unadjusted summary calculation
- Make another table for adjusted summary calculation
- Convert some columns of summary calculations to respective calculations

#### Migration Plan II
- Delete converted columns of modifier
- Delete converted columns of financial entry

#### Migration Plan III
- Make another table for papered summary calculation
