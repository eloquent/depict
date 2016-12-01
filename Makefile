test: install
	php --version
	vendor/bin/peridot

coverage: install
	phpdbg --version
	phpdbg -qrr vendor/bin/peridot --reporter html-code-coverage --code-coverage-path coverage

open-coverage:
	open coverage/index.html

lint: install
	vendor/bin/php-cs-fixer fix

install: vendor/autoload.php

travis: test
	phpdbg -qrr vendor/bin/peridot --reporter clover-code-coverage --code-coverage-path coverage
	bash <(curl -s https://codecov.io/bash)

.PHONY: test coverage open-coverage lint install travis

vendor/autoload.php: composer.lock
	composer install

composer.lock: composer.json
	composer update
