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
	- User ID
	- Currency ID
	- Name
	- Description (optional)
	- Ownership (enumeration)
		- Asset
		- Liability
4. A monthly account value has the following information:
	- Primary ID
	- Account ID
	- Start month and year
	- Value
5. An event has the following information:
	- Primary ID
	- User ID
	- Name
	- Description (optional)
	- Kind (enumeration)
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
	- Account ID
	- Name
	- Description (optional)
	- Kind
		- Reactive
		- Dependent
		- Manual Input
10. A reactive modifier has the following special information:
	- Primary ID
	- Modifier ID
	- Event ID
	- Operation
		- Adder
		- Multiplier
	- Value
11. A dependent modifier should not loop back to themselves to prevent infinite loop. It has the following special information:
	- Primary ID
	- Modifier ID
	- Parent modifier ID
	- Operation
		- Adder
		- Multiplier
	- Value
12. A financial entry is independent as it contains only common contents which are the following:
	- Primary ID
	- Entry kind
		- Transfer
		- Modification
	- Remarks
13. Transfer entry has the following information:
	- Primary ID
	- Financial Entry ID
	- Source Account ID
	- Destination Account ID
	- Source Amount
	- Destination Amount
14. Modification entry has the following information:
	- Primary ID
	- Financial Entry ID
	- Modifier ID
	- Modified Amount
