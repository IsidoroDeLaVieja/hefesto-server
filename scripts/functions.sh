#!/bin/bash

param_or_die()
{
    if [ -z "$2" ] 
    then
        echo $1
        exit
    fi
}

generate_nginx_virtualhost()
{
    SOURCE=$1
    VIRTUAL_HOST=$2
    GENERATE_CERT=$3

    cp $SOURCE/laradock/nginx/sites/default.conf.example $SOURCE/laradock/nginx/sites/$VIRTUAL_HOST.conf
    sed -i "s/#domain#/$VIRTUAL_HOST/g" $SOURCE/laradock/nginx/sites/$VIRTUAL_HOST.conf
    docker exec hefesto_nginx_1 nginx -s reload
    if [ "$GENERATE_CERT" == "generatecert" ]; then
        docker exec -it hefesto_nginx_1 certbot --nginx -d $VIRTUAL_HOST
    fi
}