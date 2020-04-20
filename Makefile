.PHONY: help infra-clean infra-rebuild infra-show-containers infra-show-images \
infra-show-logs infra-stop infra-up run run-watch show-logs test test-watch

SHELL := /bin/bash

.DEFAULT: help # Running Make will run the help target

UID := $(shell id -u)
GID := $(shell id -g)

DOCKER_SERVICE_PHP = php

# wath command
WATCH = ./watch
ARGS ?=

# conditionnal to set variable to be used as prefix for targets aimed at
# being called in a container (or not, as in CI)
ifneq ($(SKIP_DOCKER),true)
	DOCKER_CMD := docker-compose exec -u $(UID) $(DOCKER_SERVICE_PHP)
endif

help: ## to show Help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

########################################
#              INFRA                   #
########################################
infra-clean: ## to stop and remove containers, networks, images
	docker-compose down --rmi all

infra-rebuild: ## to clean and up all
	make infra-clean infra-up

infra-shell-php: ## to open a shell session in the php container
	@$(DOCKER_CMD) bash

infra-show-containers: ## to show all the containers
	docker-compose ps

infra-stop: ## to stop all the containers
	docker-compose stop

infra-up: ## to create and start all the containers
	if [ ! -f .env -a -f .env.dist ]; then sed "s,#UID#,$(UID),g;s,#GID#,$(GID),g" .env.dist > .env; fi
	docker-compose up -d


########################################
#               APP                    #
########################################
run: ## to run the command (make run ARGS='-f testdata/basic.csv -d testdata/basic.conf')
	@$(DOCKER_CMD) src/csv2json $(ARGS)

run-watch: ## to run the command with watcher (make run-watch ARGS='-f testdata/basic.csv -d testdata/basic.conf')
	@$(DOCKER_CMD) $(WATCH) src/csv2json '$(ARGS)'

test: ## to run the unit tests
	@$(DOCKER_CMD) src/unit-test

test-watch: ## to run the unit tests with watcher
	@$(DOCKER_CMD) $(WATCH) src/unit-test

show-logs: ## to show container logs
	@if [ ! -d .logs ]; then mkdir .logs && touch .logs/errors.log; fi
	@$(DOCKER_CMD) tail -f .logs/errors.log
