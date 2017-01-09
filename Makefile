test: install
	php --version
	vendor/bin/phpunit --no-coverage

coverage: install
	phpdbg --version
	phpdbg -qrr vendor/bin/phpunit

open-coverage:
	open coverage/index.html

benchmarks:
	vendor/bin/athletic -p test/benchmarks

lint: test/bin/php-cs-fixer
	test/bin/php-cs-fixer fix --using-cache no

install: vendor/autoload.php

travis: install
ifeq ($(TRAVIS_PHP_VERSION), $(filter $(TRAVIS_PHP_VERSION), 7.0 7.1))
	make coverage
else
	phpenv config-add xdebug.ini || true
	vendor/bin/phpunit
endif

.PHONY: test coverage open-coverage lint install travis

vendor/autoload.php: composer.lock
	composer install

composer.lock: composer.json
	composer update

test/bin/php-cs-fixer:
	curl -sSL https://github.com/FriendsOfPHP/PHP-CS-Fixer/releases/download/v2.0.0/php-cs-fixer.phar -o test/bin/php-cs-fixer
	chmod +x test/bin/php-cs-fixer
