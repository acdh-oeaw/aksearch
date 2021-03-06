#!/bin/bash

###############################################################################
# Import Data from Alma Dump into AKSearch
#  + Stp 1: unpack dump
#  + Stp 2: run AkImporter
#  + Stp 3: clean Up

now=`date +'%Y-%m-%d'`
start=`date +'%s'`
count=`find /var/alma/* -maxdepth 0 -print| wc -l`

# just in case it's still there...
rm -r /var/www/akimporter/data_unpacked
mkdir /var/www/akimporter/data_unpacked

#  + Stp 1: unpack dump
cd /var/alma
i=1

for entry in * ; do
  [[ $entry == *"*" ]] && continue
  echo "*****************************************"
  echo "unpacking $i out of $count : $entry"
  tar -zxf $entry -C /var/www/akimporter/data_unpacked
  ((i++))
done

# because the unpacked files have the wrong permissions
chmod -R 755 /var/www/akimporter/data_unpacked

# because the importer crashes upon ingest of deleted record headers
cd /var/www/akimporter/data_unpacked
grep -iRl "<header status=\"deleted\">" ./  | xargs rm

#  + Stp 2: run AkImporter
cd /var/www/akimporter/
# full indexing
[[ $count > 0 ]] && java -jar AkImporter.jar -p -v -o
# reindexing of updates
# [[ $count > 0 ]] && java -jar AkImporter.jar -R -v -o
# fetch, consolidate, update (broken as is!)
# [[ $count > 0 ]] && java -jar AkImporter.jar -u -v -o


#  + Stp 3: clean Up
rm -r /var/www/akimporter/data_unpacked
rm -r /var/alma/*
