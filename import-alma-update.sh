#!/bin/bash

#  + Stp 1: unpack dump
cd /var/www/akimporter/data_update/original

for import in * ; do
  [[ $import == *"*" ]] && continue
  #extract files
  i=1
  mkdir /var/www/akimporter/data_update/extracted/$import
  cd /var/www/akimporter/data_update/original/$import
  for entry in * ; do
    [[ $entry == *"*" ]] && continue
    echo "*****************************************"
    echo "unpacking $i in $import : $entry"
    tar -zxf $entry -C /var/www/akimporter/data_update/extracted/$import
    ((i++))
  done
  chmod -R 755 /var/www/akimporter/data_update/extracted/$import
  #merge files - needs to be done with xml parser
  i=1
  mkdir /var/www/akimporter/data_update/merged/$import
  cd /var/www/akimporter/data_update/extracted/$import
  cat > /var/www/akimporter/data_update/merged/$import/$import.xml
  for file in * ; do
    [[ $file == *"*" ]] && continue
    echo "*****************************************"
    echo "merging $i in $import : $entry"
    cat $file >> /var/www/akimporter/data_update/merged/$import/$import.xml
    ((i++))
  done
done

# because the unpacked files have the wrong permissions
# chmod -R 755 /var/www/akimporter/data_unpacked

#  + Stp 2: run AkImporter
# cd /var/www/akimporter/
# [[ $count > 0 ]] && java -jar AkImporter.jar -R -v -o
# [[ $count > 0 ]] && java -jar AkImporter.jar -u -v -o


#  + Stp 3: clean Up
# rm -r /var/www/akimporter/data_unpacked
# rm -r /var/alma/*
