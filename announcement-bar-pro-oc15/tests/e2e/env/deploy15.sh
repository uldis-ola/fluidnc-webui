#!/bin/bash
# deploy15.sh <shop dir name> — copies the kit's upload/ into a local test shop (FTP-style install) and clears vqcache
E=/c/Users/Admin/abp-testenv
K=/c/Users/Admin/fluidnc-webui/announcement-bar-pro-oc15/upload
W=$E/shops/$1
cp -r $K/. $W/
rm -f $W/vqmod/vqcache/vq2-* $W/vqmod/mods.cache $W/vqmod/checked.cache 2>/dev/null
echo deployed to $1
