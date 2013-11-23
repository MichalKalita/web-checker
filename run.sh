#! /bin/bash

while [ true ]
do
  echo "START at `date`"
  php ./www/index.php "$@"
  echo "STOP at `date`"
  sleep 5
done
