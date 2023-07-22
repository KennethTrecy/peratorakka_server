# General Requirements
The ones below are requirements that were thought of in the past before this repository was created
and will be implemented by the system.

## Main Requirements
The requirements below must be implemented as soon as possible.
1. It can handle multiple users, but it is intended to be used by one user.
2. A user may input a currency that they would use for financial entries.
3. Available currencies are exclusive to the user who created them. It has the following implications:
	1. Same currency info may appear on multiple users.
	2. For privacy purposes, it prevents leaking the information which currencies that other users have.
4. A currency should be archived rather than deleted if there is an associated financial entry.
5. A user may create events that are either scheduled (timeout or interval) or manually triggered.
6. A user may create an account to be associated on financial entries.
7. An account has associated currency that can only be set once.
8. An account may have multiple modifiers.
9. A modifier may be invoked after the results of other modifiers, react on an event, or manual input.
11. An automated modifier can be one of the following operation:
	- Adder
	- Multiplier
12. The result of modifier has a side, either debit or credit. Therefore, it needs an opposite
    account too.
13. An event can be deleted as long as it has no associated modifier.
