# HR Rules

## Responsibilities

Employees

Departments

Organization Units

Contracts

Employment Orders

Documents

---

## Employee

One Active Employment.

Multiple Historical Contracts.

---

## Organization

Tree Structure.

---

## Contracts

Permanent

Temporary

Hourly

Daily

---

## Employee Documents

Unlimited.

Versioned.

---

## Permissions

Employees group:

- `employees.view` / `employees.manage`
- `employees.create` / `employees.update` / `employees.delete`

HR group:

- `hr.view` / `hr.manage`
- `hr.organization.manage` / `hr.documents.manage`
- `employment-orders.view` / `employment-orders.create` / `employment-orders.update` / `employment-orders.delete` / `employment-orders.approve`
- `contracts.view` / `contracts.create` / `contracts.update` / `contracts.delete`

Worklogs group:

- `worklogs.view` / `worklogs.manage`
- `worklogs.create` / `worklogs.update` / `worklogs.delete` / `worklogs.setup.manage`

Attendance group remains separate under حضور و غیاب.

`hr.manage`, `employees.manage`, and `worklogs.manage` imply their granular action permissions.

---

## Forbidden

Delete Employee History

Delete Approved Contract