FROM php:7.4-cli-alpine


# shadow and sudo to avoid running the container a root
# see:
# https://github.com/nodejs/docker-node/blob/master/docs/BestPractices.md
# https://github.com/mhart/alpine-node/issues/48
#
# alpine-sdk and autoconf to be able to install xdebug

ARG UID
ARG GID

RUN apk add --update --upgrade alpine-sdk autoconf bash curl \
  shadow sudo make && \
  if [ -z "`getent group $GID`" ]; then \
  addgroup -S -g $GID cetacean; \
  else \
  groupmod -n cetacean `getent group $GID | cut -d: -f1`; \
  fi && \
  if [ -z "`getent passwd $UID`" ]; then \
  adduser -S -u $UID -G cetacean -s /bin/sh mobydick; \
  else \
  usermod -l mobydick -g $GID -d /home/mobydick -m `getent passwd $UID | cut -d: -f1`; \
  fi && \
  echo "mobydick ALL=(root) NOPASSWD:ALL" > /etc/sudoers.d/mobydick && \
  chmod 0440 /etc/sudoers.d/mobydick && \
  pecl install xdebug && \
  curl -sS https://getcomposer.org/installer | php && \
  mv composer.phar /usr/local/bin/composer

USER mobydick
WORKDIR /home/mobydick
RUN sudo mkdir /workdir && sudo chown mobydick:cetacean /workdir && \
  composer g require symfony/var-dumper && \
  composer g require psy/psysh:@stable && \
  composer g require friendsofphp/php-cs-fixer && \
  echo 'export PATH=$PATH:~/.composer/vendor/bin' > ~/.bashrc

COPY ./php.ini /usr/local/etc/php/
WORKDIR /workdir
