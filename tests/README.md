## Self-updating markdown tests

### Run the tests with mock data

To simply see if tests pass, run them as you normally would.

    docker-compose exec web ./vendor/bin/phpunit tests

When running like this, API responses are simulated, based on pre-recorded request and response data.

### Install nextcloud

Run

    docker-compose exec nextcloud sh /usr/bin/install-nextcloud.sh

Visit http://localhost:8081/ and do final install steps

### Run with a real nextcloud connection

To run with a real nextcloud connection:

    REAL_CLIENT=1 ./vendor/bin/phpunit tests

Note: This only works if a nextcloud instance is installed and available.

Disclaimer: This will cause some noise with auto-increment ids.

### Update fixtures automatically

First make sure that there are no uncommitted changes in git.

Then run this:

    UPDATE_TESTS=1 REAL_CLIENT=1 ./vendor/bin/phpunit tests
    git diff -U20

Review the changes.
Either commit, or discard them and fix the code.

### Creating new test cases

Usually all you need is:
- Copy an existing markdown file.
- Edit the top code section.
- Run `UPDATE_TESTS=1 REAL_CLIENT=1 ./vendor/bin/phpunit tests --filter="[..]"` to fill in all the rest.
- Review the generated output. Commit, or discard and fix.
