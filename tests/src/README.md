# Term MAnager unit tests.

### Required env variables.

`MYSQL_DATABASE`

`SIMPLETEST_BASE_URL`

`SYMFONY_DEPRECATIONS_HELPER=disabled` (This is optional but hides deprecated messages from other modules).

### Run the test
From within the `web` directory:
`../vendor/bin/phpunit -c core modules/contrib/dennis_term_manager/tests/src/  @group polaris`

