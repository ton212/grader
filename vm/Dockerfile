# grader
# Dockerfile for grading VM

FROM raring
MAINTAINER whs "manatsawin@gmail.com"

ENV MIRROR "http://mirror1.ku.ac.th/ubuntu/"

RUN echo "deb $MIRROR raring main universe" > /etc/apt/sources.list
RUN echo "deb $MIRROR raring-updates main universe" >> /etc/apt/sources.list
RUN echo "deb $MIRROR raring-backports main universe" >> /etc/apt/sources.list
RUN echo "deb $MIRROR raring-security main universe" >> /etc/apt/sources.list
RUN apt-get update

# in order:
# C/C++, C#, Java, Ruby, Python, Python3, PHP5, JavaScript
RUN apt-get install -y --no-install-recommends build-essential mono-gmcs default-jdk ruby1.9.1 python python3 php5-cli nodejs pypy

RUN apt-get clean
RUN mkdir /grader
RUN chmod 751 /grader
RUN chmod 750 /tmp