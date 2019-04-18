#!/bin/bash

# gdrive configuration
#http://olivermarshall.net/how-to-upload-a-file-to-google-drive-from-the-command-line/

# dir where script is
DIR="$(cd "$(dirname "$0")" && pwd -P)"

DATE=`date '+%Y-%m-%d-%H-%M-%S'`
TODAY_YMD=`date +%Y-%m-%d`
STORAGE=$DIR/storage
FILE=$STORAGE/$TODAY_YMD.sql
TARGZ=$FILE.tar.gz
PAST_15_DAYS=$(date --date="${TODAY_YMD} - 15 day" +%s)
COUNTER=0
FILE_ID=""

# source .env file
source $DIR/../../.env;

# create file
docker exec $DB_CONTAINER /usr/bin/mysqldump -u $DB_ROOT_USER --password=$DB_ROOT_PASS $DB_DATABASE > $FILE

tar -cvzf $STORAGE/$TODAY_YMD.sql.tar.gz $STORAGE/$TODAY_YMD.sql

rm -f $FILE

echo `/usr/local/bin/gdrive upload $TARGZ --parent $GDRIVE_DIR_BACKUP_ID`

# delete files
for i in `ls -pt $STORAGE/*.sql.tar.gz | tail -n+10`;
    do
        if [ -f $i ] ; then
            rm $i
        fi
done;

#list every file inside the dev/live backup directory
for j in `gdrive list --no-header --query "trashed = false and parents in '$GDRIVE_DIR_BACKUP_ID'"`;
    #returns
    #1 - FILE_ID
    #2 - Name
    #3 - Type
    #4 - Size
    #5 - Size multiplier (kb/mb/gb)
    #6 - Created (Y-m-d)
    #7 - Created (H-m-s)
    
    do
        COUNTER=$((COUNTER+1));
        if [ $COUNTER -eq 1 ] ; then
            FILE_ID=$j
        fi
        
        if [ $COUNTER -eq 6 ] ; then
            #Check if the api call worked
            if [ $j != "Error" ] ; then
                FILE_DATE=$(date --date="${j}" +%s)
                #Delete the file if it is older than 15 days
                if [ $FILE_DATE -lt $PAST_15_DAYS ] ; then
                    echo `gdrive delete $FILE_ID`
                fi
            else
                echo "API calls exceeded."
            fi
        fi
        
        if [ $COUNTER -eq 7 ] ; then
            COUNTER=0
        fi
done;
