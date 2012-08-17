#! /bin/sh

# monido -e build.sh src

if [ -f out/css/bootstrap.css ]; then
  echo Bootstrap exists.
else
  echo Compiling bootstrap.
  mkdir -p out/css
  mkdir out/images
  lessc boostrap/less/bootstrap.less > out/css/bootstrap.css
fi

echo Moving images

cp -r images/* out/images
lmxml $1 > out/index.html
