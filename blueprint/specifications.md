# Specifications
These are set of sections containing the specific details derived from the general requirements.

## Database Specifications
This section contains details of every entity in the system. Each entity is assumed to have
timestamps and can be soft-deleted.

If there are numerical values denoting the financial amount, they should be string to retain the accuracy of the values.

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
	- Account ID
	- Opposite account ID
	- Name
	- Description (optional)
	- Result Side
		- Debit
		- Credit
	- Kind
		- Reactive
		- Dependent
		- Manual Input
9. A reactive modifier has the following special information:
	- Primary ID
	- Modifier ID
	- Event ID
	- Operation
		- Adder
		- Multiplier
	- Value
10. A dependent modifier should not loop back to themselves to prevent infinite loop. It has the following special information:
	- Primary ID
	- Modifier ID
	- Parent modifier ID
	- Operation
		- Adder
		- Multiplier
	- Value
11. A financial entry is independent as it contains only common contents which are the following:
	- Primary ID
	- Debit Account ID
	- Credit Account ID
	- Debit Amount
	- Credit Amount
	- Remarks
