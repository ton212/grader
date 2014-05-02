#!/bin/sh
# Build trusty image
# http://docs.docker.io/en/latest/use/baseimages/
sudo debootstrap trusty trusty http://mirror1.ku.ac.th/ubuntu/
sudo tar -C trusty -c . | sudo docker import - trusty
