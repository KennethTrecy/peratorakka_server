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
2. A ticker has the following information:
   - Primary ID
   - User ID
   - Code
   - Name
3. An account has the following information:
   - Primary ID
   - Ticker ID
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
     - Itemized Asset
     - Paper Gain
     - Paper Loss
     - Revenue
     - Loss
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
9. A modifier has the following information:
   - Primary ID
   - Name
   - Description (optional)
   - Action (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Record
     - Close
     - Exchange
     - Fold
     - Bid
     - Ask
     - Transform
     - Throw
     - Catch
     - Enrich
     - Dilute
   - Kind (enumeration)
     - Unknown (to represent kinds in later versions in case the user downgraded)
     - Reactive
     - Dependent
     - Manual Input
10. A modifier atom has the following information
    - Primary ID
    - Modifier ID
    - Account ID
    - Field Kind
      - Debit
      - Credit
      - Item
11. A modifier atom activity has the following information:
    - Primary ID
    - Modifier Atom ID
    - Cash Flow Activity ID
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
14. A financial entry has the following information
    - Primary ID
    - User ID
    - Transaction Date
    - Remarks
15. A frozen period has the following information:
    - Primary ID
    - User ID
    - Started date and time
    - Finished date and time
16. A financial entry atom has the following information
    - Primary ID
    - Financial Entry ID
    - Modifier Atom ID
    - Amount
17. A unadjusted summary calculation has the following information:
    - Primary ID
    - Frozen Period ID
    - Account ID
    - Unadjusted Debit Amount
    - Unadjusted Credit Amount
18. A adjusted summary calculation has the following information
    - Primary ID
    - Frozen Period ID
    - Account ID
    - Opened Debit Amount
    - Closed Debit Amount
    - Opened Credit Amount
    - Closed Credit Amount
19. A papered summary calculation has the following information
    - Primary ID
    - Frozen Period ID
    - Account ID
    - Opened Debit Amount
    - Unadjusted Debit Amount
    - Closed Debit Amount
    - Opened Debit Amount
    - Unadjusted Credit Amount
    - Closed Credit Amount
20. A flow calculation has the following information:
    - Primary ID
    - Frozen Period ID
    - Cash Flow Activity ID
    - Account ID
    - Net amount
21. A collection has the following information:
    - Primary ID
    - User ID
    - Name
    - Description
22. An account collection has the following information:
    - Primary ID
    - Collection ID
    - Account ID
23. A formula has the following information:
    - Primary ID
    - Ticker ID
    - Name
    - Description
    - Output Format
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Raw
      - Percentage
      - Ticker
    - Exchange Rate Basis
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Periodic
      - Latest
    - Presentational Precision
    - Formula
24. A numerical tool has the following information:
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
25. An itemized configuration has the following information:
    - Primary ID
    - Ticker ID
    - Valuation Method
      - Unknown (to represent kinds in later versions in case the user downgraded)
      - Weighted Average
      - FIFO
      - LIFO
26. An item count entry has the following information:
    - Primary ID
    - Financial Entry Atom ID
    - Count
27. A inventory calculation entry has the following information:
    - Primary ID
    - Summary Calculation ID
    - Issued Date
    - Remaining Count
    - Cost

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
