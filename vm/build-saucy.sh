#!/bin/sh
# Build saucy image
# http://docs.docker.io/en/latest/use/baseimages/
sudo debootstrap saucy saucy http://mirror1.ku.ac.th/ubuntu/
sudo tar -C saucy -c . | sudo docker import - saucy