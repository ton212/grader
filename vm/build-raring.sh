#!/bin/sh
# Build raring image
# http://docs.docker.io/en/latest/use/baseimages/
sudo debootstrap raring raring http://mirror1.ku.ac.th/ubuntu/
sudo tar -C raring -c . | sudo docker import - raring