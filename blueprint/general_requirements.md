# General Requirements
The ones below are requirements that were thought of in the past before this
repository was created and will be implemented by the system.

## Main Requirements
The requirements below must be implemented as soon as possible.
1. It can handle multiple users, but it is intended to be used by one user.
2. A user may input a currency that they would use for financial entries.
3. Available currencies are exclusive to the user who created them. It has the
   following implications:
   1. Same currency info may appear on multiple users.
   2. For privacy purposes, it prevents leaking the information which
      currencies that other users have.
4. A currency should be archived rather than deleted if there is an associated
   financial entry.
5. A user may have multiple cash flow activity to categorized cash flows for
   cash flow statement.
6. A user may create events that are either scheduled (timeout or interval) or
   manually triggered.
7. A user may create an account to be associated on financial entries.
8. An account has associated currency that can only be set once.
9. An account may have multiple modifiers.
10. A modifier may be invoked after the results of other modifiers, react on
    an event, or manual input.
11. A modifier requires an account pair to represent the sides. One would be
    in the debit side and the other is in the credit side. Cash flow activity
    is required for illiquid accounts.
12. An automated modifier may have only one of the following operation:
    - Adder
    - Multiplier
13. Automated modifiers should only have account pairs that has the same
    currency for simplicity. Modifiers with account pairs from different
    currencies may be useful with dependent modifiers having a multiplier
    operation.
14. An event can be deleted as long as it has no associated modifier.
15. A user may have multiple frozen periods.
16. Each frozen period have associated summarized calculation for each
    account.
    - Unadjusted total would represent the result before including the closing
      financial entries in calculation.
    - Closed total would represent the result after including the closing
      financial entries.
17. If a financial entry is within a frozen period, it cannot be modified or
    removed.
18. If a modifier has an account pair with different currencies, it may have
    an exchange action. This action creates exchange entries.
19. Exchange entries has the same properties as record entries with several
    additional purposes:
    - They balance the accounts on different currencies.
    - They become reference for exchange rate.
20. A user may have multiple collections. Each collection may have multiple
    accounts.
21. A user may have multiple formulae.
22. A formula indicates the base currency to consider in calculation and
    exchange rate method in case one of the values are in other currencies.
23. A formula indicates the output type which may be a raw number, a
    percentage, or an amount in base currency.
24. A user may have multiple numerical tools. The sources of data may come
    from collections and/or formulae.
25. If the source of numerical tool is a collection, user must indicate the
    base currency, and exchange rate mechanism, which stage of the amount to
    select (opened, unadjusted, or closed), and which side of amount to select
    (debit or credit).
26. A collection source must instruct to show one or more of the following:
    - Individual amounts of every account
    - Sum of the collection
    - Average of the collection
27. Numerical tools instructs the following:
    - Frequency of data (recurrence like yearly or monthly)
    - Age of data to show (recency like "how many years to show?" for yearly
      recurrence or "how many months to show?" for monthly recurrence)
    - Order (likeliness to show first in the dashboard when tool is rendered)
