#!/bin/bash

# gdrive configuration
#http://olivermarshall.net/how-to-upload-a-file-to-google-drive-from-the-command-line/

if [ "$(date +\%d -d tomorrow)" = "01" ] ; then
    # dir where script is
    DIR="$(cd "$(dirname "$0")" && pwd -P)"

    DATE=`date '+%Y-%m-%d-%H-%M-%S'`
    TODAY_YMD=`date +%Y-%m-%d`
    STORAGE=$DIR/storage
    FILE=$STORAGE/${TODAY_YMD}_monthly.sql
    TARGZ=$FILE.tar.gz

    # source .env file
    source $DIR/../../.env;

    # create file
    mysqldump -u $DB_ROOT_USER --password=$DB_ROOT_PASS $DB_DATABASE > $FILE

    tar -cvzf $STORAGE/${TODAY_YMD}_monthly.sql.tar.gz $FILE

    rm -f $FILE

    echo `/usr/local/bin/gdrive upload $TARGZ --parent $GDRIVE_DIR_BACKUP_ID`

    # delete files
    for i in `ls -pt $STORAGE/*.sql.tar.gz`;
        do
            if [ -f $i ] ; then
                rm $i
            fi
    done;
else
    echo "Not last day of month"
fi
