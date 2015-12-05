#!/usr/bin/env bash
mkdir tzdata
cd tzdata
wget --retr-symlinks 'ftp://ftp.iana.org/tz/tzdata-latest.tar.gz'
tar xfvz tzdata-latest.tar.gz
