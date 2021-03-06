FROM quay.io/cuppett/ubi8-php:74

ENV PHP_MEMORY_LIMIT="512M" \
    OPCACHE_REVALIDATE_FREQ="1" \
    OPCACHE_MAX_FILES="10000" \
    PHP_OPCACHE_REVALIDATE_FREQ="1" \
    PHP_OPCACHE_MAX_ACCELERATED_FILES="10000"

USER root

# Copying in source code

COPY --chown=1001:0 ./ /tmp/src/

# Run assemble as non-root user

USER 1001

# Assemble script sourced from builder image based on user input or image metadata.

#RUN /usr/libexec/s2i/assemble
RUN /tmp/src/.s2i/bin/assemble

# Run script sourced from builder image based on user input or image metadata.

#CMD /usr/libexec/s2i/run
CMD /opt/app-root/src/.s2i/bin/run
