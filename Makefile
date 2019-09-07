#!/usr/bin/make -f

.PHONY: server

SERVER_PORT := 8080

# ---------------------------------------------------------------------

server:
	php -S 0.0.0.0:${SERVER_PORT} -t public
