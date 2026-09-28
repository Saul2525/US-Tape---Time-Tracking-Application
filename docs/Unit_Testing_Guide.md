# US Tape Time Tracking Application

## Testing Guide

## 1. Purpose

This document explains how to set up, run, and maintain the automated tests for the US Tape Time Tracking Application.

The purpose of the automated tests is to verify that important parts of the application behave as expected after changes are made to the code. The tests send HTTP requests to the running PHP application and check the responses and database results.

The testing files are currently maintained on the `ci-testing` branch.

---

## 2. Testing Environment

The automated tests require a working PHP application and database.

The current testing setup uses:

* **PHP 8.5**
* **PHPUnit 13**
* **Composer** for installing the PHPUnit testing dependency
* A local PHP server running the application
* **cURL** for sending HTTP requests to the application
* **MySQL** for the current test database

Since our meeting on 9/21/26, we're in the process of transitioning to **Microsoft SQL Server**.

The tests are designed to run against a test database rather than the application's normal production database.

---

## 3. Test File Structure

The automated tests are located in the `tests/` directory. The shared test helper is located in `test_helpers/`.

The current test files are:

* `tests/AdminTest.php` — Tests administrator access, employee creation, PIN validation, and employee activation/deactivation.
* `tests/ClockHandlerTest.php` — Tests employee clock-in, clock-out, PIN validation, inactive employees, and handling of existing open shifts.
* `tests/CronAnomalyTest.php` — Tests detection of open shifts that have been open for more than 10 hours.
* `tests/EmployeeShiftsTest.php` — Tests displaying employee shifts, date filtering, total-hour calculations, open shifts, and date validation.
* `tests/ExportShiftsTest.php` — Tests export access, employee and date filtering, employee totals, and open shifts.
* `tests/ManagerDashboardTest.php` — Tests dashboard access, employee display, search filtering, and role-specific controls.
* `tests/UpdateShiftTest.php` — Tests editing shifts, approval changes, manager notes, and audit-log entries.
* `test_helpers/BaseWebTest.php` — Provides shared functionality used by the web-based tests.

The testing configuration files are:

* `composer.json` — Defines PHPUnit as a development dependency and provides the command used to run the tests.
* `phpunit.xml.dist` — Contains the default PHPUnit test configuration used by the project.
* `.github/workflows/ci.yml` — Contains the GitHub Actions configuration used to run the automated tests.

---

## 4. Test Structure and Shared Helpers

The automated tests are web-based tests. They send HTTP GET and POST requests to the running PHP application and then check the HTTP response, page contents, and database results.

The tests share common functionality through `test_helpers/BaseWebTest.php`. This prevents each test file from having to repeat the same database, session, and HTTP setup.

### Database Setup

Before each test runs, `BaseWebTest` connects to the MySQL test database using the database settings provided through the test configuration and environment.

For safety, the tests require the database name to be `timeclock_test`. If a different database name is configured, the test fails instead of running.

The test database is cleared before each test by removing existing records from:

* `AUDIT_LOG`
* `WORK_TIMES`
* `EMPLOYEES`

This gives each test a clean starting point.

### Test Data Helpers

`BaseWebTest` provides helper functions for creating test data, including:

* `createEmployee()` — Creates an employee with a specified name, email, and role.
* `createWorkTime()` — Creates a work-time record with specified clock-in, clock-out, approval, and edit values.
* `getEmployeeByEmail()` — Retrieves an employee from the test database by email address.

### Session Helpers

The tests can create simulated logged-in sessions without going through the normal login page.

* `createSession()` — Creates a session with a specified role.
* `createUserSession()` — Creates a session with both a user ID and role.
* `destroySession()` — Removes a test session.

This allows tests for employee, manager, and administrator access without requiring the tests to perform an actual login for every test case.

### HTTP Helpers

The tests use PHP's cURL functionality to communicate with the running application.

* `get()` — Sends a GET request and returns the HTTP status, response headers, and response body.
* `post()` — Sends a POST request and returns the HTTP status and response body.

The test server is configured to run at:

`http://127.0.0.1:8001`

Session IDs can be passed to these helpers so that requests are made as the simulated logged-in user.

---

## 5. Test Setup and Configuration

The following steps describe the setup required to run the automated tests. The PHP application and test database are assumed to already be configured.

### Install Dependencies

When setting up the project for the first time, or after the project dependencies have changed, run the following command from the project directory:

```powershell
composer install
```

This installs PHPUnit and its dependencies defined in `composer.json`.

### PHPUnit Configuration

The project includes `phpunit.xml.dist` as the default PHPUnit configuration.

The file defines the database settings used by the automated tests:

* `DB_NAME` — `timeclock_test`
* `DB_USER` — `root`
* `DB_PASS` — Empty by default
* `DB_HOST` — `127.0.0.1`

A separate `phpunit.xml` file is not required for the standard testing setup. If a different local configuration is needed, `phpunit.xml.dist` can be copied to `phpunit.xml` and modified for that environment.

### Test Database

The automated tests use a separate MySQL database named `timeclock_test`.

The test database must already be configured with the application's database schema before the tests are run.

For safety, `BaseWebTest` checks that `DB_NAME` is exactly `timeclock_test`. If a different database name is detected, the tests stop before modifying any data.

### Start the Test Server

The application must be running before the web-based tests can be executed.

The project provides a Composer script for starting the test server:

```powershell
composer test-server
```

This starts the PHP development server at:

`http://127.0.0.1:8001`

The script also sets `DB_NAME` to `timeclock_test` for the PHP application.

The test server is a long-running process and is expected to remain running while the tests are being executed. Leave the terminal running `composer test-server` open and run the tests from a separate terminal.

If the environment running the command has a process timeout, it may display a message such as:

```text
The process "set DB_NAME=timeclock_test&& php -S 127.0.0.1:8001" exceeded the timeout of 300 seconds.
```

This can occur because the PHP server is designed to continue running rather than exit on its own. A timeout from the environment does not necessarily mean that the PHP server itself failed.

### Run the Tests

In a separate terminal, from the project directory, run:

```powershell
composer test
```

This runs PHPUnit against all test files in the `tests/` directory.

---

## 6. Running Individual Tests

The complete test suite can be run with:

```powershell
composer test
```

This runs all test files in the `tests/` directory.

Individual test files can also be run when testing a specific part of the application. For example:

```powershell
vendor/bin/phpunit tests/AdminTest.php
```

This runs only the tests in `AdminTest.php`.

Other test files can be run in the same way by replacing the filename:

```powershell
vendor/bin/phpunit tests/ClockHandlerTest.php
vendor/bin/phpunit tests/CronAnomalyTest.php
vendor/bin/phpunit tests/EmployeeShiftsTest.php
vendor/bin/phpunit tests/ExportShiftsTest.php
vendor/bin/phpunit tests/ManagerDashboardTest.php
vendor/bin/phpunit tests/UpdateShiftTest.php
```

Running an individual test file can be useful when making changes to a specific part of the application. After making changes, the complete test suite should also be run to check that the changes did not affect other parts of the application.

---

## 7. Understanding Test Results

After running the tests, PHPUnit displays the results in the terminal.

A successful test run will show that all tests passed. The output also includes the number of tests that were run and the time required to complete them.

When a test does not pass, PHPUnit identifies the test that failed and provides information about the failure. The test name and file location can be used to determine which part of the application needs to be investigated.

Common PHPUnit result indicators include:

* `.` — Test passed.
* `F` — Test failed because an expected result did not match the actual result.
* `E` — Test encountered an error while running.
* `S` — Test was skipped.

A failed test does not necessarily mean that the test itself is incorrect. The failure may indicate a change in the application, database, test environment, or expected behavior.

When a test fails, review the PHPUnit output first to identify the test and the reported error before making changes to the test or application.

---

## 8. Test Database Behavior

The automated tests use the `timeclock_test` database so that test data is kept separate from the application's normal database.

Before each test runs, `BaseWebTest` removes existing records from the following tables:

* `AUDIT_LOG`
* `WORK_TIMES`
* `EMPLOYEES`

This gives each test a clean database state and prevents data created by one test from affecting another test.

The tests then create the employees and work-time records needed for each test.

Because the tests modify and delete database records, **the tests must only be run against the `timeclock_test` database**. `BaseWebTest` checks the configured database name before each test and stops the test if the database is not named `timeclock_test`.

The test database should therefore be treated as temporary test data rather than a database containing production information.

---

## 9. When to Add or Modify Tests

Tests should be added or modified when a change to the application introduces, changes, or fixes behavior that should be verified automatically.

### Add a Test When

A new test should generally be added when:

* A new application feature is introduced.
* A new user action or workflow is added.
* A new validation rule is introduced.
* A new role or permission affects application behavior.
* A new database operation or type of data needs to be verified.
* A bug is fixed and a test can be added to prevent the same problem from returning.

For example, if a new employee-management feature is added, tests should verify the expected behavior of that feature rather than relying only on manual testing.

### Modify an Existing Test When

An existing test should be modified when application behavior that the test already covers is intentionally changed.

The test should be updated to reflect the new expected behavior. If the existing test still represents the intended behavior, it should not be changed simply to make a failing test pass.

### After Adding or Modifying Tests

After adding or modifying a test:

1. Run the relevant test file.
2. Run the complete test suite with `composer test`.
3. Review the PHPUnit results for failures or errors.
4. If the changes are committed to the `ci-testing` branch, GitHub Actions will run the automated test workflow.

---

## 10. GitHub Actions and Continuous Integration

The project uses GitHub Actions to automatically run the automated test suite in a separate testing environment.

The workflow is defined in:

`.github/workflows/ci.yml`

The workflow currently runs when:

* Code is pushed to the `ci-testing` branch.
* A pull request is opened or updated for the `main` branch.

The GitHub Actions environment creates a MySQL 8.0 test database named `timeclock_test`, installs PHP 8.5 and the required PHP extensions, installs the Composer dependencies, and initializes the database using `db.sql`.

The workflow then starts the PHP application on:

`http://127.0.0.1:8001`

and runs:

```powershell id="d1e4c7"
composer test
```

This allows the automated tests to be run in a consistent environment instead of relying only on a developer's local computer.

If the GitHub Actions workflow fails, the workflow results should be reviewed to determine whether the failure occurred during environment setup or while running the PHPUnit tests.

---

## 11. Troubleshooting

The following issues may occur when setting up or running the automated tests.

### `vendor/bin/phpunit` Not Found

If PHPUnit cannot be found, the Composer dependencies may not have been installed.

Run:

```powershell
composer install
```

Then try running the tests again.

### Database Connection Error

If the tests cannot connect to the database, verify that the MySQL server is running and that the test database is available.

The tests are configured to use:

* Database: `timeclock_test`
* Host: `127.0.0.1`
* User: `root`
* Password: Empty by default

### Tests Report the Wrong Database

If a test reports that it must use the `timeclock_test` database, check the database configuration in `phpunit.xml.dist` or any local `phpunit.xml` configuration.

The tests will not run against a database with a different name.

### Connection Refused on Port 8001

If the tests cannot connect to `127.0.0.1:8001`, make sure the PHP test server is running.

Start it with:

```powershell
composer test-server
```

Keep that terminal open and run `composer test` from a separate terminal.

### Test Server Timeout

The `composer test-server` command starts a long-running PHP server and is expected to remain active.

Some environments may report:

```text
The process "set DB_NAME=timeclock_test&& php -S 127.0.0.1:8001" exceeded the timeout of 300 seconds.
```

This can occur because the server continues running instead of exiting. The message does not necessarily indicate that the PHP server failed.

### PHPUnit Test Failures

If PHPUnit reports a failed test, review the test name and error message in the output.

Determine whether the failure is caused by:

* An intentional change in application behavior.
* A bug in the application.
* A problem with the test or its expected result.
* A database or testing environment problem.

If the application behavior was intentionally changed, update the affected test when appropriate and run the complete test suite again.