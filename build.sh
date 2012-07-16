#! /bin/sh

if [ -f out/css/bootstrap.css ]
  echo Bootstrap exists.
then
  echo Compiling bootstrap.
  mkdir -p out/css
  lessc bootstrap/less/bootstrap.less > out/css/bootstrap.css
fi

lmxml $1 > out/index.html
