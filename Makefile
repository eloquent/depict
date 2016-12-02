test: install
	php --version
	vendor/bin/phpunit --no-coverage

coverage: install
	phpdbg --version
	phpdbg -qrr vendor/bin/phpunit

open-coverage:
	open coverage/index.html

lint: install
	vendor/bin/php-cs-fixer fix

install: vendor/autoload.php

travis: coverage

.PHONY: test coverage open-coverage lint install travis

vendor/autoload.php: composer.lock
	composer install

composer.lock: composer.json
	composer update
