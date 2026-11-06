#!/bin/bash
S=${ABP_WORK:?set ABP_WORK to a work folder (OpenCart sources + test shops)}
$(dirname "$0")/run21oc4.sh
echo "-----"
$(dirname "$0")/run21oc3.sh
